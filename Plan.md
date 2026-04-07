# Taro Fortune Plan

## Goal
- Maintain a WordPress-first tarot plugin with `fortune -> cards[]` data structure.
- Keep admin CRUD, dynamic shortcode rendering, and animated frontend card interaction.
- Keep settings minimal and remove multilingual complexity.

## Implemented
- [x] Fortune-first JSON architecture (`data/fortunes.json`) and nested card schema.
- [x] Admin flow: fortune CRUD + per-fortune card CRUD.
- [x] Dynamic shortcode registration: `[taro_fortune_<slug_alias>]`.
- [x] Legacy shortcode compatibility: `[taro_fortune id="fortune-id"]`.
- [x] Legacy data migration from `cards.json` when `fortunes.json` is missing.
- [x] Frontend interaction: draw cap by card count, redraw, flip open/close, z-index priority.
- [x] Security baseline: nonce checks, `manage_options` checks, sanitization/escaping.
- [x] Settings simplified to single-language runtime (no multilingual keys).
- [x] Static prototype files removed; plugin files are the single source of truth.

## Next
- Validate end-to-end behavior in real WordPress runtime.
- Confirm shortcode rendering and admin save/delete flow on production-like environment.
