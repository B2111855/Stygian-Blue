<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
include '../../../database/config.php';

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
// Only branch managers (ID_QUYEN = 2 AND LOAI_NV = 'quan_ly') can access this API
if (!isset($_SESSION['ID_TK'])) {
    sendResponse(false, "Bạn cần đăng nhập.", null, 401);
}

// Verify user is a branch manager (staff with manager role)
if (($_SESSION['ID_QUYEN'] ?? '') != '2' || ($_SESSION['STAFF_TYPE'] ?? '') !== 'quan_ly') {
    sendResponse(false, "Bạn không có quyền truy cập API này. Chỉ quản lý chi nhánh mới được phép.", null, 403);
}

$managerBranchId = null;
$getBranchStmt = $conn->prepare("SELECT nv.ID_CN, nv.LOAI_NV FROM nhan_vien nv WHERE nv.ID_TK = ? AND nv.LOAI_NV = 'quan_ly' LIMIT 1");
$getBranchStmt->bind_param("s", $_SESSION['ID_TK']);
$getBranchStmt->execute();
$result = $getBranchStmt->get_result();
if ($row = $result->fetch_assoc()) {
    $managerBranchId = $row['ID_CN'];
} else {
    sendResponse(false, "Không tìm thấy chi nhánh của bạn hoặc bạn không phải quản lý chi nhánh.", null, 403);
}

// Determine request method
$method = $_SERVER['REQUEST_METHOD'];
$action = ($_GET['action'] ?? '') ?: ($_POST['action'] ?? '');

// ==================== CREATE EMPLOYEE ====================
if ($method === 'POST' && $action === 'create') {
    $id_tk = trim($_POST['ID_TK'] ?? '');
    $ho_ten = trim($_POST['HO_TEN'] ?? '');
    $ngay_sinh = trim($_POST['NGAY_SINH'] ?? '');
    $dia_chi = trim($_POST['DIA_CHI'] ?? '');
    $email = trim($_POST['EMAIL'] ?? '');
    $sdt = trim($_POST['SDT'] ?? '');
    $mat_khau = trim($_POST['MAT_KHAU'] ?? '');
    $id_cn = $managerBranchId; // Always use manager's branch
    $chuyen_mon = trim($_POST['CHUYEN_MON'] ?? '');
    $id_quyen = 2;
    $loai_nv = isset($_POST['LOAI_NV']) ? trim($_POST['LOAI_NV']) : 'chuyen_trach';
    if ($loai_nv !== 'chuyen_trach' && $loai_nv !== 'quan_ly') {
        $loai_nv = 'chuyen_trach';
    }

    // Validation
    if (empty($id_tk) || empty($ho_ten) || empty($email) || empty($sdt) || empty($mat_khau) || empty($chuyen_mon)) {
        sendResponse(false, "Vui lòng điền đầy đủ thông tin bắt buộc.", null, 400);
    } elseif (!preg_match("/^[a-zA-Z0-9]+$/", $id_tk)) {
        sendResponse(false, "ID tài khoản không hợp lệ.", null, 400);
    } elseif (!preg_match("/^[0-9]{10}$/", $sdt)) {
        sendResponse(false, "Số điện thoại phải bao gồm 10 chữ số.", null, 400);
    } elseif (strlen($mat_khau) < 6 || strlen($mat_khau) > 22) {
        sendResponse(false, "Mật khẩu phải có độ dài từ 6 đến 22 ký tự.", null, 400);
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, "Địa chỉ email không hợp lệ.", null, 400);
    }

    // Check if ID exists
    $checkIdQuery = "SELECT ID_TK FROM tai_khoan WHERE ID_TK = ?";
    $stmt = $conn->prepare($checkIdQuery);
    $stmt->bind_param("s", $id_tk);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        sendResponse(false, "ID tài khoản đã tồn tại.", null, 409);
    }

    // Check if email exists
    $checkEmailQuery = "SELECT 1 FROM tai_khoan WHERE EMAIL = ?";
    $stmtEmail = $conn->prepare($checkEmailQuery);
    $stmtEmail->bind_param("s", $email);
    $stmtEmail->execute();
    if ($stmtEmail->get_result()->num_rows > 0) {
        sendResponse(false, "Email đã được sử dụng.", null, 409);
    }

    // Hash password and insert
    $mat_khau_encrypted = password_hash($mat_khau, PASSWORD_DEFAULT);
    $insertTaiKhoan = $conn->prepare("INSERT INTO tai_khoan (ID_TK, ID_QUYEN, HO_TEN, NGAY_SINH, DIA_CHI, EMAIL, SDT, MAT_KHAU) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $insertTaiKhoan->bind_param("sissssss", $id_tk, $id_quyen, $ho_ten, $ngay_sinh, $dia_chi, $email, $sdt, $mat_khau_encrypted);

    if (!$insertTaiKhoan->execute()) {
        sendResponse(false, "Không thể thêm vào bảng tài khoản.", null, 500);
    }

    $insertNhanVien = $conn->prepare("INSERT INTO nhan_vien (ID_TK, ID_CN, CHUYEN_MON, LOAI_NV) VALUES (?, ?, ?, ?)");
    $insertNhanVien->bind_param("ssss", $id_tk, $id_cn, $chuyen_mon, $loai_nv);

    if (!$insertNhanVien->execute()) {
        sendResponse(false, "Không thể thêm vào bảng nhân viên.", null, 500);
    }

    // Create zero salary row for current month/year
    $nowMonth = (int)date('n');
    $nowYear = (int)date('Y');
    $checkSalary = $conn->prepare('SELECT 1 FROM luong_nhan_vien WHERE ID_TK = ? AND THANG = ? AND NAM = ? LIMIT 1');
    if ($checkSalary) {
        $checkSalary->bind_param('sii', $id_tk, $nowMonth, $nowYear);
        $checkSalary->execute();
        $exists = $checkSalary->get_result()->fetch_assoc();
        $checkSalary->close();
        if (!$exists) {
            $insSalary = $conn->prepare('INSERT INTO luong_nhan_vien (ID_TK, THANG, NAM, LUONG_CO_BAN, PHAN_TRAM_THUONG, TONG_TIEN_THUONG, TONG_LUONG, NGAY_TINH) VALUES (?, ?, ?, 0, 0, 0, 0, CURDATE())');
            if ($insSalary) {
                $insSalary->bind_param('sii', $id_tk, $nowMonth, $nowYear);
                $insSalary->execute();
                $insSalary->close();
            }
        }
    }

    sendResponse(true, "Thêm nhân viên mới thành công!", ['ID_TK' => $id_tk], 201);
}

