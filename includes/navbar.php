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
            $recentNotifications = getUnreadNotifications($user['id'], 5);
            ?>
            <div class="notification-menu" style="position: relative;">
                <button class="btn btn-icon notification-trigger" style="position: relative;" onclick="toggleNotificationDropdown(event)">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 01-3.46 0"/>
                    </svg>
                    <?php if ($unreadCount > 0): ?>
                    <span class="notification-badge"><?php echo $unreadCount > 9 ? '9+' : $unreadCount; ?></span>
                    <?php endif; ?>
                </button>
                
                <div class="notification-dropdown" id="notificationDropdown" style="display: none;">
                    <div class="notification-header">
                        <span style="font-weight: 600;">การแจ้งเตือน</span>
                        <?php if ($unreadCount > 0): ?>
                        <button onclick="markAllRead()" style="font-size: 0.75rem; color: var(--primary-600); background: none; border: none; cursor: pointer;">อ่านทั้งหมด</button>
                        <?php endif; ?>
                    </div>
                    <div class="notification-list">
                        <?php if (empty($recentNotifications)): ?>
                        <div class="notification-empty">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--gray-300)" stroke-width="1.5" style="margin-bottom: 0.5rem;">
                                <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                                <path d="M13.73 21a2 2 0 01-3.46 0"/>
                            </svg>
                            <span>ไม่มีการแจ้งเตือน</span>
                        </div>
                        <?php else: ?>
                        <?php foreach ($recentNotifications as $notif): ?>
                        <a href="<?php echo $notif['link'] ?? '#'; ?>" class="notification-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>" onclick="markAsRead(<?php echo $notif['id']; ?>)">
                            <div class="notification-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                    <line x1="16" y1="13" x2="8" y2="13"/>
                                    <line x1="16" y1="17" x2="8" y2="17"/>
                                </svg>
                            </div>
                            <div class="notification-content">
                                <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                                <div class="notification-time"><?php echo formatDateTime($notif['created_at']); ?></div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                        <?php endif; ?>
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
/* Notification Styles */
.notification-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    min-width: 18px;
    height: 18px;
    background: var(--danger);
    color: white;
    font-size: 0.7rem;
    font-weight: 600;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
}

.notification-dropdown {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    width: 320px;
    background: white;
    border-radius: var(--radius-lg);
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    z-index: 1000;
    overflow: hidden;
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid var(--gray-200);
    background: var(--gray-50);
}

.notification-list {
    max-height: 300px;
    overflow-y: auto;
}

.notification-empty {
    padding: 2rem;
    text-align: center;
    color: var(--gray-400);
    display: flex;
    flex-direction: column;
    align-items: center;
}

.notification-item {
    display: flex;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    text-decoration: none;
    color: var(--gray-700);
    border-bottom: 1px solid var(--gray-100);
    transition: background 0.2s;
}

.notification-item:hover {
    background: var(--gray-50);
}

.notification-item.unread {
    background: var(--primary-50);
}

.notification-icon {
    width: 36px;
    height: 36px;
    background: var(--primary-100);
    color: var(--primary-600);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.notification-content {
    flex: 1;
    min-width: 0;
}

.notification-title {
    font-size: 0.85rem;
    font-weight: 500;
    line-height: 1.3;
    margin-bottom: 2px;
    word-wrap: break-word;
}

.notification-time {
    font-size: 0.75rem;
    color: var(--gray-500);
}
</style>

<script>
function toggleNotificationDropdown(event) {
    event.stopPropagation();
    const dropdown = document.getElementById('notificationDropdown');
    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
    // Close user dropdowns
    document.querySelectorAll('.navbar-user-dropdown').forEach(d => d.classList.remove('show'));
}

function toggleUserDropdown(event) {
    event.stopPropagation();
    const dropdown = document.getElementById('userDropdownDesktop');
    dropdown.classList.toggle('show');
    // Close notification dropdown
    document.getElementById('notificationDropdown').style.display = 'none';
    // Close mobile user dropdown
    const mobileDropdown = document.getElementById('userDropdownMobile');
    if (mobileDropdown) mobileDropdown.classList.remove('show');
}

function toggleUserDropdownMobile(event) {
    event.stopPropagation();
    const dropdown = document.getElementById('userDropdownMobile');
    dropdown.classList.toggle('show');
    // Close notification dropdown
    document.getElementById('notificationDropdown').style.display = 'none';
    // Close desktop user dropdown
    const desktopDropdown = document.getElementById('userDropdownDesktop');
    if (desktopDropdown) desktopDropdown.classList.remove('show');
}

document.addEventListener('click', function(e) {
    const notifDropdown = document.getElementById('notificationDropdown');
    const notifTrigger = document.querySelector('.notification-trigger');
    if (notifDropdown && !notifDropdown.contains(e.target) && !notifTrigger.contains(e.target)) {
        notifDropdown.style.display = 'none';
    }
    
    // Close user dropdowns when clicking outside
    const userDropdowns = document.querySelectorAll('.navbar-user-dropdown');
    const userProfiles = document.querySelectorAll('.navbar-user-profile');
    let clickedInsideProfile = false;
    userProfiles.forEach(p => {
        if (p.contains(e.target)) clickedInsideProfile = true;
    });
    if (!clickedInsideProfile) {
        userDropdowns.forEach(d => d.classList.remove('show'));
    }
});

function markAsRead(notificationId) {
    fetch(window.APP_CONFIG.apiUrl + '/notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'mark_read', id: notificationId })
    });
}

