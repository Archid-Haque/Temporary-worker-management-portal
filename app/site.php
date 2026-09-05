<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function site_settings(): array
{
    static $settings = null;

    if ($settings !== null) {
        return $settings;
    }

    $settings = [
        'site_name' => 'WorkerLedger',
        'site_logo' => ''
    ];

    try {
        $stmt = db()->query(
            "SELECT setting_key, setting_value
             FROM site_settings"
        );

        foreach ($stmt->fetchAll() as $row) {
            $settings[$row['setting_key']] = $row['setting_value'] ?? '';
        }
    } catch (Throwable $e) {
        // Keep default branding if table is not available yet.
    }

    return $settings;
}

function site_name(): string
{
    $settings = site_settings();

    return trim($settings['site_name'] ?? '') ?: 'WorkerLedger';
}

function site_logo(): string
{
    $settings = site_settings();

    return trim($settings['site_logo'] ?? '');
}