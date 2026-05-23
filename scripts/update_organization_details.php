<?php
/**
 * Update Organizations with Contact Details
 * เพิ่มข้อมูลติดต่อให้หน่วยงานภาคีเครือข่าย
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    $pdo = $db->getConnection();
    
    // Organizations with full details
    $organizations = [
        1 => [
            'name' => 'สำนักสนับสนุนสุขภาวะองค์กร (สำนัก 8)',
            'description' => 'หน่วยงานภายใต้ สสส. ดูแลด้านสุขภาวะองค์กรและสถานประกอบการ ส่งเสริมให้องค์กรมีสภาพแวดล้อมที่เอื้อต่อการมีสุขภาพดี',
            'contact_phone' => '02-343-1500',
            'contact_email' => 'office8@thaihealth.or.th',
            'address' => '99/8 อาคารศูนย์เรียนรู้สุขภาวะ ถ.สาทรใต้ แขวงยานนาวา เขตสาทร กรุงเทพฯ 10120',
            'website' => 'https://www.thaihealth.or.th'
        ],
        2 => [
            'name' => 'สำนักงานแรงงานจังหวัดนครราชสีมา-กระทรวงแรงงาน',
            'description' => 'หน่วยงานราชการส่วนภูมิภาคสังกัดกระทรวงแรงงาน ดูแลด้านแรงงานและการจ้างงานในจังหวัดนครราชสีมา',
            'contact_phone' => '044-243-098',
            'contact_email' => 'nakhonratchasima@mol.go.th',
            'address' => 'ศาลากลางจังหวัดนครราชสีมา ชั้น 3 ถ.มหาดไทย ต.ในเมือง อ.เมือง จ.นครราชสีมา 30000',
            'website' => 'https://nakhonratchasima.mol.go.th'
        ],
        3 => [
            'name' => 'สำนักงานสาธารณสุขจังหวัดนครราชสีมา',
            'description' => 'หน่วยงานราชการดูแลด้านสาธารณสุข การส่งเสริมสุขภาพ และป้องกันโรคในพื้นที่จังหวัดนครราชสีมา',
            'contact_phone' => '044-465-010',
            'contact_email' => 'saraban_nmss@health.moph.go.th',
            'address' => '255 ถ.มิตรภาพ ต.ในเมือง อ.เมือง จ.นครราชสีมา 30000',
            'website' => 'https://korathealth.com'
        ],
        4 => [
            'name' => 'องค์การบริหารส่วนจังหวัดนครราชสีมา',
            'description' => 'องค์กรปกครองส่วนท้องถิ่นระดับจังหวัด ดูแลด้านการพัฒนาท้องถิ่นและบริการสาธารณะ',
            'contact_phone' => '044-251-111',
            'contact_email' => 'saraban@koratpao.go.th',
            'address' => '1111 ถ.มิตรภาพ ต.ในเมือง อ.เมือง จ.นครราชสีมา 30000',
            'website' => 'https://www.koratpao.go.th'
        ],
        5 => [
            'name' => 'ศูนย์อนามัยที่ 9 นครราชสีมา',
            'description' => 'หน่วยงานในสังกัดกรมอนามัย ดูแลด้านส่งเสริมสุขภาพและอนามัยสิ่งแวดล้อมในเขตสุขภาพที่ 9',
            'contact_phone' => '044-305-131',
            'contact_email' => 'hpc9@anamai.mail.go.th',
            'address' => '177 หมู่ 6 ต.โคกกรวด อ.เมือง จ.นครราชสีมา 30280',
            'website' => 'https://hpc9.anamai.moph.go.th'
        ],
        6 => [
            'name' => 'สำนักงานประกันสังคมจังหวัดนครราชสีมา',
            'description' => 'หน่วยงานดูแลด้านประกันสังคม สิทธิประโยชน์ และคุ้มครองผู้ประกันตนในจังหวัดนครราชสีมา',
            'contact_phone' => '044-213-266',
            'contact_email' => 'nakhonratchasima@sso.go.th',
            'address' => '369/5 ถ.สุรนารี ต.ในเมือง อ.เมือง จ.นครราชสีมา 30000',
            'website' => 'https://www.sso.go.th/wpr/nakhonratchasima'
        ],
        7 => [
            'name' => 'ศูนย์ส่งเสริมอุตสาหกรรมภาคที่ 6',
            'description' => 'หน่วยงานสนับสนุนและพัฒนาอุตสาหกรรมในภาคตะวันออกเฉียงเหนือตอนล่าง',
            'contact_phone' => '044-233-404',
            'contact_email' => 'ipc6@dip.go.th',
            'address' => '333 หมู่ 7 ถ.มิตรภาพ ต.หนองไผ่ล้อม อ.เมือง จ.นครราชสีมา 30000',
            'website' => 'https://ipc6.dip.go.th'
        ],
        8 => [
            'name' => 'สำนักงานสวัสดิการและคุ้มครองแรงงานจังหวัดนครราชสีมา',
            'description' => 'หน่วยงานดูแลด้านสวัสดิการแรงงาน ความปลอดภัยในการทำงาน และคุ้มครองแรงงาน',
            'contact_phone' => '044-243-217',
            'contact_email' => 'nakhonratchasima@labour.mail.go.th',
            'address' => 'ศาลากลางจังหวัดนครราชสีมา ชั้น 3 ถ.มหาดไทย ต.ในเมือง อ.เมือง จ.นครราชสีมา 30000',
            'website' => 'https://nakhonratchasima.labour.go.th'
        ],
        9 => [
            'name' => 'ศูนย์ความปลอดภัยในการทำงานเขต 3 (นครราชสีมา)',
            'description' => 'หน่วยงานส่งเสริมและพัฒนาความปลอดภัยอาชีวอนามัยในสถานประกอบการ',
            'contact_phone' => '044-212-048',
            'contact_email' => 'oshc3@labour.mail.go.th',
            'address' => '204 ถ.มิตรภาพ ต.ในเมือง อ.เมือง จ.นครราชสีมา 30000',
            'website' => 'https://www.oshthai.org'
        ],
        10 => [
            'name' => 'สำนักงานอุตสาหกรรมจังหวัดนครราชสีมา',
            'description' => 'หน่วยงานดูแลด้านการส่งเสริมอุตสาหกรรม กำกับโรงงาน และพัฒนาผู้ประกอบการ',
            'contact_phone' => '044-212-430',
            'contact_email' => 'nakhonratchasima@industry.go.th',
            'address' => 'ศาลากลางจังหวัดนครราชสีมา ชั้น 4 ถ.มหาดไทย ต.ในเมือง อ.เมือง จ.นครราชสีมา 30000',
            'website' => 'https://nakhonratchasima.industry.go.th'
        ],
        11 => [
            'name' => 'ศูนย์สุขภาพจิตที่ 9',
            'description' => 'หน่วยงานส่งเสริมสุขภาพจิตและป้องกันปัญหาสุขภาพจิตในเขตสุขภาพที่ 9',
            'contact_phone' => '044-256-729',
            'contact_email' => 'mhc9@dmh.mail.go.th',
            'address' => '86 ถ.ช้างเผือก ต.ในเมือง อ.เมือง จ.นครราชสีมา 30000',
            'website' => 'https://mhc9.dmh.go.th'
        ],
        12 => [
            'name' => 'สำนักงานป้องกันควบคุมโรคที่ 9 นครราชสีมา',
            'description' => 'หน่วยงานเฝ้าระวัง ป้องกัน และควบคุมโรคในเขตสุขภาพที่ 9',
            'contact_phone' => '044-212-900',
            'contact_email' => 'dpc9@ddc.mail.go.th',
            'address' => '168/5 ถ.มิตรภาพ ต.ในเมือง อ.เมือง จ.นครราชสีมา 30000',
            'website' => 'https://dpc9.ddc.moph.go.th'
        ],
        13 => [
            'name' => 'สถาบันพัฒนาฝีมือแรงงาน 5 นครราชสีมา',
            'description' => 'หน่วยงานพัฒนาทักษะฝีมือแรงงานและยกระดับมาตรฐานฝีมือ',
            'contact_phone' => '044-416-155',
            'contact_email' => 'dsd5@dsd.go.th',
            'address' => '340 หมู่ 3 ถ.มิตรภาพ ต.หนองไข่น้ำ อ.เมือง จ.นครราชสีมา 30310',
            'website' => 'https://www.dsd.go.th/nakhonratchasima'
        ],
        14 => [
            'name' => 'การนิคมอุตสาหกรรมแห่งประเทศไทย',
            'description' => 'รัฐวิสาหกิจดูแลนิคมอุตสาหกรรมและเขตประกอบการอุตสาหกรรมทั่วประเทศ',
            'contact_phone' => '02-253-0561',
            'contact_email' => 'contact@ieat.go.th',
            'address' => '618 ถ.นิคมมักกะสัน แขวงมักกะสัน เขตราชเทวี กรุงเทพฯ 10400',
            'website' => 'https://www.ieat.go.th'
        ],
        15 => [
            'name' => 'จังหวัดนครราชสีมา',
            'description' => 'หน่วยงานราชการส่วนภูมิภาคระดับจังหวัด ดูแลบริหารราชการในพื้นที่',
            'contact_phone' => '044-243-798',
            'contact_email' => 'nakhonratchasima@moi.go.th',
            'address' => 'ศาลากลางจังหวัดนครราชสีมา ถ.มหาดไทย ต.ในเมือง อ.เมือง จ.นครราชสีมา 30000',
            'website' => 'https://www.nakhonratchasima.go.th'
        ],
        16 => [
            'name' => 'สภาอุตสาหกรรมแห่งประเทศไทย',
            'description' => 'องค์กรเอกชนด้านอุตสาหกรรม เป็นตัวแทนภาคอุตสาหกรรมในการพัฒนาและแก้ไขปัญหา',
            'contact_phone' => '02-345-1000',
            'contact_email' => 'information@fti.or.th',
            'address' => 'ศูนย์การประชุมแห่งชาติสิริกิติ์ โซน C ชั้น 4 เลขที่ 60 ถ.รัชดาภิเษกตัดใหม่ แขวงคลองเตย เขตคลองเตย กรุงเทพฯ 10110',
            'website' => 'https://www.fti.or.th'
        ],
        17 => [
            'name' => 'กระทรวงดิจิทัลเพื่อเศรษฐกิจและสังคม',
            'description' => 'กระทรวงดูแลด้านเทคโนโลยีดิจิทัล การพัฒนาเศรษฐกิจและสังคมดิจิทัล',
            'contact_phone' => '02-141-6747',
            'contact_email' => 'contact@mdes.go.th',
            'address' => '120 หมู่ 3 อาคารรัฐประศาสนภักดี (อาคาร B) ศูนย์ราชการฯ ถ.แจ้งวัฒนะ แขวงทุ่งสองห้อง เขตหลักสี่ กรุงเทพฯ 10210',
            'website' => 'https://www.mdes.go.th'
        ],
        18 => [
            'name' => 'สภาอุตสาหกรรมจังหวัดนครราชสีมา',
            'description' => 'องค์กรเอกชนระดับจังหวัด เป็นตัวแทนภาคอุตสาหกรรมในจังหวัดนครราชสีมา',
            'contact_phone' => '044-261-335',
            'contact_email' => 'fti.korat@gmail.com',
            'address' => '2112/11 ถ.มิตรภาพ ต.ในเมือง อ.เมือง จ.นครราชสีมา 30000',
            'website' => 'https://www.fti.or.th/fti-chapter/nakhonratchasima'
        ]
    ];
    
    // Update each organization
    $updateStmt = $pdo->prepare("
        UPDATE organizations 
        SET description = ?, 
            contact_phone = ?, 
            contact_email = ?, 
            address = ?, 
            website = ?
        WHERE id = ?
    ");
    
    $count = 0;
    foreach ($organizations as $id => $org) {
        $updateStmt->execute([
            $org['description'],
            $org['contact_phone'],
            $org['contact_email'],
            $org['address'],
            $org['website'],
            $id
        ]);
        $count++;
        echo "✓ Updated: {$org['name']}\n";
    }
    
    echo "\n✅ Successfully updated {$count} organizations with contact details.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
