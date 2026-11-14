<?php
declare(strict_types=1);

define('PUBLIC_PAGE', true);
require_once __DIR__ . '/includes/bootstrap.php';

// Allow manual override
if (isset($_GET['view'])) {
    if ($_GET['view'] === 'desktop') {
        header('Location: web/');
        exit;
    }
    if ($_GET['view'] === 'mobile') {
        header('Location: mobile/');
        exit;
    }
}

// Smart routing: mobile/APK users to mobile/, desktop users to web/
if (is_running_in_app() || is_mobile_client()) {
    header('Location: mobile/');
    exit;
} else {
    header('Location: web/');
    exit;
}
