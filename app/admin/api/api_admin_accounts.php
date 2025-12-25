<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../../../logs/error.log');
include '../../../database/config.php';

// Global exception handler
set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi hệ thống: ' . $e->getMessage()
    ]);
    error_log('Exception in api_admin_accounts.php: ' . $e->getMessage() . '\n' . $e->getTraceAsString());
    exit;
});

// Helper function to send JSON response
function sendResponse($success, $message = '', $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// ==================== AUTHORIZATION CHECK ====================
// Only admin (ID_QUYEN = 1) can access this API
if (!isset($_SESSION['ID_QUYEN']) || $_SESSION['ID_QUYEN'] != '1') {
    error_log('API Access Denied - ID_QUYEN: ' . ($_SESSION['ID_QUYEN'] ?? 'NOT SET') . ', ID_TK: ' . ($_SESSION['ID_TK'] ?? 'NOT SET'));
    sendResponse(false, "Bạn không có quyền truy cập API này. Chỉ admin mới được phép. (ID_QUYEN: " . ($_SESSION['ID_QUYEN'] ?? 'NOT SET') . ")", null, 403);
}

// Determine request method
$method = $_SERVER['REQUEST_METHOD'];
$action = ($_GET['action'] ?? '') ?: ($_POST['action'] ?? '');

// ==================== CREATE ADMIN ACCOUNT ====================
if ($method === 'POST' && $action === 'create') {
    $id_tk = trim($_POST['ID_TK'] ?? '');
    $ho_ten = trim($_POST['HO_TEN'] ?? '');
    $email = trim($_POST['EMAIL'] ?? '');
    $mat_khau = trim($_POST['MAT_KHAU'] ?? '');

    // Validation
    if (empty($id_tk) || empty($ho_ten) || empty($email) || empty($mat_khau)) {
        sendResponse(false, "Vui lòng điền đầy đủ thông tin bắt buộc.", null, 400);
    } elseif (!preg_match("/^[a-zA-Z0-9]+$/", $id_tk)) {
        sendResponse(false, "ID tài khoản không hợp lệ (chỉ chữ và số).", null, 400);
    } elseif (strlen($mat_khau) < 6 || strlen($mat_khau) > 22) {
        sendResponse(false, "Mật khẩu phải có độ dài từ 6 đến 22 ký tự.", null, 400);
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, "Địa chỉ email không hợp lệ.", null, 400);
    }

    // Check if ID already exists
    $checkIdQuery = "SELECT ID_TK FROM tai_khoan WHERE ID_TK = ?";
    $stmt = $conn->prepare($checkIdQuery);
    if (!$stmt) {
        sendResponse(false, "Lỗi chuẩn bị câu lệnh: " . $conn->error, null, 500);
    }
    $stmt->bind_param("s", $id_tk);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        sendResponse(false, "ID tài khoản đã tồn tại.", null, 409);
    }
    $stmt->close();

    // Check if email already exists
    $checkEmailQuery = "SELECT ID_TK FROM tai_khoan WHERE EMAIL = ?";
    $stmt = $conn->prepare($checkEmailQuery);
    if (!$stmt) {
        sendResponse(false, "Lỗi chuẩn bị câu lệnh: " . $conn->error, null, 500);
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        sendResponse(false, "Email đã được sử dụng.", null, 409);
    }
    $stmt->close();

    // Hash password
    $hashedPassword = password_hash($mat_khau, PASSWORD_BCRYPT);

    // Insert new admin account
    $insertQuery = "INSERT INTO tai_khoan (ID_TK, HO_TEN, EMAIL, MAT_KHAU, ID_QUYEN) 
                    VALUES (?, ?, ?, ?, 1)";
    $stmt = $conn->prepare($insertQuery);
    if (!$stmt) {
        sendResponse(false, "Lỗi chuẩn bị câu lệnh: " . $conn->error, null, 500);
    }
    $stmt->bind_param("ssss", $id_tk, $ho_ten, $email, $hashedPassword);

    if ($stmt->execute()) {
        sendResponse(true, "Tạo tài khoản admin thành công.", ['ID_TK' => $id_tk], 201);
    } else {
        sendResponse(false, "Lỗi: Không thể tạo tài khoản admin. " . $stmt->error, null, 500);
    }
}

