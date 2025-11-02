<?php
session_start();

require_once 'vendor/autoload.php'; // không dùng __DIR__

use Dotenv\Dotenv;

// 1. NẠP .ENV
$dotenv = Dotenv::createImmutable('.'); // dấu chấm = thư mục hiện tại
$dotenv->load();

// 2. KẾT NỐI DB
include 'database/config.php';

// 3. TỰ TẠO GOOGLE CLIENT
$googleId       = $_ENV['GOOGLE_CLIENT_ID']     ?? getenv('GOOGLE_CLIENT_ID')     ?? '';
$googleSecret   = $_ENV['GOOGLE_CLIENT_SECRET'] ?? getenv('GOOGLE_CLIENT_SECRET') ?? '';
$googleRedirect = $_ENV['GOOGLE_REDIRECT_URI']  ?? getenv('GOOGLE_REDIRECT_URI')  ?? '';

$googleConfigError = '';
$client = null;

if ($googleId === '' || $googleSecret === '' || $googleRedirect === '') {
    $googleConfigError = 'Chưa cấu hình đầy đủ Google OAuth. Vui lòng kiểm tra .env';
} else {
    $client = new Google_Client();
    $client->setClientId($googleId);
    $client->setClientSecret($googleSecret);
    $client->setRedirectUri($googleRedirect);
    $client->addScope('email');
    $client->addScope('profile');
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');
}

// 4. TẠO LINK LOGIN GOOGLE
$login_url = $client->createAuthUrl();
$login_url = $client ? $client->createAuthUrl() : '#';
$googleButtonClasses = 'w-full py-2 flex items-center justify-center gap-2 '
    . 'bg-white hover:bg-gray-50 '
    . 'text-gray-700 font-medium '
    . 'rounded-lg border border-gray-300 shadow-sm transition';

if (!$client) {
    $googleButtonClasses = 'w-full py-2 flex items-center justify-center gap-2 '
        . 'bg-white text-gray-400 font-medium '
        . 'rounded-lg border border-gray-300 opacity-60 cursor-not-allowed';
}

$error = "";

// Ghi nhớ nơi user đang cố truy cập trước khi bị yêu cầu login
if (isset($_GET['redirect'])) {
    $_SESSION['redirect_to'] = $_GET['redirect'];
}

// Helper: gán session role và chuyển trang
function finishLoginAndRedirect($conn, $userRow) {
    // $userRow chứa các trường: ID_TK, ID_QUYEN
    // Ta cần xem người này có phải nhân viên hay không (để lấy LOAI_NV, ID_CN)
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

    // Thiết lập session chung
    $_SESSION['ID_TK']     = $userRow['ID_TK'];
    $_SESSION['ID_QUYEN']  = $userRow['ID_QUYEN']; // ví dụ: 1 = admin, 2 = nhân viên, ...
    $_SESSION['branch_id'] = $nvRow ? intval($nvRow['ID_CN']) : null;

    // Chuẩn hóa role để dùng cho dashboard và API
    // - admin: ID_QUYEN == '1'
    // - branch_manager: ID_QUYEN == '2' và LOAI_NV == 'quan_ly'
    // - staff: ID_QUYEN == '2' và LOAI_NV khác
    // - customer: còn lại
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

    // customer / client ngoài studio:
    $dest = $_SESSION['redirect_to'] ?? null;
    unset($_SESSION['redirect_to']);
    if ($dest && strpos($dest, '/app/') === 0) {
        header("Location: " . $dest);
        exit;
    }

    header("Location: ./app/Pages/Views/home.php");
    exit;
}

