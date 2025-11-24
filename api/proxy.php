<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$bid = $_GET['bid'] ?? '';
$fbclid = $_GET['fbclid'] ?? '';

if (empty($bid)) {
    echo json_encode(['error' => 'Missing bid parameter']);
    exit;
}

$url = 'https://nlpbds.com/getbc/?bid=' . urlencode($bid);
if (!empty($fbclid)) {
    $url .= '&fbclid=' . urlencode($fbclid);
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'PHP Script');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For development, if SSL issues

$json = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($json === false || $httpCode !== 200) {
    echo json_encode(['error' => 'Failed to fetch API', 'http_code' => $httpCode]);
    exit;
}

echo $json;
?>