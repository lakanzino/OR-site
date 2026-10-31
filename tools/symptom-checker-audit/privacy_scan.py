#!/usr/bin/env python3
"""Static privacy and hygiene scan of the files that actually ship.

The tool looks at the built plugin package (`releases/hamyar-symptom-checker-*.zip`)
when it exists, otherwise at the same file list in the repository. It answers one
question only: can the shipped code store, transmit or hide what the person
answered?

Checks:
  storage     localStorage, sessionStorage, indexedDB, cookies, cache storage
  network     XHR, WebSocket, EventSource, sendBeacon, fetch() to an unknown URL,
              external <script>/<img>/@import, remote fonts
  tracking    analytics/heatmap/error-reporting SDKs
  injection   innerHTML, outerHTML, document.write, eval, new Function
  forms       a form that posts somewhere

Exit code 0 = clean, 1 = violation found, 2 = usage error.

    python3 tools/symptom-checker-audit/privacy_scan.py
    python3 tools/symptom-checker-audit/privacy_scan.py --json
    python3 tools/symptom-checker-audit/privacy_scan.py --zip releases/hamyar-symptom-checker-0.1.0.zip
"""

from __future__ import annotations

import argparse
import json
import re
import sys
import zipfile
from dataclasses import dataclass, field
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
RELEASES = ROOT / "releases"
PACKAGE_PREFIX = "hamyar-symptom-checker-"

# The files that ship to the site. Tests, build tools and docs are deliberately
# excluded: they may talk about storage and network in order to test that the
# shipped code never uses them.
SHIPPED_FILES = [
    "symptom-checker/ui/app.js",
    "symptom-checker/ui/view-model.js",
    "symptom-checker/ui/question-help.js",
    "symptom-checker/ui/styles.css",
    "symptom-checker/engine/src/engine.js",
    "symptom-checker/engine/src/evidence.js",
    "symptom-checker/engine/src/validate-bundle.js",
    "symptom-checker/engine/src/errors.js",
    "plugins/hamyar-symptom-checker/hamyar-symptom-checker.php",
    "plugins/hamyar-symptom-checker/includes/admin.php",
    "plugins/hamyar-symptom-checker/includes/mapping.php",
    "plugins/hamyar-symptom-checker/includes/page.php",
    "plugins/hamyar-symptom-checker/templates/checker-page.php",
]

# `fetch` is allowed only in the UI controller and only through these two
# constants, whose default values are relative paths inside the site.
ALLOWED_FETCH_TARGETS = {
    "'../../docs/symptom-checker/phase-1/knowledge-bundle.v1.json'",
    "'../engine/data/evidence-map.v1.json'",
    "BUNDLE_URL",
    "EVIDENCE_URL",
}
# A relative constant is only safe while its default is relative. The scanner
# therefore refuses any absolute URL literal at all: the plugin builds its own
# asset URLs with WordPress helpers, so no literal is needed anywhere.
URL_LITERAL = re.compile(r"https?://")

# XML namespace identifiers are plain strings, never fetched. Only these exact
# URIs are exempt from the "no absolute URL" rule; anything else still fails.
NAMESPACE_URIS = ("http://www.w3.org/2000/svg", "http://www.w3.org/1999/xlink")

# A wp-admin screen posts back to itself; that is the WordPress pattern, not a
# leak. The exemption is one file, it covers only `method="post"` (never an
# external `action=`), and every other rule still applies to that file.
POSTING_FORMS_ALLOWED = ("includes/admin.php",)


def forms_may_post(name: str) -> bool:
    return any(name.endswith(allowed) for allowed in POSTING_FORMS_ALLOWED)


RULES = {
    "storage": [
        r"\blocalStorage\b",
        r"\bsessionStorage\b",
        r"\bindexedDB\b",
        r"document\s*\.\s*cookie",
        r"\bcaches\s*\.\s*open\b",
        r"\bnavigator\s*\.\s*storage\b",
    ],
    "network": [
        r"\bXMLHttpRequest\b",
        r"\bWebSocket\b",
        r"\bEventSource\b",
        r"sendBeacon",
        r"\bjQuery\s*\.\s*(?:ajax|get|post)\b",
        r"\bnavigator\s*\.\s*serviceWorker\b",
        r"wp_remote_(?:get|post|request)",
        r"\bcurl_(?:init|exec)\b",
        r"fsockopen",
    ],
    "tracking": [
        r"googletagmanager",
        r"google-analytics",
        r"\bgtag\s*\(",
        r"\bdataLayer\b",
        r"hotjar",
        r"clarity\.ms",
        r"facebook\.net",
        r"yandex",
        r"plausible",
        r"matomo",
        r"segment\.(?:com|io)",
        r"@sentry",
        r"amplitude",
    ],
    "injection": [
        r"\.innerHTML\b",
        r"\.outerHTML\b",
        r"document\s*\.\s*write\s*\(",
        r"\beval\s*\(",
        r"new\s+Function\s*\(",
    ],
    "remote_assets": [
        r"<script[^>]+src\s*=\s*['\"]https?://",
        r"<img[^>]+src\s*=\s*['\"]https?://",
        r"<link[^>]+href\s*=\s*['\"]https?://",
        r"@import\s+(?:url\()?['\"]?https?://",
        r"url\(\s*['\"]?https?://",
    ],
    "forms": [
        r"<form[^>]+action\s*=",
        r"method\s*=\s*['\"]post",
    ],
}

FETCH_CALL = re.compile(r"\bfetch\s*\(\s*([^,)]+)")
COMMENT_LINE = re.compile(r"^\s*(?://|#|\*|/\*)")


