<?php
// Usage: set $pageTitle before including this partial, e.g.:
//   <?php $pageTitle = 'Home'; include '../src/partials/head.php'; ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> | PETCOM</title>

<!-- Favicons: static files in public/favicons/, no PHP processing needed -->
<link rel="icon" type="image/png" sizes="32x32" href="/favicons/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicons/favicon-16x16.png">
<link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
<link rel="manifest" href="/favicons/site.webmanifest">

<script>
    if (localStorage.getItem('petcom:sidebar') === 'collapsed') {
        document.documentElement.dataset.sidebar = 'collapsed';
    }
</script>

<!-- Leading slash: resolves from site root regardless of which folder
     the current page lives in (public/, public/customer/, etc). -->
<link rel="stylesheet" href="/assets/css/style.css">

<link rel="stylesheet" href="/assets/css/layout/shell.css">
<link rel="stylesheet" href="/assets/css/layout/sidebar.css">

<link rel="stylesheet" href="/assets/css/components/auth.css">
<link rel="stylesheet" href="/assets/css/components/page-structure.css">
<link rel="stylesheet" href="/assets/css/components/forms.css">
<link rel="stylesheet" href="/assets/css/components/buttons.css">
<link rel="stylesheet" href="/assets/css/components/tables.css">
<link rel="stylesheet" href="/assets/css/components/alerts.css">
<link rel="stylesheet" href="/assets/css/components/badges.css">
<link rel="stylesheet" href="/assets/css/components/utilities.css">
<link rel="stylesheet" href="/assets/css/components/toasts.css">
<link rel="stylesheet" href="/assets/css/components/modals.css">
<link rel="stylesheet" href="/assets/css/components/feedback.css">
<link rel="stylesheet" href="/assets/css/components/dashboard.css">
<link rel="stylesheet" href="/assets/css/components/radio-cards.css">
<link rel="stylesheet" href="/assets/css/components/order-page.css">
<link rel="stylesheet" href="/assets/css/components/catalog.css">
<link rel="stylesheet" href="/assets/css/components/past-orders.css">

