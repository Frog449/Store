<?php
declare(strict_types=1);

define('PUBLIC_PAGE', true);

require __DIR__ . '/../includes/bootstrap.php';

auth_logout();
header('Location: login.php');
exit;

