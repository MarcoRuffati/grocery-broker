# Grocery Broker contributor instructions

A local PHP/SQLite prototype with a vanilla frontend. See README.md for startup and limitations, docs/ARCHITECTURE.md for responsibilities and docs/DATA_SOURCES.md for imports and connectors.

- Keep comparisons offline and deterministic; never invent missing prices.
- Keep integer cents, compatible quantity conversion, whole-package rounding and coverage indicators.
- Preserve distinction between fresh, prepared, frozen and dietary variants.
- Use fictional stores in public examples. Do not include brand-specific assets, personal data or machine configuration.
- Keep private data outside public/ and ignored by Git. No scheduler or remote collector is enabled by default.
- Public documentation is English; current application text and dictionary remain Italian. Document localization limitations honestly.
- A failed collector must preserve the last valid snapshot. Validate the entire collection before persistence.
- Tests: php tests/run.php; node --check public/assets/app.js; PHP lint for modified files; python3 tests/http_checks.py against the initial demo at port 8788 (or GROCERY_TEST_URL).
- Check UI changes in a real browser, desktop and mobile. Do not infer browser success from syntax alone.
- Preserve MIT licence and authorship. No real credentials or third-party data in fixtures.
