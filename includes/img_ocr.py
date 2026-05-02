import sys
from PIL import Image
import pytesseract

if len(sys.argv) < 2:
    print('')
    sys.exit(0)

img_path = sys.argv[1]
try:
    text = pytesseract.image_to_string(Image.open(img_path))
    print(text)
except Exception as e:
    print('ERROR:', str(e))