# Development continuation — 2026-11-02

The completed implementation is proposed to `main` in [PR #2](https://github.com/sobirjon-swe/ocula/pull/2), from `codex/complete-project` at `76d1971`. That PR has not been merged. The shared `dev` branch starts from the same implementation commit so subsequent work retains all delivered workflows.

New work continues on `dev`. Its pushes run the existing backend, frontend, browser and production PWA checks; the workflow can also be started manually. Keep production deployment separate from branch publication. Incorporate the reviewed `main` history into `dev` after PR #2 is merged.

## Remaining staging work

- [x] Review the PR's GitHub Actions results and resolve any Linux/CI-specific failures: [PR run](https://github.com/sobirjon-swe/ocula/actions/runs/41384526193) and [initial dev run](https://github.com/sobirjon-swe/ocula/actions/runs/41384645583) both passed all backend/frontend/browser/PWA jobs on 2026-11-02.
- [x] On a Docker-capable Linux runner, build/start the actual recipes, run production migrations/reference seeds and verify health, Horizon/scheduler, UI/API routes and the non-superuser database role: [deployment smoke run](https://github.com/sobirjon-swe/ocula/actions/runs/41385239088) passed on 2026-11-02. Actual business-host setup and interactive director bootstrap remain deployment tasks.
- [x] Rehearse backup/restore into a separate empty database and attachment volume; confirm FIFO quantity/cost, cash and private/public files match. The same run also proved refusal to restore over an existing database. Repeat with verified business data before real production cutover.
- [ ] Configure verified branch/contact/catalog data, production domains/TLS and backend environment values.
- [ ] Connect the authorized Telegram bot/webhook, SMS provider and optional Yandex map key; verify actual delivery using staging accounts.
- [ ] Exercise camera/GPS/offline installation and non-fiscal receipt printing on the target phones/tablets/printer.
- [ ] Deploy a reviewed release only after the staging checks pass.

The current Windows workstation has no Docker CLI. Linux image/startup/restore verification is now automated by `.github/workflows/deployment-smoke.yml` on disposable GitHub runners. Its domain fixture also passed locally against a separate PostgreSQL database: remaining FIFO quantity 1, remaining cost 300.00, cash 1500.00, matching private/public file content. Production hosting and service credentials are not supplied in the repository. Do not substitute demo credentials or fabricated provider data for those checks. Secrets belong in the deployment environment, not this file.

See [deployment and recovery](deployment/README.md), [implementation status](IMPLEMENTATION-STATUS.md) and [employee workflow verification](EMPLOYEE-WORKFLOWS.md) for commands and evidence. React Native, online gateways and fiscal integration remain conditional future stages in PROJECT.md.
