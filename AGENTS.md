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