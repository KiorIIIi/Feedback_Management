from pathlib import Path
import json, subprocess, concurrent.futures
root = Path(__file__).resolve().parents[1]
assets = json.loads((root/'design-reference/assets.json').read_text())
def download(a):
    dest = root/'frontend/public/assets/figma'/a['path']
    dest.parent.mkdir(parents=True, exist_ok=True)
    if not dest.exists() or not dest.stat().st_size:
        subprocess.run(['curl.exe','-4','--fail','-L','--connect-timeout','15','--max-time','45','--retry','2','--silent','--show-error',a['url'],'-o',str(dest)], check=True)
    return dest.stat().st_size
with concurrent.futures.ThreadPoolExecutor(max_workers=10) as pool:
    sizes = list(pool.map(download, assets))
print(f'Exported {len(sizes)} Figma assets, {sum(sizes)} bytes')
