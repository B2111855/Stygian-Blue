<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Kết nối DB
include './database/config.php';

// Google client
include './google_oauth_client.php'; // file này phải tạo $client cấu hình OAuth

// Helper: giống login.php
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

    // Redirect theo role
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

// ===================
// 1. Nhận code từ Google
// ===================
if (!isset($_GET['code'])) {
    // Không có code nghĩa là user vào thẳng file này hoặc Google từ chối
    header("Location: /StygianBlue/login.php?err=google_denied");
    exit;
}

// 2. Đổi code -> access token
$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
if (isset($token['error'])) {
    header("Location: /StygianBlue/login.php?err=google_token");
    exit;
}

// Gắn token vào client để gọi API userinfo
$client->setAccessToken($token['access_token']);

// 3. Lấy thông tin user từ Google
$oauth2 = new Google_Service_Oauth2($client);
$googleUser = $oauth2->userinfo->get();

$email = $googleUser->email ?? null;
if (!$email) {
    header("Location: /StygianBlue/login.php?err=no_email");
    exit;
}

// 4. Tìm user trong DB theo email
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
    // Ở đây bạn chọn policy:
    //  - Nếu muốn auto-register Google user => thêm insert.
    //  - Hiện tại, mình từ chối nếu email chưa có trong hệ thống.
    header("Location: /StygianBlue/login.php?err=not_registered");
    exit;
}

$userRow = $res->fetch_assoc();

// 5. Hoàn tất login giống local
finishLoginAndRedirect($conn, $userRow);
