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

The whole site runs **PHP 8.4** — set once, site-wide, by the Apache vhost include (outside the repo). No directory opts down via its own `.htaccess` (the old `studio/.htaccess` pinning 8.4 was deleted as redundant once 8.4 became the default). CLI is also 8.4; lint with `php8.4 -l`.

`studio/.htaccess` exists again, but only to widen the site-wide Content-Security-Policy for the Studio (CodeMirror from esm.sh, Google Fonts) — it carries **no** `SetHandler` and does not change the PHP version. Don't delete it as "the old PHP-pinning file".

Some `preview/`/site-root files still carry old "PHP 5.6 compatible" doc comments and avoid PHP 7+ syntax (`??`, etc.) from before the 2026-07-29 migration — that's now a style choice, not a hard constraint. Modern idioms are fine anywhere. If a directory ever needs to run a *different* PHP version, add `<dir>/.htaccess` with a `SetHandler` block pointing at that version's FPM socket (mirrors the old `studio/.htaccess` pattern).

### Frontend testing

You may test the Studio webapp at `https://deaf.city/studio` (password: `hola`).

When you need a catalog video for manual testing, use **2020_VALENCIA_Aurora_1_4k** (`vimeo_id` `1211616576`, edition Valencia 2020). Details page: `?action=continguts-video&vimeo_id=1211616576`. (The previously listed `1201722064` is no longer in the catalog — check `data/catalog.json` before trusting this id.)

### `data/` file ownership (Studio writes as `www-data`)

PHP-FPM runs as `www-data`. Studio must be able to write JSON under `data/` (e.g. `ui-localizations.json`, `studio-config.json`, jobs, captions).

- **Do not leave new/edited `data/` files owned by root.** After creating or overwriting anything under `data/` as root, run:
  `chown -R www-data:www-data -- <paths you touched>`
- Prefer writing as `www-data` when practical (`sudo -u www-data …`).
- Default ACLs on `data/` give `www-data` write access even on root-created files; still fix ownership so listings and backups stay consistent.

The design of the website should be MINIMALISTIC.

## Harnesss
Never touch or read the folders `doble`, `doble2`, `realtime`, `realtime_BW`. Do NOT ever read the secrets file `config/config.php`, you may only check `config/config.example.php`.