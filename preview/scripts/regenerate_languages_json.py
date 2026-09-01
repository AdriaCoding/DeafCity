#!/usr/bin/env python3
"""
Regenerate data/languages.json from Glottolog sign1238 (versioned CLDF release).

Agent documentation: preview/about/NOTES.md
(Coordinate overrides, fallbacks, branch taxonomy, regeneration workflow.)

DEAF.city editorial taxonomy (legacy Glottolog ~2018 htmlmap branch ids):
  982  Deaf Sign Language
  980  Rural Sign Language
  978  Auxiliary Sign Systems (legend default off)
  979  Pidgin Sign Language
  981  Family Sign Language

All Glottolog language-level languoids under sign1238 are mapped. Dialect-level
languoids are omitted from the public map. Branch assignments are preserved from
the legacy export and explicit editorial maps below.

Expected input: Glottolog CLDF languages.csv (see preview/scripts/glottolog-source.json).

Usage:
  # Download CLDF once (Glottolog 5.3 example):
  curl -L -o /tmp/glottolog-cldf-v5.3.zip \\
    "https://zenodo.org/api/records/18840967/files/glottolog/glottolog-cldf-v5.3.zip/content"
  unzip /tmp/glottolog-cldf-v5.3.zip -d /tmp/glottolog-5.3

  python3 preview/scripts/regenerate_languages_json.py \\
    --cldf /tmp/glottolog-5.3/glottolog-glottolog-cldf-*/cldf/languages.csv \\
    --output data/languages.json \\
    --meta-output data/languages.meta.json

  chown www-data:www-data -- data/languages.json data/languages.meta.json
"""
from __future__ import annotations

import argparse
import base64
import csv
import json
import sys
from datetime import date
from pathlib import Path

SCRIPT_DIR = Path(__file__).resolve().parent
SOURCE_CONFIG = json.loads((SCRIPT_DIR / "glottolog-source.json").read_text(encoding="utf-8"))

LAYER = "sign1238"
FAMILY_ID = "sign1238"
AUXILIARY_GLOTTOID = "auxi1235"
INCLUDED_BRANCHES = frozenset({978, 979, 980, 981, 982})

# Languages added after the 202-feature legacy export (Glottolog 5.x), classified 980.
NEW_BRANCH_980: dict[str, str] = {
    "alba1273": "Albarradas Sign Language — village/community system",
    "arab1399": "Arab El-Naim Sign Language — village/community system",
    "cena1234": "Cena — village/community system",
    "dadh1234": "Dadhkai Village Sign Language — village system",
    "douz1234": "Douz Sign Language — village/community system",
    "einm1234": "Ein Mahal Sign Language — village/community system",
    "fiji1246": "Fiji Sign Language — small-community system",
    "ibok1234": "Ibokun Sign Language — village/community system",
    "isya1234": "Isyarat Lama Asli Bali — indigenous/community system",
    "isya1235": "Isyarat Lama Cicendo — indigenous/community system",
    "ling1271": "Língua de sinais Fortalezinha — small-community system",
    "ling1272": "Língua de sinais de Caiçara — small-community system",
    "ling1273": "Língua de sinais do Uiramutã — small-community system",
    "maga1273": "Magajingari Sign Language — village/community system",
    "mave1234": "Mavea Sign Language — village/community system",
    "meem1235": "Meemul Ch'aab'al — indigenous/community system",
    "moun1255": "Mount Avejaha Sign Language — village system (PNG)",
    "neba1243": "Nebaj Shared Homesign Systems — shared homesign cluster",
    "nuev1238": "Nueva Vida Maijuna Sign Language — village/community system",
    "tere1282": "Terena Sign Language — indigenous/community system",
    "wimb1234": "Wimbum Sign Language — village/community system",
}

# Languages added after the legacy export, classified 982 (national/community standards).
NEW_BRANCH_982: dict[str, str] = {
    "bhut1234": "Bhutanese Sign Language — national/community standard",
    "mala1549": "Malawian Sign Language — national/community standard",
    "rwan1246": "Rwandan Sign Language — national/community standard",
    "seyc1234": "Seychelles Sign Language — national/community standard",
    "sout3410": "South Sudanese Sign Language — national/community standard",
}

