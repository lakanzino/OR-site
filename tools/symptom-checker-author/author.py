#!/usr/bin/env python3
"""Authoring CLI for the knowledge bundle (phase 3 — the most important phase).

Adding a disease must be a data job, not a code job. This tool is the only way
new content enters the bundle:

    scaffold-condition   کتابخانهٔ نشانه‌ها را نشان می‌دهد و اسکلت بیماری را می‌سازد
    add-*                فایل دادهٔ تازه را وارد فضای کاری می‌کند
    validate             بررسی‌های ساختاری + قواعد نویسندگی
    overlap              نشانه‌های مشترک و جداکننده‌ها؛ بی‌جداکننده = خطا
    gen-scenarios        از هر پروفایل، سناریوی «شخصیت typical» می‌سازد
    build-bundle         پایهٔ مهاجرت‌شده + فضای کاری → بستهٔ v2 با هش و نسخه
    report               پوشش نشانه‌ها و بیماری‌های بدون جداکننده

Pipeline (هر مرحله تکرارپذیر و ایدمپوتنت):

    v1 + evidence-map --v1_to_v2.py--> knowledge-bundle.v2.base.json
    base + authoring/  --build-bundle--> knowledge-bundle.v2.json

The base is generated; the final bundle is generated from the base plus the
small, human-written JSON files in the workspace. Neither is edited by hand.

    python3 tools/symptom-checker-author/author.py validate
    python3 tools/symptom-checker-author/author.py overlap --markdown
    python3 tools/symptom-checker-author/author.py build-bundle --check
"""
from __future__ import annotations

import argparse
import hashlib
from datetime import date
import json
import os
import re
import subprocess
import sys
import tempfile
from pathlib import Path

HERE = Path(__file__).resolve().parent
REPO = HERE.parents[1]

BASE_PATH = "docs/symptom-checker/phase-1/knowledge-bundle.v2.base.json"
FINAL_PATH = "docs/symptom-checker/phase-1/knowledge-bundle.v2.json"
WORKSPACE = "docs/symptom-checker/authoring"
# `AUTHOR_HELP_JS` lets a test point the copy-check at a scratch file instead of
# editing the real UI source. Nobody else should set it.
HELP_JS = os.environ.get("AUTHOR_HELP_JS", "symptom-checker/ui/question-help.js")
SCHEMA_PATH = "docs/symptom-checker/schema/knowledge-bundle.v2.schema.json"

PARTS = ("sources", "symptoms", "questions", "conditions")
LEVELS = ("typical", "possible", "rare", "not_expected")

sys.path.insert(0, str(HERE.parent / "symptom-checker-migrate"))
import validate_v2  # noqa: E402  (path setup above)


# --- io --------------------------------------------------------------------
def read_json(path: Path):
    return json.loads(path.read_text(encoding="utf-8"))


def serialise(bundle: dict) -> bytes:
    return (json.dumps(bundle, ensure_ascii=False, indent=2) + "\n").encode("utf-8")


def strip_private(value):
    """Authoring-only keys (starting with `_`) never reach the bundle."""
    if isinstance(value, dict):
        return {key: strip_private(item) for key, item in value.items() if not key.startswith("_")}
    if isinstance(value, list):
        return [strip_private(item) for item in value]
    return value


# --- workspace -------------------------------------------------------------
def collect(workspace: Path) -> dict:
    parts = {kind: [] for kind in PARTS}
    for kind in PARTS:
        directory = workspace / kind
        if not directory.is_dir():
            continue
        for path in sorted(directory.glob("*.json")):
            parts[kind].append((path, strip_private(read_json(path))))
    overrides_path = workspace / "overrides.json"
    parts["overrides"] = [(overrides_path, strip_private(read_json(overrides_path)))] if overrides_path.is_file() else []
    return parts


def duplicates(items: list[dict], kind: str) -> list[str]:
    seen, problems = set(), []
    for item in items:
        identifier = item.get("id")
        if identifier in seen:
            problems.append(f"duplicate {kind} id in the workspace: {identifier}")
        seen.add(identifier)
    return problems


def compose(base: dict, parts: dict) -> tuple[dict, list[str], bool]:
    """base + workspace → bundle. Returns (bundle, problems, changed)."""
    problems: list[str] = []
    bundle = json.loads(json.dumps(base))
    changed = False

    existing = {
        "sources": {item["id"] for item in bundle["sources"]},
        "symptoms": {item["id"] for item in bundle["symptoms"]},
        "questions": {item["id"] for item in bundle["questions"]},
        "conditions": {item["id"] for item in bundle["conditions"]},
    }

    for kind in PARTS:
        problems.extend(duplicates([item for _, item in parts[kind]], kind))
        for path, item in parts[kind]:
            identifier = item.get("id")
            if identifier in existing[kind]:
                problems.append(f"{path.name}: {kind[:-1]} \"{identifier}\" already exists in the base bundle")
                continue
            bundle[kind].append(item)
            existing[kind].add(identifier)
            changed = True

    for path, rows in parts["overrides"]:
        for row in rows:
            if row.get("condition_id") not in existing["conditions"]:
                problems.append(f"{path.name}: override \"{row.get('id')}\" points at unknown condition \"{row.get('condition_id')}\"")
                continue
            bundle["overrides"].append(row)
            changed = True

    return bundle, problems, changed


# --- validation ------------------------------------------------------------
def help_block(text: str, question_id: str) -> str | None:
    """The `q_x: { ... }` block of question-help.js, braces balanced."""
    start = text.find(f"\n  {question_id}: {{")
    if start == -1:
        return None
    depth = 0
    for index in range(text.find("{", start), len(text)):
        if text[index] == "{":
            depth += 1
        elif text[index] == "}":
            depth -= 1
            if depth == 0:
                return text[start:index + 1]
    return None


