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


class HashTest(unittest.TestCase):
    def test_shipped_and_demo_digests_differ(self) -> None:
        """The demo may never be confused with the shipped bundle."""
        shipped = hashlib.sha256(SHIPPED.read_bytes()).hexdigest()
        demo = hashlib.sha256(DEMO.read_bytes()).hexdigest()
        self.assertNotEqual(shipped, demo)


if __name__ == "__main__":
    unittest.main()
