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
    error_log('Exception in api_customers.php: ' . $e->getMessage() . '\n' . $e->getTraceAsString());
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

// Determine request method
$method = $_SERVER['REQUEST_METHOD'];
$action = ($_GET['action'] ?? '') ?: ($_POST['action'] ?? '');

// ==================== CREATE CUSTOMER ====================
if ($method === 'POST' && $action === 'create') {
    $id_tk = trim($_POST['ID_TK'] ?? '');
    $ho_ten = trim($_POST['HO_TEN'] ?? '');
    $ngay_sinh = trim($_POST['NGAY_SINH'] ?? '');
    $dia_chi = trim($_POST['DIA_CHI'] ?? '');
    $email = trim($_POST['EMAIL'] ?? '');
    $sdt = trim($_POST['SDT'] ?? '');
    $mat_khau = trim($_POST['MAT_KHAU'] ?? '');
    $id_quyen = 3; // Customer role

    // Normalize phone
    $sdt = preg_replace('/\D/', '', $sdt);

    // Validation
    if (empty($id_tk) || empty($ho_ten) || empty($ngay_sinh) || empty($dia_chi) || empty($email) || empty($sdt) || empty($mat_khau)) {
        sendResponse(false, "Vui lòng điền đầy đủ thông tin.", null, 400);
    } elseif (!preg_match('/^[A-Za-z0-9_]+$/', $id_tk)) {
        sendResponse(false, "ID tài khoản chỉ được chứa chữ, số hoặc dấu gạch dưới.", null, 400);
    } elseif (!preg_match('/^0[0-9]{9}$/', $sdt)) {
        sendResponse(false, "Số điện thoại phải bắt đầu bằng 0 và có 10 chữ số.", null, 400);
    } elseif (strlen($mat_khau) < 8) {
        sendResponse(false, "Mật khẩu phải có ít nhất 8 ký tự.", null, 400);
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, "Địa chỉ email không hợp lệ.", null, 400);
    }

    // Check date of birth (must be 18+)
    $dob = strtotime($ngay_sinh);
    if ($dob === false || $dob > strtotime('-18 years')) {
        sendResponse(false, "Khách hàng phải đủ 18 tuổi trở lên.", null, 400);
    }

    // Check if ID exists
    $checkIdQuery = "SELECT ID_TK FROM tai_khoan WHERE ID_TK = ?";
    $stmt = $conn->prepare($checkIdQuery);
    $stmt->bind_param("s", $id_tk);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        sendResponse(false, "ID tài khoản đã tồn tại.", null, 409);
    }
    $stmt->close();

    // Check if email exists
    $checkEmailQuery = "SELECT 1 FROM tai_khoan WHERE EMAIL = ?";
    $stmtEmail = $conn->prepare($checkEmailQuery);
    $stmtEmail->bind_param("s", $email);
    $stmtEmail->execute();
    if ($stmtEmail->get_result()->num_rows > 0) {
        sendResponse(false, "Email đã được sử dụng.", null, 409);
    }
    $stmtEmail->close();

    // Check if phone exists
    $checkPhoneQuery = "SELECT 1 FROM tai_khoan WHERE SDT = ?";
    $stmtPhone = $conn->prepare($checkPhoneQuery);
    $stmtPhone->bind_param("s", $sdt);
    $stmtPhone->execute();
    if ($stmtPhone->get_result()->num_rows > 0) {
        sendResponse(false, "Số điện thoại đã được sử dụng.", null, 409);
    }
    $stmtPhone->close();

    // Hash password and insert
    $mat_khau_encrypted = password_hash($mat_khau, PASSWORD_DEFAULT);
    $insertTaiKhoan = $conn->prepare("INSERT INTO tai_khoan (ID_TK, ID_QUYEN, HO_TEN, NGAY_SINH, DIA_CHI, EMAIL, SDT, MAT_KHAU) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $insertTaiKhoan->bind_param("sissssss", $id_tk, $id_quyen, $ho_ten, $ngay_sinh, $dia_chi, $email, $sdt, $mat_khau_encrypted);

    if (!$insertTaiKhoan->execute()) {
        sendResponse(false, "Không thể thêm vào bảng tài khoản.", null, 500);
    }
    $insertTaiKhoan->close();

    $insertKhachHang = $conn->prepare("INSERT INTO khach_hang (ID_TK, HO_TEN, NGAY_SINH, DIA_CHI, EMAIL, SDT) VALUES (?, ?, ?, ?, ?, ?)");
    $insertKhachHang->bind_param("ssssss", $id_tk, $ho_ten, $ngay_sinh, $dia_chi, $email, $sdt);

    if (!$insertKhachHang->execute()) {
        sendResponse(false, "Không thể thêm vào bảng khách hàng.", null, 500);
    }
    $insertKhachHang->close();

    sendResponse(true, "Thêm khách hàng mới thành công!", ['ID_TK' => $id_tk], 201);
}

