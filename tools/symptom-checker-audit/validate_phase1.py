#!/usr/bin/env python3
"""اعتبارسنجی بستهٔ دانش فاز ۱ دستیار نشانه‌ها (خوشهٔ درد شکم/پهلو).

بدون وابستگی بیرونی کار می‌کند. سه کار انجام می‌دهد:

1. ساختار و یکتایی شناسه‌های بسته را می‌سنجد.
2. یکپارچگی ارجاع‌ها را بررسی می‌کند (منبع، نشانه، سؤال، گزینه، موضوع).
3. سناریوها را با یک ارزیاب کوچک و تکرارپذیر می‌سنجد؛ اگر خروجی محاسبه‌شده
   با انتظار سناریو یکی نباشد، خطا گزارش می‌کند.

استفاده:
    python3 tools/symptom-checker-audit/validate_phase1.py
    python3 tools/symptom-checker-audit/validate_phase1.py --bundle <path> --scenarios <path>

این ابزار ادعای اعتبار بالینی نمی‌کند؛ فقط می‌گوید داده و ارجاع‌ها با قرارداد
مخزن سازگارند.
"""

from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
BUNDLE_PATH = ROOT / "docs" / "symptom-checker" / "phase-1" / "knowledge-bundle.v1.json"
SCENARIOS_PATH = ROOT / "docs" / "symptom-checker" / "phase-1" / "scenarios.v1.json"
PROFILES_PATH = ROOT / "themes" / "lax-theme-child" / "data" / "disease-profiles.json"
EVIDENCE_PATH = ROOT / "symptom-checker" / "engine" / "data" / "evidence-map.v1.json"

COVERAGE_VALUES = {"sufficient", "insufficient", "out_of_scope"}
SEVERITIES = {"urgent", "prompt", "info"}
BOOLEAN3_STATES = {"yes", "no", "unknown"}


def load_json(path: Path):
    with path.open(encoding="utf-8") as handle:
        return json.load(handle)


# --------------------------------------------------------------------------
# ارزیاب کوچک (همان معنایی که فاز ۲ باید پیاده کند)
# --------------------------------------------------------------------------

def answer_values(answers: dict, question_id: str) -> list:
    if question_id not in answers:
        return []
    value = answers[question_id]
    if isinstance(value, list):
        return value
    return [value]


def clause_matches(clause: dict, answers: dict, branches_open: set) -> bool:
    if "branches_open_min" in clause:
        return len(branches_open) >= int(clause["branches_open_min"])
    if "branches_open_max" in clause:
        return len(branches_open) <= int(clause["branches_open_max"])

    if "any_of" in clause:
        return any(
            clause_matches(sub, answers, branches_open) for sub in clause["any_of"]
        )

    question_id = clause.get("question_id", "")
    expected = set(clause.get("answers", []))
    given = set(answer_values(answers, question_id))
    return bool(given & expected)


def clause_list_matches(clauses: list, answers: dict, branches_open: set) -> bool:
    return all(clause_matches(clause, answers, branches_open) for clause in clauses)


def evaluate(bundle: dict, answers: dict) -> dict:
    """محاسبهٔ مسیرها، هشدارها و پوشش برای یک مجموعه پاسخ.

    پاسخ نداده = «پرسیده‌نشده» و هرگز معادل «خیر» نیست؛ شرطی که به پاسخ
    نیاز دارد با نبود پاسخ برآورده نمی‌شود.
    """
    branches = set()
    for path in bundle.get("paths", []):
        if clause_list_matches(path.get("when", []), answers, set()):
            branches.add(path["condition_id"])

    safety = [
        rule["id"]
        for rule in bundle.get("safety_rules", [])
        if clause_list_matches(rule.get("when", []), answers, branches)
    ]

    coverage = None
    matched_rule = None
    for rule in bundle.get("coverage_rules", []):
        if clause_list_matches(rule.get("when", []), answers, branches):
            coverage = rule["result"]
            matched_rule = rule["id"]
            break

    return {
        "branches_open": sorted(branches),
        "safety_flags": safety,
        "coverage": coverage,
        "coverage_rule": matched_rule,
    }


