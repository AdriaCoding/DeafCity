# Revision benchmark — Gemini models on revision_input.vtt

**Input:** `revision_input.vtt`
**Expected reference:** `revision_expected.vtt`
**Pricing:** Google Gemini API standard tier (text), June 2026.

Est. cost = (prompt tokens × input $/M) + ((output + thoughts) tokens × output $/M).

## Results

| Model | Wall (s) | Prompt tokens | Output tokens | Thoughts tokens | Total tokens | Est. cost (USD) | Lines >60 | Text match | Error |
|---|---|---|---|---|---|---|---|---|---|
| gemini-2.5-flash | 59.2 | 1931 | 1106 | 12482 | 15519 | 0.034549 | 0 | 19/27 |  |
| gemini-2.5-flash-lite | 2.7 | 1931 | 658 | 0 | 2589 | 0.000456 | 0 | 0/12 |  |
| gemini-2.5-pro | 86.1 | 1931 | 1141 | 11550 | 14622 | 0.129324 | 1 | 4/27 |  |
| gemini-3-flash-preview | 57.6 | 1931 | 1076 | 14040 | 17047 | 0.046314 | 0 | 5/26 |  |
| gemini-3.5-flash | 44.5 | 1931 | 1142 | 9308 | 12381 | 0.096946 | 0 | 22/27 |  |
| gemini-3.1-flash-lite | 1.9 | 1931 | 580 | 0 | 2511 | 0.001353 | 9 | 0/10 |  |

