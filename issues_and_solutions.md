# n8n Workflows: Comprehensive Issues, Risks & Solutions Report

This report outlines all identified architectural, security, scalability, and runtime issues in the **CRTT Neo** n8n workflow ecosystem, along with concrete, step-by-step technical solutions.

---

## 📋 Summary of Issues

| # | Issue Category | Severity | Impact | Primary Affected Workflows |
| :- | :--- | :--- | :--- | :--- |
| 1 | **Order History Scalability & Unindexed Filtering** | 🔴 **High** | Customers cannot see past bookings once the store exceeds 100 global orders. | `CRTT - Order History API` |
| 2 | **Hardcoded Environment URLs** | 🔴 **High** | Production deployment routes live traffic to the staging/dev server. | All workflows with HTTP nodes |
| 3 | **Google Sheets Cart Session Concurrency** | 🔴 **High** | Race conditions & high latency cause lost cart items and broken sessions. | Cart operations, Checkout, Neo agent |
| 4 | **Google Calendar Sync Pagination & Polling Delay** | 🟡 **Medium-High** | Hourly delay in calendar updates; paid orders falling past page 1 are never synced. | `CRTT - Sync Paid Orders to Google Calendar` |
| 5 | **Google Sheets API Rate Limits (429 Quota)** | 🟡 **Medium-High** | Concurrent user chats hit Google Sheets write quotas (60 req/min), crashing sessions. | `CRTT - Neo`, Cart APIs |
| 6 | **Lack of Fail-Grace Error Handling** | 🟡 **Medium** | Uncaught HTTP errors cause workflows to abort; AI agent crashes instead of explaining issues. | Checkout, Cart APIs |
| 7 | **Hardcoded Credentials, Sheet & Calendar IDs** | 🟡 **Medium** | Insecure exports; configuration drift between staging and production instances. | `CRTT - Neo`, Cart APIs, Calendar workflows |
| 8 | **Placeholder Total Price ($5,000)** | 🟡 **Medium** | AI agent quotes inaccurate $5,000 total price to users in conversation. | `Add Tour to Cart`, `Add Boat Charter to Cart` |
| 9 | **Boat Charter Availability Regex Fragility** | 🟢 **Low-Medium** | Booking conflicts may be missed if event descriptions vary in format, causing double-bookings. | `CRTT - Check Boat Charter Availability` |
| 10 | **State Code Normalization & Accent Matching** | 🟢 **Low-Medium** | Diacritics/accents (e.g., "San José") cause false validation failures during checkout. | `CRTT - Checkout API` |

---

## 🔍 Detailed Analysis & Solutions

### 1. Order History Scalability & Unindexed Filtering

#### 🛑 The Problem
`CRTT - Order History API` requests `GET /wp-json/wc/v3/orders?per_page=100&orderby=date&order=desc`.
In the following Code node (`Filter & Clean Orders`), it runs:
```javascript
const emailLower = (email || '').trim().toLowerCase();
const matched = orders.filter(o => (o.billing?.email || '').trim().toLowerCase() === emailLower);
```
It fetches the **100 most recent orders globally across all customers**, then filters them in memory.
Once the store receives more than 100 orders total, any order placed by a customer that is older than the 100th most recent store order will be completely missed, and the user will falsely be told: *"No orders were found for this email."*

#### 💡 The Solution
Pass the email directly to the WooCommerce REST API using the `search` query parameter:
1. In the `Fetch Orders from WooCommerce` HTTP Request node:
   * Keep URL: `{{ $vars.wp_base_url }}/wp-json/wc/v3/orders`
   * Add query parameter:
     * Name: `search`
     * Value: `={{ $('When Called by Agent').first().json.email }}`
     * Name: `per_page`
     * Value: `50`
2. This delegates indexing and filtering directly to WooCommerce/MySQL, ensuring that all past orders for that specific customer are returned regardless of total store order volume.

---

### 2. Hardcoded Environment URLs

#### 🛑 The Problem
All WooCommerce and WordPress REST/AJAX endpoints are hardcoded to `https://dev.costaricatransfersandtours.com`.
Deploying these workflows to production requires manually editing dozens of HTTP Request nodes across all 10 files. Pulling changes back down will overwrite production URLs with development ones.

#### 💡 The Solution
Centralize the base URL using an n8n global variable:
1. In n8n **Settings > Variables**, define:
   * Key: `wp_base_url`
   * Dev Value: `https://dev.costaricatransfersandtours.com`
   * Prod Value: `https://costaricatransfersandtours.com`
2. Update all HTTP Request nodes:
   ```text
   {{ $vars.wp_base_url }}/wp-json/wc/store/v1/cart
   {{ $vars.wp_base_url }}/wp-admin/admin-ajax.php
   {{ $vars.wp_base_url }}/wp-json/crtt/v1/services
   ```

---

### 3. Google Sheets for Cart Sessions (Concurrency & Latency Risk)

