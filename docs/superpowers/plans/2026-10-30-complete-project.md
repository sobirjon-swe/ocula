# Ocula Completion Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans. User authorized autonomous implementation, preserving architecture and all mandatory PROJECT.md functions.

**Goal:** Deliver the mandatory optika workflows as usable, verified software.
**Architecture:** Keep Laravel modular API, React admin/customer SPA and Next.js landing. Write financial and inventory changes through existing ledger services. Add focused domain screens rather than a technical API console.
**Tech Stack:** PHP/Laravel, PostgreSQL, Redis, React/TypeScript, Vite, Next.js.
**Spec:** docs/PROJECT.md, docs/SCHEMA.md, docs/ENUMS.md, docs/PERMISSIONS.md.

## Global Constraints

- All endpoints under /api/v1; FormRequests, policies, branch scope and transactions.
- Money decimal(15,2), Money+bcmath; FIFO; immutable records corrected with reversals.
- UTC persistence, Asia/Tashkent presentation; reports grouped by shift.
- uz-latn, derived uz-cyrl, ru, en; responsive role-specific workflows.
- Driver retries retain the original Idempotency-Key; customer authentication stays separate.
- React Native and online payment gateways remain conditional future stages specified in PROJECT.md.

## Review Focus

- Two simultaneous requests must not oversell, repeat cash collection or duplicate stock counts.
- Offline operations must never be sent as another employee after account switching.
- Unpaid orders are allowed before delivery; delivery on credit requires the seller's permission and limit.
- Prescription history can cross branches; active tickets cannot.
- Partial returns and pre-paid delivery must preserve bonus and financial consistency.

## Execution decisions

Use origin/main 5d52fed as baseline. Work on codex/complete-project in the existing checkout; preserve untracked branch audit. User delegated remaining choices and instructed execution, so no additional design approval cycle. Read all requirements before considering any phase complete. Track actual verification and remaining scope in docs/IMPLEMENTATION-STATUS.md.

### Task 1: Baseline and regression safety

Files: backend/tests, .github/workflows/tests.yml, frontend package scripts.
- [x] Run backend suite, frontend lint/build; diagnose environment and existing failures.
- [x] Add regressions for pre-paid delivery bonus, debt delivery authorization and idempotency isolation.
- [x] Implement domain fixes through existing actions/services; run targeted tests then suite.

### Task 2: Inventory and financial completeness

Files: Warehouse/{Actions,Models,Http,Policies,Routes}, database/migrations, tests/Feature/Warehouse; Sales/Services, Payroll/Services and tests.
- [x] Add failing inventory tests for snapshots, counts, finalization, stale stock, authorization and repeat submission.
- [x] Add transactional inventory adjustments via StockLedger with persisted snapshot and count history.
- [x] Link supplier defects to their FIFO purchase provenance and supplier return ledger.
- [x] Implement proportional bonus reversals and explicit monetary count rate; test paid and unpaid cases.
- [x] Add missing scheduled abandoned-order and daily operational reporting workflows.

### Task 3: Employee workflows

Files: admin/src/features/{sales,orders,customers,shifts,warehouse,clinic,workshop,finance,analytics,settings}, app/routes.tsx, layout, api types, translations.
- [x] Implement permission-aware navigation, mobile navigation, locale persistence and account/device switching.
- [x] Build two-column POS with barcode/name search, branch balances, cart, customer warnings, keyboard shortcuts and payment.
- [x] Build orders/detail/payment/return, customers/history, shifts and catalog maintenance forms.
- [x] Build purchase/transfer/request/count screens with actual API validation.
- [x] Build doctor queue and OD/OS prescription form, copy/history and optical warnings.
- [x] Build tablet kanban/mobile list, material issue/consume/defect/rework workflows.
- [x] Build cash/debt/expenses/suppliers/payroll/plans and director attention summary with report export.
- [x] Verify workflows with meaningful tests and browser checks, build and lint.

### Task 4: Driver offline delivery

Files: admin/src/features/delivery, lib/offline, public/service-worker.js, manifest; backend Delivery controllers/resources.
- [x] Add tests for queue persistence, retries, stable idempotency keys, account isolation and conflict handling.
- [x] Implement cached trips and sequential durable operations, GPS on delivery only, optional photo upload, maps/phone links, pending indicator and balance.
- [x] Add dispatcher trip/stop creation, collection and discrepancy handling.
- [x] Verify offline reload, reconnect, duplicate retries and logout/account change.

### Task 5: Customer and public surfaces

Files: customer/src, customer/public, landing/app, landing/components, backend Customer.
- [x] Verify existing Mini App auth/profile/order/prescription/debt/delivery flows; add rating and saveable prescription card.
- [x] Add safe PWA caching and update behavior; never cache authenticated API responses across users.
- [x] Complete landing locales, booking errors, maps and SEO without invented business details.
- [x] Run lint/typecheck/production builds and browser verification.

### Task 6: Operational delivery

Files: README.md, docs/deployment, CI, backend configuration and tests.
- [x] Provide environment/dependency/install/seed instructions, queue/scheduler/webhook setup, backup/restore and deployment configuration.
- [x] Add frontend checks to CI and retain immutable action revisions.
- [x] Run all verification, request independent final branch review, resolve material findings, and record remaining external credentials/hosting needs.
- [x] Commit reviewed changes on the implementation branch; do not claim completion while mandatory scope remains.