NEW_BRANCH_981: dict[str, str] = {
    "zina1239": "Zinacantec family homesign — family sign language",
}

# Map marker palette — muted complements to brand green (#007800); readable on OSM tiles.
BRANCH_ICONS = {
    978: "#6B5B95",  # Auxiliary
    979: "#9B4D7A",  # Pidgin
    980: "#B85C38",  # Rural
    981: "#4A6670",  # Family
    982: "#1B6B93",  # Deaf Sign Language
}
BRANCH_ICON_STROKE = "#111111"

# Explicit coordinate fallbacks when Glottolog CLDF has no coordinates.
# Only country centroids — never silent approximate points.
COORD_FALLBACKS: dict[str, dict] = {
    "mala1549": {
        "country": "MW",
        "latitude": -13.254,
        "longitude": 34.301,
        "reason": "Malawi country centroid; Glottolog 5.3 CLDF has no coordinates",
    },
    "moun1255": {
        "country": "PG",
        "latitude": -6.315,
        "longitude": 143.955,
        "reason": "Papua New Guinea country centroid; Glottolog 5.3 CLDF has no coordinates",
    },
    "tere1282": {
        "country": "BR",
        "latitude": -14.235,
        "longitude": -51.925,
        "reason": "Brazil country centroid; Glottolog 5.3 CLDF has no coordinates",
    },
}

# Editorial coordinate overrides when Glottolog CLDF coordinates are wrong.
# Checked before CLDF values; document every override and its rationale.
COORD_OVERRIDES: dict[str, dict] = {
    "cata1241": {
        "latitude": 41.635,
        "longitude": 1.97,
        "reason": (
            "Glottolog 5.3 CLDF has longitude -1.97 (western hemisphere); "
            "corrected to 1.97 for Catalonia"
        ),
    },
}


def svg_icon(hex_color: str) -> str:
    svg = (
        '<svg  xmlns="http://www.w3.org/2000/svg"\n'
        '      xmlns:xlink="http://www.w3.org/1999/xlink" height="40" width="40">\n'
        f'  <circle cx="20" cy="20" r="14" style="fill:{hex_color};stroke:{BRANCH_ICON_STROKE};'
        'stroke-width:1px;stroke-linecap:round;stroke-linejoin:round;"/>\n'
        "</svg>"
    )
    b64 = base64.b64encode(svg.encode()).decode()
    return f"data:image/svg+xml;base64,{b64}"


def load_sign_languoids(cldf_path: Path, level: str) -> list[dict]:
    rows: list[dict] = []
    with cldf_path.open(newline="", encoding="utf-8") as handle:
        for row in csv.DictReader(handle):
            if row["Family_ID"] == FAMILY_ID and row["Level"] == level:
                rows.append(row)
    return rows


def load_legacy_branches(legacy_path: Path | None) -> dict[str, int]:
    if legacy_path is None or not legacy_path.is_file():
        return {}
    data = json.loads(legacy_path.read_text(encoding="utf-8"))
    out: dict[str, int] = {}
    for feature in data.get("features", []):
        branch = feature.get("properties", {}).get("branch")
        gid = feature.get("id")
        if gid and branch in INCLUDED_BRANCHES:
            out[gid] = branch
    return out


def resolve_branch(gid: str, legacy: dict[str, int]) -> tuple[int | None, str]:
    if gid in legacy:
        return legacy[gid], "legacy export"
    if gid in NEW_BRANCH_980:
        return 980, NEW_BRANCH_980[gid]
    if gid in NEW_BRANCH_982:
        return 982, NEW_BRANCH_982[gid]
    if gid in NEW_BRANCH_981:
        return 981, NEW_BRANCH_981[gid]
    return None, "needs editorial branch assignment (978, 979, 980, 981, or 982)"


def make_feature(gid: str, name: str, branch: int, lon: float, lat: float) -> dict:
    return {
        "type": "Feature",
        "properties": {
            "icon": svg_icon(BRANCH_ICONS[branch]),
            "branch": branch,
            "language": {
                "pk": branch,
                "name": name,
                "longitude": lon,
                "latitude": lat,
                "id": gid,
            },
            "name": name,
        },
        "geometry": {"type": "Point", "coordinates": [lon, lat]},
        "id": gid,
    }


