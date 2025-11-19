<?php
include '../../database/config.php';
require_once __DIR__ . '/../../helpers/assets.php';

if (!defined('CUSTOMER_PAGE_SIZE')) {
    define('CUSTOMER_PAGE_SIZE', 8);
}

function escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function clean_input($value)
{
    if (is_string($value)) {
        return trim($value);
    }

    if (is_numeric($value)) {
        return (string) $value;
    }

    return '';
}

function normalize_phone($phone)
{
    return preg_replace('/\D/', '', (string) $phone);
}

function collectCustomerPayload(array $source)
{
    return [
        'ID_TK' => clean_input($source['ID_TK'] ?? ''),
        'HO_TEN' => clean_input($source['HO_TEN'] ?? ''),
        'NGAY_SINH' => clean_input($source['NGAY_SINH'] ?? ''),
        'DIA_CHI' => clean_input($source['DIA_CHI'] ?? ''),
        'EMAIL' => strtolower(clean_input($source['EMAIL'] ?? '')),
        'SDT' => normalize_phone($source['SDT'] ?? ''),
        'MAT_KHAU' => (string) ($source['MAT_KHAU'] ?? ''),
    ];
}

function validateCustomerPayload(array $data, $isNew = false)
{
    if ($isNew) {
        if ($data['ID_TK'] === '') {
            return 'ID tài khoản không được để trống.';
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', $data['ID_TK'])) {
            return 'ID tài khoản chỉ được chứa chữ, số hoặc dấu gạch dưới.';
        }
    }

    if ($data['HO_TEN'] === '') {
        return 'Vui lòng nhập họ tên khách hàng.';
    }

    if ($data['NGAY_SINH'] === '') {
        return 'Vui lòng chọn ngày sinh.';
    }

    $dob = strtotime($data['NGAY_SINH']);
    if ($dob === false) {
        return 'Ngày sinh không hợp lệ.';
    }

    if ($dob > strtotime('-18 years')) {
        return 'Khách hàng phải đủ 18 tuổi trở lên.';
    }

    if ($data['DIA_CHI'] === '') {
        return 'Vui lòng nhập địa chỉ.';
    }

    if ($data['EMAIL'] === '' || !filter_var($data['EMAIL'], FILTER_VALIDATE_EMAIL)) {
        return 'Địa chỉ email không hợp lệ.';
    }

    if (!preg_match('/^0[0-9]{9}$/', $data['SDT'])) {
        return 'Số điện thoại phải bắt đầu bằng 0 và có 10 chữ số.';
    }

    if ($isNew && $data['MAT_KHAU'] === '') {
        return 'Vui lòng nhập mật khẩu.';
    }

    if ($data['MAT_KHAU'] !== '' && strlen($data['MAT_KHAU']) < 8) {
        return 'Mật khẩu phải có ít nhất 8 ký tự.';
    }

    return null;
}

function ensureUniqueContact($email, $sdt, $currentId = null)
{
    global $conn;

    $sql = 'SELECT ID_TK, EMAIL, SDT FROM tai_khoan WHERE (EMAIL = ? OR SDT = ?)';
    $types = 'ss';

    if ($currentId !== null) {
        $sql .= ' AND ID_TK <> ?';
        $types .= 's';
    }

    $sql .= ' LIMIT 1';

    $stmt = mysqli_prepare($conn, $sql);
    if ($currentId !== null) {
        mysqli_stmt_bind_param($stmt, $types, $email, $sdt, $currentId);
    } else {
        mysqli_stmt_bind_param($stmt, $types, $email, $sdt);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $duplicate = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    if (!$duplicate) {
        return null;
    }

    if ($duplicate['EMAIL'] === $email) {
        return 'Email đã được sử dụng bởi tài khoản khác.';
    }

    return 'Số điện thoại đã được sử dụng bởi tài khoản khác.';
}

function redirectWithMessage($type, $message)
{
    $normalizedType = $type === 'success' ? 'success' : 'error';
    $url = '?page=customers&notice=' . $normalizedType . '&msg=' . urlencode($message);
    header("Location: $url");
    exit;
}

function fetchCustomerById($idTk)
{
    global $conn;

    $query = "SELECT kh.ID_TK, kh.HO_TEN, kh.NGAY_SINH, kh.DIA_CHI, kh.EMAIL, kh.SDT
              FROM khach_hang kh
              INNER JOIN tai_khoan tk ON kh.ID_TK = tk.ID_TK
              WHERE kh.ID_TK = ?";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 's', $idTk);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $customer = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return $customer;
}

function checkIfCustomerHasAppointments($idTk)
{
    global $conn;

    $query = 'SELECT 1 FROM lich_hen WHERE ID_TK = ? LIMIT 1';
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 's', $idTk);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $hasAppointment = $result && mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (bool) $hasAppointment;
}

