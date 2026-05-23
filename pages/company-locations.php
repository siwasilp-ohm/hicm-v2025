<?php
/**
 * HICM V2025 Assessment System - Company Locations Map
 * แผนที่ตำแหน่งที่ตั้งบริษัททั้งหมด
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/companies.php';

requireAuth();

// Only Admin, Auditor, and CEO can access
if (!hasRole(ROLE_ADMIN) && !hasRole(ROLE_AUDITOR) && !hasRole('ceo')) {
    header('Location: dashboard.php');
    exit;
}

$user = getCurrentUser();
$db = getDB();

// Get all companies with location
$stmt = $db->prepare("
    SELECT c.id, c.company_name, c.company_name_en, c.latitude, c.longitude,
           c.address, c.province, c.industry_type, c.company_size, c.employee_count,
           c.contact_name, c.contact_phone, c.contact_email
    FROM companies c
    WHERE c.latitude IS NOT NULL AND c.longitude IS NOT NULL
    ORDER BY c.company_name
");
$stmt->execute();
$companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stmt = $db->prepare("SELECT COUNT(*) as total FROM companies");
$stmt->execute();
$totalCompanies = $stmt->fetch()['total'];

$companiesWithLocation = count($companies);
$companiesWithoutLocation = $totalCompanies - $companiesWithLocation;

// Size labels
$sizeLabels = [
    'small' => 'ขนาดเล็ก',
    'medium' => 'ขนาดกลาง',
    'large' => 'ขนาดใหญ่'
];

// Size colors for markers
$sizeColors = [
    'small' => '#10B981',   // green
    'medium' => '#F59E0B',  // orange
    'large' => '#EF4444'    // red
];

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตำแหน่งที่ตั้งบริษัท - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css">
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .page-title svg {
            width: 32px;
            height: 32px;
            color: #EF4444;
        }
        
        /* Stats Cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        @media (max-width: 768px) {
            .stats-row { grid-template-columns: 1fr; }
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border: 1px solid var(--gray-100);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .stat-content {
            flex: 1;
        }
        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--gray-900);
            line-height: 1;
        }
        .stat-label {
            font-size: 0.85rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }
        
        /* Map Container */
        .map-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid var(--gray-100);
            overflow: hidden;
        }
        .map-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--gray-100);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            background: var(--gray-50);
        }
        .map-header h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-800);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .map-legend {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
            color: var(--gray-600);
        }
        .legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        #companies-map {
            width: 100%;
            height: 600px;
        }
        
        /* Custom Popup */
        .company-popup {
            min-width: 280px;
        }
        .popup-header {
            padding: 0.75rem;
            background: linear-gradient(135deg, #1e3a5f 0%, #0c4a6e 100%);
            color: white;
            border-radius: 8px 8px 0 0;
            margin: -14px -14px 0.75rem -14px;
        }
        .popup-title {
            font-size: 0.95rem;
            font-weight: 600;
            margin: 0 0 0.25rem 0;
            line-height: 1.3;
        }
        .popup-subtitle {
            font-size: 0.75rem;
            opacity: 0.9;
        }
        .popup-body {
            padding: 0 0.25rem;
        }
        .popup-row {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--gray-100);
            font-size: 0.8rem;
        }
        .popup-row:last-child {
            border-bottom: none;
        }
        .popup-row svg {
            width: 14px;
            height: 14px;
            color: var(--gray-400);
            flex-shrink: 0;
            margin-top: 2px;
        }
        .popup-row-content {
            flex: 1;
            color: var(--gray-700);
        }
        .popup-label {
            font-size: 0.7rem;
            color: var(--gray-500);
            display: block;
        }
        .popup-badge {
            display: inline-block;
            padding: 0.2rem 0.5rem;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        .popup-footer {
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid var(--gray-100);
        }
        .popup-link {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.8rem;
            color: var(--primary);
            text-decoration: none;
        }
        .popup-link:hover {
            text-decoration: underline;
        }
        
        /* Filter Controls */
        .filter-controls {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .filter-btn {
            padding: 0.5rem 1rem;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            background: white;
            font-size: 0.85rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }
        .filter-btn:hover {
            border-color: var(--primary);
            background: var(--gray-50);
        }
        .filter-btn.active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-in {
            animation: fadeIn 0.4s ease-out forwards;
        }
        
        /* Leaflet Custom Styles */
        .leaflet-popup-content-wrapper {
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        .leaflet-popup-content {
            margin: 14px;
        }
        .marker-cluster-small {
            background-color: rgba(16, 185, 129, 0.6);
        }
        .marker-cluster-small div {
            background-color: rgba(16, 185, 129, 0.8);
        }
        .marker-cluster-medium {
            background-color: rgba(245, 158, 11, 0.6);
        }
        .marker-cluster-medium div {
            background-color: rgba(245, 158, 11, 0.8);
        }
        .marker-cluster-large {
            background-color: rgba(239, 68, 68, 0.6);
        }
        .marker-cluster-large div {
            background-color: rgba(239, 68, 68, 0.8);
        }
    </style>
</head>
<body class="has-sidebar">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="sidebar-overlay"></div>
    
    <main class="main-wrapper">
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header animate-in">
                <h1 class="page-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                        <circle cx="12" cy="10" r="3"/>
                    </svg>
                    ตำแหน่งที่ตั้งบริษัท
                </h1>
            </div>
            
            <!-- Stats Row -->
            <div class="stats-row animate-in" style="animation-delay: 0.1s;">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #DBEAFE; color: #3B82F6;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($totalCompanies); ?></div>
                        <div class="stat-label">บริษัททั้งหมด</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #D1FAE5; color: #10B981;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                            <circle cx="12" cy="10" r="3"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($companiesWithLocation); ?></div>
                        <div class="stat-label">มีพิกัดที่ตั้ง</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #FEE2E2; color: #EF4444;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="15" y1="9" x2="9" y2="15"/>
                            <line x1="9" y1="9" x2="15" y2="15"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($companiesWithoutLocation); ?></div>
                        <div class="stat-label">ยังไม่มีพิกัด</div>
                    </div>
                </div>
            </div>
            
            <!-- Map Card -->
            <div class="map-card animate-in" style="animation-delay: 0.2s;">
                <div class="map-header">
                    <h3>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/>
                            <line x1="8" y1="2" x2="8" y2="18"/>
                            <line x1="16" y1="6" x2="16" y2="22"/>
                        </svg>
                        แผนที่ตำแหน่งสถานประกอบการ
                    </h3>
                    <div class="map-legend">
                        <div class="legend-item">
                            <div class="legend-dot" style="background: #10B981;"></div>
                            ขนาดเล็ก
                        </div>
                        <div class="legend-item">
                            <div class="legend-dot" style="background: #F59E0B;"></div>
                            ขนาดกลาง
                        </div>
                        <div class="legend-item">
                            <div class="legend-dot" style="background: #EF4444;"></div>
                            ขนาดใหญ่
                        </div>
                    </div>
                </div>
                <div id="companies-map"></div>
            </div>
            
        </div>
    </main>
    
    <script src="<?php echo getBaseUrl(); ?>/assets/js/main.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
    <script>
        // Company data from PHP
        const companies = <?php echo json_encode($companies, JSON_UNESCAPED_UNICODE); ?>;
        const sizeLabels = <?php echo json_encode($sizeLabels, JSON_UNESCAPED_UNICODE); ?>;
        const sizeColors = <?php echo json_encode($sizeColors); ?>;
        
        // Initialize map centered on Thailand
        const map = L.map('companies-map').setView([13.7563, 100.5018], 6);
        
        // Add tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap'
        }).addTo(map);
        
        // Create marker cluster group
        const markers = L.markerClusterGroup({
            maxClusterRadius: 50,
            spiderfyOnMaxZoom: true,
            showCoverageOnHover: false,
            zoomToBoundsOnClick: true
        });
        
        // Create custom icon based on company size
        function createMarkerIcon(size) {
            const color = sizeColors[size] || '#3B82F6';
            return L.divIcon({
                className: 'custom-marker',
                html: `<div style="
                    background: ${color};
                    width: 24px;
                    height: 24px;
                    border-radius: 50% 50% 50% 0;
                    transform: rotate(-45deg);
                    border: 3px solid white;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
                "><div style="
                    background: white;
                    width: 8px;
                    height: 8px;
                    border-radius: 50%;
                    margin: 5px;
                "></div></div>`,
                iconSize: [24, 24],
                iconAnchor: [12, 24],
                popupAnchor: [0, -24]
            });
        }
        
        // Create popup content
        function createPopupContent(company) {
            const sizeLabel = sizeLabels[company.company_size] || 'ไม่ระบุ';
            const sizeColor = sizeColors[company.company_size] || '#6B7280';
            const industries = company.industry_type ? company.industry_type.split('|').slice(0, 2).join(', ') : 'ไม่ระบุ';
            
            return `
                <div class="company-popup">
                    <div class="popup-header">
                        <div class="popup-title">${company.company_name}</div>
                        ${company.company_name_en ? `<div class="popup-subtitle">${company.company_name_en}</div>` : ''}
                    </div>
                    <div class="popup-body">
                        <div class="popup-row">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                            <div class="popup-row-content">
                                <span class="popup-label">ขนาดองค์กร</span>
                                <span class="popup-badge" style="background: ${sizeColor}20; color: ${sizeColor};">${sizeLabel}</span>
                                ${company.employee_count ? ` (${Number(company.employee_count).toLocaleString()} คน)` : ''}
                            </div>
                        </div>
                        ${company.industry_type ? `
                        <div class="popup-row">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/></svg>
                            <div class="popup-row-content">
                                <span class="popup-label">ประเภทธุรกิจ</span>
                                ${industries}
                            </div>
                        </div>
                        ` : ''}
                        ${company.address || company.province ? `
                        <div class="popup-row">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <div class="popup-row-content">
                                <span class="popup-label">ที่อยู่</span>
                                ${company.address ? company.address.substring(0, 80) + (company.address.length > 80 ? '...' : '') : company.province}
                            </div>
                        </div>
                        ` : ''}
                        ${company.contact_name ? `
                        <div class="popup-row">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            <div class="popup-row-content">
                                <span class="popup-label">ผู้ติดต่อ</span>
                                ${company.contact_name}
                                ${company.contact_phone ? `<br><small>${company.contact_phone}</small>` : ''}
                            </div>
                        </div>
                        ` : ''}
                        <div class="popup-footer">
                            <a href="companies.php?view=${company.id}" class="popup-link">
                                ดูรายละเอียด
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                            </a>
                        </div>
                    </div>
                </div>
            `;
        }
        
        // Add markers for each company
        companies.forEach(company => {
            if (company.latitude && company.longitude) {
                const marker = L.marker(
                    [parseFloat(company.latitude), parseFloat(company.longitude)],
                    { icon: createMarkerIcon(company.company_size) }
                );
                
                marker.bindPopup(createPopupContent(company), {
                    maxWidth: 320
                });
                
                // Tooltip on hover
                marker.bindTooltip(company.company_name, {
                    permanent: false,
                    direction: 'top',
                    offset: [0, -20]
                });
                
                markers.addLayer(marker);
            }
        });
        
        map.addLayer(markers);
        
        // Fit map to markers if there are any
        if (companies.length > 0) {
            const bounds = markers.getBounds();
            if (bounds.isValid()) {
                map.fitBounds(bounds, { padding: [50, 50] });
            }
        }
    </script>
</body>
</html>
