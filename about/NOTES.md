# Sign-language map — dataset & regeneration

Canonical documentation for the map at `/about/`. **Read this before
changing map data, coordinates, categories, or the regeneration pipeline.**

Glottolog is the versioned linguistic source. **DEAF.city owns editorial
selection, branch taxonomy, coordinate fixes, and exclusions.**

---

## Files

| File | Role |
|------|------|
| `data/languages.json` | GeoJSON consumed by Leaflet (`js/sign_language_map.js`) |
| `data/languages.meta.json` | Provenance, counts, fallbacks, overrides (not loaded by the map) |
| `scripts/regenerate_languages_json.py` | **Only** supported way to rebuild the dataset |
| `scripts/glottolog-source.json` | Expected Glottolog version, DOI, CLDF download URL |
| `tests/languages_json_validate_test.py` | Run after every regeneration |
| `about/map.php` | Map markup + Glottolog attribution |
| `js/sign_language_map.js` | Legend branches, default visibility |

**Do not hand-edit `data/languages.json` for structural or bulk changes** — edits
will be lost on the next regeneration. Exception: you may spot-check output, then
encode the fix in the script (see overrides below) and regenerate.

After writing `data/languages.json` or `data/languages.meta.json` as root, run:

```bash
chown www-data:www-data -- data/languages.json data/languages.meta.json
```

---

## Source

- **Glottolog 5.3** — DOI [10.5281/zenodo.18840935](https://doi.org/10.5281/zenodo.18840935)
- **CLDF export** — DOI [10.5281/zenodo.18840967](https://doi.org/10.5281/zenodo.18840967)
- **Family:** `sign1238` (Sign Language)
- **Input:** versioned Zenodo CLDF `languages.csv` (not the live Glottolog API)

Check `scripts/glottolog-source.json` for the pinned version. When Glottolog
releases a new stable version, update that file, download the new CLDF zip, regenerate,
and update the attribution string in `about/map.php` if the version changed.

---

## What appears on the map

| Scope | Count | On map? |
|-------|------:|---------|
| Glottolog `sign1238` language-level languoids | 227 | **yes** |
| Glottolog `sign1238` dialect-level languoids | 71 | **no** (intentional) |
| **Map features** | **227** | |

Dialect-level languoids are documented in metadata but **must not** be re-added to
the public map unless the product owner explicitly requests it (they stack on parent
language coordinates and clutter the legend).

---

## DEAF.city branch taxonomy

Legacy Glottolog ~2018 htmlmap branch ids (not Glottolog 5.x’s current tree):

| Branch | Label | Legend default |
|--------|-------|----------------|
| 982 | Deaf Sign Language | on |
| 980 | Rural Sign Language | on |
| 978 | Auxiliary Sign Systems | **off** |
| 979 | Pidgin Sign Language | **off** |
| 981 | Family Sign Language | **off** |

Current counts: 982=141, 980=79, 978=4, 979=1, 981=2.

Branch assignments are resolved in this order inside `regenerate_languages_json.py`:

1. Legacy export (`/srv/www/deaf.city/website_old/data/languages.json`) when present
2. Explicit maps: `NEW_BRANCH_980`, `NEW_BRANCH_982`, `NEW_BRANCH_981`
3. Otherwise the script **fails** — add the glottocode to the appropriate map; do
   not silently default to 982 or 980

To change legend labels or default visibility, edit `js/sign_language_map.js`
(`branchesdef`, `defaultOffBranches`).

---

## Overriding Glottolog source data

The script supports **three coordinate mechanisms** (in resolution order):

### 1. `COORD_OVERRIDES` — wrong Glottolog coordinates

Use when CLDF **has** coordinates but they are **incorrect** (typo, wrong hemisphere,
misplaced point). Overrides run **before** CLDF values.

```python
COORD_OVERRIDES: dict[str, dict] = {
    "cata1241": {
        "latitude": 41.635,
        "longitude": 1.97,
        "reason": "Glottolog 5.3 CLDF has longitude -1.97; corrected for Catalonia",
    },
}
```

**Agent workflow when a user reports wrong map coordinates:**

1. Confirm the glottocode (`id` in GeoJSON) and the correct lat/lon.
2. Add or update an entry in `COORD_OVERRIDES` with a clear `reason`.
3. Regenerate (see below) — do **not** leave a manual-only fix in `languages.json`.
4. Optionally add an assertion in `tests/languages_json_validate_test.py`
   for high-value entries (see `cata1241` test).
5. Verify `coordinate_overrides` in `data/languages.meta.json` lists the entry.

### 2. `COORD_FALLBACKS` — missing Glottolog coordinates

Use only when CLDF ** lacks** latitude/longitude. Prefer documented country centroids;
never invent silent approximate points. Each entry needs a `reason`.

### 3. Glottolog CLDF (default)

Used when no override or fallback applies.

---

## Regeneration

```bash
# Download CLDF once per Glottolog release (example: 5.3)
curl -L -o /tmp/glottolog-cldf-v5.3.zip \
  "https://zenodo.org/api/records/18840967/files/glottolog/glottolog-cldf-v5.3.zip/content"
unzip -q /tmp/glottolog-cldf-v5.3.zip -d /tmp/glottolog-5.3

cd /srv/www/deaf.city/public_html

python3 scripts/regenerate_languages_json.py \
  --cldf /tmp/glottolog-5.3/glottolog-glottolog-cldf-*/cldf/languages.csv \
  --output data/languages.json \
  --meta-output data/languages.meta.json

chown www-data:www-data -- data/languages.json data/languages.meta.json

python3 tests/languages_json_validate_test.py
php8.4 tests/about_page_test.php   # optional smoke test
```

Validate existing output without regenerating:

```bash
python3 scripts/regenerate_languages_json.py --validate-only
```

---

## GeoJSON schema (do not break)

Consumers expect Leaflet GeoJSON with:

- `properties.branch` — integer (978, 979, 980, 981, 982)
- `properties.icon` — base64 SVG data URI
- `properties.name` — display name
- `properties.language` — legacy object with `pk`, `name`, `id`, `latitude`, `longitude`
- `geometry.coordinates` — `[longitude, latitude]`

Do not add top-level metadata to `languages.json`; keep provenance in
`languages.meta.json`.

---

## Public map

- URL: `https://deaf.city/about/`
- Attribution footer in `about/map.php` (Glottolog version + DEAF.city note)

When describing counts on the site, say DEAF.city maps a **curated selection** based
on Glottolog — do not claim 227 is the definitive worldwide sign language count.
