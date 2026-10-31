#!/usr/bin/env python3
"""Tests for the v1 → v2 migrator and the v2 validator.

The migrator is the only thing allowed to produce the v2 bundle — no hand edits —
so the tests concentrate on three promises: the committed file is exactly what
the script produces, running it twice changes nothing, and the validator
actually rejects broken data instead of waving it through.
"""
from __future__ import annotations

import copy
import hashlib
import importlib.util
import json
import sys
import tempfile
import unittest
from pathlib import Path

HERE = Path(__file__).resolve().parent
REPO = HERE.parents[1]


def load(name):
    spec = importlib.util.spec_from_file_location(name, HERE / f"{name}.py")
    module = importlib.util.module_from_spec(spec)
    sys.modules[spec.name] = module
    spec.loader.exec_module(module)
    return module


migrate_tool = load("v1_to_v2")
validator = load("validate_v2")


class MigratorTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.v1 = migrate_tool.read_json(migrate_tool.V1_PATH)
        cls.evidence = migrate_tool.read_json(migrate_tool.EVIDENCE_PATH)
        cls.bundle = migrate_tool.migrate(cls.v1, cls.evidence)

    def test_migration_is_deterministic(self):
        first = migrate_tool.serialise(migrate_tool.migrate(self.v1, self.evidence))
        second = migrate_tool.serialise(migrate_tool.migrate(self.v1, self.evidence))
        self.assertEqual(first, second)
        self.assertEqual(hashlib.sha256(first).hexdigest(), hashlib.sha256(second).hexdigest())

    def test_committed_bundle_matches_the_migrator(self):
        _, payload, digest = migrate_tool.build()
        committed = (REPO / migrate_tool.V2_PATH).read_bytes()
        self.assertEqual(
            hashlib.sha256(committed).hexdigest(),
            digest,
            "docs/symptom-checker/phase-1/knowledge-bundle.v2.json is stale: run v1_to_v2.py",
        )
        self.assertEqual(committed, payload)

    def test_check_flag_passes_on_the_committed_file(self):
        self.assertEqual(migrate_tool.main(["--check"]), 0)

    def test_check_flag_fails_when_the_target_is_missing(self):
        with tempfile.TemporaryDirectory() as work:
            self.assertEqual(migrate_tool.main(["--check", "--out", str(Path(work) / "missing.json")]), 1)

    def test_symptom_ids_get_a_cluster_prefix_and_keep_their_v1_id(self):
        symptoms = {item["id"]: item for item in self.bundle["symptoms"]}
        self.assertIn("abd.pain_rlq", symptoms)
        self.assertEqual(symptoms["abd.pain_rlq"]["legacy_id"], "s_pain_rlq")
        self.assertEqual(symptoms["abd.pain_rlq"]["cluster"], "abd")
        self.assertEqual(len(symptoms), len(self.v1["symptoms"]))

    def test_every_evidence_row_lands_on_an_option(self):
        rows = [
            row
            for question in self.bundle["questions"]
            for option in question.get("options", [])
            for row in option["evidence"]
        ]
        self.assertEqual(len(rows), len(self.evidence["entries"]))
        symptom_ids = {item["id"] for item in self.bundle["symptoms"]}
        for row in rows:
            self.assertIn(row["symptom_id"], symptom_ids)
            self.assertIn(row["state"], ("present", "absent"))

    def test_profile_rows_start_as_typical(self):
        for condition in self.bundle["conditions"]:
            self.assertTrue(condition["profile"])
            for row in condition["profile"]:
                self.assertEqual(row["level"], "typical")
                self.assertTrue(row["profile_line"])
                self.assertTrue(row["source_refs"])

    def test_overrides_are_the_v1_paths_untouched(self):
        self.assertEqual(self.bundle["overrides"], self.v1["paths"])

    def test_unchanged_blocks_are_copied_verbatim(self):
        for key in ("scope", "sources", "safety_rules", "coverage_rules", "disclaimers", "answer_states", "change_notes"):
            self.assertEqual(self.bundle[key], self.v1[key], key)
        self.assertEqual(self.bundle["bundle_version"], self.v1["bundle_version"])
        self.assertEqual(self.bundle["schema_version"], "2.0.0")


class ValidatorTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.schema = validator.load(validator.SCHEMA_PATH)
        cls.bundle = json.loads((REPO / validator.DEFAULT_BUNDLE).read_text(encoding="utf-8"))

    def broken(self):
        return copy.deepcopy(self.bundle)

    def test_the_real_bundle_passes(self):
        report = validator.validate(self.bundle, self.schema)
        self.assertEqual(report.errors, [])

    def test_known_warnings_are_reported_not_hidden(self):
        report = validator.validate(self.bundle, self.schema)
        text = " ".join(report.warnings)
        self.assertIn("abd.bladder_pressure", text)
        self.assertIn("abd.abdominal_cramp", text)

    def test_unknown_symptom_in_evidence_is_an_error(self):
        bundle = self.broken()
        bundle["questions"][0]["options"][0]["evidence"][0]["symptom_id"] = "abd.does_not_exist"
        self.assertTrue(any("unknown symptom" in error for error in validator.validate(bundle, self.schema).errors))

    def test_duplicate_option_ids_are_an_error(self):
        bundle = self.broken()
        options = bundle["questions"][0]["options"]
        options[1]["id"] = options[0]["id"]
        self.assertTrue(any("duplicate option" in error for error in validator.validate(bundle, self.schema).errors))

    def test_profile_without_a_typical_row_is_an_error(self):
        bundle = self.broken()
        for row in bundle["conditions"][0]["profile"]:
            row["level"] = "possible"
        self.assertTrue(any("no typical" in error for error in validator.validate(bundle, self.schema).errors))

    def test_unknown_source_is_an_error(self):
        bundle = self.broken()
        bundle["conditions"][0]["profile"][0]["source_refs"] = ["src_nope"]
        self.assertTrue(any("unknown source" in error for error in validator.validate(bundle, self.schema).errors))

    def test_missing_review_status_is_an_error(self):
        bundle = self.broken()
        del bundle["conditions"][0]["review_status"]
        self.assertTrue(any("review_status" in error for error in validator.validate(bundle, self.schema).errors))

    def test_probability_or_score_fields_are_forbidden(self):
        for key in ("probability", "score", "likelihood", "confidence"):
            bundle = self.broken()
            bundle["conditions"][0][key] = 0.7
            errors = validator.validate(bundle, self.schema).errors
            self.assertTrue(any("forbidden" in error for error in errors), key)

    def test_override_on_a_missing_condition_is_an_error(self):
        bundle = self.broken()
        bundle["overrides"][0]["condition_id"] = "ghost"
        self.assertTrue(any("unknown condition" in error for error in validator.validate(bundle, self.schema).errors))

    def test_clause_naming_an_unknown_question_is_an_error(self):
        bundle = self.broken()
        bundle["overrides"][0]["when"][0]["any_of"][0]["question_id"] = "q_ghost"
        self.assertTrue(any("unknown question" in error for error in validator.validate(bundle, self.schema).errors))

    def test_wrong_schema_version_is_an_error(self):
        bundle = self.broken()
        bundle["schema_version"] = "3.0.0"
        self.assertTrue(any("schema_version" in error for error in validator.validate(bundle, self.schema).errors))

    def test_symptom_id_without_cluster_prefix_is_an_error(self):
        bundle = self.broken()
        bundle["symptoms"][0]["id"] = "pain_rlq"
        self.assertTrue(any("abd.pain_rlq" in error or "cluster" in error for error in validator.validate(bundle, self.schema).errors))

    def test_schema_and_validator_agree_on_required_fields(self):
        requirements = validator.schema_requirements(self.schema)
        for key in ("bundle_id", "bundle_version", "symptoms", "questions", "conditions", "overrides", "safety_rules", "coverage_rules"):
            self.assertIn(key, requirements["$root"])
        for key in ("symptom", "evidence", "option", "question", "profileRow", "condition", "override", "safetyRule", "coverageRule", "source"):
            self.assertIn(key, requirements, f"$defs.{key} must declare required fields")


class JsonSchemaTest(unittest.TestCase):
    """Cross-check the hand-written validator with a real JSON Schema engine."""

    @classmethod
    def setUpClass(cls):
        try:
            import jsonschema  # noqa: F401
        except ImportError as error:  # pragma: no cover - environment dependent
            raise unittest.SkipTest(f"jsonschema not installed ({error}); run `pip install jsonschema` to cross-check")

    def test_real_bundle_validates_against_the_schema_file(self):
        import jsonschema

        schema = validator.load(validator.SCHEMA_PATH)
        bundle = json.loads((REPO / validator.DEFAULT_BUNDLE).read_text(encoding="utf-8"))
        jsonschema.Draft202012Validator(schema).validate(bundle)

    def test_schema_rejects_a_mutated_bundle(self):
        import jsonschema

        schema = validator.load(validator.SCHEMA_PATH)
        bundle = json.loads((REPO / validator.DEFAULT_BUNDLE).read_text(encoding="utf-8"))
        bundle["conditions"][0]["profile"][0]["level"] = "certain"
        with self.assertRaises(jsonschema.ValidationError):
            jsonschema.Draft202012Validator(schema).validate(bundle)


if __name__ == "__main__":
    unittest.main()