# --------------------------------------------------------------------------
# اعتبارسنجی بسته
# --------------------------------------------------------------------------

def _duplicates(values: list) -> set:
    seen, dupes = set(), set()
    for value in values:
        if value in seen:
            dupes.add(value)
        seen.add(value)
    return dupes


def validate_bundle(bundle: dict, profiles_path: Path = PROFILES_PATH) -> list:
    errors: list = []

    def err(message: str) -> None:
        errors.append(message)

    sources = {item["id"]: item for item in bundle.get("sources", [])}
    symptoms = {item["id"]: item for item in bundle.get("symptoms", [])}
    questions = {item["id"]: item for item in bundle.get("questions", [])}
    conditions = {item["id"]: item for item in bundle.get("conditions", [])}
    safety_ids = {item["id"] for item in bundle.get("safety_rules", [])}

    for label, items in (
        ("source", bundle.get("sources", [])),
        ("symptom", bundle.get("symptoms", [])),
        ("question", bundle.get("questions", [])),
        ("condition", bundle.get("conditions", [])),
        ("path", bundle.get("paths", [])),
        ("safety_rule", bundle.get("safety_rules", [])),
        ("coverage_rule", bundle.get("coverage_rules", [])),
    ):
        dupes = _duplicates([item.get("id") for item in items])
        for dupe in sorted(dupes):
            err(f"duplicate {label} id: {dupe}")

    if not bundle.get("sources"):
        err("bundle has no sources")
    for source in bundle.get("sources", []):
        if not str(source.get("url", "")).startswith("https://"):
            err(f"source {source.get('id')} url must be https")
        if not source.get("accessed"):
            err(f"source {source.get('id')} missing accessed date")

    # ترتیب پرسش و وابستگی نمایش
    orders = {}
    for question in bundle.get("questions", []):
        qid = question["id"]
        order = question.get("ask_order")
        if order in orders:
            err(f"ask_order {order} used by both {orders[order]} and {qid}")
        orders[order] = qid

        if not question.get("source_refs"):
            err(f"question {qid} has no source_refs")
        for ref in question.get("source_refs", []):
            if ref not in sources:
                err(f"question {qid} references unknown source {ref}")

        visibility = question.get("visibility", {})
        for rule in visibility.get("any_of", []):
            dep = rule.get("question_id")
            if dep not in questions:
                err(f"question {qid} visibility references unknown question {dep}")
            elif dep == qid:
                err(f"question {qid} visibility references itself")
            else:
                dep_order = questions[dep].get("ask_order", 0)
                if dep_order >= order:
                    err(
                        f"question {qid} (order {order}) depends on {dep} "
                        f"(order {dep_order}); dependency must be asked earlier"
                    )
            for option in rule.get("answers", []):
                dep_q = questions.get(dep, {})
                option_ids = {opt["id"] for opt in dep_q.get("options", [])}
                if option_ids and option not in option_ids:
                    err(f"question {qid} visibility uses unknown option {dep}.{option}")

        qtype = question.get("type")
        option_ids = [opt["id"] for opt in question.get("options", [])]
        if qtype == "boolean3":
            if set(option_ids) != BOOLEAN3_STATES:
                err(f"boolean3 question {qid} must have exactly yes/no/unknown options")
        elif qtype in {"single", "multi"}:
            if len(option_ids) < 2:
                err(f"question {qid} needs at least two options")
        elif qtype == "scale":
            if "min" not in question or "max" not in question:
                err(f"scale question {qid} needs min and max")
            elif question["min"] >= question["max"]:
                err(f"scale question {qid} has invalid range")
        else:
            err(f"question {qid} has unknown type {qtype}")

        if qtype == "multi":
            flags = {opt["id"]: bool(opt.get("exclusive")) for opt in question.get("options", [])}
            if "none" in flags and not flags["none"]:
                err(f"multi question {qid}: 'none' must be exclusive")
            if "unknown" in flags and not flags["unknown"]:
                err(f"multi question {qid}: 'unknown' must be exclusive")

        if len(set(option_ids)) != len(option_ids):
            err(f"question {qid} has duplicate option ids")

    # نشانه‌ها و موضوع‌ها
    profile_ids = set()
    if profiles_path.is_file():
        for entry in load_json(profiles_path):
            if isinstance(entry, dict) and entry.get("diseaseId"):
                profile_ids.add(entry["diseaseId"])

    for condition in bundle.get("conditions", []):
        cid = condition["id"]
        links = condition.get("symptom_links", [])
        if len(links) < 3:
            err(f"condition {cid} has fewer than 3 symptom links")
        for link in links:
            if link["symptom_id"] not in symptoms:
                err(f"condition {cid} links unknown symptom {link['symptom_id']}")
            if not link.get("source_refs"):
                err(f"condition {cid} symptom link {link.get('symptom_id')} has no source_refs")
            for ref in link.get("source_refs", []):
                if ref not in sources:
                    err(f"condition {cid} symptom link references unknown source {ref}")
        for ref in condition.get("red_flag_refs", []):
            if ref not in safety_ids:
                err(f"condition {cid} references unknown safety rule {ref}")
        ref = condition.get("profile_ref", "")
        if "#" in ref:
            disease_id = ref.rsplit("#", 1)[1]
            if profile_ids and disease_id not in profile_ids:
                err(f"condition {cid} profile_ref points to missing profile {disease_id}")
        else:
            err(f"condition {cid} profile_ref lacks #diseaseId")
        if condition.get("review_status") not in {"pending_team_review", "approved_by_team", "rejected"}:
            err(f"condition {cid} has invalid review_status")

    # مسیرها، هشدارها و پوشش
    def check_clause(owner: str, clause: dict) -> None:
        if "any_of" in clause:
            for sub in clause["any_of"]:
                check_clause(owner, sub)
            return
        if "branches_open_min" in clause or "branches_open_max" in clause:
            return
        qid = clause.get("question_id")
        if qid not in questions:
            err(f"{owner} references unknown question {qid}")
            return
        option_ids = {opt["id"] for opt in questions[qid].get("options", [])}
        answers = clause.get("answers", [])
        if not answers:
            err(f"{owner} has an empty answer list for {qid}")
        if option_ids:
            for answer in answers:
                if answer not in option_ids:
                    err(f"{owner} uses unknown option {qid}.{answer}")

    for path in bundle.get("paths", []):
        pid = path["id"]
        if path.get("condition_id") not in conditions:
            err(f"path {pid} points to unknown condition {path.get('condition_id')}")
        if not path.get("source_refs"):
            err(f"path {pid} has no source_refs")
        for ref in path.get("source_refs", []):
            if ref not in sources:
                err(f"path {pid} references unknown source {ref}")
        for clause in path.get("when", []):
            check_clause(f"path {pid}", clause)

    for rule in bundle.get("safety_rules", []):
        rid = rule["id"]
        if rule.get("severity") not in SEVERITIES:
            err(f"safety rule {rid} has invalid severity")
        if not rule.get("source_refs"):
            err(f"safety rule {rid} has no source_refs")
        for ref in rule.get("source_refs", []):
            if ref not in sources:
                err(f"safety rule {rid} references unknown source {ref}")
        for clause in rule.get("when", []):
            check_clause(f"safety rule {rid}", clause)

    for rule in bundle.get("coverage_rules", []):
        rid = rule["id"]
        if rule.get("result") not in COVERAGE_VALUES:
            err(f"coverage rule {rid} has invalid result")
        if not rule.get("source_refs"):
            err(f"coverage rule {rid} has no source_refs")
        for ref in rule.get("source_refs", []):
            if ref not in sources:
                err(f"coverage rule {rid} references unknown source {ref}")
        for clause in rule.get("when", []):
            check_clause(f"coverage rule {rid}", clause)

    for cid in conditions:
        if not any(path.get("condition_id") == cid for path in bundle.get("paths", [])):
            err(f"condition {cid} has no path that can open it")

    return errors


