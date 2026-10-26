# Verification — 26 October 2026

Fresh checks for this sanitized derivative, performed in a separate workspace with invented data:

- A clean-room source export passed Composer installation, `npm ci`, asset build, application-key generation and PHPUnit without inherited state. Configuration and route caching were also checked.
- Frontend installation/build succeeds from the declared lockfile; browser dependencies are provisioned by `tools/prepare-assets.cjs`.
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

All database integration harnesses provision disposable private-socket MariaDB instances and remove them afterward. The original business repository and database were not modified. Inherited recovery primitives are covered by unit/archive tests; full backup restoration is disabled in the demo and was not exercised as a public capability.

These checks are scoped evidence, not a security certification, capacity guarantee or verification of a future hosted environment. CI is supplied for repeatability; no remote CI run is claimed before publication.

Claude independently reviewed the credential-free isolation design. Its suggestions on global recovery denial, encoded-path tests, overwrite/reset safeguards and shared-demo limits were checked against the implementation. Unverified hypothetical profile/upload routes were not treated as existing defects.
