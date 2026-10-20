"""Check repository documentation links and local Markdown heading anchors."""
from pathlib import Path
import re
import sys
from urllib.parse import unquote, urlsplit

ROOT = Path(__file__).resolve().parent.parent
FILES = [ROOT / name for name in ("README.md", "AGENTS.md", "PRODUCT.md", "DESIGN.md")]
FILES += sorted((ROOT / "docs").rglob("*.md"))
FILES += [ROOT / "backend/app/Integrations/Mcp/README.md"]


def prose(path):
    text = path.read_text(encoding="utf-8-sig")
    return re.sub(r"^(`{3,}|~{3,}).*?^\1[^\n]*$", "", text, flags=re.M | re.S)


def anchors(path):
    result, counts = set(), {}
    for heading in re.findall(r"^ {0,3}#{1,6}\s+(.+?)\s*#*\s*$", prose(path), re.M):
        heading = re.sub(r"<[^>]+>", "", heading).lower()
        slug = re.sub(r"[^\w\- ]", "", heading, flags=re.UNICODE).replace(" ", "-")
        count = counts.get(slug, 0)
        counts[slug] = count + 1
        result.add(slug + (f"-{count}" if count else ""))
    return result


errors, checked = [], 0
for source in FILES:
    text = prose(source)
    for match in re.finditer(r"!?\[[^\]\n]*\]\(([^)\n]+)\)", text):
        raw = match.group(1).strip()
        target = raw[1:raw.index(">")] if raw.startswith("<") else re.split(r'\s+["\']', raw, maxsplit=1)[0]
        parsed = urlsplit(target)
        if parsed.scheme or parsed.netloc:
            continue
        checked += 1
        destination = (source.parent / unquote(parsed.path)).resolve() if parsed.path else source
        line = text.count("\n", 0, match.start()) + 1
        if not destination.exists():
            errors.append(f"{source.relative_to(ROOT)}:{line}: missing {target}")
        elif parsed.fragment and destination.suffix.lower() == ".md":
            if unquote(parsed.fragment) not in anchors(destination):
                errors.append(f"{source.relative_to(ROOT)}:{line}: missing heading {target}")

for error in errors:
    print(error)
print(f"Checked {len(FILES)} Markdown files and {checked} local links/anchors: " + ("FAIL" if errors else "PASS"))
sys.exit(bool(errors))
