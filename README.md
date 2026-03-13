# FAIR DID Manager WordPress

`fairpm/did-manager-wordpress` contains the WordPress-specific layer that was extracted from the core `fairpm/did-manager` package. It provides WordPress header parsing, `readme.txt` parsing, FAIR metadata generation, and a small adapter around the core DID manager for package-aware workflows.

## Features

- Parse WordPress plugin headers and discover the main plugin file
- Parse WordPress.org-style `readme.txt` files outside of a WordPress runtime
- Generate FAIR metadata arrays and `metadata.json` files for plugins and themes
- Detect whether a package path is a plugin or theme
- Inject `Plugin ID` or `Theme ID` values after a DID is created
- Compose the core `FAIR\\DID\\DIDManager` instead of duplicating DID logic

## Installation

```bash
composer require fairpm/did-manager-wordpress
```

For repository development:

```bash
git clone https://github.com/fairpm/did-manager-wordpress.git
cd did-manager-wordpress
composer install
```

## Usage

```php
<?php

require_once 'vendor/autoload.php';

use FAIR\DID\DIDManager;
use FAIR\DID\PLC\PlcClient;
use FAIR\DID\Storage\KeyStore;
use FAIR\WordPress\DID\Parsers\MetadataGenerator;
use FAIR\WordPress\DID\WordPressDIDManager;

$core = new DIDManager(
	new KeyStore(__DIR__ . '/keys.json'),
	new PlcClient(),
);

$wordpress = new WordPressDIDManager($core);
$result = $wordpress->create_package_did(__DIR__ . '/my-plugin', 'my-plugin.example.com', null, true);
$metadata = $wordpress->generate_metadata(__DIR__ . '/my-plugin', did: $result['did']);
```

## Namespaces

- `FAIR\\WordPress\\DID\\Parsers` for header, readme, and metadata helpers
- `FAIR\\WordPress\\DID` for package-aware orchestration around the core DID manager

## Examples

See [examples](examples):

- `01-parse-plugin-headers.php`
- `02-generate-metadata.php`

## Testing

```bash
composer tests
composer lint
composer analyze
```
