<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/csv-export.php';
$user = require_role('admin');

$requests = db()->query(
    "SELECT tr.visitor_name AS name, tr.visitor_phone AS phone, tr.visitor_email AS email,
            tr.seats_requested, tr.status, t.departs_at, b.name AS boat_name, tr.created_at
     FROM trip_requests tr
     JOIN trips t ON t.id = tr.trip_id
     LEFT JOIN boats b ON b.id = t.boat_id
     ORDER BY tr.created_at DESC"
)->fetchAll();

foreach ($requests as &$row) {
    $row['departs_at'] = date('Y-m-d H:i', strtotime($row['departs_at']));
    $row['created_at'] = utc_to_local($row['created_at'], 'Y-m-d H:i');
}

stream_csv($requests, 'capitony-trip-requests-' . date('Y-m-d') . '.csv');
