<?php
// Usage: set $pageTitle before including this partial, e.g.:
//   <?php $pageTitle = 'Home'; include '../src/partials/head.php'; ?>
$iconDataUri = 'data:image/svg+xml;base64,' . base64_encode(file_get_contents(__DIR__ . '/icon.svg'));
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> | PETStack</title>

<!-- Favicon: inlined from icon.svg -->
<link rel="icon" type="image/svg+xml" href="<?= $iconDataUri ?>">

<script>
    if (localStorage.getItem('petstack:sidebar') === 'collapsed') {
        document.documentElement.dataset.sidebar = 'collapsed';
    }
</script>

<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/layout.css">
<link rel="stylesheet" href="assets/css/components.css">
