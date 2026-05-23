<?php
/**
 * HICM V2025 Assessment System - Change Password
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();

$pageTitle = 'เปลี่ยนรหัสผ่าน';
$user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validation
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        setFlashMessage('กรุณากรอกข้อมูลให้ครบถ้วน', 'error');
    } elseif ($newPassword !== $confirmPassword) {
        setFlashMessage('รหัสผ่านใหม่และการยืนยันรหัสผ่านไม่ตรงกัน', 'error');
    } elseif (strlen($newPassword) < 6) { // Basic length check
        setFlashMessage('รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร', 'error');
    } else {
        // Attempt change
        $result = changePassword($user['id'], $currentPassword, $newPassword);
        
        if ($result['success']) {
            setFlashMessage('เปลี่ยนรหัสผ่านเรียบร้อยแล้ว กรุณาเข้าสู่ระบบใหม่', 'success');
            // Optional: Logout user to force relogin or just keep them logged in
            // For security, often good to keep them in but notify.
            // Let's redirect to dashboard.
        } else {
            setFlashMessage($result['message'], 'error');
        }
    }
    
    redirect(getBaseUrl() . '/pages/change-password.php');
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .report-header {
            background: linear-gradient(135deg, var(--primary-600), var(--primary-800));
            color: white;
            padding: 2.5rem 2rem;
            border-radius: var(--radius-xl);
            margin-bottom: 2.5rem;
            position: relative;
            overflow: hidden;
        }
        
        .report-header::after {
            content: "\f023";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            top: 50%;
            right: 5%;
            transform: translateY(-50%);
            font-size: 8rem;
            opacity: 0.1;
            pointer-events: none;
        }
        
        .report-title {
            font-size: 1.85rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }
        
        .report-meta {
            opacity: 0.9;
            font-size: 1rem;
            position: relative;
            z-index: 1;
        }

        .password-container {
            max-width: 550px;
            margin: 0 auto;
        }

        .card {
            background: white;
            border-radius: var(--radius-xl);
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .card-header-pro {
            padding: 1.5rem 2rem;
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
        }

        .card-header-pro h3 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 600;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card-body {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--gray-700);
            font-size: 0.9rem;
        }

        .input-group-pro {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-group-pro i {
            position: absolute;
            left: 1rem;
            color: var(--gray-400);
            font-size: 1rem;
        }

        .input-group-pro .form-control {
            padding-left: 3rem;
            height: 48px;
            border-radius: 10px;
            border: 1px solid var(--gray-300);
            transition: all 0.2s ease;
        }

        .input-group-pro .form-control:focus {
            border-color: var(--primary-500);
            box-shadow: 0 0 0 4px var(--primary-50);
        }

        .password-requirement {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
            color: var(--gray-500);
            margin-top: 0.5rem;
        }

        .btn-submit {
            height: 50px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            background: linear-gradient(135deg, var(--primary-600), var(--primary-700));
            border: none;
            box-shadow: 0 4px 15px rgba(var(--primary-rgb), 0.3);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(var(--primary-rgb), 0.4);
            filter: brightness(1.1);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        hr {
            border: 0;
            border-top: 1px solid var(--gray-200);
            margin: 2rem 0;
        }

        .back-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
            color: var(--gray-500);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: var(--primary-600);
        }
    </style>
</head>
<body class="has-sidebar">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="sidebar-overlay"></div>
    
    <main class="main-wrapper">
        <div class="main-content">
            <?php echo getFlashMessage(); ?>
            
            <!-- Report Header style -->
            <div class="report-header">
                <h1 class="report-title"><?php echo $pageTitle; ?></h1>
                <p class="report-meta">เปลี่ยนรหัสผ่านเพื่อความปลอดภัยของบัญชีผู้ใช้งาน</p>
            </div>
            
            <div class="password-container">
                <div class="card">
                    <div class="card-header-pro">
                        <h3><i class="fas fa-shield-alt text-primary"></i> ตั้งค่ารหัสผ่านใหม่</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-group">
                                <label>รหัสผ่านปัจจุบัน</label>
                                <div class="input-group-pro">
                                    <i class="fas fa-lock"></i>
                                    <input type="password" name="current_password" class="form-control" placeholder="กรอกรหัสผ่านเดิม" required>
                                </div>
                            </div>

                            <hr>

                            <div class="form-group">
                                <label>รหัสผ่านใหม่</label>
                                <div class="input-group-pro">
                                    <i class="fas fa-key"></i>
                                    <input type="password" name="new_password" class="form-control" placeholder="กรอกรหัสผ่านใหม่" required minlength="6">
                                </div>
                                <div class="password-requirement">
                                    <i class="fas fa-info-circle"></i>
                                    <span>ความยาวอย่างน้อย 6 ตัวอักษร</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>ยืนยันรหัสผ่านใหม่</label>
                                <div class="input-group-pro">
                                    <i class="fas fa-check-circle"></i>
                                    <input type="password" name="confirm_password" class="form-control" placeholder="กรอกรหัสผ่านใหม่อีกครั้ง" required minlength="6">
                                </div>
                            </div>
                            
                            <div class="mt-5">
                                <button type="submit" class="btn btn-primary btn-block btn-submit">
                                    <i class="fas fa-save mr-2"></i> บันทึกการเปลี่ยนแปลง
                                </button>
                            </div>
                        </form>

                        <a href="<?php echo getBaseUrl(); ?>/pages/dashboard.php" class="back-link">
                            <i class="fas fa-arrow-left"></i> กลับไปหน้าภาพรวมระบบ
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <script src="<?php echo getBaseUrl(); ?>/assets/js/main.js"></script>
</body>
</html>
