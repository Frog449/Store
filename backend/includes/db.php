<?php
declare(strict_types=1);

/**
 * Database Helper
 * Store Automeation
 */

function db(): PDO
{
    return inventory_db();
}
