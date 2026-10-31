#!/usr/bin/env python3
"""Validate a schema-2.0.0 knowledge bundle (disease × symptom matrix).

The JSON Schema file is the written spec
(`docs/symptom-checker/schema/knowledge-bundle.v2.schema.json`); this script is
the gate that runs without third-party packages. It reads the schema's
`required` lists straight out of that file, so a field added to the spec cannot
go unchecked here, and then runs the checks a schema cannot express:

* broken references — a profile row pointing at a symptom that does not exist,
  an override pointing at a missing condition, an evidence row or a visibility
  clause naming an unknown question/option, a `source_refs` id no source
  defines;
* a symptom nobody can report (only a warning: «خارج از پرسش‌نامه» is allowed,
  it must then show as `not_covered`, not as `absent`);
* a condition without a single `typical` row;
* content without `review_status`;
* any probability/score/percentage field anywhere — the interface never
  scores, ranks or percentages.

    python3 tools/symptom-checker-migrate/validate_v2.py
    python3 tools/symptom-checker-migrate/validate_v2.py path/to/bundle.json
"""
from __future__ import annotations

import json
import re
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
REPO = HERE.parents[1]

DEFAULT_BUNDLE = "docs/symptom-checker/phase-1/knowledge-bundle.v2.json"
SCHEMA_PATH = "docs/symptom-checker/schema/knowledge-bundle.v2.schema.json"

SYMPTOM_ID = re.compile(r"^[a-z]{2,5}\.[a-z0-9_]+$")
LINK_STATUSES = ("needs_live_verification", "verified_published", "unmapped")
FORBIDDEN_KEYS = re.compile(r"(probab|likelih|percent|score|weight|confidence|odds)", re.I)


class Report:
    def __init__(self) -> None:
        self.errors: list[str] = []
        self.warnings: list[str] = []

    def error(self, message: str) -> None:
        self.errors.append(message)

    def warn(self, message: str) -> None:
        self.warnings.append(message)


def load(relative: str) -> dict:
    return json.loads((REPO / relative).read_text(encoding="utf-8"))


def schema_requirements(schema: dict) -> dict[str, list[str]]:
    """`required` lists from the schema: root plus every `$defs` entry."""
    requirements = {"$root": list(schema.get("required", []))}
    for name, definition in (schema.get("$defs") or {}).items():
        requirements[name] = list(definition.get("required", []))
    return requirements


def check_required(report: Report, owner: str, item: dict, fields: list[str]) -> None:
    for field in fields:
        if field not in item:
            report.error(f"{owner}: missing required field \"{field}\"")


def clause_questions(clause: dict, out: list[dict]) -> None:
    if not isinstance(clause, dict):
        return
    for key in ("any_of", "all_of"):
        for sub in clause.get(key, []):
            clause_questions(sub, out)
    if clause.get("question_id"):
        out.append(clause)


def check_clause(report: Report, owner: str, clause: dict, questions: dict, where: str) -> None:
    found: list[dict] = []
    clause_questions(clause, found)
    if not found and not any(key in clause for key in ("always", "branches_open_min", "branches_open_max", "unanswered_min", "unanswered_max")):
        report.error(f"{owner}: clause has no condition ({where})")
    for item in found:
        question = questions.get(item["question_id"])
        if not question:
            report.error(f"{owner}: {where} names unknown question \"{item['question_id']}\"")
            continue
        options = {option["id"] for option in question.get("options", [])}
        if not item.get("answers"):
            report.error(f"{owner}: {where} has an empty answer list for {item['question_id']}")
        for answer in item.get("answers", []):
            if options and answer not in options:
                report.error(f"{owner}: {where} names unknown option {item['question_id']}.{answer}")


def find_forbidden_keys(node, trail: str, report: Report) -> None:
    if isinstance(node, dict):
        for key, value in node.items():
            if FORBIDDEN_KEYS.search(key):
                report.error(f"{trail}.{key}: probability/score fields are forbidden")
            find_forbidden_keys(value, f"{trail}.{key}", report)
    elif isinstance(node, list):
        for index, value in enumerate(node):
            find_forbidden_keys(value, f"{trail}[{index}]", report)


