#!/usr/bin/env python3
"""Tests for the authoring CLI (`author.py`).

The CLI is the only door a new disease may come through, so the tests pin the
promises the runbook makes about that door:

* the shipped bundle is exactly what `base + empty workspace` produces, and the
  command is deterministic (run it twice, same bytes);
* a question without a help line cannot reach a bundle;
* a disease that no question can distinguish stops with
  «به پرسش تازه نیاز دارد» and a non-zero exit;
* a new symptom that no option reports is an error, not a warning;
* the acceptance dataset itself is valid, opens its own disease and bumps only
  the minor version.

Everything runs against a temporary workspace, so the tests never touch the
committed data.
"""
from __future__ import annotations

import hashlib
import json
import shutil
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

HERE = Path(__file__).resolve().parent
REPO = HERE.parents[1]
CLI = HERE / "author.py"
EXAMPLE = REPO / "docs/symptom-checker/authoring/examples/cholecystitis"
BASE = REPO / "docs/symptom-checker/phase-1/knowledge-bundle.v2.base.json"
SHIPPED = REPO / "docs/symptom-checker/phase-1/knowledge-bundle.v2.json"
DEMO = REPO / "docs/symptom-checker/phase-3/built/knowledge-bundle.v2.cholecystitis-demo.json"


def run(*args: str, cwd: Path = REPO, env: dict | None = None) -> subprocess.CompletedProcess:
    import os

    return subprocess.run(
        [sys.executable, str(CLI), *args],
        cwd=cwd,
        capture_output=True,
        text=True,
        timeout=300,
        env={**os.environ, **(env or {})},
    )


class Fixture(unittest.TestCase):
    def setUp(self) -> None:
        self._tmp = tempfile.TemporaryDirectory()
        self.tmp = Path(self._tmp.name)

    def tearDown(self) -> None:
        self._tmp.cleanup()

    def workspace(self, name: str = "ws") -> Path:
        """Copy of the acceptance dataset into the temporary directory."""
        target = self.tmp / name
        shutil.copytree(EXAMPLE, target)
        return target

    def write(self, path: Path, data: object) -> None:
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