// ==================== UPDATE ADMIN ACCOUNT ====================
if ($method === 'POST' && $action === 'update') {
    $id_tk = trim($_POST['ID_TK'] ?? '');
    $ho_ten = trim($_POST['HO_TEN'] ?? '');
    $email = trim($_POST['EMAIL'] ?? '');
    $mat_khau = trim($_POST['MAT_KHAU'] ?? '');

    // Validation
    if (empty($id_tk) || empty($ho_ten) || empty($email)) {
        sendResponse(false, "Vui lòng điền đầy đủ thông tin bắt buộc.", null, 400);
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, "Địa chỉ email không hợp lệ.", null, 400);
    }

    // Check if account exists
    $checkQuery = "SELECT ID_TK FROM tai_khoan WHERE ID_TK = ? AND ID_QUYEN = 1";
    $stmt = $conn->prepare($checkQuery);
    if (!$stmt) {
        sendResponse(false, "Lỗi chuẩn bị câu lệnh: " . $conn->error, null, 500);
    }
    $stmt->bind_param("s", $id_tk);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        sendResponse(false, "Tài khoản admin không tồn tại.", null, 404);
    }
    $stmt->close();

    // Check if email is already used by another account
    $checkEmailQuery = "SELECT ID_TK FROM tai_khoan WHERE EMAIL = ? AND ID_TK != ?";
    $stmt = $conn->prepare($checkEmailQuery);
    if (!$stmt) {
        sendResponse(false, "Lỗi chuẩn bị câu lệnh: " . $conn->error, null, 500);
    }
    $stmt->bind_param("ss", $email, $id_tk);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        sendResponse(false, "Email đã được sử dụng bởi tài khoản khác.", null, 409);
    }
    $stmt->close();

    // Build update query
    if (!empty($mat_khau)) {
        if (strlen($mat_khau) < 6 || strlen($mat_khau) > 22) {
            sendResponse(false, "Mật khẩu phải có độ dài từ 6 đến 22 ký tự.", null, 400);
        }
        $hashedPassword = password_hash($mat_khau, PASSWORD_BCRYPT);
        $updateQuery = "UPDATE tai_khoan SET HO_TEN = ?, EMAIL = ?, MAT_KHAU = ? WHERE ID_TK = ? AND ID_QUYEN = 1";
        $stmt = $conn->prepare($updateQuery);
        if (!$stmt) {
            sendResponse(false, "Lỗi chuẩn bị câu lệnh: " . $conn->error, null, 500);
        }
        $stmt->bind_param("ssss", $ho_ten, $email, $hashedPassword, $id_tk);
    } else {
        $updateQuery = "UPDATE tai_khoan SET HO_TEN = ?, EMAIL = ? WHERE ID_TK = ? AND ID_QUYEN = 1";
        $stmt = $conn->prepare($updateQuery);
        if (!$stmt) {
            sendResponse(false, "Lỗi chuẩn bị câu lệnh: " . $conn->error, null, 500);
        }
        $stmt->bind_param("sss", $ho_ten, $email, $id_tk);
    }

    if ($stmt->execute()) {
        sendResponse(true, "Cập nhật tài khoản admin thành công.");
    } else {
        sendResponse(false, "Lỗi: Không thể cập nhật tài khoản admin. " . $stmt->error, null, 500);
    }
}

// ==================== DELETE ADMIN ACCOUNT ====================
if ($method === 'POST' && $action === 'delete') {
    $id_tk = trim($_POST['ID_TK'] ?? '');

    if (empty($id_tk)) {
        sendResponse(false, "Vui lòng chỉ định ID tài khoản.", null, 400);
    }

    // Prevent deleting the current admin account
    if ($id_tk === $_SESSION['ID_TK']) {
        sendResponse(false, "Bạn không thể xóa tài khoản của chính mình.", null, 403);
    }

    // Check if account is admin
    $checkQuery = "SELECT ID_TK FROM tai_khoan WHERE ID_TK = ? AND ID_QUYEN = 1";
    $stmt = $conn->prepare($checkQuery);
    if (!$stmt) {
        sendResponse(false, "Lỗi chuẩn bị câu lệnh: " . $conn->error, null, 500);
    }
    $stmt->bind_param("s", $id_tk);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        sendResponse(false, "Tài khoản admin không tồn tại.", null, 404);
    }
    $stmt->close();

    // Delete the admin account
    $deleteQuery = "DELETE FROM tai_khoan WHERE ID_TK = ? AND ID_QUYEN = 1";
    $stmt = $conn->prepare($deleteQuery);
    if (!$stmt) {
        sendResponse(false, "Lỗi chuẩn bị câu lệnh: " . $conn->error, null, 500);
    }
    $stmt->bind_param("s", $id_tk);

    if ($stmt->execute()) {
        sendResponse(true, "Xóa tài khoản admin thành công.");
    } else {
        sendResponse(false, "Lỗi: Không thể xóa tài khoản admin. " . $stmt->error, null, 500);
    }
}

// ==================== GET ALL ADMIN ACCOUNTS ====================
if ($method === 'GET' && $action === 'search') {
    $search = trim($_GET['search'] ?? '');
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = intval($_GET['limit'] ?? 10);
    $offset = ($page - 1) * $limit;

    // Build query
    $baseQuery = "FROM tai_khoan WHERE ID_QUYEN = 1";
    $conditions = [];

    if (!empty($search)) {
        $searchTerm = '%' . $conn->real_escape_string($search) . '%';
        $conditions[] = "(ID_TK LIKE '$searchTerm' OR HO_TEN LIKE '$searchTerm' OR EMAIL LIKE '$searchTerm')";
    }

    if (!empty($conditions)) {
        $baseQuery .= ' AND ' . implode(' AND ', $conditions);
    }

    // Get total count
    $countQuery = "SELECT COUNT(*) as total " . $baseQuery;
    $countResult = $conn->query($countQuery);
    $totalRecords = $countResult->fetch_assoc()['total'] ?? 0;

    // Get paginated results
    $dataQuery = "SELECT ID_TK, HO_TEN, EMAIL " . $baseQuery . " ORDER BY ID_TK DESC LIMIT $limit OFFSET $offset";
    $result = $conn->query($dataQuery);

    $admins = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $admins[] = $row;
        }
    }

    $totalPages = ceil($totalRecords / $limit);

    sendResponse(true, "Lấy danh sách admin thành công.", [
        'admins' => $admins,
        'pagination' => [
            'total' => $totalRecords,
            'page' => $page,
            'limit' => $limit,
            'pages' => $totalPages
        ]
    ]);
}

// If no action matched
sendResponse(false, "Action không hợp lệ.", null, 400);
