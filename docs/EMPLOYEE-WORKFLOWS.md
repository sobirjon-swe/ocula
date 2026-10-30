# Employee workflows and verification

The employee application remains a React SPA calling the Laravel `/api/v1` API. This document records the employee UI delivered in this change and its verification boundaries.

## Functional coverage

| Workflow | Employee UI |
| --- | --- |
| Access and navigation | Permission-filtered desktop/mobile navigation, protected routes, password/PIN sign-in, shared-device enrollment, account-scoped query cleanup, four locales. Uzbek Cyrillic is derived from Latin; authored translations are JSON loaded through react-i18next. Locale changes persist through `/auth/locale`, with an account-scoped pending preference when offline. |
| POS | Two-column search/cart, barcode/name/SKU lookup, all-branch quantities, customer debt, services and custom-lens service lines with manual cost/optical parameters, split cash/card/transfer payments, atomic quick checkout, made-to-order delivery/due date/prescription selection. Enter adds the current name/SKU or exact barcode result; F2 focuses payment, Escape clears search, F8 focuses checkout. Money uses integer cents and the server's `/pos-config` rounding step. Failed/zero-stock searches record deduplicated lost-sale events. |
| Quick catalog entry | Explicit physically counted opening quantity, unit cost, branch, barcode and first price; the returned variant enters the cart. The pending catalog identity, opening receipt and FIFO layer are created together under idempotency. |
| Orders and customers | Order detail, permitted status changes, payments, credit approval, handover/cancellation, returns with explicit saleable-stock choice, payment reversals, server-rendered receipt printing, customer/order/prescription history and expired-prescription warnings. |
| Shifts and cash | Opening/closing counts, expected/actual cash and differences, cash summary/ledger, manual cash categories and reversal reasons. |
| Warehouse | Purchases/receipt, transfer creation/send/actual receipt/discrepancy, taxi cost and receipt-photo capture/view, stock requests, movements/reversals, master stock, physical defects, supplier-return posting and lost-sale records. Inventory distinguishes uncounted from an explicit zero, supports partial/extra counts, review, completion and cancellation. |
| Clinic | Date/status-filtered queue and visit actions, appointment handling, OD/OS forms stacked on phones, quarter-diopter selectors, AXIS validation, copying nested historical prescriptions, change warnings at 1.5 diopters, prescription edits and ticket transfer. |
| Workshop | Desktop drag/drop board and phone status list, large prescription values and 60px actions, assignment/priority/material use, defect/rework. A card's defect control records a selected material/reason without a required note dialog. |
| Finance and payroll | Expenses/approval, debt reminders/write-off/collection, supplier balances and ledger, bonus rules including rate/product type, bonus approval/payment, branch plans. |
| Director and reports | Four principal figures, outstanding liability, branch cash/plan/elapsed-month comparison and attention links. Twelve report choices cover cash, profit, ABC, dead stock, lost sales, staff, branch ranking, debt aging, turnover, abandoned orders, transport costs and supplier balances. CSV/XLSX use authenticated backend downloads. |
| Maintenance | Staff/roles/primary and extra branches, PIN/debt limit, devices/allowed roles/revocation, branches, settings, products/variants/prices/history/approval/merge, reference names/translations, audit field changes. Product names remain canonical rather than translated. |
| Driver integration | `/delivery`, PWA manifest/icon, production shell worker and shared JSON translations integrate the delivery implementation maintained by the parent task. The worker precaches the entry module, three vendor modules, CSS and shell assets; it excludes API responses. |

The UI hides unavailable actions for convenience. Laravel policies and branch scopes remain the authorization boundary. Financial document corrections use domain reversal endpoints; these screens do not provide destructive ledger editing.

## Reproduce the checks

Run these commands from the repository root with admin dependencies installed:

```powershell
npm --prefix admin run build
npm --prefix admin run lint
npm --prefix admin run test:unit
npm --prefix admin run test:e2e
npm --prefix admin run test:pwa
npm --prefix admin audit
```