class ShippedBundleTest(Fixture):
    def test_shipped_bundle_is_reproducible(self) -> None:
        done = run("build-bundle", "--check")
        self.assertEqual(done.returncode, 0, done.stdout + done.stderr)
        self.assertIn("matches the authoring pipeline", done.stdout)

    def test_empty_workspace_reproduces_the_base(self) -> None:
        """Nothing authored yet → the bundle must equal the base, byte for byte."""
        done = run("build-bundle", "--no-bump", "--out", "tmp-check.json")
        self.assertEqual(done.returncode, 0, done.stdout + done.stderr)
        produced = REPO / "tmp-check.json"
        try:
            self.assertEqual(produced.read_bytes(), BASE.read_bytes())
        finally:
            produced.unlink()
            if (REPO / "tmp-check.json").exists():  # pragma: no cover
                (REPO / "tmp-check.json").unlink()

    def test_demo_bundle_is_reproducible(self) -> None:
        relative = DEMO.relative_to(REPO).as_posix()
        done = run("--workspace", str(EXAMPLE), "build-bundle", "--out", relative, "--check")
        self.assertEqual(done.returncode, 0, done.stdout + done.stderr)

    def test_committed_generated_scenarios_are_reproducible(self) -> None:
        """`docs/symptom-checker/scenarios/generated/` must be what the CLI writes today."""
        committed = REPO / "docs/symptom-checker/scenarios/generated"
        scratch = self.tmp / "generated"
        done = run("gen-scenarios", "--out", str(scratch))
        self.assertEqual(done.returncode, 0, done.stdout + done.stderr)
        self.assertEqual(
            sorted(path.name for path in committed.glob("gen_*.json")),
            sorted(path.name for path in scratch.glob("gen_*.json")),
        )
        for path in committed.glob("gen_*.json"):
            self.assertEqual(path.read_bytes(), (scratch / path.name).read_bytes(), path.name)

    def test_publish_bundle_names_the_file_for_uploads(self) -> None:
        out = self.tmp / "hj-sc"
        done = run("publish-bundle", "--out", str(out))
        self.assertEqual(done.returncode, 0, done.stdout + done.stderr)
        files = sorted(path.name for path in out.glob("knowledge-bundle.*.json"))
        self.assertEqual(len(files), 1, files)
        name = files[0]
        self.assertRegex(name, r"^knowledge-bundle\.[0-9A-Za-z._-]+\.[0-9a-f]{8}\.json$")
        payload = (out / name).read_bytes()
        self.assertEqual(hashlib.sha256(payload).hexdigest()[:8], name.split(".")[-2])
        bundle = json.loads(payload)
        self.assertIn(bundle["bundle_version"], name)
        self.assertIn("hj-sc", done.stdout)

    def test_publish_bundle_check_detects_drift(self) -> None:
        out = self.tmp / "hj-sc"
        self.assertEqual(run("publish-bundle", "--out", str(out)).returncode, 0)
        self.assertEqual(run("publish-bundle", "--out", str(out), "--check").returncode, 0)
        for path in out.glob("knowledge-bundle.*.json"):
            path.write_bytes(path.read_bytes().replace(b"", b"", 1) + b" ")
        self.assertEqual(run("publish-bundle", "--out", str(out), "--check").returncode, 1)

    def test_publish_bundle_refuses_broken_data(self) -> None:
        """Nothing is published when the data cannot pass the gates."""
        workspace = self.tmp / "broken"
        shutil.copytree(EXAMPLE, workspace)
        condition = json.loads((workspace / "conditions/chole.json").read_text(encoding="utf-8"))
        condition["profile"][0]["symptom_id"] = "abd.does_not_exist"
        self.write(workspace / "conditions/chole.json", condition)
        out = self.tmp / "hj-sc"
        done = run("--workspace", str(workspace), "publish-bundle", "--out", str(out))
        self.assertEqual(done.returncode, 1)
        self.assertIn("abd.does_not_exist", done.stdout)
        self.assertEqual([], list(out.glob("*.json")) if out.exists() else [])

    def test_publish_bundle_of_an_empty_workspace_is_the_base(self) -> None:
        empty = self.tmp / "empty"
        empty.mkdir()
        out = self.tmp / "hj-sc"
        done = run("--workspace", str(empty), "publish-bundle", "--out", str(out), "--no-bump")
        self.assertEqual(done.returncode, 0, done.stdout + done.stderr)
        [path] = list(out.glob("*.json"))
        self.assertEqual(path.read_bytes(), BASE.read_bytes())

    def test_build_is_deterministic(self) -> None:
        first = run("--workspace", str(EXAMPLE), "build-bundle", "--no-bump", "--out", "a.json")
        second = run("--workspace", str(EXAMPLE), "build-bundle", "--no-bump", "--out", "b.json")
        self.assertEqual(first.returncode, 0, first.stdout + first.stderr)
        self.assertEqual(second.returncode, 0, second.stdout + second.stderr)
        try:
            self.assertEqual((REPO / "a.json").read_bytes(), (REPO / "b.json").read_bytes())
        finally:
            for name in ("a.json", "b.json"):
                (REPO / name).unlink(missing_ok=True)