def resolve_coords(row: dict, gid: str) -> tuple[float, float, str] | None:
    if gid in COORD_OVERRIDES:
        override = COORD_OVERRIDES[gid]
        return (
            override["longitude"],
            override["latitude"],
            f"override:{override['reason']}",
        )

    lat = (row.get("Latitude") or "").strip()
    lon = (row.get("Longitude") or "").strip()
    if lat and lon:
        return float(lon), float(lat), "glottolog_cldf"

    if gid in COORD_FALLBACKS:
        fb = COORD_FALLBACKS[gid]
        return fb["longitude"], fb["latitude"], f"fallback:{fb['reason']}"

    return None


def build_collection(cldf_path: Path, legacy_path: Path | None) -> tuple[dict, dict]:
    legacy = load_legacy_branches(legacy_path)
    all_sign_langs = load_sign_languoids(cldf_path, "language")
    dialect_count = len(load_sign_languoids(cldf_path, "dialect"))
    features: list[dict] = []
    skipped_no_coords: list[dict] = []
    needs_review: list[dict] = []
    coord_sources: dict[str, str] = {}

    for row in sorted(all_sign_langs, key=lambda r: r["Name"].lower()):
        gid = row["Glottocode"]
        branch, branch_note = resolve_branch(gid, legacy)
        if branch is None:
            needs_review.append({"id": gid, "name": row["Name"], "reason": branch_note})
            continue

        coords = resolve_coords(row, gid)
        if coords is None:
            skipped_no_coords.append({"id": gid, "name": row["Name"], "branch": branch})
            continue

        lon, lat, coord_source = coords
        coord_sources[gid] = coord_source
        features.append(make_feature(gid, row["Name"], branch, lon, lat))

    branches: dict[int, int] = {}
    for feature in features:
        b = feature["properties"]["branch"]
        branches[b] = branches.get(b, 0) + 1

    meta = {
        "source": "Glottolog",
        "source_version": SOURCE_CONFIG["version"],
        "source_release_year": SOURCE_CONFIG["release_year"],
        "source_doi": SOURCE_CONFIG["doi"],
        "source_cldf_doi": SOURCE_CONFIG["cldf_doi"],
        "source_family": FAMILY_ID,
        "generation_date": date.today().isoformat(),
        "editorial_note": (
            "DEAF.city applies its own branch classification (978/979/980/981/982). "
            "Only language-level languoids under sign1238 are mapped; "
            f"{dialect_count} dialect-level languoids are omitted from the public map."
        ),
        "glottolog_sign1238_language_count": len(all_sign_langs),
        "glottolog_sign1238_dialect_count": dialect_count,
        "glottolog_sign1238_dialect_count_omitted": dialect_count,
        "feature_count": len(features),
        "branch_counts": {str(k): v for k, v in sorted(branches.items())},
        "skipped_no_coordinates": skipped_no_coords,
        "needs_review": needs_review,
        "coordinate_fallbacks": {
            gid: {**info, "used_for": gid} for gid, info in COORD_FALLBACKS.items()
        },
        "coordinate_overrides": {
            gid: {**info, "used_for": gid} for gid, info in COORD_OVERRIDES.items()
        },
        "coordinate_sources_non_cldf": {
            gid: src for gid, src in coord_sources.items() if not src.startswith("glottolog")
        },
    }

    collection = {
        "type": "FeatureCollection",
        "properties": {"layer": LAYER},
        "features": features,
    }
    return collection, meta