def authoring_checks(
    bundle: dict, help_text: str, authored_symptoms: set[str] | None = None
) -> tuple[list[str], list[str]]:
    """Rules the CLI adds on top of the schema: errors and warnings.

    `authored_symptoms` are the symptom ids that came from the workspace rather
    than from the base bundle. A gap — a symptom nobody can be asked about — is a
    warning when it is inherited v1 content (shown as not_covered, never absent)
    and an **error** when the authoring run introduced it, because that disease
    could never be reached.
    """
    errors: list[str] = []
    warnings: list[str] = []

    for condition in bundle["conditions"]:
        owner = f"بیماری {condition['id']}"
        profile = condition.get("profile", [])
        if len(profile) < 3:
            errors.append(f"{owner}: حداقل ۳ ردیف پروفایل لازم است (الان {len(profile)})")
        levels = [row.get("level") for row in profile]
        if profile and "typical" not in levels:
            errors.append(f"{owner}: حداقل یک ردیف typical لازم است")
        if not condition.get("review_status"):
            errors.append(f"{owner}: review_status ندارد")
        if not condition.get("article_slug"):
            warnings.append(f"{owner}: مقاله‌ای وصل نیست (article_slug خالی) — کارت بدون لینک نمایش داده می‌شود")
        for row in profile:
            if not row.get("source_refs"):
                errors.append(f"{owner}: ردیف {row.get('symptom_id')} منبع ندارد")
            if not row.get("profile_line"):
                errors.append(f"{owner}: ردیف {row.get('symptom_id')} متن نمایشی ندارد")

    askable = askable_symptoms(bundle)
    for symptom_id in sorted(authored_symptoms or set()):
        if symptom_id not in askable:
            errors.append(
                f"نشانهٔ {symptom_id} هیچ گزینه‌ای ندارد که آن را ثبت کند — «به پرسش تازه نیاز دارد»"
            )

    for question in bundle["questions"]:
        owner = f"پرسش {question['id']}"
        block = help_block(help_text, question["id"])
        if block is None:
            errors.append(f"{owner}: در {HELP_JS} خط راهنما ندارد (هر پرسش تازه یک متن راهنما لازم دارد)")
            continue
        for option in question.get("options", []):
            if not re.search(r"\b%s\s*:" % re.escape(option["id"]), block):
                errors.append(f"{owner}: گزینهٔ {option['id']} در {HELP_JS} خط ندارد")

    return errors, warnings


ENGINE_LOADER = "symptom-checker/engine/src/load-bundle.js"


def engine_issues(bundle: dict) -> list[str]:
    """Ask the engine's own validator, so the Python mirror cannot drift silently.

    `validate_v2.py` is a second implementation of the same rules; when the two
    disagree the engine wins, because the engine is what will run in the browser.
    """
    module = (REPO / ENGINE_LOADER).resolve().as_uri()
    script = (
        f"import {{ loadBundle }} from {json.dumps(module)};\n"
        "import { readFileSync } from 'node:fs';\n"
        "try { loadBundle(JSON.parse(readFileSync(process.argv[1], 'utf8'))); }\n"
        "catch (error) {\n"
        "  for (const issue of (error?.details?.issues ?? [error?.message ?? String(error)])) console.log(issue);\n"
        "  process.exit(1);\n"
        "}\n"
        "process.exit(0);\n"
    )
    with tempfile.NamedTemporaryFile("w", suffix=".json", delete=False, encoding="utf-8") as handle:
        json.dump(bundle, handle, ensure_ascii=False)
        path = handle.name
    try:
        done = subprocess.run(
            ["node", "--input-type=module", "-e", script, path],
            capture_output=True,
            text=True,
            timeout=120,
        )
    except (OSError, subprocess.SubprocessError) as error:  # node missing → do not hide it
        return [f"engine validator could not run: {error}"]
    finally:
        Path(path).unlink(missing_ok=True)
    lines = [line.strip() for line in done.stdout.splitlines() if line.strip()]
    if done.returncode != 0 and not lines:
        lines = [f"engine validator failed: {done.stderr.strip() or done.returncode}"]
    return [f"engine: {line}" for line in lines]


def validate_bundle(
    bundle: dict, schema: dict, help_text: str, authored_symptoms: set[str] | None = None
) -> tuple[list[str], list[str]]:
    report = validate_v2.validate(bundle, schema)
    errors = list(report.errors)
    warnings = list(report.warnings)
    extra_errors, extra_warnings = authoring_checks(bundle, help_text, authored_symptoms)
    return errors + extra_errors + engine_issues(bundle), warnings + extra_warnings


# --- overlap ---------------------------------------------------------------
def askable_symptoms(bundle: dict) -> set[str]:
    return {
        entry["symptom_id"]
        for question in bundle["questions"]
        for option in question.get("options", [])
        for entry in option.get("evidence", [])
    }


def overlap_pairs(bundle: dict) -> tuple[list[dict], list[str]]:
    askable = askable_symptoms(bundle)
    typical = {
        condition["id"]: {row["symptom_id"] for row in condition["profile"] if row.get("level") == "typical"}
        for condition in bundle["conditions"]
    }
    ids = [condition["id"] for condition in bundle["conditions"]]
    pairs, errors = [], []

    for index, left in enumerate(ids):
        for right in ids[index + 1:]:
            shared = sorted(typical[left] & typical[right])
            if not shared:
                continue
            differentiators = sorted(((typical[left] ^ typical[right]) & askable))
            pairs.append(
                {
                    "a": left,
                    "b": right,
                    "shared": shared,
                    "shared_askable": [item for item in shared if item in askable],
                    "differentiators": differentiators,
                }
            )
            if not differentiators:
                errors.append(
                    f"{left} ↔ {right}: هیچ جداکنندهٔ قابل‌پرسشی ندارد — «به پرسش تازه نیاز دارد»"
                )
    return pairs, errors