// ==================== UPDATE CUSTOMER ====================
if ($method === 'POST' && $action === 'update') {
    $id_tk = trim($_POST['ID_TK'] ?? '');
    $ho_ten = trim($_POST['HO_TEN'] ?? '');
    $ngay_sinh = trim($_POST['NGAY_SINH'] ?? '');
    $dia_chi = trim($_POST['DIA_CHI'] ?? '');
    $email = trim($_POST['EMAIL'] ?? '');
    $sdt = trim($_POST['SDT'] ?? '');
    $mat_khau = trim($_POST['MAT_KHAU'] ?? '');

    // Normalize phone
    $sdt = preg_replace('/\D/', '', $sdt);

    // Validation
    if (empty($id_tk)) {
        sendResponse(false, "ID tài khoản không hợp lệ.", null, 400);
    } elseif (empty($ho_ten) || empty($ngay_sinh) || empty($dia_chi) || empty($email) || empty($sdt)) {
        sendResponse(false, "Vui lòng điền đầy đủ thông tin bắt buộc.", null, 400);
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, "Địa chỉ email không hợp lệ.", null, 400);
    } elseif (!preg_match('/^0[0-9]{9}$/', $sdt)) {
        sendResponse(false, "Số điện thoại phải bắt đầu bằng 0 và có 10 chữ số.", null, 400);
    }

    // Check date of birth (must be 18+)
    $dob = strtotime($ngay_sinh);
    if ($dob === false || $dob > strtotime('-18 years')) {
        sendResponse(false, "Khách hàng phải đủ 18 tuổi trở lên.", null, 400);
    }

    if ($mat_khau !== '' && strlen($mat_khau) < 8) {
        sendResponse(false, "Mật khẩu phải có ít nhất 8 ký tự.", null, 400);
    }

    // Check if email/phone already used by another account
    $checkDuplicateQuery = "SELECT ID_TK FROM tai_khoan WHERE (EMAIL = ? OR SDT = ?) AND ID_TK <> ? LIMIT 1";
    $stmtDup = $conn->prepare($checkDuplicateQuery);
    $stmtDup->bind_param("sss", $email, $sdt, $id_tk);
    $stmtDup->execute();
    $dupResult = $stmtDup->get_result();
    if ($dupResult->num_rows > 0) {
        $dupRow = $dupResult->fetch_assoc();
        if ($dupRow) {
            sendResponse(false, "Email hoặc số điện thoại đã được sử dụng.", null, 409);
        }
    }
    $stmtDup->close();

    // Update account
    $updateTaiKhoanQuery = "UPDATE tai_khoan SET HO_TEN = ?, NGAY_SINH = ?, DIA_CHI = ?, EMAIL = ?, SDT = ?";
    $params = [$ho_ten, $ngay_sinh, $dia_chi, $email, $sdt];
    $types = "sssss";

    if ($mat_khau !== '') {
        $updateTaiKhoanQuery .= ", MAT_KHAU = ?";
        $mat_khau_encrypted = password_hash($mat_khau, PASSWORD_DEFAULT);
        $params[] = $mat_khau_encrypted;
        $types .= "s";
    }

    $updateTaiKhoanQuery .= " WHERE ID_TK = ?";
    $params[] = $id_tk;
    $types .= "s";

    $stmtUpdate = $conn->prepare($updateTaiKhoanQuery);
    $stmtUpdate->bind_param($types, ...$params);

    if (!$stmtUpdate->execute()) {
        sendResponse(false, "Không thể cập nhật bảng tài khoản.", null, 500);
    }
    $stmtUpdate->close();

    // Update customer table
    $updateKhachHangQuery = "UPDATE khach_hang SET HO_TEN = ?, NGAY_SINH = ?, DIA_CHI = ?, EMAIL = ?, SDT = ? WHERE ID_TK = ?";
    $stmtCustomer = $conn->prepare($updateKhachHangQuery);
    $stmtCustomer->bind_param("ssssss", $ho_ten, $ngay_sinh, $dia_chi, $email, $sdt, $id_tk);

    if (!$stmtCustomer->execute()) {
        sendResponse(false, "Không thể cập nhật bảng khách hàng.", null, 500);
    }
    $stmtCustomer->close();

    sendResponse(true, "Cập nhật khách hàng thành công!", ['ID_TK' => $id_tk], 200);
}