function deleteCustomer($idTk)
{
    global $conn;

    mysqli_begin_transaction($conn);

    $deleteCustomerSql = 'DELETE FROM khach_hang WHERE ID_TK = ?';
    $stmtCustomer = mysqli_prepare($conn, $deleteCustomerSql);
    mysqli_stmt_bind_param($stmtCustomer, 's', $idTk);
    $customerResult = mysqli_stmt_execute($stmtCustomer);
    mysqli_stmt_close($stmtCustomer);

    $deleteAccountSql = 'DELETE FROM tai_khoan WHERE ID_TK = ?';
    $stmtAccount = mysqli_prepare($conn, $deleteAccountSql);
    mysqli_stmt_bind_param($stmtAccount, 's', $idTk);
    $accountResult = mysqli_stmt_execute($stmtAccount);
    mysqli_stmt_close($stmtAccount);

    if ($customerResult && $accountResult) {
        mysqli_commit($conn);
        return ['ok' => true, 'message' => 'Khách hàng đã được xóa thành công.'];
    }

    mysqli_rollback($conn);
    return ['ok' => false, 'message' => 'Xóa khách hàng thất bại. Vui lòng thử lại.'];
}

function addCustomer(array $data)
{
    global $conn;

    $idCheckSql = 'SELECT 1 FROM tai_khoan WHERE ID_TK = ? LIMIT 1';
    $idStmt = mysqli_prepare($conn, $idCheckSql);
    mysqli_stmt_bind_param($idStmt, 's', $data['ID_TK']);
    mysqli_stmt_execute($idStmt);
    $idResult = mysqli_stmt_get_result($idStmt);
    $idExists = $idResult && mysqli_fetch_assoc($idResult);
    mysqli_stmt_close($idStmt);

    if ($idExists) {
        return ['ok' => false, 'message' => 'ID tài khoản đã tồn tại. Vui lòng chọn ID khác.'];
    }

    $contactMessage = ensureUniqueContact($data['EMAIL'], $data['SDT']);
    if ($contactMessage) {
        return ['ok' => false, 'message' => $contactMessage];
    }

    $roleId = 3;
    $hashedPassword = password_hash($data['MAT_KHAU'], PASSWORD_DEFAULT);

    mysqli_begin_transaction($conn);

    $accountSql = 'INSERT INTO tai_khoan (ID_TK, ID_QUYEN, HO_TEN, NGAY_SINH, DIA_CHI, EMAIL, SDT, MAT_KHAU)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
    $accountStmt = mysqli_prepare($conn, $accountSql);
    mysqli_stmt_bind_param(
        $accountStmt,
        'sissssss',
        $data['ID_TK'],
        $roleId,
        $data['HO_TEN'],
        $data['NGAY_SINH'],
        $data['DIA_CHI'],
        $data['EMAIL'],
        $data['SDT'],
        $hashedPassword
    );
    $accountInserted = mysqli_stmt_execute($accountStmt);
    mysqli_stmt_close($accountStmt);

    if (!$accountInserted) {
        mysqli_rollback($conn);
        return ['ok' => false, 'message' => 'Không thể thêm vào bảng tài khoản. Vui lòng thử lại.'];
    }

    $customerSql = 'INSERT INTO khach_hang (ID_TK, HO_TEN, NGAY_SINH, DIA_CHI, EMAIL, SDT)
                    VALUES (?, ?, ?, ?, ?, ?)';
    $customerStmt = mysqli_prepare($conn, $customerSql);
    mysqli_stmt_bind_param(
        $customerStmt,
        'ssssss',
        $data['ID_TK'],
        $data['HO_TEN'],
        $data['NGAY_SINH'],
        $data['DIA_CHI'],
        $data['EMAIL'],
        $data['SDT']
    );
    $customerInserted = mysqli_stmt_execute($customerStmt);
    mysqli_stmt_close($customerStmt);

    if (!$customerInserted) {
        mysqli_rollback($conn);
        return ['ok' => false, 'message' => 'Không thể thêm vào bảng khách hàng. Vui lòng thử lại.'];
    }

    mysqli_commit($conn);
    return ['ok' => true, 'message' => 'Thêm khách hàng mới thành công.'];
}