class RefusalTest(Fixture):
    def test_question_without_help_cannot_be_built(self) -> None:
        workspace = self.workspace()
        question = json.loads((workspace / "questions/q_pain_after_meal.json").read_text(encoding="utf-8"))
        original = (REPO / "symptom-checker/ui/question-help.js").read_text(encoding="utf-8")
        start = original.index("  q_pain_after_meal: {")
        end = original.index("\n  },\n", start) + len("\n  },\n")
        scratch_js = self.tmp / "question-help.js"
        scratch_js.write_text(original[:start] + original[end:], encoding="utf-8")
        done = run(
            "--workspace",
            str(workspace),
            "build-bundle",
            "--out",
            "never.json",
            env={"AUTHOR_HELP_JS": str(scratch_js)},
        )
        self.assertEqual(done.returncode, 1)
        self.assertIn(question["id"], done.stdout)
        self.assertIn("خط راهنما ندارد", done.stdout)
        self.assertFalse((REPO / "never.json").exists())

    def test_twin_diseases_need_a_new_question(self) -> None:
        workspace = self.workspace()
        twin = json.loads((workspace / "conditions/chole.json").read_text(encoding="utf-8"))
        twin["id"] = "chole_twin"
        self.write(workspace / "conditions/chole_twin.json", twin)
        done = run("--workspace", str(workspace), "overlap")
        self.assertEqual(done.returncode, 1)
        self.assertIn("به پرسش تازه نیاز دارد", done.stdout)

    def test_overlap_json_exits_non_zero_too(self) -> None:
        workspace = self.workspace()
        twin = json.loads((workspace / "conditions/chole.json").read_text(encoding="utf-8"))
        twin["id"] = "chole_twin"
        self.write(workspace / "conditions/chole_twin.json", twin)
        self.assertEqual(run("--workspace", str(workspace), "overlap", "--json").returncode, 1)

    def test_new_symptom_without_a_question_is_an_error(self) -> None:
        workspace = self.workspace()
        condition = json.loads((workspace / "conditions/chole.json").read_text(encoding="utf-8"))
        condition["profile"].append(
            {
                "symptom_id": "abd.invented_gap",
                "level": "possible",
                "profile_line": "ردیف آزمایشی برای آزمون شکاف",
                "source_refs": ["src_niddk_gallstones"],
            }
        )
        self.write(workspace / "conditions/chole.json", condition)
        done = run("--workspace", str(workspace), "validate")
        self.assertEqual(done.returncode, 1)
        self.assertIn("abd.invented_gap", done.stdout)
        self.assertIn("به پرسش تازه نیاز دارد", done.stdout)

    def test_duplicate_id_is_refused(self) -> None:
        workspace = self.workspace()
        condition = json.loads((workspace / "conditions/chole.json").read_text(encoding="utf-8"))
        self.write(workspace / "conditions/chole_copy.json", condition)
        done = run("--workspace", str(workspace), "validate")
        self.assertEqual(done.returncode, 1)
        self.assertIn("duplicate", done.stdout)


class AcceptanceDatasetTest(Fixture):
    def setUp(self) -> None:
        super().setUp()
        done = run("--workspace", str(EXAMPLE), "build-bundle", "--out", "self.json")
        self.assertEqual(done.returncode, 0, done.stdout + done.stderr)
        self.bundle = json.loads((REPO / "self.json").read_text(encoding="utf-8"))
        (REPO / "self.json").unlink()

    def test_validate_and_overlap_are_green(self) -> None:
        self.assertEqual(run("--workspace", str(EXAMPLE), "validate").returncode, 0)
        self.assertEqual(run("--workspace", str(EXAMPLE), "overlap").returncode, 0)

    def test_only_a_minor_bump(self) -> None:
        """New content may add a disease, never a new major — v1 has to keep loading."""
        base = json.loads(BASE.read_text(encoding="utf-8"))
        base_major, base_minor, _ = base["bundle_version"].split(".")
        major, minor, _ = self.bundle["bundle_version"].split(".")
        self.assertEqual(major, base_major)
        self.assertEqual(int(minor), int(base_minor) + 1)
        self.assertTrue(self.bundle["change_notes"][0]["changes_fa"])

    def test_new_content_is_pending_review_and_sourced(self) -> None:
        source_ids = {source["id"] for source in self.bundle["sources"]}
        condition = next(item for item in self.bundle["conditions"] if item["id"] == "chole")
        self.assertEqual(condition["review_status"], "pending_team_review")
        self.assertIn("#", condition["profile_ref"])
        for row in condition["profile"]:
            self.assertTrue(row["source_refs"], row)
            self.assertTrue(set(row["source_refs"]) <= source_ids, row)

    def test_help_line_is_complete(self) -> None:
        help_js = (REPO / "symptom-checker/ui/question-help.js").read_text(encoding="utf-8")
        question = next(item for item in self.bundle["questions"] if item["id"] == "q_pain_after_meal")
        block = help_js[help_js.index("  q_pain_after_meal: {"):]
        for option in question["options"]:
            self.assertIn(f"{option['id']}:", block.split("\n  },\n")[0])

    def test_scenario_for_the_new_disease_reports_its_own_symptoms(self) -> None:
        out = self.tmp / "scenarios"
        done = run("--workspace", str(EXAMPLE), "gen-scenarios", "--out", str(out))
        self.assertEqual(done.returncode, 0, done.stdout + done.stderr)
        files = sorted(out.glob("*.json"))
        self.assertEqual(len(files), len(self.bundle["conditions"]))
        scenario = json.loads((out / "gen_chole_typical.json").read_text(encoding="utf-8"))
        self.assertEqual(scenario["generated_from"]["condition_id"], "chole")
        self.assertEqual(scenario["answers"]["q_pain_after_meal"], "yes")
        # A generated persona must not trip a coverage rule by accident.
        self.assertEqual(scenario["answers"]["q_pregnancy_possible"], "no")
        self.assertIn("chole", scenario["expected"]["must_open"])

    def test_report_lists_every_disease(self) -> None:
        out = self.tmp / "report.md"
        done = run("--workspace", str(EXAMPLE), "report", "--out", str(out))
        self.assertEqual(done.returncode, 0, done.stdout + done.stderr)
        text = out.read_text(encoding="utf-8")
        for condition in self.bundle["conditions"]:
            self.assertIn(condition["id"], text)


