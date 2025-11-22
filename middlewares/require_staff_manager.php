<?php
require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/require_role.php';
require_role(['1','2']); // admin hoặc nhân viên

// Nếu là nhân viên (ID_QUYEN = 2), chỉ cho phép loại 'quan_ly' truy cập
if (($_SESSION['ID_QUYEN'] ?? '') === '2' && (($_SESSION['STAFF_TYPE'] ?? '') !== 'quan_ly')) {
  header('Location: /');
  exit;
}
