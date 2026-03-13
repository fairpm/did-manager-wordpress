# FAIR DID Manager WordPress Examples

This directory contains WordPress-specific examples for `fairpm/did-manager-wordpress`.

## Running Examples

```bash
cd did-manager-wordpress
php examples/01-parse-plugin-headers.php
php examples/02-generate-metadata.php
```

## Example Overview

### 01-parse-plugin-headers.php
- Parse standard and minimal plugin headers
- Parse a plugin file from disk
- Inspect plugin IDs and other header fields
- Show the parser output shape used by downstream tooling

### 02-generate-metadata.php
- Parse headers and `readme.txt`
- Generate FAIR metadata arrays
- Attach a DID to generated metadata
- Override the slug and inspect the final JSON structure
