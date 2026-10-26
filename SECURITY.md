# Security and hosting notes

Keep `DEMO_MODE=true` and use only made-up data. The passwords in the README are public. They must never protect real business records.

## What demo mode blocks

- Backup, recovery and cloud routes, including requests made before signing in.
- Account administration, receipt settings, shop/payment-type settings and product imports.
- Owner changes, except choosing a shop and signing out.
- Recovery commands and scheduled backups.

Outside tests, the database name must end in `_demo`. This helps catch mistakes, but it does not prove that the database is safe to erase.

Cashier prices and product names come from the catalog. Checkout checks prices again. Managers can deliberately override sale prices. Account, role, shop, CSRF and request-limit checks remain enabled.

## Limits to understand

Visitors share the same fictional records and can change them. Managers can edit stock and workshop payment records. Shops can sell below zero stock. Workshop entries do not have a general undo screen. Payment and payout entries record examples only; they do not move money.

The app asks search engines not to index demo pages. That does not restrict access.

## Before hosting

Use a separate database and database account. Give the running app only the database permissions it needs; use a separate account for migrations and resets where possible.

Point your web server at `public/`. Keep `.env`, `.git`, `vendor`, storage and database tools off the public web. Use HTTPS, `SESSION_SECURE_COOKIE=true`, `APP_DEBUG=false`, a fresh app key and a separate session cookie. Do not expose a development server.

Keep mail, SMS and cloud accounts disconnected. Backup code remains in the source for study and tests, but demo mode prevents its use.

## Resetting a shared demo

Take the app offline first. Check the configured database, then run `php artisan demo:reset --force`. This replaces that demo database and clears file sessions. Keep the default file-session driver. Restart the app before letting visitors back in.

Set limits for requests, CPU and disk use, and keep dependencies up to date. This demo has not been certified for production use.

## Report a problem

Open an issue for an ordinary demo bug. Leave out passwords, personal data and hosting details. For a sensitive finding, contact the owner privately through a channel you already trust.