# --- scenarios -------------------------------------------------------------
def answer_for(question: dict, targets: set[str], blocked: set[str] | None = None) -> object:
    """An answer that reports as many `targets` as possible, else a neutral one.

    `blocked` holds answer values that a coverage rule reacts to (pregnancy
    `prefer_not`, "no pain" and so on). A generated persona must never trip one by
    accident, or the scenario would test the safety rule instead of the disease.
    """
    blocked = blocked or set()
    options = question.get("options", [])
    if question.get("type") == "scale":
        return "unknown" if question.get("allow_unknown") else question.get("min", 0)

    picked = [
        option["id"]
        for option in options
        if any(entry.get("state") == "present" and entry["symptom_id"] in targets for entry in option.get("evidence", []))
    ]
    if picked:
        if question.get("type") == "multi":
            exclusive = {option["id"] for option in options if option.get("exclusive")}
            picked = [option for option in picked if option not in exclusive] or picked
            order = [option["id"] for option in options]
            return [option for option in order if option in picked]
        return picked[0]

    for wanted in ("unknown", "none", "no"):
        if wanted in blocked:
            continue
        if any(option["id"] == wanted for option in options):
            return [wanted] if question.get("type") == "multi" else wanted
    # Last resort: an option that reports nothing and does not move a coverage rule.
    for option in options:
        if option["id"] in blocked or option.get("evidence"):
            continue
        return [option["id"]] if question.get("type") == "multi" else option["id"]
    return [options[-1]["id"]] if question.get("type") == "multi" else options[-1]["id"]


def blocked_answers(bundle: dict) -> dict[str, set[str]]:
    """Per question: the answer values a coverage rule reacts to."""
    blocked: dict[str, set[str]] = {}
    for rule in bundle.get("coverage_rules", []):
        for clause in rule.get("when", []):
            question_id = clause.get("question_id")
            if not question_id:
                continue
            slot = blocked.setdefault(question_id, set())
            slot |= {answer for answer in clause.get("answers", []) if isinstance(answer, str)}
    return blocked


def simulate(bundle: dict, targets: set[str], blocked: dict[str, set[str]] | None = None) -> dict:
    """Walk the real engine rules through a Python mirror of `nextQuestion`.

    Only what a scenario needs: visibility (any_of on answers) and ask_order.
    """
    answers: dict = {}
    questions = sorted(bundle["questions"], key=lambda item: item["ask_order"])

    def visible(question: dict) -> bool:
        rule = question.get("visibility", {})
        if rule.get("always"):
            return True
        for clause in rule.get("any_of", []):
            given = answers.get(clause["question_id"])
            if given is None:
                continue
            values = given if isinstance(given, list) else [given]
            if any(answer in values for answer in clause["answers"]):
                return True
        return False

    for question in questions:
        if not visible(question):
            continue
        answers[question["id"]] = answer_for(question, targets, (blocked or {}).get(question["id"], set()))

    return answers


def generate_scenarios(bundle: dict) -> list[dict]:
    scenarios = []
    blocked = blocked_answers(bundle)
    for condition in bundle["conditions"]:
        typical = {row["symptom_id"] for row in condition["profile"] if row.get("level") == "typical"}
        if not typical:
            continue
        must_not_open = [
            other["id"]
            for other in bundle["conditions"]
            if other["id"] != condition["id"]
            and all(row.get("level") == "not_expected" for row in other["profile"])
        ]
        scenarios.append(
            {
                "id": f"gen_{condition['id']}_typical",
                "generated_from": {"condition_id": condition["id"], "persona": "typical"},
                "title_fa": f"شخصیت typical برای {condition['name_fa']}",
                "answers": simulate(bundle, typical, blocked),
                "expected": {"must_open": [condition["id"]], "must_not_open": must_not_open},
            }
        )
    return scenarios


# --- report ----------------------------------------------------------------
def report_markdown(bundle: dict) -> str:
    askable = askable_symptoms(bundle)
    symptom_ids = [item["id"] for item in bundle["symptoms"]]
    profiled = {row["symptom_id"] for condition in bundle["conditions"] for row in condition["profile"]}
    pairs, overlap_errors = overlap_pairs(bundle)

    lines = [
        "# گزارش پوشش بستهٔ دانش",
        "",
        f"- بسته: `{bundle['bundle_id']}` · نسخه `{bundle['bundle_version']}` · schema `{bundle['schema_version']}`",
        f"- {len(symptom_ids)} نشانه · {len(bundle['questions'])} پرسش · {len(bundle['conditions'])} بیماری · {len(bundle['overrides'])} override",
        "",
        "## نشانه‌هایی که هیچ پرسشی نمی‌تواند گزارش کند",
        "",
    ]
    unaskable = [identifier for identifier in symptom_ids if identifier not in askable]
    lines += [f"- `{identifier}`" for identifier in unaskable] or ["- هیچ"]
    lines += ["", "## نشانه‌هایی که در هیچ پروفایلی نیستند", ""]
    unprofiled = [identifier for identifier in symptom_ids if identifier not in profiled]
    lines += [f"- `{identifier}`" for identifier in unprofiled] or ["- هیچ"]
    lines += ["", "## بیماری‌های بدون جداکننده", ""]
    lines += [f"- {error}" for error in overlap_errors] or ["- هیچ"]
    lines += ["", "## جفت‌های هم‌پوشان", ""]
    if pairs:
        lines += ["| بیماری | بیماری | نشانه‌های مشترک | جداکننده‌ها |", "| --- | --- | --- | --- |"]
        for pair in pairs:
            lines.append(
                f"| {pair['a']} | {pair['b']} | {', '.join(pair['shared'])} | {', '.join(pair['differentiators']) or '—'} |"
            )
    else:
        lines.append("- هیچ")
    lines += ["", "بازبینی: این گزارش فقط ساختار و پوشش را می‌سنجد؛ هیچ ادعای بالینی نمی‌کند.", ""]
    return "\n".join(lines)


