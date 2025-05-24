<?php
header('Content-Type: application/json');

if (!isset($_FILES['csvFile'])) {
    echo json_encode(['error' => 'csvFile not set in $_FILES']);
    exit;
}

if ($_FILES['csvFile']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode([
        'error' => 'Upload error',
        'details' => $_FILES['csvFile']['error']
    ]);
    exit;
}

$file = $_FILES['csvFile']['tmp_name'];
$handle = fopen($file, 'r');

if (!$handle) {
    echo json_encode(['error' => 'Cannot open file']);
    exit;
}

$headers = fgetcsv($handle);
$data = [];

while (($row = fgetcsv($handle)) !== false) {
    $assoc = array_combine($headers, $row);
    if (!is_numeric($assoc["Time (s)"] ?? null)) continue;
    $data[] = $assoc;
}

fclose($handle);

echo json_encode([
    'headers' => $headers,
    'data' => $data
]);
?>
