#!/usr/bin/env python3
"""Generate seed-vote CSV files for Site Reviews import.

Spec (owner decision 2026-11-01, docs/doctor-ratings/seed-votes.fa.md):
per doctor a random vote count in [22, 61]; 80% of the votes are "above four
stars" (= 5 stars); the remaining 20% are randomly 2 or 3 stars; vote dates
are spread over the last N days (default 180) so they never look like a
same-day burst.

Usage:
  python3 generate_csv.py --doctors "Name:POST_ID,Name2:POST_ID2" \
      [--out seeds] [--days 180] [--min 22] [--max 61] [--seed 42] \
      [--header post_id,rating,date,status]

Output: <out>/doctor-<POST_ID>.csv (UTF-8, comma-separated, newest first).
Match --header and the column set to the import template of the installed
Site Reviews version before importing.
"""
import argparse
import csv
import datetime
import os
import random
import sys


def votes_for(rng, n, five_ratio=0.8):
    """n star values: five_ratio of them are 5 stars, the rest random 2/3."""
    five = round(n * five_ratio)
    out = [5] * five + [rng.choice([2, 3]) for _ in range(n - five)]
    rng.shuffle(out)
    return out


def main():
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument('--doctors', required=True,
                    help='comma-separated "Name:PostID" pairs')
    ap.add_argument('--out', default='seeds', help='output directory')
    ap.add_argument('--days', type=int, default=180,
                    help='spread vote dates over this many past days')
    ap.add_argument('--min', type=int, default=22, help='min vote count')
    ap.add_argument('--max', type=int, default=61, help='max vote count')
    ap.add_argument('--seed', type=int, default=None,
                    help='RNG seed for reproducible output')
    ap.add_argument('--header', default='post_id,rating,date,status',
                    help='CSV column order for the import template')
    args = ap.parse_args()

    rng = random.Random(args.seed)
    os.makedirs(args.out, exist_ok=True)
    now = datetime.datetime.now()

    for pair in args.doctors.split(','):
        name, sep, pid = pair.rpartition(':')
        if not sep or not pid.strip():
            sys.exit('bad --doctors entry %r (expected "Name:PostID")' % pair)
        pid = pid.strip()
        n = rng.randint(args.min, args.max)
        rows = []
        for rating in votes_for(rng, n):
            d = now - datetime.timedelta(days=rng.randint(0, args.days),
                                         hours=rng.randint(0, 23),
                                         minutes=rng.randint(0, 59))
            rows.append({'post_id': pid,
                         'rating': rating,
                         'date': d.strftime('%Y-%m-%d %H:%M:%S'),
                         'status': 'approved',
                         'name': name})
        rows.sort(key=lambda r: r['date'], reverse=True)
        path = os.path.join(args.out, 'doctor-%s.csv' % pid)
        fields = args.header.split(',')
        with open(path, 'w', newline='', encoding='utf-8') as f:
            w = csv.DictWriter(f, fieldnames=fields, extrasaction='ignore')
            w.writeheader()
            w.writerows(rows)
        stars5 = sum(1 for r in rows if r['rating'] == 5)
        print('%s -> %s (%d votes: %d x 5-star, %d x 2/3-star)'
              % (name, path, n, stars5, n - stars5))


if __name__ == '__main__':
    main()
