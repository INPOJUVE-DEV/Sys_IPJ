"""End-to-end test using real cropped images against the Docker service."""

import os
import time
import httpx
import json
import glob

API_URL = "http://localhost:8001/v1/ine/extract"
API_KEY = "change-me-in-production"
TEST_IMAGES_DIR = os.path.join(os.path.dirname(__file__), "..", "test_images", "cropped")
RESULTS_DIR = os.path.join(os.path.dirname(__file__), "..", "test_results")


def test_pair(front_path: str, back_path: str):
    print(f"Testing {os.path.basename(front_path)} + {os.path.basename(back_path)}...")
    
    with open(front_path, "rb") as f_front, open(back_path, "rb") as f_back:
        files = {
            "front_image": ("front.jpg", f_front, "image/jpeg"),
            "back_image": ("back.jpg", f_back, "image/jpeg"),
        }
        headers = {"X-Api-Key": API_KEY}
        
        start = time.time()
        try:
            resp = httpx.post(API_URL, files=files, headers=headers, timeout=30.0)
            duration = time.time() - start
            
            print(f"  Status: {resp.status_code} ({duration:.2f}s)")
            
            if resp.status_code == 200:
                data = resp.json()
                # Save result
                base_name = os.path.basename(front_path).replace("_front.jpg", "")
                out_path = os.path.join(RESULTS_DIR, f"{base_name}_result.json")
                with open(out_path, "w", encoding="utf-8") as f:
                    json.dump(data, f, indent=2, ensure_ascii=False)
                print(f"  Saved result to {out_path}")
                
                # Print key confidence
                ben = data.get("beneficiarios", {})
                print(f"  Model: {data.get('model_id')}")
                print(f"  Nombre: {ben.get('nombre', {}).get('value')} ({ben.get('nombre', {}).get('confidence')})")
                print(f"  CURP: {ben.get('curp', {}).get('value')} ({ben.get('curp', {}).get('confidence')})")
                print(f"  ID INE: {ben.get('id_ine', {}).get('value')} ({ben.get('id_ine', {}).get('confidence')})")
            else:
                print(f"  Error: {resp.text}")
                
        except httpx.ConnectError:
            print("  Error: Could not connect to API. Is Docker running?")
        except httpx.TimeoutException:
            print("  Error: Request timed out.")

def main():
    os.makedirs(RESULTS_DIR, exist_ok=True)
    
    # Find all front images
    fronts = glob.glob(os.path.join(TEST_IMAGES_DIR, "*_front.jpg"))
    
    if not fronts:
        print("No cropped images found. Run crop_test_images.py first.")
        return

    print(f"Found {len(fronts)} test cases.")
    
    for front in fronts:
        back = front.replace("_front.jpg", "_back.jpg")
        if os.path.exists(back):
            test_pair(front, back)
        else:
            print(f"Skipping {front}: No matching back image.")

if __name__ == "__main__":
    main()
