<?php
require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/require_role.php';
require_role(['1','2']);

if (($_SESSION['ID_QUYEN'] ?? '') === '2' && (($_SESSION['STAFF_TYPE'] ?? '') !== 'quan_ly')) {
  header('Location: /');
  exit;
}
