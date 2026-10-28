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
    def test_hero_copy_and_actions(self):
        home = source("front-page.php")
        self.assertIn("آنچه برای مراقبت‌های قبل و بعد از جراحی باید بدانید", home)
        self.assertIn("ابزار هوشمند ارزیابی علائم بیماری", home)
        self.assertIn("شروع از راهنمای بیمار", home)
        self.assertIn("شناخت اتاق عمل و تیم", home)
        self.assertNotIn("hj-home-hero__chips", home)
        self.assertNotIn("$hj_popular", home)

    def test_glossary_topics_and_partial_update_guards(self):
        home = source("front-page.php")
        functions = source("functions.php")
        self.assertIn("function_exists( 'hj_home_glossary_topics' )", home)
        self.assertIn("function_exists( 'hj_home_specialty_label' )", home)
        self.assertIn("'/inc/homepage-ui.php'", functions)
        self.assertIn("clearstatcache( true, $hj_homepage_ui_path )", functions)
        self.assertIn("is_readable( $hj_homepage_ui_path )", functions)
        self.assertIn("@include_once $hj_homepage_ui_path", functions)
        self.assertIn("hj-glossary-topic-list", home)
        self.assertNotIn("hj_glossary_get_featured_terms", home)

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
        self.assertIn("hj-care-tool__flag", source("front-page.php"))
        self.assertIn("hj-box--", source("inc/medical-boxes.php"))


if __name__ == "__main__":
    unittest.main(verbosity=2)
