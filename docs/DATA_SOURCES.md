# Data sources and imports

No live retailer connector is shipped. All four catalogue entries and badges are fictional. Source URLs point to reserved example domains. Original pilot collectors, downloaded retailer HTML, logos, real prices and installed scheduler configuration are excluded.

## Assisted import

In settings, select a default store, upload a CSV or enter an offer, review every field and confirm. `examples/offers.csv` is a synthetic format example with historical dates; adjust its dates before comparing. CSV dates use YYYY-MM-DD. Separators may be semicolons or commas; quote fields containing a separator.

Required offer values are title, price, package size, unit, start/end dates and an HTTPS source URL. Supply `store_id` and `chain` when importing through the JSON API; the interface can derive them from the default store. Source IDs and store IDs must already exist in the catalogue. This release has no store-creation UI: edit the seed catalogue before initializing a new database, or add a migration for an existing database.

`price` is the package price for packaged goods. For loose goods set `loose=1` and specify the base quantity in `pack_size`. Set `loyalty=1` for card-only offers, `min_packs` for supported quantity thresholds, and `conditions_known=0` when the promotion cannot be interpreted safely.

A textual PDF is extracted with Poppler and displayed for manual transcription. Image-only PDFs require manual entry. Automated OCR is outside this release.

## Adding a connector

Implement `Connector::collect(): array` and register the instance in `configuredConnectors()`, keyed by a source ID already in `sources`. A minimal implementation can read a file supplied by an authorized publisher rather than scrape a website:

```php
final class AuthorizedFileConnector implements Connector {
    public function collect(): array {
        $path = dataDir() . '/authorized-offers.csv';
        requireValue(is_file($path), 'Publisher file missing.');
        $offers = [];
        foreach (previewCsv(file_get_contents($path)) as $row) {
            // Explicit local store mapping prevents accidental nationwide scope.
            $row['chain'] = 'market-a';
            $row['store_id'] = 'market-a-main';
            $offers[] = validateOffer($row, 'automatic');
        }
        return $offers;
    }
}

function configuredConnectors(): array {
    return ['market-a' => new AuthorizedFileConnector()];
}
```

Replace the existing registry function; do not define it twice. Install the class in `app/connectors.php` or require a separate project file from there. Adapt the source/store IDs and publisher format to your own authorized data. Then run `php bin/sync.php`.

A connector must validate the entire snapshot before returning it. A successful nonempty snapshot replaces earlier automatic offers for that source, while assisted imports remain. Returning a partial collection as a complete snapshot may remove valid offers. Exceptions or empty collections retain the last valid data, with an error recorded in `runs`. Offers still expire by their published dates.

Collectors must establish permission, applicability to stores, prices, formats, dates and conditions. Any future HTTP implementation needs an explicit host allowlist, timeouts, response-size limits, bounded retries, caching and respect for source restrictions. Do not bypass authentication or blocks. A successful request does not establish reuse rights.

The CLI also supports `--if-due` (Monday 07:00, Europe/Rome, one-hour retry interval). Schedule it yourself only after configuring and testing a source; no weekly job is installed automatically. Demo databases refuse live refresh.
