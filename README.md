# TYPO3 Extension `ns_license`

[![Latest Stable Version](https://img.shields.io/badge/Stable-14.4.2-success)](https://extensions.typo3.org/extension/ns_license/)
[![TYPO3 14](https://img.shields.io/badge/TYPO3-14-important.svg?logo=typo3)](https://get.typo3.org/version/14)
[![TYPO3 13](https://img.shields.io/badge/TYPO3-13-important.svg?logo=typo3)](https://get.typo3.org/version/13)
[![TYPO3 12](https://img.shields.io/badge/TYPO3-12-important.svg?logo=typo3)](https://get.typo3.org/version/12)
[![Packagist](https://img.shields.io/badge/Packagist-nitsan%2Fns--license-informational)](https://packagist.org/packages/nitsan/ns-license)

**License Manager** for T3Planet products: activate and validate license keys, browse the product catalog, manage domains, and download licensed extensions from the TYPO3 backend.

## Features

* Activate, deactivate, and repair licenses  
* Validate license status and show license details  
* Browse the in-module catalog (AI Universe, Extensions, Templates) with product detail and release notes  
* Download licensed T3Planet extensions (classic / non-Composer installs)  
* Manage registered domains and view authentication logs  
* **Get New License** right from the backend — pick any T3Planet product and start a **free 30‑day trial** (email OTP) or **buy a license** via secure t3planet.shop checkout (Pabbly), then activate the emailed key  
* Extend trial licenses when available  
* Works with free **EXT:ns_t3af** license keys (Packagist install; license registration only)

## Requirements

| | |
|---|---|
| TYPO3 | 12 – 14 |
| Extension Manager | 12 – 14 |

## Installation

### Composer

```bash
composer require nitsan/ns-license
```

### TER

https://extensions.typo3.org/extension/ns_license/

## Usage

1. Install and activate `ns_license`.
2. Open **Admin Tools → T3Planet License** (TYPO3 14: **System → T3Planet License**).
3. Enter your license key to activate the product for the current domain.
4. Browse **AI Universe**, **Extensions**, or **Templates** to explore products, open detail, and start trial or purchase.
5. Don't have a key yet? Use **Get New License** in the module header:
   - **Start Free Trial** — confirm your email with the one‑time code; a 30‑day trial key is issued.
   - **Buy / Purchase** — review the annual price, accept terms, and complete payment securely on **t3planet.shop** (Pabbly). The license key is emailed after payment; then **Activate** it in this module.

## Documentation

https://docs.t3planet.com/en/latest/License/LicenseActivation/Index.html

## Links

| | |
|---|---|
| **Repository** | https://github.com/nitsan-technologies/ns_license |
| **Issues** | https://github.com/nitsan-technologies/ns_license/issues |
| **Packagist** | https://packagist.org/packages/nitsan/ns-license |
| **Support** | https://t3planet.de/support |
