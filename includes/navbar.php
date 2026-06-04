<?php
/**
 * HICM V2025 Assessment System - Navigation Bar
 */

$user = getCurrentUser();
$isAdmin = hasRole(ROLE_ADMIN);
$isAuditor = hasRole(ROLE_AUDITOR);
$isCompany = hasRole(ROLE_COMPANY);
$isCEO = hasRole('ceo');
?>
<!-- Skip to main content link for accessibility -->
<a href="#main-content" class="skip-to-main">ข้ามไปยังเนื้อหาหลัก</a>

<script>
    window.APP_CONFIG = {
        baseUrl: '<?php echo getBaseUrl(); ?>',
        apiUrl: '<?php echo getBaseUrl(); ?>/api'
    };
    window.HICM_USER_ROLE = '<?php echo addslashes($user['role'] ?? 'guest'); ?>';
</script>
<script src="<?php echo getBaseUrl(); ?>/assets/js/tour.js" defer></script>

<style>
/* HICM Navbar Brand Styles */
.hicm-brand-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.35rem;
    text-decoration: none;
    text-align: center;
}

.hicm-brand-logo {
    height: 50px;
    width: auto;
    object-fit: contain;
}

.hicm-brand-title {
    font-size: 0.6rem;
    font-weight: 600;
    color: var(--gray-600);
    line-height: 1.3;
    max-width: 500px;
    text-align: center;
}

/* User Profile in Navbar */
.navbar-user-profile {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    padding: 0.35rem 0.75rem;
    border-radius: var(--radius-lg);
    transition: background 0.2s;
    position: relative;
    margin-left: 0.5rem;
}

.navbar-user-profile:hover {
    background: var(--gray-100);
}

.navbar-user-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--primary-100);
    color: var(--primary-600);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.9rem;
    flex-shrink: 0;
    overflow: hidden;
}

.navbar-user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.navbar-user-info {
    display: flex;
    flex-direction: column;
    line-height: 1.3;
}

.navbar-user-name {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--gray-800);
}

.navbar-user-role {
    font-size: 0.7rem;
    color: var(--gray-500);
    font-weight: 500;
}

/* User Dropdown */
.navbar-user-dropdown {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    min-width: 200px;
    background: white;
    border-radius: var(--radius-lg);
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    z-index: 99999;
    overflow: hidden;
    display: none;
}

.navbar-user-dropdown.show {
    display: block;
}

.navbar-user-dropdown-header {
    padding: 1rem;
    background: var(--gray-50);
    border-bottom: 1px solid var(--gray-200);
}

.navbar-user-dropdown-header .name {
    font-weight: 600;
    color: var(--gray-800);
    font-size: 0.9rem;
}

.navbar-user-dropdown-header .role {
    font-size: 0.75rem;
    color: var(--primary-600);
    font-weight: 500;
}

/* Desktop: Show user info, hide on mobile */
@media (min-width: 769px) {
    .navbar-user-info {
        display: flex;
    }
    .navbar-user-dropdown-header {
        display: none;
    }
    .desktop-user-section {
        display: flex;
    }
    .mobile-user-section {
        display: none !important;
    }
}

@media (max-width: 768px) {
    .hicm-brand-logo {
        height: 40px;
    }
    .hicm-brand-title {
        font-size: 0.5rem;
        max-width: 300px;
    }
    .navbar-user-info {
        display: none;
    }
    .navbar-user-dropdown-header {
        display: block;
    }
    .navbar-user-profile {
        margin-left: 0;
        padding: 0.25rem;
    }
    .desktop-user-section {
        display: none !important;
    }
    .mobile-user-section {
        display: flex !important;
    }
}

@media (max-width: 480px) {
    .hicm-brand-logo {
        height: 32px;
    }
    .hicm-brand-title {
        display: none;
    }
}

/* Menu Toggle Button - works for both desktop collapse and mobile slide */
.menu-toggle {
    display: flex !important;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius-lg);
    background: white;
    cursor: pointer;
    transition: all 0.2s ease;
    color: var(--gray-600);
    padding: 0;
}

.menu-toggle:hover {
    background: var(--primary-50);
    border-color: var(--primary-300);
    color: var(--primary-600);
}

.menu-toggle:active {
    transform: scale(0.95);
}

.menu-toggle svg {
    width: 20px;
    height: 20px;
}
</style>

<?php
$roleLabels = ['admin' => 'ผู้ดูแลระบบ', 'auditor' => 'กรรมการ', 'company' => 'บริษัท', 'ceo' => 'CEO'];
$userRoleLabel = $roleLabels[$user['role']] ?? $user['role'];
?>