@dataclass
class Finding:
    file: str
    line: int
    rule: str
    snippet: str

    def as_dict(self) -> dict:
        return {"file": self.file, "line": self.line, "rule": self.rule, "snippet": self.snippet}


@dataclass
class Report:
    scanned: list[str] = field(default_factory=list)
    findings: list[Finding] = field(default_factory=list)
    fetch_calls: list[dict] = field(default_factory=list)

    @property
    def clean(self) -> bool:
        return not self.findings

    def as_dict(self) -> dict:
        return {
            "scanned": self.scanned,
            "clean": self.clean,
            "fetch_calls": self.fetch_calls,
            "findings": [item.as_dict() for item in self.findings],
        }


def _strip_comments(text: str) -> str:
    """Drop comment-only lines so that documentation about storage is not a hit."""
    return "\n".join("" if COMMENT_LINE.match(line) else line for line in text.splitlines())


def _is_namespace_uri(code: str, start: int) -> bool:
    """True only when a namespace URI stands alone as a whole string.

    A look-alike such as ``.../svg.evil.example/x`` or ``.../svg?collect=1``
    keeps the finding: the exemption must not swallow real remote URLs.
    """
    for uri in NAMESPACE_URIS:
        if not code.startswith(uri, start):
            continue
        following = code[start + len(uri): start + len(uri) + 1]
        if following in ("", "'", '"', "`"):
            return True
    return False


def scan_text(name: str, text: str) -> list[Finding]:
    findings: list[Finding] = []
    code = _strip_comments(text)

    for rule, patterns in RULES.items():
        if rule == "forms" and forms_may_post(name):
            patterns = [item for item in patterns if item != r"method\s*=\s*['\"]post"]
        for pattern in patterns:
            for match in re.finditer(pattern, code):
                line = code[: match.start()].count("\n") + 1
                snippet = code.splitlines()[line - 1].strip()[:160] if code.splitlines() else ""
                findings.append(Finding(name, line, rule, snippet))

    for match in URL_LITERAL.finditer(code):
        if _is_namespace_uri(code, match.start()):
            continue
        line = code[: match.start()].count("\n") + 1
        snippet = code.splitlines()[line - 1].strip()[:160] if code.splitlines() else ""
        findings.append(Finding(name, line, "remote_literal", snippet))

    # fetch() is allowed only for the two local bundle URLs.
    for match in FETCH_CALL.finditer(code):
        target = match.group(1).strip()
        line = code[: match.start()].count("\n") + 1
        allowed = target in ALLOWED_FETCH_TARGETS and "app.js" in name
        if not allowed:
            findings.append(Finding(name, line, "network", f"fetch({target})"))
    return findings


def scan_repository() -> Report:
    report = Report()
    for relative in SHIPPED_FILES:
        path = ROOT / relative
        if not path.is_file():
            report.findings.append(Finding(relative, 0, "missing", "file does not exist"))
            continue
        report.scanned.append(relative)
        text = path.read_text(encoding="utf-8")
        report.findings.extend(scan_text(relative, text))
        for match in FETCH_CALL.finditer(_strip_comments(text)):
            report.fetch_calls.append(
                {"file": relative, "target": match.group(1).strip()}
            )
    return report


def default_zip() -> Path | None:
    candidates = sorted(RELEASES.glob(f"{PACKAGE_PREFIX}*.zip"))
    return candidates[-1] if candidates else None


def scan_zip(path: Path) -> Report:
    report = Report()
    with zipfile.ZipFile(path) as archive:
        for info in sorted(archive.infolist(), key=lambda item: item.filename):
            if info.is_dir() or not info.filename.endswith((".js", ".css", ".php", ".html")):
                continue
            name = info.filename
            if "/assets/data/" in name or "/assets/engine/data/" in name:
                continue  # JSON data has no code to scan.
            text = archive.read(info).decode("utf-8", errors="replace")
            report.scanned.append(name)
            report.findings.extend(scan_text(name, text))
            for match in FETCH_CALL.finditer(_strip_comments(text)):
                report.fetch_calls.append({"file": name, "target": match.group(1).strip()})
    return report


def main(argv: list[str]) -> int:
    parser = argparse.ArgumentParser(description="Privacy and hygiene scan of the shipped symptom-checker files.")
    parser.add_argument("--zip", type=Path, help="scan this package instead of the repository files")
    parser.add_argument("--json", action="store_true", help="print the report as JSON")
    parser.add_argument("--list", action="store_true", help="print the scanned files and exit")
    args = parser.parse_args(argv)

    if args.zip:
        if not args.zip.is_file():
            print(f"no such package: {args.zip}", file=sys.stderr)
            return 2
        report = scan_zip(args.zip)
        source = str(args.zip)
    else:
        report = scan_repository()
        source = "repository files"

    if args.list:
        for name in report.scanned:
            print(name)
        return 0

    if args.json:
        print(json.dumps({"source": source, **report.as_dict()}, ensure_ascii=False, indent=2))
    else:
        print(f"scanned {len(report.scanned)} files from {source}")
        print(f"fetch() call sites: {len(report.fetch_calls)}")
        for call in report.fetch_calls:
            print(f"  {call['file']}: {call['target']}")
        if report.clean:
            print("OK — no storage, no tracking, no unknown network target, no HTML injection found")
        else:
            for finding in report.findings:
                print(f"FAIL {finding.file}:{finding.line} [{finding.rule}] {finding.snippet}", file=sys.stderr)

    return 0 if report.clean else 1


if __name__ == "__main__":
    raise SystemExit(main(sys.argv[1:]))