function updateCustomer(array $data)
{
    global $conn;

    $contactMessage = ensureUniqueContact($data['EMAIL'], $data['SDT'], $data['ID_TK']);
    if ($contactMessage) {
        return ['ok' => false, 'message' => $contactMessage];
    }

    mysqli_begin_transaction($conn);

    $shouldUpdatePassword = $data['MAT_KHAU'] !== '';
    $accountSql = 'UPDATE tai_khoan SET HO_TEN = ?, NGAY_SINH = ?, DIA_CHI = ?, EMAIL = ?, SDT = ?';

    if ($shouldUpdatePassword) {
        $accountSql .= ', MAT_KHAU = ?';
    }

    $accountSql .= ' WHERE ID_TK = ?';
    $accountStmt = mysqli_prepare($conn, $accountSql);

    if ($shouldUpdatePassword) {
        $hashedPassword = password_hash($data['MAT_KHAU'], PASSWORD_DEFAULT);
        mysqli_stmt_bind_param(
            $accountStmt,
            'sssssss',
            $data['HO_TEN'],
            $data['NGAY_SINH'],
            $data['DIA_CHI'],
            $data['EMAIL'],
            $data['SDT'],
            $hashedPassword,
            $data['ID_TK']
        );
    } else {
        mysqli_stmt_bind_param(
            $accountStmt,
            'ssssss',
            $data['HO_TEN'],
            $data['NGAY_SINH'],
            $data['DIA_CHI'],
            $data['EMAIL'],
            $data['SDT'],
            $data['ID_TK']
        );
    }

    $accountUpdated = mysqli_stmt_execute($accountStmt);
    mysqli_stmt_close($accountStmt);

    if (!$accountUpdated) {
        mysqli_rollback($conn);
        return ['ok' => false, 'message' => 'Không thể cập nhật bảng tài khoản. Vui lòng thử lại.'];
    }

    $customerSql = 'UPDATE khach_hang SET HO_TEN = ?, NGAY_SINH = ?, DIA_CHI = ?, EMAIL = ?, SDT = ? WHERE ID_TK = ?';
    $customerStmt = mysqli_prepare($conn, $customerSql);
    mysqli_stmt_bind_param(
        $customerStmt,
        'ssssss',
        $data['HO_TEN'],
        $data['NGAY_SINH'],
        $data['DIA_CHI'],
        $data['EMAIL'],
        $data['SDT'],
        $data['ID_TK']
    );
    $customerUpdated = mysqli_stmt_execute($customerStmt);
    mysqli_stmt_close($customerStmt);

    if (!$customerUpdated) {
        mysqli_rollback($conn);
        return ['ok' => false, 'message' => 'Không thể cập nhật bảng khách hàng. Vui lòng thử lại.'];
    }

    mysqli_commit($conn);
    return ['ok' => true, 'message' => 'Cập nhật khách hàng thành công.'];
}

