#!/usr/bin/env python3
"""Compare checker IDs with profile IDs; this does not verify live WordPress."""
import argparse
import json
import sys


class InventoryError(ValueError):
    pass


def index_items(items, id_key, kind):
    if not isinstance(items, list):
        raise InventoryError(f'{kind}: expected a list')
    result = {}
    for number, item in enumerate(items, 1):
        if not isinstance(item, dict):
            raise InventoryError(f'{kind} item {number}: expected an object')
        identifier = item.get(id_key)
        if not isinstance(identifier, str) or not identifier.strip():
            raise InventoryError(f'{kind} item {number}: missing {id_key}')
        if identifier != identifier.strip():
            raise InventoryError(f'{kind} item {number}: whitespace in {id_key}')
        if identifier in result:
            raise InventoryError(f'{kind}: duplicate ID {identifier}')
        result[identifier] = item
    return result


def compare(checker, profiles):
    if not isinstance(checker, dict):
        raise InventoryError('checker: expected an object')
    left = index_items(checker.get('diseases'), 'id', 'checker')
    right = index_items(profiles, 'diseaseId', 'profiles')
    shared = sorted(left.keys() & right.keys())
    mismatches, missing_names = [], []
    for identifier in shared:
        name = left[identifier].get('nameFa')
        profile = right[identifier].get('profile')
        profile_name = profile.get('nameFa') if isinstance(profile, dict) else None
        if not isinstance(name, str) or not name.strip() or not isinstance(profile_name, str) or not profile_name.strip():
            missing_names.append(identifier)
        elif name != profile_name:
            mismatches.append({'id': identifier, 'checker': name, 'profile': profile_name})
    return {
        'checker_count': len(left),
        'profiles_count': len(right),
        'shared_count': len(shared),
        'shared_ids': shared,
        'checker_only': sorted(left.keys() - right.keys()),
        'profiles_only': sorted(right.keys() - left.keys()),
        'different_names': mismatches,
        'missing_names': missing_names,
        'note': 'Exact ID and name comparison only; no WordPress publication or clinical equivalence verified.',
    }


def read_json(path):
    if path == '-':
        return json.load(sys.stdin)
    with open(path, encoding='utf-8') as source:
        return json.load(source)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--checker', required=True, help='checker JSON file, or - for stdin')
    parser.add_argument('--profiles', required=True, help='profiles JSON file, or - for stdin')
    args = parser.parse_args()
    if args.checker == args.profiles == '-':
        parser.error('only one input may come from stdin')
    try:
        result = compare(read_json(args.checker), read_json(args.profiles))
    except (OSError, ValueError) as exc:
        print(f'inventory error: {exc}', file=sys.stderr)
        return 2
    print(json.dumps(result, ensure_ascii=False, indent=2))
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
