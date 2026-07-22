<?php
require __DIR__ . '/../includes/bootstrap.php';
$role = 'captain';
$user = require_role('captain');
require __DIR__ . '/../includes/change-password-form.php';