#### 🛑 The Problem
User WooCommerce cart session cookies (`wp_woocommerce_session_...`) are read and written to a Google Sheet (`CRTT` sheet `cart`) keyed by email.
* **Latency**: Each cart operation incurs 600ms–1.5s Google Sheets round-trip latency.
* **Race Conditions**: If a user sends messages quickly or updates items consecutively, simultaneous workflow runs read stale cookie rows before previous writes complete, overwriting session tokens and wiping cart contents.

#### 💡 The Solution
Migrate session cookie storage to a fast, atomic key-value store or database:
1. **Redis (Recommended)**:
   * Replace Google Sheets nodes with Redis `GET email` and `SETEX email 172800 cookie_value` (48-hour TTL).
   * Reduces latency from ~1.2s to <20ms and guarantees atomic updates.
2. **PostgreSQL / MySQL**:
   * If Redis is unavailable, use an atomic `INSERT ... ON CONFLICT (email) DO UPDATE` query against a dedicated `cart_sessions` table.

---

### 4. Google Calendar Sync Pagination & Polling Delay

#### 🛑 The Problem
`CRTT - Sync Paid Orders to Google Calendar` triggers on an hourly schedule and requests only `per_page=100`.
* **Missed Orders**: If an order was created earlier and its payment status changes to `processing` or `completed` after 100 newer orders have been created, it falls off the query result and is never added to Google Calendar.
* **Polling Latency**: Staff/drivers may wait up to 60 minutes after customer payment before seeing the tour on their calendar.

#### 💡 The Solution
1. **Short-Term (Query Parameter Optimization)**:
   * Filter the orders query by status and modification timestamp:
     ```text
     {{ $vars.wp_base_url }}/wp-json/wc/v3/orders?status=processing,completed&after={{ $now.minus({ hours: 3 }).toISO() }}
     ```
2. **Long-Term (Event-Driven Webhook)**:
   * Add a WooCommerce Webhook in WP Admin targeting an n8n Webhook node (`action: order.updated` / topic `woocommerce_order_status_processing`).
   * Syncs paid bookings to Google Calendar instantly in real time with zero polling overhead.

---

### 5. Google Sheets API Rate Limits (429 Quota Exceeded)

#### 🛑 The Problem
Google Sheets API enforces a limit of **60 write requests per minute per user/project**.
In `CRTT - Neo`, every conversational turn executes `Lookup CRM Profile` and `Upsert CRM Row`. In parallel, subworkflows execute `Read Cart Row` and `Save Cart Cookie`.
During traffic spikes or with multiple simultaneous users, n8n runs into `429 Too Many Requests` Google Sheets rate-limit errors, causing agent interactions to crash mid-session.

#### 💡 The Solution
1. **In-Memory / Sliding Window CRM Cache**:
   * Only trigger `Upsert CRM Row` when `should_save === true` and meaningful profile fields (phone, email, confirmed booking) have actually changed, rather than writing on every conversational turn.
2. **Move CRM Storage to PostgreSQL / Supabase / HubSpot**:
   * For scalable production CRM workloads, connect n8n to a relational database or native CRM without strict per-minute REST quotas.

---

### 6. Lack of Fail-Grace Error Handling

#### 🛑 The Problem
Critical HTTP nodes (`Submit Checkout`, `Get Fresh Cart`, `Remove Item via Store API`, `Update Item via Store API`) are configured to fail on non-2xx status codes by default (`stopWorkflow`).
If WooCommerce returns a 400 validation error (e.g. invalid postal code, card decline, out-of-stock tour), the workflow halts abruptly. NEO receives an unhandled tool exception and responds with a generic error instead of explaining the problem to the customer.

#### 💡 The Solution
1. In the **Settings** tab of each HTTP Request node, toggle **Continue On Fail / Never Error** to `true`.
2. Inspect the HTTP status code in the following Code node:
   ```javascript
   const resp = $input.first().json;
   if (resp.error || resp.statusCode >= 400) {
     const errorMsg = resp.body?.message || resp.body?.data?.message || 'The booking service encountered an error. Please try again.';
     return [{
       json: {
         error: true,
         message: errorMsg
       }
     }];
   }
   ```

---

### 7. Hardcoded Credentials, Sheet & Calendar IDs

#### 🛑 The Problem
* Google Sheet ID `1qMTLCPL2yhU1DSU_TU0S9DrU2zhRvNv1lO5dWyGb37M` is hardcoded in 7 different workflows.
* Google Calendar ID `3c1c31b0bb9cecb83a09f7c9f9c92d4fd6ca27423c3011cbebe15f3e4bf6b93e@group.calendar.google.com` is hardcoded in 2 workflows.
This makes environment promotion (Dev $\rightarrow$ Staging $\rightarrow$ Prod) error-prone and exposes internal identifiers in source repositories.