# --------------------------------------------------------------------------
# اعتبارسنجی سناریوها
# --------------------------------------------------------------------------

def question_visible(question: dict, answers: dict) -> bool:
    visibility = question.get("visibility", {})
    if visibility.get("always"):
        return True
    for rule in visibility.get("any_of", []):
        given = set(answer_values(answers, rule.get("question_id", "")))
        if given & set(rule.get("answers", [])):
            return True
    return False


def validate_scenarios(bundle: dict, scenarios: dict) -> list:
    errors: list = []
    questions = {item["id"]: item for item in bundle.get("questions", [])}
    conditions = {item["id"] for item in bundle.get("conditions", [])}
    safety_ids = {item["id"] for item in bundle.get("safety_rules", [])}
    items = scenarios.get("scenarios", [])

    if scenarios.get("bundle_id") != bundle.get("bundle_id"):
        errors.append("scenarios bundle_id does not match the knowledge bundle")
    if not items:
        errors.append("no scenarios found")

    dupes = _duplicates([item.get("id") for item in items])
    for dupe in sorted(dupes):
        errors.append(f"duplicate scenario id: {dupe}")

    for scenario in items:
        sid = scenario.get("id", "?")
        answers = scenario.get("answers", {})
        expected = scenario.get("expected", {})

        for qid, value in answers.items():
            if qid not in questions:
                errors.append(f"scenario {sid} answers unknown question {qid}")
                continue
            question = questions[qid]
            qtype = question.get("type")
            if qtype == "scale":
                if value == "unknown":
                    if not question.get("allow_unknown"):
                        errors.append(f"scenario {sid} uses unknown for {qid} that forbids it")
                elif not isinstance(value, int) or not (
                    question.get("min", 0) <= value <= question.get("max", 0)
                ):
                    errors.append(f"scenario {sid} has out-of-range value for {qid}: {value!r}")
            else:
                option_ids = [opt["id"] for opt in question.get("options", [])]
                values = value if isinstance(value, list) else [value]
                for item in values:
                    if item not in option_ids:
                        errors.append(f"scenario {sid} uses unknown option {qid}.{item}")
                exclusive = {
                    opt["id"] for opt in question.get("options", []) if opt.get("exclusive")
                }
                chosen_exclusive = exclusive & set(values)
                if chosen_exclusive and len(values) > 1:
                    errors.append(
                        f"scenario {sid} combines exclusive option {sorted(chosen_exclusive)} "
                        f"with other answers for {qid}"
                    )
            if not question_visible(question, answers):
                errors.append(f"scenario {sid} answers {qid} although it would not be shown")

        for key in ("branches_open", "branches_must_not_open", "safety_flags"):
            if key not in expected:
                errors.append(f"scenario {sid} missing expected.{key}")
        if "coverage" not in expected:
            errors.append(f"scenario {sid} missing expected.coverage")
        if "result_cards_allowed" not in expected:
            errors.append(f"scenario {sid} missing expected.result_cards_allowed")

        for branch in expected.get("branches_open", []) + expected.get("branches_must_not_open", []):
            if branch not in conditions:
                errors.append(f"scenario {sid} references unknown condition {branch}")
        for flag in expected.get("safety_flags", []):
            if flag not in safety_ids:
                errors.append(f"scenario {sid} references unknown safety rule {flag}")
        if expected.get("coverage") not in COVERAGE_VALUES:
            errors.append(f"scenario {sid} has invalid expected.coverage")
        if expected.get("coverage") in {"insufficient", "out_of_scope"} and expected.get(
            "result_cards_allowed"
        ):
            errors.append(
                f"scenario {sid} allows result cards while coverage is {expected.get('coverage')}"
            )

        calculated = evaluate(bundle, answers)
        got_branches = set(calculated["branches_open"])
        want_branches = set(expected.get("branches_open", []))
        if got_branches != want_branches:
            errors.append(
                f"scenario {sid}: branches differ — expected {sorted(want_branches)}, "
                f"got {sorted(got_branches)}"
            )
        forbidden = set(expected.get("branches_must_not_open", []))
        leaked = got_branches & forbidden
        if leaked:
            errors.append(f"scenario {sid}: forbidden branches opened {sorted(leaked)}")

        if sorted(calculated["safety_flags"]) != sorted(expected.get("safety_flags", [])):
            errors.append(
                f"scenario {sid}: safety flags differ — expected "
                f"{sorted(expected.get('safety_flags', []))}, got {sorted(calculated['safety_flags'])}"
            )
        if calculated["coverage"] != expected.get("coverage"):
            errors.append(
                f"scenario {sid}: coverage differs — expected {expected.get('coverage')}, "
                f"got {calculated['coverage']}"
            )

    return errors


