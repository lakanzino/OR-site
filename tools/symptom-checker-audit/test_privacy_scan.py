#!/usr/bin/env python3
"""Tests for the privacy scanner itself.

Run from the repository root:

    python3 -m unittest discover -s tools/symptom-checker-audit -p 'test_*.py' -v

A scanner that never fails is worthless, so every rule is proven with a planted
violation before the clean result on the real package is trusted.
"""

from __future__ import annotations

import io
import json
import sys
import tempfile
import unittest
import zipfile
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE))

import privacy_scan as scan  # noqa: E402


class ScannerRuleTests(unittest.TestCase):
    def assertRule(self, text: str, rule: str, name: str = "symptom-checker/ui/app.js") -> None:
        findings = scan.scan_text(name, text)
        self.assertTrue(
            any(item.rule == rule for item in findings),
            f"expected a {rule} finding, got {[item.rule for item in findings]}",
        )

    def test_storage_is_detected(self) -> None:
        for text in (
            "localStorage.setItem('answers', payload);",
            "sessionStorage.setItem('x', '1');",
            "document.cookie = 'a=b';",
            "const open = indexedDB.open('db');",
        ):
            self.assertRule(text, "storage")

    def test_tracking_is_detected(self) -> None:
        for text in (
            "gtag('event', 'start');",
            "window.dataLayer.push({});",
            "https://www.googletagmanager.com/gtag/js",
            "hotjar.identify('1');",
            "clarity.ms/tag/x",
        ):
            self.assertRule(text, "tracking")

    def test_network_is_detected(self) -> None:
        for text in (
            "const xhr = new XMLHttpRequest();",
            "navigator.sendBeacon('/collect', body);",
            "const socket = new WebSocket('wss://example.org');",
            "wp_remote_post($url, $args);",
            "navigator.serviceWorker.register('/sw.js');",
        ):
            self.assertRule(text, "network")

    def test_absolute_url_is_detected(self) -> None:
        self.assertRule("const url = 'https://example.org/answers';", "remote_literal")
        self.assertRule("<script src=\"https://cdn.example.org/x.js\"></script>", "remote_assets")

    def test_xml_namespace_is_not_a_remote_url(self) -> None:
        # The SVG namespace is an identifier string, not a request.
        findings = scan.scan_text(
            "symptom-checker/ui/app.js",
            "const SVG_NS = 'http://www.w3.org/2000/svg';\nsvg.setAttribute('xmlns', SVG_NS);",
        )
        self.assertFalse([item for item in findings if item.rule == "remote_literal"], findings)
        # The exemption is exact: a look-alike host is still a finding.
        self.assertRule("const x = 'http://www.w3.org/2000/svg.evil.example/x';", "remote_literal")
        self.assertRule("const x = 'http://www.w3.org/2000/svg?collect=1';", "remote_literal")

    def test_unknown_fetch_target_is_detected(self) -> None:
        self.assertRule("fetch('https://example.org/answers');", "network")
        self.assertRule("fetch('/api/answers', { method: 'POST' });", "network")

    def test_allowed_fetch_only_in_the_app_controller(self) -> None:
        self.assertRule("fetch(BUNDLE_URL);", "network", name="symptom-checker/ui/view-model.js")
        # The same call in the controller is the one allowed shape.
        findings = scan.scan_text("symptom-checker/ui/app.js", "fetch(BUNDLE_URL, { cache: 'no-store' });")
        self.assertFalse([item for item in findings if item.rule == "network"], findings)

    def test_html_injection_is_detected(self) -> None:
        for text in (
            "node.innerHTML = answer;",
            "node.outerHTML = '<b>x</b>';",
            "document.write('<div>');",
            "eval(input);",
            "new Function('return 1')();",
        ):
            self.assertRule(text, "injection")

    def test_a_submitting_form_is_detected(self) -> None:
        self.assertRule("<form action=\"/collect\" method=\"post\">", "forms")

    def test_comments_are_not_findings(self) -> None:
        text = "// no localStorage, no cookies, no gtag()\nconst a = 1;"
        self.assertEqual(scan.scan_text("symptom-checker/ui/app.js", text), [])


class ScannerReportTests(unittest.TestCase):
    def test_repository_is_clean(self) -> None:
        report = scan.scan_repository()
        self.assertTrue(report.scanned, "nothing was scanned")
        self.assertTrue(
            report.clean,
            "privacy scan findings: " + json.dumps([item.as_dict() for item in report.findings], ensure_ascii=False),
        )

    def test_only_two_local_fetch_calls(self) -> None:
        report = scan.scan_repository()
        targets = [call["target"] for call in report.fetch_calls]
        self.assertEqual(targets, ["BUNDLE_URL", "EVIDENCE_URL"])

    def test_zip_scan_finds_a_planted_leak(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "package.zip"
            with zipfile.ZipFile(path, "w") as archive:
                archive.writestr("pkg/hamyar-symptom-checker.php", "<?php defined( 'ABSPATH' ) || exit;")
                archive.writestr("pkg/assets/ui/app.js", "localStorage.setItem('answers', JSON.stringify(a));")
            report = scan.scan_zip(path)
            self.assertFalse(report.clean)
            self.assertEqual([item.rule for item in report.findings], ["storage"])
            self.assertEqual(report.findings[0].file, "pkg/assets/ui/app.js")

    def test_built_package_is_clean(self) -> None:
        package = scan.default_zip()
        if package is None:
            self.skipTest("no built package in releases/")
        report = scan.scan_zip(package)
        self.assertTrue(report.clean, [item.as_dict() for item in report.findings])

    def test_json_report_shape(self) -> None:
        report = scan.scan_repository()
        payload = json.loads(json.dumps(report.as_dict()))
        self.assertIn("scanned", payload)
        self.assertIn("findings", payload)
        self.assertTrue(payload["clean"])


if __name__ == "__main__":
    unittest.main()
