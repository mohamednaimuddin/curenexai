"""
Scrape Kent's Repertory MIND chapter (pages 1-95) from homeoint.org
into a CSV compatible with import_kent_mind.php.

Output columns: page,rubric,sub_rubric,complete_rubric,remedy,grade
"""
import re
import csv
import html
import time
import sys
from pathlib import Path

import requests
import urllib3
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

BASE = "https://www.homeoint.org/books/kentrep/"
# 20 files: kent0000, kent0005, ..., kent0095
PAGES = [f"kent{n:04d}.htm" for n in range(0, 96, 5)]
HEADERS = {"User-Agent": "Mozilla/5.0"}

ROOT = Path(__file__).resolve().parent.parent
CACHE = ROOT / "assets" / "Kent_rubics" / "_kent_html"
OUT = ROOT / "assets" / "Kent_rubics" / "kent_mind_az.csv"
CACHE.mkdir(parents=True, exist_ok=True)


def fetch(name):
    p = CACHE / name
    if not p.exists():
        r = requests.get(BASE + name, headers=HEADERS, timeout=30, verify=False)
        r.raise_for_status()
        p.write_bytes(r.content)
        time.sleep(0.4)
    return p.read_bytes().decode("cp1252", errors="replace")


# ---- Tokenizer ----
TAG_RE = re.compile(r"<(/?)(\w+)([^>]*)>", re.I)


def tokenize(html):
    """Yield events with current style.
    Events:
      ('text', s, grade)
      ('open_b_p',)              -- <b><p>...
      ('close_p',)
      ('open_dir',) / ('close_dir',)
      ('anchor', name)
      ('br',)
    grade is 1/2/3 inferred from current b/i/font color stacks.
    """
    b_depth = 0
    i_depth = 0
    color_stack = []  # current color (or None)

    pos = 0
    pending_b = False  # saw <b> recently, watching for <p>

    def cur_grade():
        color = color_stack[-1] if color_stack else None
        if b_depth > 0 and color == "red":
            return 3
        if i_depth > 0 and color == "blue":
            return 2
        return 1

    while True:
        m = TAG_RE.search(html, pos)
        if not m:
            tail = html[pos:]
            if tail.strip():
                yield ("text", tail, cur_grade())
            return
        if m.start() > pos:
            text = html[pos : m.start()]
            if text.strip():
                yield ("text", text, cur_grade())
        is_close = m.group(1) == "/"
        tag = m.group(2).lower()
        attrs = m.group(3) or ""
        pos = m.end()

        if tag == "b":
            if is_close:
                b_depth = max(0, b_depth - 1)
                pending_b = False
            else:
                b_depth += 1
                pending_b = True
        elif tag == "i":
            if is_close:
                i_depth = max(0, i_depth - 1)
            else:
                i_depth += 1
        elif tag == "font":
            if is_close:
                if color_stack:
                    color_stack.pop()
            else:
                a = attrs.lower()
                if "ff0000" in a:
                    color_stack.append("red")
                elif "0000ff" in a:
                    color_stack.append("blue")
                else:
                    color_stack.append(None)
        elif tag == "p":
            if is_close:
                yield ("close_p",)
            else:
                if pending_b:
                    yield ("open_b_p",)
                    pending_b = False
                else:
                    yield ("open_p",)
        elif tag == "dir":
            yield ("close_dir",) if is_close else ("open_dir",)
            pending_b = False
        elif tag == "a":
            if not is_close:
                am = re.search(r'name\s*=\s*"([^"]+)"', attrs, re.I)
                if am:
                    yield ("anchor", am.group(1))
        elif tag == "br":
            yield ("br",)
        # ignore others; pending_b reset on most non-<p>
        if tag not in ("b", "font", "i", "a", "br"):
            pending_b = False


# ---- Parser: build rubric tree from token stream ----

# Heuristic: in Kent format, the rubric heading is the bold/red part at start
# of `<b><p>...</b>`, then the colon and remedies follow until </p>.
# Sub-rubrics live inside <dir>: `<p>label : remedies</p>`.
# Sub-rubric depth = current <dir> nesting.

REMEDY_TOKEN_RE = re.compile(r"^[A-Za-z][A-Za-z0-9-]*\.?$")


def split_label_and_remedies(text):
    text = html.unescape(text)
    text = re.sub(r"\s+", " ", text).strip()
    m = re.search(r"\s+:\s+", text)
    if not m:
        return text, ""
    return text[: m.start()].strip(), text[m.end() :].strip()