# --- gap and conflict scan -------------------------------------------------
def _norm_label(text: str) -> str:
    """Whitespace/punctuation-insensitive label key, for duplicate detection."""
    cleaned = "".join(
        " " if ch in "،٫.؟!?:;()\u060c\u200c" else ch
        for ch in str(text).replace("\u064b", "")
    )
    return " ".join(cleaned.split())


def gap_scan(bundle: dict) -> dict:
    """Structural gaps and contradictions in one bundle — no clinical judgement.

    Every finding is derived only from what the bundle itself declares: symbol
    reachability, reference integrity, label uniqueness, review metadata. The
    scan never says whether content is *correct*; only whether it can be
    reached, told apart, and traced to a source.
    """
    symptom_ids = {item["id"] for item in bundle.get("symptoms", [])}
    condition_ids = [condition["id"] for condition in bundle.get("conditions", [])]
    source_ids = {item["id"] for item in bundle.get("sources", [])}
    askable = askable_symptoms(bundle)
    profiled = {
        row["symptom_id"]
        for condition in bundle.get("conditions", [])
        for row in condition.get("profile", [])
    }
    evidence_rows = [
        (question, option, entry)
        for question in bundle.get("questions", [])
        for option in question.get("options", [])
        for entry in option.get("evidence", [])
    ]

    broken_refs = [f"question {q['id']} option {o['id']}: unknown symptom {e['symptom_id']}"
                   for q, o, e in evidence_rows if e.get("symptom_id") not in symptom_ids]
    broken_refs += [f"condition {c['id']} profile: unknown symptom {row['symptom_id']}"
                    for c in bundle.get("conditions", [])
                    for row in c.get("profile", []) if row.get("symptom_id") not in symptom_ids]
    broken_refs += [f"overrides {item['id']}: unknown condition {item.get('condition_id')}"
                    for item in bundle.get("overrides", [])
                    if item.get("condition_id") not in condition_ids]

    def unknown_sources(where: str, refs) -> list[str]:
        return [f"{where}: unknown source {ref}" for ref in (refs or []) if ref not in source_ids]

    for question in bundle.get("questions", []):
        broken_refs += unknown_sources(f"question {question['id']}", question.get("source_refs"))
    for condition in bundle.get("conditions", []):
        for row in condition.get("profile", []):
            broken_refs += unknown_sources(f"condition {condition['id']} row {row['symptom_id']}", row.get("source_refs"))
    for kind in ("overrides", "safety_rules", "coverage_rules"):
        for item in bundle.get(kind, []):
            broken_refs += unknown_sources(f"{kind} {item.get('id')}", item.get("source_refs"))

    # A question whose answers no rule and no visibility condition ever reads.
    referenced_questions = set()
    for kind in ("safety_rules", "coverage_rules"):
        for item in bundle.get(kind, []):
            stack = [item.get("when")]
            while stack:
                node = stack.pop()
                if isinstance(node, dict):
                    if isinstance(node.get("question_id"), str):
                        referenced_questions.add(node["question_id"])
                    stack.extend(value for value in node.values() if isinstance(value, (dict, list)))
                elif isinstance(node, list):
                    stack.extend(node)
    for question in bundle.get("questions", []):
        stack = [question.get("visibility")]
        while stack:
            node = stack.pop()
            if isinstance(node, dict):
                if isinstance(node.get("question_id"), str):
                    referenced_questions.add(node["question_id"])
                stack.extend(value for value in node.values() if isinstance(value, (dict, list)))
            elif isinstance(node, list):
                stack.extend(node)

    # Dead evidence: an option that reports symbols no condition profile mentions.
    dead_options, evidence_orphans, dead_questions = [], [], []
    for question in bundle.get("questions", []):
        # A question "lands" only if some answer reports a symbol a profile uses.
        lands = False
        for option in question.get("options", []):
            symbols = [entry["symptom_id"] for entry in option.get("evidence", [])]
            if symbols and all(symbol in symptom_ids and symbol not in profiled for symbol in symbols):
                dead_options.append({"question": question["id"], "option": option["id"], "evidence": symbols})
            if symbols and any(symbol in profiled for symbol in symbols):
                lands = True
        if lands:
            continue
        if question["id"] in referenced_questions:
            evidence_orphans.append(question["id"])  # banner/visibility only, no ranking effect
        else:
            dead_questions.append(question["id"])

    inert_questions = [
        question["id"]
        for question in bundle.get("questions", [])
        if question["id"] not in referenced_questions
        and not any(entry.get("symptom_id") for option in question.get("options", []) for entry in option.get("evidence", []))
    ]

    pairs, overlap_errors = overlap_pairs(bundle)
    indistinguishable = [
        {"a": pair["a"], "b": pair["b"], "shared": pair["shared_askable"]}
        for pair in pairs if not pair["differentiators"]
    ]
    fragile = [
        {"a": pair["a"], "b": pair["b"], "only": pair["differentiators"]}
        for pair in pairs if len(pair["differentiators"]) == 1
    ]
    for error in overlap_errors:
        if error not in {f"{item['a']} ↔ {item['b']}: هیچ جداکنندهٔ قابل‌پرسشی ندارد — «به پرسش تازه نیاز دارد»" for item in indistinguishable}:
            broken_refs.append(f"overlap: {error}")

    # Duplicate labels inside one question: two options a user cannot tell apart.
    duplicate_labels = []
    for question in bundle.get("questions", []):
        seen: dict[tuple, list[str]] = {}
        for option in question.get("options", []):
            seen.setdefault(_norm_label(option.get("label_fa", "")), []).append(option["id"])
        for key, ids in seen.items():
            if len(ids) > 1:
                duplicate_labels.append({"question": question["id"], "label": " ".join(key), "options": ids})

    deprecated_in_use = [
        {"question": q["id"], "option": o["id"]}
        for q in bundle.get("questions", [])
        for o in q.get("options", [])
        if o.get("deprecated")
    ]

    missing_source_refs = [
        {"kind": "question", "id": question["id"]}
        for question in bundle.get("questions", [])
        if not question.get("source_refs")
    ]
    missing_source_refs += [
        {"kind": "condition", "id": condition["id"]}
        for condition in bundle.get("conditions", [])
        if not any(row.get("source_refs") for row in condition.get("profile", []))
    ]
    missing_source_refs += [
        {"kind": kind, "id": item.get("id")}
        for kind in ("overrides", "safety_rules", "coverage_rules")
        for item in bundle.get(kind, [])
        if not item.get("source_refs")
    ]

    review = {}
    for kind in ("conditions", "overrides", "safety_rules", "coverage_rules", "change_notes"):
        for item in bundle.get(kind, []):
            status = item.get("review_status") or "missing"
            review.setdefault(status, []).append(f"{kind}:{item.get('id') or item.get('version')}")

    red_flag_gaps = [
        condition["id"] for condition in bundle.get("conditions", []) if not condition.get("red_flag_refs")
    ]

    return {
        "bundle": bundle.get("bundle_id"),
        "bundle_version": bundle.get("bundle_version"),
        "counts": {
            "symptoms": len(symptom_ids),
            "askable_symptoms": len(askable & symptom_ids),
            "questions": len(bundle.get("questions", [])),
            "conditions": len(condition_ids),
            "overrides": len(bundle.get("overrides", [])),
            "safety_rules": len(bundle.get("safety_rules", [])),
            "coverage_rules": len(bundle.get("coverage_rules", [])),
        },
        "unaskable": sorted(symptom_ids - askable),
        "unprofiled": sorted(symptom_ids - profiled),
        "dead_options": dead_options,
        "dead_questions": sorted(set(dead_questions) - set(inert_questions)),
        "evidence_orphans": sorted(set(evidence_orphans)),
        "inert_questions": sorted(set(inert_questions)),
        "indistinguishable_pairs": indistinguishable,
        "fragile_pairs": fragile,
        "duplicate_labels": duplicate_labels,
        "deprecated_in_use": deprecated_in_use,
        "broken_refs": broken_refs,
        "missing_source_refs": missing_source_refs,
        "red_flag_gaps": red_flag_gaps,
        "review_status": {status: sorted(ids) for status, ids in sorted(review.items())},
    }


