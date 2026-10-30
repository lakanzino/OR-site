#!/usr/bin/env python3
"""Compare published disease slugs in a WP CSV export to local profile IDs.

Slug equality is a candidate match, not proof of the site's internal meta ID.
The export may contain non-public content; only aggregate disease results are reported.
"""
import argparse
from collections import Counter
import csv
import json
import sys

from compare_inventory import InventoryError, index_items

REQUIRED = {'id', 'post_type', 'status', 'title', 'slug', 'url'}


def compare_export(rows, profiles):
    indexed = index_items(profiles, 'diseaseId', 'profiles')
    published = [row for row in rows if row['post_type'] == 'disease' and row['status'] == 'publish']
    ids, slugs = Counter(), Counter()
    for row in published:
        for field in ('id', 'slug', 'title', 'url'):
            if not isinstance(row.get(field), str) or not row[field].strip():
                raise InventoryError(f'published disease has missing {field}')
        ids[row['id']] += 1
        slugs[row['slug']] += 1
    if any(n > 1 for n in ids.values()) or any(n > 1 for n in slugs.values()):
        raise InventoryError('published diseases contain duplicate ID or slug')
    shared = sorted(slugs.keys() & indexed.keys())
    profiles_only = sorted(indexed.keys() - slugs.keys())
    slug_only = sorted(slugs.keys() - indexed.keys())
    names = {row['slug']: row['title'] for row in published}
    differences, missing_names = [], []
    for identifier in shared:
        profile = indexed[identifier].get('profile')
        profile_title = profile.get('nameFa') if isinstance(profile, dict) else None
        if not isinstance(profile_title, str) or not profile_title.strip():
            missing_names.append(identifier)
        elif profile_title != names[identifier]:
            differences.append({'slug': identifier, 'wordpress_title': names[identifier], 'profile_title': profile_title})
    return {
        'published_diseases': len(published),
        'profiles_count': len(indexed),
        'shared_slug_and_profile_id_count': len(shared),
        'wordpress_slug_only': slug_only,
        'profile_id_only': profiles_only,
        'different_titles': differences,
        'missing_profile_names': missing_names,
        'note': 'Slug matches are candidates only: seed metadata and clinical equivalence were not verified.',
    }


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--export', required=True)
    parser.add_argument('--profiles', required=True)
    args = parser.parse_args()
    try:
        with open(args.export, encoding='utf-8-sig', newline='') as source:
            reader = csv.DictReader(source)
            if not reader.fieldnames or not REQUIRED.issubset(reader.fieldnames):
                raise InventoryError('CSV is missing required columns')
            rows = list(reader)
        with open(args.profiles, encoding='utf-8') as source:
            profiles = json.load(source)
        output = compare_export(rows, profiles)
    except (OSError, ValueError, csv.Error) as exc:
        print(f'export audit error: {exc}', file=sys.stderr)
        return 2
    print(json.dumps(output, ensure_ascii=False, indent=2))
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