// ==================== DELETE CUSTOMER ====================
if ($method === 'POST' && $action === 'delete') {
    $id_tk = trim($_POST['ID_TK'] ?? '');

    if (empty($id_tk)) {
        sendResponse(false, "ID tài khoản không hợp lệ.", null, 400);
    }

    // Check if customer has appointments
    $checkAppointmentQuery = "SELECT 1 FROM lich_hen WHERE ID_TK = ? LIMIT 1";
    $stmtCheck = $conn->prepare($checkAppointmentQuery);
    $stmtCheck->bind_param("s", $id_tk);
    $stmtCheck->execute();
    if ($stmtCheck->get_result()->num_rows > 0) {
        sendResponse(false, "Không thể xóa khách hàng vì đang có lịch hẹn.", null, 409);
    }
    $stmtCheck->close();

    // Get customer details before deletion (for audit)
    $custQuery = "SELECT kh.HO_TEN, kh.EMAIL, tk.SDT FROM khach_hang kh 
                  JOIN tai_khoan tk ON kh.ID_TK = tk.ID_TK WHERE kh.ID_TK = ?";
    $custStmt = $conn->prepare($custQuery);
    $custStmt->bind_param("s", $id_tk);
    $custStmt->execute();
    $custData = $custStmt->get_result()->fetch_assoc();
    $custStmt->close();

    if (!$custData) {
        sendResponse(false, "Khách hàng không tồn tại.", null, 404);
    }

    // Start transaction for soft delete
    $conn->begin_transaction();

    try {
        // Soft delete: Mark as deleted with timestamp
        $softDeleteQuery = "UPDATE khach_hang SET IS_DELETED = 1, DELETED_AT = NOW() WHERE ID_TK = ?";
        $stmtDelete = $conn->prepare($softDeleteQuery);
        
        if (!$stmtDelete) {
            throw new Exception("Lỗi chuẩn bị câu lệnh: " . $conn->error);
        }
        
        $stmtDelete->bind_param("s", $id_tk);
        
        if (!$stmtDelete->execute()) {
            throw new Exception("Không thể xóa khách hàng: " . $stmtDelete->error);
        }
        $stmtDelete->close();

        // Log to nhat_ky_he_thong (system audit log)
        $truocJson = json_encode([
            'ID_TK' => $id_tk,
            'HO_TEN' => $custData['HO_TEN'],
            'EMAIL' => $custData['EMAIL'],
            'SDT' => $custData['SDT']
        ], JSON_UNESCAPED_UNICODE);

        $logQuery = "INSERT INTO nhat_ky_he_thong (ACTOR_ID, VAI_TRO, HANH_DONG, DOI_TUONG, TRUOC_JSON, SAU_JSON, IP, USER_AGENT) 
                    VALUES (?, ?, 'DELETE_CUSTOMER', 'customer', ?, NULL, ?, ?)";
        $logStmt = $conn->prepare($logQuery);
        
        if (!$logStmt) {
            throw new Exception("Lỗi chuẩn bị audit log: " . $conn->error);
        }
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $actor_id = $_SESSION['ID_TK'] ?? 'system';
        $vai_tro = $_SESSION['ID_QUYEN'] ?? 'unknown';

        $logStmt->bind_param("sssss", $actor_id, $vai_tro, $truocJson, $ip, $userAgent);
        
        if (!$logStmt->execute()) {
            throw new Exception("Không thể ghi audit log: " . $logStmt->error);
        }
        $logStmt->close();

        $conn->commit();
        sendResponse(true, "Khách hàng đã được xóa khỏi hệ thống. Dữ liệu sẽ bị xóa vĩnh viễn sau 30 ngày.", ['ID_TK' => $id_tk]);
    } catch (Exception $e) {
        $conn->rollback();
        sendResponse(false, $e->getMessage(), null, 500);
    }
}

