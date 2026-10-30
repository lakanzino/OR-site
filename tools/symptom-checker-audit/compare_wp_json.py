#!/usr/bin/env python3
"""Compare published WordPress export metadata to profile IDs, without exporting article text."""
import argparse
from collections import Counter
import json
import sys
from compare_inventory import InventoryError, index_items


def compare_export(export, profiles):
    if not isinstance(export, dict) or not isinstance(export.get('articles'), list):
        raise InventoryError('export must contain an articles list')
    indexed = index_items(profiles, 'diseaseId', 'profiles')
    articles = [a for a in export['articles'] if isinstance(a, dict) and a.get('post_type') == 'disease' and a.get('status') == 'publish']
    identifiers, posts, slugs = Counter(), Counter(), Counter()
    bad_metadata, missing_profiles, title_diff, slug_diff = [], [], [], []
    exact_profiles, newline_only_profiles, other_profiles = [], [], []
    newline_fields = Counter()
    for article in articles:
        post_id = article.get('id')
        if not isinstance(post_id, int) or isinstance(post_id, bool) or post_id <= 0:
            raise InventoryError('published disease is missing a valid post ID')
        posts[post_id] += 1
        slug = article.get('slug')
        if not isinstance(slug, str) or not slug:
            raise InventoryError(f'post {post_id}: missing slug')
        slugs[slug] += 1
        meta = article.get('meta')
        ident = meta.get('_hj_disease_seed_id') if isinstance(meta, dict) else None
        if not isinstance(ident, str) or not ident.strip() or ident != ident.strip():
            bad_metadata.append(post_id)
            continue
        identifiers[ident] += 1
        if ident != slug:
            slug_diff.append(post_id)
        if ident not in indexed:
            continue
        repo_profile = indexed[ident].get('profile')
        raw_profile = meta.get('_hj_disease_profile')
        try:
            wp_profile = json.loads(raw_profile) if isinstance(raw_profile, str) else raw_profile
        except (TypeError, ValueError):
            wp_profile = None
        if not isinstance(wp_profile, dict) or not isinstance(repo_profile, dict):
            missing_profiles.append(ident)
            continue
        if article.get('title') != repo_profile.get('nameFa'):
            title_diff.append(ident)
        different = [k for k in set(wp_profile) | set(repo_profile) if wp_profile.get(k) != repo_profile.get(k)]
        other = []
        for key in different:
            stored, original = wp_profile.get(key), repo_profile.get(key)
            if isinstance(stored, str) and isinstance(original, str) and '\n' in original and stored == original.replace('\n', 'n'):
                newline_fields[key] += 1
            else:
                other.append(key)
        if not different:
            exact_profiles.append(ident)
        elif not other:
            newline_only_profiles.append(ident)
        else:
            other_profiles.append(ident)
    return {
        'published_diseases': len(articles),
        'unique_internal_ids': len(identifiers),
        'duplicate_internal_ids': sorted(k for k, v in identifiers.items() if v > 1),
        'duplicate_post_ids': sorted(k for k, v in posts.items() if v > 1),
        'duplicate_slugs': sorted(k for k, v in slugs.items() if v > 1),
        'missing_or_invalid_metadata_post_ids': sorted(bad_metadata),
        'meta_slug_mismatch_post_ids': sorted(slug_diff),
        'wordpress_ids_not_in_profiles': sorted(identifiers.keys() - indexed.keys()),
        'profile_ids_not_in_wordpress': sorted(indexed.keys() - identifiers.keys()),
        'missing_or_invalid_profiles': sorted(missing_profiles),
        'different_titles': sorted(title_diff),
        'exact_profiles_count': len(exact_profiles),
        'newline_to_literal_n_only_count': len(newline_only_profiles),
        'newline_to_literal_n_field_counts': dict(sorted(newline_fields.items())),
        'other_profile_difference_ids': sorted(other_profiles),
        'note': 'Snapshot only; the origin of newline differences, present site state and clinical validity are not established.',
    }


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--export', required=True)
    parser.add_argument('--profiles', required=True)
    args = parser.parse_args()
    try:
        with open(args.export, encoding='utf-8-sig') as f:
            export = json.load(f)
        with open(args.profiles, encoding='utf-8-sig') as f:
            profiles = json.load(f)
        result = compare_export(export, profiles)
    except (OSError, ValueError) as exc:
        print(f'export audit error: {exc}', file=sys.stderr)
        return 2
    print(json.dumps(result, ensure_ascii=False, indent=2))
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