function markAllRead() {
    fetch(window.APP_CONFIG.apiUrl + '/notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'mark_all_read' })
    }).then(() => location.reload());
}

// ===== Notification Real-time Updates =====
const NotificationManager = {
    refreshInterval: 60000, // 1 minute
    timer: null,
    
    init() {
        this.startAutoRefresh();
        this.checkNewNotifications();
    },
    
    startAutoRefresh() {
        this.timer = setInterval(() => {
            this.checkNewNotifications();
        }, this.refreshInterval);
    },
    
    async checkNewNotifications() {
        try {
            const response = await fetch(window.APP_CONFIG.apiUrl + '/notifications.php?action=count');
            const data = await response.json();
            this.updateBadge(data.unread_count);
        } catch (error) {
            console.error('Error checking notifications:', error);
        }
    },
    
    updateBadge(count) {
        const badge = document.querySelector('.notification-badge');
        if (count > 0) {
            if (badge) {
                badge.textContent = count > 9 ? '9+' : count;
                badge.style.display = 'flex';
            } else {
                // Create badge if not exists
                const trigger = document.querySelector('.notification-trigger');
                if (trigger) {
                    const newBadge = document.createElement('span');
                    newBadge.className = 'notification-badge';
                    newBadge.textContent = count > 9 ? '9+' : count;
                    trigger.appendChild(newBadge);
                }
            }
        } else if (badge) {
            badge.style.display = 'none';
        }
    },
    
    async loadNotifications() {
        try {
            const response = await fetch(window.APP_CONFIG.apiUrl + '/notifications.php?action=list&limit=10');
            const data = await response.json();
            this.renderNotifications(data.notifications);
            this.updateBadge(data.unread_count);
        } catch (error) {
            console.error('Error loading notifications:', error);
        }
    },
    
    getNotificationIcon(type) {
        const icons = {
            'info': '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
            'success': '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
            'warning': '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
            'error': '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>'
        };
        return icons[type] || icons['info'];
    },
    
    getIconStyle(type) {
        const styles = {
            'info': 'background: var(--primary-100); color: var(--primary-600);',
            'success': 'background: var(--success-100); color: var(--success-600);',
            'warning': 'background: var(--warning-100); color: var(--warning-600);',
            'error': 'background: var(--danger-100, #fee2e2); color: var(--danger, #ef4444);'
        };
        return styles[type] || styles['info'];
    },
    
    formatTime(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);
        
        if (diffMins < 1) return 'เมื่อสักครู่';
        if (diffMins < 60) return `${diffMins} นาทีที่แล้ว`;
        if (diffHours < 24) return `${diffHours} ชั่วโมงที่แล้ว`;
        if (diffDays < 7) return `${diffDays} วันที่แล้ว`;
        return date.toLocaleDateString('th-TH');
    },
    
    renderNotifications(notifications) {
        const list = document.querySelector('.notification-list');
        if (!list) return;
        
        if (!notifications || notifications.length === 0) {
            list.innerHTML = `
                <div class="notification-empty">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--gray-300)" stroke-width="1.5" style="margin-bottom: 0.5rem;">
                        <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 01-3.46 0"/>
                    </svg>
                    <span>ไม่มีการแจ้งเตือน</span>
                </div>`;
            return;
        }
        
        list.innerHTML = notifications.map(notif => `
            <a href="${notif.link || '#'}" class="notification-item ${notif.is_read ? '' : 'unread'}" onclick="markAsRead(${notif.id})">
                <div class="notification-icon" style="${this.getIconStyle(notif.type)}">
                    ${this.getNotificationIcon(notif.type)}
                </div>
                <div class="notification-content">
                    <div class="notification-title">${this.escapeHtml(notif.title)}</div>
                    <div class="notification-message" style="font-size: 0.75rem; color: var(--gray-500); margin-bottom: 2px;">${this.escapeHtml(notif.message || '').substring(0, 50)}${notif.message && notif.message.length > 50 ? '...' : ''}</div>
                    <div class="notification-time">${this.formatTime(notif.created_at)}</div>
                </div>
            </a>
        `).join('');
    },
    
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    NotificationManager.init();
});

// Refresh when dropdown opens
const origToggleNotif = toggleNotificationDropdown;
toggleNotificationDropdown = function(event) {
    origToggleNotif(event);
    const dropdown = document.getElementById('notificationDropdown');
    if (dropdown && dropdown.style.display === 'block') {
        NotificationManager.loadNotifications();
    }
};
</script>
