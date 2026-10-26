# Architecture and invariants

The app is a server-rendered Laravel application, with Blade/Sass and JavaScript for catalog search and interactive forms. Routes/controllers enforce account state, role and shop boundaries. Domain services own multi-row inventory and production mutations.

| Area | Main responsibility |
|---|---|
| `app/Http/Middleware` | Account checks, CSRF integration, demo isolation and response policies |
| `app/Http/Controllers` | Request validation, shop-scoped queries and views |
| `app/Services` | Transfer state machines, hub movements, workshop balances and ledgers |
| `app/Support` | Money/tender helpers, receipt snapshots, CSV cells and transaction retries |
| `database/migrations` | Relational structure, constraints, unique keys and lookup indexes |
| `database/seeders` | Invented demo dataset, with overwrite refusal |
| `tests` and `tools/test-*` | Feature/unit tests plus isolated MySQL/MariaDB integrity/concurrency checks |

## Key invariants

- Cart items are bound to the selected shop. Changing shops clears the cart; checkout rejects mismatched items.
- Cashiers cannot lower prices by forging a request or leaving stale cart prices; managers intentionally have override permission.
- Refund amounts use historical sale prices, and the order row is locked before remaining quantities are checked.
- Stock operations lock affected balances in stable order and retry supported transaction conflicts. Atomic updates do not imply a no-negative-shop-stock policy.
- Transfer transitions validate source/destination authority and current state; repeated workflow requests must not credit inventory twice.
- Workshop production commits stock and earnings together. UUID request keys and payload hashes distinguish safe replays from mismatched submissions. Historical rates are snapshots.
- Assembly consumes available pooled upper pairs; soles are outside this simplified rule.
- Fundi payments record an already-made fictional payment. They do not contact a financial service.
- Public demo owner powers are reduced independently of general owner permissions.

The backup cipher/archive utilities remain as educational source and regression examples. Demo HTTP, CLI and scheduling paths deny actual recovery operations. Do not connect cloud accounts to this sandbox.
