#!/usr/bin/env python3
"""Fast source-level regressions for the homepage presentation update.

Run from the repository root with:
    python3 themes/lax-theme-child/tests/test_homepage_presentation.py
The display-helper behavior is covered separately by homepage-ui.test.php.
"""

import unittest
from pathlib import Path

CHILD = Path(__file__).resolve().parents[1]


def source(path):
    return (CHILD / path).read_text(encoding="utf-8")


class HomepagePresentationTest(unittest.TestCase):
    def test_front_page_renders_editable_page(self):
        home = source("front-page.php")
        self.assertIn("the_content()", home)
        self.assertIn("get_option( 'show_on_front' )", home)
        self.assertNotIn("new WP_Query", home)
        self.assertNotIn("hj-home-hero", home)

    def test_starter_is_editable_and_links_to_existing_flows(self):
        starter = source("homepage/starter.html")
        install = source("inc/static-home.php")
        for label in ("راهنمای بیمار", "ثبت‌نام جراحان", "ورود به پروفایل", "ورود با گوگل", "قبل از عمل", "حین عمل", "بعد از عمل و ریکاوری", "اتاق عمل، تیم و تجهیزات", "دانشنامه بیماری‌ها", "واژه‌نامه", "ویژه دانشجویان اتاق عمل", "پرسش‌های متداول"):
            self.assertIn(label, starter)
        for token in ("GUIDES", "SURGERIES", "DOCTORS", "SIGNUP", "PROFILE", "GOOGLE", "IMAGE", "BEFORE", "DURING", "AFTER", "OR", "DISEASES", "GLOSSARY", "STUDENTS", "CARE", "FAQ", "CONTACT", "SYMPTOM_BUTTON"):
            self.assertIn("{{" + token + "}}", starter)
            self.assertIn("{{" + token + "}}", install)
        self.assertIn("hj_google_start", install)
        self.assertIn("loginSocial", install)
        self.assertIn("current_user_can( 'manage_options' )", install)
        self.assertIn("check_admin_referer", install)
        self.assertIn("wp_insert_post", install)
        self.assertNotIn("wp_update_post", install.split("function hj_static_home_create_page()", 1)[1].split("/** One-time upgrade", 1)[0])
        self.assertIn("hj_static_magazine_block", install)
        self.assertIn("_hj_magazine_section_added", install)
        self.assertIn("'page_on_front'", install)
        self.assertIn("'show_on_front'", install)

    def test_block_markup_balanced(self):
        import re
        starter = source("homepage/starter.html")
        stack = []
        for closing, name in re.findall(r"<!--\s*(/?)wp:([a-z]+)(?:\s+.*?)?\s*-->", starter):
            if closing:
                self.assertTrue(stack)
                self.assertEqual(stack.pop(), name)
            else:
                stack.append(name)
        self.assertFalse(stack)

    def test_full_card_title_and_mobile_single_line(self):
        card = source("template-parts/article-card.php")
        css = source("assets/css/soft-ui.css")
        fitter = source("assets/js/fit-headings.js")
        self.assertIn("esc_attr( wp_strip_all_tags( get_the_title() ) )", card)
        self.assertIn("@media (max-width: 768px)", css)
        self.assertIn("text-overflow: ellipsis", css)
        self.assertIn("white-space: nowrap", css)
        selectors = fitter.split("].join(',');", 1)[0]
        self.assertNotIn("'.orsite-card-article__title'", selectors)
        self.assertNotIn("'.hj-home-hero__title'", selectors)

    def test_only_the_global_footer_warning_is_removed(self):
        self.assertNotIn("hj-emergency--above-footer", source("footer.php"))
        self.assertIn("hj-box--", source("inc/medical-boxes.php"))


if __name__ == "__main__":
    unittest.main(verbosity=2)
