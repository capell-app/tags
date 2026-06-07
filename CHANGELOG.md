# Changelog

All notable changes to `capell-app/tags` will be documented in this file.

## Unreleased

### 2026-06-04

- Bound the admin tag `type` field to `TagTypeEnum` through an enum-backed Select instead of free text.
- Updated tag factories to generate only supported enum-backed tag types.
- Declared Tags taxonomy capabilities and the `tags` cache tag in `capell.json`.
- Updated docs and tests for the enum-backed type and manifest capability contracts.

- Prepared package metadata and documentation for ongoing Capell 4.x package work.

## 2026-06-03

- Improved marketplace and Composer metadata for the Tags package.
- Promoted existing product screenshots into the package manifest.
- Added real Tags package health diagnostics for storage tables, tag model configuration, install status, and admin resource registration.
