<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/csv-export.php';
$user = require_role('admin');

$alerts = db()->query(
    "SELECT ca.visitor_name AS name, ca.visitor_phone AS phone, ca.visitor_email AS email,
            GROUP_CONCAT(s.name SEPARATOR ', ') AS species,
            ca.min_weight_kg, ca.max_weight_kg, ca.is_active, ca.created_at
     FROM catch_alerts ca
     LEFT JOIN catch_alert_species cas ON cas.alert_id = ca.id
     LEFT JOIN species s ON s.id = cas.species_id
     GROUP BY ca.id
     ORDER BY ca.created_at DESC"
)->fetchAll();

foreach ($alerts as &$row) {
    $row['created_at'] = utc_to_local($row['created_at'], 'Y-m-d H:i');
    $row['is_active'] = $row['is_active'] ? 'active' : 'unsubscribed';
    $row['species'] = $row['species'] ?: 'any';
}

stream_csv($alerts, 'capitony-customers-' . date('Y-m-d') . '.csv');
