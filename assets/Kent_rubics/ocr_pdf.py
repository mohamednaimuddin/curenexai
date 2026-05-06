"""OCR Kent_Mind_Rubrics 1-30.pdf to text files."""
import os, sys
import pytesseract
from pdf2image import convert_from_path

TESS = r"C:\Program Files\Tesseract-OCR\tesseract.exe"
POPPLER = r"C:\Users\moham\AppData\Local\Microsoft\WinGet\Packages\oschwartz10612.Poppler_Microsoft.Winget.Source_8wekyb3d8bbwe\poppler-25.07.0\Library\bin"

pytesseract.pytesseract.tesseract_cmd = TESS

PDF = sys.argv[1] if len(sys.argv) > 1 else r"c:\xampp\htdocs\curenexai\assets\Kent_rubics\Kent_Mind_Rubrics 1-30.pdf"
OUT_DIR = sys.argv[2] if len(sys.argv) > 2 else r"c:\xampp\htdocs\curenexai\assets\Kent_rubics\extracted_ocr"

os.makedirs(OUT_DIR, exist_ok=True)
print(f"Converting {PDF} ...", flush=True)
pages = convert_from_path(PDF, dpi=300, poppler_path=POPPLER)
print(f"Got {len(pages)} pages", flush=True)

all_text = []
for i, img in enumerate(pages, 1):
    txt = pytesseract.image_to_string(img)
    out = os.path.join(OUT_DIR, f"page_{i:03d}.txt")
    with open(out, "w", encoding="utf-8") as f:
        f.write(txt)
    all_text.append(f"===== PAGE {i} =====\n{txt}")
    print(f"  page {i} -> {len(txt)} chars", flush=True)

with open(os.path.join(OUT_DIR, "all.txt"), "w", encoding="utf-8") as f:
    f.write("\n".join(all_text))
print("Done.")
