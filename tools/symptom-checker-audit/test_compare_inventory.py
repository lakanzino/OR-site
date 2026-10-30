import unittest

from compare_inventory import InventoryError, compare


class CompareInventoryTest(unittest.TestCase):
    def test_exact_matches_and_unmatched_ids(self):
        checker = {'diseases': [{'id': 'a', 'nameFa': 'الف'}, {'id': 'b', 'nameFa': 'ب'}]}
        profiles = [{'diseaseId': 'a', 'profile': {'nameFa': 'الف'}}, {'diseaseId': 'c', 'profile': {'nameFa': 'ج'}}]
        result = compare(checker, profiles)
        self.assertEqual(result['shared_ids'], ['a'])
        self.assertEqual(result['checker_only'], ['b'])
        self.assertEqual(result['profiles_only'], ['c'])
        self.assertEqual(result['different_names'], [])

    def test_different_names_are_reported_not_matched_by_name(self):
        result = compare({'diseases': [{'id': 'a', 'nameFa': 'الف'}]},
                         [{'diseaseId': 'a', 'profile': {'nameFa': 'ب'}}])
        self.assertEqual(result['different_names'][0]['id'], 'a')

    def test_missing_name_is_not_a_match(self):
        result = compare({'diseases': [{'id': 'a'}]}, [{'diseaseId': 'a', 'profile': {}}])
        self.assertEqual(result['missing_names'], ['a'])
        self.assertEqual(result['different_names'], [])

    def test_duplicate_ids_are_rejected(self):
        with self.assertRaisesRegex(InventoryError, 'duplicate'):
            compare({'diseases': [{'id': 'a'}, {'id': 'a'}]}, [])
        with self.assertRaisesRegex(InventoryError, 'duplicate'):
            compare({'diseases': []}, [{'diseaseId': 'a'}, {'diseaseId': 'a'}])

    def test_missing_and_whitespace_ids_are_rejected(self):
        for bad in [None, '', ' ', ' a']:
            with self.subTest(bad=bad), self.assertRaises(InventoryError):
                compare({'diseases': [{'id': bad}]}, [])

    def test_invalid_shapes_are_rejected(self):
        for checker, profiles in [(None, []), ({}, []), ({'diseases': [None]}, []), ({'diseases': []}, {})]:
            with self.subTest(checker=checker, profiles=profiles), self.assertRaises(InventoryError):
                compare(checker, profiles)


if __name__ == '__main__':
    unittest.main()