def validate_evidence_map(bundle: dict, evidence: dict) -> list:
    """نگاشت پاسخ→نشانه باید فقط به سؤال، گزینه و نشانهٔ موجود اشاره کند."""
    errors: list = []
    questions = {item["id"]: item for item in bundle.get("questions", [])}
    symptoms = {item["id"] for item in bundle.get("symptoms", [])}
    seen = set()

    if evidence.get("bundle_id") != bundle.get("bundle_id"):
        errors.append("evidence map bundle_id does not match the knowledge bundle")
    if evidence.get("bundle_version") != bundle.get("bundle_version"):
        errors.append(
            "evidence map bundle_version {!r} does not match bundle {!r}".format(
                evidence.get("bundle_version"), bundle.get("bundle_version")
            )
        )

    for entry in evidence.get("entries", []):
        owner = "evidence entry {}->{}".format(entry.get("question_id"), entry.get("symptom_id"))
        question = questions.get(entry.get("question_id"))
        if not question:
            errors.append(f"{owner}: unknown question")
            continue
        option_ids = {opt["id"] for opt in question.get("options", [])}
        if entry.get("option_id") not in option_ids:
            errors.append(f"{owner}: unknown option {entry.get('question_id')}.{entry.get('option_id')}")
        if entry.get("symptom_id") not in symptoms:
            errors.append(f"{owner}: unknown symptom")
        if entry.get("state") not in {"present", "absent"}:
            errors.append(f"{owner}: state must be present or absent")
        key = (entry.get("question_id"), entry.get("option_id"), entry.get("symptom_id"), entry.get("state"))
        if key in seen:
            errors.append(f"{owner}: duplicate entry")
        seen.add(key)

    return errors


