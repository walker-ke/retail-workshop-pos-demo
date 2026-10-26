# What was checked — 26 October 2026

These checks ran on this separate demo, using made-up data:

- A fresh copy of the source passed Composer installation, `npm ci`, asset build, application-key generation and PHPUnit without files or state from the original app. Configuration and route caching were also checked.
- The frontend installed and built from the lockfile. Browser libraries are prepared by `tools/prepare-assets.cjs`.
- **97 PHPUnit tests, 677 assertions passed**, including demo recovery denial, encoded backup paths, owner read-only behavior, administrative write denial, sensitive permission checks, recovery CLI refusal and reset-name/confirmation safeguards.
- `tools/test-demo.php` passed full migrations and synthetic seeding on an isolated temporary MariaDB server, cashier name/price tamper rejection, checkout repricing, authorized manager override, assigned-shop binding and repeat-seed refusal.
- Workshop integrity/concurrency harness passed production stages, component shortages/replays, pooled-upper assembly, exact-money ledger operations, rollback, concurrent payouts, hub transfers, manager permissions and rendered views.
- Stock-transfer harness passed competing accepts/dispatch/receipts, reservation protection, repeated requests, partial confirmation, cancellation, rollback and rendering.
- Hub harness passed role/shop checks, repeated dispatch/receipt, discrepancies/return/write-off, concurrency and rendering.
- Refund/payment integrity harness passed payment validation, shop/cart binding, product/stock management, partial/full refunds, money/stock reconciliation, rollback and 14 rendered screens.
- JavaScript catalog-search checks passed; three Python archive-safety tests passed.
- Composer locked-dependency audit reported no known advisories/abandoned packages; npm audit reported **zero known vulnerabilities** at review time.
- Gitleaks v8.30.1 staged-source scan reported no leaks. An additional file/identity scan found no former business branding, private host paths, operational data files or recognizable credential patterns in the proposed export.
- Browser sign-in, hub inventory and seeded workshop overview were checked in the in-app preview. Screenshots contain only the invented dataset. No browser errors/warnings were observed on the checked screen.

Each database check creates a temporary MariaDB server with a private socket, then removes it. The original business repository and database were not modified. Unit and archive tests cover the backup helpers. Full restoration is disabled in demo mode and was not tested as a public feature.

These results describe the local checks on that date. They do not certify security, capacity or a future hosted setup. The included CI workflow lets these checks run again.

Claude independently reviewed the credential-free isolation design. Its suggestions on global recovery denial, encoded-path tests, overwrite/reset safeguards and shared-demo limits were checked against the implementation. Unverified hypothetical profile/upload routes were not treated as existing defects.