def validate(bundle: dict, schema: dict) -> Report:
    report = Report()
    requirements = schema_requirements(schema)
    check_required(report, "bundle", bundle, requirements["$root"])

    if bundle.get("schema_version") != "2.0.0":
        report.error(f"schema_version must be \"2.0.0\", got {bundle.get('schema_version')!r}")
    if not re.fullmatch(r"[0-9]+\.[0-9]+\.[0-9]+(-[a-z0-9.-]+)?", str(bundle.get("bundle_version", ""))):
        report.error(f"bundle_version is not a version: {bundle.get('bundle_version')!r}")

    clusters = {item["id"] for item in bundle.get("symptom_clusters", [])}
    sources = {item["id"] for item in bundle.get("sources", [])}
    symptoms = {item["id"]: item for item in bundle.get("symptoms", [])}
    questions = {item["id"]: item for item in bundle.get("questions", [])}
    conditions = {item["id"]: item for item in bundle.get("conditions", [])}
    safety_ids = {item["id"] for item in bundle.get("safety_rules", [])}

    def unique(kind: str, items: list[dict]) -> None:
        seen = set()
        for item in items:
            identifier = item.get("id")
            if identifier in seen:
                report.error(f"duplicate {kind} id \"{identifier}\"")
            seen.add(identifier)

    for kind, key in (
        ("symptom", "symptoms"),
        ("question", "questions"),
        ("condition", "conditions"),
        ("source", "sources"),
        ("override", "overrides"),
        ("safety rule", "safety_rules"),
        ("coverage rule", "coverage_rules"),
    ):
        unique(kind, bundle.get(key, []))

    def check_refs(owner: str, refs, label: str = "source") -> None:
        if not isinstance(refs, list) or not refs:
            report.error(f"{owner}: missing {label}_refs")
            return
        for ref in refs:
            if ref not in sources:
                report.error(f"{owner}: unknown {label} \"{ref}\"")

    # --- symptoms -------------------------------------------------------
    for symptom in bundle.get("symptoms", []):
        owner = f"symptom {symptom.get('id')}"
        check_required(report, owner, symptom, requirements["symptom"])
        identifier = symptom.get("id", "")
        if not SYMPTOM_ID.fullmatch(identifier):
            report.error(f"{owner}: id must look like \"abd.pain_rlq\"")
        else:
            prefix = identifier.split(".", 1)[0]
            if prefix not in clusters:
                report.error(f"{owner}: cluster \"{prefix}\" is not declared in symptom_clusters")
            if symptom.get("cluster") != prefix:
                report.error(f"{owner}: cluster field {symptom.get('cluster')!r} ≠ id prefix \"{prefix}\"")

    # --- questions and options -----------------------------------------
    ask_orders = {}
    for question in bundle.get("questions", []):
        owner = f"question {question.get('id')}"
        check_required(report, owner, question, requirements["question"])
        check_refs(owner, question.get("source_refs"))
        check_clause(report, owner, question.get("visibility", {}), questions, "visibility")
        order = question.get("ask_order")
        if isinstance(order, int):
            if order in ask_orders:
                report.error(f"{owner}: ask_order {order} already used by {ask_orders[order]}")
            ask_orders[order] = question.get("id")
        if question.get("type") == "scale":
            if not isinstance(question.get("min"), int) or not isinstance(question.get("max"), int):
                report.error(f"{owner}: scale questions need integer min/max")
        elif not question.get("options"):
            report.error(f"{owner}: {question.get('type')} question has no options")

        seen_options = set()
        for option in question.get("options", []):
            option_owner = f"{owner} option {option.get('id')}"
            if option.get("id") in seen_options:
                report.error(f"{option_owner}: duplicate option id")
            seen_options.add(option.get("id"))
            check_required(report, option_owner, option, requirements["option"])
            pairs = set()
            for entry in option.get("evidence", []):
                check_required(report, option_owner, entry, requirements["evidence"])
                if entry.get("symptom_id") not in symptoms:
                    report.error(f"{option_owner}: evidence points at unknown symptom \"{entry.get('symptom_id')}\"")
                if entry.get("state") not in ("present", "absent"):
                    report.error(f"{option_owner}: evidence state must be present|absent")
                key = (entry.get("symptom_id"), entry.get("state"))
                if key in pairs:
                    report.error(f"{option_owner}: duplicate evidence {key[0]} {key[1]}")
                pairs.add(key)

    # --- conditions -----------------------------------------------------
    for condition in bundle.get("conditions", []):
        owner = f"condition {condition.get('id')}"
        check_required(report, owner, condition, requirements["condition"])
        if not condition.get("review_status"):
            report.error(f"{owner}: missing review_status")
        profile_ref = condition.get("profile_ref")
        if not isinstance(profile_ref, str) or "#" not in profile_ref:
            report.error(f"{owner}: profile_ref must point to a profile id")
        if condition.get("link_status") not in LINK_STATUSES:
            report.error(f"{owner}: invalid link_status")
        profile = condition.get("profile", [])
        if not profile:
            report.error(f"{owner}: empty profile")
        levels = [row.get("level") for row in profile]
        if profile and "typical" not in levels:
            report.error(f"{owner}: profile has no typical row")
        for row in profile:
            row_owner = f"{owner} profile {row.get('symptom_id')}"
            check_required(report, row_owner, row, requirements["profileRow"])
            if row.get("symptom_id") not in symptoms:
                report.error(f"{row_owner}: unknown symptom")
            if row.get("level") not in ("typical", "possible", "rare", "not_expected"):
                report.error(f"{row_owner}: level must be typical|possible|rare|not_expected")
            check_refs(row_owner, row.get("source_refs"))
        for ref in condition.get("red_flag_refs", []):
            if ref not in safety_ids:
                report.error(f"{owner}: red_flag_refs names unknown safety rule \"{ref}\"")

    # --- overrides, safety, coverage ------------------------------------
    for override in bundle.get("overrides", []):
        owner = f"override {override.get('id')}"
        check_required(report, owner, override, requirements["override"])
        if override.get("condition_id") not in conditions:
            report.error(f"{owner}: unknown condition \"{override.get('condition_id')}\"")
        if not override.get("when"):
            report.error(f"{owner}: empty when")
        for clause in override.get("when", []):
            check_clause(report, owner, clause, questions, "when")
        for ref in override.get("source_refs", []):
            if ref not in sources:
                report.error(f"{owner}: unknown source \"{ref}\"")

    for rule in bundle.get("safety_rules", []):
        owner = f"safety rule {rule.get('id')}"
        check_required(report, owner, rule, requirements["safetyRule"])
        for clause in rule.get("when", []):
            check_clause(report, owner, clause, questions, "when")
        check_refs(owner, rule.get("source_refs"))
        if not rule.get("review_status"):
            report.warn(f"{owner}: no review_status (defaults to pending_team_review)")

    for rule in bundle.get("coverage_rules", []):
        owner = f"coverage rule {rule.get('id')}"
        check_required(report, owner, rule, requirements["coverageRule"])
        for clause in rule.get("when", []):
            check_clause(report, owner, clause, questions, "when")
        for ref in rule.get("source_refs", []):
            if ref not in sources:
                report.error(f"{owner}: unknown source \"{ref}\"")

    # --- scope and sources ----------------------------------------------
    scope = bundle.get("scope", {})
    for condition_id in scope.get("conditions_in_scope", []):
        if condition_id not in conditions:
            report.error(f"scope.conditions_in_scope names unknown condition \"{condition_id}\"")
    for source in bundle.get("sources", []):
        owner = f"source {source.get('id')}"
        check_required(report, owner, source, requirements["source"])
        if not re.fullmatch(r"[0-9]{4}-[0-9]{2}-[0-9]{2}", str(source.get("accessed", ""))):
            report.error(f"{owner}: accessed must be a date like 2026-10-31")

    # --- warnings -------------------------------------------------------
    reportable = {entry["symptom_id"] for question in bundle.get("questions", []) for option in question.get("options", []) for entry in option.get("evidence", [])}
    profiled = {row["symptom_id"] for condition in bundle.get("conditions", []) for row in condition.get("profile", [])}
    for identifier in sorted(set(symptoms) - reportable):
        report.warn(f"symptom {identifier}: no option reports it (must show as not_covered, never absent)")
    for identifier in sorted(set(symptoms) - profiled):
        report.warn(f"symptom {identifier}: not in any condition profile")
    for identifier in sorted(clusters):
        if not any(item.get("cluster") == identifier for item in bundle.get("symptoms", [])):
            report.warn(f"cluster {identifier}: declared but unused")

    find_forbidden_keys(bundle, "bundle", report)
    return report


def main(argv: list[str]) -> int:
    target = argv[0] if argv and not argv[0].startswith("-") else DEFAULT_BUNDLE
    bundle = json.loads((REPO / target).read_text(encoding="utf-8")) if not Path(target).is_absolute() else json.loads(Path(target).read_text(encoding="utf-8"))
    schema = load(SCHEMA_PATH)
    report = validate(bundle, schema)

    for line in report.warnings:
        print(f"WARNING {line}")
    for line in report.errors:
        print(f"ERROR   {line}")

    if report.errors:
        print(f"\nFAIL {len(report.errors)} error(s) in {target}")
        return 1

    print(
        f"OK {target}: schema {bundle.get('schema_version')} · bundle {bundle.get('bundle_id')} "
        f"{bundle.get('bundle_version')} — {len(bundle.get('symptoms', []))} نشانه، "
        f"{len(bundle.get('questions', []))} پرسش، {len(bundle.get('conditions', []))} بیماری، "
        f"{len(bundle.get('overrides', []))} override، {len(report.warnings)} هشدار"
    )
    print("Advisory: structure and traceability only — no clinical validation.")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