def gap_blocking(scan: dict) -> list[str]:
    """Findings that must stop an authoring run: they make content unreachable,
    ambiguous, or untraceable. Coverage gaps are reported but never block."""
    blocking = []
    blocking += [f"ارجاع شکسته — {item}" for item in scan["broken_refs"]]
    blocking += [f"پرسش مرده (هیچ گزینه‌اش اثری ندارد): {item}" for item in scan["dead_questions"]]
    blocking += [
        f"برچسب تکراری در {item['question']}: {', '.join(item['options'])}" for item in scan["duplicate_labels"]
    ]
    blocking += [
        f"گزینهٔ منسوخ هنوز پرسیده می‌شود: {item['question']}/{item['option']}" for item in scan["deprecated_in_use"]
    ]
    blocking += [f"{item['a']} ↔ {item['b']}: هیچ جداکنندهٔ قابل‌پرسشی" for item in scan["indistinguishable_pairs"]]
    return blocking


def gap_markdown(scan: dict) -> str:
    counts = scan["counts"]
    lines = [
        "# گزارش گپ و تداخل بستهٔ دانش",
        "",
        f"- بسته: `{scan['bundle']}` · نسخه `{scan['bundle_version']}`",
        f"- {counts['symptoms']} نشانه ({counts['askable_symptoms']} قابل‌پرسش) · {counts['questions']} پرسش · "
        f"{counts['conditions']} بیماری · {counts['overrides']} override · {counts['safety_rules']} قاعدهٔ ایمنی · "
        f"{counts['coverage_rules']} قاعدهٔ پوشش",
        "",
        "## گپ‌های پوشش (مسدودکننده نیستند)",
        "",
        f"- نشانهٔ بی‌پرسش (هیچ گزینه‌ای آن را گزارش نمی‌کند): {', '.join(f'`{i}`' for i in scan['unaskable']) or 'هیچ'}",
        f"- نشانهٔ بی‌پروفایل (هیچ بیماری‌ای آن را ندارد): {', '.join(f'`{i}`' for i in scan['unprofiled']) or 'هیچ'}",
        f"- بیماری بدون پرچم قرمز: {', '.join(f'`{i}`' for i in scan['red_flag_gaps']) or 'هیچ'}",
        "",
        "## تداخل و ابهام (مسدودکننده)",
        "",
        f"- ارجاع شکسته: {len(scan['broken_refs'])}",
        f"- پرسش مرده: {', '.join(scan['dead_questions']) or 'هیچ'}",
        f"- پرسش فقط‌بنری (فقط قاعدهٔ ایمنی/پوشش پاسخش را می‌خواند، در رتبه‌بندی اثری ندارد): {', '.join(scan['evidence_orphans']) or 'هیچ'}",
        f"- پرسش بی‌اثر (نه شاهد، نه قاعده، نه شرط نمایش): {', '.join(scan['inert_questions']) or 'هیچ'}",
        f"- گزینهٔ مرده: {len(scan['dead_options'])}",
        f"- برچسب تکراری: {len(scan['duplicate_labels'])}",
        f"- گزینهٔ منسوخ در حال پرسش: {len(scan['deprecated_in_use'])}",
        f"- جفت بیماری بدون جداکنندهٔ قابل‌پرسش: {len(scan['indistinguishable_pairs'])}",
        f"- جفت بیماری با تنها یک جداکننده (شکننده): {len(scan['fragile_pairs'])}",
        "",
    ]
    if scan["fragile_pairs"]:
        lines += ["| بیماری | بیماری | تنها جداکننده |", "| --- | --- | --- |"]
        lines += [f"| {item['a']} | {item['b']} | {', '.join(item['only'])} |" for item in scan["fragile_pairs"]]
        lines.append("")
    lines += ["## ردگیری و بازبینی", ""]
    lines += [f"- بدون `source_refs`: {item['kind']} `{item['id']}`" for item in scan["missing_source_refs"]] or ["- بدون `source_refs`: هیچ"]
    for status, ids in scan["review_status"].items():
        lines.append(f"- `{status}`: {len(ids)}")
    lines += ["", "این گزارش فقط ساختار را می‌سنجد؛ هیچ ادعای بالینی نمی‌کند.", ""]
    return "\n".join(lines)


