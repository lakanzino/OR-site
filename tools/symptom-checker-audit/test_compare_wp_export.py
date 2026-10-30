import unittest

from compare_inventory import InventoryError
from compare_wp_export import compare_export


def article(slug, status='publish', post_type='disease', id='1', title='الف'):
    return {'id': id, 'slug': slug, 'post_type': post_type, 'status': status, 'title': title, 'url': 'https://example.test/' + slug}


class CompareExportTest(unittest.TestCase):
    def test_matches_are_only_candidates(self):
        output = compare_export([article('a'), article('b', id='2')], [
            {'diseaseId': 'a', 'profile': {'nameFa': 'الف'}},
            {'diseaseId': 'c', 'profile': {'nameFa': 'ج'}},
        ])
        self.assertEqual(output['shared_slug_and_profile_id_count'], 1)
        self.assertEqual(output['wordpress_slug_only'], ['b'])
        self.assertEqual(output['profile_id_only'], ['c'])
        self.assertIn('candidates', output['note'])

    def test_drafts_private_and_other_types_are_not_reported(self):
        rows = [article('a', status='draft'), article('b', status='private'), article('c', post_type='page')]
        self.assertEqual(compare_export(rows, [])['published_diseases'], 0)

    def test_duplicate_slug_or_id_is_rejected(self):
        for rows in ([article('a'), article('a', id='2')], [article('a'), article('b')]):
            with self.subTest(rows=rows), self.assertRaises(InventoryError):
                compare_export(rows, [])

    def test_title_mismatch_and_missing_name(self):
        output = compare_export([article('a'), article('b', id='2')], [
            {'diseaseId': 'a', 'profile': {'nameFa': 'ب'}}, {'diseaseId': 'b', 'profile': {}},
        ])
        self.assertEqual(output['different_titles'][0]['slug'], 'a')
        self.assertEqual(output['missing_profile_names'], ['b'])

    def test_missing_published_field_rejected(self):
        row = article('a')
        row['id'] = ''
        with self.assertRaises(InventoryError):
            compare_export([row], [])


if __name__ == '__main__':
    unittest.main()
