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
You may, at any time, open up the browser and test the `https://deaf.city/studio` webapp on your own. The password to it is "hola" (I will change it later)