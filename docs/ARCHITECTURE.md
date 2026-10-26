# How the app works

Laravel builds the pages with Blade. JavaScript handles product search and interactive forms. Controllers check requests and the selected shop. Services handle stock, deliveries and workshop work that changes several records at once.

| Folder | What it does |
|---|---|
| `app/Http/Middleware` | Checks accounts, roles, demo restrictions and response rules |
| `app/Http/Controllers` | Checks input, loads shop records and returns pages |
| `app/Services` | Handles stock moves, deliveries, workshop balances and earnings |
| `app/Support` | Helps with money, receipt snapshots, CSV output and transaction retries |
| `database/migrations` | Defines database tables, constraints and indexes |
| `database/seeders` | Adds fictional sample data and refuses to overwrite existing records |
| `tests` and `tools/test-*` | Checks features and database behavior |

## Rules the code protects

- A cart belongs to one shop. Switching shops clears it; checkout rejects items from another shop.
- Cashiers use catalog prices, even if someone changes a request or leaves an old price in the cart. Managers may override sale prices.
- Refunds use the original sale price and check how much is left to refund while the sale is locked.
- Stock changes lock the relevant balances and retry supported database conflicts. Shops can still have negative stock under the demo's selling rule.
- Deliveries check who can act and which step comes next. Repeating a request must not add stock twice.
- Workshop jobs update stock and earnings together. Request IDs and saved input hashes help detect repeated or changed submissions. Historical rates are saved with each entry.
- Shoe assembly consumes available pooled uppers. Soles are outside this example.
- Worker payment entries record fictional payments; they do not contact a financial service.
- Demo mode restricts the owner account separately from the app's wider owner permissions.

Backup utilities remain as study and test examples. Demo routes, commands and schedules block recovery operations. Do not connect a cloud account.