#### 💡 The Solution
Define environment variables in n8n Settings:
* `cart_sheet_id`: `1qMTLCPL2yhU1DSU_TU0S9DrU2zhRvNv1lO5dWyGb37M`
* `crm_sheet_id`: `1qMTLCPL2yhU1DSU_TU0S9DrU2zhRvNv1lO5dWyGb37M`
* `boat_calendar_id`: `3c1c31b0bb9cecb83a09f7c9f9c92d4fd6ca27423c3011cbebe15f3e4bf6b93e@group.calendar.google.com`

Reference them dynamically in node parameters using `{{ $vars.cart_sheet_id }}` and `{{ $vars.boat_calendar_id }}`.

---

### 8. Hardcoded Placeholder Total Price ($5,000)

#### 🛑 The Problem
In the Code nodes `Validate + Calculate Price` ([Add Tour to Cart](file:///c:/Users/ROY/Desktop/works/costarica-n8n-workflows/workflows/3Y156z1PqTLcmopZ_crtt_-_add_tour_to_cart_api.json)) and `Validate + Calculate Price (Charter)` ([Add Boat Charter to Cart](file:///c:/Users/ROY/Desktop/works/costarica-n8n-workflows/workflows/qkkI8nmxhbe07bC4_crtt_-_add_boat_charter_to_cart_api.json)), the returned payload sets:
```javascript
total_price: 5000
```
While the WooCommerce backend recalculates exact totals upon checkout, the sub-workflow returns `total_price: 5000` to the NEO AI agent, causing the bot to quote "$5,000.00" to customers in chat.

#### 💡 The Solution
Calculate dynamic estimates using the fetched service detail:
```javascript
// Add Tour: calculate price per passenger from rate_table or regular price
const unitPrice = parseFloat(detail.price || fitRow.price || '0');
const estimatedTotal = unitPrice * passengers;

// Add Boat Charter: calculate from matched rate table duration row
const charterPrice = parseFloat(fitRow.price || detail.price || '0');

return [{
  json: {
    ...resolvedData,
    total_price: estimatedTotal > 0 ? estimatedTotal : null
  }
}];
```

---

### 9. Boat Charter Availability Regex Fragility

#### 🛑 The Problem
In `CRTT - Check Boat Charter Availability`, conflict detection relies on:
```javascript
const boatEvents = events.filter(ev => (ev.description || '').match(/Type:\s*Boat Charters/i));
```
If an event is created manually by an administrator without `Type: Boat Charters` in the description, or if the summary is formatted slightly differently, the filter evaluates to `false` and falsely reports the date as available, risking a double-booking of the boat.

#### 💡 The Solution
1. Use a dedicated Google Calendar specifically for Boat Charters (e.g. `boat_charters@group.calendar.google.com`).
2. If any event exists on that calendar for that date, immediately flag as unavailable without relying on text parsing.
3. For multi-service calendars, use Google Calendar Extended Properties:
   ```javascript
   extendedProperties: {
     private: {
       service_type: 'boat_charter',
       order_id: String(order.id)
     }
   }
   ```

---

### 10. State Code Normalization & Accent Matching

#### 🛑 The Problem
In `CRTT - Checkout API`, `Resolve State Code` matches the user's input string against WooCommerce country state lists.
If a customer types `"San José"` with an accent, but the WooCommerce state table stores `"San Jose"`, or vice versa, exact and substring matching can fail, rejecting valid checkout attempts.

#### 💡 The Solution
Normalize diacritics and accents before comparing strings:
```javascript
function cleanString(str) {
  return (str || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .trim()
    .toLowerCase();
}

const userInput = cleanString(prior.state);
const exactCodeMatch = states.find(s => cleanString(s.code) === userInput);
const nameMatch = states.find(s => {
  const sName = cleanString(s.name);
  return sName === userInput || sName.includes(userInput) || userInput.includes(sName);
});
```

---

## 🗺️ Recommended Implementation Roadmap

```mermaid
graph TD
    subgraph Phase 1: High-Impact Fixes (Immediate)
        P1_1[1. Fix Order History WC search query parameter]
        P1_2[2. Centralize URLs and Sheet/Calendar IDs in $vars]
        P1_3[3. Fix $5000 placeholder in Add-to-Cart Code nodes]
        P1_4[4. Add accent normalization to Checkout state resolver]
    end

    subgraph Phase 2: Reliability & Error Resilience
        P2_1[5. Enable Continue On Fail on all HTTP nodes]
        P2_2[6. Optimize Calendar Sync query with modified timestamps]
        P2_3[7. Standardize Boat Charter Calendar conflict filtering]
    end

    subgraph Phase 3: Architecture & Performance Scaling
        P3_1[8. Migrate Cart Sessions from Google Sheets to Redis]
        P3_2[9. Switch Calendar Sync from hourly poll to WC Webhook]
        P3_3[10. Migrate CRM storage to relational database]
    end

    Phase 1 --> Phase 2 --> Phase 3
```
