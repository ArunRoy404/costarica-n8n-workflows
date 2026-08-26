# CRTT Workflow Fix Priority List

> Work through these **one workflow at a time**, in order.  
> Each section lists the specific issues to fix for that workflow.

---

## 1. 🔴 Calendar Sync (`nCuSVzLVgng6anR4`)

**Why first:** Burning Google Calendar API quota every hour right now — most time-sensitive fix.

- [ ] Add `status=processing,completed` query parameter to the WC orders API call (currently fetches ALL statuses and filters in code)
- [ ] Add `after=<2 hours ago ISO date>` filter so it only processes recently updated orders instead of all 100
- [ ] Fix event ID format — `crtt${order.id}o${li.id}` may not comply with Google Calendar's `[a-v0-9]{5,1024}` pattern
- [ ] Add error handling on `Update Calendar Event` (currently `continueOnFail: true` silently swallows failures)
- [ ] Fix default 2-hour event duration — boat charters can be 4–8 hours; use the duration from metadata when available
- [ ] Move hardcoded Google Calendar ID to an environment variable or config node

---

## 2. 🔴 Checkout API (`ElKNfXom6KIH1y3h`)

**Why second:** Successful checkouts leave stale cart sessions — causes confusion on next visit.

- [ ] Add a Google Sheets "Delete Row" or "Clear Fields" node after `Return Checkout Success` to clean up the cart cookie row
- [ ] Fix ambiguous state resolution — `includes()` matching can return wrong state (e.g., "New" matches "New York" and "New Jersey")
- [ ] Make country code case-insensitive — `.toUpperCase()` before the `^[A-Z]{2}$` regex check
- [ ] Add `onError` / retry logic on the `Submit Checkout` HTTP call
- [ ] Consider adding an idempotency guard (e.g., a "checkout_in_progress" flag on the cart row) to prevent double-orders

---

## 3. 🟡 Add Tour to Cart (`3Y156z1PqTLcmopZ`)

**Why third:** Most frequently called sub-workflow — has unnecessary duplication and a risky hardcode.

- [ ] Remove the cookie/no-cookie branch — merge "Add Tour to same cart" and "Add tour to cart" into one HTTP node with a conditional Cookie header
- [ ] Remove or fix `total_price: 5000` hardcode — either omit the field or compute from `pricing_summary` tiers
- [ ] Fix `$input` reference in "Validate + Calculate Price" — should explicitly reference `$('Fetch Tour Detail')` for clarity
- [ ] Add `onError` handling on `Fetch Tour Detail` — currently `continueRegularOutput` can pass broken data downstream
- [ ] Add retry logic on the WooCommerce `admin-ajax.php` HTTP call
- [ ] Remove the pass-through `Return Validation Error` node (just returns `$json.message` unchanged)

---

## 4. 🟡 Add Boat Charter to Cart (`qkkI8nmxhbe07bC4`)

**Why fourth:** Same structural issues as Add Tour + missing availability guard.