// ==================== UPDATE EMPLOYEE ====================
if ($method === 'POST' && $action === 'update') {
    $id_tk = trim($_POST['ID_TK'] ?? '');
    $ho_ten = trim($_POST['HO_TEN'] ?? '');
    $ngay_sinh = trim($_POST['NGAY_SINH'] ?? '');
    $dia_chi = trim($_POST['DIA_CHI'] ?? '');
    $email = trim($_POST['EMAIL'] ?? '');
    $sdt = trim($_POST['SDT'] ?? '');
    $mat_khau = trim($_POST['MAT_KHAU'] ?? '');
    $chuyen_mon = trim($_POST['CHUYEN_MON'] ?? '');
    $loai_nv = trim($_POST['LOAI_NV'] ?? 'chuyen_trach');

    // Validation
    if (empty($id_tk)) {
        sendResponse(false, "ID tài khoản không hợp lệ.", null, 400);
    } elseif (empty($ho_ten) || empty($email) || empty($sdt)) {
        sendResponse(false, "Vui lòng điền đầy đủ thông tin bắt buộc.", null, 400);
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, "Địa chỉ email không hợp lệ.", null, 400);
    } elseif (!preg_match("/^[0-9]{10}$/", $sdt)) {
        sendResponse(false, "Số điện thoại phải gồm 10 chữ số.", null, 400);
    }

    // Verify employee belongs to manager's branch
    $verifyStmt = $conn->prepare("SELECT ID_CN FROM nhan_vien WHERE ID_TK = ? LIMIT 1");
    $verifyStmt->bind_param("s", $id_tk);
    $verifyStmt->execute();
    $verifyResult = $verifyStmt->get_result();
    if (!$verifyResult->fetch_assoc() || $verifyResult->fetch_assoc()['ID_CN'] !== $managerBranchId) {
        sendResponse(false, "Nhân viên này không thuộc chi nhánh của bạn.", null, 403);
    }

    $updateTaiKhoanQuery = "UPDATE tai_khoan SET HO_TEN = ?, NGAY_SINH = ?, DIA_CHI = ?, EMAIL = ?, SDT = ?";
    $params = [$ho_ten, $ngay_sinh, $dia_chi, $email, $sdt];
    $types = "sssss";

    if ($mat_khau !== '') {
        if (strlen($mat_khau) < 6) {
            sendResponse(false, "Mật khẩu phải ít nhất 6 ký tự.", null, 400);
        }
        $hashedPassword = password_hash($mat_khau, PASSWORD_DEFAULT);
        $updateTaiKhoanQuery .= ", MAT_KHAU = ?";
        $params[] = $hashedPassword;
        $types .= "s";
    }

    $updateTaiKhoanQuery .= " WHERE ID_TK = ?";
    $params[] = $id_tk;
    $types .= "s";

    $stmt = $conn->prepare($updateTaiKhoanQuery);
    if (!$stmt) {
        sendResponse(false, "Lỗi chuẩn bị câu lệnh: " . $conn->error, null, 500);
    }
    
    $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) {
        sendResponse(false, "Không thể cập nhật tài khoản: " . $stmt->error, null, 500);
    }

    $updateNhanVienQuery = "UPDATE nhan_vien SET CHUYEN_MON = ?, LOAI_NV = ? WHERE ID_TK = ?";
    $stmtNV = $conn->prepare($updateNhanVienQuery);
    if (!$stmtNV) {
        sendResponse(false, "Lỗi chuẩn bị câu lệnh: " . $conn->error, null, 500);
    }
    
    $stmtNV->bind_param("sss", $chuyen_mon, $loai_nv, $id_tk);

    if (!$stmtNV->execute()) {
        sendResponse(false, "Không thể cập nhật nhân viên: " . $stmtNV->error, null, 500);
    }

    sendResponse(true, "Cập nhật thông tin nhân viên thành công!", ['ID_TK' => $id_tk]);
}

