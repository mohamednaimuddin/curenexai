"""Extract main rubric headings from OCR'd Kent Mind 1-30 pages.

A rubric heading in Kent's repertory format starts at column 1, is fully uppercase
(letters, spaces, hyphens, apostrophes, commas), and ends with `:` or `.` (sometimes
followed by qualifier like `(See Forsaken.)`). Sub-rubrics are indented or lowercase.
"""
import os, re, json, glob

OCR_DIR = r"c:\xampp\htdocs\curenexai\assets\Kent_rubics\extracted_ocr"

# A heading line: starts with 2+ uppercase letters, then optional uppercase/spaces/-,'
# ends with `:` or `.` possibly followed by ` (See ...)` and final `:` or `.`.
HEAD_RE = re.compile(r"^([A-Z][A-Z'’\-, ]{1,60}?)\s*[:.]\s*(\(See[^)]*\)\s*[:.]?)?\s*$")

rubrics = []
seen = set()
for path in sorted(glob.glob(os.path.join(OCR_DIR, "page_*.txt"))):
    with open(path, "r", encoding="utf-8") as f:
        for raw in f:
            line = raw.rstrip()
            if not line:
                continue
            m = HEAD_RE.match(line)
            if not m:
                continue
            name = m.group(1).strip()
            # Filter junk: must contain a vowel and length >= 3
            if len(name) < 3 or not re.search(r"[AEIOU]", name):
                continue
            # Skip month/page-noise like "MIND" alone? keep MIND as category header? skip.
            if name in {"MIND", "PAGE", "GRADING"}:
                continue
            # Normalize spacing
            name = re.sub(r"\s+", " ", name)
            key = name.upper()
            if key in seen:
                continue
            seen.add(key)
            rubrics.append(name)

with open(os.path.join(OCR_DIR, "rubrics_pdf.json"), "w", encoding="utf-8") as f:
    json.dump(rubrics, f, indent=2)

print(f"Extracted {len(rubrics)} unique rubric headings")
for r in rubrics:
    print(" -", r)
