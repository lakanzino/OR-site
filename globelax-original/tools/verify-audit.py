import csv, pathlib, re, zipfile
root = pathlib.Path(__file__).resolve().parents[1]
text = (root/'src/data/countries.ts').read_text()
pattern = re.compile(r'^\s*\["([A-Z]{2})", "[^"]*", "[^"]*", "([^"]*)", "([^"]*)", "([A-Z]{3})", ([0-9.]+), ([0-9.]+),', re.M)
rows = {m[1]: m.groups() for m in pattern.finditer(text)}
audit = list(csv.DictReader((root/'audit/country-comparison.csv').open()))
assert len(rows) == len(audit) == 195
assert len({r['iso'] for r in audit}) == 195
for row in audit:
    iso = row['iso']
    _, fa, cap, currency, pop, area = rows[iso]
    assert cap == row['new_capital'] and currency == row['currency']
    assert round(float(pop)*1000) == int(float(row['new_population']))
    assert float(area) == float(row['new_area'])
    for field in ('population', 'area'):
        actual = 1 + sum(float(other['new_'+field]) > float(row['new_'+field]) for other in audit)
        assert actual == int(row['new_'+field+'_rank'])
    svg = (root/'src/flags'/f'{iso}.svg').read_text()
    assert '<svg' in svg and 'viewBox=' in svg and not re.search(r'<script|<foreignObject|(?:href|src)=["\']https?://', svg, re.I)
assert rows['GQ'][2] == 'Ciudad de la Paz'
assert rows['BG'][3] == 'EUR' and rows['ZW'][3] == 'ZWG'
assert len(list((root/'src/flags').glob('*.svg'))) == 195
assert (root/'dist/index.html').exists()
print('195 rows, rankings, SVGs and key corrections: OK')
