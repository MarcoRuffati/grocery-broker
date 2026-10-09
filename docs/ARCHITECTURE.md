# Architecture

## Files and responsibilities

- `app/bootstrap.php`: database creation, synthetic store catalogue, settings and shared helpers.
- `app/schema.sql`: SQLite schema and indexes. Initialization is automatic.
- `app/domain.php`: Italian category dictionary, quantity parsing, compatibility checks and basket estimates.
- `app/imports.php`: input validation, CSV preview, transactional offer persistence and optional textual PDF extraction.
- `app/connectors.php`: source interface, empty default registry and snapshot refresh orchestration.
- `public/index.php`: local HTTP boundary, session/CSRF handling, JSON routes and HTML shell.
- `public/router.php`: explicit static asset routing; private files stay outside the document root.
- `public/assets/`: vanilla JavaScript, CSS and original fictional store badges.
- `bin/demo.php`: creates the separate synthetic demo database.
- `bin/sync.php`: invokes registered collectors. With the default registry it reports that none are configured.
- `bin/backup.php`: coherent SQLite backup through `VACUUM INTO`.
- `tests/`: domain and HTTP checks plus synthetic PDF fixtures.

No account, framework, package manager, AI service or frontend build is required. The app makes no external requests during comparison. No OS scheduler is installed by this repository.

## Data and calculation

`GROCERY_DB` overrides the SQLite path; otherwise it is `var/grocery-broker.sqlite`. `var/` is created as needed and excluded from Git. The demo always uses `var/demo.sqlite`.

Money is stored in integer cents. Package sizes are normalized to kg, l or pz (items); conf means packages requested by the user. Weight and volume cannot be converted into one another. For whole packages the calculation rounds up the required count; loose goods use the requested quantity. Monetary results and unit-price displays round to cents.

Only active offers with known conditions participate. Loyalty offers require the corresponding store preference. A minimum-package promotion is used only when the requested purchase reaches its threshold. Arbitrary coupons, 3-for-2 and other unsupported conditions must be excluded from automatic totals.

Unknown products match only their normalized exact name. The dictionary distinguishes prepared and frozen goods and requires a choice for generic chicken. Matching is deliberately limited and does not claim general semantic understanding.

Each basket displays its coverage. An incomplete basket is a subtotal, never a complete estimate. The split basket chooses the lowest checkout estimate for each covered item, without travel costs or stock guarantees.

## HTTP boundary

The built-in PHP server must bind to `127.0.0.1`. Host checks, a session CSRF token, a content security policy and explicit static routing are implemented. This is a trusted local prototype, not a deployment template for an Internet-facing multiuser service.

GET routes: `/api/state`, `/api/compare`, `/api/offers`.

POST routes: `/api/list/parse`, `/api/list`, `/api/preferences`, `/api/store`, `/api/import/preview`, `/api/import`, `/api/offers/delete`, `/api/sync`.

POST requests require `X-CSRF-Token` from the current session. Import confirmation requires `reviewed: true`. PDF import extracts text for manual transcription; it does not associate prices with products or perform OCR.

## Maintenance

Back up the chosen database with `php bin/backup.php` (use the same `GROCERY_DB` setting). Do not copy a running SQLite database by copying only its main file: WAL may contain committed changes. Restoring or resetting data should happen with the server stopped.
