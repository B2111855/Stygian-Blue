<?php
// /app/admin/components/head.php
if (!isset($pageTitle)) $pageTitle = 'Dashboard';
require_once __DIR__ . '/../../helpers/assets.php';
?>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <?= sb_tailwind_link_tag(); ?>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
</head>
