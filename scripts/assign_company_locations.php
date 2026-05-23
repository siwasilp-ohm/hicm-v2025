<?php
/**
 * Script to assign random lat/long locations to demo companies
 * Uses real coordinates from various industrial areas in Thailand
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Real industrial area coordinates in Thailand
$industrialLocations = [
    // กรุงเทพและปริมณฑล
    ['name' => 'นิคมอุตสาหกรรมบางชัน', 'lat' => 13.8147, 'lng' => 100.6892],
    ['name' => 'นิคมอุตสาหกรรมลาดกระบัง', 'lat' => 13.7234, 'lng' => 100.7856],
    ['name' => 'นิคมอุตสาหกรรมบางพลี', 'lat' => 13.5891, 'lng' => 100.7234],
    
    // สมุทรปราการ
    ['name' => 'นิคมอุตสาหกรรมบางปู', 'lat' => 13.5234, 'lng' => 100.6567],
    ['name' => 'นิคมอุตสาหกรรมเทพารักษ์', 'lat' => 13.6123, 'lng' => 100.6234],
    
    // ปทุมธานี
    ['name' => 'นิคมอุตสาหกรรมนวนคร', 'lat' => 14.1234, 'lng' => 100.6456],
    ['name' => 'นิคมอุตสาหกรรมบางกะดี', 'lat' => 13.9567, 'lng' => 100.5234],
    
    // อยุธยา
    ['name' => 'นิคมอุตสาหกรรมโรจนะ', 'lat' => 14.2345, 'lng' => 100.6789],
    ['name' => 'นิคมอุตสาหกรรมไฮเทค', 'lat' => 14.3456, 'lng' => 100.5678],
    ['name' => 'นิคมอุตสาหกรรมบางปะอิน', 'lat' => 14.2134, 'lng' => 100.5891],
    
    // ชลบุรี
    ['name' => 'นิคมอุตสาหกรรมอมตะนคร', 'lat' => 13.2567, 'lng' => 101.0234],
    ['name' => 'นิคมอุตสาหกรรมอมตะซิตี้', 'lat' => 13.1234, 'lng' => 101.1567],
    ['name' => 'นิคมอุตสาหกรรมแหลมฉบัง', 'lat' => 13.0891, 'lng' => 100.9234],
    ['name' => 'นิคมอุตสาหกรรมปิ่นทอง', 'lat' => 13.2891, 'lng' => 101.0567],
    
    // ระยอง
    ['name' => 'นิคมอุตสาหกรรมมาบตาพุด', 'lat' => 12.7234, 'lng' => 101.1456],
    ['name' => 'นิคมอุตสาหกรรมอีสเทิร์นซีบอร์ด', 'lat' => 12.8567, 'lng' => 101.2789],
    ['name' => 'นิคมอุตสาหกรรมเหมราช', 'lat' => 12.7891, 'lng' => 101.2234],
    
    // นครราชสีมา (โคราช)
    ['name' => 'นิคมอุตสาหกรรมสุรนารี', 'lat' => 14.9345, 'lng' => 102.0567],
    ['name' => 'นิคมอุตสาหกรรมนวนคร (โคราช)', 'lat' => 14.8567, 'lng' => 102.1234],
    
    // สระบุรี
    ['name' => 'นิคมอุตสาหกรรมแก่งคอย', 'lat' => 14.5891, 'lng' => 101.0234],
    ['name' => 'นิคมอุตสาหกรรมหนองแค', 'lat' => 14.3234, 'lng' => 100.8567],
    
    // ลำพูน
    ['name' => 'นิคมอุตสาหกรรมลำพูน', 'lat' => 18.5234, 'lng' => 99.0123],
    
    // สงขลา
    ['name' => 'นิคมอุตสาหกรรมภาคใต้ (ฉลุง)', 'lat' => 6.9234, 'lng' => 100.4567],
    
    // ขอนแก่น
    ['name' => 'เขตอุตสาหกรรมขอนแก่น', 'lat' => 16.4567, 'lng' => 102.8234],
    
    // ปราจีนบุรี
    ['name' => 'นิคมอุตสาหกรรม 304', 'lat' => 14.0567, 'lng' => 101.3891],
    ['name' => 'เขตอุตสาหกรรมกบินทร์บุรี', 'lat' => 13.9891, 'lng' => 101.7234],
];

$db = getDB();

// Get all companies without location
$stmt = $db->prepare("SELECT id, company_name FROM companies WHERE latitude IS NULL OR longitude IS NULL");
$stmt->execute();
$companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== Assigning Locations to Demo Companies ===\n\n";
echo "Found " . count($companies) . " companies without location\n\n";

$count = 0;
foreach ($companies as $company) {
    // Pick a random industrial location
    $location = $industrialLocations[array_rand($industrialLocations)];
    
    // Add small random offset to make each location unique (within ~1km)
    $latOffset = (mt_rand(-500, 500) / 100000);
    $lngOffset = (mt_rand(-500, 500) / 100000);
    
    $lat = $location['lat'] + $latOffset;
    $lng = $location['lng'] + $lngOffset;
    
    // Update company
    $updateStmt = $db->prepare("UPDATE companies SET latitude = ?, longitude = ? WHERE id = ?");
    $updateStmt->execute([$lat, $lng, $company['id']]);
    
    $count++;
    echo sprintf(
        "%2d. [ID: %d] %s\n    -> %s (%.6f, %.6f)\n\n",
        $count,
        $company['id'],
        mb_substr($company['company_name'], 0, 40),
        $location['name'],
        $lat,
        $lng
    );
}

echo "=== Done! Updated $count companies ===\n";

// Verify
$stmt = $db->prepare("SELECT COUNT(*) as total FROM companies WHERE latitude IS NOT NULL AND longitude IS NOT NULL");
$stmt->execute();
$result = $stmt->fetch();
echo "\nTotal companies with location: " . $result['total'] . "\n";
