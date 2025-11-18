<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 0. Autoload + Dotenv (giống login.php, không dùng __DIR__)
require_once 'vendor/autoload.php';
require_once __DIR__ . '/app/helpers/system_log.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable('.'); // nạp biến môi trường từ .env ở thư mục hiện tại
$dotenv->load();

// 1. Kết nối DB
include 'database/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

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

    record_system_log(
        $conn,
        'LOGIN_SUCCESS',
        'auth',
        null,
        [
            'ID_TK'    => $userRow['ID_TK'],
            'ID_QUYEN' => $userRow['ID_QUYEN'],
            'role'     => $_SESSION['role'] ?? null,
            'method'   => 'google_oauth'
        ]
    );

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

function findUserByEmail(mysqli $conn, string $email): ?array {
    $stmt = $conn->prepare("
        SELECT ID_TK, ID_QUYEN, EMAIL
        FROM tai_khoan
        WHERE EMAIL = ?
        LIMIT 1
    ");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = ($result && $result->num_rows > 0) ? $result->fetch_assoc() : null;
    $stmt->close();

    return $user;
}

function usernameExists(mysqli $conn, string $username): bool {
    $stmt = $conn->prepare('SELECT 1 FROM tai_khoan WHERE ID_TK = ? LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    return $exists;
}

function generateGoogleUsername(mysqli $conn, string $email): string {
    $emailParts = explode('@', $email, 2);
    $localPart = strtolower($emailParts[0] ?? '');
    $localPart = preg_replace('/[^a-z0-9]/', '', $localPart);
    $localPart = substr($localPart, 0, 8);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $suffix = $attempt === 0 ? '' : (string) $attempt;
        $candidate = 'gg' . $localPart . $suffix;
        if (strlen($candidate) < 8) {
            $candidate = str_pad($candidate, 8, '0');
        }
        $candidate = substr($candidate, 0, 16);
        if (!usernameExists($conn, $candidate)) {
            return $candidate;
        }
    }

    do {
        $candidate = 'gg' . substr(bin2hex(random_bytes(6)), 0, 10);
    } while (usernameExists($conn, $candidate));

    return $candidate;
}

function registerGoogleAccount(mysqli $conn, $googleUser, string $email): array {
    $roleId = 3; // khách hàng
    $username = generateGoogleUsername($conn, $email);

    $fullName = trim($googleUser->name ?? $googleUser->givenName ?? $googleUser->familyName ?? '');
    if ($fullName === '') {
        $emailParts = explode('@', $email, 2);
        $fullName = $emailParts[0] ?? 'Google User';
    }
    if (function_exists('mb_substr')) {
        $fullName = mb_substr($fullName, 0, 50);
    } else {
        $fullName = substr($fullName, 0, 50);
    }

    $birthDate = null;
    $address = null;
    $phone = null;
    $randomPassword = bin2hex(random_bytes(20));
    $hashedPassword = password_hash($randomPassword, PASSWORD_DEFAULT);

    $conn->begin_transaction();
    try {
        $insertAccount = $conn->prepare(
            'INSERT INTO tai_khoan (ID_TK, ID_QUYEN, HO_TEN, NGAY_SINH, DIA_CHI, EMAIL, SDT, MAT_KHAU) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insertAccount->bind_param(
            'sissssss',
            $username,
            $roleId,
            $fullName,
            $birthDate,
            $address,
            $email,
            $phone,
            $hashedPassword
        );
        $insertAccount->execute();
        $insertAccount->close();

        $insertCustomer = $conn->prepare(
            'INSERT INTO khach_hang (ID_TK, ID_QUYEN, HO_TEN, NGAY_SINH, DIA_CHI, EMAIL, SDT, MAT_KHAU) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insertCustomer->bind_param(
            'sissssss',
            $username,
            $roleId,
            $fullName,
            $birthDate,
            $address,
            $email,
            $phone,
            $hashedPassword
        );
        $insertCustomer->execute();
        $insertCustomer->close();

        $conn->commit();
    } catch (Throwable $th) {
        $conn->rollback();
        if (isset($insertAccount) && $insertAccount instanceof mysqli_stmt) {
            $insertAccount->close();
        }
        if (isset($insertCustomer) && $insertCustomer instanceof mysqli_stmt) {
            $insertCustomer->close();
        }
        throw $th;
    }

    return [
        'ID_TK' => $username,
        'ID_QUYEN' => $roleId,
        'EMAIL' => $email,
    ];
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

// 9. Tìm user tương ứng trong DB (hoặc tạo mới nếu chưa tồn tại)
$userRow = findUserByEmail($conn, $email);

if (!$userRow) {
    try {
        $userRow = registerGoogleAccount($conn, $googleUser, $email);
        $_SESSION['message'] = 'Đã tạo tài khoản khách hàng mới từ Google.';
        $_SESSION['message_type'] = 'success';
        record_system_log(
            $conn,
            'CUSTOMER_AUTO_REGISTER',
            'tai_khoan',
            null,
            [
                'ID_TK' => $userRow['ID_TK'],
                'EMAIL' => $userRow['EMAIL']
            ],
            $userRow['ID_TK'],
            'customer'
        );
    } catch (Throwable $th) {
        error_log('Google auto-register failed: ' . $th->getMessage());
        header("Location: /StygianBlue/login.php?err=auto_register");
        exit;
    }
}

// 10. Đăng nhập và điều hướng như tài khoản nội bộ
finishLoginAndRedirect($conn, $userRow);
