+35
-66

<?php
session_start();

require_once './vendor/autoload.php'; 
require_once './app/helpers/auth_background.php';

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

$flashMessage = $_SESSION['message'] ?? '';
$flashType = $_SESSION['message_type'] ?? 'success';
$flashClass = 'auth-form__message--success';

if ($flashMessage !== '') {
    if ($flashType !== 'success') {
        $flashClass = 'auth-form__message--error';
    }
    unset($_SESSION['message'], $_SESSION['message_type']);
} else {
    $flashType = '';
}

// Cấu hình nền cho trang đăng nhập (cập nhật đường dẫn/overlay nếu cần)
$authBodyAttributes = buildAuthBodyAttributes([
    'image'   => '',
    'overlay' => '',
    'blur'    => '',
]);

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
                $_SESSION['message'] = 'Đăng nhập thành công!';
                $_SESSION['message_type'] = 'success';
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
    <link rel="stylesheet" href="public/css/auth.css">
</head>

<body <?= $authBodyAttributes ?>>
    <!-- Để hiển thị ảnh nền, thiết lập giá trị cho --auth-background-image trong thuộc tính style của thẻ body -->
    <div class="auth-card">
        <div class="auth-card__logo">
            <a href="./app/Pages/Views/home.php" title="Về trang chủ">
                <img
                    src="public/images/logo5.png"
                    alt="Logo"
                />
            </a>
        </div>

        <h2 class="auth-heading">Đăng nhập</h2>

        <!-- FORM ĐĂNG NHẬP TÀI KHOẢN NỘI BỘ -->
        <form method="POST" action="" class="auth-form">
            <?php if ($flashMessage !== ''): ?>
                <div class="auth-form__message <?= htmlspecialchars($flashClass) ?>">
                    <?= htmlspecialchars($flashMessage) ?>
                </div>
            <?php endif; ?>

            <input
                type="text"
                name="username"
                placeholder="Mã người dùng"
                required
                class="auth-form__field"
            />

            <input
                type="password"
                name="password"
                placeholder="Mật khẩu"
                required
                class="auth-form__field"
            />

            <button
                type="submit"
                class="auth-form__button"
            >
                Đăng nhập
            </button>

            <?php if (!empty($error)): ?>
                <div class="auth-form__message auth-form__message--error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="auth-helper">
                <a href="forgot_password.php" class="auth-helper__link">Quên mật khẩu hoặc mã người dùng?</a>
            </div>
        </form>

        <div class="auth-divider">
            <span class="auth-divider__text">hoặc</span>
        </div>

        <div>
            <a
                href="<?= htmlspecialchars($login_url) ?>"
                class="auth-social-button <?= $client ? '' : 'is-disabled' ?>"
                role="button"
                <?= $client ? '' : 'aria-disabled="true" tabindex="-1"' ?>
            >
                <img
                    src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg"
                    alt="Google"
                />
                <span>Đăng nhập bằng Google</span>
            </a>
        </div>

        <div class="auth-footer">
            Chưa có tài khoản?
            <a href="register.php" class="auth-footer__link">Đăng ký</a>
        </div>
    </div>
</body>
</html>