<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Bangkok');

// Disable HTTP caching for all dynamic pages so stock & history updates are always live
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
unset($_SESSION['quote']);

require_once __DIR__ . '/inventory.php';
require_once __DIR__ . '/auth.php';

// Enforce authentication on all protected pages unless explicitly marked public
if (!defined('PUBLIC_PAGE') || PUBLIC_PAGE !== true) {
    auth_require_login();
}

$currentUser = auth_user();
$products = inventory_products();
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$csrfToken = h($_SESSION['csrf_token']);
$notice = '';
$noticeType = 'success';

if (isset($_SESSION['flash_notice'])) {
    $flashNotice = $_SESSION['flash_notice'];
    unset($_SESSION['flash_notice']);
    if (is_array($flashNotice) && isset($flashNotice['message'], $flashNotice['type'])) {
        $notice = (string) $flashNotice['message'];
        $noticeType = $flashNotice['type'] === 'error' ? 'error' : 'success';
    }
}

function is_running_in_app(): bool
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (str_contains($ua, 'StoreAutomeationApp')) {
        return true;
    }
    if (isset($_COOKIE['is_standalone']) && $_COOKIE['is_standalone'] === '1') {
        return true;
    }
    return false;
}

function is_mobile_client(): bool
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (str_contains($ua, 'StoreAutomeationApp')) {
        return true;
    }
    return (bool) preg_match('/(android|iphone|ipad|ipod|mobile|blackberry|iemobile|opera mini)/i', $ua);
}
