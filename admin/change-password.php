<?php
require __DIR__ . '/../includes/bootstrap.php';
$role = 'admin';
$user = require_role('admin');
require __DIR__ . '/../includes/change-password-form.php';
