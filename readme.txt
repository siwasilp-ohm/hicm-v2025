============================================================
  HICM V2025 — ระบบประเมินสถานประกอบการน่าอยู่คู่ชุมชน
  คู่มือการติดตั้งและใช้งาน
============================================================

สิ่งที่ต้องติดตั้งก่อน
─────────────────────────────
  ✓ Docker Desktop  https://www.docker.com/products/docker-desktop
    (ติดตั้งเสร็จแล้วเปิด Docker Desktop ทิ้งไว้ก่อนรันคำสั่ง)


วิธีติดตั้ง (ทำครั้งเดียว)
─────────────────────────────
  1. วางไฟล์ทั้งหมดในโฟลเดอร์เดียวกัน

  2. เปิด Terminal / PowerShell ที่โฟลเดอร์นั้น แล้วรันตามลำดับ:

     Windows PowerShell:
       > copy .env.example .env
       > docker load -i hicm_v2025_image.tar.gz
       > docker compose up -d

     macOS / Linux:
       $ cp .env.example .env
       $ docker load < hicm_v2025_image.tar.gz
       $ docker compose up -d

  3. รอประมาณ 30–60 วินาที แล้วเปิด browser


URL การใช้งาน
─────────────────────────────
  ระบบ HICM   →  http://localhost:8080
  phpMyAdmin  →  http://localhost:8081


คำสั่งที่ใช้บ่อย
─────────────────────────────
  เริ่มระบบ    :  docker compose up -d
  หยุดระบบ    :  docker compose stop
  ดู log       :  docker compose logs -f app


หมายเหตุ
─────────────────────────────
  • ถ้ารัน XAMPP อยู่ด้วย ให้แก้ไฟล์ .env
      DB_PORT=3307   (แทน 3306)
    แล้วรัน: docker compose up -d

  • ข้อมูลฐานข้อมูลและไฟล์อัปโหลดจะถูกเก็บใน Docker Volume
    ข้อมูลจะไม่หายแม้จะหยุด/รีสตาร์ท container

============================================================
