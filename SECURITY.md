# Demo security boundaries

Keep `DEMO_MODE=true`. The public fixture accounts are deliberately known and must never protect real information.

- A global middleware rejects `/backup` and its descendants, including encoded paths and unauthenticated OAuth callbacks. The web/API demo middleware additionally blocks recovery paths.
- Shared-account administration, receipt changes, shop/payment-type changes and product imports are blocked server-side. Owner requests are read-only except logout and acting-shop selection.
- Outside the test environment, demo startup requires a database name ending in `_demo`. This is an additional naming guard, not proof of isolation.
- Recovery service creation/verification/restore and automatic backup schedules are disabled. No recovery key or Google authorization is provisioned.
- Cashier cart prices/names come from catalog records; cashier checkout re-prices stale session items. Managers can override sale prices intentionally.
- Active account, role and assigned-shop checks, CSRF validation, session invalidation, login limits, write throttling and no-store responses remain enabled.
- Dynamic demo responses carry `X-Robots-Tag: noindex, nofollow`. This is indexing guidance, not access control.

## Limits

This is a portfolio sandbox, not a security-certified or production-ready POS. Managers can change fictional stock and workshop ledgers. Zero-stock shop sales and manager price overrides are intentional. Posted workshop entries currently lack a general reversal interface. Paybill/combined payments and workshop payouts are records only; no funds move.

Shared visitors can see and modify the same fictional records. Never enter personal information, real account numbers, worker identities or authentic financial records. Use a dedicated database account and schema. A runtime identity with only needed DML privileges is preferable; migrations/resets need a separate administrative identity at deployment.

The `_demo` suffix is a reset safeguard, not proof that a database is safe. Inspect your configuration before running reset commands. Reset purges the application's file sessions; retain the default file-session driver for this demo.

Do not expose `.env`, `.git`, `vendor`, storage, database administration interfaces or development servers. Point the web-server document root at `public/`. Use HTTPS, `SESSION_SECURE_COOKIE=true`, `APP_DEBUG=false`, a fresh application key, independent session cookies and separate credentials. Avoid outbound mail/SMS integrations; no real cloud authorization is needed. The retained backup code is available for studying/testing, not as an enabled demo feature.

For a shared host, take traffic offline before externally running `php artisan demo:reset --force`; restart the app afterward. This operation is destructive only to the configured disposable schema. Never run it against other records. Limit request/CPU/disk usage and review dependencies regularly.

## Reporting a problem

Open a minimal issue for a demo bug without credentials, real data or deployment information. For sensitive findings, contact the repository owner privately through an established channel. Do not post secret values in issues.