// ==================== SEARCH/LIST CUSTOMERS ====================
if ($method === 'GET' && $action === 'search') {
    $search = trim($_GET['search'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 8)));
    $offset = ($page - 1) * $limit;

    $baseQuery = "SELECT kh.ID_TK, kh.HO_TEN, kh.NGAY_SINH, kh.DIA_CHI, kh.EMAIL, kh.SDT
                  FROM khach_hang kh
                  INNER JOIN tai_khoan tk ON kh.ID_TK = tk.ID_TK
                  WHERE kh.IS_DELETED = 0";

    $countQuery = "SELECT COUNT(*) as total FROM khach_hang kh INNER JOIN tai_khoan tk ON kh.ID_TK = tk.ID_TK WHERE kh.IS_DELETED = 0";

    if ($search !== '') {
        $searchParam = '%' . $search . '%';
        $baseQuery .= " AND (tk.ID_TK LIKE ? OR kh.HO_TEN LIKE ? OR kh.EMAIL LIKE ? OR kh.SDT LIKE ?)";
        $countQuery .= " AND (tk.ID_TK LIKE ? OR kh.HO_TEN LIKE ? OR kh.EMAIL LIKE ? OR kh.SDT LIKE ?)";

        $stmtCount = $conn->prepare($countQuery);
        $stmtCount->bind_param("ssss", $searchParam, $searchParam, $searchParam, $searchParam);
    } else {
        $stmtCount = $conn->prepare($countQuery);
    }

    $stmtCount->execute();
    $countResult = $stmtCount->get_result();
    $total = (int)$countResult->fetch_assoc()['total'];
    $stmtCount->close();

    $baseQuery .= " ORDER BY kh.HO_TEN ASC LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($baseQuery);
    if ($search !== '') {
        $stmt->bind_param("ssssii", $searchParam, $searchParam, $searchParam, $searchParam, $limit, $offset);
    } else {
        $stmt->bind_param("ii", $limit, $offset);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $customers = [];

    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
    $stmt->close();

    $totalPages = max(1, ceil($total / $limit));

    sendResponse(true, "", [
        'customers' => $customers,
        'total' => $total,
        'page' => $page,
        'totalPages' => $totalPages,
        'limit' => $limit
    ]);
}