class GapScanTest(unittest.TestCase):
    """`gaps` is the "no silent dead ends" gate: content that cannot be reached,
    told apart, or traced back must be visible before it ships."""

    @staticmethod
    def tiny() -> dict:
        return {
            "bundle_id": "tiny",
            "bundle_version": "0.0.1",
            "schema_version": "2.0.0",
            "sources": [{"id": "s1"}],
            "symptoms": [{"id": "a"}, {"id": "b"}, {"id": "orphan"}],
            "questions": [
                {
                    "id": "q1",
                    "source_refs": ["s1"],
                    "options": [
                        {"id": "yes", "label_fa": "بله", "evidence": [{"symptom_id": "a", "state": "present"}]},
                        {"id": "no", "label_fa": "نه", "evidence": [{"symptom_id": "a", "state": "absent"}]},
                    ],
                }
            ],
            "conditions": [
                {
                    "id": "c1",
                    "profile": [{"symptom_id": "a", "level": "typical", "source_refs": ["s1"]}],
                    "red_flag_refs": ["r1"],
                    "review_status": "pending_team_review",
                }
            ],
            "overrides": [],
            "safety_rules": [],
            "coverage_rules": [],
            "change_notes": [],
        }

    def setUp(self) -> None:
        sys.path.insert(0, str(HERE))
        import author  # noqa: PLC0415

        self.author = author

    def scan(self, bundle: dict) -> dict:
        return self.author.gap_scan(bundle)

    def test_a_clean_bundle_blocks_nothing(self) -> None:
        scan = self.scan(self.tiny())
        self.assertEqual(self.author.gap_blocking(scan), [])
        self.assertEqual(scan["dead_questions"], [])
        self.assertEqual(scan["duplicate_labels"], [])

    def test_an_unprofiled_symptom_is_a_coverage_gap_not_a_blocker(self) -> None:
        """`b` and `orphan` are declared but no condition uses them: a gap, not an error."""
        scan = self.scan(self.tiny())
        self.assertEqual(scan["unprofiled"], ["b", "orphan"])
        self.assertEqual(scan["unaskable"], ["b", "orphan"])
        self.assertEqual(self.author.gap_blocking(scan), [])

    def test_an_option_that_reports_only_orphans_is_dead(self) -> None:
        bundle = self.tiny()
        bundle["questions"][0]["options"].append(
            {"id": "maybe", "label_fa": "شاید", "evidence": [{"symptom_id": "orphan", "state": "present"}]}
        )
        scan = self.scan(bundle)
        self.assertEqual([item["option"] for item in scan["dead_options"]], ["maybe"])
        self.assertEqual(scan["dead_questions"], [])  # q1 still lands on `a`

    def test_a_question_that_nothing_reads_is_inert_and_one_a_rule_reads_is_not(self) -> None:
        bundle = self.tiny()
        bundle["questions"].append(
            {"id": "q_ignored", "source_refs": ["s1"], "options": [{"id": "x", "label_fa": "الف", "evidence": []}]}
        )
        bundle["questions"].append(
            {"id": "q_banner", "source_refs": ["s1"], "options": [{"id": "y", "label_fa": "ب", "evidence": []}]}
        )
        bundle["safety_rules"].append(
            {"id": "sf", "when": [{"question_id": "q_banner", "answers": ["y"]}], "source_refs": ["s1"]}
        )
        scan = self.scan(bundle)
        self.assertEqual(scan["inert_questions"], ["q_ignored"])
        self.assertEqual(scan["evidence_orphans"], ["q_banner"])

    def test_two_options_with_the_same_label_block(self) -> None:
        bundle = self.tiny()
        bundle["questions"][0]["options"][1]["label_fa"] = "بله "
        scan = self.scan(bundle)
        self.assertEqual(scan["duplicate_labels"][0]["question"], "q1")
        self.assertTrue(any("برچسب تکراری" in line for line in self.author.gap_blocking(scan)))

    def test_a_broken_reference_blocks(self) -> None:
        bundle = self.tiny()
        bundle["questions"][0]["options"][0]["evidence"] = [{"symptom_id": "ghost", "state": "present"}]
        bundle["safety_rules"].append({"id": "sf", "when": [], "source_refs": ["src_missing"]})
        scan = self.scan(bundle)
        blockers = self.author.gap_blocking(scan)
        self.assertTrue(any("ghost" in line for line in blockers))
        self.assertTrue(any("src_missing" in line for line in blockers))
        self.assertEqual(scan["review_status"]["pending_team_review"], ["conditions:c1"])

    def test_a_rule_without_sources_is_a_traceability_gap(self) -> None:
        bundle = self.tiny()
        bundle["safety_rules"].append({"id": "sf", "when": [], "source_refs": []})
        scan = self.scan(bundle)
        self.assertIn({"kind": "safety_rules", "id": "sf"}, scan["missing_source_refs"])
        self.assertEqual(self.author.gap_blocking(scan), [])

    def test_a_deprecated_option_that_is_still_asked_blocks(self) -> None:
        bundle = self.tiny()
        bundle["questions"][0]["options"][1]["deprecated"] = True
        scan = self.scan(bundle)
        self.assertEqual(scan["deprecated_in_use"], [{"question": "q1", "option": "no"}])
        self.assertTrue(any("منسوخ" in line for line in self.author.gap_blocking(scan)))

    def test_the_shipped_bundle_has_no_blocking_finding(self) -> None:
        done = run("gaps", "--strict", "--json")
        self.assertEqual(done.returncode, 0, done.stderr)
        scan = json.loads(done.stdout)
        self.assertEqual(scan["broken_refs"], [])
        self.assertEqual(scan["duplicate_labels"], [])
        self.assertEqual(scan["dead_questions"], [])
        self.assertEqual(scan["indistinguishable_pairs"], [])
        # Known, reported coverage gaps — pinned so a fix shows up as a diff here.
        self.assertEqual(scan["unaskable"], ["abd.abdominal_cramp", "abd.bladder_pressure"])
        self.assertEqual(scan["unprofiled"], ["abd.blood_stool"])
        self.assertEqual(scan["inert_questions"], ["q_pain_onset", "q_pain_severity"])

    def test_markdown_mentions_every_section(self) -> None:
        text = self.author.gap_markdown(self.scan(self.tiny()))
        for heading in ("گپ‌های پوشش", "تداخل و ابهام", "ردگیری و بازبینی"):
            self.assertIn(heading, text)


class HashTest(unittest.TestCase):
    def test_shipped_and_demo_digests_differ(self) -> None:
        """The demo may never be confused with the shipped bundle."""
        shipped = hashlib.sha256(SHIPPED.read_bytes()).hexdigest()
        demo = hashlib.sha256(DEMO.read_bytes()).hexdigest()
        self.assertNotEqual(shipped, demo)


if __name__ == "__main__":
    unittest.main()
