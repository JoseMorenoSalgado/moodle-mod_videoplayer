from pathlib import Path

SOURCE = Path('amd/src/pdfviewer.js')
BUNDLE = Path('amd/build/pdfviewer.min.js')
MAP = Path('amd/build/pdfviewer.min.js.map')

source = SOURCE.read_text(encoding='utf-8')
for old, new in (
    ('ebook-achievements', 'pdfjs-achievements'),
    ('ebook-points', 'pdfjs-points'),
    ('ebook-progress', 'pdfjs-progress'),
):
    if old not in source:
        raise SystemExit(f'Missing expected source token: {old}')
    source = source.replace(old, new)

old_source_points = "pointsNode.textContent = 'Points: ' + response.points;"
new_source_points = 'pointsNode.textContent = String(response.points);'
if old_source_points not in source:
    raise SystemExit('Missing expected source points assignment')
source = source.replace(old_source_points, new_source_points, 1)
SOURCE.write_text(source, encoding='utf-8')

bundle = BUNDLE.read_text(encoding='utf-8')
for old, new in (
    ('ebook-achievements', 'pdfjs-achievements'),
    ('ebook-points', 'pdfjs-points'),
    ('ebook-progress', 'pdfjs-progress'),
):
    if old not in bundle:
        raise SystemExit(f'Missing expected bundle token: {old}')
    bundle = bundle.replace(old, new)

old_bundle_points = 'pointsNode.textContent="Points: "+response.points'
new_bundle_points = 'pointsNode.textContent=String(response.points)'
if old_bundle_points not in bundle:
    raise SystemExit('Missing expected bundle points assignment')
bundle = bundle.replace(old_bundle_points, new_bundle_points, 1)
bundle = bundle.replace('\n//# sourceMappingURL=pdfviewer.min.js.map', '')
BUNDLE.write_text(bundle.rstrip() + '\n', encoding='utf-8')

if MAP.exists():
    MAP.unlink()
