<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$filename = isset($_GET['file']) && is_string($_GET['file']) ? trim($_GET['file']) : '';

if (!preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $filename)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Bad Request';
    exit;
}

// 1. Check local cache on disk first
$cachedPath = __DIR__ . '/storage/product-images/' . $filename;
if (is_file($cachedPath) && is_readable($cachedPath)) {
    $etag = '"' . md5_file($cachedPath) . '"';
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
        http_response_code(304);
        exit;
    }

    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $mimeType = match ($ext) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        default => 'application/octet-stream',
    };
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . (string) filesize($cachedPath));
    header('Cache-Control: public, max-age=31536000, immutable');
    header('ETag: ' . $etag);
    readfile($cachedPath);
    exit;
}

// 2. Fetch from database
try {
    $db = inventory_db();
    $stmt = $db->prepare('SELECT `mime_type`, `data` FROM `product_images` WHERE `filename` = :filename');
    $stmt->execute(['filename' => $filename]);
    $image = $stmt->fetch();

    if ($image && !empty($image['data'])) {
        $mimeType = (string) $image['mime_type'];
        $data = $image['data'];
        $etag = '"' . md5($data) . '"';

        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
            http_response_code(304);
            exit;
        }

        // Cache to disk for subsequent requests
        $dir = __DIR__ . '/storage/product-images';
        if (is_dir($dir) || @mkdir($dir, 0755, true)) {
            @file_put_contents($cachedPath, $data);
        }

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . (string) strlen($data));
        header('Cache-Control: public, max-age=31536000, immutable');
        header('ETag: ' . $etag);
        echo $data;
        exit;
    }
} catch (Throwable $e) {
    error_log('Error loading product image: ' . $e->getMessage());
}

// 3. Not found
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Image Not Found';
exit;

