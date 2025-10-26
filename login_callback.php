<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 0. Autoload + Dotenv (giống login.php, không dùng __DIR__)
require_once 'vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable('.'); // nạp biến môi trường từ .env ở thư mục hiện tại
$dotenv->load();

// 1. Kết nối DB
include 'database/config.php';

// 2. Hàm helper đăng nhập và redirect (giữ nguyên của bạn)
function finishLoginAndRedirect($conn, $userRow) {
    // Lấy thông tin nhân viên (LOAI_NV, ID_CN) nếu có
    $stmt2 = $conn->prepare("
        SELECT LOAI_NV, ID_CN
        FROM nhan_vien
        WHERE ID_TK = ?
        LIMIT 1
    ");
    $stmt2->bind_param("s", $userRow['ID_TK']);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    $nvRow = ($res2 && $res2->num_rows > 0) ? $res2->fetch_assoc() : null;

    // Set session
    $_SESSION['ID_TK']     = $userRow['ID_TK'];
    $_SESSION['ID_QUYEN']  = $userRow['ID_QUYEN'];
    $_SESSION['branch_id'] = $nvRow ? intval($nvRow['ID_CN']) : null;

    // Xác định role
    if ($userRow['ID_QUYEN'] == '1') {
        $_SESSION['role'] = 'admin';
    } elseif ($userRow['ID_QUYEN'] == '2' && $nvRow) {
        if ($nvRow['LOAI_NV'] === 'quan_ly') {
            $_SESSION['role'] = 'branch_manager';
        } else {
            $_SESSION['role'] = 'staff';
        }
    } else {
        $_SESSION['role'] = 'customer';
    }

    // Điều hướng dựa trên role
    if ($_SESSION['role'] === 'admin') {
        header("Location: /StygianBlue/app/admin/admin_dashboard.php");
        exit;
    }

    if ($_SESSION['role'] === 'branch_manager') {
        header("Location: /StygianBlue/app/admin/manager_dashboard.php");
        exit;
    }

    if ($_SESSION['role'] === 'staff') {
        header("Location: /StygianBlue/app/admin/staff_dashboard.php");
        exit;
    }

    // customer
    $dest = $_SESSION['redirect_to'] ?? null;
    unset($_SESSION['redirect_to']);
    if ($dest && strpos($dest, '/app/') === 0) {
        header("Location: " . $dest);
        exit;
    }

    header("Location: /StygianBlue/app/Pages/Views/home.php");
    exit;
}

// 3. Kiểm tra code từ Google
if (!isset($_GET['code'])) {
    header("Location: /StygianBlue/login.php?err=google_denied");
    exit;
}

// 4. Tạo Google Client trực tiếp ở đây (không rely google_oauth_client.php)
$googleId       = $_ENV['GOOGLE_CLIENT_ID']     ?? getenv('GOOGLE_CLIENT_ID')     ?? '';
$googleSecret   = $_ENV['GOOGLE_CLIENT_SECRET'] ?? getenv('GOOGLE_CLIENT_SECRET') ?? '';
$googleRedirect = $_ENV['GOOGLE_REDIRECT_URI']  ?? getenv('GOOGLE_REDIRECT_URI')  ?? '';

if ($googleId === '' || $googleSecret === '' || $googleRedirect === '') {
    die('Google OAuth env vars are missing in callback. Kiểm tra .env.');
}

$client = new Google_Client();
$client->setClientId($googleId);
$client->setClientSecret($googleSecret);
$client->setRedirectUri($googleRedirect);
$client->addScope("email");
$client->addScope("profile");
$client->setAccessType('offline');
$client->setPrompt('select_account consent');

// 5. Đổi code -> access token
$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
if (isset($token['error'])) {
    header("Location: /StygianBlue/login.php?err=google_token");
    exit;
}

// 6. Gắn token lại để gọi API userinfo
$client->setAccessToken($token['access_token']);

// 7. Lấy thông tin người dùng Google
$oauth2 = new Google_Service_Oauth2($client);
$googleUser = $oauth2->userinfo->get();

// 8. Lấy email (bắt buộc để map vào tài khoản hệ thống)
$email = $googleUser->email ?? null;
if (!$email) {
    header("Location: /StygianBlue/login.php?err=no_email");
    exit;
}

// 9. Tìm user tương ứng trong DB
$stmt = $conn->prepare("
    SELECT ID_TK, ID_QUYEN, EMAIL
    FROM tai_khoan
    WHERE EMAIL = ?
    LIMIT 1
");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows === 0) {
    // Tùy chính sách: auto-register hay chặn
    header("Location: /StygianBlue/login.php?err=not_registered");
    exit;
}

$userRow = $res->fetch_assoc();

// 10. Đăng nhập và điều hướng như tài khoản nội bộ
finishLoginAndRedirect($conn, $userRow);
