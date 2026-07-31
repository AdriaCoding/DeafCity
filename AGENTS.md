## Mandatory skill usage

- After any planning session or grilling session (`/grill-me`, `/grill-me-qa`, `/grill-with-docs`, `/grill-with-docs-qa`), always publish the outcome with `/to-prd` (single feature) or `/to-issues` (multi-issue breakdown). Never end a planning or grilling session without one of these.
- Whenever implementing a plan, PRD, or issue — use `/tdd`. No exceptions.

## Agent skills

### Issue tracker

Issues live as markdown files under `.scratch/`. See `docs/agents/issue-tracker.md`.

### Triage labels

Five canonical triage roles with default label strings. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context layout: `CONTEXT.md` and `docs/adr/` at the repo root. See `docs/agents/domain.md`.

### Studio UI language

The Studio webapp (`studio/`) is Catalan-only for all controls and user-visible text. See `studio/README.md`.

### PHP version

We run two PHP versions — match the tree you are editing:

- **Site root** (`src/`, `preview/`, root `*.php`, etc.) — **PHP 5.6** (vhost default). Use PHP 5–compatible syntax only; follow the style of the file.
- **Studio** (`studio/`) — **PHP 8.4** (`studio/.htaccess`). Use modern idioms already in `studio/src/`; do not use features above 8.4. Lint with `php8.4 -l`.

### Frontend testing

You may test the Studio webapp at `https://deaf.city/studio` (password: `hola`).

When you need a catalog video for manual testing, use **2020_VALENCIA_Aurora_1_HD** (`vimeo_id` `1201722064`, edition Valencia 2020). Details page: `?action=continguts-video&vimeo_id=1201722064`.

### `data/` file ownership (Studio writes as `www-data`)

PHP-FPM runs as `www-data`. Studio must be able to write JSON under `data/` (e.g. `ui-localizations.json`, `studio-config.json`, jobs, captions).

- **Do not leave new/edited `data/` files owned by root.** After creating or overwriting anything under `data/` as root, run:
  `chown -R www-data:www-data -- <paths you touched>`
- Prefer writing as `www-data` when practical (`sudo -u www-data …`).
- Default ACLs on `data/` give `www-data` write access even on root-created files; still fix ownership so listings and backups stay consistent.

The design of the website should be MINIMALISTIC.

## Harnesss
Never touch or read the folders `doble`, `doble2`, `realtime`, `realtime_BW`. Do NOT ever read the secrets file `config/config.php`, you may only check `config/config.example.php`.