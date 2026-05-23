<?php
/**
 * HICM V2025 Assessment System - Sidebar
 */

$user = getCurrentUser();
$isAdmin = hasRole(ROLE_ADMIN);
$isAuditor = hasRole(ROLE_AUDITOR);
$isCompany = hasRole(ROLE_COMPANY);
$isCEO = hasRole('ceo');

// Get current page
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Check sidebar state from cookie/localStorage
?>
<aside class="sidebar" id="mainSidebar">
    <nav class="sidebar-nav">
        <!-- Main Menu -->
        <div class="sidebar-section">
            <div class="sidebar-title">เมนูหลัก</div>
            <a href="<?php echo getBaseUrl(); ?>/pages/dashboard.php" class="sidebar-link <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>" data-tooltip="แดชบอร์ด">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"/>
                    <rect x="14" y="3" width="7" height="7"/>
                    <rect x="14" y="14" width="7" height="7"/>
                    <rect x="3" y="14" width="7" height="7"/>
                </svg>
                <span class="sidebar-link-text">แดชบอร์ด</span>
            </a>
            
            <?php if ($isCompany): ?>
                <a href="<?php echo getBaseUrl(); ?>/pages/assessment-form.php" class="sidebar-link <?php echo $currentPage === 'assessment-form' ? 'active' : ''; ?>" data-tooltip="ทำแบบประเมิน">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                    <span class="sidebar-link-text">ทำแบบประเมิน</span>
                </a>
                
                <a href="<?php echo getBaseUrl(); ?>/pages/milestones.php" class="sidebar-link <?php echo $currentPage === 'milestones' ? 'active' : ''; ?>" data-tooltip="My Milestones">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                        <polyline points="2 17 12 22 22 17"/>
                        <polyline points="2 12 12 17 22 12"/>
                    </svg>
                    <span class="sidebar-link-text">My Milestones</span>
                </a>
                
                <a href="<?php echo getBaseUrl(); ?>/pages/assessment-result.php" class="sidebar-link <?php echo ($currentPage === 'assessment-result' && !isset($_GET['combined'])) ? 'active' : ''; ?>" data-tooltip="ผลการประเมิน">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                    </svg>
                    <span class="sidebar-link-text">ผลการประเมิน</span>
                </a>
                
                <?php
                // Check if any period has auditor results published for this company
                $companyIdForSidebar = $user['company_id'] ?? null;
                $showCombinedMenu = false;
                if ($companyIdForSidebar) {
                    try {
                        $dbSidebar = getDB();
                        $stmtSidebar = $dbSidebar->prepare("
                            SELECT COUNT(*) FROM assessment_periods ap
                            JOIN assessments a ON a.period_id = ap.id
                            WHERE ap.show_auditor_results = 1 
                            AND ap.is_active = 1
                            AND a.company_id = ?
                            AND a.status IN ('submitted', 'under_review', 'evaluated', 'completed')
                        ");
                        $stmtSidebar->execute([$companyIdForSidebar]);
                        $showCombinedMenu = $stmtSidebar->fetchColumn() > 0;
                    } catch (Exception $e) {
                        $showCombinedMenu = false;
                    }
                }
                if ($showCombinedMenu): ?>
                <a href="<?php echo getBaseUrl(); ?>/pages/assessment-result.php?combined=1" class="sidebar-link <?php echo ($currentPage === 'assessment-result' && isset($_GET['combined'])) ? 'active' : ''; ?>" data-tooltip="ผลการประเมินรวม">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                    <span class="sidebar-link-text">ผลการประเมินรวม</span>
                </a>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if ($isAdmin || $isAuditor): ?>
                <a href="<?php echo getBaseUrl(); ?>/pages/assessments.php" class="sidebar-link <?php echo $currentPage === 'assessments' ? 'active' : ''; ?>" data-tooltip="รายการประเมิน">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                    </svg>
                    <span class="sidebar-link-text">รายการประเมิน</span>
                </a>
            <?php endif; ?>
            
            <?php if ($isAdmin || $isAuditor || $isCEO): ?>
                <a href="<?php echo getBaseUrl(); ?>/pages/companies.php" class="sidebar-link <?php echo $currentPage === 'companies' ? 'active' : ''; ?>" data-tooltip="สถานประกอบการ">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                        <polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                    <span class="sidebar-link-text">สถานประกอบการ</span>
                </a>
                
                <a href="<?php echo getBaseUrl(); ?>/pages/company-locations.php" class="sidebar-link <?php echo $currentPage === 'company-locations' ? 'active' : ''; ?>" data-tooltip="ตำแหน่งที่ตั้งบริษัท">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                        <circle cx="12" cy="10" r="3"/>
                    </svg>
                    <span class="sidebar-link-text">ตำแหน่งที่ตั้งบริษัท</span>
                </a>
            <?php endif; ?>
            
            <?php if ($isAuditor): ?>
                <a href="<?php echo getBaseUrl(); ?>/pages/my-assessments.php" class="sidebar-link <?php echo $currentPage === 'my-assessments' ? 'active' : ''; ?>" data-tooltip="การประเมินของฉัน">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 11l3 3L22 4"/>
                        <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
                    </svg>
                    <span class="sidebar-link-text">การประเมินของฉัน</span>
                </a>
            <?php endif; ?>
        </div>
        
        <!-- Reports -->
        <?php if ($isAdmin || $isAuditor || $isCEO): ?>
        <div class="sidebar-section">
            <div class="sidebar-title">รายงาน</div>
            
            <a href="<?php echo getBaseUrl(); ?>/pages/reports.php" class="sidebar-link <?php echo $currentPage === 'reports' ? 'active' : ''; ?>" data-tooltip="รายงานสรุป">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="20" x2="18" y2="10"/>
                    <line x1="12" y1="20" x2="12" y2="4"/>
                    <line x1="6" y1="20" x2="6" y2="14"/>
                </svg>
                <span class="sidebar-link-text">รายงานสรุป</span>
            </a>
            
            <?php if ($isAdmin): ?>
            <a href="<?php echo getBaseUrl(); ?>/pages/export.php" class="sidebar-link <?php echo $currentPage === 'export' ? 'active' : ''; ?>" data-tooltip="ส่งออกข้อมูล">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                <span class="sidebar-link-text">ส่งออกข้อมูล</span>
            </a>
            
            <a href="<?php echo getBaseUrl(); ?>/pages/output-management.php" class="sidebar-link <?php echo $currentPage === 'output-management' ? 'active' : ''; ?>" data-tooltip="จัดการ Output">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 19H5a2 2 0 01-2-2V7a2 2 0 012-2h4m0 0h10a2 2 0 012 2v10a2 2 0 01-2 2h-4m0 0v-5m0 5l-4-4m4 4l4-4"/>
                </svg>
                <span class="sidebar-link-text">จัดการ Output</span>
            </a>
            <a href="<?php echo getBaseUrl(); ?>/pages/api-docs.php" class="sidebar-link <?php echo $currentPage === 'api-docs' ? 'active' : ''; ?>" data-tooltip="API Documentation">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 19.5A2.5 2.5 0 016.5 17H20"/>
                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/>
                    <path d="M8 7h8"/>
                    <path d="M8 11h8"/>
                    <path d="M8 15h5"/>
                </svg>
                <span class="sidebar-link-text">API Documentation</span>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Admin Settings -->
        <?php if ($isAdmin): ?>
        <div class="sidebar-section">
            <div class="sidebar-title">การจัดการระบบ</div>
            
            <a href="<?php echo getBaseUrl(); ?>/pages/users.php" class="sidebar-link <?php echo $currentPage === 'users' ? 'active' : ''; ?>" data-tooltip="ผู้ใช้งาน">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                <span class="sidebar-link-text">ผู้ใช้งาน</span>
            </a>
            
            <a href="<?php echo getBaseUrl(); ?>/pages/organizations.php" class="sidebar-link <?php echo $currentPage === 'organizations' ? 'active' : ''; ?>" data-tooltip="หน่วยงานสังกัด">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                    <polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
                <span class="sidebar-link-text">หน่วยงานสังกัด</span>
            </a>
            
            <a href="<?php echo getBaseUrl(); ?>/pages/indicators.php" class="sidebar-link <?php echo $currentPage === 'indicators' ? 'active' : ''; ?>" data-tooltip="ตัวชี้วัด">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="8" y1="6" x2="21" y2="6"/>
                    <line x1="8" y1="12" x2="21" y2="12"/>
                    <line x1="8" y1="18" x2="21" y2="18"/>
                    <line x1="3" y1="6" x2="3.01" y2="6"/>
                    <line x1="3" y1="12" x2="3.01" y2="12"/>
                    <line x1="3" y1="18" x2="3.01" y2="18"/>
                </svg>
                <span class="sidebar-link-text">ตัวชี้วัด</span>
            </a>
            
            <a href="<?php echo getBaseUrl(); ?>/pages/industry-categories.php" class="sidebar-link <?php echo $currentPage === 'industry-categories' ? 'active' : ''; ?>" data-tooltip="หมวดหมู่อุตสาหกรรม">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                    <line x1="12" y1="11" x2="12" y2="17"/>
                    <line x1="9" y1="14" x2="15" y2="14"/>
                </svg>
                <span class="sidebar-link-text">หมวดหมู่อุตสาหกรรม</span>
            </a>
            
            <a href="<?php echo getBaseUrl(); ?>/pages/auditor-expertise.php" class="sidebar-link <?php echo $currentPage === 'auditor-expertise' ? 'active' : ''; ?>" data-tooltip="ความเชี่ยวชาญกรรมการ">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                </svg>
                <span class="sidebar-link-text">ความเชี่ยวชาญกรรมการ</span>
            </a>
            
            <a href="<?php echo getBaseUrl(); ?>/pages/periods.php" class="sidebar-link <?php echo $currentPage === 'periods' ? 'active' : ''; ?>" data-tooltip="รอบการประเมิน">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                <span class="sidebar-link-text">รอบการประเมิน</span>
            </a>
 
            <a href="<?php echo getBaseUrl(); ?>/pages/auditor-assignments.php" class="sidebar-link <?php echo $currentPage === 'auditor-assignments' ? 'active' : ''; ?>" data-tooltip="จับคู่การประเมิน">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                    <circle cx="8.5" cy="7" r="4"/>
                    <polyline points="17 11 19 13 23 9"/>
                </svg>
                <span class="sidebar-link-text">จับคู่การประเมิน</span>
            </a>
            
            <a href="<?php echo getBaseUrl(); ?>/pages/announcements.php" class="sidebar-link <?php echo $currentPage === 'announcements' ? 'active' : ''; ?>" data-tooltip="ประชาสัมพันธ์">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                </svg>
                <span class="sidebar-link-text">ประชาสัมพันธ์</span>
            </a>
            
            <a href="<?php echo getBaseUrl(); ?>/pages/settings.php" class="sidebar-link <?php echo $currentPage === 'settings' ? 'active' : ''; ?>" data-tooltip="ตั้งค่าระบบ">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3"/>
                    <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/>
                </svg>
                <span class="sidebar-link-text">ตั้งค่าระบบ</span>
            </a>
        </div>
        <?php endif; ?>
        
        <!-- Account -->
        <div class="sidebar-section">
            <div class="sidebar-title">บัญชี</div>
            
            <a href="<?php echo getBaseUrl(); ?>/pages/profile.php" class="sidebar-link <?php echo $currentPage === 'profile' ? 'active' : ''; ?>" data-tooltip="โปรไฟล์">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                <span class="sidebar-link-text">โปรไฟล์</span>
            </a>
            
            <?php if ($isCompany): ?>
                <a href="<?php echo getBaseUrl(); ?>/pages/company-profile.php" class="sidebar-link <?php echo $currentPage === 'company-profile' ? 'active' : ''; ?>" data-tooltip="ข้อมูลบริษัท">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                        <polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                    <span class="sidebar-link-text">ข้อมูลบริษัท</span>
                </a>
            <?php endif; ?>
            
            <a href="<?php echo getBaseUrl(); ?>/pages/change-password.php" class="sidebar-link <?php echo $currentPage === 'change-password' ? 'active' : ''; ?>" data-tooltip="เปลี่ยนรหัสผ่าน">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0110 0v4"/>
                </svg>
                <span class="sidebar-link-text">เปลี่ยนรหัสผ่าน</span>
            </a>
 
            <a href="<?php echo getBaseUrl(); ?>/pages/manual.php" class="sidebar-link <?php echo $currentPage === 'manual' ? 'active' : ''; ?>" data-tooltip="ข้อมูลระบบ HICM">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 6c0 1 .2 2.2 1 3.5.7.8 1.2 1.5 1.5 2.5"/>
                    <path d="M9 18h6"/>
                    <path d="M10 22h4"/>
                </svg>
                <span class="sidebar-link-text">ข้อมูลระบบ HICM</span>
            </a>

            <a href="<?php echo getBaseUrl(); ?>/pages/user-manual.php" class="sidebar-link <?php echo $currentPage === 'user-manual' ? 'active' : ''; ?>" data-tooltip="คู่มือการใช้งาน">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="m21 21-4.35-4.35"/>
                </svg>
                <span class="sidebar-link-text">คู่มือการใช้งาน</span>
            </a>
 
            <a href="<?php echo getBaseUrl(); ?>/pages/logout.php" class="sidebar-link" style="color: var(--danger);" data-tooltip="ออกจากระบบ">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
                    <polyline points="16 17 21 12 16 7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
                <span class="sidebar-link-text">ออกจากระบบ</span>
            </a>
        </div>
    </nav>
</aside>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
// Sidebar Toggle Functionality - Run immediately
(function() {
    function initSidebar() {
        const sidebar = document.getElementById('mainSidebar');
        const menuToggleBtn = document.getElementById('menuToggle');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const body = document.body;
        
        if (!sidebar) {
            console.error('Sidebar not found');
            return;
        }
        
        // Check if desktop or mobile
        function isDesktop() {
            return window.innerWidth >= 1024;
        }
        
        // Load saved state from localStorage (desktop only)
        if (isDesktop()) {
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed) {
                sidebar.classList.add('collapsed');
                body.classList.add('sidebar-collapsed');
            }
        }
        
        // Toggle function
        function toggleSidebar(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            console.log('Toggle sidebar clicked, isDesktop:', isDesktop());
            
            if (isDesktop()) {
                // Desktop: collapse/expand sidebar
                sidebar.classList.toggle('collapsed');
                const nowCollapsed = sidebar.classList.contains('collapsed');
                body.classList.toggle('sidebar-collapsed', nowCollapsed);
                localStorage.setItem('sidebarCollapsed', nowCollapsed);
            } else {
                // Mobile: slide sidebar in/out
                const isOpen = sidebar.classList.toggle('open');
                console.log('Mobile sidebar open:', isOpen);
                
                // Toggle all overlays
                document.querySelectorAll('.sidebar-overlay').forEach(overlay => {
                    overlay.classList.toggle('active', isOpen);
                });
                
                // Prevent body scroll when sidebar is open
                body.style.overflow = isOpen ? 'hidden' : '';
            }
        }
        
        // Close sidebar function
        function closeSidebar() {
            sidebar.classList.remove('open');
            document.querySelectorAll('.sidebar-overlay').forEach(overlay => {
                overlay.classList.remove('active');
            });
            body.style.overflow = '';
        }
        
        // Menu toggle button click
        if (menuToggleBtn) {
            menuToggleBtn.onclick = null;
            menuToggleBtn.addEventListener('click', toggleSidebar, { capture: true });
        }
        
        // Close sidebar when clicking any overlay (mobile)
        document.querySelectorAll('.sidebar-overlay').forEach(overlay => {
            overlay.addEventListener('click', closeSidebar);
        });
        
        // Close sidebar when clicking a link (mobile)
        sidebar.querySelectorAll('.sidebar-link').forEach(link => {
            link.addEventListener('click', function() {
                if (!isDesktop()) {
                    closeSidebar();
                }
            });
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            if (isDesktop()) {
                closeSidebar();
                // Restore desktop state
                const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
                sidebar.classList.toggle('collapsed', isCollapsed);
                body.classList.toggle('sidebar-collapsed', isCollapsed);
            } else {
                sidebar.classList.remove('collapsed');
                body.classList.remove('sidebar-collapsed');
            }
        });
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSidebar);
    } else {
        initSidebar();
    }
})();
</script>
