<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['ID_TK'])) {
  $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'] ?? '/';
  header('Location: /app/login.php');
  exit;
}