// ==================
// ĐĂNG NHẬP LOCAL
// ==================
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $inputUsername = trim($_POST['username'] ?? '');
    $inputPassword = $_POST['password'] ?? '';

    if ($inputUsername !== '' && $inputPassword !== '') {
        // Lấy tài khoản theo ID_TK (username = mã tài khoản nội bộ)
        $stmt = $conn->prepare("
            SELECT ID_TK, MAT_KHAU, ID_QUYEN
            FROM tai_khoan
            WHERE ID_TK = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $inputUsername);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();

            if (password_verify($inputPassword, $user['MAT_KHAU'])) {
                // Thành công → set session + redirect
                finishLoginAndRedirect($conn, $user);
            } else {
                $error = "Mật khẩu không chính xác.";
            }
        } else {
            $error = "Tài khoản không tồn tại.";
        }
    } else {
        $error = "Vui lòng nhập đầy đủ thông tin.";
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8" />
    <title>Đăng nhập - Stygian Blue Studio</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        body {
            background: linear-gradient(to bottom right, #e6f0ff, #cfd9e8);
            font-family: 'Segoe UI', sans-serif;
        }
        .glass {
            backdrop-filter: blur(12px);
            background-color: rgba(255,255,255,0.7);
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }
        .input-style {
            background-color: #f4f6f9;
        }
        .input-style:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.3);
        }
    </style>
</head>

<body class="flex items-center justify-center min-h-screen px-4">
    <div class="w-full max-w-md glass p-8 rounded-xl border border-blue-100">
        <!-- Logo -->
        <div class="flex justify-center mb-6">
            <a href="./app/Pages/Views/home.php" title="Về trang chủ">
                <img
                    src="public/images/logo5.png"
                    alt="Logo"
                    class="h-20 w-20 rounded-lg shadow-md hover:scale-105 transition-transform"
                />
            </a>
        </div>

        <h2 class="text-2xl font-bold text-center text-blue-800 mb-6">Đăng nhập</h2>

        <!-- FORM ĐĂNG NHẬP TÀI KHOẢN NỘI BỘ -->
        <form method="POST" action="" class="space-y-4">
            <input
                type="text"
                name="username"
                placeholder="Mã người dùng"
                required
                class="w-full px-4 py-2 rounded-lg border input-style focus:outline-none"
            />

            <input
                type="password"
                name="password"
                placeholder="Mật khẩu"
                required
                class="w-full px-4 py-2 rounded-lg border input-style focus:outline-none"
            />

            <button
                type="submit"
                class="w-full py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-semibold transition"
            >
                Đăng nhập
            </button>

            <?php if (!empty($error)): ?>
                <div class="text-sm text-red-600 font-medium mt-2">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <a
                href="forgot_password.php"
                class="text-sm text-blue-700 hover:underline block mt-3 text-center"
            >
                Quên mật khẩu hoặc mã người dùng?
            </a>
        </form>

        <!-- NGĂN CÁCH -->
        <div class="relative my-6">
            <div class="w-full h-px bg-gray-300"></div>
            <div class="absolute inset-0 flex justify-center">
                <span class="bg-white/70 text-gray-500 text-xs px-3 py-0.5 rounded-full border border-gray-300 shadow-sm">
                    hoặc
                </span>
            </div>
        </div>

        <!-- NÚT GOOGLE -->
        <div class="space-y-3">
            <a
                href="<?= htmlspecialchars($login_url) ?>"
                class="block w-full"
            >
                <button
                    type="button"
                    class="w-full py-2 flex items-center justify-center gap-2
                           bg-white hover:bg-gray-50
                           text-gray-700 font-medium
                           rounded-lg border border-gray-300 shadow-sm transition"
                >
                    <img
                        src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg"
                        alt=""
                        class="h-5 w-5"
                    />
                    <span>Đăng nhập bằng Google</span>
                </button>
            </a>
        </div>

        <!-- LINK ĐĂNG KÝ -->
        <div class="mt-8 text-center text-sm text-gray-600">
            Chưa có tài khoản?
            <a href="register.php" class="text-blue-700 hover:underline font-semibold">Đăng ký</a>
        </div>
    </div>
</body>
</html>
