import os
import cv2
import numpy as np
from flask import Flask, request, jsonify

app = Flask(__name__)

def analyze_image_damage(customer_img_path, original_img_path=None):
    """
    Perform robust OpenCV image analysis to detect package damage and spoofing risks.
    """
    # Read customer image
    c_img = cv2.imread(customer_img_path)
    if c_img is None:
        return {"result": "ERROR: Unable to read claim image", "damage_score": 0.0, "fake_score": 0.0}
    
    # 1. Edge Density Analysis (Canny) to detect splits, cracks, tear lines, and crushing
    c_gray = cv2.cvtColor(c_img, cv2.COLOR_BGR2GRAY)
    blurred = cv2.GaussianBlur(c_gray, (5, 5), 0)
    edges = cv2.Canny(blurred, 50, 150)
    edge_density = (np.sum(edges == 255) / edges.size) * 100
    
    # Baseline normal edge density is typically around 1.5% - 3.5%. Crushed or torn parcels exhibit 6%+ density
    damage_score = min(100.0, max(0.0, (edge_density / 8.0) * 100.0))
    
    fake_score = 5.0  # Safe base risk
    
    # 2. Side-by-Side Reference Comparison (if Grower uploaded an original package image)
    if original_img_path and os.path.exists(original_img_path):
        o_img = cv2.imread(original_img_path)
        if o_img is not None:
            # Resize both to standard dimension for fair histogram & structure matching
            c_resized = cv2.resize(c_img, (300, 300))
            o_resized = cv2.resize(o_img, (300, 300))
            
            # Convert to gray
            c_gray_res = cv2.cvtColor(c_resized, cv2.COLOR_BGR2GRAY)
            o_gray_res = cv2.cvtColor(o_resized, cv2.COLOR_BGR2GRAY)
            
            # Compute Absolute Difference
            diff = cv2.absdiff(c_gray_res, o_gray_res)
            mean_diff = np.mean(diff)
            
            # If the mean difference is near 0, the user uploaded the exact same image (highly suspicious!)
            if mean_diff < 1.5:
                fake_score = 98.0
                damage_score = 0.0
            else:
                # Compare Color Histograms (disparity in color profiles indicates parcel tampering/content changes)
                hist_c = cv2.calcHist([c_resized], [0, 1, 2], None, [8, 8, 8], [0, 256, 0, 256, 0, 256])
                hist_o = cv2.calcHist([o_resized], [0, 1, 2], None, [8, 8, 8], [0, 256, 0, 256, 0, 256])
                
                cv2.normalize(hist_c, hist_c)
                cv2.normalize(hist_o, hist_o)
                
                # Correlation matching: 1.0 = identical, smaller = different
                overlap = cv2.compareHist(hist_c, hist_o, cv2.HISTCMP_CORREL)
                
                # Disparity maps to damage and tamper metrics
                disparity = max(0.0, 1.0 - overlap)
                damage_score = min(100.0, max(damage_score, disparity * 100.0))
                
                # Check for metadata/noise profile spoofing (basic verification)
                fake_score = min(100.0, max(5.0, (1.0 - overlap) * 20.0))
    
    # Classify final result
    if fake_score > 60.0:
        result_label = "SUSPECTED DISPUTE / FAKE"
    elif damage_score > 35.0:
        result_label = "REAL DAMAGE"
    else:
        result_label = "NO DAMAGE DETECTED"
        
    return {
        "result": result_label,
        "damage_score": round(damage_score, 1),
        "fake_score": round(fake_score, 1)
    }

@app.route('/check', methods=['POST'])
def check_image():
    if 'customer_image' not in request.files:
        return jsonify({"error": "Missing claim image file"}), 400
        
    c_file = request.files['customer_image']
    c_path = "temp_claim.jpg"
    c_file.save(c_path)
    
    o_path = None
    if 'original_image' in request.files:
        o_file = request.files['original_image']
        o_path = "temp_original.jpg"
        o_file.save(o_path)
        
    analysis = analyze_image_damage(c_path, o_path)
    
    # Clean up temp files safely
    if os.path.exists(c_path):
        os.remove(c_path)
    if o_path and os.path.exists(o_path):
        os.remove(o_path)
        
    return jsonify(analysis)

if __name__ == '__main__':
    # Running locally on default 5000 port
    app.run(host='127.0.0.1', port=5000, debug=False)