def validate_collection(collection: dict, meta: dict) -> list[str]:
    errors: list[str] = []
    features = collection.get("features", [])
    ids: list[str] = []
    for i, feature in enumerate(features):
        gid = feature.get("id")
        if not gid:
            errors.append(f"feature[{i}]: missing id")
            continue
        ids.append(gid)
        props = feature.get("properties", {})
        branch = props.get("branch")
        if branch not in INCLUDED_BRANCHES:
            errors.append(f"{gid}: invalid branch {branch!r}")
        if props.get("glottolog_level") == "dialect":
            errors.append(f"{gid}: dialect-level entry must not be on the public map")
        name = props.get("name")
        if not name:
            errors.append(f"{gid}: missing name")
        geom = feature.get("geometry", {})
        coords = geom.get("coordinates")
        if not coords or len(coords) != 2:
            errors.append(f"{gid}: missing coordinates")
            continue
        lon, lat = coords
        if not (-180 <= lon <= 180 and -90 <= lat <= 90):
            errors.append(f"{gid}: invalid coordinates [{lon}, {lat}]")

    if len(ids) != len(set(ids)):
        dupes = sorted({x for x in ids if ids.count(x) > 1})
        errors.append(f"duplicate ids: {', '.join(dupes)}")

    expected = meta.get("glottolog_sign1238_language_count")
    if expected is not None and len(features) != expected:
        errors.append(
            f"feature count {len(features)} != Glottolog language-level count {expected}"
        )

    if meta.get("needs_review"):
        errors.append(
            f"{len(meta['needs_review'])} language(s) need editorial branch assignment"
        )
    if meta.get("skipped_no_coordinates"):
        errors.append(
            f"{len(meta['skipped_no_coordinates'])} language(s) skipped for missing coordinates"
        )
    return errors


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--cldf",
        type=Path,
        help="Path to Glottolog CLDF languages.csv (versioned Zenodo release)",
    )
    parser.add_argument(
        "--legacy",
        type=Path,
        default=Path("/srv/www/deaf.city/website_old/data/languages.json"),
        help="Legacy languages.json for branch preservation",
    )
    parser.add_argument(
        "--output",
        type=Path,
        default=Path("data/languages.json"),
        help="Output GeoJSON path (schema unchanged for map consumers)",
    )
    parser.add_argument(
        "--meta-output",
        type=Path,
        default=Path("data/languages.meta.json"),
        help="Provenance and audit metadata (adjacent to GeoJSON)",
    )
    parser.add_argument(
        "--validate-only",
        action="store_true",
        help="Validate existing output + meta without regenerating",
    )
    args = parser.parse_args()

    if args.validate_only:
        if not args.output.is_file():
            print(f"Output not found: {args.output}", file=sys.stderr)
            return 1
        collection = json.loads(args.output.read_text(encoding="utf-8"))
        meta = {}
        if args.meta_output.is_file():
            meta = json.loads(args.meta_output.read_text(encoding="utf-8"))
        errors = validate_collection(collection, meta)
        if errors:
            for err in errors:
                print(f"ERROR: {err}", file=sys.stderr)
            return 1
        branches = {}
        for f in collection["features"]:
            b = f["properties"]["branch"]
            branches[b] = branches.get(b, 0) + 1
        print(f"Validation OK: {len(collection['features'])} features")
        print("Branch breakdown:", dict(sorted(branches.items())))
        return 0

    if not args.cldf or not args.cldf.is_file():
        print(f"CLDF not found: {args.cldf}", file=sys.stderr)
        print(f"Download Glottolog {SOURCE_CONFIG['version']} CLDF from:", file=sys.stderr)
        print(f"  {SOURCE_CONFIG['cldf_download']}", file=sys.stderr)
        return 1

    collection, meta = build_collection(args.cldf, args.legacy)
    errors = validate_collection(collection, meta)
    if errors:
        for err in errors:
            print(f"ERROR: {err}", file=sys.stderr)
        if meta.get("needs_review"):
            print("\nLanguages needing editorial classification:", file=sys.stderr)
            for item in meta["needs_review"]:
                print(f"  {item['id']}\t{item['name']}", file=sys.stderr)
            print(
                "\nAdd each id to NEW_BRANCH_980, NEW_BRANCH_982, or legacy auxiliary (978) "
                "in regenerate_languages_json.py, then re-run.",
                file=sys.stderr,
            )
        return 1

    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(
        json.dumps(collection, indent=4, ensure_ascii=False).replace("/", "\\/") + "\n",
        encoding="utf-8",
    )
    args.meta_output.parent.mkdir(parents=True, exist_ok=True)
    args.meta_output.write_text(
        json.dumps(meta, indent=2, ensure_ascii=False) + "\n",
        encoding="utf-8",
    )

    print(f"Wrote {len(collection['features'])} features to {args.output}")
    print(f"Wrote metadata to {args.meta_output}")
    print("Branch breakdown:", meta["branch_counts"])
    if meta.get("coordinate_sources_non_cldf"):
        print("Non-CLDF coordinates used:", meta["coordinate_sources_non_cldf"])
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