def parse_remedy_list(items):
    """Given a list of (text_chunk, grade) tuples, return list of (remedy, grade).
    Splits chunks by commas, handles parenthetical "(See ...)" by skipping.
    Strips trailing punctuation from each abbreviation.
    """
    out = []
    skip_paren = 0
    # Concatenate while preserving grade boundaries: tokenize per character is overkill;
    # commas split remedies. Within a comma-separated chunk, a single remedy may have
    # mixed grades only at trailing punctuation — take the highest grade present in the
    # alpha part.
    # Build a flat list: for each chunk, split by ',', producing (segment, grade) per piece
    # if comma is inside a chunk we keep the same grade for both halves.
    pieces = []  # list of (seg_text, grade)
    for txt, g in items:
        parts = txt.split(",")
        for j, part in enumerate(parts):
            pieces.append((part, g))
            if j < len(parts) - 1:
                pieces.append(("__COMMA__", g))

    cur_text = ""
    cur_grade = 1
    for seg, g in pieces:
        if seg == "__COMMA__":
            # flush
            r = clean_remedy(cur_text)
            if r:
                out.append((r, cur_grade))
            cur_text = ""
            cur_grade = 1
            continue
        # parenthetical filtering
        s = seg
        # Drop parenthesised cross-references
        while "(" in s:
            i = s.index("(")
            j = s.find(")", i)
            if j == -1:
                s = s[:i]
                break
            s = s[:i] + s[j + 1 :]
        if not s.strip():
            continue
        cur_text += s
        if g > cur_grade:
            cur_grade = g
    r = clean_remedy(cur_text)
    if r:
        out.append((r, cur_grade))
    return out


REMEDY_CLEAN_RE = re.compile(r"[A-Za-z][A-Za-z0-9\-]*\.?")


def clean_remedy(text):
    text = text.strip()
    if not text:
        return None
    # Strip trailing colon/semicolon if any leaked through
    text = text.strip(" .,:;")
    if not text:
        return None
    # Match the abbreviation token
    # Some entries are "Nux-v" or "ph-ac" — keep hyphens
    m = re.match(r"^[A-Za-z][A-Za-z0-9\-]*$", text)
    if not m:
        # take first match
        m2 = re.search(r"[A-Za-z][A-Za-z0-9\-]+", text)
        if not m2:
            return None
        text = m2.group(0)
    # Filter obvious noise
    bad = {"see", "compare", "and", "or", "also", "etc", "the", "with",
           "in", "for", "from", "of", "on", "at", "to", "agg", "amel",
           "menses", "morning", "evening"}
    if text.lower() in bad:
        return None
    if len(text) < 2:
        return None
    return text


