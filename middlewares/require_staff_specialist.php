<?php
require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/require_role.php';
require_role(['1','2']);

// Nếu là nhân viên (ID_QUYEN = 2), chỉ cho phép loại 'chuyen_trach' truy cập
if (($_SESSION['ID_QUYEN'] ?? '') === '2' && (($_SESSION['STAFF_TYPE'] ?? '') !== 'chuyen_trach')) {
  header('Location: /');
  exit;
}