// Returns paginated customer data and metadata.
function getPaginatedCustomers($search, $page, $limit = CUSTOMER_PAGE_SIZE)
{
    global $conn;

    $page = max(1, (int) $page);
    $limit = max(1, (int) $limit);
    $offset = ($page - 1) * $limit;
    $search = clean_input($search);

    $whereClause = '';
    $keyword = null;

    if ($search !== '') {
        $whereClause = 'WHERE kh.HO_TEN LIKE ? OR kh.EMAIL LIKE ? OR kh.SDT LIKE ?';
        $keyword = '%' . $search . '%';
    }

    $countSql = "SELECT COUNT(*) AS total
                 FROM khach_hang kh
                 INNER JOIN tai_khoan tk ON kh.ID_TK = tk.ID_TK
                 $whereClause";
    $countStmt = mysqli_prepare($conn, $countSql);

    if ($keyword !== null) {
        mysqli_stmt_bind_param($countStmt, 'sss', $keyword, $keyword, $keyword);
    }

    mysqli_stmt_execute($countStmt);
    $countResult = mysqli_stmt_get_result($countStmt);
    $total = $countResult ? (int) mysqli_fetch_assoc($countResult)['total'] : 0;
    mysqli_stmt_close($countStmt);

    $totalPages = max(1, (int) ceil(max(1, $total) / $limit));
    if ($total === 0) {
        $totalPages = 1;
        $page = 1;
        $offset = 0;
    } elseif ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $limit;
    }

    $listSql = "SELECT kh.ID_TK, kh.HO_TEN, kh.NGAY_SINH, kh.DIA_CHI, kh.EMAIL, kh.SDT
                FROM khach_hang kh
                INNER JOIN tai_khoan tk ON kh.ID_TK = tk.ID_TK
                $whereClause
                ORDER BY kh.HO_TEN ASC
                LIMIT $limit OFFSET $offset";
    $listStmt = mysqli_prepare($conn, $listSql);

    if ($keyword !== null) {
        mysqli_stmt_bind_param($listStmt, 'sss', $keyword, $keyword, $keyword);
    }

    mysqli_stmt_execute($listStmt);
    $listResult = mysqli_stmt_get_result($listStmt);
    $rows = $listResult ? mysqli_fetch_all($listResult, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($listStmt);

    return [
        'rows' => $rows,
        'totalPages' => $totalPages,
        'total' => $total,
        'limit' => $limit,
        'page' => $page,
    ];
}

function formatDateForInput($date)
{
    if (!$date) {
        return '';
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return '';
    }

    return date('Y-m-d', $timestamp);
}

function formatDateForDisplay($date)
{
    if (!$date) {
        return '';
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }

    return date('d/m/Y', $timestamp);
}

$successMessage = null;
$errorMessage = null;

if (isset($_GET['notice'], $_GET['msg'])) {
    $noticeType = $_GET['notice'] === 'success' ? 'success' : ($_GET['notice'] === 'error' ? 'error' : null);
    $messageText = clean_input($_GET['msg']);

    if ($noticeType === 'success') {
        $successMessage = $messageText;
    } elseif ($noticeType === 'error') {
        $errorMessage = $messageText;
    }
}

if (isset($_GET['delete'])) {
    $deleteId = clean_input($_GET['delete']);

    if ($deleteId === '') {
        redirectWithMessage('error', 'Thông tin khách hàng không hợp lệ.');
    }

    if (checkIfCustomerHasAppointments($deleteId)) {
        redirectWithMessage('error', 'Không thể xóa khách hàng vì đang có lịch hẹn.');
    }

    $deleteResult = deleteCustomer($deleteId);
    $type = $deleteResult['ok'] ? 'success' : 'error';
    redirectWithMessage($type, $deleteResult['message']);
}

$editCustomer = null;
if (isset($_GET['edit'])) {
    $editId = clean_input($_GET['edit']);
    if ($editId !== '') {
        $editCustomer = fetchCustomerById($editId);
        if (!$editCustomer && !$errorMessage) {
            $errorMessage = 'Không tìm thấy khách hàng yêu cầu.';
        }
    } else {
        $errorMessage = 'Không tìm thấy khách hàng yêu cầu.';
    }
}

if (isset($_POST['edit_customer'])) {
    $payload = collectCustomerPayload($_POST);
    $validationError = validateCustomerPayload($payload, false);

    if ($validationError) {
        $errorMessage = $validationError;
        $editCustomer = array_merge($editCustomer ?? [], $payload);
    } else {
        $result = updateCustomer($payload);
        if ($result['ok']) {
            redirectWithMessage('success', $result['message']);
        }

        $errorMessage = $result['message'];
        $editCustomer = array_merge($editCustomer ?? [], $payload);
    }
}

if (isset($_POST['add_customer'])) {
    $payload = collectCustomerPayload($_POST);
    $validationError = validateCustomerPayload($payload, true);

    if ($validationError) {
        $errorMessage = $validationError;
    } else {
        $result = addCustomer($payload);
        if ($result['ok']) {
            redirectWithMessage('success', $result['message']);
        }

        $errorMessage = $result['message'];
    }
}

