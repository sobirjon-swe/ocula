# Implementation status — 2026-10-30

Baseline: origin/main `5d52fed`; implementation branch: `codex/complete-project`.
Scope: all mandatory PROJECT.md modules; existing Laravel modular API, React employee/customer apps and Next.js public site retained.

## Delivered coverage

| Requirement | Implementation |
|---|---|
| Core | Roles/policies, branch isolation and additional branches, devices/PIN switching, own locale, settings, immutable audit history and director bootstrap |
| Catalog | Parameterized variants, branch prices, inline pending product creation with opening stock, approval notifications, duplicate suggestions and safe merge |
| Warehouse | Purchase/FIFO layers, transfers/taxi receipts and discrepancies, requests, stock counts and stale-snapshot rejection, defects and source-linked supplier returns |
| Sales | Two-column POS, barcode/name lookup and shortcuts, atomic split-payment checkout, orders, creator-specific credit limits/approval, proportional returns, shifts and cash reversals |
| Clinic/Workshop | Queue/QR check-in, OD/OS prescriptions/history/copy, optical validation, tablet kanban, materials/defects/rework and staff statistics |
| Delivery | Dispatch routes/stops, mandatory handover GPS, optional compressed photo, maps/calls, driver cash/goods, collections and durable account-isolated offline queue |
| Finance/Payroll | Two-sided supplier ledger, debt aging/reminders/write-off, expenses, individual percentage/count bonus rules, proportional recoveries and safe payout |
| Analytics | Separate cash/profit by business shift, liabilities, returns/expenses/doubtful debt, director attention cards, plan-based ranking, ABC/dead stock and real XLSX/CSV exports |
| Customer | Telegram authentication, own orders/debt/prescriptions, delivery confirmation, rating, prescription export and safe static-only PWA cache |
| Public site | Four locale SSR routes, branches/maps/printed QR, prices, future booking with validation/recovery, metadata and sitemap |
| Operations | Scheduled reminders/abandoned orders/catalog digest/director summary, Telegram and SMS fallback, Horizon, CI, deployment and backup/restore recipes |

Detailed employee workflows and browser checks: [EMPLOYEE-WORKFLOWS.md](EMPLOYEE-WORKFLOWS.md).
Financial decisions: [FINANCIAL-RULES.md](FINANCIAL-RULES.md). Branch audit: [BRANCH-AUDIT-2026-10-30.md](BRANCH-AUDIT-2026-10-30.md).

## Verification

Final verification passed on PHP 8.4.27 and PostgreSQL 17.6 after dependency updates (Laravel 13.39.0): **525 backend tests, 1,859 assertions; zero failures/errors**. The complete suite was partitioned across four isolated databases (119/164/75/163 cases), plus the three root health cases and one production demo-seeder safety regression. No test partitions shared a database. Whole-backend PHPStan reports zero errors; whole-backend Pint check passes. Composer validation and locked security audit pass with no advisories.

The parent reran all 16 frontend behavior tests together successfully. All three apps pass production build/lint and their locked npm audits report zero vulnerabilities. The employee UI has 10 mocked browser cases, five live Laravel scenarios and one production PWA scenario, all passing. CI retains backend checks and now runs frontend build/lint/unit plus employee browser/PWA checks.

- Customer: final lint, production build and 5 behavior tests pass.
- Landing: final lint, production build and four-locale SSR/booking smoke test pass.
- Employee: build/lint, 10 exact-money/i18n/offline unit tests, 10 mocked browser workflows, five live Laravel scenarios and one production PWA workflow pass; see workflow document for commands and boundaries.
- Production PWA browser test proves cold offline reload, cached per-account trip/balance, GPS outbox persistence, retry with unchanged idempotency key, eventual server reconciliation, and exclusion of API responses from Cache Storage.
- Concurrent document numbering and merge-versus-receipt regressions use actual separate PHP processes/PostgreSQL connections.
- Independent final review identified FIFO/merge concurrency, original-seller credit, cancellation, field-receipt reversal and bonus payout issues; fixes have targeted regressions.

## Decisions and deployment boundary

- PHP minimum is 8.4 (activitylog 5 requires it); Composer platform, CI and container agree. Security advisories are resolved through patched locked dependencies.
- All seven remote branches were audited; their feature patches were already in main. The separate Dependabot setup-php revision was incorporated, and setup-node is also pinned to an immutable revision.
- PostgreSQL test databases are disposable and separate from development data. Tests prohibit stray HTTP requests; no real customer messages are sent by verification.
- Stock mutations share a transaction advisory lock, favoring ledger/merge correctness over parallel stock-write throughput for this five-branch deployment. Historical document identities and FIFO acquisition timestamps/purchase provenance are preserved.
- Supplier returns without known purchase provenance remain drafts until a real source purchase is selected.
- Count-based bonus rules require an explicit monetary rate. Returns use original rule snapshots; paid bonus corrections become recoveries.
- Field cash receipts may be reversed only while pending collection and supported by the driver's held cash. Collected funds need the audited refund/reconciliation flow.
- Driver offline balances are the last confirmed server snapshot; queued operations are visibly pending until acknowledged. Conflicts stop the queue for explicit retry/discard.
- Driver goods combine transfer stock units and active customer delivery packages; delivered packages leave the count. Default catalogs remain empty. Demo employees and optional sample stock are restricted to local/testing, including direct seeder invocation.
- SMS provider, Telegram bot, map key, actual branch/contact data, production domains/TLS and infrastructure must be configured for the deployment. Provider behavior is tested with fakes, not live credentials.
- Linux container startup and isolated backup/restore are now verified by the [2026-11-02 deployment smoke run](https://github.com/sobirjon-swe/ocula/actions/runs/41385239088) on dev. It built the actual images, migrated/seeded production-mode containers, verified Horizon/scheduler, restricted PostgreSQL access, routes and private-file denial, then restored matching FIFO/cash/files into separate volumes. Docker remains unavailable on this Windows host. Actual business-host configuration, physical printer and target-phone checks remain staging work.
- Fiscal integration/online gateways and React Native are conditional future stages in PROJECT.md. This release supplies non-fiscal printable receipts and the required PWA.
