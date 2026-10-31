#!/usr/bin/env python3
"""Migrate the knowledge bundle from schema 1.0.0 to 2.0.0 — automatically.

Phase 1 of the migration in the owner's runbook. The v2 shape is the
disease × symptom matrix; the v1 shape is a list of paths plus a separate
evidence map. Nothing here is authored by hand: the script reads v1 and the
evidence map and writes v2, so the matrix cannot silently disagree with the
data that proves today's behaviour.

What changes in the format (and what does not):

* `symptoms[]` gains cluster-prefixed ids (`s_pain_rlq` → `abd.pain_rlq`) and
  keeps the old id in `legacy_id`, so the engine adapter can map back.
* every option carries its own `evidence: [{symptom_id, state}]`, merged from
  `evidence-map.v1.json`; the external map stays untouched for v1 readers.
* `conditions[].symptom_links[]` becomes `conditions[].profile[]` with an
  explicit `level`. v1 has no level, so every link starts at `typical` — the
  runbook's rule («سطح اولیهٔ هر symptom_link برابر typical») — and the level is
  recorded in the data, not inferred later.
* `paths` become `overrides` (unchanged content): the rules a matrix alone
  cannot express.
* `safety_rules`, `coverage_rules`, `sources`, `scope`, `disclaimers`,
  `answer_states` and `change_notes` move unchanged.

Determinism and idempotency: the output is UTF-8, `ensure_ascii=False`,
sorted nowhere (insertion order is fixed by the script), and the script never
reads its own output, so running it twice produces the same bytes and the same
SHA-256. `--check` fails when the committed file is stale.

    python3 tools/symptom-checker-migrate/v1_to_v2.py
    python3 tools/symptom-checker-migrate/v1_to_v2.py --check
"""
from __future__ import annotations

import argparse
import copy
import hashlib
import json
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
REPO = HERE.parents[1]

V1_PATH = "docs/symptom-checker/phase-1/knowledge-bundle.v1.json"
EVIDENCE_PATH = "symptom-checker/engine/data/evidence-map.v1.json"
V2_PATH = "docs/symptom-checker/phase-1/knowledge-bundle.v2.json"
SCHEMA_PATH = "docs/symptom-checker/schema/knowledge-bundle.v2.schema.json"

SCHEMA_VERSION = "2.0.0"
TOOL = "v1_to_v2.py"
TOOL_VERSION = "1.0.0"

# The one cluster this bundle covers. The prefix is part of every symptom id;
# a second cluster (e.g. `uro.`) simply adds a row here and its own ids.
CLUSTER_ID = "abd"
CLUSTER_LABEL_FA = "شکم، پهلو و لگن"
DEFAULT_LEVEL = "typical"

MIGRATION_NOTE_FA = (
    "مهاجرت قالبی از schema 1.0.0 به 2.0.0 با اسکریپت v1_to_v2.py: شناسهٔ نشانه‌ها "
    "پیشوند خوشه گرفت، نگاشت پاسخ→نشانه داخل گزینه‌ها ادغام شد و symptom_links به "
    "profile با سطح typical تبدیل شد. هیچ محتوا، متن یا قاعدهٔ بالینی تازه‌ای اضافه نشد."
)


def read_json(relative: str) -> dict:
    return json.loads((REPO / relative).read_text(encoding="utf-8"))


def symptom_id_v2(v1_id: str) -> str:
    """`s_pain_rlq` → `abd.pain_rlq`; ids already carrying the prefix stay put."""
    if v1_id.startswith(f"{CLUSTER_ID}."):
        return v1_id
    stem = v1_id[2:] if v1_id.startswith("s_") else v1_id
    return f"{CLUSTER_ID}.{stem}"


def evidence_index(evidence_map: dict) -> dict[tuple[str, str], list[dict]]:
    index: dict[tuple[str, str], list[dict]] = {}
    for entry in evidence_map.get("entries", []):
        key = (entry["question_id"], entry["option_id"])
        index.setdefault(key, []).append(
            {"symptom_id": symptom_id_v2(entry["symptom_id"]), "state": entry["state"]}
        )
    return index


