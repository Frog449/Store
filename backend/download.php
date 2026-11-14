<?php
declare(strict_types=1);

define('PUBLIC_PAGE', true);
require __DIR__ . '/includes/bootstrap.php';

$type = (string) ($_GET['file'] ?? 'apk');

if ($type !== 'apk') {
    http_response_code(404);
    die('File not found');
}

$filePath = __DIR__ . '/downloads/store-automeation.apk';
$fileName = 'store-automeation.apk';

if (!file_exists($filePath) || !is_readable($filePath)) {
    http_response_code(404);
    die('APK file not found on server');
}

// Clean any previous output buffer to avoid corrupted bytes
while (ob_get_level() > 0) {
    ob_end_clean();
}

$fileSize = filesize($filePath);

header('Content-Description: File Transfer');
header('Content-Type: application/vnd.android.package-archive');
header('Content-Disposition: attachment; filename="' . $fileName . '"; filename*=UTF-8\'\'' . rawurlencode($fileName));
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: public, must-revalidate, max-age=0');
header('Pragma: public');
header('Content-Length: ' . (string) $fileSize);
header('Accept-Ranges: bytes');
header('X-Content-Type-Options: nosniff');

$handle = fopen($filePath, 'rb');
if ($handle !== false) {
    while (!feof($handle)) {
        echo fread($handle, 65536);
        flush();
    }
    fclose($handle);
} else {
    readfile($filePath);
}
exit;

