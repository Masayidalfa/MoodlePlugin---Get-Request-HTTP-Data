<?php
require_once('../../config.php');

defined('MOODLE_INTERNAL') || die();

// Hanya terima request POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Ambil data sesuai Content-Type
$contentType = $_SERVER["CONTENT_TYPE"] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $rawdata = file_get_contents('php://input');
    $data = json_decode($rawdata, true) ?: $_POST;
} elseif (strpos($contentType, 'application/x-www-form-urlencoded') !== false || strpos($contentType, 'multipart/form-data') !== false) {
    $data = $_POST;
} else {
    parse_str(file_get_contents("php://input"), $data);
}

// Ambil informasi request
$userid         = isset($data['userid']) ? (int)$data['userid'] : 0;
$ip             = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$url            = $_SERVER['HTTP_REFERER'] ?? 'unknown';
$request_method = $_SERVER['REQUEST_METHOD'] ?? 'unknown';

// Decode pageurl jika tersedia
$decodedPageUrl = '';
if (isset($data['pageurl'])) {
    $decodedPageUrl = urldecode($data['pageurl']);
    $data['pageurl'] = $decodedPageUrl;
}

// Buat timestamp dengan format presisi milidetik
$micro     = microtime(true);
$datetime  = date("d/m/Y H:i:s", (int)$micro);
$millisec  = sprintf("%03d", ($micro - floor($micro)) * 1000);
$timestamp = $datetime . '.' . $millisec;

// Susun payload
$payloadData = [
    "method" => $request_method,
    "url"    => $decodedPageUrl ?: $url,
    "body"   => $data
];

$record = new \stdClass();
$record->userid       = $userid;
$record->ip           = $ip;
$record->timestamp    = $timestamp;
$record->payloadData  = $payloadData;

// Kirim respon JSON ke klien
header('Content-Type: application/json');
echo json_encode(['status' => 'success']);

// Publish log ke Redis (jika tersedia)
try {
    $host = get_config('local_requestlogger', 'redis_host') ?: '127.0.0.1';
    $port = get_config('local_requestlogger', 'redis_port') ?: 6379;
    $channel = get_config('local_requestlogger', 'redis_channel') ?: 'moodle_logs';

    $redis = new \Redis();
    $redis->connect($host, (int)$port);
    $redis->publish($channel, json_encode($record));

} catch (\Throwable $e) {
    error_log('Redis error (log.php): ' . $e->getMessage());
}

?>