def migrate(v1: dict, evidence_map: dict) -> dict:
    """Pure v1 + evidence map → v2. No I/O, so tests can call it directly."""
    index = evidence_index(evidence_map)
    known_symptoms = {symptom["id"] for symptom in v1["symptoms"]}
    mapped = {item["symptom_id"] for entries in index.values() for item in entries}

    unknown = mapped - {symptom_id_v2(symptom) for symptom in known_symptoms} - known_symptoms
    if unknown:
        raise SystemExit(f"evidence map points at unknown symptoms: {sorted(unknown)}")

    symptoms = []
    for symptom in v1["symptoms"]:
        item = {"id": symptom_id_v2(symptom["id"])}
        # Keep the v1 id so the engine adapter can map v2 back without guessing.
        if not symptom["id"].startswith(f"{CLUSTER_ID}."):
            item["legacy_id"] = symptom["id"]
        item.update(
            {
                "cluster": CLUSTER_ID,
                "label_fa": symptom["label_fa"],
                "synonyms": list(symptom.get("synonyms", [])),
                "category": symptom.get("category"),
            }
        )
        symptoms.append(item)

    questions = []
    for question in v1["questions"]:
        migrated_question = copy.deepcopy(question)
        if question.get("options"):
            migrated_question["options"] = [
                {
                    **{key: copy.deepcopy(value) for key, value in option.items()},
                    "evidence": index.get((question["id"], option["id"]), []),
                }
                for option in question["options"]
            ]
        questions.append(migrated_question)

    conditions = []
    for condition in v1["conditions"]:
        profile = [
            {
                "symptom_id": symptom_id_v2(link["symptom_id"]),
                "level": DEFAULT_LEVEL,
                "profile_line": link["profile_line"],
                "source_refs": list(link.get("source_refs", [])),
            }
            for link in condition.get("symptom_links", [])
        ]
        conditions.append(
            {
                "id": condition["id"],
                "name_fa": condition["name_fa"],
                "article_slug": condition["article_slug"],
                "profile_ref": condition.get("profile_ref"),
                "link_status": condition.get("link_status"),
                "profile": profile,
                "red_flag_refs": list(condition.get("red_flag_refs", [])),
                "review_status": condition.get("review_status", "pending_team_review"),
            }
        )

    return {
        "schema_version": SCHEMA_VERSION,
        "bundle_id": v1["bundle_id"],
        "bundle_version": v1["bundle_version"],
        "status": v1["status"],
        "locale": v1["locale"],
        "created": v1["created"],
        "migrated_from": {
            "schema_version": v1["schema_version"],
            "bundle_id": v1["bundle_id"],
            "bundle_version": v1["bundle_version"],
            "v1_file": V1_PATH,
            "evidence_map": EVIDENCE_PATH,
            "tool": TOOL,
            "tool_version": TOOL_VERSION,
        },
        "migration_notes_fa": [MIGRATION_NOTE_FA],
        "scope": copy.deepcopy(v1["scope"]),
        "answer_states": list(v1["answer_states"]),
        "symptom_clusters": [{"id": CLUSTER_ID, "label_fa": CLUSTER_LABEL_FA}],
        "sources": copy.deepcopy(v1["sources"]),
        "symptoms": symptoms,
        "questions": questions,
        "conditions": conditions,
        "overrides": copy.deepcopy(v1["paths"]),
        "safety_rules": copy.deepcopy(v1["safety_rules"]),
        "coverage_rules": copy.deepcopy(v1["coverage_rules"]),
        "disclaimers": copy.deepcopy(v1["disclaimers"]),
        "change_notes": copy.deepcopy(v1.get("change_notes", [])),
    }


def serialise(bundle: dict) -> bytes:
    return (json.dumps(bundle, ensure_ascii=False, indent=2) + "\n").encode("utf-8")


def build() -> tuple[dict, bytes, str]:
    bundle = migrate(read_json(V1_PATH), read_json(EVIDENCE_PATH))
    payload = serialise(bundle)
    return bundle, payload, hashlib.sha256(payload).hexdigest()


def summarise(path: Path, bundle: dict) -> str:
    symptoms = len(bundle["symptoms"])
    options = sum(len(question.get("options", [])) for question in bundle["questions"])
    evidence = sum(len(option.get("evidence", [])) for question in bundle["questions"] for option in question.get("options", []))
    profiles = sum(len(condition["profile"]) for condition in bundle["conditions"])
    return (
        f"{path.name}: {symptoms} نشانه، {len(bundle['questions'])} پرسش، {options} گزینه، "
        f"{evidence} نگاشت پاسخ→نشانه، {len(bundle['conditions'])} بیماری با {profiles} سطر پروفایل، "
        f"{len(bundle['overrides'])} override"
    )


def main(argv: list[str]) -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--check", action="store_true", help="fail when the committed v2 file is stale")
    parser.add_argument("--out", default=V2_PATH)
    args = parser.parse_args(argv)

    bundle, payload, digest = build()
    target = REPO / args.out

    if args.check:
        if not target.is_file():
            print(f"FAIL missing {args.out}: run v1_to_v2.py")
            return 1
        current = target.read_bytes()
        if hashlib.sha256(current).hexdigest() != digest:
            print(f"FAIL {args.out} is stale (sha256 {hashlib.sha256(current).hexdigest()[:12]} ≠ {digest[:12]})")
            return 1
        print(f"OK {args.out} matches the migrator — sha256 {digest}")
        print(summarise(target, bundle))
        return 0

    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_bytes(payload)
    print(f"wrote {args.out} (sha256 {digest})")
    print(summarise(target, bundle))
    if not (REPO / SCHEMA_PATH).is_file():
        print(f"WARNING schema file missing: {SCHEMA_PATH}")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
