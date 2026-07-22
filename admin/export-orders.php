<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/csv-export.php';
$user = require_role('admin');

$orders = db()->query(
    "SELECT og.id AS order_id, og.visitor_name, og.visitor_phone, og.status,
            og.total_price_aed, og.delivery_fee_aed, og.created_at,
            o.sku, s.name AS species_name, o.quantity_kg,
            o.service_pickup, o.service_deliver, o.service_clean, o.service_cook,
            o.total_price_aed AS line_price_aed
     FROM order_groups og
     JOIN orders o ON o.order_group_id = og.id
     JOIN catch_items ci ON ci.id = o.catch_item_id
     JOIN species s ON s.id = ci.species_id
     ORDER BY og.created_at DESC, og.id"
)->fetchAll();

foreach ($orders as &$row) {
    $row['created_at'] = utc_to_local($row['created_at'], 'Y-m-d H:i');
    $row['service_pickup'] = $row['service_pickup'] ? 'yes' : 'no';
    $row['service_deliver'] = $row['service_deliver'] ? 'yes' : 'no';
    $row['service_clean'] = $row['service_clean'] ? 'yes' : 'no';
    $row['service_cook'] = $row['service_cook'] ? 'yes' : 'no';
}

stream_csv($orders, 'capitony-orders-' . date('Y-m-d') . '.csv');