- [ ] Apply all the same fixes as Add Tour to Cart (#3 above) — cookie branch, hardcoded price, retry logic
- [ ] **Add an internal `Check Boat Availability` call** before the WC add-to-cart call — currently relies entirely on the AI remembering to check first
- [ ] Consider merging this workflow with Add Tour to Cart into a single unified "Add to Cart API" (branch by `service_type`)

---

## 5. 🟡 Check Boat Availability (`6TmemRadcJokPItN`)

**Why fifth:** Works fine but is fragile — hardcoded calendar ID and text-matching.

- [ ] Move Google Calendar ID to environment variable (shared with Calendar Sync)
- [ ] Replace hardcoded `UTC-6` offset math with Luxon timezone handling (n8n has Luxon built-in)
- [ ] Make boat event detection more robust — currently relies on regex `Type:\s*Boat Charters` in event description text
- [ ] Handle the case where multiple boat events exist on the same day (currently only checks `boatEvents[0]`)
- [ ] Document the race condition between availability check and add-to-cart (can't fully fix without a lock, but should be acknowledged)

---

## 6. 🟡 Get Cart API (`b78Su2zPQsu2tHBK`)

**Why sixth:** Users hit this frequently — stale sessions cause confusing errors.

- [ ] Add error handling on the `Get Cart` WC Store API call — detect expired/invalid sessions and return a clear "session expired" message
- [ ] Add retry logic on the WC HTTP call
- [ ] Validate the fee key lookup (`payment-fee`, `balance-to-pay-on-arrival`) — add fallback behavior if keys aren't found
- [ ] Remove the redundant `No Cart Yet` and `No Email` code nodes (they just wrap a static message — could be a Set node or inline)

---

## 7. 🟡 Remove from Cart (`OsGh5tDXTX5QIw1g`)

**Why seventh:** Functional but leaves behind stale data.

- [ ] Add cart cookie cleanup when `remaining_items === 0` (delete or clear the Google Sheets row)
- [ ] Add retry logic on the `Remove Item via Store API` HTTP call
- [ ] Add `onError` handling on `Get Fresh Cart` HTTP call
- [ ] Remove the pass-through `Return Removal Success` / `Return Removal Failure` nodes

---

## 8. 🟢 Update Cart Item Passengers (`R4DoiRxM7WE6CWS6`)

**Why eighth:** Works but has no input validation.

- [ ] Add validation that `passenger_count` is a positive integer before calling the WC API
- [ ] Add retry logic on both the `Get Fresh Cart` and `Update Item` HTTP calls
- [ ] Remove the pass-through error nodes
- [ ] ~80% of this workflow is identical boilerplate to Remove from Cart — note for future consolidation

---

## 9. 🟢 Order History (`n0ynlw5Mf1D6I9A6`)

**Why ninth:** Simplest workflow, lowest risk, but has a potential data leak.

- [ ] Add email validation — if email is empty/missing, return an error immediately (currently queries ALL orders)
- [ ] Replace `search` query parameter with a more specific filter (e.g., WC `customer` param if available)
- [ ] Add pagination support for users with 100+ orders
- [ ] Add `'Boat Charter'` as a meta key fallback alongside `'Tour'` in the `extractMeta` calls
- [ ] Remove the pass-through `Return Orders Success` / `Return Orders Failure` nodes

---

## 10. 🟢 NEO — Main Agent (`ZeNz4VZTUkNMPhOP`)

**Why last:** Touches everything — should only be optimized after all sub-workflows are stable.

- [ ] Add webhook authentication (API key header check as first filter node)
- [ ] Trim the system prompt from ~4,000 words to ~1,500 words (compact rules, remove redundancy)
- [ ] Optimize CRM extraction — evaluate combining into main agent response to eliminate 2nd LLM call
- [ ] Reduce context window from 20 to 12–15 messages (test for quality impact first)
- [ ] Remove redundant HTTP tools if consolidated workflows make them unnecessary
- [ ] Update all tool workflow references to point to any consolidated/renamed workflows from earlier milestones

---

## Quick Reference

| # | Workflow | Severity | Est. Effort | Key Risk if Unfixed |
|---|---------|----------|-------------|-------------------|
| 1 | Calendar Sync | 🔴 Critical | ~1h | API quota exhaustion |
| 2 | Checkout API | 🔴 Critical | ~1h | Stale carts + double orders |
| 3 | Add Tour to Cart | 🟡 Medium | ~1.5h | Wrong price + maintenance burden |
| 4 | Add Boat Charter to Cart | 🟡 Medium | ~2h | Double-booking boats |
| 5 | Check Boat Availability | 🟡 Medium | ~45min | Fragile text matching |
| 6 | Get Cart API | 🟡 Medium | ~45min | Confusing stale session errors |
| 7 | Remove from Cart | 🟡 Medium | ~30min | Stale data accumulation |
| 8 | Update Passengers | 🟢 Low | ~30min | No input validation |
| 9 | Order History | 🟢 Low | ~30min | Potential data leak |
| 10 | NEO (Main Agent) | 🟢 Low | ~3-4h | Token cost + no auth |
