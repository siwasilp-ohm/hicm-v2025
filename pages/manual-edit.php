<?php
/**
 * HICM V2025 - แก้ไขคู่มือการใช้งาน (Admin Only)
 * Admin can edit manual sections: intro, features, references
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();
if (!hasRole(ROLE_ADMIN)) {
    header('Location: ' . getBaseUrl() . '/pages/dashboard.php');
    exit;
}

$manualFile = dirname(__DIR__) . '/manual_content.json';
$defaultData = [
    'updated_at' => date('Y-m-d H:i'),
    'content' => '',
    'download_files' => [],
    'sections' => [
        'intro' => '',
        'part2' => '',
        'features' => '',
        'references' => ''
    ]
];

// Load existing
$manualData = $defaultData;
if (file_exists($manualFile)) {
    $raw = json_decode(file_get_contents($manualFile), true);
    if (is_array($raw)) {
        $manualData = array_merge($defaultData, $raw);
        if (empty($manualData['sections'])) {
            $manualData['sections'] = $defaultData['sections'];
        }
    }
}

// Save on POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $manualData['updated_at'] = date('Y-m-d H:i');
    $manualData['content'] = $_POST['content'] ?? '';
    $manualData['sections'] = [
        'intro' => $_POST['section_intro'] ?? '',
        'part2' => $_POST['section_part2'] ?? '',
        'features' => $_POST['section_features'] ?? '',
        'references' => $_POST['section_references'] ?? ''
    ];
    $downloadFiles = [];
    $labels = $_POST['download_label'] ?? [];
    $descs = $_POST['download_desc'] ?? [];
    $filenames = $_POST['download_filename'] ?? [];
    $filenameCustom = $_POST['download_filename_custom'] ?? [];
    if (is_array($labels)) {
        foreach ($labels as $i => $label) {
            $label = trim($label);
            $fn = trim($filenames[$i] ?? '');
            $fnCustom = trim($filenameCustom[$i] ?? '');
            $filename = ($fn === '::custom::' && $fnCustom) ? $fnCustom : $fn;
            if ($label && $filename) {
                $downloadFiles[] = [
                    'label' => $label,
                    'description' => trim($descs[$i] ?? ''),
                    'filename' => $filename
                ];
            }
        }
    }
    $manualData['download_files'] = $downloadFiles;
    file_put_contents($manualFile, json_encode($manualData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    header('Location: manual-edit.php?saved=1');
    exit;
}

$downloadFiles = $manualData['download_files'] ?? [];
if (empty($downloadFiles)) {
    $downloadFiles = [
        ['label' => 'ส่วนที่ 1 บทนำและหลักการ', 'description' => 'เนื้อหาคู่มือฉบับเต็ม', 'filename' => 'A_ส่วนที่ 1 บทนำและหลักการ.xlsx'],
        ['label' => 'ส่วนที่ 2 โครงสร้างและระบบคะแนน', 'description' => 'โครงสร้างตัวชี้วัดและสูตรคำนวณ', 'filename' => 'B_ส่วนที่ 2 โครงสร้างและระบบคะแนน.xlsx']
    ];
}

$saved = isset($_GET['saved']);
$baseUrl = getBaseUrl();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขคู่มือ - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/style.css">
    <style>
        .edit-container { max-width: 900px; margin: 2rem auto; padding: 0 1.5rem; }
        .edit-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
        .edit-title { font-size: 1.5rem; font-weight: 700; color: var(--primary-700); }
        .edit-tabs { display: flex; gap: 0.5rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .edit-tab { padding: 0.6rem 1.25rem; border-radius: var(--radius-lg); background: var(--gray-100); color: var(--gray-600); border: none; cursor: pointer; font-weight: 500; transition: all 0.2s; }
        .edit-tab:hover { background: var(--gray-200); }
        .edit-tab.active { background: var(--primary-600); color: #fff; }
        .edit-panel { display: none; background: #fff; border-radius: var(--radius-xl); box-shadow: var(--shadow-md); padding: 1.5rem; border: 1px solid var(--gray-100); }
        .edit-panel.active { display: block; }
        .edit-panel h3 { font-size: 1rem; color: var(--gray-600); margin-bottom: 0.75rem; }
        .edit-panel textarea { width: 100%; min-height: 280px; font-family: inherit; font-size: 0.95rem; padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--gray-300); resize: vertical; }
        .edit-panel textarea:focus { outline: none; border-color: var(--primary-500); box-shadow: 0 0 0 3px rgba(59,130,246,0.15); }
        .edit-help { font-size: 0.8rem; color: var(--gray-500); margin-top: 0.5rem; }
        .edit-actions { display: flex; gap: 1rem; margin-top: 1.5rem; flex-wrap: wrap; }
        .btn { padding: 0.6rem 1.5rem; border-radius: var(--radius-lg); font-weight: 600; border: none; cursor: pointer; font-size: 1rem; text-decoration: none; display: inline-block; }
        .btn-primary { background: var(--primary-600); color: #fff; }
        .btn-primary:hover { background: var(--primary-700); }
        .btn-secondary { background: var(--gray-100); color: var(--gray-700); }
        .btn-secondary:hover { background: var(--gray-200); }
        .alert-success { background: var(--success-light); color: #065f46; padding: 1rem; border-radius: var(--radius-lg); margin-bottom: 1.5rem; }
        .download-row { display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 1rem; padding: 1rem; background: var(--gray-50); border-radius: var(--radius-md); }
        .download-row-fields { flex: 1; display: flex; flex-direction: column; gap: 0.5rem; }
        .download-input { padding: 0.5rem; border-radius: var(--radius-md); border: 1px solid var(--gray-300); }
        .download-select { padding: 0.5rem; border-radius: var(--radius-md); border: 1px solid var(--gray-300); min-width: 280px; }
        .btn-remove-download { background: var(--danger); color: #fff; border: none; width: 32px; height: 32px; border-radius: var(--radius-md); cursor: pointer; flex-shrink: 0; }
        .btn-remove-download:hover { opacity: 0.9; }
    </style>
</head>
<body class="has-sidebar">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="sidebar-overlay"></div>
    <main class="main-wrapper">
        <div class="main-content">
        <div class="edit-container">
            <div class="edit-header">
                <h1 class="edit-title">✏️ แก้ไขคู่มือการใช้งานระบบ</h1>
                <a href="manual.php" class="btn btn-secondary">← กลับไปดูคู่มือ</a>
            </div>

            <?php if ($saved): ?>
                <div class="alert-success">บันทึกข้อมูลเรียบร้อยแล้ว</div>
            <?php endif; ?>

            <form method="post" id="manualEditForm">
                <div class="edit-tabs" role="tablist">
                    <button type="button" class="edit-tab active" data-tab="intro">บทนำและหลักการ</button>
                    <button type="button" class="edit-tab" data-tab="part2">โครงสร้างและระบบคะแนน</button>
                    <button type="button" class="edit-tab" data-tab="features">ฟีเจอร์และฟังก์ชัน</button>
                    <button type="button" class="edit-tab" data-tab="downloads">ลิงก์ดาวน์โหลดเอกสาร</button>
                    <button type="button" class="edit-tab" data-tab="references">เอกสารอ้างอิง</button>
                </div>

                <div id="panel-intro" class="edit-panel active">
                    <h3>บทนำและหลักการ (ส่วนที่ 1)</h3>
                    <textarea name="section_intro" placeholder="กรอกเนื้อหาบทนำ หลักการ HICM... รองรับ Markdown"><?php echo htmlspecialchars($manualData['sections']['intro'] ?? ''); ?></textarea>
                    <div class="edit-help">รองรับ Markdown: # หัวข้อ, **ตัวหนา**, - รายการ</div>
                </div>
                <div id="panel-part2" class="edit-panel">
                    <h3>โครงสร้างและระบบคะแนน (ส่วนที่ 2)</h3>
                    <textarea name="section_part2" placeholder="กรอกเนื้อหาโครงสร้าง 4 Pillars ระบบคะแนน ระดับการรับรอง... รองรับ Markdown และตาราง"><?php echo htmlspecialchars($manualData['sections']['part2'] ?? ''); ?></textarea>
                    <div class="edit-help">รองรับ Markdown และตาราง | Column1 | Column2 |</div>
                </div>
                <div id="panel-features" class="edit-panel">
                    <h3>ฟีเจอร์และฟังก์ชันหลัก</h3>
                    <textarea name="section_features" placeholder="กรอกรายละเอียดฟีเจอร์ ระบบประเมิน ระบบคะแนน..."><?php echo htmlspecialchars($manualData['sections']['features'] ?? ''); ?></textarea>
                    <div class="edit-help">รองรับ Markdown</div>
                </div>
                <div id="panel-downloads" class="edit-panel">
                    <h3>ลิงก์ดาวน์โหลดเอกสาร</h3>
                    <p class="edit-help" style="margin-bottom:1rem;">เพิ่ม/แก้ไขรายการไฟล์ที่ให้ User ดาวน์โหลดได้ ลิงก์จะแสดงอัตโนมัติในหน้ามืออาชีพและส่วนเอกสารอ้างอิง</p>
                    <div id="download-entries">
                        <?php foreach ($downloadFiles as $idx => $df): ?>
                        <div class="download-row" data-idx="<?php echo $idx; ?>">
                            <div class="download-row-fields">
                                <input type="text" name="download_label[]" value="<?php echo htmlspecialchars($df['label'] ?? ''); ?>" placeholder="ชื่อเอกสาร (เช่น ส่วนที่ 1 บทนำและหลักการ)" class="download-input">
                                <input type="text" name="download_desc[]" value="<?php echo htmlspecialchars($df['description'] ?? ''); ?>" placeholder="คำอธิบายสั้นๆ" class="download-input">
                                <select name="download_filename[]" class="download-select download-filename-select" style="max-width:320px;">
                                    <option value="<?php echo htmlspecialchars($df['filename'] ?? ''); ?>" selected><?php echo htmlspecialchars($df['filename'] ?? ''); ?></option>
                                    <option value="::custom::">--- ระบุชื่อไฟล์เอง ---</option>
                                </select>
                                <span class="download-custom-wrap" style="display:none; margin-top:0.5rem;">
                                    ชื่อไฟล์: <input type="text" name="download_filename_custom[]" placeholder="เช่น เอกสาร.xlsx" class="download-input" style="width:220px;">
                                </span>
                            </div>
                            <button type="button" class="btn-remove-download" title="ลบ">✕</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top:1rem;">
                        <button type="button" id="add-download-btn" class="btn btn-secondary">+ เพิ่มรายการ</button>
                        <div style="margin-top:1rem; padding:1rem; background:var(--gray-50); border-radius:var(--radius-md);">
                            <strong>อัปโหลดไฟล์ใหม่:</strong>
                            <input type="file" id="upload-manual-file" accept=".xlsx,.xls,.pdf,.doc,.docx" style="margin-left:0.5rem; max-width:300px;">
                            <span id="upload-status"></span>
                        </div>
                    </div>
                </div>
                <div id="panel-references" class="edit-panel">
                    <h3>เอกสารอ้างอิง</h3>
                    <textarea name="section_references" placeholder="กรอกคำอธิบายเอกสารอ้างอิง..."><?php echo htmlspecialchars($manualData['sections']['references'] ?? ''); ?></textarea>
                    <div class="edit-help">ตั้งค่าลิงก์ดาวน์โหลดได้ที่แท็บ "ลิงก์ดาวน์โหลดเอกสาร"</div>
                </div>

                <input type="hidden" name="content" value="<?php echo htmlspecialchars($manualData['content'] ?? ''); ?>">

                <div class="edit-actions">
                    <button type="submit" class="btn btn-primary">💾 บันทึก</button>
                    <a href="manual.php" class="btn btn-secondary">ยกเลิก</a>
                </div>
            </form>
        </div>
        </div>
    </main>
    <script>
        const baseUrl = '<?php echo $baseUrl; ?>';
        document.querySelectorAll('.edit-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                const target = tab.dataset.tab;
                document.querySelectorAll('.edit-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.edit-panel').forEach(p => p.classList.remove('active'));
                tab.classList.add('active');
                document.getElementById('panel-' + target).classList.add('active');
            });
        });

        let availableFiles = [];
        fetch(baseUrl + '/api/manual-ref-files.php?action=list').then(r=>r.json()).then(d=>{ if(d.success) availableFiles=d.files; });

        function addDownloadRow(label='', desc='', filename='') {
            const container = document.getElementById('download-entries');
            const idx = container.querySelectorAll('.download-row').length;
            const row = document.createElement('div');
            row.className = 'download-row';
            row.dataset.idx = idx;
            let opts = '<option value="">-- เลือกไฟล์ --</option>';
            availableFiles.forEach(f=>{ opts += '<option value="'+f.name+'"'+(f.name===filename?' selected':'')+'>'+f.name+'</option>'; });
            if (filename && !availableFiles.find(f=>f.name===filename)) opts += '<option value="'+filename+'" selected>'+filename+'</option>';
            row.innerHTML = '<div class="download-row-fields">' +
                '<input type="text" name="download_label[]" value="'+label+'" placeholder="ชื่อเอกสาร" class="download-input">' +
                '<input type="text" name="download_desc[]" value="'+desc+'" placeholder="คำอธิบายสั้นๆ" class="download-input">' +
                '<select name="download_filename[]" class="download-select download-filename-select">'+opts+'<option value="::custom::">--- ระบุชื่อไฟล์เอง ---</option></select>' +
                '<span class="download-custom-wrap" style="display:none; margin-top:0.5rem;">ชื่อไฟล์: <input type="text" name="download_filename_custom[]" placeholder="เช่น เอกสาร.xlsx" class="download-input" style="width:220px;"></span>' +
                '</div><button type="button" class="btn-remove-download" title="ลบ">✕</button>';
            container.appendChild(row);
            row.querySelector('.btn-remove-download').addEventListener('click', () => row.remove());
        }

        document.getElementById('add-download-btn').addEventListener('click', () => addDownloadRow());

        document.querySelectorAll('.btn-remove-download').forEach(btn => {
            btn.addEventListener('click', () => btn.closest('.download-row').remove());
        });

        document.getElementById('upload-manual-file').addEventListener('change', function() {
            const fd = new FormData();
            fd.append('file', this.files[0]);
            fd.append('action', 'upload');
            const status = document.getElementById('upload-status');
            status.textContent = 'กำลังอัปโหลด...';
            fetch(baseUrl + '/api/manual-ref-files.php', { method: 'POST', body: fd }).then(r=>r.json()).then(d=>{
                status.textContent = d.success ? 'อัปโหลดสำเร็จ: ' + d.filename : (d.message || 'เกิดข้อผิดพลาด');
                if (d.success) {
                    availableFiles.push({ name: d.filename, source: 'manual_refs' });
                    addDownloadRow('', '', d.filename);
                }
                this.value = '';
            });
        });

        fetch(baseUrl + '/api/manual-ref-files.php?action=list').then(r=>r.json()).then(d=>{
            if (d.success) {
                availableFiles = d.files;
                document.querySelectorAll('.download-row').forEach(row => {
                    const sel = row.querySelector('.download-filename-select');
                    const cur = sel.value;
                    const wrap = row.querySelector('.download-custom-wrap');
                    sel.innerHTML = '<option value="">-- เลือกไฟล์ --</option>';
                    d.files.forEach(f => { sel.innerHTML += '<option value="'+f.name+'"'+(f.name===cur?' selected':'')+'>'+f.name+'</option>'; });
                    if (cur && !d.files.find(f=>f.name===cur)) sel.innerHTML += '<option value="'+cur+'" selected>'+cur+'</option>';
                    sel.innerHTML += '<option value="::custom::"'+(cur==='::custom::'?' selected':'')+'>--- ระบุชื่อไฟล์เอง ---</option>';
                    wrap.style.display = cur === '::custom::' ? 'inline' : 'none';
                    sel.addEventListener('change', function() { wrap.style.display = this.value === '::custom::' ? 'inline' : 'none'; });
                });
            }
        });
    </script>
</body>
</html>
