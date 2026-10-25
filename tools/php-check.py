#!/usr/bin/env python3
"""Bracket / string sanity checker for PHP files without a PHP binary.

It is not a compiler. It catches the mistakes that actually happen when
editing PHP by hand: an unbalanced brace, an unterminated string, a stray
`?>` that eats the rest of the file, a file that ends inside PHP code.

The scanner understands PHP strings, line comments, block comments and the
HTML/PHP boundary, so `?>` inside a regex still counts as PHP.

Usage:
    python3 tools/php-check.py themes/lax-theme-child/inc/*.php
"""

from __future__ import annotations

import sys
from pathlib import Path

OPEN = {"(": ")", "[": "]", "{": "}"}
CLOSE = {v: k for k, v in OPEN.items()}


def scan_php(src: str, start: int, line: int, stack: list):
    """Scan one PHP block, carrying the bracket stack across blocks.

    Braces legitimately span `?> ... <?php` in template files, so the stack
    lives for the whole file, not for a single block.

    Returns (problems, end_index, end_line, closed_by_tag).
    """
    problems = []
    i = start
    n = len(src)

    while i < n:
        ch = src[i]

        if ch == "\n":
            line += 1
            i += 1
            continue

        # Closing tag ends PHP mode (outside strings/comments).
        if src.startswith("?>", i):
            return problems, i + 2, line, True

        # Comments.
        if src.startswith("//", i) or (ch == "#" and not src.startswith("#[", i)):
            j = i
            while j < n and src[j] != "\n":
                if src.startswith("?>", j):
                    break
                j += 1
            i = j
            continue

        if src.startswith("/*", i):
            j = src.find("*/", i + 2)
            if j == -1:
                problems.append((line, "unterminated block comment"))
                return problems, n, line, False
            line += src.count("\n", i, j)
            i = j + 2
            continue

        # Strings (single, double, backtick). All may span lines in PHP.
        if ch in "'\"`":
            quote = ch
            j = i + 1
            while j < n:
                if src[j] == "\\":
                    j += 2
                    continue
                if src[j] == quote:
                    break
                j += 1
            if j >= n:
                problems.append((line, "unterminated string literal"))
                return problems, n, line, False
            line += src.count("\n", i, j)
            i = j + 1
            continue

        if ch in OPEN:
            stack.append((ch, line))
            i += 1
            continue

        if ch in CLOSE:
            if not stack:
                problems.append((line, f"extra closing '{ch}'"))
            else:
                opener, oline = stack.pop()
                if OPEN[opener] != ch:
                    problems.append(
                        (line, f"'{ch}' closes '{opener}' opened on line {oline}")
                    )
            i += 1
            continue

        i += 1

    return problems, n, line, False


def check_file(path: Path):
    src = path.read_text(encoding="utf-8")
    problems = []
    stack = []
    i = 0
    line = 1
    n = len(src)
    blocks = 0

    while i < n:
        nxt = src.find("<?", i)
        if nxt == -1:
            break
        line += src.count("\n", i, nxt)
        if src.startswith("<?php", nxt):
            body = nxt + 5
        elif src.startswith("<?=", nxt):
            body = nxt + 3
        else:
            # Short open tag is disabled by WordPress hosts; skip it.
            i = nxt + 2
            continue
        blocks += 1
        found, end, line, closed = scan_php(src, body, line, stack)
        problems.extend(found)
        if closed:
            line += 1
        i = end

    for opener, oline in stack:
        problems.append((oline, f"'{opener}' never closed (end of file)"))

    if blocks == 0:
        problems.append((0, "no PHP block found"))
    return problems


def main(argv):
    files = [Path(a) for a in argv[1:]]
    if not files:
        print("usage: php-check.py FILE...")
        return 2
    bad = 0
    for path in files:
        problems = check_file(path)
        if problems:
            bad += 1
            print(f"FAIL  {path}")
            for line, msg in problems:
                print(f"      line {line}: {msg}")
        else:
            print(f"OK    {path}")
    return 1 if bad else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