<nav class="navbar">
    <div class="navbar-container" style="justify-content: space-between; position: relative;">
        <!-- Left: Menu Toggle + User Profile (Desktop) -->
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <!-- Menu Toggle (Desktop: collapse sidebar, Mobile: slide sidebar) -->
            <button class="menu-toggle" id="menuToggle" title="ยุบ/ขยายเมนู">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="3" y1="12" x2="21" y2="12"/>
                    <line x1="3" y1="6" x2="21" y2="6"/>
                    <line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
            </button>
            
            <!-- User Profile (Desktop Only) -->
            <div class="desktop-user-section navbar-user-profile" onclick="toggleUserDropdown(event)">
                <div class="navbar-user-avatar">
                    <?php
                    $avatar = $user['avatar'] ?? '';
                    $hasAvatar = !empty($avatar) && $avatar !== 'default' &&
                                 (in_array($avatar, AVATAR_PRESETS) || file_exists(APP_UPLOAD_PATH . 'avatars/' . $avatar));
                    if ($hasAvatar) {
                        echo '<img src="' . getAvatarUrl($avatar) . '" alt="Avatar">';
                    } else {
                        echo mb_substr($user['name'], 0, 1, 'UTF-8');
                    }
                    ?>
                </div>
                <div class="navbar-user-info">
                    <span class="navbar-user-name"><?php echo htmlspecialchars($user['name']); ?></span>
                    <span class="navbar-user-role"><?php echo $userRoleLabel; ?></span>
                </div>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--gray-400)" stroke-width="2">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
                
                <!-- User Dropdown Menu -->
                <div class="navbar-user-dropdown" id="userDropdownDesktop">
                    <!-- Tour trigger -->
                    <button type="button" class="notification-item" data-tour-trigger="true"
                        style="width:100%;text-align:left;border:none;border-bottom:1px solid var(--gray-100);cursor:pointer;background:none;font-family:inherit;"
                        onclick="HICMTour.restart(); document.getElementById('userDropdownDesktop').classList.remove('show');">
                        <div class="notification-icon" style="background:linear-gradient(135deg,#EFF6FF,#DBEAFE);color:#2563EB;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <path d="M12 8v4l3 3"/>
                            </svg>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title" style="display:flex;align-items:center;gap:0.4rem;">
                                แนะนำการใช้งาน
                                <span style="font-size:0.6rem;font-weight:700;background:linear-gradient(135deg,#2563EB,#7C3AED);color:#fff;padding:0.1rem 0.45rem;border-radius:999px;letter-spacing:0.03em;">TOUR</span>
                            </div>
                        </div>
                    </button>
                    <a href="<?php echo getBaseUrl(); ?>/pages/profile.php" class="notification-item" style="text-decoration: none;">
                        <div class="notification-icon" style="background: var(--primary-100); color: var(--primary-600);">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title">โปรไฟล์</div>
                        </div>
                    </a>
                    <a href="<?php echo getBaseUrl(); ?>/pages/change-password.php" class="notification-item" style="text-decoration: none;">
                        <div class="notification-icon" style="background: var(--warning-100); color: var(--warning-600);">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                <path d="M7 11V7a5 5 0 0110 0v4"/>
                            </svg>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title">เปลี่ยนรหัสผ่าน</div>
                        </div>
                    </a>
                    <a href="<?php echo getBaseUrl(); ?>/pages/logout.php" class="notification-item" style="text-decoration: none; border-top: 1px solid var(--gray-200);">
                        <div class="notification-icon" style="background: var(--danger-100, #fee2e2); color: var(--danger, #ef4444);">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
                                <polyline points="16 17 21 12 16 7"/>
                                <line x1="21" y1="12" x2="9" y2="12"/>
                            </svg>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title" style="color: var(--danger, #ef4444);">ออกจากระบบ</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Center: Brand Logo + Title -->
        <a href="<?php echo getBaseUrl(); ?>/pages/dashboard.php" class="navbar-brand hicm-brand-wrapper" style="position: absolute; left: 50%; transform: translateX(-50%);">
            <img src="<?php echo getBaseUrl(); ?>/assets/icon/master.png" alt="HICM" class="hicm-brand-logo">
            <span class="hicm-brand-title">โครงการการพัฒนาโมเดลชุมชนอุตสาหกรรมสุขภาวะเพื่อการเสริมสร้างสุขภาพคนวัยทำงานแบบบูรณาการและยั่งยืน (HICM)</span>
        </a>
        
        <!-- Right: Notifications + User Profile (Mobile) -->
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <!-- Notifications -->
            <?php
            require_once __DIR__ . '/notification.php';
            $unreadCount = getUnreadCount($user['id']);
            $recentNotifications = getUnreadNotifications($user['id'], 10);
            $thMonthsShort = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
            function formatNotifFullTime($dateStr, $thMonths) {
                $ts = strtotime($dateStr);
                return date('j', $ts) . ' ' . $thMonths[(int)date('n',$ts)] . ' ' . ((int)date('Y',$ts)+543) . ', ' . date('H:i', $ts) . ' น.';
            }
            function getNotifTypeIcon($type) {
                $icons = [
                    'info'    => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
                    'success' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
                    'warning' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
                    'error'   => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
                ];
                return $icons[$type] ?? $icons['info'];
            }
            ?>
            <div class="notification-menu" style="position: relative;">
                <button class="btn btn-icon notification-trigger" style="position: relative;" onclick="toggleNotificationDropdown(event)" title="การแจ้งเตือน">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 01-3.46 0"/>
                    </svg>
                    <?php if ($unreadCount > 0): ?>
                    <span class="notification-badge"><?php echo $unreadCount > 9 ? '9+' : $unreadCount; ?></span>
                    <?php endif; ?>
                </button>

                <div class="notif-panel" id="notificationDropdown">
                    <!-- Header -->
                    <div class="notif-panel-header">
                        <div class="notif-panel-title">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="color:var(--primary-600)">
                                <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                                <path d="M13.73 21a2 2 0 01-3.46 0"/>
                            </svg>
                            <span>การแจ้งเตือน</span>
                            <span class="notif-unread-chip" id="notifUnreadChip" style="<?php echo $unreadCount > 0 ? '' : 'display:none'; ?>"><?php echo $unreadCount; ?></span>
                        </div>
                        <div class="notif-header-actions">
                            <button class="notif-action-btn" onclick="markAllRead()" id="markAllReadBtn" title="ทำเครื่องหมายอ่านทั้งหมด" style="<?php echo $unreadCount > 0 ? '' : 'display:none'; ?>">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                อ่านทั้งหมด
                            </button>
                            <button class="notif-action-btn notif-danger-btn" onclick="clearAllNotifications()" title="ล้างการแจ้งเตือนทั้งหมด">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                                ล้างทั้งหมด
                            </button>
                        </div>
                    </div>

                    <!-- Notification List -->
                    <div class="notif-list" id="notificationList">
                        <?php if (empty($recentNotifications)): ?>
                        <div class="notif-empty">
                            <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="var(--gray-300)" stroke-width="1.5">
                                <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                                <path d="M13.73 21a2 2 0 01-3.46 0"/>
                            </svg>
                            <p>ไม่มีการแจ้งเตือน</p>
                            <span>ระบบจะแจ้งเตือนเมื่อมีกิจกรรมใหม่</span>
                        </div>
                        <?php else: ?>
                        <?php foreach ($recentNotifications as $notif):
                            $type = $notif['type'] ?? 'info';
                            $isUnread = !$notif['is_read'];
                            $fullTime = formatNotifFullTime($notif['created_at'], $thMonthsShort);
                        ?>
                        <div class="notif-item<?php echo $isUnread ? ' unread' : ''; ?>" data-id="<?php echo $notif['id']; ?>">
                            <div class="notif-type-bar type-<?php echo htmlspecialchars($type); ?>"></div>
                            <div class="notif-icon-wrap type-<?php echo htmlspecialchars($type); ?>">
                                <?php echo getNotifTypeIcon($type); ?>
                            </div>
                            <a href="<?php echo htmlspecialchars($notif['link'] ?? '#'); ?>" class="notif-item-body" onclick="handleNotifClick(<?php echo $notif['id']; ?>, event)">
                                <div class="notif-item-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                                <?php if (!empty($notif['message'])): ?>
                                <div class="notif-item-message"><?php echo htmlspecialchars(mb_substr($notif['message'], 0, 80, 'UTF-8')); ?><?php echo mb_strlen($notif['message'], 'UTF-8') > 80 ? '...' : ''; ?></div>
                                <?php endif; ?>
                                <div class="notif-item-meta">
                                    <?php if ($isUnread): ?><span class="notif-unread-dot"></span><?php endif; ?>
                                    <span class="notif-full-time"><?php echo $fullTime; ?></span>
                                </div>
                            </a>
                            <div class="notif-item-actions">
                                <?php if ($isUnread): ?>
                                <button class="notif-action-icon read-btn" onclick="markAsReadUI(<?php echo $notif['id']; ?>, event)" title="ทำเครื่องหมายว่าอ่านแล้ว">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                </button>
                                <?php endif; ?>
                                <button class="notif-action-icon dismiss-btn" onclick="dismissNotificationUI(<?php echo $notif['id']; ?>, event)" title="ปิดการแจ้งเตือนนี้">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Footer -->
                    <div class="notif-panel-footer">
                        <span class="notif-footer-count" id="notifFooterCount">
                            <?php
                            $total = count($recentNotifications);
                            echo $total > 0 ? "{$total} รายการ" : '';
                            ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- User Profile (Mobile Only) -->
            <div class="mobile-user-section navbar-user-profile" style="display: none;" onclick="toggleUserDropdownMobile(event)">
                <div class="navbar-user-avatar">
                    <?php
                    $avatar = $user['avatar'] ?? '';
                    $hasAvatar = !empty($avatar) && $avatar !== 'default' &&
                                 (in_array($avatar, AVATAR_PRESETS) || file_exists(APP_UPLOAD_PATH . 'avatars/' . $avatar));
                    if ($hasAvatar) {
                        echo '<img src="' . getAvatarUrl($avatar) . '" alt="Avatar">';
                    } else {
                        echo mb_substr($user['name'], 0, 1, 'UTF-8');
                    }
                    ?>
                </div>

                <!-- Mobile User Dropdown Menu -->
                <div class="navbar-user-dropdown" id="userDropdownMobile" style="left: auto; right: 0;">
                    <div class="navbar-user-dropdown-header">
                        <div class="name"><?php echo htmlspecialchars($user['name']); ?></div>
                        <div class="role"><?php echo $userRoleLabel; ?></div>
                    </div>
                    <!-- Tour trigger (mobile) -->
                    <button type="button" class="notification-item" data-tour-trigger="true"
                        style="width:100%;text-align:left;border:none;border-bottom:1px solid var(--gray-100);cursor:pointer;background:none;font-family:inherit;"
                        onclick="HICMTour.restart(); document.getElementById('userDropdownMobile').classList.remove('show');">
                        <div class="notification-icon" style="background:linear-gradient(135deg,#EFF6FF,#DBEAFE);color:#2563EB;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <path d="M12 8v4l3 3"/>
                            </svg>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title" style="display:flex;align-items:center;gap:0.4rem;">
                                แนะนำการใช้งาน
                                <span style="font-size:0.6rem;font-weight:700;background:linear-gradient(135deg,#2563EB,#7C3AED);color:#fff;padding:0.1rem 0.45rem;border-radius:999px;letter-spacing:0.03em;">TOUR</span>
                            </div>
                        </div>
                    </button>
                    <a href="<?php echo getBaseUrl(); ?>/pages/profile.php" class="notification-item" style="text-decoration: none;">
                        <div class="notification-icon" style="background: var(--primary-100); color: var(--primary-600);">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title">โปรไฟล์</div>
                        </div>
                    </a>
                    <a href="<?php echo getBaseUrl(); ?>/pages/change-password.php" class="notification-item" style="text-decoration: none;">
                        <div class="notification-icon" style="background: var(--warning-100); color: var(--warning-600);">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                <path d="M7 11V7a5 5 0 0110 0v4"/>
                            </svg>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title">เปลี่ยนรหัสผ่าน</div>
                        </div>
                    </a>
                    <a href="<?php echo getBaseUrl(); ?>/pages/logout.php" class="notification-item" style="text-decoration: none; border-top: 1px solid var(--gray-200);">
                        <div class="notification-icon" style="background: var(--danger-100, #fee2e2); color: var(--danger, #ef4444);">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
                                <polyline points="16 17 21 12 16 7"/>
                                <line x1="21" y1="12" x2="9" y2="12"/>
                            </svg>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title" style="color: var(--danger, #ef4444);">ออกจากระบบ</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>

<style>
/* ===== Notification Panel Pro ===== */

.notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    min-width: 18px;
    height: 18px;
    background: var(--danger, #ef4444);
    color: #fff;
    font-size: 0.62rem;
    font-weight: 700;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
    border: 2px solid #fff;
    animation: badge-pulse 2.5s ease-in-out infinite;
    pointer-events: none;
}
@keyframes badge-pulse {
    0%,100% { transform: scale(1); }
    50%      { transform: scale(1.15); }
}

/* Panel container */
.notif-panel {
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    width: 380px;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 24px 64px rgba(0,0,0,0.14), 0 4px 16px rgba(0,0,0,0.07);
    border: 1px solid rgba(0,0,0,0.06);
    z-index: 9999;
    overflow: hidden;
    display: none;
    transform-origin: top right;
}
.notif-panel.show {
    display: block;
    animation: notif-panel-in 0.22s cubic-bezier(0.34,1.56,0.64,1);
}
@keyframes notif-panel-in {
    from { opacity: 0; transform: scale(0.94) translateY(-8px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
}

/* Header */
.notif-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.875rem 1.125rem 0.75rem;
    border-bottom: 1px solid var(--gray-100);
    background: #fff;
    gap: 0.5rem;
}
.notif-panel-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    font-size: 0.9rem;
    color: var(--gray-900, #111827);
    flex-shrink: 0;
}
.notif-unread-chip {
    background: var(--danger, #ef4444);
    color: #fff;
    font-size: 0.62rem;
    font-weight: 700;
    padding: 0.1rem 0.45rem;
    border-radius: 999px;
    min-width: 18px;
    text-align: center;
    line-height: 1.5;
}
.notif-header-actions {
    display: flex;
    align-items: center;
    gap: 0.2rem;
    flex-shrink: 0;
}
.notif-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.72rem;
    font-weight: 500;
    color: var(--primary-600);
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.3rem 0.55rem;
    border-radius: 7px;
    transition: background 0.15s, color 0.15s;
    white-space: nowrap;
    font-family: inherit;
    line-height: 1;
}
.notif-action-btn:hover {
    background: var(--primary-50);
    color: var(--primary-700);
}
.notif-danger-btn {
    color: var(--danger, #ef4444);
}
.notif-danger-btn:hover {
    background: #fee2e2;
    color: #dc2626;
}

/* List */
.notif-list {
    max-height: 380px;
    overflow-y: auto;
    scroll-behavior: smooth;
}
.notif-list::-webkit-scrollbar { width: 4px; }
.notif-list::-webkit-scrollbar-track { background: transparent; }
.notif-list::-webkit-scrollbar-thumb { background: var(--gray-200); border-radius: 2px; }

/* Empty */
.notif-empty {
    padding: 2.5rem 1.5rem 2rem;
    text-align: center;
    color: var(--gray-400);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}
.notif-empty p {
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--gray-500);
    margin: 0.25rem 0 0;
}
.notif-empty span {
    font-size: 0.75rem;
    color: var(--gray-400);
}

/* Item */
.notif-item {
    display: flex;
    align-items: stretch;
    background: #fff;
    border-bottom: 1px solid var(--gray-50);
    transition: background 0.15s;
    position: relative;
    overflow: hidden;
}
.notif-item:last-child { border-bottom: none; }
.notif-item:hover { background: var(--gray-50); }
.notif-item.unread { background: #f0f7ff; }
.notif-item.unread:hover { background: #e5f2ff; }

/* Slide-out animation */
.notif-item.dismissing {
    animation: notif-dismiss 0.25s ease forwards;
    overflow: hidden;
}
@keyframes notif-dismiss {
    to { opacity: 0; transform: translateX(30px); max-height: 0; padding-top: 0; padding-bottom: 0; border: none; }
}

/* Type bar */
.notif-type-bar {
    width: 3px;
    flex-shrink: 0;
    border-radius: 0 2px 2px 0;
}
.notif-type-bar.type-info    { background: var(--primary-500, #3b82f6); }
.notif-type-bar.type-success { background: var(--success-500, #22c55e); }
.notif-type-bar.type-warning { background: var(--warning-500, #f59e0b); }
.notif-type-bar.type-error   { background: var(--danger, #ef4444); }

/* Icon */
.notif-icon-wrap {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin: 0.8rem 0 0.8rem 0.7rem;
}
.notif-icon-wrap.type-info    { background: var(--primary-100); color: var(--primary-600); }
.notif-icon-wrap.type-success { background: #dcfce7; color: #16a34a; }
.notif-icon-wrap.type-warning { background: var(--warning-100); color: var(--warning-600); }
.notif-icon-wrap.type-error   { background: #fee2e2; color: #ef4444; }

/* Body link */
.notif-item-body {
    flex: 1;
    min-width: 0;
    text-decoration: none;
    color: inherit;
    padding: 0.7rem 0.5rem 0.7rem 0.65rem;
    display: block;
    cursor: pointer;
}
.notif-item-title {
    font-size: 0.82rem;
    font-weight: 500;
    color: var(--gray-700);
    line-height: 1.35;
    margin-bottom: 3px;
    word-break: break-word;
}
.notif-item.unread .notif-item-title {
    font-weight: 650;
    color: var(--gray-900, #111827);
}
.notif-item-message {
    font-size: 0.74rem;
    color: var(--gray-500);
    line-height: 1.45;
    margin-bottom: 5px;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}
.notif-item-meta {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.notif-unread-dot {
    width: 6px;
    height: 6px;
    background: var(--primary-500, #3b82f6);
    border-radius: 50%;
    flex-shrink: 0;
}
.notif-full-time {
    font-size: 0.68rem;
    color: var(--gray-400);
    font-weight: 500;
}

/* Action buttons (read ✓ + dismiss ×) */
.notif-item-actions {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 0 0.6rem;
    opacity: 0;
    transition: opacity 0.15s;
    flex-shrink: 0;
}
.notif-item:hover .notif-item-actions { opacity: 1; }
.notif-action-icon {
    width: 26px;
    height: 26px;
    border-radius: 7px;
    border: none;
    background: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gray-400);
    padding: 0;
    transition: background 0.15s, color 0.15s;
}
.notif-action-icon.read-btn:hover    { background: #dcfce7; color: #16a34a; }
.notif-action-icon.dismiss-btn:hover { background: #fee2e2; color: #ef4444; }

/* Footer */
.notif-panel-footer {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.6rem 1rem;
    border-top: 1px solid var(--gray-100);
    background: var(--gray-50);
    min-height: 36px;
}
.notif-footer-count {
    font-size: 0.72rem;
    color: var(--gray-400);
    font-weight: 500;
}

@media (max-width: 480px) {
    .notif-panel { width: 320px; right: -4px; }
}

/* Keep legacy classes for user-dropdown reuse */
.notification-item {
    display: flex;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    text-decoration: none;
    color: var(--gray-700);
    border-bottom: 1px solid var(--gray-100);
    transition: background 0.2s;
}
.notification-item:hover { background: var(--gray-50); }
.notification-icon {
    width: 36px; height: 36px;
    background: var(--primary-100); color: var(--primary-600);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.notification-content { flex: 1; min-width: 0; }
.notification-title { font-size: 0.85rem; font-weight: 500; line-height: 1.3; margin-bottom: 2px; word-wrap: break-word; }
.notification-time  { font-size: 0.75rem; color: var(--gray-500); }
</style>

<script>
// ===== Notification Panel =====

function toggleNotificationDropdown(event) {
    event.stopPropagation();
    const panel = document.getElementById('notificationDropdown');
    const isOpen = panel.classList.contains('show');
    if (isOpen) {
        panel.classList.remove('show');
    } else {
        panel.classList.remove('show');
        void panel.offsetWidth; // reflow to restart animation
        panel.classList.add('show');
        NotificationManager.loadNotifications();
    }
    document.querySelectorAll('.navbar-user-dropdown').forEach(d => d.classList.remove('show'));
}

function toggleUserDropdown(event) {
    event.stopPropagation();
    document.getElementById('userDropdownDesktop').classList.toggle('show');
    document.getElementById('notificationDropdown').classList.remove('show');
    const m = document.getElementById('userDropdownMobile');
    if (m) m.classList.remove('show');
}

function toggleUserDropdownMobile(event) {
    event.stopPropagation();
    document.getElementById('userDropdownMobile').classList.toggle('show');
    document.getElementById('notificationDropdown').classList.remove('show');
    const d = document.getElementById('userDropdownDesktop');
    if (d) d.classList.remove('show');
}

document.addEventListener('click', function(e) {
    const panel   = document.getElementById('notificationDropdown');
    const trigger = document.querySelector('.notification-trigger');
    if (panel && trigger && !panel.contains(e.target) && !trigger.contains(e.target)) {
        panel.classList.remove('show');
    }
    const userProfiles = document.querySelectorAll('.navbar-user-profile');
    let insideProfile = false;
    userProfiles.forEach(p => { if (p.contains(e.target)) insideProfile = true; });
    if (!insideProfile) {
        document.querySelectorAll('.navbar-user-dropdown').forEach(d => d.classList.remove('show'));
    }
});

// ---- Per-item actions ----

function handleNotifClick(id, event) {
    // Just mark as read silently; navigation happens naturally via the <a> href
    _apiPost({ action: 'mark_read', id });
    // Update UI: remove unread state
    const item = document.querySelector(`.notif-item[data-id="${id}"]`);
    if (item) {
        item.classList.remove('unread');
        item.querySelector('.notif-unread-dot')?.remove();
        item.querySelector('.read-btn')?.remove();
    }
    NotificationManager.decrementUnread();
}

function markAsReadUI(id, event) {
    event.preventDefault();
    event.stopPropagation();
    _apiPost({ action: 'mark_read', id }).then(() => {
        const item = document.querySelector(`.notif-item[data-id="${id}"]`);
        if (item) {
            item.classList.remove('unread');
            item.querySelector('.notif-unread-dot')?.remove();
            const readBtn = item.querySelector('.read-btn');
            if (readBtn) readBtn.remove();
        }
        NotificationManager.decrementUnread();
    });
}

function dismissNotificationUI(id, event) {
    event.preventDefault();
    event.stopPropagation();
    const item = document.querySelector(`.notif-item[data-id="${id}"]`);
    if (!item) return;
    const wasUnread = item.classList.contains('unread');
    item.classList.add('dismissing');
    item.addEventListener('animationend', () => {
        item.remove();
        if (wasUnread) NotificationManager.decrementUnread();
        NotificationManager.updateFooterCount(-1);
        NotificationManager.checkEmpty();
    }, { once: true });
    _apiPost({ action: 'dismiss', id });
}

function markAllRead() {
    _apiPost({ action: 'mark_all_read' }).then(() => {
        document.querySelectorAll('.notif-item.unread').forEach(item => {
            item.classList.remove('unread');
            item.querySelector('.notif-unread-dot')?.remove();
            item.querySelector('.read-btn')?.remove();
        });
        NotificationManager.setUnreadCount(0);
    });
}

function clearAllNotifications() {
    if (!confirm('ล้างการแจ้งเตือนทั้งหมดของคุณ?\n\nการแจ้งเตือนทั้งหมดจะถูกลบออกถาวร')) return;
    _apiPost({ action: 'clear_all' }).then(() => {
        const list = document.getElementById('notificationList');
        if (list) {
            list.innerHTML = `
                <div class="notif-empty">
                    <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="var(--gray-300)" stroke-width="1.5">
                        <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 01-3.46 0"/>
                    </svg>
                    <p>ไม่มีการแจ้งเตือน</p>
                    <span>ระบบจะแจ้งเตือนเมื่อมีกิจกรรมใหม่</span>
                </div>`;
        }
        NotificationManager.setUnreadCount(0);
        const fc = document.getElementById('notifFooterCount');
        if (fc) fc.textContent = '';
    });
}

// ---- Internal helpers ----

function _apiPost(body) {
    return fetch(window.APP_CONFIG.apiUrl + '/notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    }).then(r => r.json()).catch(() => {});
}

// ===== NotificationManager =====
const NotificationManager = {
    refreshInterval: 60000,
    timer: null,
    _footerCount: 0,

    init() {
        this.startAutoRefresh();
        this.checkNewNotifications();
    },

    startAutoRefresh() {
        this.timer = setInterval(() => this.checkNewNotifications(), this.refreshInterval);
    },

    async checkNewNotifications() {
        try {
            const r = await fetch(window.APP_CONFIG.apiUrl + '/notifications.php?action=count');
            const d = await r.json();
            this.setUnreadCount(d.unread_count ?? 0);
        } catch {}
    },

    setUnreadCount(count) {
        const badge = document.querySelector('.notification-badge');
        const chip  = document.getElementById('notifUnreadChip');
        const markAllBtn = document.getElementById('markAllReadBtn');
        if (count > 0) {
            const label = count > 9 ? '9+' : String(count);
            if (badge) { badge.textContent = label; badge.style.display = 'flex'; }
            else {
                const trigger = document.querySelector('.notification-trigger');
                if (trigger) {
                    const nb = document.createElement('span');
                    nb.className = 'notification-badge';
                    nb.textContent = label;
                    trigger.appendChild(nb);
                }
            }
            if (chip)  { chip.textContent = count; chip.style.display = ''; }
            if (markAllBtn) markAllBtn.style.display = '';
        } else {
            if (badge)  badge.style.display = 'none';
            if (chip)   chip.style.display = 'none';
            if (markAllBtn) markAllBtn.style.display = 'none';
        }
    },

    decrementUnread() {
        const chip = document.getElementById('notifUnreadChip');
        const badge = document.querySelector('.notification-badge');
        let cur = parseInt(chip?.textContent || badge?.textContent || '0', 10) || 0;
        this.setUnreadCount(Math.max(0, cur - 1));
    },

    updateFooterCount(delta) {
        this._footerCount = Math.max(0, (this._footerCount || 0) + delta);
        const fc = document.getElementById('notifFooterCount');
        if (fc) fc.textContent = this._footerCount > 0 ? `${this._footerCount} รายการ` : '';
    },

    checkEmpty() {
        const list = document.getElementById('notificationList');
        if (!list) return;
        if (!list.querySelector('.notif-item')) {
            list.innerHTML = `
                <div class="notif-empty">
                    <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="var(--gray-300)" stroke-width="1.5">
                        <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 01-3.46 0"/>
                    </svg>
                    <p>ไม่มีการแจ้งเตือน</p>
                    <span>ระบบจะแจ้งเตือนเมื่อมีกิจกรรมใหม่</span>
                </div>`;
        }
    },

    async loadNotifications() {
        try {
            const r = await fetch(window.APP_CONFIG.apiUrl + '/notifications.php?action=list&limit=10');
            const d = await r.json();
            this.renderNotifications(d.notifications || []);
            this.setUnreadCount(d.unread_count ?? 0);
        } catch {}
    },

    getIcon(type) {
        const icons = {
            info:    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
            success: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
            warning: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
            error:   '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
        };
        return icons[type] || icons.info;
    },

    formatFullTime(dateString) {
        const thM = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
        const d = new Date(dateString);
        const day = d.getDate();
        const mon = thM[d.getMonth() + 1];
        const yr  = d.getFullYear() + 543;
        const hh  = String(d.getHours()).padStart(2, '0');
        const mm  = String(d.getMinutes()).padStart(2, '0');
        return `${day} ${mon} ${yr}, ${hh}:${mm} น.`;
    },

    esc(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    renderNotifications(notifications) {
        const list = document.getElementById('notificationList');
        if (!list) return;

        if (!notifications.length) {
            list.innerHTML = `
                <div class="notif-empty">
                    <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="var(--gray-300)" stroke-width="1.5">
                        <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 01-3.46 0"/>
                    </svg>
                    <p>ไม่มีการแจ้งเตือน</p>
                    <span>ระบบจะแจ้งเตือนเมื่อมีกิจกรรมใหม่</span>
                </div>`;
            this._footerCount = 0;
            const fc = document.getElementById('notifFooterCount');
            if (fc) fc.textContent = '';
            return;
        }

        this._footerCount = notifications.length;
        const fc = document.getElementById('notifFooterCount');
        if (fc) fc.textContent = `${notifications.length} รายการ`;

        list.innerHTML = notifications.map(n => {
            const type     = n.type || 'info';
            const isUnread = !n.is_read;
            const msg      = n.message ? this.esc(n.message).substring(0, 80) + (n.message.length > 80 ? '…' : '') : '';
            const fullTime = this.formatFullTime(n.created_at);
            const readBtn  = isUnread
                ? `<button class="notif-action-icon read-btn" onclick="markAsReadUI(${n.id}, event)" title="ทำเครื่องหมายว่าอ่านแล้ว">
                       <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                   </button>` : '';
            const unreadDot = isUnread ? '<span class="notif-unread-dot"></span>' : '';
            return `
            <div class="notif-item${isUnread ? ' unread' : ''}" data-id="${n.id}">
                <div class="notif-type-bar type-${type}"></div>
                <div class="notif-icon-wrap type-${type}">${this.getIcon(type)}</div>
                <a href="${this.esc(n.link || '#')}" class="notif-item-body" onclick="handleNotifClick(${n.id}, event)">
                    <div class="notif-item-title">${this.esc(n.title)}</div>
                    ${msg ? `<div class="notif-item-message">${msg}</div>` : ''}
                    <div class="notif-item-meta">
                        ${unreadDot}
                        <span class="notif-full-time">${fullTime}</span>
                    </div>
                </a>
                <div class="notif-item-actions">
                    ${readBtn}
                    <button class="notif-action-icon dismiss-btn" onclick="dismissNotificationUI(${n.id}, event)" title="ปิดการแจ้งเตือนนี้">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
            </div>`;
        }).join('');
    }
};

document.addEventListener('DOMContentLoaded', () => NotificationManager.init());
</script>
