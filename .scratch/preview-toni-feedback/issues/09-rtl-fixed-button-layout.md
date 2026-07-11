Status: done

# RTL languages: fixed button layout

## Parent

[PRD](../PRD.md) — Preview Toni feedback (Jul 2026)

## What to build

When UI language is RTL (international Arabic `ar`), **text** reads right-to-left but the **bottom chrome button order stays identical to LTR** — no flex reversal from `direction: rtl` on `.vpc-control-row` / `.vpc-bottom-bar`.

Remove or override:

```css
html[dir="rtl"] .vpc-control-row { direction: rtl; }
html[dir="rtl"] .vpc-bottom-bar { direction: rtl; }
```

Apply RTL only where needed for text blocks (About body, captions), not transport/filter flex order.

Arabic language ids (`arq`, `aeb`) are removed in issue 11; this issue targets `ar` only.

## Acceptance criteria

- [x] With `?lang=ar`, button order matches English layout (spot-check ? / filters / transport / Participants positions)
- [x] About and Participants page text still renders RTL where appropriate
- [x] No menu reorder regression when switching from a LTR language to Arabic

## Blocked by

- [01-unified-player-chrome-phase0.md](01-unified-player-chrome-phase0.md)
- [11-arabic-consolidate-to-international-ar.md](11-arabic-consolidate-to-international-ar.md) (recommended — test with final `ar` id)

## Comments

> Jul 2026: Darija/Tunisian dropped; single international Arabic (`ar`). Toni note on unified language control moved to issue 11.
