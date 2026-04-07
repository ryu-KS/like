# Development Log

## 2026-04-03

### Architecture
- Confirmed plugin-first structure as the active runtime target.
- Kept `fortunes.json` as primary data source with legacy migration from `cards.json`.

### Multilingual Removal
- Removed multilingual settings keys from defaults and runtime data:
  - `frontend_default_language`
  - `enabled_languages`
- Removed multilingual controls from admin settings page and save handler.
- Simplified frontend runtime config by removing `labels` and language-based branches.
- Frontend fallback text is now fixed Korean copy.

### Static Prototype Cleanup
- Removed root static prototype files:
  - `index.html`
  - `script.js`
  - `script copy.js`
  - `style.css`
- Confirmed `img/` assets were only referenced by the deleted prototype and removed the folder.

### Current Status
- Plugin behavior and settings are simplified for single-language operation.
- Admin CRUD, shortcode rendering, and frontend card UX remain intact.