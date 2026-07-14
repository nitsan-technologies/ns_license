# TYPO3 Extension `ns_license`

[![Latest Stable Version](https://img.shields.io/badge/Stable-14.3.0-success)](https://extensions.typo3.org/extension/ns_license/)
[![TYPO3 14](https://img.shields.io/badge/TYPO3-14-important.svg?logo=typo3)](https://get.typo3.org/version/14)
[![TYPO3 13](https://img.shields.io/badge/TYPO3-13-important.svg?logo=typo3)](https://get.typo3.org/version/13)
[![TYPO3 12](https://img.shields.io/badge/TYPO3-12-important.svg?logo=typo3)](https://get.typo3.org/version/12)
[![License](https://img.shields.io/badge/license-MIT-blue)](LICENSE)
[![Packagist](https://img.shields.io/badge/Packagist-nitsan%2Fns--license-informational)](https://packagist.org/packages/nitsan/ns-license)

**License Manager** for T3Planet products: activate and validate license keys, manage domains, and download licensed extensions from the TYPO3 backend.

## Features

* Activate, deactivate, and repair licenses  
* Validate license status and show license details  
* Download licensed T3Planet extensions (classic / non-Composer installs)  
* Manage registered domains and view authentication logs  
* Extend trial licenses when available  
* Works with free **EXT:ns_t3af** license keys (Packagist install; license registration only)

## Requirements

| | |
|---|---|
| TYPO3 | 12 – 14 |
| Extension Manager | 12 – 14 |
| PHP | as required by your TYPO3 version |

## Installation

### Composer

```bash
composer require nitsan/ns-license
```

### TER

https://extensions.typo3.org/extension/ns_license/

## Usage

1. Install and activate `ns_license`.
2. Open **Admin Tools → T3Planet License**.
3. Enter your license key to activate the product for the current domain.

For **AI Foundation (ns_t3af)**, install the extension from Packagist/TER first, then activate the free lifetime key here.

## Documentation

https://docs.t3planet.com/en/latest/License/LicenseActivation/Index.html

## Links

| | |
|---|---|
| **Repository** | https://github.com/nitsan-technologies/ns_license |
| **Issues** | https://github.com/nitsan-technologies/ns_license/issues |
| **Packagist** | https://packagist.org/packages/nitsan/ns-license |
| **Support** | https://t3planet.de/support |
