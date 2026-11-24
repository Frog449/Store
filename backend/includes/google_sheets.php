<?php
declare(strict_types=1);

/**
 * Google Sheets Webhook Real-time Synchronization Service
 * Store Automeation (delegates to unified sync functions in inventory.php)
 */

function google_sheets_get_settings(): array
{
    return inventory_get_settings();
}

function google_sheets_post(array $payload): array
{
    return inventory_post_google_sheet($payload);
}

function google_sheets_sync_movement(array $movement, string $kind, ?array $product = null, bool $isNewProduct = false): array
{
    return inventory_sync_google_sheet_movement($movement, $kind, $product, $isNewProduct);
}

function google_sheets_sync_borrow(array $record, string $actionType = 'borrow'): array
{
    return inventory_sync_google_sheet($record, $actionType);
}

function google_sheets_sync_product(array $product): array
{
    return inventory_sync_google_sheet_product($product);
}

function google_sheets_sync_all_catalog(): array
{
    return inventory_sync_all_catalog_to_google_sheet();
}
