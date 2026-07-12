---
name: capell-tags-development
description: Use when editing Capell Tags, taggable relationships, or tag inputs.
---

# Capell Tags

Tag management, taggable relationships, reusable tag input, and model concerns.

## Look

- `packages/tags/src`
- `packages/tags/docs`
- `packages/tags/README.md`

## Rules

- Keep tags generic across content types.
- Taggable writes must respect model relationships, not inline pivot hacks.
- Filament tag options should come from package APIs.
- Run `vendor/bin/pest packages/tags/tests`.
