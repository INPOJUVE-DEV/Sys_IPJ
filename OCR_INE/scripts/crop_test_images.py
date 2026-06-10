"""Crop and save front/back images from scanned pages."""

import os
import cv2
import numpy as np

TEST_IMAGES_DIR = os.path.join(os.path.dirname(__file__), "..", "test_images")
CROPPED_DIR = os.path.join(TEST_IMAGES_DIR, "cropped")


def crop_cards(image_path: str):
    filename = os.path.basename(image_path)
    name, _ = os.path.splitext(filename)
    
    img = cv2.imread(image_path)
    if img is None:
        print(f"Could not read {image_path}")
        return

    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    
    # Threshold to find white cards on potentially non-white background, 
    # or if background is white and cards have border.
    # Scans usually have white background. Cards are also light.
    # Otsu might work if there's contrast. 
    # Let's try adaptive threshold or just Canny.
    
    blur = cv2.GaussianBlur(gray, (5, 5), 0)
    _, thresh = cv2.threshold(blur, 200, 255, cv2.THRESH_BINARY_INV) # Invert: assume white bg, card has some darkness? 
    # Actually INE cards are colorful.
    
    # Better: Canny
    edges = cv2.Canny(blur, 50, 150)
    
    # Dilate to connect edges
    kernel = np.ones((5, 5), np.uint8)
    dilated = cv2.dilate(edges, kernel, iterations=2)
    
    contours, _ = cv2.findContours(dilated, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    
    # Filter by area
    min_area = (img.shape[0] * img.shape[1]) * 0.05
    valid_contours = []
    
    for c in contours:
        if cv2.contourArea(c) > min_area:
            valid_contours.append(c)
            
    # Sort by area (descending)
    valid_contours.sort(key=cv2.contourArea, reverse=True)
    
    # Take top 2
    cards = valid_contours[:2]
    
    if len(cards) < 2:
        print(f"Found {len(cards)} cards in {filename}, expected 2. Skipping auto-crop.")
        # Fallback: just divide image in half vertically or horizontally?
        # Let's simple-split if detection fails
        h, w = img.shape[:2]
        if h > w: # Portrait scan
             # Split Top/Bottom
            front = img[0:h//2, :]
            back = img[h//2:, :]
        else:
            # Split Left/Right
            front = img[:, 0:w//2]
            back = img[:, w//2:]
            
        _save(front, name + "_front")
        _save(back, name + "_back")
        return

    # Sort top-to-bottom or left-to-right
    # Get bounding boxes
    bboxes = [cv2.boundingRect(c) for c in cards]
    # Sort by Y mainly
    bboxes.sort(key=lambda b: b[1]) 
    
    # If Y difference is small, sort by X
    if abs(bboxes[0][1] - bboxes[1][1]) < 100:
        bboxes.sort(key=lambda b: b[0])
        
    for i, (x, y, w, h) in enumerate(bboxes):
        suffix = "front" if i == 0 else "back"
        crop = img[y:y+h, x:x+w]
        _save(crop, name + "_" + suffix)

def _save(img, name):
    os.makedirs(CROPPED_DIR, exist_ok=True)
    path = os.path.join(CROPPED_DIR, name + ".jpg")
    cv2.imwrite(path, img)
    print(f"Saved {path}")

def main():
    # Process only the first few distinct pages to avoid noise
    # page1 (rotated), page2 (rotated)
    files = ["page1_img1.png", "page2_img1.png", "page10_img1.png"]
    
    for f in files:
        path = os.path.join(TEST_IMAGES_DIR, f)
        if os.path.exists(path):
            print(f"Processing {f}...")
            crop_cards(path)

if __name__ == "__main__":
    main()
