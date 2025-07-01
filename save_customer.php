<?php
header('Content-Type: application/json');

// Required fields
$required = ['customerId','customerName','fatherName','dob','address','mobileNo'];
foreach ($required as $f) {
    if (empty($_POST[$f])) {
        echo json_encode(['status'=>'error','message'=>"Missing required field: $f"]);
        exit;
    }
}

// Server-side validation
if (!preg_match('/^[A-Za-z\s.\-\']+$/', $_POST['customerName'])) {
    echo json_encode(['status'=>'error','message'=>'Invalid customer name']);
    exit;
}

if (!preg_match('/^[A-Za-z\s.\-\']+$/', $_POST['fatherName'])) {
    echo json_encode(['status'=>'error','message'=>'Invalid father/spouse name']);
    exit;
}

if (!preg_match('/^\d{10}$/', $_POST['mobileNo'])) {
    echo json_encode(['status'=>'error','message'=>'Invalid mobile number']);
    exit;
}

if (!empty($_POST['altMobileNo']) && !preg_match('/^\d{10}$/', $_POST['altMobileNo'])) {
    echo json_encode(['status'=>'error','message'=>'Invalid alternate mobile number']);
    exit;
}

if (!empty($_POST['revenue']) && !preg_match('/^\d+(\.\d{1,2})?$/', $_POST['revenue'])) {
    echo json_encode(['status'=>'error','message'=>'Revenue must be a valid number']);
    exit;
}

if (!empty($_POST['loanLimit']) && !preg_match('/^\d+(\.\d{1,2})?$/', $_POST['loanLimit'])) {
    echo json_encode(['status'=>'error','message'=>'Loan limit must be a valid number']);
    exit;
}

// Load existing customers.json
$dataFile = __DIR__ . '/customers.json';
if (!file_exists($dataFile)) {
    file_put_contents($dataFile, json_encode([], JSON_PRETTY_PRINT));
}
$data = json_decode(file_get_contents($dataFile), true);

// Prevent duplicates (custid & mobile)
foreach ($data as $rec) {
    if ($rec['cusid'] === $_POST['customerId']) {
        exit(json_encode(['status'=>'error','message'=>'Customer ID already exists']));
    }
    if ($rec['cno'] === $_POST['mobileNo']) {
        exit(json_encode(['status'=>'error','message'=>'Mobile number already registered']));
    }
}

// Build proofs array
$proofs = [];
for ($i = 1; $i <= 3; $i++) {
    $type = trim($_POST["proofType$i"] ?? '');
    $num = trim($_POST["proofNumber$i"] ?? '');
    if ($type !== '' && $num !== '') {
        $proofs[] = "$type:$num";
    }
}
$proofsStr = implode(',', $proofs);
//eid
$eids = array_column($data, 'eid');
$eids = array_map('intval', $eids);
$lastEid = max($eids);
$newEid = $lastEid + 1;


// Prepare new record
$new = [
    'eid'           => $lastEid+1,
    'cusid'         => $_POST['customerId'],
    'cname'         => $_POST['customerName'],
    'fname'         => $_POST['fatherName'],
    'gender'        => $_POST['gender'] ?? '',
    'cdate'         => date('Y-m-d'),
    'caddress'      => $_POST['address'],
    'cno'           => preg_replace('/\D/', '', $_POST['mobileNo']),
    'dob'           => date("d/m/Y", strtotime($_POST['dob'])),
    'emailid'       => $_POST['email'] ?? '',
    'proofs'        => $proofsStr,
    'cno2'          => $_POST['altMobileNo'] ?? '',
    'loanlimit'     => !empty($_POST['loanLimit']) ? floatval($_POST['loanLimit']) : 0,
    'occupation'    => ($_POST['occupation'] === 'Other')
                        ? ($_POST['otherOccupation'] ?? '')
                        : ($_POST['occupation'] ?? ''),
    'qualification' => ($_POST['qualification'] === 'Other')
                        ? ($_POST['otherQualification'] ?? '')
                        : ($_POST['qualification'] ?? ''),
    'revenue'       => $_POST['revenue'] ?? '',
    'remark1'       => $_POST['remarks'] ?? '',
    'introcat'      => $_POST['introducer'] ?? '',
    'introname'     => $_POST['introducerName'] ?? '',
    'status'        => 'a',
    'username'      => 'admin',
    'fdt'           => date("d/m/Y h:i:s A")
];

// Helper to map MIME to extension
function mimeToExt(string $mime): string {
    $map = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
    ];
    return $map[$mime] ?? 'bin';
}

// Create directories if needed
$uploadDir = __DIR__ . '/uploads/';
$photoDir = $uploadDir . 'photos' . DIRECTORY_SEPARATOR;
$signatureDir = $uploadDir . 'signatures' .DIRECTORY_SEPARATOR;

if (!is_dir($photoDir)) mkdir($photoDir, 0777, true);
if (!is_dir($signatureDir)) mkdir($signatureDir, 0777, true);

// Handle file uploads
$finfo = new finfo(FILEINFO_MIME_TYPE);
foreach (['photo' => 100*1024, 'signature' => 50*1024] as $field => $maxSize) {
    if (!empty($_FILES[$field]['tmp_name']) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
        if ($_FILES[$field]['size'] <= $maxSize) {
            $mime = $finfo->file($_FILES[$field]['tmp_name']);
            $ext = mimeToExt($mime);
            $filename = $_POST['customerId'] . '.' . $ext;
            
            if ($field === 'photo') {
                $dest = $photoDir . $filename;
                $new["photo_path"] = 'photos/' . $filename;
            } else {
                $dest = $signatureDir . $filename;
                $new["signature_path"] = 'signatures/' . $filename;
            }
            
            if (move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) {
                // File uploaded successfully
            }
        }
    }
}
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Append & save
$data[] = $new;
if (file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT))) {
    echo json_encode(['status'=>'success','message'=>'Customer added successfully!']);
} else {
    echo json_encode(['status'=>'error','message'=>'Failed to save data']);
}
?>
