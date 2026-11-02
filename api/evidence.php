<?php
/**
 * API Evidence Streamer / Cloudflare R2 Gateway
 * /api/evidence.php?key=evidence/...
 * 
 * Melayani tampilan foto bukti visual secara tersentralisasi dari Cloudflare R2:
 * 1. Jika ada di cache lokal, kirim instan (0 ms).
 * 2. Jika sudah ter-offload ke Cloudflare R2, ambil via S3 API resmi Cloudflare dengan SigV4.
 * 3. Bekerja 100% tanpa terpengaruh blokir domain dev oleh ISP lokal.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$key = $_GET['key'] ?? '';
$id  = $_GET['id'] ?? '';
$path = $_GET['path'] ?? '';

$pdo = getDBConnection();

// Jika dipanggil via ID event
if ($id && !$key) {
    $stmt = $pdo->prepare("SELECT snapshot_path, metadata_json FROM counting_events WHERE id = ?");
    $stmt->execute([$id]);
    $ev = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($ev) {
        $snapPath = $ev['snapshot_path'] ?? '';
        // Prioritas 1: ambil r2_key dari metadata_json
        if (!empty($ev['metadata_json'])) {
            $m = json_decode($ev['metadata_json'], true);
            if (!empty($m['r2_key'])) {
                $key = $m['r2_key'];
            }
        }
        // Prioritas 2: snapshot_path sudah berupa R2 key (evidence/YYYY-MM-DD/IN|OUT/...)
        if (!$key && $snapPath && strpos($snapPath, 'evidence/') !== false && strpos($snapPath, 'http') === false) {
            $key = $snapPath;
        }
        // Prioritas 3: snapshot_path berupa URL r2.dev — ekstrak path-nya
        if (!$key && $snapPath && strpos($snapPath, 'r2.dev') !== false) {
            $parsed = parse_url($snapPath);
            $key = ltrim($parsed['path'] ?? '', '/');
        }
        // Prioritas 4: snapshot_path berupa local path
        if (!$key && $snapPath) {
            $path = $snapPath;
        }
    }
}

// Ekstrak r2_key dari path
if ($path && !$key) {
    if (strpos($path, 'evidence/') !== false) {
        $key = substr($path, strpos($path, 'evidence/'));
    } else {
        $key = ltrim($path, '/');
    }
}

// Cek apakah ada file lokal
if ($key) {
    $localPath = __DIR__ . '/../uploads/' . ltrim(str_replace('evidence/', 'evidence/', $key), '/');
    if (file_exists($localPath)) {
        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=604800');
        readfile($localPath);
        exit;
    }
}

// Ambil pengaturan Cloudflare R2 dari database
$settings = getAllSettings();
$accountId = $settings['r2_account_id'] ?? '';
$bucket    = $settings['r2_bucket'] ?? 'countiva';
$accessKey = $settings['r2_access_key_id'] ?? '';
$secretKey = $settings['r2_secret_access_key'] ?? '';

if (!$key || !$accountId || !$accessKey || !$secretKey) {
    // Tampilkan placeholder avatar jika tidak ada
    header('Content-Type: image/svg+xml');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>';
    exit;
}

// Ambil langsung dari Cloudflare R2 via SigV4 S3 API
$host = "$accountId.r2.cloudflarestorage.com";
$region = 'auto';
$service = 's3';

$amzDate = gmdate('Ymd\THis\Z');
$dateStamp = gmdate('Ymd');

$canonicalUri = '/' . $bucket . '/' . ltrim($key, '/');
$canonicalQuery = '';
$payloadHash = hash('sha256', '');

$canonicalHeaders = "host:$host\nx-amz-content-sha256:$payloadHash\nx-amz-date:$amzDate\n";
$signedHeaders = 'host;x-amz-content-sha256;x-amz-date';

$canonicalRequest = "GET\n$canonicalUri\n$canonicalQuery\n$canonicalHeaders\n$signedHeaders\n$payloadHash";

$algorithm = 'AWS4-HMAC-SHA256';
$credentialScope = "$dateStamp/$region/$service/aws4_request";
$stringToSign = "$algorithm\n$amzDate\n$credentialScope\n" . hash('sha256', $canonicalRequest);

$kSecret = 'AWS4' . $secretKey;
$kDate = hash_hmac('sha256', $dateStamp, $kSecret, true);
$kRegion = hash_hmac('sha256', $region, $kDate, true);
$kService = hash_hmac('sha256', $service, $kRegion, true);
$kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

$signature = hash_hmac('sha256', $stringToSign, $kSigning);

$authorization = "$algorithm Credential=$accessKey/$credentialScope, SignedHeaders=$signedHeaders, Signature=$signature";

$url = "https://$host$canonicalUri";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 6);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Host: $host",
    "x-amz-date: $amzDate",
    "x-amz-content-sha256: $payloadHash",
    "Authorization: $authorization"
]);

$imageData = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200 && !empty($imageData)) {
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=604800'); // Cache di browser selama 7 hari
    echo $imageData;
    exit;
}

// Fallback jika tidak ditemukan
http_response_code(404);
header('Content-Type: image/svg+xml');
echo '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>';
exit;
