import sys
from pdfminer.high_level import extract_text

if len(sys.argv) < 2:
    print('')
    sys.exit(0)

pdf_path = sys.argv[1]
try:
    text = extract_text(pdf_path)
    print(text)
except Exception as e:
    print('ERROR:', str(e))