`test:e2e` uses `admin/playwright.config.ts`, Chrome and a temporary Vite server on `127.0.0.1:5178`. `test:pwa` uses `admin/playwright.production.config.ts`, Chrome and `npm preview` on `127.0.0.1:4176`. Build immediately before the PWA check: it deliberately tests `dist`, not the development server. The PWA test has no real-backend dependency.

The normal browser suite mocks API responses. It checks payloads and interaction behavior: atomic split checkout, OD/OS history copying/change warning, explicit-zero inventory, responsive/Cyrillic navigation, route denial, rendered mobile layouts, automatic lost-sale deduplication, custom-lens payloads, card defect recording and keyboard name/SKU entry. Unit checks cover exact monetary arithmetic/rounding, locale-key parity/transliteration and the account-scoped offline queue.

### Live Laravel checks

The opt-in suite requires an isolated, current sandbox API on `127.0.0.1:8010` and the admin development server on `127.0.0.1:5175`, proxied to it. It uses `DemoUserSeeder` sandbox accounts and requires the preview fixtures: barcode `20260001` with available stock and a current price, and customer `Demo mijoz`. These checks write sandbox sales, visits, prescriptions and counted-stock receipts.

Start the admin server in another terminal:

```powershell
$env:VITE_BACKEND_URL = 'http://127.0.0.1:8010'
npm --prefix admin run dev -- --host 127.0.0.1 --port 5175
```

Then run:

```powershell
$env:OPTIKA_LIVE = '1'
npm --prefix admin run test:e2e -- --grep 'live API'
Remove-Item Env:OPTIKA_LIVE
```

Live checks cover scanned fully paid checkout, clinic queue/prescription and invalid AXIS, counted quick entry, every visible director navigation endpoint, all twelve reports, and a downloaded XLSX ZIP signature. They are skipped by the normal browser command. A stale preview database initially lacked the subsequently added `stock_layers.origin_purchase_id`; after applying that migration the affected counted-entry check passed again.

## Production offline evidence

The production PWA check uses deterministic online API fixtures and simulated GPS. It waits for an active service worker and verifies at least five hashed JS/CSS assets are in Cache Storage, with no API entries. It then:

1. Reloads the complete driver page offline, retaining the route and previously downloaded cash balance.
2. Records one GPS delivery offline and checks that a second cold reload retains the pending operation and prevents another delivery tap.
3. Reconnects, injects a transient HTTP 503, retries and verifies that both attempts use the original idempotency key.
4. Verifies an empty outbox, the refreshed server balance and no browser runtime errors.

The cash/goods banner after a cold offline reload is the **last downloaded server snapshot**. The pending-operation count identifies unsent work; the banner is not a newly reconciled server balance until synchronization succeeds.

This check exposed a real cold-reload failure caused by `Vary: Origin` on cached static modules. Worker version 3 ignores Vary only for public shell/hashed-asset matching. Authenticated API data is still never stored in Cache Storage; driver trip/balance/outbox persistence is account-scoped separately.

## Recorded evidence and limits

- Build and lint passed; npm audit reported zero vulnerabilities after compatible Vite/transitive patches.
- Ten unit checks passed. Nine initial mocked browser checks and the subsequent keyboard regression passed; the full suite now contains ten mocked checks plus five opt-in live checks.
- All five live scenarios passed (the counted-entry scenario was rerun after the preview migration).
- The production offline test passed, including cold reload, cached balance, GPS queueing, transient failure and stable-key replay.
- Desktop POS and mobile clinic/workshop screenshots were visually inspected; the production driver test also captures a screenshot. Generated evidence lives under ignored `admin/test-results/`; browser reports and TypeScript build caches are also ignored.
- Impeccable's mechanical detector returned no findings for the changed employee surfaces.

Physical printers, real phone camera/GPS behavior, external notification providers and a deployed Yandex API key are not simulated by these checks. Receipt printing opens the authenticated server-rendered HTML in the browser. Parent-owned backend, delivery and mapping behavior has separate verification; the mocked UI suite does not establish every domain invariant by itself.