// ==================== DELETE EMPLOYEE (SOFT DELETE) ====================
if ($method === 'POST' && $action === 'delete') {
    session_start();
    $id_tk = $_POST['ID_TK'] ?? '';
    $manager_id = $_SESSION['ID_TK'] ?? 'SYSTEM';
    $manager_role = $_SESSION['VAI_TRO'] ?? 'manager';

    if (empty($id_tk)) {
        sendResponse(false, "ID tài khoản không hợp lệ.", null, 400);
    }

    // Verify employee belongs to manager's branch AND get employee details
    $verifyStmt = $conn->prepare("SELECT nv.ID_CN, tk.HO_TEN, tk.EMAIL, nv.LOAI_NV FROM nhan_vien nv 
                                  JOIN tai_khoan tk ON nv.ID_TK = tk.ID_TK
                                  WHERE nv.ID_TK = ? AND nv.IS_DELETED = 0 LIMIT 1");
    $verifyStmt->bind_param("s", $id_tk);
    $verifyStmt->execute();
    $empData = $verifyStmt->get_result()->fetch_assoc();
    
    if (!$empData || $empData['ID_CN'] != $managerBranchId) {
        sendResponse(false, "Nhân viên này không thuộc chi nhánh của bạn.", null, 403);
    }

    // Check if employee has active assignments
    $checkAssignmentsQuery = "SELECT COUNT(*) as count FROM phan_cong_nhan_vien pc 
                              JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN
                              WHERE pc.ID_TK = ? AND lh.THOI_GIAN_KET_THUC > NOW()";
    $stmt = $conn->prepare($checkAssignmentsQuery);
    $stmt->bind_param("s", $id_tk);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) {
        sendResponse(false, "Không thể xóa nhân viên vì nhân viên này vẫn được phân công cho lịch hẹn sắp tới.", null, 409);
    }

    // Start transaction for soft delete + audit log
    $conn->begin_transaction();

    try {
        // Soft delete: Mark as deleted with timestamp
        $softDeleteQuery = "UPDATE nhan_vien SET IS_DELETED = 1, DELETED_AT = NOW() WHERE ID_TK = ?";
        $stmtDelete = $conn->prepare($softDeleteQuery);
        
        if (!$stmtDelete) {
            throw new Exception("Lỗi chuẩn bị câu lệnh: " . $conn->error);
        }
        
        $stmtDelete->bind_param("s", $id_tk);
        
        if (!$stmtDelete->execute()) {
            throw new Exception("Không thể xóa nhân viên. Vui lòng thử lại.");
        }

        // Log to nhat_ky_he_thong (system audit log)
        $truocJson = json_encode([
            'ID_TK' => $id_tk,
            'HO_TEN' => $empData['HO_TEN'],
            'EMAIL' => $empData['EMAIL'],
            'LOAI_NV' => $empData['LOAI_NV'],
            'ID_CN' => $empData['ID_CN']
        ], JSON_UNESCAPED_UNICODE);

        $logQuery = "INSERT INTO nhat_ky_he_thong (ACTOR_ID, VAI_TRO, HANH_DONG, DOI_TUONG, TRUOC_JSON, SAU_JSON, IP, USER_AGENT) 
                    VALUES (?, ?, 'DELETE_EMPLOYEE', 'employee', ?, NULL, ?, ?)";
        $logStmt = $conn->prepare($logQuery);
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $logStmt->bind_param("sssss", $manager_id, $manager_role, $truocJson, $ip, $userAgent);
        
        if (!$logStmt->execute()) {
            throw new Exception("Không thể ghi audit log.");
        }

        $conn->commit();
        sendResponse(true, "Nhân viên đã được xóa khỏi hệ thống. Dữ liệu sẽ bị xóa vĩnh viễn sau 30 ngày.", ['ID_TK' => $id_tk]);
    } catch (Exception $e) {
        $conn->rollback();
        sendResponse(false, $e->getMessage(), null, 500);
    }
}

