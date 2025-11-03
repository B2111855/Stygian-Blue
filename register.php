<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
session_start();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require  './database/config.php';
require './app/helpers/auth_background.php';

header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

$authBodyAttributes = buildAuthBodyAttributes([
    'image'   => '',
    'overlay' => '',
    'blur'    => '',
]);

$errors = [];
$success = '';

$input = [
    'full_name'  => '',
    'username'   => '',
    'email'      => '',
    'address'    => '',
    'phone'      => '',
    'birth_date' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($input as $field => $default) {
        $input[$field] = isset($_POST[$field]) ? trim((string) $_POST[$field]) : '';
    }

    $rawPassword = isset($_POST['password']) ? trim((string) $_POST['password']) : '';
    $roleId = 3;

    if ($input['full_name'] === '' || $input['username'] === '' || $rawPassword === '' || $input['email'] === '' || $input['phone'] === '') {
        $errors[] = 'Vui lòng điền đầy đủ thông tin bắt buộc.';
    }

    $usernameLength = strlen($input['username']);
    if ($usernameLength < 8 || $usernameLength > 16) {
        $errors[] = 'Mã đăng nhập phải từ 8 đến 16 ký tự.';
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $input['username'])) {
        $errors[] = 'Mã đăng nhập chỉ được chứa chữ cái, số và dấu gạch dưới.';
    }

    $passwordLength = strlen($rawPassword);
    if ($passwordLength < 6 || $passwordLength > 22) {
        $errors[] = 'Mật khẩu phải từ 6 đến 22 ký tự.';
    }

    if (!preg_match('/^0[0-9]{9,11}$/', $input['phone'])) {
        $errors[] = 'Số điện thoại không hợp lệ. Vui lòng nhập lại.';
    }

    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Địa chỉ email không hợp lệ.';
    }

    if ($input['birth_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['birth_date'])) {
        $errors[] = 'Ngày sinh không hợp lệ.';
    }

    if (!$errors) {
        try {
            $conn->begin_transaction();

            $checkStmt = $conn->prepare('SELECT 1 FROM tai_khoan WHERE ID_TK = ? LIMIT 1');
            $checkStmt->bind_param('s', $input['username']);
            $checkStmt->execute();
            $checkStmt->store_result();

            if ($checkStmt->num_rows > 0) {
                $checkStmt->close();
                $errors[] = 'Tên đăng nhập đã tồn tại. Vui lòng chọn tên khác.';
                $conn->rollback();
            } else {
                $hashedPassword = password_hash($rawPassword, PASSWORD_DEFAULT);

                $username = $input['username'];
                $fullName = $input['full_name'];
                $birthDate = $input['birth_date'] !== '' ? $input['birth_date'] : null;
                $address = $input['address'];
                $email = $input['email'];
                $phone = $input['phone'];

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

                $insertAccount->close();
                $insertCustomer->close();
                $checkStmt->close();

                $conn->commit();

                $_SESSION['message'] = 'Tạo tài khoản thành công! Vui lòng đăng nhập.';
                $_SESSION['message_type'] = 'success';
                header('Location: login.php');
                exit;
            }
        } catch (mysqli_sql_exception $exception) {
            if (isset($insertAccount) && $insertAccount instanceof mysqli_stmt) {
                $insertAccount->close();
            }
            if (isset($insertCustomer) && $insertCustomer instanceof mysqli_stmt) {
                $insertCustomer->close();
            }
            if (isset($checkStmt) && $checkStmt instanceof mysqli_stmt) {
                $checkStmt->close();
            }

            $conn->rollback();
            $errors[] = 'Không thể tạo tài khoản. Vui lòng thử lại sau.';
            error_log('Registration error: ' . $exception->getMessage());
        }
    }
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng ký - Stygian Blue Studio</title>
    <link rel="stylesheet" href="public/css/auth.css">
</head>
<body <?= $authBodyAttributes ?>>
    <div class="auth-card">
        <div class="auth-card__logo">
            <a href="./app/Pages/Views/home.php" title="Về trang chủ">
                <img src="public/images/logo5.png" alt="Logo Stygian Blue Studio">
            </a>
        </div>

        <h2 class="auth-heading">Đăng ký tài khoản</h2>

        <?php if ($errors): ?>
            <div class="auth-form__message auth-form__message--error">
                <?= implode('<br>', array_map(function ($message) {
                    return htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
                }, $errors)) ?>
            </div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="auth-form__message auth-form__message--success">
                <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="auth-form" autocomplete="off">
            <input
                type="text"
                name="full_name"
                class="auth-form__field"
                placeholder="Họ tên"
                value="<?= htmlspecialchars($input['full_name'], ENT_QUOTES, 'UTF-8') ?>"
                required
            />
            <input
                type="text"
                name="username"
                class="auth-form__field"
                placeholder="Mã đăng nhập"
                minlength="8"
                maxlength="16"
                value="<?= htmlspecialchars($input['username'], ENT_QUOTES, 'UTF-8') ?>"
                required
            />
            <input
                type="email"
                name="email"
                class="auth-form__field"
                placeholder="Email"
                value="<?= htmlspecialchars($input['email'], ENT_QUOTES, 'UTF-8') ?>"
                required
            />
            <input
                type="text"
                name="address"
                class="auth-form__field"
                placeholder="Địa chỉ"
                value="<?= htmlspecialchars($input['address'], ENT_QUOTES, 'UTF-8') ?>"
                required
            />
            <input
                type="tel"
                name="phone"
                class="auth-form__field"
                placeholder="Số điện thoại"
                pattern="0[0-9]{9,11}"
                value="<?= htmlspecialchars($input['phone'], ENT_QUOTES, 'UTF-8') ?>"
                required
            />
            <input
                type="password"
                name="password"
                class="auth-form__field"
                placeholder="Mật khẩu"
                minlength="6"
                maxlength="22"
                required
            />
            <input
                type="date"
                name="birth_date"
                class="auth-form__field"
                value="<?= htmlspecialchars($input['birth_date'], ENT_QUOTES, 'UTF-8') ?>"
            />

            <button type="submit" class="auth-form__button">Đăng ký</button>
        </form>

        <div class="auth-footer">
            Đã có tài khoản?
            <a href="login.php" class="auth-footer__link">Đăng nhập</a>
        </div>
    </div>
</body>
</html>