import cv2
import sys
import base64
import numpy as np
import face_recognition

def compare_faces(stored_image, captured_image):
    try:
        stored_data = base64.b64decode(stored_image.split(',')[1])
        captured_data = base64.b64decode(captured_image.split(',')[1])
        stored_img = cv2.imdecode(np.frombuffer(stored_data, np.uint8), cv2.IMREAD_COLOR)
        captured_img = cv2.imdecode(np.frombuffer(captured_data, np.uint8), cv2.IMREAD_COLOR)
        stored_rgb = cv2.cvtColor(stored_img, cv2.COLOR_BGR2RGB)
        captured_rgb = cv2.cvtColor(captured_img, cv2.COLOR_BGR2RGB)
        stored_encodings = face_recognition.face_encodings(stored_rgb)
        captured_encodings = face_recognition.face_encodings(captured_rgb)
        if not stored_encodings or not captured_encodings:
            return False
        result = face_recognition.compare_faces([stored_encodings[0]], captured_encodings[0], tolerance=0.6)
        return result[0]
    except Exception as e:
        print(f"Error: {e}", file=sys.stderr)
        return False

if __name__ == "__main__":
    stored = sys.argv[1]
    captured = sys.argv[2]
    print(compare_faces(stored, captured))