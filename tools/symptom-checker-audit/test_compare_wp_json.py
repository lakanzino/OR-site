import json
import unittest
from compare_inventory import InventoryError
from compare_wp_json import compare_export


def post(id=1, slug='a', seed='a', status='publish', profile=None):
    if profile is None:
        profile = {'nameFa': 'الف', 'symptoms': 'one\ntwo'}
    return {'id': id, 'post_type': 'disease', 'status': status, 'slug': slug, 'title': 'الف', 'meta': {'_hj_disease_seed_id': seed, '_hj_disease_profile': json.dumps(profile)}}


class CompareJsonTest(unittest.TestCase):
    def setUp(self):
        self.profiles = [{'diseaseId': 'a', 'profile': {'nameFa': 'الف', 'symptoms': 'one\ntwo'}}]

    def test_exact(self):
        result = compare_export({'articles': [post()]}, self.profiles)
        self.assertEqual(result['exact_profiles_count'], 1)
        self.assertEqual(result['newline_to_literal_n_only_count'], 0)

    def test_newline_change_is_detected_not_repaired(self):
        result = compare_export({'articles': [post(profile={'nameFa': 'الف', 'symptoms': 'onentwo'})]}, self.profiles)
        self.assertEqual(result['newline_to_literal_n_only_count'], 1)
        self.assertEqual(result['newline_to_literal_n_field_counts'], {'symptoms': 1})

    def test_other_difference_is_not_called_newline_only(self):
        result = compare_export({'articles': [post(profile={'nameFa': 'الف', 'symptoms': 'other'})]}, self.profiles)
        self.assertEqual(result['other_profile_difference_ids'], ['a'])

    def test_missing_metadata_and_duplicates(self):
        result = compare_export({'articles': [post(), post(id=2), post(id=3, seed=None)]}, self.profiles)
        self.assertEqual(result['duplicate_internal_ids'], ['a'])
        self.assertEqual(result['missing_or_invalid_metadata_post_ids'], [3])

    def test_drafts_ignored(self):
        result = compare_export({'articles': [post(status='draft')]}, self.profiles)
        self.assertEqual(result['published_diseases'], 0)
        self.assertEqual(result['profile_ids_not_in_wordpress'], ['a'])

    def test_slug_title_and_invalid_profile(self):
        a = post(slug='new'); a['title'] = 'متفاوت'
        b = post(id=2, slug='missing', seed='missing'); b['meta']['_hj_disease_profile'] = '{'
        self.profiles.append({'diseaseId': 'missing', 'profile': {}})
        result = compare_export({'articles': [a, b]}, self.profiles)
        self.assertEqual(result['meta_slug_mismatch_post_ids'], [1])
        self.assertEqual(result['different_titles'], ['a'])
        self.assertEqual(result['missing_or_invalid_profiles'], ['missing'])

    def test_invalid_shape_or_post_id(self):
        for export in ({}, {'articles': [post(id=0)]}):
            with self.subTest(export=export), self.assertRaises(InventoryError):
                compare_export(export, self.profiles)


if __name__ == '__main__':
    unittest.main()
