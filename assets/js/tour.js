/**
 * HICM V2025 — Interactive Guided Tour System  v3
 * Spotlight tour · Series navigation · Role-aware · Page-specific deep steps
 */

const HICMTour = (() => {

  // ─── Constants ─────────────────────────────────────────────────
  const STORAGE_KEY    = 'hicm_tour_v3';
  const TRANSITION_MS  = 380;
  const PAD            = 10;   // spotlight padding
  const TIP_W          = 308;  // regular tooltip width
  const FIN_W          = 348;  // finale tooltip width

  // ─── SVG Icon Library ──────────────────────────────────────────
  const IC = {
    nav:     `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>`,
    chart:   `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>`,
    stats:   `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>`,
    form:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>`,
    score:   `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>`,
    upload:  `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>`,
    send:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>`,
    notif:   `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>`,
    user:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>`,
    company: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>`,
    info:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>`,
    period:  `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>`,
    report:  `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>`,
    pillar:  `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>`,
    filter:  `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>`,
    check:   `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>`,
    map:     `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>`,
    edit:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>`,
    status:  `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>`,
    export:  `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>`,
    welcome: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><path d="M9 22V12h6v10"/></svg>`,
    arrow:   `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>`,
  };

  // ─── Icon colors ───────────────────────────────────────────────
  const IC_COLOR = {
    nav:    ['#EFF6FF','#2563EB'], chart:  ['#F0FDF4','#10B981'],
    stats:  ['#EDE9FE','#8B5CF6'], form:   ['#FFF7ED','#F59E0B'],
    score:  ['#ECFDF5','#059669'], upload: ['#EFF6FF','#3B82F6'],
    send:   ['#F0FDF4','#10B981'], notif:  ['#FEF3C7','#D97706'],
    user:   ['#EFF6FF','#2563EB'], company:['#F5F3FF','#7C3AED'],
    info:   ['#F0F9FF','#0284C7'], period: ['#FEF3C7','#D97706'],
    report: ['#F5F3FF','#7C3AED'], pillar: ['#D1FAE5','#059669'],
    filter: ['#FFF7ED','#EA580C'], check:  ['#F0FDF4','#059669'],
    map:    ['#FEF3C7','#D97706'], edit:   ['#FFF7ED','#F59E0B'],
    status: ['#ECFDF5','#059669'], export: ['#EFF6FF','#2563EB'],
    welcome:['gradient','#fff'],
  };

  // ─── Tour Series (role → ordered pages) ────────────────────────
  const SERIES = {
    admin: [
      { page:'dashboard',   url:'/pages/dashboard.php',    label:'แดชบอร์ด',        icon:'nav',     desc:'ภาพรวมระบบและสถิติ' },
      { page:'users',       url:'/pages/users.php',        label:'ผู้ใช้งาน',       icon:'user',    desc:'จัดการบัญชีและสิทธิ์' },
      { page:'companies',   url:'/pages/companies.php',    label:'สถานประกอบการ',   icon:'company', desc:'ข้อมูลผู้เข้าร่วมทั้งหมด' },
      { page:'periods',     url:'/pages/periods.php',      label:'รอบการประเมิน',   icon:'period',  desc:'กำหนดช่วงเวลาและวันส่ง' },
      { page:'assessments', url:'/pages/assessments.php',  label:'รายการประเมิน',   icon:'form',    desc:'ติดตามและจัดการทุกรายการ' },
      { page:'reports',     url:'/pages/reports.php',      label:'รายงาน',          icon:'report',  desc:'สรุปผลและ Export ข้อมูล' },
    ],
    auditor: [
      { page:'dashboard',   url:'/pages/dashboard.php',    label:'แดชบอร์ด',        icon:'nav',     desc:'ภาพรวมและสถิติรวม' },
      { page:'assessments', url:'/pages/assessments.php',  label:'รายการประเมิน',   icon:'form',    desc:'รายการที่รอการตรวจสอบ' },
      { page:'reports',     url:'/pages/reports.php',      label:'รายงาน',          icon:'report',  desc:'ดูผลการประเมินรวม' },
    ],
    company: [
      { page:'dashboard',          url:'/pages/dashboard.php',         label:'แดชบอร์ด',     icon:'nav',    desc:'ภาพรวมและคะแนนของคุณ' },
      { page:'company-profile',    url:'/pages/company-profile.php',   label:'ข้อมูลบริษัท', icon:'company',desc:'กรอกข้อมูลสถานประกอบการ' },
      { page:'assessment-form',    url:'/pages/assessment-form.php',   label:'แบบประเมิน',   icon:'form',   desc:'ทำแบบประเมินตนเอง 60 ข้อ' },
      { page:'assessment-result',  url:'/pages/assessment-result.php', label:'ผลการประเมิน', icon:'stats',  desc:'คะแนนและระดับ HICM ของคุณ' },
    ],
  };

  // ─── Page Steps ────────────────────────────────────────────────
  const STEPS = {

    // ════════════════════════════════════
    //  DASHBOARD — Admin / Auditor / CEO
    // ════════════════════════════════════
    dashboard_admin: [
      { sel:null, icon:'welcome', pos:'center',
        title:'ยินดีต้อนรับสู่ HICM V2025 👋',
        desc: 'ระบบประเมินสถานประกอบการ Health Industrial Community Model — ขอพาทัวร์ฟีเจอร์ทุกส่วนแบบ step-by-step' },
      { sel:'#mainSidebar', icon:'nav', pos:'right',
        title:'เมนูนำทางหลัก',
        desc: 'Sidebar ด้านซ้ายเป็นทางเข้าสู่ทุกส่วนของระบบ กดปุ่ม ☰ ที่ navbar เพื่อย่อ/ขยายได้ตลอดเวลา' },
      { sel:'.sidebar-section:first-child', icon:'nav', pos:'right',
        title:'เมนูหลัก',
        desc: 'แถบเมนูหลักประกอบด้วย แดชบอร์ด รายการประเมิน และสถานประกอบการ — คลิกไอคอนแต่ละอันเพื่อนำทาง' },
      { sel:'.dashboard-stats, .stats-grid', icon:'stats', pos:'bottom',
        title:'สถิติภาพรวมระบบ',
        desc: 'แสดงจำนวนสถานประกอบการทั้งหมด รายการรอตรวจสอบ ประเมินเสร็จสิ้น และคะแนนเฉลี่ยแบบ real-time' },
      { sel:'.stat-card, .stat-item', icon:'stats', pos:'bottom',
        title:'การ์ดสถิติแต่ละรายการ',
        desc: 'ตัวเลขแสดงผลแบบ animated counter — กดเข้าไปดูรายละเอียดเพิ่มเติมได้ที่หน้ารายงาน' },
      { sel:'#levelChart, .chart-container canvas, canvas', icon:'chart', pos:'top',
        title:'กราฟวิเคราะห์ผล',
        desc: 'กราฟแสดงการกระจาย Level HICM และสถานะการประเมิน ช่วยให้เห็นภาพรวมในเชิงสถิติได้ทันที' },
      { sel:'.notification-trigger', icon:'notif', pos:'bottom',
        title:'ระบบแจ้งเตือน 🔔',
        desc: 'รับแจ้งเตือนอัตโนมัติเมื่อมีการส่งแบบประเมินใหม่ มีการเปลี่ยนสถานะ หรือมีประกาศสำคัญ' },
      { sel:'.desktop-user-section, .navbar-user-profile', icon:'user', pos:'bottom',
        title:'เมนูบัญชีผู้ใช้',
        desc: 'คลิกชื่อ/avatar ที่ navbar เพื่อเข้า โปรไฟล์ · เปลี่ยนรหัสผ่าน · ออกจากระบบ หรือเปิด Tour ซ้ำได้ที่นี่' },
    ],

    // ════════════════════════════════════
    //  DASHBOARD — Company
    // ════════════════════════════════════
    dashboard_company: [
      { sel:null, icon:'welcome', pos:'center',
        title:'ยินดีต้อนรับสู่ HICM V2025 👋',
        desc: 'Dashboard ของคุณแสดงสถานะการประเมินและคะแนนล่าสุด — มาดูทุกส่วนไปพร้อมกัน' },
      { sel:'#mainSidebar', icon:'nav', pos:'right',
        title:'เมนูนำทาง',
        desc: 'ใช้เมนูด้านซ้ายเข้าถึง ทำแบบประเมิน · ผลคะแนน · ข้อมูลบริษัท และ My Milestones ได้ทันที' },
      { sel:'.sidebar-link[data-tooltip="ทำแบบประเมิน"]', icon:'form', pos:'right',
        title:'เมนู "ทำแบบประเมิน"',
        desc: 'ลิงก์โดยตรงไปยังแบบประเมิน HICM 60 ตัวชี้วัด — สามารถบันทึกและกลับมาต่อได้ตลอด' },
      { sel:'.score-card-pro, .card.score-card-pro', icon:'stats', pos:'auto',
        title:'คะแนนการประเมินของคุณ',
        desc: 'แสดงคะแนนรวม ระดับ HICM ล่าสุด และ progress bar ของแต่ละ Pillar ในรอบปัจจุบัน' },
      { sel:'.quick-menu-card', icon:'nav', pos:'auto',
        title:'เมนูลัด Quick Access',
        desc: 'ทางลัดไปยังฟีเจอร์หลัก: ทำแบบประเมิน · ดูผลคะแนน · อัปเดตข้อมูลบริษัท — คลิกได้เลย' },
      { sel:'.notification-trigger', icon:'notif', pos:'bottom',
        title:'การแจ้งเตือน',
        desc: 'รับข่าวสารเมื่อกรรมการส่งผลการตรวจสอบกลับ หรือมีประกาศผลคะแนนรอบใหม่' },
      { sel:'.desktop-user-section, .navbar-user-profile', icon:'user', pos:'bottom',
        title:'บัญชีผู้ใช้',
        desc: 'เข้าจัดการโปรไฟล์ เปลี่ยนรหัสผ่าน หรือดู Tour นี้ซ้ำได้เสมอผ่านปุ่ม "แนะนำการใช้งาน"' },
    ],

    // ════════════════════════════════════
    //  ASSESSMENT FORM
    // ════════════════════════════════════
    'assessment-form': [
      { sel:null, icon:'welcome', pos:'center',
        title:'แบบประเมิน HICM V2025',
        desc: '60 ตัวชี้วัดใน 4 Pillars รวม 1,000 คะแนน — ระบบบันทึกอัตโนมัติทุกครั้งที่เลือกคะแนน ไม่ต้องกลัวข้อมูลหาย' },
      { sel:'#pillarScoresSummary', icon:'stats', pos:'bottom',
        title:'สรุปคะแนนรายหมวด',
        desc: 'แถบด้านบนแสดงคะแนนสะสมของทั้ง 4 Pillars แบบ real-time — กดที่ชื่อ Pillar เพื่อ jump ไปยังหมวดนั้นได้เลย' },
      { sel:'#totalScoreProgress, .progress-bar-fill', icon:'chart', pos:'bottom',
        title:'Progress Bar คะแนนรวม',
        desc: 'แสดงเปอร์เซ็นต์ความคืบหน้าของคะแนนรวมเทียบกับ 1,000 คะแนนเต็ม อัปเดตทันทีที่เลือกคะแนนใหม่' },
      { sel:'.pillar-tabs', icon:'pillar', pos:'bottom',
        title:'แท็บ 4 Pillars',
        desc: 'H1 Health Promotion (300pt) · I2 Industrial Safety & Env. (300pt) · C3 Community Engagement (200pt) · M4 Management (200pt) — คลิกสลับได้' },
      { sel:'.pillar-tab.active, .pillar-tab:first-child', icon:'pillar', pos:'bottom',
        title:'แท็บ Pillar ที่เลือก',
        desc: 'ตัวเลข "x/15 ตัวชี้วัด" แสดงว่าทำไปกี่ข้อแล้ว แท็บจะเปลี่ยนสีเมื่อครบทุกข้อในหมวด' },
      { sel:'.indicator-card, .indicator-card-pro', icon:'form', pos:'right',
        title:'การ์ดตัวชี้วัดแต่ละข้อ',
        desc: 'แต่ละข้อแสดง รหัสตัวชี้วัด · ชื่อ · เกณฑ์พิจารณา และช่องให้คะแนน — อ่านเกณฑ์ให้ครบก่อนเลือก' },
      { sel:'.indicator-criteria, .criteria-text', icon:'info', pos:'right',
        title:'เกณฑ์พิจารณา',
        desc: 'อธิบายรายละเอียดของแต่ละระดับคะแนน ควรอ่านให้ครบก่อนตัดสินใจให้คะแนน เพื่อผลการประเมินที่แม่นยำ' },
      { sel:'.score-selector, .score-option:first-of-type', icon:'score', pos:'top',
        title:'เลือกระดับคะแนน',
        desc: '0 ไม่มี · 0.25 เริ่มต้น · 0.5 บางส่วน · 0.75 ครอบคลุม · 1.0 สมบูรณ์ · N/A ไม่เข้าเกณฑ์ — บันทึกอัตโนมัติทันที' },
      { sel:'.file-upload, .file-upload-area', icon:'upload', pos:'top',
        title:'แนบไฟล์หลักฐาน 📎',
        desc: 'แนบภาพถ่าย PDF หรือเอกสารประกอบได้หลายไฟล์ต่อ 1 ตัวชี้วัด รองรับ JPG PNG PDF DOC XLSX ขนาดไม่เกิน 10 MB' },
      { sel:'button[onclick="submitAssessment()"]', icon:'send', pos:'top',
        title:'ปุ่มส่งแบบประเมิน',
        desc: 'กดเพื่อส่งแบบประเมินให้กรรมการตรวจสอบ — ตรวจสอบให้ครบทุกข้อก่อนส่ง สามารถส่งใหม่ได้ถ้ายังอยู่ในช่วงเวลา' },
    ],

    // ════════════════════════════════════
    //  ASSESSMENTS LIST
    // ════════════════════════════════════
    assessments: [
      { sel:null, icon:'report', pos:'center',
        title:'หน้ารายการแบบประเมิน',
        desc: 'ศูนย์กลางติดตามแบบประเมินทุกสถานประกอบการ — ดูสถานะ จัดการกรรมการ และเปลี่ยนสถานะได้จากหน้าเดียว' },
      { sel:'.management-hero, .report-hero, .page-hero', icon:'stats', pos:'bottom',
        title:'ส่วนหัวหน้า',
        desc: 'แสดงสถิติรวมด่วน เช่น จำนวนรายการทั้งหมด รอตรวจสอบ และประเมินเสร็จแล้ว ให้เห็นภาพรวมก่อนดูรายละเอียด' },
      { sel:'.filter-panel, .filter-section, .filter-form', icon:'filter', pos:'bottom',
        title:'ค้นหาและกรองรายการ',
        desc: 'กรองตาม ชื่อบริษัท · รอบการประเมิน · สถานะ (Draft / Submitted / Under Review / Evaluated / Completed)' },
      { sel:'.assessment-row, .assessment-list .assessment-row:first-child', icon:'form', pos:'bottom',
        title:'แถวรายการประเมิน',
        desc: 'แต่ละแถวแสดง ชื่อสถานประกอบการ · สถานะ · คะแนน · วันที่ส่ง กด View เพื่อดูรายละเอียดได้ทันที' },
      { sel:'.ar-btn.view', icon:'info', pos:'top',
        title:'ปุ่ม "ดูรายละเอียด"',
        desc: 'เปิดหน้าแบบประเมินแบบ read-only พร้อมดูคำตอบ · ไฟล์แนบ · และคะแนนที่ให้ไว้ทั้งหมด' },
      { sel:'.ar-btn.evaluate', icon:'score', pos:'top',
        title:'ปุ่ม "ประเมิน" (กรรมการ)',
        desc: 'เข้าสู่หน้าให้คะแนนประเมินโดยกรรมการ — มีเฉพาะรายการที่ได้รับมอบหมายให้ตรวจสอบเท่านั้น' },
      { sel:'.ar-btn.status', icon:'status', pos:'top',
        title:'ปุ่มเปลี่ยนสถานะ (Admin)',
        desc: 'Admin สามารถเปลี่ยนสถานะรายการได้โดยตรง พร้อม timeline แสดงขั้นตอนทั้งหมดให้เห็นชัดเจน' },
      { sel:'.ar-btn.auditor', icon:'user', pos:'top',
        title:'ปุ่มจัดการกรรมการ',
        desc: 'กำหนดหรือเปลี่ยนกรรมการผู้ตรวจสอบ รองรับทั้งเลือกด้วยตนเองและสุ่มอัตโนมัติตามความเชี่ยวชาญ' },
    ],

    // ════════════════════════════════════
    //  COMPANIES
    // ════════════════════════════════════
    companies: [
      { sel:null, icon:'company', pos:'center',
        title:'หน้าจัดการสถานประกอบการ',
        desc: 'รายชื่อและข้อมูลสถานประกอบการทั้งหมดในระบบ พร้อมสถานะการประเมินและคะแนนล่าสุดของแต่ละแห่ง' },
      { sel:'.page-hero, .management-hero', icon:'stats', pos:'bottom',
        title:'ส่วนหัวและสถิติ',
        desc: 'แสดงจำนวนสถานประกอบการทั้งหมด ยืนยันแล้ว รอยืนยัน และสัดส่วนตามขนาด' },
      { sel:'.btn-hero, button[onclick*="openCreateModal"], .btn.btn-primary', icon:'form', pos:'bottom',
        title:'ปุ่มเพิ่มสถานประกอบการ',
        desc: 'กดเพื่อเพิ่มบริษัทใหม่เข้าระบบ กรอกชื่อ ประเภทอุตสาหกรรม ขนาดองค์กร และผู้ประสานงาน' },
      { sel:'.stats-grid, .dashboard-stats', icon:'stats', pos:'bottom',
        title:'สถิติรวมด่วน',
        desc: 'ดูจำนวนแยกตามขนาดองค์กร (S/M/L) และประเภทอุตสาหกรรม ใช้วิเคราะห์ composition ของผู้เข้าร่วม' },
      { sel:'#search, input[name="search"]', icon:'filter', pos:'bottom',
        title:'ช่องค้นหา',
        desc: 'พิมพ์ชื่อบริษัทเพื่อค้นหา หรือใช้ dropdown กรองตาม ประเภทอุตสาหกรรม · ขนาด · สถานะได้พร้อมกัน' },
      { sel:'.company-row, .table tbody tr:first-child', icon:'company', pos:'bottom',
        title:'แถวข้อมูลสถานประกอบการ',
        desc: 'แสดงชื่อ · ประเภทอุตสาหกรรม · ขนาด · สถานะการประเมินล่าสุด และคะแนนรวม กดเพื่อดูรายละเอียด' },
      { sel:'.btn-view, .action-buttons .btn:first-child', icon:'info', pos:'left',
        title:'ปุ่มดูรายละเอียด',
        desc: 'เปิด popup แสดงข้อมูลครบถ้วนของสถานประกอบการ รวมถึงประวัติการประเมินทุกรอบ' },
      { sel:'.btn-edit, .action-buttons .btn:nth-child(2)', icon:'edit', pos:'left',
        title:'ปุ่มแก้ไขข้อมูล',
        desc: 'แก้ไขข้อมูลพื้นฐาน เปลี่ยนผู้ดูแล หรืออัปเดตสถานะ ข้อมูลอัปเดตทันทีในระบบ' },
    ],

    // ════════════════════════════════════
    //  USERS
    // ════════════════════════════════════
    users: [
      { sel:null, icon:'user', pos:'center',
        title:'หน้าจัดการผู้ใช้งาน',
        desc: 'เพิ่ม แก้ไข และจัดการสิทธิ์ผู้ใช้ 3 บทบาท: Admin · กรรมการ (Auditor) · สถานประกอบการ (Company)' },
      { sel:'.dashboard-stats, .report-stats', icon:'stats', pos:'bottom',
        title:'สถิติผู้ใช้งาน',
        desc: 'แสดงจำนวนผู้ใช้แยกตามบทบาท — ดูภาพรวมว่ามีกรรมการและสถานประกอบการลงทะเบียนแล้วเท่าใด' },
      { sel:'.btn.btn-plus, button[onclick*="createUserModal"]', icon:'form', pos:'bottom',
        title:'ปุ่มเพิ่มผู้ใช้ใหม่ ➕',
        desc: 'กดเพื่อสร้างบัญชีผู้ใช้ใหม่ เลือกบทบาทที่เหมาะสม กรอกชื่อ-อีเมล และตั้งรหัสผ่านเริ่มต้น' },
      { sel:'.filter-section, form[method="GET"]', icon:'filter', pos:'bottom',
        title:'ค้นหาและกรองผู้ใช้',
        desc: 'ค้นหาด้วยชื่อหรืออีเมล กรองตามบทบาท (Admin / Auditor / Company) หรือสถานะบัญชี' },
      { sel:'.table-pro, .table-pro tbody tr:first-child', icon:'user', pos:'bottom',
        title:'ตารางรายชื่อผู้ใช้',
        desc: 'แสดงชื่อ · อีเมล · บทบาท · วันสร้างบัญชี · สถานะ Active/Inactive พร้อมปุ่มจัดการในแต่ละแถว' },
      { sel:'.role-badge, .badge[class*="admin"], .badge[class*="auditor"], .badge[class*="company"]', icon:'info', pos:'top',
        title:'Badge บทบาท',
        desc: 'สีของ badge แสดงบทบาท: น้ำเงิน=Admin · เขียว=Auditor · ม่วง=Company — ช่วยระบุสิทธิ์ได้ทันที' },
    ],

    // ════════════════════════════════════
    //  PERIODS
    // ════════════════════════════════════
    periods: [
      { sel:null, icon:'period', pos:'center',
        title:'หน้าจัดการรอบการประเมิน',
        desc: 'สร้างและบริหารรอบการประเมินประจำปี กำหนดวันเริ่ม-สิ้นสุด กำหนดส่ง วันประเมิน และวันประกาศผล' },
      { sel:'.btn.btn-primary, button[onclick*="openModal(\'create\')"]', icon:'form', pos:'bottom',
        title:'ปุ่มสร้างรอบใหม่ ➕',
        desc: 'กดเพื่อสร้างรอบการประเมินใหม่ ตั้งชื่อรอบ กำหนดวันเริ่ม-วันส่ง และช่วงเวลาการตรวจสอบ' },
      { sel:'.periods-grid', icon:'period', pos:'bottom',
        title:'รายการรอบการประเมิน',
        desc: 'แสดงรอบทั้งหมดในรูปแบบ card grid — แต่ละ card แสดงชื่อรอบ ปี สถานะ และ action ที่ทำได้' },
      { sel:'.period-card, .period-card:first-child', icon:'period', pos:'bottom',
        title:'การ์ดรอบการประเมิน',
        desc: 'ดูวันเริ่ม-สิ้นสุด · กำหนดส่ง · จำนวนผู้เข้าร่วม และสถานะปัจจุบัน กด ⋯ เพื่อดู action เพิ่มเติม' },
      { sel:'.period-status-badge, .badge[class*="status"]', icon:'status', pos:'top',
        title:'สถานะรอบการประเมิน',
        desc: 'Draft → Open → Evaluating → Closed → Announced — สถานะเปลี่ยนแต่ละขั้นจะ unlock ฟีเจอร์ที่สอดคล้อง' },
      { sel:'.dropdown-toggle, .dropdown-container button', icon:'edit', pos:'left',
        title:'เมนู Actions รายรอบ',
        desc: 'กด ⋯ เพื่อดู: แก้ไขรอบ · ขยายกำหนดส่ง · เปลี่ยนสถานะ · Archive — แต่ละ action ขึ้นอยู่กับสถานะปัจจุบัน' },
      { sel:'.announce-toggle, #announceForm-1, [id^="announceForm"]', icon:'notif', pos:'top',
        title:'ประกาศผลการประเมิน',
        desc: 'เปิด toggle เพื่อประกาศผลคะแนนให้ทุกสถานประกอบการเห็น พร้อมกำหนดว่าจะแสดง Leaderboard หรือไม่' },
    ],

    // ════════════════════════════════════
    //  REPORTS
    // ════════════════════════════════════
    reports: [
      { sel:null, icon:'report', pos:'center',
        title:'หน้ารายงานสรุปผล',
        desc: 'ดูภาพรวมผลการประเมินทั้งหมด เปรียบเทียบคะแนน วิเคราะห์ Pillar และ Export รายงานในรูปแบบต่างๆ' },
      { sel:'.report-hero, .page-hero', icon:'stats', pos:'bottom',
        title:'ส่วนหัวรายงาน',
        desc: 'แสดงสถิติสรุป: จำนวนสถานประกอบการที่เสร็จสมบูรณ์ คะแนนสูงสุด ต่ำสุด และเฉลี่ยของรอบล่าสุด' },
      { sel:'.filter-section, .report-filters', icon:'filter', pos:'bottom',
        title:'กรองข้อมูลรายงาน',
        desc: 'กรองตาม รอบการประเมิน · สถานะ · ระดับ HICM หรือค้นหาชื่อสถานประกอบการ ผลกราฟจะอัปเดตทันที' },
      { sel:'#levelChart, #statusChart', icon:'chart', pos:'top',
        title:'กราฟ Level Distribution',
        desc: 'แสดงการกระจายตัวของระดับ HICM (Level 1-5) และสถานะการประเมิน — hover ที่ชิ้นกราฟเพื่อดูตัวเลข' },
      { sel:'.leaderboard-section, .leaderboard', icon:'score', pos:'top',
        title:'ตาราง Leaderboard 🏆',
        desc: 'อันดับสถานประกอบการตามคะแนนรวม พร้อมเหรียญ 🥇🥈🥉 สำหรับ 3 อันดับแรก ดูคะแนนแต่ละ Pillar ด้วย' },
      { sel:'.pillar-grid, .pillar-performance', icon:'pillar', pos:'top',
        title:'ผลคะแนนรายหมวด Pillar',
        desc: 'เปรียบเทียบคะแนนเฉลี่ยของ 4 Pillars ทั่วทั้งระบบ ช่วยระบุจุดแข็งและโอกาสพัฒนาในภาพรวม' },
      { sel:'#reportTable, .data-table', icon:'report', pos:'top',
        title:'ตารางข้อมูลรายละเอียด',
        desc: 'ตารางแสดงข้อมูลทุกสถานประกอบการ กดหัวคอลัมน์เพื่อเรียงลำดับ ค้นหาในตาราง หรือซ่อน/แสดงคอลัมน์' },
      { sel:'.btn-excel, [onclick*="exportToExcel"]', icon:'export', pos:'top',
        title:'Export เป็น Excel 📊',
        desc: 'ดาวน์โหลดข้อมูลทั้งหมดในรูปแบบ .xlsx พร้อม pivot table สำหรับนำไปวิเคราะห์เพิ่มเติมได้ทันที' },
      { sel:'.btn-pdf, [onclick*="downloadReportPDF"], [onclick*="HICM_PDF"]', icon:'export', pos:'top',
        title:'Export เป็น PDF 📄',
        desc: 'สร้างรายงาน PDF พร้อมกราฟ Radar และตาราง — เหมาะสำหรับนำเสนอหรือเก็บเป็นเอกสารอย่างเป็นทางการ' },
    ],

    // ════════════════════════════════════
    //  ASSESSMENT RESULT
    // ════════════════════════════════════
    'assessment-result': [
      { sel:null, icon:'stats', pos:'center',
        title:'ผลการประเมินของคุณ',
        desc: 'หน้านี้แสดงคะแนนรวม ระดับ HICM ที่ได้รับ และรายละเอียดคะแนนแยกตาม 4 Pillars พร้อมกราฟ Radar' },
      { sel:'.result-hero, .hero-score-zone, .score-display', icon:'stats', pos:'bottom',
        title:'คะแนนรวม HICM',
        desc: 'คะแนนสุดท้ายจาก 0-1000 คะแนน แสดงทั้งคะแนนตนเองและคะแนนกรรมการ (ถ้ามี) พร้อม progress circle' },
      { sel:'.level-seal, .hicm-level-badge, [class*="level"]', icon:'score', pos:'right',
        title:'ระดับ HICM ที่ได้รับ',
        desc: 'Level 1 Emerging → Level 2 Developing → Level 3 Performing → Level 4 Excellence → Level 5 World-Class' },
      { sel:'#resultRadarChart, #radarChart, canvas', icon:'chart', pos:'right',
        title:'กราฟ Radar Chart',
        desc: 'แสดงความสมดุลของ 4 Pillars พื้นที่ที่กว้างกว่าคือส่วนที่ทำได้ดี — ดูว่า Pillar ไหนควรพัฒนาเพิ่ม' },
      { sel:'.pillar-header, .pillar-section:first-child', icon:'pillar', pos:'bottom',
        title:'คะแนนรายหมวด Pillar',
        desc: 'ดูคะแนนและ progress bar ของแต่ละ Pillar คลิกเพื่อขยายดูตัวชี้วัดรายข้อที่ทำได้ดีและต้องพัฒนา' },
      { sel:'.table, .result-table', icon:'report', pos:'top',
        title:'ตารางคะแนนรายตัวชี้วัด',
        desc: 'ดูคะแนนที่ให้เองเทียบกับคะแนนกรรมการแต่ละข้อ พร้อมสัญลักษณ์แสดงว่าสูงกว่า/ต่ำกว่าหรือเท่ากัน' },
      { sel:'button[onclick*="downloadResultPDF"], button[onclick*="triggerExport"], button[onclick*="HICM_PDF"]', icon:'export', pos:'top',
        title:'Export ผลการประเมิน',
        desc: 'ดาวน์โหลดผลเป็น PDF สำหรับการรายงาน หรือ Excel สำหรับวิเคราะห์ต่อ — บันทึกไว้เป็นหลักฐานอย่างเป็นทางการ' },
    ],

    // ════════════════════════════════════
    //  AUDITOR EVALUATE
    // ════════════════════════════════════
    'auditor-evaluate': [
      { sel:null, icon:'score', pos:'center',
        title:'หน้าประเมินโดยกรรมการ',
        desc: 'ตรวจสอบคำตอบและหลักฐานของสถานประกอบการ ให้คะแนนแต่ละตัวชี้วัด และบันทึกความคิดเห็น' },
      { sel:'.progress-summary-strip, .progress-bar, .pillar-pills', icon:'chart', pos:'bottom',
        title:'แถบ Progress การประเมิน',
        desc: 'แสดงว่าประเมินไปแล้วกี่ข้อ แยกตาม Pillar — ควรทำให้ครบทั้ง 4 Pillars ก่อนกดส่งผล' },
      { sel:'.pillar-nav-pro, .pillar-btn-pro:first-child, .pillar-pills', icon:'pillar', pos:'bottom',
        title:'ปุ่มเลือก Pillar',
        desc: 'กดปุ่ม H1 · I2 · C3 · M4 เพื่อสลับไปยังหมวดที่ต้องการ ตัวเลขข้างๆ แสดงจำนวนที่ประเมินแล้ว' },
      { sel:'#hintToggleBtn, button[onclick*="toggleHint"]', icon:'info', pos:'bottom',
        title:'Toggle ซ่อนคะแนนบริษัท',
        desc: 'เปิด/ปิดการแสดงคะแนนที่สถานประกอบการให้ตนเอง — ช่วยให้กรรมการประเมินอย่างเป็นอิสระโดยไม่ถูกชักนำ' },
      { sel:'#criteriaToggleBtn, button[onclick*="toggleCriteria"]', icon:'info', pos:'bottom',
        title:'Toggle แสดงเกณฑ์',
        desc: 'เปิด/ปิดการแสดงคำอธิบายเกณฑ์แต่ละข้อ — เปิดไว้ขณะตรวจสอบ ปิดเมื่อคุ้นเคยแล้วเพื่อประหยัดพื้นที่' },
      { sel:'.indicator-card-pro, .indicator-card', icon:'form', pos:'right',
        title:'การ์ดตัวชี้วัด',
        desc: 'แสดงหัวข้อ · เกณฑ์ · หลักฐานที่บริษัทแนบมา และคะแนนที่บริษัทประเมินตนเอง — ตรวจสอบก่อนให้คะแนน' },
      { sel:'.company-panel-pro, .company-response', icon:'company', pos:'right',
        title:'คำตอบและหลักฐานของบริษัท',
        desc: 'ดูหลักฐานที่แนบมาในรูปแบบ thumbnail กดเพื่อดูไฟล์เต็ม ตรวจสอบว่าสอดคล้องกับเกณฑ์หรือไม่' },
      { sel:'.score-cards-pro, .score-radio, input[name^="score_"]', icon:'score', pos:'top',
        title:'ให้คะแนนประเมิน ⭐',
        desc: 'เลือกระดับคะแนน 0–1.0 จากการพิจารณาหลักฐาน — ระบบบันทึกอัตโนมัติ (auto-save) ทุกครั้งที่เลือก' },
      { sel:'.comment-area-pro, textarea[name^="comment_"]', icon:'edit', pos:'top',
        title:'ช่องความคิดเห็น',
        desc: 'บันทึกข้อสังเกต จุดที่ดี หรือข้อแนะนำสำหรับสถานประกอบการ — auto-save เมื่อเลื่อนออกจากช่อง' },
      { sel:'#btnSubmitEval, button[onclick*="submitEvaluation"], button[onclick*="openSubmitModal"]', icon:'send', pos:'top',
        title:'ปุ่มส่งผลการประเมิน',
        desc: 'กดเมื่อให้คะแนนครบทุกตัวชี้วัดแล้ว ระบบจะแสดง summary ให้ยืนยันก่อน สามารถถอนคืนได้ถ้ายังอยู่ในช่วงเวลา' },
    ],

    // ════════════════════════════════════
    //  COMPANY PROFILE
    // ════════════════════════════════════
    'company-profile': [
      { sel:null, icon:'company', pos:'center',
        title:'หน้าข้อมูลสถานประกอบการ',
        desc: 'กรอกข้อมูลพื้นฐานของสถานประกอบการให้ครบถ้วนก่อนทำแบบประเมิน ข้อมูลนี้ใช้ในรายงานอย่างเป็นทางการ' },
      { sel:'.profile-hero, .page-hero', icon:'company', pos:'bottom',
        title:'ส่วนหัวโปรไฟล์',
        desc: 'แสดงชื่อบริษัท โลโก้ และสถานะการยืนยัน — อัปโหลดโลโก้ได้ที่นี่เพื่อให้รายงานดูเป็นทางการ' },
      { sel:'.stats-row, .quick-stats', icon:'stats', pos:'bottom',
        title:'สถิติสรุปด่วน',
        desc: 'แสดงจำนวนพนักงาน รอบที่เข้าร่วม และคะแนนล่าสุด ให้เห็นภาพรวมของบริษัทก่อนแก้ไขข้อมูล' },
      { sel:'.profile-grid, .company-info-card, .info-form', icon:'form', pos:'right',
        title:'ข้อมูลทั่วไปบริษัท',
        desc: 'กรอก ชื่อบริษัท · เลขทะเบียน · จำนวนพนักงาน · ที่อยู่ · และข้อมูลผู้ประสานงาน — ข้อมูลครบจะช่วยให้รายงานสมบูรณ์' },
      { sel:'.checkbox-grid, input[name*="industry_type"]', icon:'info', pos:'right',
        title:'ประเภทอุตสาหกรรม',
        desc: 'เลือกประเภทที่ตรงกับธุรกิจของคุณ รองรับหลายประเภทพร้อมกัน ข้อมูลนี้ใช้จับคู่กรรมการที่มีความเชี่ยวชาญตรงสาขา' },
      { sel:'#company-map, .map-container', icon:'map', pos:'top',
        title:'ตำแหน่งที่ตั้งบน Map',
        desc: 'ปักหมุดที่ตั้งบริษัทบนแผนที่ กด "ใช้ตำแหน่งปัจจุบัน" หรือพิมพ์ที่อยู่ค้นหา แล้วกด Save เพื่อบันทึก' },
      { sel:'.btn-card-save, button[type="submit"]', icon:'send', pos:'top',
        title:'ปุ่มบันทึกข้อมูล',
        desc: 'แต่ละส่วนมีปุ่ม Save แยกกัน กดบันทึกหลังแก้ไขแต่ละส่วน ระบบจะแจ้งยืนยันเมื่อบันทึกสำเร็จ' },
    ],

  };

  // ─── State ─────────────────────────────────────────────────────
  let st = { active: false, steps: [], cur: 0, animating: false, page: '' };
  let dom = {};

  // ─── Utilities ─────────────────────────────────────────────────
  const pageName = () => window.location.pathname.split('/').pop().replace('.php','') || 'index';
  const role     = () => window.HICM_USER_ROLE || 'guest';
  const baseUrl  = () => (window.APP_CONFIG || {}).baseUrl || '';

  function stored()       { try { return JSON.parse(localStorage.getItem(STORAGE_KEY))||{}; } catch { return {}; } }
  function saveStore(d)   { try { localStorage.setItem(STORAGE_KEY, JSON.stringify(d)); } catch {} }
  function markSeen(p)    { const d=stored(); d[p]=true; saveStore(d); }
  function isSeen(p)      { return stored()[p]===true; }

  function findEl(selStr) {
    if (!selStr) return null;
    for (const s of selStr.split(',').map(x=>x.trim())) {
      try { const e=document.querySelector(s); if (e&&e.offsetParent!==null) return e; } catch {}
    }
    return null;
  }

  // ─── Series helpers ────────────────────────────────────────────
  function getSeries() {
    const r = role();
    if (r === 'admin' || r === 'ceo') return SERIES.admin;
    if (r === 'auditor')              return SERIES.auditor;
    if (r === 'company')              return SERIES.company;
    return [];
  }

  function getNextInSeries(currentPage) {
    const s = getSeries();
    const idx = s.findIndex(x => x.page === currentPage);
    return idx >= 0 && idx < s.length - 1 ? s[idx + 1] : null;
  }

  function getSeriesProgress(currentPage) {
    const s = getSeries();
    const idx = s.findIndex(x => x.page === currentPage);
    return idx >= 0 ? { cur: idx + 1, total: s.length, done: idx } : null;
  }

  // ─── Build steps for current page ──────────────────────────────
  function resolveSteps(page) {
    let raw;
    if (page === 'dashboard') {
      const r = role();
      raw = (r === 'company') ? STEPS.dashboard_company : STEPS.dashboard_admin;
    } else {
      raw = STEPS[page] || [];
    }
    if (!raw.length) return [];

    // Filter: only steps whose selector resolves (or no selector = always show)
    const visible = raw.filter(s => !s.sel || !!findEl(s.sel));
    if (!visible.length) return [];

    // Append finale step
    const next = getNextInSeries(page);
    visible.push({ sel: null, type: 'finale', icon: 'check', pos: 'center', next });
    return visible;
  }

  // ─── CSS ───────────────────────────────────────────────────────
  function injectCSS() {
    if (document.getElementById('hicm-tour-css-v3')) return;
    const s = document.createElement('style');
    s.id = 'hicm-tour-css-v3';
    s.textContent = `
/* ── backdrop / spotlight ─────────────────────── */
.ht-backdrop {
  position:fixed;inset:0;z-index:99988;
  pointer-events:all;background:transparent;
}
.ht-spotlight {
  position:fixed;z-index:99989;pointer-events:none;
  border-radius:10px;
  box-shadow:0 0 0 9999px rgba(10,15,30,0.70);
  outline:2px solid rgba(255,255,255,0.50);
  outline-offset:1px;
  transition:
    top    ${TRANSITION_MS}ms cubic-bezier(0.34,1.18,0.64,1),
    left   ${TRANSITION_MS}ms cubic-bezier(0.34,1.18,0.64,1),
    width  ${TRANSITION_MS}ms cubic-bezier(0.34,1.18,0.64,1),
    height ${TRANSITION_MS}ms cubic-bezier(0.34,1.18,0.64,1),
    opacity .25s ease;
}
.ht-spotlight.hidden { opacity:0!important; }
.ht-spotlight.pulse  { animation:ht-pulse 2.2s ease-in-out infinite; }
@keyframes ht-pulse {
  0%,100%{ outline-color:rgba(255,255,255,.50); }
  50%    { outline-color:rgba(255,255,255,.95); box-shadow:0 0 0 9999px rgba(10,15,30,.70),0 0 0 4px rgba(255,255,255,.18); }
}

/* ── tooltip base ─────────────────────────────── */
.ht-tip {
  position:fixed;z-index:99995;
  background:#fff;
  border-radius:18px;
  box-shadow:0 20px 60px rgba(0,0,0,.20),0 4px 16px rgba(0,0,0,.10);
  font-family:var(--font-family,'Prompt',sans-serif);
  overflow:hidden;
  pointer-events:all;
  transition:opacity .2s ease, transform .2s ease;
}
.ht-tip.hidden  { opacity:0;pointer-events:none;transform:scale(.94) translateY(8px); }
.ht-tip.visible { opacity:1;transform:scale(1) translateY(0); }

/* animated gradient top strip */
.ht-strip {
  height:4px;
  background:linear-gradient(90deg,#2563EB,#7C3AED,#10B981,#2563EB);
  background-size:300% 100%;
  animation:ht-grad 4s linear infinite;
}
@keyframes ht-grad { to{ background-position:-300% 0; } }

/* header */
.ht-head {
  display:flex;align-items:center;gap:.7rem;
  padding:.9rem 1.1rem .5rem;
}
.ht-icon {
  width:34px;height:34px;border-radius:9px;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
}
.ht-icon svg { width:17px;height:17px; }
.ht-counter {
  margin-left:auto;
  font-size:.68rem;font-weight:600;
  color:var(--gray-400,#9CA3AF);white-space:nowrap;
}

/* progress dots */
.ht-dots {
  display:flex;align-items:center;gap:5px;
  padding:0 1.1rem .6rem;
}
.ht-dot {
  width:6px;height:6px;border-radius:50%;
  background:#E5E7EB;transition:all .25s ease;
}
.ht-dot.active { background:#2563EB;width:16px;border-radius:3px; }
.ht-dot.done   { background:#93C5FD; }

/* body */
.ht-body     { padding:0 1.1rem .75rem; }
.ht-title    { font-size:.9rem;font-weight:700;color:#111827;margin-bottom:.3rem;line-height:1.35; }
.ht-desc     { font-size:.77rem;color:#6B7280;line-height:1.65; }

/* footer */
.ht-foot {
  display:flex;align-items:center;justify-content:space-between;
  padding:.7rem 1.1rem;
  border-top:1px solid #F3F4F6;background:#FAFAFA;
}
.ht-skip {
  font-size:.7rem;color:#9CA3AF;
  background:none;border:none;cursor:pointer;
  font-family:inherit;padding:.25rem 0;
  transition:color .15s;
}
.ht-skip:hover { color:#6B7280; }
.ht-nav { display:flex;gap:.45rem; }
.ht-btn {
  font-size:.76rem;font-weight:600;
  padding:.42rem .85rem;border-radius:8px;
  border:none;cursor:pointer;font-family:inherit;
  display:flex;align-items:center;gap:.3rem;
  transition:all .18s;
}
.ht-btn-prev { background:#F3F4F6;color:#374151; }
.ht-btn-prev:hover { background:#E5E7EB; }
.ht-btn-next {
  background:linear-gradient(135deg,#2563EB,#1D4ED8);color:#fff;
  box-shadow:0 2px 8px rgba(37,99,235,.30);
}
.ht-btn-next:hover { box-shadow:0 4px 14px rgba(37,99,235,.42); }
.ht-btn-fin {
  background:linear-gradient(135deg,#10B981,#059669);color:#fff;
  box-shadow:0 2px 8px rgba(16,185,129,.28);
}
.ht-btn-fin:hover { box-shadow:0 4px 14px rgba(16,185,129,.40); }

/* arrow pointers */
.ht-arrow    { position:absolute;width:0;height:0; }
.ht-arrow-t  { bottom:-8px;left:50%;transform:translateX(-50%);border-left:9px solid transparent;border-right:9px solid transparent;border-top:9px solid #fff;filter:drop-shadow(0 2px 2px rgba(0,0,0,.08)); }
.ht-arrow-b  { top:-8px;left:50%;transform:translateX(-50%);border-left:9px solid transparent;border-right:9px solid transparent;border-bottom:9px solid #fff;filter:drop-shadow(0 -2px 2px rgba(0,0,0,.06)); }
.ht-arrow-l  { right:-8px;top:50%;transform:translateY(-50%);border-top:9px solid transparent;border-bottom:9px solid transparent;border-left:9px solid #fff;filter:drop-shadow(2px 0 2px rgba(0,0,0,.08)); }
.ht-arrow-r  { left:-8px;top:50%;transform:translateY(-50%);border-top:9px solid transparent;border-bottom:9px solid transparent;border-right:9px solid #fff;filter:drop-shadow(-2px 0 2px rgba(0,0,0,.08)); }

/* ── FINALE ───────────────────────────────────── */
.ht-fin-head {
  background:linear-gradient(135deg,#1e3a8a 0%,#6d28d9 100%);
  padding:1.4rem 1.2rem 1rem;
  position:relative;overflow:hidden;
}
.ht-fin-head::before {
  content:'';position:absolute;inset:0;
  background:url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23fff' fill-opacity='.04'%3E%3Ccircle cx='20' cy='20' r='3'/%3E%3Ccircle cx='5' cy='5' r='1.5'/%3E%3Ccircle cx='35' cy='35' r='1.5'/%3E%3Ccircle cx='35' cy='5' r='1.5'/%3E%3Ccircle cx='5' cy='35' r='1.5'/%3E%3C/g%3E%3C/svg%3E");
}
.ht-fin-check {
  width:42px;height:42px;border-radius:50%;
  background:rgba(255,255,255,.18);
  display:flex;align-items:center;justify-content:center;
  margin-bottom:.75rem;
  animation:ht-check-pop .45s cubic-bezier(.34,1.56,.64,1) forwards;
}
@keyframes ht-check-pop {
  0%  { transform:scale(0);opacity:0; }
  100%{ transform:scale(1);opacity:1; }
}
.ht-fin-check svg { width:22px;height:22px;stroke:#fff;stroke-width:2.5; }
.ht-fin-title { font-size:1rem;font-weight:700;color:#fff;margin-bottom:.2rem; }
.ht-fin-sub   { font-size:.72rem;color:rgba(255,255,255,.7); }

/* series progress dots in finale */
.ht-ser-dots {
  display:flex;align-items:center;gap:6px;
  margin-top:.75rem;
}
.ht-ser-dot {
  height:4px;border-radius:2px;background:rgba(255,255,255,.25);
  transition:all .25s;
}
.ht-ser-dot.done    { background:rgba(255,255,255,.80); flex:2; }
.ht-ser-dot.current { background:#fff; flex:2; }
.ht-ser-dot.next    { flex:1; }

/* next-page card */
.ht-next-card {
  margin:1rem 1rem .25rem;
  border:1.5px solid #E0E7FF;
  border-radius:12px;
  overflow:hidden;
  transition:all .2s;
  cursor:pointer;
}
.ht-next-card:hover { border-color:#818CF8;box-shadow:0 4px 16px rgba(99,102,241,.16); }
.ht-next-card-inner {
  display:flex;align-items:center;gap:.75rem;
  padding:.8rem 1rem;
  background:linear-gradient(135deg,#F0F4FF,#EEF2FF);
}
.ht-next-icon {
  width:36px;height:36px;border-radius:9px;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
}
.ht-next-icon svg { width:17px;height:17px; }
.ht-next-info { flex:1;min-width:0; }
.ht-next-label { font-size:.65rem;color:#6366F1;font-weight:600;margin-bottom:.15rem;letter-spacing:.03em;text-transform:uppercase; }
.ht-next-name  { font-size:.87rem;font-weight:700;color:#1E1B4B; }
.ht-next-desc  { font-size:.7rem;color:#6B7280;margin-top:.1rem; }
.ht-next-arrow {
  width:28px;height:28px;border-radius:50%;flex-shrink:0;
  background:linear-gradient(135deg,#6366F1,#8B5CF6);
  display:flex;align-items:center;justify-content:center;
  transition:transform .2s;
}
.ht-next-card:hover .ht-next-arrow { transform:translateX(3px); }
.ht-next-arrow svg { width:13px;height:13px;stroke:#fff; }
.ht-next-btn {
  display:block;width:calc(100% - 2rem);margin:.75rem 1rem 0;
  padding:.6rem 1rem;
  background:linear-gradient(135deg,#4F46E5,#7C3AED);
  color:#fff;font-size:.82rem;font-weight:700;
  border:none;border-radius:10px;cursor:pointer;
  font-family:inherit;
  display:flex;align-items:center;justify-content:center;gap:.5rem;
  box-shadow:0 4px 14px rgba(99,102,241,.35);
  transition:all .2s;
}
.ht-next-btn:hover { box-shadow:0 6px 20px rgba(99,102,241,.50);transform:translateY(-1px); }
.ht-next-btn svg { width:15px;height:15px;stroke:#fff; }

.ht-fin-end {
  display:block;width:100%;
  padding:.7rem 1.1rem .85rem;
  background:none;border:none;cursor:pointer;font-family:inherit;
  font-size:.72rem;color:#9CA3AF;
  text-align:center;transition:color .15s;
}
.ht-fin-end:hover { color:#6B7280; }

/* all-done (no next page) */
.ht-fin-allDone {
  padding:1rem 1.1rem;
  text-align:center;
  font-size:.78rem;color:#374151;
  line-height:1.6;
}
.ht-fin-allDone strong { display:block;font-size:1rem;margin-bottom:.35rem;color:#1e40af; }

/* navbar tour trigger */
.tour-trigger-btn {
  display:flex;gap:.75rem;padding:.75rem 1rem;
  background:none;border:none;border-bottom:1px solid var(--gray-100,#F3F4F6);
  cursor:pointer;font-family:inherit;text-align:left;width:100%;
  align-items:center;color:var(--gray-700,#374151);
  transition:background .15s;
}
.tour-trigger-btn:hover { background:#F9FAFB; }
    `;
    document.head.appendChild(s);
  }

  // ─── Create DOM ────────────────────────────────────────────────
  function buildDOM() {
    injectCSS();
    document.getElementById('ht-backdrop')?.remove();
    document.getElementById('ht-spotlight')?.remove();
    document.getElementById('ht-tip')?.remove();

    const bd = document.createElement('div');
    bd.id = 'ht-backdrop'; bd.className = 'ht-backdrop';
    bd.addEventListener('click', e => { if (!dom.tip?.contains(e.target)) next(); });

    const sp = document.createElement('div');
    sp.id = 'ht-spotlight'; sp.className = 'ht-spotlight hidden';

    const tp = document.createElement('div');
    tp.id = 'ht-tip'; tp.className = 'ht-tip hidden';
    tp.style.width = TIP_W + 'px';

    document.body.append(bd, sp, tp);
    dom = { bd, sp, tp };
  }

  // ─── Render regular step ───────────────────────────────────────
  function renderStep(step, idx, total) {
    const ic     = step.icon || 'info';
    const col    = IC_COLOR[ic] || IC_COLOR.info;
    const isWelc = ic === 'welcome';
    const isLast = step.type === 'finale';

    const iconBg  = isWelc ? 'linear-gradient(135deg,#1e40af,#7c3aed)' : col[0];
    const iconFg  = isWelc ? '#fff' : col[1];
    const prevCnt = idx > 0 && !isLast;
    const stepsCount = total - 1; // exclude finale from counter

    const dots = Array.from({ length: Math.min(stepsCount, 10) }, (_, i) =>
      `<div class="ht-dot ${i === idx && !isLast ? 'active' : i < idx ? 'done' : ''}"></div>`
    ).join('');

    dom.tp.style.width = TIP_W + 'px';
    dom.tp.innerHTML = `
      <div class="ht-strip"></div>
      <div class="ht-head">
        <div class="ht-icon" style="background:${iconBg};color:${iconFg}">${IC[ic]||IC.info}</div>
        <span class="ht-counter">${isLast ? stepsCount+'/'+stepsCount : (idx+1)+'/'+stepsCount}</span>
      </div>
      <div class="ht-dots">${dots}</div>
      <div class="ht-body">
        <div class="ht-title">${step.title}</div>
        <div class="ht-desc">${step.desc}</div>
      </div>
      <div class="ht-foot">
        <button class="ht-skip" id="ht-skip">ข้ามทั้งหมด</button>
        <div class="ht-nav">
          ${prevCnt ? `<button class="ht-btn ht-btn-prev" id="ht-prev">← ก่อน</button>` : ''}
          <button class="ht-btn ht-btn-next" id="ht-next">ถัดไป →</button>
        </div>
      </div>`;

    dom.tp.querySelector('#ht-skip').onclick = skip;
    dom.tp.querySelector('#ht-next').onclick = next;
    dom.tp.querySelector('#ht-prev')?.addEventListener('click', prev);
  }

  // ─── Render finale step ────────────────────────────────────────
  function renderFinale(step) {
    const prog  = getSeriesProgress(st.page);
    const next  = step.next;
    const series= getSeries();

    // Series progress dots
    const serDots = prog ? series.map((_, i) => {
      const cls = i < prog.cur ? 'done' : i === prog.cur - 1 ? 'current' : 'next';
      return `<div class="ht-ser-dot ${cls}"></div>`;
    }).join('') : '';

    const nextIC  = next ? (IC_COLOR[next.icon] || IC_COLOR.info) : null;

    dom.tp.style.width = FIN_W + 'px';
    dom.tp.innerHTML = `
      <div class="ht-fin-head">
        <div class="ht-fin-check">${IC.check}</div>
        <div class="ht-fin-title">ครบทุก Step แล้ว! 🎉</div>
        <div class="ht-fin-sub">
          ${prog ? `หน้า ${prog.cur}/${prog.total} ในชุด Tour สำหรับคุณ` : 'เรียนรู้ฟีเจอร์ทุกส่วนของหน้านี้แล้ว'}
        </div>
        ${prog ? `<div class="ht-ser-dots">${serDots}</div>` : ''}
      </div>

      ${next ? `
      <div style="padding:.2rem 0 0;">
        <div class="ht-next-card" id="ht-next-card">
          <div class="ht-next-card-inner">
            <div class="ht-next-icon" style="background:${nextIC[0]};color:${nextIC[1]}">${IC[next.icon]||IC.info}</div>
            <div class="ht-next-info">
              <div class="ht-next-label">หน้าถัดไปในชุด Tour</div>
              <div class="ht-next-name">${next.label}</div>
              <div class="ht-next-desc">${next.desc}</div>
            </div>
            <div class="ht-next-arrow">${IC.arrow}</div>
          </div>
        </div>
        <button class="ht-next-btn" id="ht-next-btn">
          ${IC.arrow} ไปหน้า "${next.label}" ต่อเลย
        </button>
      </div>
      <button class="ht-fin-end" id="ht-fin-end">จบ Tour ทั้งหมด</button>
      ` : `
      <div class="ht-fin-allDone">
        <strong>🎊 เยี่ยมมาก!</strong>
        คุณเรียนรู้ระบบ HICM V2025 ครบทุกส่วนแล้ว<br>
        พร้อมใช้งานอย่างเต็มที่ได้เลย
      </div>
      <button class="ht-fin-end" id="ht-fin-end">ปิด Tour</button>
      `}`;

    // Wire events
    dom.tp.querySelector('#ht-fin-end').onclick = () => { markSeen(st.page); teardown(); };

    const cardEl = dom.tp.querySelector('#ht-next-card');
    const btnEl  = dom.tp.querySelector('#ht-next-btn');
    if (next && (cardEl || btnEl)) {
      const go = () => { markSeen(st.page); teardown(); window.location.href = baseUrl() + next.url; };
      cardEl?.addEventListener('click', go);
      btnEl?.addEventListener('click', go);
    }
  }

  // ─── Position tooltip ─────────────────────────────────────────
  function positionTip(rect, pos) {
    const W = parseInt(dom.tp.style.width) || TIP_W;
    const H = 240;
    const vw = window.innerWidth, vh = window.innerHeight;
    const mg = 14;

    dom.tp.querySelectorAll('.ht-arrow').forEach(a => a.remove());

    if (!rect || pos === 'center') {
      dom.tp.style.top  = Math.max(mg, (vh - H) / 2) + 'px';
      dom.tp.style.left = Math.max(mg, (vw - W) / 2) + 'px';
      return;
    }

    let p = pos === 'auto' ? '' : pos;
    if (!p) {
      const sB = vh - rect.bottom - PAD, sT = rect.top - PAD;
      const sR = vw - rect.right - PAD,  sL = rect.left - PAD;
      if      (sB >= H + mg) p = 'bottom';
      else if (sT >= H + mg) p = 'top';
      else if (sR >= W + mg) p = 'right';
      else if (sL >= W + mg) p = 'left';
      else                   p = 'bottom';
    }

    const cx = rect.left + rect.width  / 2;
    const cy = rect.top  + rect.height / 2;
    let top, left, arrowCls;

    if (p === 'bottom') {
      top = rect.bottom + PAD + 12; left = cx - W / 2; arrowCls = 'ht-arrow-b';
    } else if (p === 'top') {
      top = rect.top - PAD - H - 12; left = cx - W / 2; arrowCls = 'ht-arrow-t';
    } else if (p === 'right') {
      top = cy - H / 2; left = rect.right + PAD + 12; arrowCls = 'ht-arrow-r';
    } else {
      top = cy - H / 2; left = rect.left - PAD - W - 12; arrowCls = 'ht-arrow-l';
    }

    top  = Math.max(mg, Math.min(top,  vh - H - mg));
    left = Math.max(mg, Math.min(left, vw - W - mg));
    dom.tp.style.top = top + 'px'; dom.tp.style.left = left + 'px';

    const arrow = document.createElement('div');
    arrow.className = 'ht-arrow ' + arrowCls;
    if (p === 'right' || p === 'left') {
      arrow.style.top = Math.max(20, Math.min(H - 20, cy - top)) + 'px';
      arrow.style.transform = '';
    } else {
      arrow.style.left = Math.max(20, Math.min(W - 20, cx - left)) + 'px';
      arrow.style.transform = '';
    }
    dom.tp.appendChild(arrow);
  }

  // ─── Move spotlight ────────────────────────────────────────────
  function moveSpot(el) {
    if (!el) { dom.sp.classList.add('hidden'); return; }
    const r = el.getBoundingClientRect();
    Object.assign(dom.sp.style, {
      top:    (r.top    - PAD) + 'px',
      left:   (r.left   - PAD) + 'px',
      width:  (r.width  + PAD * 2) + 'px',
      height: (r.height + PAD * 2) + 'px',
    });
    dom.sp.classList.remove('hidden');
  }

  // ─── Show step N ───────────────────────────────────────────────
  function goTo(idx) {
    if (st.animating) return;
    if (idx < 0 || idx >= st.steps.length) return;
    st.animating = true; st.cur = idx;

    const step    = st.steps[idx];
    const el      = step.sel ? findEl(step.sel) : null;
    const isFinale= step.type === 'finale';

    dom.tp.classList.remove('visible'); dom.tp.classList.add('hidden');

    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });

    setTimeout(() => {
      if (el) { moveSpot(el); dom.sp.classList.remove('hidden'); }
      else    { dom.sp.classList.add('hidden'); }

      if (isFinale) renderFinale(step);
      else renderStep(step, idx, st.steps.length);

      const rect = el ? el.getBoundingClientRect() : null;
      positionTip(rect, isFinale ? 'center' : (step.pos || 'auto'));

      requestAnimationFrame(() => {
        dom.tp.classList.remove('hidden'); dom.tp.classList.add('visible');
        st.animating = false;
      });

      dom.sp.classList.remove('pulse');
      void dom.sp.offsetWidth;
      if (!isFinale && el) dom.sp.classList.add('pulse');

    }, el ? 310 : 80);
  }

  // ─── Navigation ────────────────────────────────────────────────
  function next() { if (st.cur < st.steps.length - 1) goTo(st.cur + 1); else finish(); }
  function prev() { if (st.cur > 0) goTo(st.cur - 1); }
  function skip() { finish(); }
  function finish() { markSeen(st.page); teardown(); }

  function teardown() {
    st.active = false;
    dom.bd?.remove(); dom.sp?.remove(); dom.tp?.remove();
    dom = {};
    document.removeEventListener('keydown', keyNav);
  }

  function keyNav(e) {
    if (!st.active) return;
    if (e.key === 'Escape')     { skip(); return; }
    if (e.key === 'ArrowRight') { next(); return; }
    if (e.key === 'ArrowLeft')  { prev(); }
  }

  // ─── Public: start / restart ────────────────────────────────────
  function start(force = false) {
    if (st.active && !force) return;
    const page  = pageName();
    const steps = resolveSteps(page);
    if (!steps.length) return;
    if (!force && isSeen(page)) return;
    if (st.active) teardown();

    st = { active: true, steps, cur: 0, animating: false, page };
    buildDOM();
    document.addEventListener('keydown', keyNav);
    setTimeout(() => goTo(0), 450);
  }

  function restart() { start(true); }

  // ─── Inject navbar trigger (fallback) ──────────────────────────
  function addNavTrigger() {
    const html = `
      <button type="button" class="notification-item" data-tour-trigger="true"
        style="width:100%;text-align:left;border:none;border-bottom:1px solid var(--gray-100,#F3F4F6);cursor:pointer;background:none;font-family:inherit;"
        onclick="HICMTour.restart(); this.closest('.navbar-user-dropdown')?.classList.remove('show');">
        <div class="notification-icon" style="background:linear-gradient(135deg,#EFF6FF,#DBEAFE);color:#2563EB;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/>
          </svg>
        </div>
        <div class="notification-content">
          <div class="notification-title" style="display:flex;align-items:center;gap:.4rem;">
            แนะนำการใช้งาน
            <span style="font-size:.6rem;font-weight:700;background:linear-gradient(135deg,#2563EB,#7C3AED);color:#fff;padding:.1rem .45rem;border-radius:999px;letter-spacing:.03em;">TOUR</span>
          </div>
        </div>
      </button>`;
    document.querySelectorAll('.navbar-user-dropdown').forEach(dd => {
      if (dd.querySelector('[data-tour-trigger]')) return;
      dd.querySelector('.notification-item')?.insertAdjacentHTML('beforebegin', html);
    });
  }

  // ─── Init ──────────────────────────────────────────────────────
  let _inited = false;
  function _doInit() {
    if (_inited) return; _inited = true;
    addNavTrigger();
    setTimeout(() => start(false), 850);
  }

  function init() {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', _doInit);
    else _doInit();
  }

  return { init, start, restart, skip, finish };
})();

HICMTour.init();
