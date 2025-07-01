<?php
header('Content-Type: application/json');

$type = $_POST['type'] ?? '';
$value = trim($_POST['value'] ?? '');

if (!$type || !$value) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
    exit;
}

$file = 'custom_options.json';
$data = [];

if (file_exists($file)) {
    $data = json_decode(file_get_contents($file), true);
} else {
    $data = ['qualifications' => [], 'occupations' => []];
}

// Add to the appropriate array if it doesn't already exist
if ($type === 'qualification' && !in_array($value, $data['qualifications'])) {
    $data['qualifications'][] = $value;
} elseif ($type === 'occupation' && !in_array($value, $data['occupations'])) {
    $data['occupations'][] = $value;
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid type or duplicate value']);
    exit;
}

if (file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT))) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to save data']);
}
?>