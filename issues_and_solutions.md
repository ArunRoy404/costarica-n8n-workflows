# n8n Workflows: Issues and Solutions Report

This report outlines critical design, security, and runtime issues found in the active n8n workflows for the **CRTT Neo** system, along with step-by-step solutions to address them.

---

## 📋 Summary of Issues

| Issue Category | Severity | Impact | Primary Affected Workflows |
| :--- | :--- | :--- | :--- |
| **Hardcoded Environment URLs** | 🔴 High | Live traffic routes to development environment after launch. | All workflows using HTTP Request nodes. |
| **Google Sheets Concurrency** | 🟡 Medium-High | Race conditions in cart session cookie lookup causing lost cart items. | Cart operations, checkout, Neo agent. |
| **Lack of Fail-Grace Error Handling** | 🟡 Medium | Agent crashes on network/validation errors instead of showing clear feedback. | Checkout, Cart update/remove APIs. |
| **Hardcoded Credentials & IDs** | 🟡 Medium | Insecure exports; difficulty setting up staging vs. production. | Calendar check, Cart APIs, Neo agent. |
| **Placeholder Pricing ($5000)** | 🟢 Low-Medium | AI agent might quote a placeholder price of $5,000 to users in chat. | Add Tour, Add Boat Charter APIs. |

---

## 🔍 Detailed Analysis & Solutions

### 1. Hardcoded Environment URLs

#### 🛑 The Problem
All WooCommerce and WordPress API calls are hardcoded to the staging/dev URL: `https://dev.costaricatransfersandtours.com`.
If you deploy these workflows to production, you will have to manually update every HTTP Request node. If you pull updates back from production later, your local environment will point to production.

#### 💡 The Solution
Use n8n global variables or environment configurations.
1. Open n8n Settings and define a custom variable:
   * Key: `wp_base_url`
   * Value (Dev): `https://dev.costaricatransfersandtours.com`
   * Value (Prod): `https://costaricatransfersandtours.com`
2. Update the HTTP Request nodes to use this expression:
   ```text
   {{ $vars.wp_base_url }}/wp-json/wc/store/v1/cart
   ```

---

### 2. Google Sheets for Cart Sessions (Concurrency Risk)

#### 🛑 The Problem
The system stores user WooCommerce cart sessions (`wp_woocommerce_session_` cookies) in a Google Sheet (`1qMTLCPL2yhU1DSU_TU0S9DrU2zhRvNv1lO5dWyGb37M`) using the user's email as the key.
* **Latency**: Google Sheets reads and writes are slow (~600ms to 1.5s).
* **Race Conditions**: If a user performs actions rapidly (e.g. clicks "add to cart" twice), the second write will execute before the first write updates the cookie, leading to cart inconsistency.

#### 💡 The Solution
Move session storage to a fast, atomic database or key-value store:
1. **Redis (Recommended)**: Use a lightweight Redis instance to get/set `email:cart_cookie` with an expiration TTL.
2. **SQLite/PostgreSQL**: If you prefer SQL, use a local database with atomic transactions to update the session.

---

### 3. Fail-Grace Error Handling

#### 🛑 The Problem
HTTP nodes like `Submit Checkout`, `Get Fresh Cart`, and `Remove Item` are set to `stopWorkflow` on error. If WooCommerce validation fails (e.g. invalid zip code, out-of-stock item), the workflow stops executing entirely.
The calling AI Agent (Neo) receives a generic crash error and cannot tell the user *why* the checkout or cart operation failed.

#### 💡 The Solution
1. In the HTTP Request node configuration under **Settings**, set **On Error** to `Continue regular output` (or toggle `Ignore SSL Issues / Continue on Fail`).
2. Add an **If** node or check in the following **Code** node:
   ```javascript
   // Inside code node following HTTP node:
   const response = $input.first().json;
   if (response.error || response.statusCode >= 400) {
     return [{
       json: {
         error: true,
         message: response.body?.message || 'The store service encountered an error. Please try again.'
       }
     }];
   }
   ```

---

### 4. Hardcoded Credentials & Sheet/Calendar IDs

#### 🛑 The Problem
* The Google Sheet ID (`1qMTLCPL2yhU1DSU_TU0S9DrU2zhRvNv1lO5dWyGb37M`) is hardcoded in 7 separate workflows.
* The Google Calendar ID (`3c1c31b0bb9cecb83a09f7c9f9c92d4fd6ca27423c3011cbebe15f3e4bf6b93e@group.calendar.google.com`) is hardcoded in 2 workflows.
This makes the exported JSONs hard to share and insecure.

#### 💡 The Solution
Centralize these parameters.
1. Define n8n variables for these IDs:
   * `cart_sheet_id`
   * `boat_calendar_id`
2. Update the Google Sheets and Google Calendar nodes to read these variables using:
   ```text
   {{ $vars.cart_sheet_id }}
   ```
   and
   ```text
   {{ $vars.boat_calendar_id }}
   ```

---

### 5. Hardcoded Total Price Placeholder ($5000)

#### 🛑 The Problem
In the Code nodes `Validate + Calculate Price` and `Validate + Calculate Price (Charter)`, the `total_price` is hardcoded to `5000` because WooCommerce handles the math on the backend anyway.
However, because this workflow outputs `total_price: 5000` back to the Neo Agent, the AI agent might say: *"The total price is $5000.00"* during chat.

#### 💡 The Solution
Calculate an estimated total price dynamically:
1. Extract the individual item price from the preceding `Fetch Tour Detail` node response.
2. Multiply by the passenger count.
3. Return the calculated estimate in the JSON response so the agent has access to correct information.
