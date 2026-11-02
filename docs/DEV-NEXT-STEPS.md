# Development continuation — 2026-11-02

The completed implementation is proposed to `main` in [PR #2](https://github.com/sobirjon-swe/ocula/pull/2), from `codex/complete-project` at `76d1971`. That PR has not been merged. The shared `dev` branch starts from the same implementation commit so subsequent work retains all delivered workflows.

New work continues on `dev`. Its pushes run the existing backend, frontend, browser and production PWA checks; the workflow can also be started manually. Keep production deployment separate from branch publication. Incorporate the reviewed `main` history into `dev` after PR #2 is merged.

## Remaining staging work

- [ ] Review the PR's GitHub Actions results and resolve any Linux/CI-specific failures.
- [ ] On a Docker-capable Linux host, build/start the recipes in `docs/deployment`, run migrations/seed/bootstrap and verify health, Horizon and scheduler.
- [ ] Rehearse backup/restore into a separate empty database and verify FIFO balances, financial totals and private attachments.
- [ ] Configure verified branch/contact/catalog data, production domains/TLS and backend environment values.
- [ ] Connect the authorized Telegram bot/webhook, SMS provider and optional Yandex map key; verify actual delivery using staging accounts.
- [ ] Exercise camera/GPS/offline installation and non-fiscal receipt printing on the target phones/tablets/printer.
- [ ] Deploy a reviewed release only after the staging checks pass.

The current Windows workstation has no Docker CLI. Production hosting and service credentials are not supplied in the repository. Do not substitute demo credentials or fabricated provider data for those checks. Secrets belong in the deployment environment, not this file.

See [deployment and recovery](deployment/README.md), [implementation status](IMPLEMENTATION-STATUS.md) and [employee workflow verification](EMPLOYEE-WORKFLOWS.md) for commands and evidence. React Native, online gateways and fiscal integration remain conditional future stages in PROJECT.md.
