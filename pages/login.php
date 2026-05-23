<?php
/**
 * HICM V2025 Assessment System - Login Page
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(getBaseUrl() . '/pages/dashboard.php');
}

$error = '';
$demoAccountsEnabled = getVal('demo_accounts_enabled') == 'true';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($username) || empty($password)) {
        $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    } else {
        $result = login($username, $password, $remember);
        
        if ($result['success']) {
            $redirect = $_SESSION['redirect_url'] ?? getBaseUrl() . '/pages/dashboard.php';
            unset($_SESSION['redirect_url']);
            redirect($redirect);
        } else {
            $error = $result['message'];
        }
    }
}

// Helper function to get setting
function getVal($key, $default = '') {
    $db = getDB()->getConnection();
    $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['setting_value'] : $default;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - HICM V2025</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; }
        .bg-gradient-custom {
            background: linear-gradient(to bottom right, #f8fafc, #eff6ff, #ecfdf5);
        }
    </style>
</head>
<body class="bg-gradient-custom min-h-screen text-slate-900">
    <header class="border-b bg-white/80 backdrop-blur-sm sticky top-0 z-50">
        <div class="container mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img src="/hicm-v2025/assets/uploads/avatars/suth.png" alt="Logo" class="h-10 w-10 object-contain">
                <div>
                    <h1 class="text-xl font-bold bg-gradient-to-r from-emerald-600 to-blue-600 bg-clip-text text-transparent">HICM V2025</h1>
                    <p class="text-[10px] text-slate-500 uppercase tracking-wider">Health Industrial Community Model</p>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-10 md:py-16">
        <div class="grid lg:grid-cols-2 gap-12 items-center">
            <div class="space-y-8">
                <div>
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700 mb-4 border border-emerald-200">
                        V2025 Official Version
                    </span>
                    <h2 class="text-2xl md:text-3xl font-bold text-slate-900 mb-4 leading-tight">
                        โครงการการพัฒนาโมเดลชุมชนอุตสาหกรรมสุขภาวะเพื่อการเสริมสร้างสุขภาพคนวัยทำงานแบบบูรณาการและยั่งยืน (HICM)
                    </h2>
                    <p class="text-slate-600 leading-relaxed mb-6">
                        เครื่องมือประเมินมาตรฐานองค์กรที่มุ่งเน้นการสร้างสุขภาวะที่ดีอย่างยั่งยืนในภาคอุตสาหกรรม
                    </p>
                    
                    <div class="relative rounded-2xl overflow-hidden shadow-xl mb-8 group border bg-white">
                        <img src="/hicm-v2025/assets/uploads/avatars/master.png" alt="HICM Banner" class="w-full h-48 object-contain p-4 transition-transform duration-500 group-hover:scale-105">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/5 to-transparent"></div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-white p-4 rounded-xl border border-emerald-100 shadow-sm border-l-4 border-l-emerald-500">
                        <h4 class="font-bold text-sm text-slate-800">Health Promotion</h4>
                        <p class="text-xs text-slate-500">ส่งเสริมสุขภาพ</p>
                        <div class="mt-2 text-emerald-600 font-bold text-lg">300 <span class="text-[10px] text-slate-400 font-normal">คะแนน</span></div>
                    </div>
                    <div class="bg-white p-4 rounded-xl border border-amber-100 shadow-sm border-l-4 border-l-amber-500">
                        <h4 class="font-bold text-sm text-slate-800">Safety & Environment</h4>
                        <p class="text-xs text-slate-500">ความปลอดภัยและสิ่งแวดล้อม</p>
                        <div class="mt-2 text-amber-600 font-bold text-lg">300 <span class="text-[10px] text-slate-400 font-normal">คะแนน</span></div>
                    </div>
                    <div class="bg-white p-4 rounded-xl border border-blue-100 shadow-sm border-l-4 border-l-blue-500">
                        <h4 class="font-bold text-sm text-slate-800">Community</h4>
                        <p class="text-xs text-slate-500">การมีส่วนร่วมกับชุมชน</p>
                        <div class="mt-2 text-blue-600 font-bold text-lg">200 <span class="text-[10px] text-slate-400 font-normal">คะแนน</span></div>
                    </div>
                    <div class="bg-white p-4 rounded-xl border border-purple-100 shadow-sm border-l-4 border-l-purple-500">
                        <h4 class="font-bold text-sm text-slate-800">Management</h4>
                        <p class="text-xs text-slate-500">การบริหารจัดการและความยั่งยืน</p>
                        <div class="mt-2 text-purple-600 font-bold text-lg">200 <span class="text-[10px] text-slate-400 font-normal">คะแนน</span></div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <span class="px-3 py-1 bg-white border border-slate-200 rounded-lg text-xs font-medium text-slate-600 flex items-center gap-1.5 shadow-sm">
                        <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Real-time Scoring
                    </span>
                    <span class="px-3 py-1 bg-white border border-slate-200 rounded-lg text-xs font-medium text-slate-600 flex items-center gap-1.5 shadow-sm">
                        <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                        Dashboard Analytics
                    </span>
                </div>
            </div>

            <div class="w-full max-w-md mx-auto lg:ml-auto">
                <div class="bg-white rounded-3xl border shadow-2xl overflow-hidden border-t-8 border-t-emerald-600">
                    <div class="p-8">
                        <div class="text-center mb-10">
                           
                            <h3 class="text-2xl font-bold text-slate-900">เข้าสู่ระบบ</h3>
                            <p class="text-slate-500 text-sm mt-2">กรุณาระบุชื่อผู้ใช้และรหัสผ่าน</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="mb-6 p-4 bg-red-50 border border-red-100 text-red-600 rounded-2xl text-sm flex items-center gap-3">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" class="space-y-6">
                            <div class="space-y-2">
                                <label for="username" class="text-sm font-semibold text-slate-700 ml-1">ชื่อผู้ใช้</label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                    </span>
                                    <input type="text" id="username" name="username" required
                                        class="w-full pl-12 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all"
                                        placeholder="Username">
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label for="password" class="text-sm font-semibold text-slate-700 ml-1">รหัสผ่าน</label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                    </span>
                                    <input type="password" id="password" name="password" required
                                        class="w-full pl-12 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all"
                                        placeholder="••••••••">
                                </div>
                            </div>

                            <button type="submit" 
                                class="w-full py-4 bg-gradient-to-r from-emerald-600 to-blue-600 hover:from-emerald-700 hover:to-blue-700 text-white font-bold rounded-2xl shadow-xl shadow-emerald-200 transition-all transform hover:-translate-y-1 active:scale-95">
                                เข้าสู่ระบบการประเมิน
                            </button>
                        </form>
                    </div>

                    <div class="bg-slate-50 border-t p-8" <?php echo $demoAccountsEnabled ? '' : 'style="display: none;"'; ?>>
                        <div class="flex items-center gap-2 mb-4">
                            <span class="h-px flex-1 bg-slate-200"></span>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Demo Accounts</span>
                            <span class="h-px flex-1 bg-slate-200"></span>
                        </div>
                        
                        <div class="space-y-4">
                            <div class="flex flex-wrap gap-2 items-center">
                                <span class="text-[10px] text-slate-400 w-full mb-1">Admin:</span>
                                <button onclick="fillLogin('admin1')" class="px-3 py-1.5 bg-white border rounded-xl text-xs font-medium hover:border-emerald-500 hover:text-emerald-600 transition-all shadow-sm">admin1</button>
                                <button onclick="fillLogin('admin2')" class="px-3 py-1.5 bg-white border rounded-xl text-xs font-medium hover:border-emerald-500 hover:text-emerald-600 transition-all shadow-sm">admin2</button>
                            </div>

                            <div class="flex flex-wrap gap-2 items-center">
                                <span class="text-[10px] text-slate-400 w-full mb-1">CEO:</span>
                                <button onclick="fillLogin('ceo1')" class="px-3 py-1.5 bg-white border rounded-xl text-xs font-medium hover:border-amber-500 hover:text-amber-600 transition-all shadow-sm">ceo1</button>
                                <button onclick="fillLogin('ceo2')" class="px-3 py-1.5 bg-white border rounded-xl text-xs font-medium hover:border-amber-500 hover:text-amber-600 transition-all shadow-sm">ceo2</button>
                            </div>

                            <div class="flex flex-wrap gap-2 items-center">
                                <span class="text-[10px] text-slate-400 w-full mb-1">Auditor:</span>
                                <button onclick="fillLogin('aud1')" class="px-3 py-1.5 bg-white border rounded-xl text-xs font-medium hover:border-blue-500 hover:text-blue-600 transition-all shadow-sm">aud1</button>
                                <button onclick="fillLogin('aud2')" class="px-3 py-1.5 bg-white border rounded-xl text-xs font-medium hover:border-blue-500 hover:text-blue-600 transition-all shadow-sm">aud2</button>
                                <button onclick="fillLogin('aud3')" class="px-3 py-1.5 bg-white border rounded-xl text-xs font-medium hover:border-blue-500 hover:text-blue-600 transition-all shadow-sm">aud3</button>
                            </div>

                            <div class="flex flex-wrap gap-2 items-center">
                                <span class="text-[10px] text-slate-400 w-full mb-1">Company:</span>
                                <button onclick="fillLogin('com1')" class="px-3 py-1.5 bg-white border rounded-xl text-xs font-medium hover:border-purple-500 hover:text-purple-600 transition-all shadow-sm">com1</button>
                                <button onclick="fillLogin('com2')" class="px-3 py-1.5 bg-white border rounded-xl text-xs font-medium hover:border-purple-500 hover:text-purple-600 transition-all shadow-sm">com2</button>
                                <button onclick="fillLogin('com3')" class="px-3 py-1.5 bg-white border rounded-xl text-xs font-medium hover:border-purple-500 hover:text-purple-600 transition-all shadow-sm">com3</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function fillLogin(user) {
            document.getElementById('username').value = user;
            document.getElementById('password').value = '123';
            
            // Highlight effect
            const inputs = document.querySelectorAll('input');
            inputs.forEach(i => {
                i.classList.add('ring-4', 'ring-emerald-500/20');
                setTimeout(() => i.classList.remove('ring-4', 'ring-emerald-500/20'), 500);
            });
        }
    </script>
</body>
</html>