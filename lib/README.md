# Backend modularization layer

The original public functions are preserved for compatibility, while this directory introduces responsibility-based entry points:

- `Auth.php` — authentication helpers
- `Wallet.php` — token wallet and ledger operations
- `Gateway.php` — OpenAI-compatible gateway handlers
- `Provider.php` — provider registry and model resolution
- `Order.php` — package/order workflow
- `Redeem.php` — redeem-code workflow
- `Database.php` — MySQL-backed storage access
- `Cache.php` — small file-cache layer for frequently requested data

You can progressively migrate controllers to these classes without changing endpoint URLs.
