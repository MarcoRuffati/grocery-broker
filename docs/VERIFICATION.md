# Verification

Verified on 9 October 2026 with PHP 8.0.30, SQLite, mbstring, Poppler, Python 3 and a local Chrome instance. PHP 8.0 is the syntax minimum, not a recommendation for an unsupported runtime; use a supported PHP release for your own installation.

## Automated checks

- 46 PHP checks: parsing, g/ml conversion, whole packages, loose goods, incompatible units, loyalty, minimum-package conditions, expiry, invalid dates, ambiguity, transactional imports, idempotence, snapshot replacement, failure preservation and demo isolation.
- 16 HTTP scenarios against the separate demo: totals, normalization, imports, removal, CSRF, Host checks, private-file protection and demo refresh refusal.
- PHP syntax and JavaScript syntax checks.

The PHP suite uses a temporary SQLite database and removes its database sidecars afterward. HTTP tests target the demo at `http://127.0.0.1:8788`, overridable with `GROCERY_TEST_URL`. Start from the initial demo list; these tests create and remove one synthetic offer.

Demo reference totals: split basket 14.15 EUR, all four products covered. Example markets A, B and D have complete baskets of 20.91, 15.23 and 16.14 EUR respectively. C has a 14.36 EUR subtotal covering three of four products. These are synthetic prices.

## Browser checks

Rendered the list, comparison, store selection and settings views. Checked desktop at 1365 px and mobile at 390 px. No JavaScript exceptions or HTTP errors were observed in the checked views. At mobile width the price table scrolls inside its container; the page itself does not overflow.

Screenshots: [desktop](demo-desktop.png) and [mobile](demo-mobile.png). Both show synthetic offers and fictional stores.

## Limits

No live source was queried or certified for this release. No production hosting, scheduling or online purchasing was deployed. UI text and product matching remain Italian; localization, other currencies and postal formats require further work. Browser checks do not constitute a full accessibility audit.
