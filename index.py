import requests
import cv2
import time
import datetime
import mysql.connector
import json
import os

# =====================================================================
# ⏱ เวลาหน่วงแจ้งเตือน (วินาที)
# =====================================================================
COOLDOWN_TIME = 60 
COOLDOWN_FILE = "cira_memory.json"

# =====================================================================
#  ตั้งค่า Telegram
# =====================================================================
TOKEN = "7782238693:AAEMZ-q-4x9Cp9drMB3WcSkGG5Dz9XiMT1o"
CHAT_ID = "6756013706"
URL = f"https://api.telegram.org/bot{TOKEN}/sendPhoto"

# ตัวแปรสำหรับจับเวลาโชว์ข้อความสถานะ 5 วินาที บนหน้าจอ
if 'display_msg_time' not in globals():
    globals()['display_msg_time'] = 0
if 'current_display_msg' not in globals():
    globals()['current_display_msg'] = ""

th_names = {
    "DOG": "สุนัข",
    "CAT": "แมว",
    "CHICKEN": "ไก่",
    "BUFFALO": "ควาย"
}

# ---------------------------------------------------------
#  ระบบความจำถาวร: เปิดอ่านเวลาที่เคยเตือนล่าสุดจากสมุดจด
# ---------------------------------------------------------
last_alert_time = {}
if os.path.exists(COOLDOWN_FILE):
    try:
        with open(COOLDOWN_FILE, 'r', encoding='utf-8') as f:
            last_alert_time = json.load(f)
    except:
        pass

live_counts = {} # ตะกร้าเก็บจำนวนสัตว์สดๆ ในแต่ละเฟรม

try:
    # ดึงข้อมูลกรอบจาก AI
    boxes = payload.get("DeepDetect", {}).get("objects", [])
    
    if 'img' in globals() and img is not None:
        target_animals = ["DOG", "CAT", "CHICKEN", "BUFFALO"]
        
        # 1. นับยอดสัตว์จากกรอบในหน้าจอ
        for b in boxes:
            name = b.get("name", "").upper()
            if name in target_animals:
                live_counts[name] = live_counts.get(name, 0) + 1
        
        alert_list = []
        current_ts = time.time()
        need_save_memory = False 
        
        animals_to_alert = [] # เก็บรายชื่อสัตว์เพื่อเอาไปตั้งชื่อไฟล์

        # 2. ตรวจสอบเงื่อนไข Cooldown
        for name, count in live_counts.items():
            last_time = last_alert_time.get(name, 0)
            
            if (current_ts - last_time) > COOLDOWN_TIME:
                alert_list.append({
                    "name": name,
                    "th_name": th_names.get(name, name),
                    "count": count
                })
                animals_to_alert.append(name)
                # อัปเดตเวลาล่าสุดให้สัตว์ชนิดนี้
                last_alert_time[name] = current_ts
                need_save_memory = True 
        
        # 3. อัปเดตสมุดจด (เขียนลงไฟล์ JSON)
        if need_save_memory:
            try:
                with open(COOLDOWN_FILE, 'w', encoding='utf-8') as f:
                    json.dump(last_alert_time, f)
            except:
                pass

        # 4. ถ้าผ่านเงื่อนไขและต้องส่งแจ้งเตือน
        if alert_list:
            now = datetime.datetime.now()
            formatted_time = now.strftime('%Y-%m-%d %H:%M:%S')
            file_name_time = now.strftime('%Y%m%d_%H%M%S') 
            
            #  รวมชื่อสัตว์ตั้งเป็นชื่อไฟล์ (เช่น 20260305_094556_CHICKEN_CAT.jpg)
            animal_suffix = "_".join(animals_to_alert)
            full_img_name = f"{file_name_time}_{animal_suffix}.jpg"
            save_path = f"C:/xampp/htdocs/cira_report/images/{full_img_name}"
            
            # ประทับตราข้อความลงบนรูป
            watermark_text = f"ALERT: Animal Detected | {formatted_time}"
            cv2.putText(img, watermark_text, (20, 40), cv2.FONT_HERSHEY_SIMPLEX, 0.8, (0, 255, 0), 2, cv2.LINE_AA)
            
            details_text = ", ".join([f"{item['th_name']} {item['count']} ตัว" for item in alert_list])
            message = f" แจ้งเตือน! พบสัตว์บุกรุก: {details_text} เมื่อ {formatted_time}"
            
            # --- ทำงาน 3 อย่างหลัก ---
            # 1 เซฟรูปภาพลง XAMPP
            try:
                cv2.imwrite(save_path, img)
            except Exception as e:
                print("Error saving image:", e)

            # 2 ส่งแจ้งเตือนเข้า Telegram
            try:
                _, img_encoded = cv2.imencode('.jpg', img)
                files = {'photo': (full_img_name, img_encoded.tobytes())}
                data = {'chat_id': CHAT_ID, 'caption': message}
                requests.post(URL, data=data, files=files)
            except:
                pass

            # 3 บันทึกข้อมูลลงฐานข้อมูล MySQL
            try:
                db = mysql.connector.connect(host="localhost", user="root", password="", database="cira_db")
                cursor = db.cursor()
                sql = "INSERT INTO tbl_object (name, object_count, activedatetime) VALUES (%s, %s, %s)"
                for item in alert_list:
                    val = (item['name'], item['count'], now)
                    cursor.execute(sql, val)
                db.commit()
                db.close()
            except:
                pass
            
            # ตั้งเวลาโชว์ข้อความหลัก 5 วินาที
            globals()['current_display_msg'] = f"ส่งแจ้งเตือน: {details_text} (Telegram & Database เรียบร้อย)"
            globals()['display_msg_time'] = current_ts

except Exception as e:
    pass

# =====================================================================
#  โชว์ข้อความสถานะบนหน้าจอ CiRA CORE
# =====================================================================
if (time.time() - globals().get('display_msg_time', 0)) <= 5:
    print(globals().get('current_display_msg', ''))
else:
    if len(live_counts) > 0:
        live_details = ", ".join([f"{th_names.get(name, name)} {count} ตัว" for name, count in live_counts.items()])
        print(f"พบสัตว์บุกรุก: {live_details} (รอ Cooldown {COOLDOWN_TIME} วินาที)")
    else:
        print("ไม่พบสัตว์บุกรุก")