def cmd_gaps(args) -> int:
    bundle, _schema, _help, _errors, _warnings = load_inputs(args)
    scan = gap_scan(bundle)
    blocking = gap_blocking(scan)
    if args.json:
        print(json.dumps(scan, ensure_ascii=False, indent=2))
    else:
        text = gap_markdown(scan)
        if args.out:
            (REPO / args.out).write_text(text, encoding="utf-8")
            print(f"wrote {args.out}")
        else:
            print(text)
    if blocking:
        for line in blocking:
            print(f"BLOCK {line}", file=sys.stderr)
        return 1 if args.strict else 0
    return 0


# --- commands --------------------------------------------------------------
def load_inputs(args) -> tuple[dict, dict, str, list[str], list[str]]:
    base = read_json(REPO / BASE_PATH)
    schema = read_json(REPO / SCHEMA_PATH)
    help_text = (REPO / HELP_JS).read_text(encoding="utf-8")
    parts = collect(REPO / args.workspace)
    bundle, problems, _ = compose(base, parts)
    authored = {item["id"] for _, item in parts["symptoms"]}
    for _, condition in parts["conditions"]:
        authored |= {
            row["symptom_id"]
            for row in condition.get("profile", [])
            if row.get("symptom_id")
        }
    return bundle, schema, help_text, problems, sorted(authored)


def cmd_validate(args) -> int:
    bundle, schema, help_text, problems, authored = load_inputs(args)
    errors, warnings = validate_bundle(bundle, schema, help_text, set(authored))
    errors = problems + errors
    for line in warnings:
        print(f"WARNING {line}")
    for line in errors:
        print(f"ERROR   {line}")
    if errors:
        print(f"\nFAIL {len(errors)} error(s) — {bundle['bundle_id']} {bundle['bundle_version']}")
        return 1
    print(
        f"OK {bundle['bundle_id']} {bundle['bundle_version']}: {len(bundle['symptoms'])} نشانه، "
        f"{len(bundle['questions'])} پرسش، {len(bundle['conditions'])} بیماری، "
        f"{len(bundle['overrides'])} override — {len(warnings)} هشدار"
    )
    print("Advisory: structure and traceability only — no clinical validation.")
    return 0


def cmd_overlap(args) -> int:
    bundle, _, _, problems, _ = load_inputs(args)
    pairs, errors = overlap_pairs(bundle)
    errors = problems + errors
    if errors:
        for line in errors:
            print(f"ERROR   {line}")
        return 1
    if args.json:
        print(json.dumps(pairs, ensure_ascii=False, indent=2))
    else:
        print(f"{len(pairs)} جفت هم‌پوشان، همه با جداکنندهٔ قابل‌پرسش")
        for pair in pairs:
            print(
                f"  {pair['a']} ↔ {pair['b']} — مشترک: {', '.join(pair['shared'])} | "
                f"جداکننده: {', '.join(pair['differentiators'])}"
            )
    return 0


