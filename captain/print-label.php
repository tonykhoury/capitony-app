<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('captain');

$id = (int)($_GET['id'] ?? 0);

$stmt = db()->prepare(
    "SELECT ci.*, s.name AS species_name, t.captain_id
     FROM catch_items ci
     JOIN species s ON s.id = ci.species_id
     JOIN trips t ON t.id = ci.trip_id
     WHERE ci.id = ? AND t.captain_id = ?"
);
$stmt->execute([$id, $user['id']]);
$item = $stmt->fetch();

if (!$item || !$item['sku']) {
    http_response_code(404);
    die('Label not found.');
}

$qrData = urlencode($item['sku']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Label — <?= e($item['sku']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'IBM Plex Mono', monospace; background: #eee; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
  .label {
    width: 320px; background: #fff; border: 2px solid #12191D; padding: 16px;
    text-align: center;
  }
  .label .brand { font-family: 'Fjalla One', sans-serif; font-size: 0.9rem; letter-spacing: 0.08em; margin-bottom: 8px; }
  .label img { width: 160px; height: 160px; margin: 8px auto; display: block; }
  .label .sku { font-size: 1.3rem; font-weight: 600; letter-spacing: 0.05em; margin: 8px 0; }
  .label .species { font-family: 'Fjalla One', sans-serif; font-size: 1.1rem; text-transform: uppercase; margin-bottom: 4px; }
  .label .meta { font-size: 0.78rem; color: #444; }
  .print-btn {
    margin-top: 20px; font-family: 'Fjalla One', sans-serif; font-size: 0.85rem;
    padding: 12px 24px; background: #E8A33D; color: #fff; border: none; cursor: pointer;
  }
  @media print {
    body { background: #fff; min-height: auto; }
    .print-btn { display: none; }
    .label { border: 1px solid #000; }
  }
</style>
</head>
<body>
<div>
  <div class="label">
    <div class="brand">CAPITONY</div>
    <div class="species"><?= e($item['species_name']) ?></div>
    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= $qrData ?>" alt="QR code for <?= e($item['sku']) ?>">
    <div class="sku"><?= e($item['sku']) ?></div>
    <div class="meta">
      <?= number_format($item['weight_kg'], 1) ?> kg &middot; <?= e(utc_to_local($item['posted_at'], 'M j, g:i A')) ?>
    </div>
  </div>
  <div style="text-align:center;">
    <button class="print-btn" onclick="window.print()">Print This Label</button>
  </div>
</div>
</body>
</html>
