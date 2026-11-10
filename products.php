<?php
declare(strict_types=1);

$editCode = $_GET['edit'] ?? null;
$query = is_string($editCode) && $editCode !== ''
    ? '?' . http_build_query(['edit' => $editCode])
    : '';
header('Location: catalog.php' . $query, true, 302);
exit;
