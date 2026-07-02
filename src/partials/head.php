<?php
// Usage: set $pageTitle before including this partial, e.g.:
//   <?php $pageTitle = 'Home'; include '../src/partials/head.php'; ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> | PETStack</title>

<!-- Favicon -->
<link rel="icon" href="/favicons/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="/favicons/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicons/favicon-16x16.png">
<link rel="apple-touch-icon" href="/favicons/apple-touch-icon.png">
<link rel="manifest" href="/favicons/site.webmanifest">

<script>
    if (localStorage.getItem('petstack:sidebar') === 'collapsed') {
        document.documentElement.dataset.sidebar = 'collapsed';
    }
    if (localStorage.getItem('petstack:theme') === 'dark') {
        document.documentElement.dataset.theme = 'dark';
    }
</script>

<link rel="stylesheet" href="assets/css/tokens.css">
<link rel="stylesheet" href="assets/css/base.css">
<link rel="stylesheet" href="assets/css/layout.css">
<link rel="stylesheet" href="assets/css/components.css">
<?php if (!empty($roleCss)): ?>
<link rel="stylesheet" href="assets/css/<?= htmlspecialchars($roleCss) ?>.css">
<?php endif; ?>