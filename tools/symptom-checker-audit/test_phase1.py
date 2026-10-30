#!/usr/bin/env python3
"""آزمون‌های قرارداد داده و سناریوهای فاز ۱ دستیار نشانه‌ها.

اجرا از ریشهٔ مخزن:
    python3 tools/symptom-checker-audit/test_phase1.py

این آزمون‌ها فقط ساختار، یکپارچگی ارجاع و منطق مسیر/هشدار/پوشش را می‌سنجند.
هیچ ادعایی دربارهٔ دقت بالینی یا ایمنی واقعی نمی‌کنند.
"""

import copy
import unittest

from validate_phase1 import (
    BUNDLE_PATH,
    SCENARIOS_PATH,
    evaluate,
    load_json,
    validate_bundle,
    validate_scenarios,
)


class Phase1ContractTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.bundle = load_json(BUNDLE_PATH)
        cls.scenarios = load_json(SCENARIOS_PATH)

    def mutated_bundle(self, mutate):
        bundle = copy.deepcopy(self.bundle)
        mutate(bundle)
        return bundle

    def mutated_scenarios(self, mutate):
        scenarios = copy.deepcopy(self.scenarios)
        mutate(scenarios)
        return scenarios

    # --- مسیر خوشحال -------------------------------------------------
    def test_bundle_is_valid(self):
        self.assertEqual(validate_bundle(self.bundle), [])

    def test_scenarios_are_valid(self):
        self.assertEqual(validate_scenarios(self.bundle, self.scenarios), [])

    def test_every_condition_can_be_opened_by_a_path(self):
        for condition in self.bundle["conditions"]:
            paths = [p for p in self.bundle["paths"] if p["condition_id"] == condition["id"]]
            self.assertTrue(paths, f"{condition['id']} has no path")

    def test_every_question_is_reachable_or_documented(self):
        """هر سؤال باید یا همیشه پرسیده شود یا شرط نمایش مستند داشته باشد."""
        for question in self.bundle["questions"]:
            visibility = question["visibility"]
            self.assertTrue(
                visibility.get("always") or visibility.get("any_of"),
                f"{question['id']} has no visibility rule",
            )

    # --- معنای پاسخ‌ها ------------------------------------------------
    def test_unknown_is_never_treated_as_no(self):
        answers = {
            "q_pain_present": "yes",
            "q_pain_location": "unknown",
            "q_pain_migrated_rlq": "unknown",
            "q_urinary_symptoms": ["unknown"],
            "q_flank_radiation": "unknown",
            "q_fever_chills": "unknown",
            "q_diarrhea_vomiting": ["unknown"],
            "q_pregnancy_possible": "no",
        }
        result = evaluate(self.bundle, answers)
        self.assertEqual(result["branches_open"], [])
        self.assertEqual(result["coverage"], "insufficient")

    def test_unanswered_question_does_not_satisfy_a_rule(self):
        answers = {
            "q_pain_present": "yes",
            "q_pain_location": "right_lower",
            "q_pregnancy_possible": "no",
        }
        result = evaluate(self.bundle, answers)
        self.assertIn("app", result["branches_open"])
        self.assertNotIn("kidney_infection", result["branches_open"])

    def test_parallel_branches_stay_open(self):
        answers = {
            "q_pain_present": "yes",
            "q_pain_location": "right_lower",
            "q_diarrhea_vomiting": ["diarrhea"],
            "q_urinary_symptoms": ["dysuria"],
            "q_pregnancy_possible": "no",
        }
        result = evaluate(self.bundle, answers)
        self.assertEqual(
            result["branches_open"], ["app", "gastroenteritis", "urinary_tract"]
        )

    def test_pregnancy_out_of_scope_wins_over_branches(self):
        answers = {
            "q_pain_present": "yes",
            "q_pain_location": "right_lower",
            "q_pregnancy_possible": "unsure",
            "q_urinary_symptoms": ["none"],
            "q_diarrhea_vomiting": ["none"],
        }
        result = evaluate(self.bundle, answers)
        self.assertEqual(result["coverage"], "out_of_scope")
        self.assertIn("sf_pregnancy_out_of_scope", result["safety_flags"])

    def test_safety_flags_are_independent_from_branches(self):
        answers = {
            "q_pain_present": "yes",
            "q_pain_location": "other_abdomen",
            "q_diarrhea_vomiting": ["diarrhea"],
            "q_blood_in_stool": "yes",
            "q_pregnancy_possible": "no",
        }
        result = evaluate(self.bundle, answers)
        self.assertIn("sf_blood_in_stool", result["safety_flags"])
        self.assertIn("gastroenteritis", result["branches_open"])

    # --- نگهبان‌های اعتبارسنج ---------------------------------------
    def test_duplicate_question_id_is_rejected(self):
        def mutate(bundle):
            bundle["questions"][1]["id"] = bundle["questions"][0]["id"]

        errors = validate_bundle(self.mutated_bundle(mutate))
        self.assertTrue(any("duplicate question id" in e for e in errors), errors)

    def test_path_without_source_is_rejected(self):
        def mutate(bundle):
            bundle["paths"][0]["source_refs"] = []

        errors = validate_bundle(self.mutated_bundle(mutate))
        self.assertTrue(errors)

    def test_path_with_unknown_source_is_rejected(self):
        def mutate(bundle):
            bundle["paths"][0]["source_refs"] = ["src_missing"]

        errors = validate_bundle(self.mutated_bundle(mutate))
        self.assertTrue(any("unknown source src_missing" in e for e in errors), errors)

    def test_boolean3_without_unknown_state_is_rejected(self):
        def mutate(bundle):
            for question in bundle["questions"]:
                if question["id"] == "q_fever_chills":
                    question["options"] = [o for o in question["options"] if o["id"] != "unknown"]

        errors = validate_bundle(self.mutated_bundle(mutate))
        self.assertTrue(any("yes/no/unknown" in e for e in errors), errors)

    def test_missing_profile_is_rejected(self):
        def mutate(bundle):
            bundle["conditions"][0]["profile_ref"] = (
                "themes/lax-theme-child/data/disease-profiles.json#ghost_profile"
            )

        errors = validate_bundle(self.mutated_bundle(mutate))
        self.assertTrue(any("missing profile" in e for e in errors), errors)

    def test_option_reference_in_path_must_exist(self):
        def mutate(bundle):
            bundle["paths"][0]["when"] = [
                {"question_id": "q_pain_location", "answers": ["left_elbow"]}
            ]

        errors = validate_bundle(self.mutated_bundle(mutate))
        self.assertTrue(any("unknown option" in e for e in errors), errors)

    def test_scenario_with_exclusive_conflict_is_rejected(self):
        def mutate(scenarios):
            scenarios["scenarios"][0]["answers"]["q_urinary_symptoms"] = ["none", "dysuria"]

        errors = validate_scenarios(self.bundle, self.mutated_scenarios(mutate))
        self.assertTrue(any("exclusive option" in e for e in errors), errors)

    def test_scenario_answering_hidden_question_is_rejected(self):
        def mutate(scenarios):
            scenarios["scenarios"][0]["answers"]["q_pain_severity"] = 5
            scenarios["scenarios"][0]["answers"]["q_pain_present"] = "no"

        errors = validate_scenarios(self.bundle, self.mutated_scenarios(mutate))
        self.assertTrue(any("would not be shown" in e for e in errors), errors)

    def test_scale_out_of_range_is_rejected(self):
        def mutate(scenarios):
            scenarios["scenarios"][0]["answers"]["q_pain_severity"] = 42

        errors = validate_scenarios(self.bundle, self.mutated_scenarios(mutate))
        self.assertTrue(any("out-of-range" in e for e in errors), errors)

    def test_cards_are_not_allowed_for_insufficient_coverage(self):
        def mutate(scenarios):
            scenarios["scenarios"][7]["expected"]["result_cards_allowed"] = True

        errors = validate_scenarios(self.bundle, self.mutated_scenarios(mutate))
        self.assertTrue(any("allows result cards" in e for e in errors), errors)

    def test_wrong_expected_branch_is_caught(self):
        def mutate(scenarios):
            scenarios["scenarios"][0]["expected"]["branches_open"] = ["app", "kidney_stone"]

        errors = validate_scenarios(self.bundle, self.mutated_scenarios(mutate))
        self.assertTrue(any("branches differ" in e for e in errors), errors)

    def test_forbidden_branch_leak_is_caught(self):
        def mutate(scenarios):
            scenarios["scenarios"][2]["expected"]["branches_must_not_open"] = ["kidney_stone"]

        errors = validate_scenarios(self.bundle, self.mutated_scenarios(mutate))
        self.assertTrue(any("forbidden branches opened" in e for e in errors), errors)


if __name__ == "__main__":
    unittest.main(verbosity=2)
