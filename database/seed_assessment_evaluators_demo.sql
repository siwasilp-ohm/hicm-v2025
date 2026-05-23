-- ============================================
-- Demo data for assessment_evaluators
-- ใส่ข้อมูล demo เพื่อทดสอบ flow การมอบหมายกรรมการ
-- ============================================
-- หมายเหตุ: ID ของ assessments และ users ขึ้นกับลำดับการ insert
-- ถ้า ID ไม่ตรง แนะนำให้รัน: php scripts/seed_assessment_evaluators.php
-- ============================================

USE hicm_v2025;

-- ตัวอย่าง: มอบหมายกรรมการให้แบบประเมิน (ปรับ assessment_id, user_id ให้ตรงกับ DB จริง)
-- หา auditor: SELECT id, username FROM users WHERE role = 'auditor';
-- หา assessment: SELECT id, company_id FROM assessments ORDER BY id;

-- ตัวอย่างแบบใช้ subquery (มอบหมาย auditor คนแรกให้ทุก assessment ที่มีอยู่)
INSERT IGNORE INTO assessment_evaluators (assessment_id, user_id)
SELECT a.id, (SELECT id FROM users WHERE role = 'auditor' AND is_active = 1 ORDER BY id LIMIT 1)
FROM assessments a
WHERE NOT EXISTS (
    SELECT 1 FROM assessment_evaluators ae
    WHERE ae.assessment_id = a.id
    AND ae.user_id = (SELECT id FROM users WHERE role = 'auditor' AND is_active = 1 ORDER BY id LIMIT 1)
);

-- อัปเดต assessments ให้มี evaluated_by / evaluator_id (คนแรกที่ถูกมอบหมาย)
UPDATE assessments a
SET evaluated_by = (
    SELECT ae.user_id FROM assessment_evaluators ae WHERE ae.assessment_id = a.id ORDER BY ae.assigned_at LIMIT 1
),
evaluator_id = (
    SELECT ae.user_id FROM assessment_evaluators ae WHERE ae.assessment_id = a.id ORDER BY ae.assigned_at LIMIT 1
),
status = CASE WHEN a.status = 'submitted' THEN 'under_review' ELSE a.status END,
updated_at = NOW()
WHERE EXISTS (SELECT 1 FROM assessment_evaluators ae WHERE ae.assessment_id = a.id);

-- ใส่กรรมการคนที่ 2 ให้บาง assessment (เลือก assessment ที่มีแค่ 1 คน)
INSERT IGNORE INTO assessment_evaluators (assessment_id, user_id)
SELECT a.id, u.id
FROM assessments a
CROSS JOIN users u
WHERE u.role = 'auditor' AND u.is_active = 1
AND (SELECT COUNT(*) FROM assessment_evaluators ae WHERE ae.assessment_id = a.id) = 1
AND u.id != (SELECT user_id FROM assessment_evaluators WHERE assessment_id = a.id LIMIT 1)
ORDER BY a.id, u.id
LIMIT 5;
