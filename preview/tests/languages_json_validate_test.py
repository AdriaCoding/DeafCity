#!/usr/bin/env python3
"""Validate data/languages.json and data/languages.meta.json for the sign-language map.

Run: python3 preview/tests/languages_json_validate_test.py
"""
from __future__ import annotations

import json
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
GEOJSON = ROOT / "data" / "languages.json"
META = ROOT / "data" / "languages.meta.json"
REGEN = ROOT / "preview" / "scripts" / "regenerate_languages_json.py"

INCLUDED_BRANCHES = {978, 979, 980, 981, 982}
DIALECT_GLOTTOCODES = {"kolk1234", "mars1235"}


def fail(msg: str) -> None:
    print(f"FAIL: {msg}", file=sys.stderr)
    sys.exit(1)


def ok(msg: str) -> None:
    print(f"PASS: {msg}")


def main() -> int:
    if not GEOJSON.is_file():
        fail(f"missing {GEOJSON}")
    if not META.is_file():
        fail(f"missing {META}")

    data = json.loads(GEOJSON.read_text(encoding="utf-8"))
    meta = json.loads(META.read_text(encoding="utf-8"))
    features = data.get("features", [])

    if meta.get("glottolog_sign1238_language_count") != 227:
        fail(f"expected 227 languages in metadata, got {meta.get('glottolog_sign1238_language_count')}")
    ok("227 Glottolog sign1238 language-level languoids documented")

    if meta.get("glottolog_sign1238_dialect_count") != 71:
        fail(f"expected 71 dialects documented as omitted, got {meta.get('glottolog_sign1238_dialect_count')}")
    ok("71 dialect-level languoids documented as omitted")

    if len(features) != 227:
        fail(f"expected 227 map features, got {len(features)}")
    ok("227 map features (language-level only)")

    if meta.get("source_version") != "5.3":
        fail(f"expected Glottolog 5.3 metadata, got {meta.get('source_version')!r}")
    ok("metadata cites Glottolog 5.3")

    ids = [f.get("id") for f in features]
    if len(ids) != len(set(ids)):
        fail("duplicate glottocodes in features")
    ok("unique glottocodes")

    on_map_dialects = DIALECT_GLOTTOCODES & set(ids)
    if on_map_dialects:
        fail(f"dialect-level languoids must not be on map: {sorted(on_map_dialects)}")
    ok("dialect-level languoids omitted from map")

    for feature in features:
        if feature.get("properties", {}).get("glottolog_level") == "dialect":
            fail(f"{feature.get('id')} is a dialect entry on the map")

    branches: dict[int, int] = {}
    for feature in features:
        gid = feature.get("id")
        if not gid:
            fail("feature missing id")
        branch = feature.get("properties", {}).get("branch")
        if branch not in INCLUDED_BRANCHES:
            fail(f"{gid} has unexpected branch {branch}")
        branches[branch] = branches.get(branch, 0) + 1
        name = feature.get("properties", {}).get("name")
        if not name:
            fail(f"{gid} missing name")
        coords = feature.get("geometry", {}).get("coordinates")
        if not coords or len(coords) != 2:
            fail(f"{gid} missing coordinates")
        lon, lat = coords
        if not (-180 <= lon <= 180 and -90 <= lat <= 90):
            fail(f"{gid} invalid coordinates")

    ok("all features have id, name, valid branch, valid coordinates")

    expected_branches = {978: 4, 979: 1, 980: 79, 981: 2, 982: 141}
    if branches != expected_branches:
        fail(f"unexpected branch counts: {branches}")
    ok("branch counts: 978=4, 979=1, 980=79, 981=2, 982=141")

    by_id = {f["id"]: f for f in features}
    cata = by_id.get("cata1241")
    if not cata or cata["geometry"]["coordinates"] != [1.97, 41.635]:
        fail("cata1241 (Catalan Sign Language) must use editorial coordinate override [1.97, 41.635]")
    ok("cata1241 coordinate override applied")

    proc = subprocess.run(
        [sys.executable, str(REGEN), "--validate-only", "--output", str(GEOJSON), "--meta-output", str(META)],
        capture_output=True,
        text=True,
    )
    if proc.returncode != 0:
        fail(f"regenerate --validate-only failed:\n{proc.stdout}\n{proc.stderr}")
    ok("regenerate script validate-only passes")

    print("All tests passed.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