def cmd_gen_scenarios(args) -> int:
    bundle, _, _, problems, _ = load_inputs(args)
    if problems:
        for line in problems:
            print(f"ERROR   {line}")
        return 1
    scenarios = generate_scenarios(bundle)
    out = REPO / args.out
    out.mkdir(parents=True, exist_ok=True)
    for stale in out.glob("gen_*.json"):
        stale.unlink()
    for scenario in scenarios:
        (out / f"{scenario['id']}.json").write_text(
            json.dumps(scenario, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
        )
    print(f"wrote {len(scenarios)} scenario(s) to {args.out}")
    return 0


def bump_version(version: str) -> str:
    """Content change = minor bump; the `-draft` suffix stays until review."""
    match = re.fullmatch(r"(\d+)\.(\d+)\.(\d+)(-.+)?", version)
    if not match:
        return version
    major, minor, _patch, suffix = match.groups()
    return f"{major}.{int(minor) + 1}.0{suffix or ''}"


def compose_for_output(args) -> tuple[dict, bytes, list[str], list[str], dict, set[str]]:
    """base + workspace (+ version bump) → (bundle, payload, problems, errors, warnings, parts)."""
    base = read_json(REPO / BASE_PATH)
    schema = read_json(REPO / SCHEMA_PATH)
    help_text = (REPO / HELP_JS).read_text(encoding="utf-8")
    parts = collect(REPO / args.workspace)
    bundle, problems, changed = compose(base, parts)

    if changed and not args.no_bump:
        previous = bundle["bundle_version"]
        bundle["bundle_version"] = bump_version(previous)
        bundle["change_notes"] = [
            {
                "version": bundle["bundle_version"],
                "date": args.date,
                "changes_fa": [
                    f"افزودن {len(parts['conditions'])} بیماری، {len(parts['questions'])} پرسش، "
                    f"{len(parts['symptoms'])} نشانه و {len(parts['sources'])} منبع با ابزار نویسندگی "
                    f"(نسخهٔ قبلی: {previous}). محتوای تازه pending_team_review است."
                ],
            }
        ] + list(bundle.get("change_notes", []))

    authored = {item["id"] for _, item in parts["symptoms"]}
    for _, condition in parts["conditions"]:
        authored |= {
            row["symptom_id"] for row in condition.get("profile", []) if row.get("symptom_id")
        }

    errors, warnings = validate_bundle(bundle, schema, help_text, authored)
    errors = problems + errors
    return bundle, serialise(bundle), problems, errors, warnings, parts


def cmd_publish(args) -> int:
    """Write the bundle under the name the plugin reads from uploads/hj-sc/.

    The file name is the contract: `knowledge-bundle.<bundle_version>.<sha8>.json`.
    Dropping it into `wp-content/uploads/hj-sc/` publishes the content without a
    new plugin release; the hash in the name is both the cache-buster and the
    proof that the bytes are the ones this command validated.
    """
    bundle, payload, _problems, errors, warnings, _parts = compose_for_output(args)
    if errors:
        for line in errors:
            print(f"ERROR   {line}")
        print("\nFAIL nothing was published — fix the data first")
        return 1

    digest = hashlib.sha256(payload).hexdigest()
    name = f"knowledge-bundle.{bundle['bundle_version']}.{digest[:8]}.json"
    target = (REPO / args.out / name) if not Path(args.out).is_absolute() else Path(args.out) / name

    if args.check:
        if not target.is_file():
            print(f"FAIL missing {target}: run publish-bundle")
            return 1
        if hashlib.sha256(target.read_bytes()).hexdigest() != digest:
            print(f"FAIL {target} is stale")
            return 1
        print(f"OK {target} matches the authoring pipeline — sha8 {digest[:8]}")
    else:
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(payload)
        print(f"wrote {target} ({len(payload)} bytes, sha8 {digest[:8]})")

    for line in warnings:
        print(f"WARNING {line}")
    print(
        f"{bundle['bundle_id']} {bundle['bundle_version']} · schema {bundle['schema_version']} · "
        f"{len(bundle['symptoms'])} نشانه، {len(bundle['questions'])} پرسش، "
        f"{len(bundle['conditions'])} بیماری"
    )
    print("\nنصب روی سایت:")
    print(f"  1) این فایل را در wp-content/uploads/{'hj-sc'}/ بگذارید: {name}")
    print("  2) صفحهٔ ابزار را یک بار با حساب مدیر باز کنید (یا کش LiteSpeed را پاک کنید).")
    print(f"  3) برای برگشت: option «hj_sc_active_bundle» را روی نام فایل قبلی بگذارید.")
    return 0


def cmd_build(args) -> int:
    base = read_json(REPO / BASE_PATH)
    schema = read_json(REPO / SCHEMA_PATH)
    help_text = (REPO / HELP_JS).read_text(encoding="utf-8")
    parts = collect(REPO / args.workspace)
    bundle, problems, changed = compose(base, parts)
    authored = {item["id"] for _, item in parts["symptoms"]}
    for _, condition in parts["conditions"]:
        authored |= {
            row["symptom_id"] for row in condition.get("profile", []) if row.get("symptom_id")
        }

    if changed and not args.no_bump:
        previous = bundle["bundle_version"]
        bundle["bundle_version"] = bump_version(previous)
        bundle["change_notes"] = [
            {
                "version": bundle["bundle_version"],
                "date": args.date,
                "changes_fa": [
                    f"افزودن {len(parts['conditions'])} بیماری، {len(parts['questions'])} پرسش، "
                    f"{len(parts['symptoms'])} نشانه و {len(parts['sources'])} منبع با ابزار نویسندگی "
                    f"(نسخهٔ قبلی: {previous}). محتوای تازه pending_team_review است."
                ],
            }
        ] + list(bundle.get("change_notes", []))

    errors, warnings = validate_bundle(bundle, schema, help_text, set(authored))
    errors = problems + errors
    if errors:
        for line in errors:
            print(f"ERROR   {line}")
        print("\nFAIL the bundle was not written — fix the data first")
        return 1

    payload = serialise(bundle)
    digest = hashlib.sha256(payload).hexdigest()
    target = REPO / args.out

    if args.check:
        if not target.is_file():
            print(f"FAIL missing {args.out}: run build-bundle")
            return 1
        current = target.read_bytes()
        if hashlib.sha256(current).hexdigest() != digest:
            print(
                f"FAIL {args.out} is stale (sha256 {hashlib.sha256(current).hexdigest()[:12]} ≠ {digest[:12]})"
            )
            return 1
        print(f"OK {args.out} matches the authoring pipeline — sha256 {digest}")
    else:
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(payload)
        print(f"wrote {args.out} (sha256 {digest})")

    for line in warnings:
        print(f"WARNING {line}")
    print(
        f"{bundle['bundle_id']} {bundle['bundle_version']}: {len(bundle['symptoms'])} نشانه، "
        f"{len(bundle['questions'])} پرسش، {len(bundle['conditions'])} بیماری، "
        f"{len(bundle['overrides'])} override"
    )
    return 0


def cmd_report(args) -> int:
    bundle, _, _, problems, _ = load_inputs(args)
    if problems:
        for line in problems:
            print(f"ERROR   {line}")
        return 1
    text = report_markdown(bundle)
    if args.out:
        (REPO / args.out).parent.mkdir(parents=True, exist_ok=True)
        (REPO / args.out).write_text(text, encoding="utf-8")
        print(f"wrote {args.out}")
    else:
        print(text)
    return 0


def cmd_scaffold(args) -> int:
    workspace = REPO / args.workspace
    target = workspace / "conditions" / f"{args.id}.json"
    if target.exists() and not args.force:
        print(f"FAIL {target.relative_to(REPO)} already exists (use --force)")
        return 1
    base = read_json(REPO / BASE_PATH)
    catalog = "\n".join(
        f"  - {item['id']} — {item['label_fa']}" for item in base["symptoms"]
    )
    scaffold = {
        "id": args.id,
        "name_fa": args.name,
        "article_slug": args.slug or None,
        "profile_ref": None,
        "link_status": "unmapped",
        "profile": [],
        "red_flag_refs": [],
        "review_status": "pending_team_review",
        "_scaffold_fa": (
            "ردیف‌های profile را از کاتالوگ زیر کامل کنید (حداقل ۳ ردیف، حداقل یکی typical). "
            "هر ردیف: symptom_id، level (typical|possible|rare|not_expected)، profile_line، source_refs.\n"
            f"{catalog}"
        ),
    }
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(json.dumps(scaffold, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"wrote {target.relative_to(REPO)}")
    print("بعدی: profile را کامل کنید، منبع تازه را در sources/ بگذارید، بعد `validate` و `build-bundle`.")
    return 0


def cmd_add(args) -> int:
    workspace = REPO / args.workspace
    source = Path(args.file)
    if not source.is_file():
        print(f"FAIL {args.file} does not exist")
        return 1
    payload = strip_private(read_json(source))
    if args.kind == "overrides":
        if not isinstance(payload, list):
            print("FAIL overrides must be a JSON array")
            return 1
        target = workspace / "overrides.json"
        existing = read_json(target) if target.is_file() else []
        known = {row["id"] for row in existing}
        clashing = [row["id"] for row in payload if row["id"] in known]
        if clashing:
            print(f"FAIL override id already exists: {', '.join(clashing)}")
            return 1
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(json.dumps(existing + payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
        print(f"added {len(payload)} override(s) → {target.relative_to(REPO)}")
        return 0

    identifier = payload.get("id")
    if not identifier:
        print("FAIL the file has no \"id\"")
        return 1
    target = workspace / args.kind / f"{identifier}.json"
    if target.exists():
        print(f"FAIL {target.relative_to(REPO)} already exists — ids are never overwritten")
        return 1
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"added {args.kind[:-1]} {identifier} → {target.relative_to(REPO)}")
    return 0


def main(argv: list[str]) -> int:
    parser = argparse.ArgumentParser(description="Knowledge-bundle authoring CLI")
    parser.add_argument("--workspace", default=WORKSPACE)
    sub = parser.add_subparsers(dest="command", required=True)

    for name, handler, help_text in (
        ("validate", cmd_validate, "structural + authoring checks"),
        ("report", cmd_report, "coverage report (markdown)"),
    ):
        item = sub.add_parser(name, help=help_text)
        item.set_defaults(func=handler)
        if name == "report":
            item.add_argument("--out", default=None)

    overlap = sub.add_parser("overlap", help="shared symptoms and differentiators")
    overlap.add_argument("--json", action="store_true")
    overlap.set_defaults(func=cmd_overlap)

    gaps = sub.add_parser("gaps", help="structural gaps, dead content, duplicate labels")
    gaps.add_argument("--json", action="store_true")
    gaps.add_argument("--strict", action="store_true", help="exit 1 on blocking findings")
    gaps.add_argument("--out", default=None)
    gaps.set_defaults(func=cmd_gaps)

    scenarios = sub.add_parser("gen-scenarios", help="one typical persona per condition")
    scenarios.add_argument("--out", default="docs/symptom-checker/scenarios/generated")
    scenarios.set_defaults(func=cmd_gen_scenarios)

    build = sub.add_parser("build-bundle", help="base + workspace → bundle")
    build.add_argument("--out", default=FINAL_PATH)
    build.add_argument("--check", action="store_true")
    build.add_argument("--no-bump", action="store_true")
    build.add_argument("--date", default="2026-10-31")
    build.set_defaults(func=cmd_build)

    scaffold = sub.add_parser(
        "scaffold-condition",
        aliases=["add-condition"],
        help="condition skeleton with the catalog",
    )
    scaffold.add_argument("id")
    scaffold.add_argument("--name", required=True)
    scaffold.add_argument("--slug", default=None)
    scaffold.add_argument("--force", action="store_true")
    scaffold.set_defaults(func=cmd_scaffold)

    publish = sub.add_parser("publish-bundle", help="bundle named for uploads/hj-sc/ (phase 5-a)")
    publish.add_argument("--out", default="dist/hj-sc")
    publish.add_argument("--check", action="store_true")
    publish.add_argument("--no-bump", action="store_true")
    publish.add_argument("--date", default=None)
    publish.set_defaults(func=cmd_publish)

    add = sub.add_parser("add", help="add one data file (source, symptom, question, condition, overrides)")
    add.add_argument("kind", choices=list(PARTS) + ["overrides"])
    add.add_argument("--file", required=True)
    add.set_defaults(func=cmd_add)

    # A default date keeps `change_notes` reproducible in CI.
    args = parser.parse_args(argv)
    if getattr(args, "date", None) is None and hasattr(args, "date"):
        args.date = date.today().isoformat()
    return args.func(args)


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