$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$pageNumber = isset($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
$pagination = getPaginatedCustomers($search, $pageNumber);
$customers = $pagination['rows'];
$pageNumber = $pagination['page'];
$totalPages = $pagination['totalPages'];
$totalCustomers = $pagination['total'];
$perPage = $pagination['limit'];
$firstItemIndex = $totalCustomers ? (($pageNumber - 1) * $perPage) + 1 : 0;
$lastItemIndex = $totalCustomers ? min($totalCustomers, $pageNumber * $perPage) : 0;
$maxAllowedBirthDate = date('Y-m-d', strtotime('-18 years'));
?>
<body class="bg-gray-100 min-h-screen p-6">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-3xl font-bold text-indigo-700 mb-6 text-center">Quản lý khách hàng</h1>

        <?php if ($successMessage) : ?>
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 p-4 text-green-800">
                <?= escape($successMessage) ?>
            </div>
        <?php elseif ($errorMessage) : ?>
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-red-700">
                <?= escape($errorMessage) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['edit']) && $editCustomer) : ?>
            <form method="POST" class="mb-6 rounded-lg bg-white p-6 shadow">
                <h2 class="text-xl font-semibold text-indigo-600 mb-4">Cập nhật khách hàng</h2>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-600">ID tài khoản</label>
                        <input type="text" name="ID_TK" value="<?= escape($editCustomer['ID_TK'] ?? '') ?>" readonly class="w-full rounded border border-gray-300 bg-gray-100 p-2" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-600">Họ tên</label>
                        <input type="text" name="HO_TEN" value="<?= escape($editCustomer['HO_TEN'] ?? '') ?>" required class="w-full rounded border border-gray-300 p-2" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-600">Ngày sinh</label>
                        <input type="date" name="NGAY_SINH" max="<?= escape($maxAllowedBirthDate) ?>" value="<?= escape(formatDateForInput($editCustomer['NGAY_SINH'] ?? '')) ?>" required class="w-full rounded border border-gray-300 p-2" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-600">Địa chỉ</label>
                        <input type="text" name="DIA_CHI" value="<?= escape($editCustomer['DIA_CHI'] ?? '') ?>" required class="w-full rounded border border-gray-300 p-2" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-600">Email</label>
                        <input type="email" name="EMAIL" value="<?= escape($editCustomer['EMAIL'] ?? '') ?>" required class="w-full rounded border border-gray-300 p-2" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-600">Số điện thoại</label>
                        <input type="text" name="SDT" value="<?= escape($editCustomer['SDT'] ?? '') ?>" required class="w-full rounded border border-gray-300 p-2" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-600">Mật khẩu mới</label>
                        <input type="password" name="MAT_KHAU" placeholder="Để trống nếu không thay đổi" class="w-full rounded border border-gray-300 p-2" />
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <a href="?page=customers" class="rounded border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-100">Hủy</a>
                    <button type="submit" name="edit_customer" class="rounded bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700">Lưu thay đổi</button>
                </div>
            </form>

        <?php elseif (isset($_GET['edit']) && !$editCustomer) : ?>
            <div class="rounded-lg bg-white p-6 text-center shadow">
                <p class="text-gray-700">Không tìm thấy thông tin khách hàng.</p>
                <a href="?page=customers" class="mt-4 inline-flex rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700">Quay lại danh sách</a>
            </div>

        <?php elseif (isset($_GET['add'])) : ?>
            <form method="POST" class="mb-6 rounded-lg bg-white p-6 shadow">
                <h2 class="text-xl font-semibold text-indigo-600 mb-4">Thêm khách hàng mới</h2>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-600">ID tài khoản</label>
                        <input type="text" name="ID_TK" value="<?= escape($_POST['ID_TK'] ?? '') ?>" required class="w-full rounded border border-gray-300 p-2" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-600">Họ tên</label>
                        <input type="text" name="HO_TEN" value="<?= escape($_POST['HO_TEN'] ?? '') ?>" required class="w-full rounded border border-gray-300 p-2" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-600">Ngày sinh</label>
                        <input type="date" name="NGAY_SINH" max="<?= escape($maxAllowedBirthDate) ?>" value="<?= escape($_POST['NGAY_SINH'] ?? '') ?>" required class="w-full rounded border border-gray-300 p-2" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-600">Địa chỉ</label>
                        <input type="text" name="DIA_CHI" value="<?= escape($_POST['DIA_CHI'] ?? '') ?>" required class="w-full rounded border border-gray-300 p-2" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-600">Email</label>
                        <input type="email" name="EMAIL" value="<?= escape($_POST['EMAIL'] ?? '') ?>" required class="w-full rounded border border-gray-300 p-2" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-600">Số điện thoại</label>
                        <input type="text" name="SDT" value="<?= escape($_POST['SDT'] ?? '') ?>" required class="w-full rounded border border-gray-300 p-2" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-600">Mật khẩu</label>
                        <input type="password" name="MAT_KHAU" required class="w-full rounded border border-gray-300 p-2" />
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <a href="?page=customers" class="rounded border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-100">Hủy</a>
                    <button type="submit" name="add_customer" class="rounded bg-green-600 px-4 py-2 font-medium text-white hover:bg-green-700">Thêm mới</button>
                </div>
            </form>

        <?php else : ?>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <form method="GET" class="flex flex-wrap items-center gap-2">
                    <input type="hidden" name="page" value="customers">
                    <input type="text" name="search" placeholder="Tìm theo tên, email, số điện thoại" value="<?= escape($search) ?>" class="w-64 rounded border border-gray-300 p-2 shadow-sm focus:border-indigo-500 focus:outline-none" />
                    <button type="submit" class="rounded bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700">Tìm kiếm</button>
                </form>
                <a href="?page=customers&add=true" class="rounded bg-green-600 px-4 py-2 font-medium text-white hover:bg-green-700">Thêm khách hàng</a>
            </div>

            <div class="mb-4 rounded-lg bg-white p-4 shadow">
                <?php if ($totalCustomers > 0) : ?>
                    <p class="text-sm text-gray-600">
                        Đang hiển thị <?= escape($firstItemIndex) ?> - <?= escape($lastItemIndex) ?> trên tổng số <?= escape($totalCustomers) ?> khách hàng.
                    </p>
                <?php else : ?>
                    <p class="text-sm text-gray-600">Không tìm thấy khách hàng phù hợp với từ khóa hiện tại.</p>
                <?php endif; ?>
            </div>

            <div class="overflow-x-auto rounded-lg bg-white shadow">
                <table class="min-w-full table-auto text-sm">
                    <thead class="bg-gray-200 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">
                        <tr>
                            <th class="p-4">ID tài khoản</th>
                            <th class="p-4">Họ tên</th>
                            <th class="p-4">Ngày sinh</th>
                            <th class="p-4">Địa chỉ</th>
                            <th class="p-4">Email</th>
                            <th class="p-4">Số điện thoại</th>
                            <th class="p-4">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customers)) : ?>
                            <tr>
                                <td colspan="7" class="p-6 text-center text-gray-500">Chưa có dữ liệu khách hàng.</td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($customers as $customer) : ?>
                                <tr class="border-b last:border-b-0 hover:bg-gray-50">
                                    <td class="p-4 font-medium text-gray-700"><?= escape($customer['ID_TK']) ?></td>
                                    <td class="p-4 text-gray-700"><?= escape($customer['HO_TEN']) ?></td>
                                    <td class="p-4 text-gray-700"><?= escape(formatDateForDisplay($customer['NGAY_SINH'])) ?></td>
                                    <td class="p-4 text-gray-700"><?= escape($customer['DIA_CHI']) ?></td>
                                    <td class="p-4 text-gray-700"><?= escape($customer['EMAIL']) ?></td>
                                    <td class="p-4 text-gray-700"><?= escape($customer['SDT']) ?></td>
                                    <td class="p-4">
                                        <div class="flex flex-wrap gap-2">
                                            <a href="?page=customers&edit=<?= urlencode($customer['ID_TK']) ?>" class="rounded border border-yellow-500 px-3 py-1 text-sm font-medium text-yellow-700 hover:bg-yellow-50">Chỉnh sửa</a>
                                            <a href="?page=customers&delete=<?= urlencode($customer['ID_TK']) ?>" onclick="return confirm('Bạn có chắc chắn muốn xóa khách hàng này?');" class="rounded border border-red-500 px-3 py-1 text-sm font-medium text-red-600 hover:bg-red-50">Xóa</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1 && $totalCustomers > 0) : ?>
                <div class="mt-6 flex flex-wrap justify-center gap-2">
                    <?php for ($i = 1; $i <= $totalPages; $i++) : ?>
                        <a href="?page=customers&search=<?= urlencode($search) ?>&p=<?= $i ?>" class="rounded border px-3 py-1 text-sm <?= ($i == $pageNumber) ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