def parse_page(html, default_page=None):
    """Yield dicts: {page, rubric, sub_rubric, complete_rubric, remedy, grade}."""
    tokens = list(tokenize(html))

    # First pass: locate page anchors P\d+
    # We'll track the current page by walking tokens and watching anchors.
    cur_page = default_page

    # We'll collect a sequence of "blocks": each block is either an open_b_p
    # rubric block or a regular open_p sub-rubric block. We track <dir> depth.

    # State machine over tokens:
    in_p = False
    p_kind = None  # 'rubric' or 'sub'
    p_text_chunks = []  # list of (text, grade)
    dir_depth = 0
    rubric_stack = []  # list of {label, depth}; rubric_stack[0] is current main rubric

    def flush_block():
        nonlocal p_text_chunks, p_kind, in_p
        if p_kind is None:
            p_text_chunks = []
            in_p = False
            return
        # Build the visible text to extract label
        visible = "".join(t for t, _ in p_text_chunks)
        label, _ = split_label_and_remedies(visible)
        # For remedy parsing, split chunks by " : " too
        # Find the colon position in the *visible* text and split chunks
        items_after_colon = []
        if " : " in visible:
            # rebuild chunks past the colon
            cum = 0
            past = False
            for t, g in p_text_chunks:
                if past:
                    items_after_colon.append((t, g))
                    continue
                if " : " in t:
                    idx = t.index(" : ")
                    after = t[idx + 3 :]
                    if after.strip():
                        items_after_colon.append((after, g))
                    past = True
                else:
                    cum += len(t)
                    if " : " in visible[: cum]:
                        # already passed in earlier chunk; shouldn't reach here
                        items_after_colon.append((t, g))
                        past = True
        # else: no remedies on this line (just a header)

        remedies = parse_remedy_list(items_after_colon) if items_after_colon else []

        # Update rubric stack
        if p_kind == "rubric":
            cleaned = clean_label(label)
            if not is_valid_rubric_label(cleaned):
                # Garbage (navigation arrow, separator). Ignore.
                p_text_chunks = []
                p_kind = None
                in_p = False
                return
            # New main rubric: replace stack
            rubric_stack[:] = [{"label": cleaned, "depth": 0}]
        else:
            cleaned = clean_label(label)
            if not is_valid_rubric_label(cleaned):
                # Sub-rubric without a real label (e.g. just remedies after a colon
                # without preceding text). Ignore but keep current rubric stack.
                p_text_chunks = []
                p_kind = None
                in_p = False
                return
            # Sub-rubric at current dir_depth
            # Pop deeper or equal levels
            while rubric_stack and rubric_stack[-1]["depth"] >= dir_depth:
                if rubric_stack[-1]["depth"] == 0:
                    break  # don't pop main rubric
                rubric_stack.pop()
            rubric_stack.append({"label": cleaned, "depth": dir_depth})

        if remedies and rubric_stack:
            main = rubric_stack[0]["label"]
            subs = [r["label"] for r in rubric_stack[1:]]
            sub_join = ", ".join(subs)
            complete = "Mind, " + main + ((", " + sub_join) if sub_join else "")
            for rem, g in remedies:
                yield_buffer.append({
                    "page": cur_page,
                    "rubric": main,
                    "sub_rubric": sub_join,
                    "complete_rubric": complete,
                    "remedy": rem,
                    "grade": g,
                })

        p_text_chunks = []
        p_kind = None
        in_p = False

    yield_buffer = []

    for ev in tokens:
        kind = ev[0]
        if kind == "anchor":
            name = ev[1]
            mp = re.match(r"P(\d+)$", name)
            if mp:
                cur_page = int(mp.group(1))
            continue
        if kind == "open_dir":
            flush_block()
            dir_depth += 1
            continue
        if kind == "close_dir":
            flush_block()
            dir_depth = max(0, dir_depth - 1)
            continue
        if kind == "open_b_p":
            flush_block()
            in_p = True
            p_kind = "rubric"
            p_text_chunks = []
            continue
        if kind == "open_p":
            flush_block()
            in_p = True
            p_kind = "sub"
            p_text_chunks = []
            continue
        if kind == "close_p":
            flush_block()
            continue
        if kind == "br":
            # treat as paragraph break in some pages
            continue
        if kind == "text" and in_p:
            t, g = ev[1], ev[2]
            # Skip header lines like "MIND" / "----------"
            if re.match(r"^\s*[-]+\s*$", t):
                continue
            p_text_chunks.append((t, g))

    # final flush
    flush_block()

    for r in yield_buffer:
        yield r


def clean_label(label):
    label = html.unescape(label).strip()
    # Strip trailing colon/period/comma
    label = re.sub(r"[\s:.,;]+$", "", label)
    # Drop "(See ...)" parentheses
    label = re.sub(r"\([^)]*\)", "", label).strip()
    label = re.sub(r"\s+", " ", label)
    return label


def is_valid_rubric_label(label):
    """Reject navigation arrows, separator lines, empty labels."""
    if not label:
        return False
    # Must contain at least one letter and start with a letter
    if not re.match(r"^[A-Za-z]", label):
        return False
    # Reject if it's just dashes / less-than signs
    if re.fullmatch(r"[-<>\s]+", label):
        return False
    return True


def main():
    rows = []
    for name in PAGES:
        # Page anchor base: kent0005.htm starts at P5 etc.
        base_p = int(re.search(r"kent(\d+)", name).group(1))
        if base_p == 0:
            base_p = 1  # P1 is on kent0000.htm
        print(f"Parsing {name} (starting near P{base_p}) ...", file=sys.stderr)
        html = fetch(name)
        n_before = len(rows)
        for r in parse_page(html, default_page=base_p):
            rows.append(r)
        print(f"  +{len(rows) - n_before} rows", file=sys.stderr)

    # Deduplicate (rubric+remedy) keeping max grade
    seen = {}
    for r in rows:
        key = (r["complete_rubric"].lower(), r["remedy"].lower())
        prev = seen.get(key)
        if prev is None or r["grade"] > prev["grade"]:
            seen[key] = r
    deduped = list(seen.values())

    with OUT.open("w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=["page", "rubric", "sub_rubric",
                                          "complete_rubric", "remedy", "grade"])
        w.writeheader()
        for r in deduped:
            w.writerow(r)

    distinct_rubrics = len({r["complete_rubric"] for r in deduped})
    distinct_remedies = len({r["remedy"].lower() for r in deduped})
    print(f"\nWrote {OUT}", file=sys.stderr)
    print(f"  rows               : {len(deduped)}", file=sys.stderr)
    print(f"  distinct rubrics   : {distinct_rubrics}", file=sys.stderr)
    print(f"  distinct remedies  : {distinct_remedies}", file=sys.stderr)


if __name__ == "__main__":
    main()
