<?php
/** Expects $pageTitle to be set before including. */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> — Capitony Captain</title>
<link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Source+Serif+4&family=IBM+Plex+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">

<!-- PWA -->
<link rel="manifest" href="/captain-manifest.json">
<meta name="theme-color" content="#10404E">
<link rel="apple-touch-icon" href="/assets/img/pwa/icon-192.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Capitony">

<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register('/captain/sw.js', { scope: '/captain/' }).catch(function () {});
  });
}
</script>
</head>