// ==================== GET DELETED CUSTOMERS ====================
if ($method === 'GET' && $action === 'get_deleted') {
    $search = trim($_GET['search'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;

    $baseQuery = "SELECT kh.ID_TK, kh.HO_TEN, kh.NGAY_SINH, kh.DIA_CHI, kh.EMAIL, kh.SDT, kh.DELETED_AT
                  FROM khach_hang kh
                  INNER JOIN tai_khoan tk ON kh.ID_TK = tk.ID_TK
                  WHERE kh.IS_DELETED = 1";

    $countQuery = "SELECT COUNT(*) as total FROM khach_hang kh INNER JOIN tai_khoan tk ON kh.ID_TK = tk.ID_TK WHERE kh.IS_DELETED = 1";

    if ($search !== '') {
        $searchParam = '%' . $search . '%';
        $baseQuery .= " AND (tk.ID_TK LIKE ? OR kh.HO_TEN LIKE ? OR kh.EMAIL LIKE ? OR kh.SDT LIKE ?)";
        $countQuery .= " AND (tk.ID_TK LIKE ? OR kh.HO_TEN LIKE ? OR kh.EMAIL LIKE ? OR kh.SDT LIKE ?)";

        $stmtCount = $conn->prepare($countQuery);
        $stmtCount->bind_param("ssss", $searchParam, $searchParam, $searchParam, $searchParam);
    } else {
        $stmtCount = $conn->prepare($countQuery);
    }

    $stmtCount->execute();
    $countResult = $stmtCount->get_result();
    $total = (int)$countResult->fetch_assoc()['total'];
    $stmtCount->close();

    $baseQuery .= " ORDER BY kh.DELETED_AT DESC LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($baseQuery);
    if ($search !== '') {
        $stmt->bind_param("ssssii", $searchParam, $searchParam, $searchParam, $searchParam, $limit, $offset);
    } else {
        $stmt->bind_param("ii", $limit, $offset);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $customers = [];

    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
    $stmt->close();

    $totalPages = max(1, ceil($total / $limit));

    sendResponse(true, "", [
        'customers' => $customers,
        'pagination' => [
            'page' => $page,
            'pages' => $totalPages,
            'total' => $total,
            'limit' => $limit
        ]
    ]);
}

// ==================== RESTORE CUSTOMER ====================
if ($method === 'POST' && $action === 'restore') {
    $id_tk = trim($_POST['ID_TK'] ?? '');

    if (empty($id_tk)) {
        sendResponse(false, "ID tài khoản không hợp lệ.", null, 400);
    }

    // Get customer details before restore (for audit)
    $custQuery = "SELECT kh.HO_TEN, kh.EMAIL, tk.SDT FROM khach_hang kh 
                  JOIN tai_khoan tk ON kh.ID_TK = tk.ID_TK WHERE kh.ID_TK = ? AND kh.IS_DELETED = 1";
    $custStmt = $conn->prepare($custQuery);
    $custStmt->bind_param("s", $id_tk);
    $custStmt->execute();
    $custData = $custStmt->get_result()->fetch_assoc();
    $custStmt->close();

    if (!$custData) {
        sendResponse(false, "Khách hàng không tồn tại hoặc chưa bị xóa.", null, 404);
    }

    // Start transaction for restore
    $conn->begin_transaction();

    try {
        // Restore: Mark as not deleted
        $restoreQuery = "UPDATE khach_hang SET IS_DELETED = 0, DELETED_AT = NULL WHERE ID_TK = ?";
        $stmtRestore = $conn->prepare($restoreQuery);
        
        if (!$stmtRestore) {
            throw new Exception("Lỗi chuẩn bị câu lệnh: " . $conn->error);
        }
        
        $stmtRestore->bind_param("s", $id_tk);
        
        if (!$stmtRestore->execute()) {
            throw new Exception("Không thể khôi phục khách hàng: " . $stmtRestore->error);
        }
        $stmtRestore->close();

        // Log to nhat_ky_he_thong (system audit log)
        $afterJson = json_encode([
            'ID_TK' => $id_tk,
            'HO_TEN' => $custData['HO_TEN'],
            'EMAIL' => $custData['EMAIL'],
            'SDT' => $custData['SDT'],
            'IS_DELETED' => 0
        ], JSON_UNESCAPED_UNICODE);

        $logQuery = "INSERT INTO nhat_ky_he_thong (ACTOR_ID, VAI_TRO, HANH_DONG, DOI_TUONG, TRUOC_JSON, SAU_JSON, IP, USER_AGENT) 
                    VALUES (?, ?, 'RESTORE_CUSTOMER', 'customer', NULL, ?, ?, ?)";
        $logStmt = $conn->prepare($logQuery);
        
        if (!$logStmt) {
            throw new Exception("Lỗi chuẩn bị audit log: " . $conn->error);
        }
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $actor_id = $_SESSION['ID_TK'] ?? 'system';
        $vai_tro = $_SESSION['ID_QUYEN'] ?? 'unknown';

        $logStmt->bind_param("sssss", $actor_id, $vai_tro, $afterJson, $ip, $userAgent);
        
        if (!$logStmt->execute()) {
            throw new Exception("Không thể ghi audit log: " . $logStmt->error);
        }
        $logStmt->close();

        $conn->commit();
        sendResponse(true, "Khách hàng đã được khôi phục thành công!", ['ID_TK' => $id_tk]);
    } catch (Exception $e) {
        $conn->rollback();
        sendResponse(false, $e->getMessage(), null, 500);
    }
}

// Default response
sendResponse(false, "Invalid action or request method.", null, 400);
?>
