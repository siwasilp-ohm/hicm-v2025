<?php
/**
 * HICM V2025 Assessment System - Login Page
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(getBaseUrl() . '/pages/dashboard.php');
}

$error = '';

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
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
    <style>
        .login-bg {
            position: fixed;
            inset: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            overflow: hidden;
        }
        
        .login-bg::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        
        .login-bg-shapes {
            position: absolute;
            inset: 0;
            overflow: hidden;
        }
        
        .shape {
            position: absolute;
            border-radius: 50%;
            opacity: 0.1;
            animation: float 20s ease-in-out infinite;
        }
        
        .shape-1 {
            width: 400px;
            height: 400px;
            background: white;
            top: -100px;
            right: -100px;
            animation-delay: 0s;
        }
        
        .shape-2 {
            width: 300px;
            height: 300px;
            background: white;
            bottom: -50px;
            left: -50px;
            animation-delay: -5s;
        }
        
        .shape-3 {
            width: 200px;
            height: 200px;
            background: white;
            top: 50%;
            left: 20%;
            animation-delay: -10s;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-30px) rotate(180deg); }
        }
        
        .login-card {
            position: relative;
            z-index: 1;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
        }
        
        .login-logo {
            background: linear-gradient(135deg, var(--primary-500), var(--primary-600));
            box-shadow: 0 10px 30px rgba(59, 130, 246, 0.3);
        }
        
        .input-group {
            position: relative;
        }
        
        .input-group input {
            padding-left: 3rem;
        }
        
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            transition: color var(--transition-fast);
        }
        
        .input-group input:focus + .input-icon {
            color: var(--primary-500);
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--primary-500), var(--primary-600));
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
            transition: all var(--transition-base);
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
        }
        
        .remember-checkbox {
            appearance: none;
            width: 1.25rem;
            height: 1.25rem;
            border: 2px solid var(--gray-300);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all var(--transition-fast);
            position: relative;
        }
        
        .remember-checkbox:checked {
            background-color: var(--primary-500);
            border-color: var(--primary-500);
        }
        
        .remember-checkbox:checked::after {
            content: '';
            position: absolute;
            left: 5px;
            top: 2px;
            width: 5px;
            height: 10px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }
        
        .demo-users {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px dashed var(--gray-300);
        }
        
        .demo-users-title {
            font-size: 0.75rem;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.75rem;
        }
        
        .demo-user-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .demo-user-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            background: var(--gray-100);
            border-radius: var(--radius-md);
            color: var(--gray-600);
            cursor: pointer;
            transition: all var(--transition-fast);
        }
        
        .demo-user-badge:hover {
            background: var(--primary-100);
            color: var(--primary-600);
        }
    </style>
</head>
<body class="login-page">
    <div class="login-bg">
        <div class="login-bg-shapes">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
            <div class="shape shape-3"></div>
        </div>
    </div>
    
    <div class="login-card animate-scale-in">
        <div class="login-header">
            <div class="login-logo animate-bounce">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 12l2 2 4-4"/>
                    <path d="M21 12c-1 0-3-1-3-3s2-3 3-3 3 1 3 3-2 3-3 3"/>
                    <path d="M3 12c1 0 3-1 3-3s-2-3-3-3-3 1-3 3 2 3 3 3"/>
                    <path d="M12 21c0-1 1-3 3-3s3 2 3 3-1 3-3 3-3-2-3-3"/>
                    <path d="M12 3c0 1-1 3-3 3s-3-2-3-3 1-3 3-3 3 2 3 3"/>
                </svg>
            </div>
            <h1 class="login-title">HICM V2025</h1>
            <p class="login-subtitle">ระบบแบบประเมินสถานประกอบการ<br>ตามเกณฑ์ HICM V2025</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger animate-fade-in" style="margin-bottom: 1.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="15" y1="9" x2="9" y2="15"/>
                            <line x1="9" y1="9" x2="15" y2="15"/>
                        </svg>
                        <?php echo $error; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label" for="loginUsername">ชื่อผู้ใช้</label>
                    <div class="input-group">
                        <input type="text" name="username" id="loginUsername" class="form-input" placeholder="กรอกชื่อผู้ใช้" required autofocus>
                        <svg class="input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">รหัสผ่าน</label>
                    <div class="input-group">
                        <input type="password" name="password" class="form-input" placeholder="กรอกรหัสผ่าน" required>
                        <svg class="input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0110 0v4"/>
                        </svg>
                    </div>
                </div>
                
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;" for="loginRemember">
                        <input type="checkbox" name="remember" id="loginRemember" class="remember-checkbox">
                        <span style="font-size: 0.875rem; color: var(--gray-600);">จดจำการเข้าสู่ระบบ</span>
                    </label>
                    <a href="forgot-password.php" style="font-size: 0.875rem;">ลืมรหัสผ่าน?</a>
                </div>
                
                <button type="submit" class="btn btn-login btn-block btn-lg" style="margin-bottom: 1.5rem;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/>
                        <polyline points="10 17 15 12 10 7"/>
                        <line x1="15" y1="12" x2="3" y2="12"/>
                    </svg>
                    เข้าสู่ระบบ
                </button>
                
                <div style="text-align: center;">
                    <p style="font-size: 0.875rem; color: var(--gray-500);">
                        ยังไม่มีบัญชี? 
                        <a href="register.php" style="font-weight: 500;">ลงทะเบียนบริษัท</a>
                    </p>
                </div>
            </form>
            
            <!-- Demo Users -->
            <div class="demo-users">
                <div class="demo-users-title">บัญชีทดลองใช้งาน (รหัสผ่าน: 123)</div>
                <div class="demo-user-list">
                    <span class="demo-user-badge" onclick="fillLogin('admin1')">admin1</span>
                    <span class="demo-user-badge" onclick="fillLogin('admin2')">admin2</span>
                    <span class="demo-user-badge" onclick="fillLogin('aud1')">aud1</span>
                    <span class="demo-user-badge" onclick="fillLogin('aud2')">aud2</span>
                    <span class="demo-user-badge" onclick="fillLogin('aud3')">aud3</span>
                    <span class="demo-user-badge" onclick="fillLogin('com1')">com1</span>
                    <span class="demo-user-badge" onclick="fillLogin('com2')">com2</span>
                    <span class="demo-user-badge" onclick="fillLogin('ceo1')">ceo1</span>
                    <span class="demo-user-badge" onclick="fillLogin('ceo2')">ceo2</span>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Add animation on load
        document.addEventListener('DOMContentLoaded', function() {
            const card = document.querySelector('.login-card');
            card.style.opacity = '0';
            card.style.transform = 'scale(0.95)';
            
            setTimeout(() => {
                card.style.transition = 'all 0.5s ease-out';
                card.style.opacity = '1';
                card.style.transform = 'scale(1)';
            }, 100);
        });
        
        // Fill login form with demo user
        function fillLogin(username) {
            document.getElementById('loginUsername').value = username;
            document.getElementById('loginPassword').value = '123';
        }
    </script>
</body>
</html>
