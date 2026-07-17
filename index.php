<?php
/**
 * Placeholder root page. The visitor-facing site (trip browsing, catch
 * shop, live view) hasn't been built yet — only admin/captain tooling
 * exists so far. Redirecting to admin login for now so the domain
 * resolves to something functional instead of a blank 403.
 *
 * Replace this with the real homepage once the visitor pages are built.
 */
header('Location: /admin/login.php');
exit;
