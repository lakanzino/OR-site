#!/usr/bin/env python3
"""Tests for the name-collision gate.

A gate that cannot fail is worthless: 0.1.0 shipped with a theme function
collision the build never noticed. These tests plant the failure on purpose and
expect the gate to shout.
"""
import importlib.util
import sys
import tempfile
import unittest
from pathlib import Path
from unittest import mock

HERE = Path(__file__).resolve().parent
MODULE_PATH = HERE / "check-name-collisions.py"

spec = importlib.util.spec_from_file_location("check_name_collisions", MODULE_PATH)
checker = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = checker  # dataclasses needs the module registered
spec.loader.exec_module(checker)


class DeclaredNamesTest(unittest.TestCase):
    def test_guarded_fallback_is_not_a_duplicate(self):
        text = """
if ( ! function_exists( 'hjsc_activate' ) ) {
    function hjsc_activate() {}
}
function hjsc_page() {}
"""
        self.assertEqual(checker.declared_functions(text), ["hjsc_page"])

    def test_constants_from_define_and_const(self):
        text = """
define( 'HJ_SC_VERSION', '0.1.4' );
define("HJSC_PATH", __DIR__);
class Thing { const HJSC_MODE = 'x'; }
"""
        self.assertEqual(
            checker.declared_constants(text), ["HJ_SC_VERSION", "HJSC_PATH", "HJSC_MODE"]
        )


class CollisionTest(unittest.TestCase):
    def test_function_collision_ignores_case_like_php(self):
        mine = checker.symbols_from_text("function hj_sc_enqueue_assets() {}", "plugin/a.php")
        theirs = checker.symbols_from_text("function HJ_SC_Enqueue_Assets() {}", "theme/b.php")
        problems = checker.collisions(mine, theirs)
        self.assertEqual(len(problems), 1)
        self.assertIn("function", problems[0])
        self.assertIn("plugin/a.php", problems[0])

    def test_constant_collision_is_case_sensitive(self):
        mine = checker.symbols_from_text("define( 'HJSC_MODE', 1 );", "plugin/a.php")
        same = checker.symbols_from_text("define( 'HJSC_MODE', 2 );", "theme/b.php")
        other = checker.symbols_from_text("define( 'HJSC_mode', 2 );", "theme/c.php")
        self.assertEqual(len(checker.collisions(mine, same)), 1)
        self.assertEqual(checker.collisions(mine, other), [])

    def test_disjoint_names_pass(self):
        mine = checker.symbols_from_text("function hjsc_page() {}", "plugin/a.php")
        theirs = checker.symbols_from_text("function hj_other() {}\ndefine('X', 1);", "theme/b.php")
        self.assertEqual(checker.collisions(mine, theirs), [])


class LegacySymbolTest(unittest.TestCase):
    def test_legacy_family_is_reported(self):
        symbols = checker.symbols_from_text(
            "function hj_sc_stats() {}\ndefine( 'HJ_SC_VERSION', '1' );\nfunction fine() {}",
            "theme/a.php",
        )
        found = {name for _, name, _ in checker.legacy_symbols(symbols)}
        self.assertEqual(found, {"hj_sc_stats", "HJ_SC_VERSION"})


class GateTest(unittest.TestCase):
    def test_repository_passes(self):
        self.assertEqual(checker.main([]), 0)

    def test_legacy_theme_file_fails_the_gate(self):
        with tempfile.TemporaryDirectory() as raw:
            theme = Path(raw)
            (theme / "lax-theme-child").mkdir()
            (theme / "lax-theme-child" / "symptom-checker-test.php").write_text(
                "<?php function hj_sc_stats() {}\n", encoding="utf-8"
            )
            with mock.patch.object(checker, "THEMES", theme):
                self.assertEqual(checker.main([]), 1)

    def test_clean_theme_folder_passes(self):
        with tempfile.TemporaryDirectory() as raw:
            theme = Path(raw)
            (theme / "lax-theme-child").mkdir()
            (theme / "lax-theme-child" / "functions.php").write_text(
                "<?php function hj_other() {}\n", encoding="utf-8"
            )
            with mock.patch.object(checker, "THEMES", theme):
                self.assertEqual(checker.main([]), 0)


if __name__ == "__main__":
    unittest.main()
