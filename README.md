# Grocery Broker

A PHP and SQLite project for comparing local grocery offers and planning where to buy each item on a shopping list.

## Project status

**Development is paused. This repository currently contains documentation only.**

A local prototype was developed using PHP, SQLite, HTML, CSS and vanilla JavaScript. Preparing its code and a synthetic demo for this repository is a possible next step; neither is included yet. There is currently no installation or runnable release here.

The main obstacle is reliable offer data: there is no verified, free, documented API covering the retailers needed for the original pilot. Public pages and promotional leaflets can provide partial information, but collection requires ongoing maintenance and validation. This project does not promise comprehensive coverage or a production-ready data service.

## What the prototype explores

- Shopping lists with explicit quantities and preferred stores.
- Comparable unit prices, such as price per kilogram, litre or item.
- Checkout estimates that account for whole packages and required quantities.
- Splitting a shopping list between stores or comparing a basket at one store.
- Coverage indicators: missing prices remain unknown, never zero.
- Offer validity, loyalty conditions, source references and assisted imports.

For example, a 300 g package priced at 1.50 costs 5.00 per kilogram. Buying at least 1 kg requires four packages: 6.00 for 1.2 kg. The lowest unit price and the lowest checkout cost are not always the same.

## Direction for future development

Separate the comparison engine from retailer-specific data sources. A future code release should include a synthetic demo, documented connector contracts and import examples, so developers can adapt the project to their own region without depending on one live source.

This repository does not currently provide live offers, a public offers API, retailer credentials, third-party datasets or an automated leaflet-reading service. No retailer affiliation or endorsement is implied.

## Data collection and responsible use

Software availability does not grant permission to access, extract, retain or redistribute someone else's data. Anyone operating a collector must establish that their access and intended use are permitted, respect applicable terms and technical restrictions, and obtain any necessary authorizations.

Do not bypass authentication, CAPTCHAs, access controls or explicit blocks. Minimize requests and data retention, avoid collecting personal information, and stop collection when permission or source validity is uncertain. A publicly accessible page is not evidence of a reusable data licence.

Offer prices, dates, package sizes and store applicability must be verified before use. Estimates do not guarantee shelf availability, checkout prices or savings. Historical receipts must not be presented as current offers.

See [DISCLAIMER.md](DISCLAIMER.md) for the scope of the project and limitations of responsibility.

## Licence and attribution

Original project materials are provided under the [MIT License](LICENSE). Commercial use, modification and redistribution are permitted. You must retain the copyright notice and the MIT permission notice in all copies or substantial portions of the materials.

Please also credit "Grocery Broker by MarcoRuffati" and link to the original repository in your documentation or credits. This additional acknowledgement is appreciated, but is not an extra licence condition. MIT does not require an on-screen credit or the publication of modifications.

The licence covers original project materials, not third-party data, images, trademarks or access to external services. [DISCLAIMER.md](DISCLAIMER.md) explains operational responsibilities and limitations; it does not add restrictions to the MIT licence or remove rights granted by it.

## Authorship and contact

Created by [MarcoRuffati](https://github.com/MarcoRuffati).

Original project: <https://github.com/MarcoRuffati/grocery-broker>.

For questions about the project or permission requests, open a repository issue without including credentials, personal information or confidential material.
