<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function require_role(array $allowed_roles) {
  $role = (string)($_SESSION['ID_QUYEN'] ?? '');
  if (!in_array($role, array_map('strval', $allowed_roles), true)) {
    header('Location: /');
    exit;
  }
}