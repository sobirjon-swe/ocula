# CI and staging verification implementation plan

> Execute inline with superpowers:executing-plans. The user authorized CI fixes and continuation on dev; no production deployment or main merge is requested.

**Goal:** Verify GitHub CI and exercise the existing deployment/restore recipes on Linux.
**Architecture:** Keep the existing Compose stack. Add a dev-only/manual Actions job and a disposable smoke harness; use the production images and existing backup/restore scripts without real providers.
**Tech stack:** GitHub Actions, Docker Compose, PHP 8.4, PostgreSQL 17, Redis 7, nginx, Node 22.
**Spec:** docs/DEV-NEXT-STEPS.md and docs/deployment/README.md.

## Constraints and review focus

- All changes and pushes on dev. PR #2 remains unmerged.
- Never use real deployment secrets, data or an existing Compose project for smoke checks.
- Restore must refuse a populated database; a separate empty database and attachment volume must recover the same FIFO/cash/file state.
- Static web/API routing, private-file denial, queue/scheduler health and non-superuser database access must be verified against built containers.
- Keep provider/hardware/production tasks pending until actually verified.

## Tasks

- [x] Inspect PR/dev job results and failed logs; reproduce and fix confirmed failures, if any.
- [x] Add `.github/workflows/deployment-smoke.yml`, `docs/deployment/smoke.sh` and a guarded PHP ledger fixture. Build/start the existing Compose stack on Linux; verify routes, private files, FIFO and cash state.
- [x] Run the existing backup script, prove nonempty restore refusal, restore into another CI project, compare ledger/files and verify worker/scheduler health.
- [x] Run shell/YAML/PHP validation locally; push and inspect the actual Actions result; fix real container issues until green.
- [x] Review final changes, record evidence/remaining external work, commit and push dev.

## Ledger

- Baseline dev a41af62; PR frontend jobs all green, backend still running at initial inspection. Dev frontend jobs also all green.
- Docker CLI absent on Windows and Ubuntu WSL. Use an ephemeral GitHub-hosted Linux runner rather than installing a daemon on the user's machine.
- PR run41384526193 and initial dev run41384645583 completed successfully; no CI defect required a code fix.
- Linux smoke run41385239088 passed build/boot/routing/role/private-file checks and backup/restore. Matching source/target snapshots: quantity1, remaining FIFO cost300.00, cash1500.00, matching attachments. Nonempty restore was rejected.
- Local shell syntax/YAML/PHP/Pint checks, non-CI harness refusal and a PostgreSQL fixture seed/verify passed. Fresh read-only review reported no critical/important findings.
- Ruling: expose the existing director/SMS runtime settings and the public Yandex browser build key in deployment templates, because otherwise operators cannot configure those integrations through the supplied packaging. No credentials supplied or live provider calls made.