// ==================== SEARCH EMPLOYEES ====================
if ($method === 'GET' && $action === 'search') {
    $search = trim($_GET['search'] ?? '');
    $role = trim($_GET['role'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, (int)($_GET['limit'] ?? 6));
    $offset = ($page - 1) * $limit;

    $query = "
    SELECT nv.ID_TK,
           nv.ID_CN,
           nv.CHUYEN_MON,
           nv.LOAI_NV,      
           tk.HO_TEN,
           tk.NGAY_SINH,
           tk.DIA_CHI,
           tk.EMAIL,
           tk.SDT,
           cn.TEN_CN
    FROM nhan_vien nv
    INNER JOIN tai_khoan tk ON nv.ID_TK = tk.ID_TK
    INNER JOIN chi_nhanh cn ON nv.ID_CN = cn.ID_CN
    WHERE nv.ID_CN = ? AND nv.IS_DELETED = 0
    ";

    $params = [$managerBranchId];
    $types = 's';

    if (!empty($search)) {
        $search_param = "%$search%";
        $query .= " AND (tk.ID_TK LIKE ? OR tk.HO_TEN LIKE ? OR tk.EMAIL LIKE ?)";
        $params = [$managerBranchId, $search_param, $search_param, $search_param];
        $types = 'ssss';
    }

    if (!empty($role)) {
        $query .= " AND nv.LOAI_NV = ?";
        $params[] = $role;
        $types .= 's';
    }

    // Get total count
    $countQuery = str_replace("SELECT nv.ID_TK, nv.ID_CN, nv.CHUYEN_MON, nv.LOAI_NV, tk.HO_TEN, tk.NGAY_SINH, tk.DIA_CHI, tk.EMAIL, tk.SDT, cn.TEN_CN", "SELECT COUNT(*) as total", $query);
    $countStmt = $conn->prepare($countQuery);
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $totalRecords = (int)$countStmt->get_result()->fetch_assoc()['total'];
    $totalPages = max(1, ceil($totalRecords / $limit));

    // Get data
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $employees = [];
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }

    sendResponse(true, "Lấy dữ liệu thành công.", [
        'employees' => $employees,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalRecords,
            'pages' => $totalPages
        ]
    ]);
}

// Default: Invalid action
sendResponse(false, "Action không hợp lệ.", null, 400);
?>
