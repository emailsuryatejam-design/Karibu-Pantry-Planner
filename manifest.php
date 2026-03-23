<?php
/**
 * Dynamic PWA manifest — returns camp-specific start_url and name
 * Usage: manifest.php?kitchen=SWC
 */
header('Content-Type: application/manifest+json');
header('Cache-Control: no-cache');

$kitchen = strtoupper(trim($_GET['kitchen'] ?? ''));

$kitchenNames = [
    'NLP' => 'Ngorongoro Lions Paw',
    'SWC' => 'Serengeti Woodlands Camp',
    'SSC' => 'Serengeti Safari Camp',
    'SRC' => 'Serengeti River Camp',
    'TES' => 'Tarangire Elephant Springs',
];

$name = $kitchen && isset($kitchenNames[$kitchen])
    ? 'Karibu - ' . $kitchenNames[$kitchen]
    : 'Karibu Pantry Planner';

$shortName = $kitchen && isset($kitchenNames[$kitchen])
    ? 'Karibu ' . $kitchen
    : 'Karibu Pantry';

$startUrl = $kitchen ? "/{$kitchen}/" : '/app.php';

echo json_encode([
    'name' => $name,
    'short_name' => $shortName,
    'description' => 'Kitchen requisition and pantry management',
    'start_url' => $startUrl,
    'display' => 'standalone',
    'background_color' => '#f9fafb',
    'theme_color' => '#ea580c',
    'orientation' => 'any',
    'icons' => [
        [
            'src' => '/assets/icons/icon-192.png',
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any maskable',
        ],
        [
            'src' => '/assets/icons/icon-512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any maskable',
        ],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
