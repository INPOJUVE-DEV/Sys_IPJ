"""Extract images from the Pruebas OCR.pdf test file."""

import os
import sys
import fitz  # PyMuPDF

PDF_PATH = os.path.join(
    os.path.dirname(__file__), "..", "..",
    "docs", "modules", "ocr-ine", "Pruebas OCR.pdf",
)
OUTPUT_DIR = os.path.join(os.path.dirname(__file__), "..", "test_images")


def main():
    os.makedirs(OUTPUT_DIR, exist_ok=True)

    doc = fitz.open(PDF_PATH)
    print(f"PDF has {len(doc)} pages")

    img_count = 0
    for page_num, page in enumerate(doc):
        images = page.get_images(full=True)
        print(f"  Page {page_num + 1}: {len(images)} images")

        for img_idx, img_info in enumerate(images):
            xref = img_info[0]
            pix = fitz.Pixmap(doc, xref)

            # Convert CMYK to RGB if needed
            if pix.n > 4:
                pix = fitz.Pixmap(fitz.csRGB, pix)

            filename = f"page{page_num + 1}_img{img_idx + 1}.png"
            filepath = os.path.join(OUTPUT_DIR, filename)
            pix.save(filepath)
            print(f"    Saved: {filename} ({pix.width}x{pix.height})")
            img_count += 1

    print(f"\nTotal: {img_count} images extracted to {OUTPUT_DIR}")
    doc.close()


if __name__ == "__main__":
    main()
