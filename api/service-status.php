<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    $services = db()->safeQuery(
        "SELECT id, slug, name, category, is_enabled, maintenance_message
         FROM services
         ORDER BY FIELD(slug, 'airtime', 'data', 'cable_tv', 'electricity', 'data_card', 'exam_pin', 'recharge_card', 'bulk_sms'), name"
    );

    $result = array_map(static function (array $service): array {
        $isEnabled = (int) $service['is_enabled'] === 1;
        $maintenanceMsg = $service['maintenance_message'] ?? '';
        $isUnderMaintenance = !$isEnabled && $maintenanceMsg !== '';

        return [
            'id' => (int) $service['id'],
            'slug' => $service['slug'],
            'name' => $service['name'],
            'category' => $service['category'],
            'status' => $isUnderMaintenance ? 'maintenance' : ($isEnabled ? 'active' : 'disabled'),
            'maintenance_message' => $isUnderMaintenance ? $maintenanceMsg : null,
            'available' => $isEnabled,
        ];
    }, $services);

    echo json_encode([
        'status' => true,
        'data' => $result,
    ], JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Service temporarily unavailable.']);
}