def main(argv=None) -> int:
    parser = argparse.ArgumentParser(description="Validate the phase-1 symptom-checker bundle")
    parser.add_argument("--bundle", default=str(BUNDLE_PATH))
    parser.add_argument("--scenarios", default=str(SCENARIOS_PATH))
    parser.add_argument("--evidence", default=str(EVIDENCE_PATH))
    parser.add_argument("--quiet", action="store_true")
    args = parser.parse_args(argv)

    bundle = load_json(Path(args.bundle))
    scenarios = load_json(Path(args.scenarios))

    errors = validate_bundle(bundle)
    errors += validate_scenarios(bundle, scenarios)

    evidence_file = Path(args.evidence)
    evidence_note = "بدون نگاشت شواهد"
    if evidence_file.is_file():
        evidence = load_json(evidence_file)
        errors += validate_evidence_map(bundle, evidence)
        evidence_note = f"{len(evidence.get('entries', []))} نگاشت پاسخ→نشانه"

    if errors:
        print(f"FAIL — {len(errors)} issue(s)")
        for message in errors:
            print(f"  • {message}")
        return 1

    if not args.quiet:
        print(
            "OK — bundle {} v{}: {} symptoms, {} questions, {} conditions, "
            "{} paths, {} safety rules, {} coverage rules, {} scenarios — {}".format(
                bundle["bundle_id"],
                bundle["bundle_version"],
                len(bundle["symptoms"]),
                len(bundle["questions"]),
                len(bundle["conditions"]),
                len(bundle["paths"]),
                len(bundle["safety_rules"]),
                len(bundle["coverage_rules"]),
                len(scenarios["scenarios"]),
                evidence_note,
            )
        )
        print("Advisory: structure and traceability only — no clinical validation.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
