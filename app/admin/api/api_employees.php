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
    error_log('Exception in api_employees.php: ' . $e->getMessage() . '\n' . $e->getTraceAsString());
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
// Admin (ID_QUYEN = 1) full access; branch manager (ID_QUYEN = 2, STAFF_TYPE = 'quan_ly') limited to their branch
$isAdmin = isset($_SESSION['ID_QUYEN']) && $_SESSION['ID_QUYEN'] == '1';
$isManager = isset($_SESSION['ID_QUYEN'], $_SESSION['STAFF_TYPE'])
    && $_SESSION['ID_QUYEN'] == '2'
    && $_SESSION['STAFF_TYPE'] === 'quan_ly';

if (!$isAdmin && !$isManager) {
    sendResponse(false, "Bạn không có quyền truy cập API này. Chỉ admin hoặc quản lý chi nhánh được phép.", null, 403);
}

$managerBranchId = null;
if ($isManager) {
    $branchStmt = $conn->prepare("SELECT ID_CN FROM nhan_vien WHERE ID_TK = ? AND LOAI_NV = 'quan_ly' LIMIT 1");
    if ($branchStmt) {
        $branchStmt->bind_param('s', $_SESSION['ID_TK']);
        $branchStmt->execute();
        $branchRes = $branchStmt->get_result();
        if ($branchRes && ($branchRow = $branchRes->fetch_assoc())) {
            $managerBranchId = $branchRow['ID_CN'];
        }
        $branchStmt->close();
    }

    if ($managerBranchId === null) {
        sendResponse(false, "Không tìm thấy chi nhánh của bạn hoặc bạn không phải quản lý chi nhánh.", null, 403);
    }
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
    $id_cn = trim($_POST['ID_CN'] ?? '');
    $chuyen_mon = trim($_POST['CHUYEN_MON'] ?? '');
    $id_quyen = 2;
    $loai_nv = isset($_POST['LOAI_NV']) ? trim($_POST['LOAI_NV']) : 'chuyen_trach';
    if ($loai_nv !== 'chuyen_trach' && $loai_nv !== 'quan_ly') {
        $loai_nv = 'chuyen_trach';
    }

    // Restrict branch for manager
    if ($isManager) {
        $id_cn = $managerBranchId; // manager can only manage own branch
    }

    // Validation
    if (empty($id_tk) || empty($ho_ten) || empty($ngay_sinh) || empty($dia_chi) || empty($email) || empty($sdt) || empty($mat_khau) || empty($id_cn) || empty($chuyen_mon)) {
        sendResponse(false, "Vui lòng điền đầy đủ thông tin.", null, 400);
    } elseif (!preg_match("/^[a-zA-Z0-9]+$/", $id_tk)) {
        sendResponse(false, "ID tài khoản không hợp lệ.", null, 400);
    } elseif (!preg_match("/^[0-9]{10}$/", $sdt)) {
        sendResponse(false, "Số điện thoại phải bao gồm 10 chữ số.", null, 400);
    } elseif (strlen($mat_khau) < 6 || strlen($mat_khau) > 22) {
        sendResponse(false, "Mật khẩu phải có độ dài từ 6 đến 22 ký tự.", null, 400);
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, "Địa chỉ email không hợp lệ.", null, 400);
    }

    // Business rule: each branch can have only one manager
    if ($loai_nv === 'quan_ly') {
        $checkMgr = $conn->prepare("SELECT COUNT(*) AS cnt FROM nhan_vien WHERE ID_CN = ? AND LOAI_NV = 'quan_ly' AND IS_DELETED = 0");
        if ($checkMgr) {
            $checkMgr->bind_param('s', $id_cn);
            $checkMgr->execute();
            $mgrCnt = (int)($checkMgr->get_result()->fetch_assoc()['cnt'] ?? 0);
            $checkMgr->close();
            if ($mgrCnt > 0) {
                sendResponse(false, "Mỗi chi nhánh chỉ được phép có 1 quản lý.", null, 409);
            }
        }
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
    $id_cn = trim($_POST['ID_CN'] ?? '');
    $chuyen_mon = trim($_POST['CHUYEN_MON'] ?? '');
    $loai_nv = trim($_POST['LOAI_NV'] ?? 'chuyen_trach');

    if ($isManager) {
        $id_cn = $managerBranchId; // manager cannot move employees to another branch
    }

    // Validation
    if (empty($id_tk)) {
        sendResponse(false, "ID tài khoản không hợp lệ.", null, 400);
    } elseif (empty($ho_ten) || empty($email) || empty($sdt) || empty($id_cn)) {
        sendResponse(false, "Vui lòng điền đầy đủ thông tin bắt buộc.", null, 400);
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, "Địa chỉ email không hợp lệ.", null, 400);
    } elseif (!preg_match("/^[0-9]{10}$/", $sdt)) {
        sendResponse(false, "Số điện thoại phải gồm 10 chữ số.", null, 400);
    }

    // Check duplicate email/phone on other accounts
    $dupStmt = $conn->prepare("SELECT ID_TK FROM tai_khoan WHERE (EMAIL = ? OR SDT = ?) AND ID_TK <> ? LIMIT 1");
    if (!$dupStmt) {
        sendResponse(false, "Lỗi kiểm tra trùng dữ liệu: " . $conn->error, null, 500);
    }
    $dupStmt->bind_param('sss', $email, $sdt, $id_tk);
    $dupStmt->execute();
    $dupRes = $dupStmt->get_result();
    if ($dupRes && $dupRes->num_rows > 0) {
        $dupStmt->close();
        sendResponse(false, "Email hoặc số điện thoại đã được sử dụng.", null, 409);
    }
    $dupStmt->close();

    // Fetch current employee data for branch-change check and audit
    $currentEmpStmt = $conn->prepare("SELECT nv.ID_CN AS CUR_ID_CN, nv.LOAI_NV AS CUR_LOAI_NV, nv.CHUYEN_MON AS CUR_CHUYEN_MON, tk.HO_TEN AS CUR_HO_TEN, tk.NGAY_SINH AS CUR_NGAY_SINH, tk.DIA_CHI AS CUR_DIA_CHI, tk.EMAIL AS CUR_EMAIL, tk.SDT AS CUR_SDT FROM nhan_vien nv JOIN tai_khoan tk ON nv.ID_TK = tk.ID_TK WHERE nv.ID_TK = ? AND nv.IS_DELETED = 0 LIMIT 1");
    if (!$currentEmpStmt) {
        sendResponse(false, "Lỗi truy vấn dữ liệu hiện tại: " . $conn->error, null, 500);
    }
    $currentEmpStmt->bind_param('s', $id_tk);
    $currentEmpStmt->execute();
    $currentData = $currentEmpStmt->get_result()->fetch_assoc();
    $currentEmpStmt->close();
    if (!$currentData) {
        sendResponse(false, "Không tìm thấy nhân viên để cập nhật.", null, 404);
    }

    $curBranch = $currentData['CUR_ID_CN'];

    if ($isManager && $curBranch !== $managerBranchId) {
        sendResponse(false, "Bạn chỉ được phép chỉnh sửa nhân viên thuộc chi nhánh của bạn.", null, 403);
    }

    // Business rule: cannot change branch if employee has active assignments
    if ($id_cn !== $curBranch) {
        $assignStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM phan_cong_nhan_vien WHERE ID_TK = ? AND THOI_GIAN_KET_THUC > NOW()");
        if ($assignStmt) {
            $assignStmt->bind_param('s', $id_tk);
            $assignStmt->execute();
            $assignCnt = (int)($assignStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
            $assignStmt->close();
            if ($assignCnt > 0) {
                sendResponse(false, "Nhân viên đang có phân công hoạt động, không được phép đổi chi nhánh.", null, 409);
            }
        }
    }

    // Business rule: each branch can have only one manager
    if ($loai_nv === 'quan_ly') {
        $checkMgr = $conn->prepare("SELECT COUNT(*) AS cnt FROM nhan_vien WHERE ID_CN = ? AND LOAI_NV = 'quan_ly' AND IS_DELETED = 0 AND ID_TK <> ?");
        if ($checkMgr) {
            $checkMgr->bind_param('ss', $id_cn, $id_tk);
            $checkMgr->execute();
            $mgrCnt = (int)($checkMgr->get_result()->fetch_assoc()['cnt'] ?? 0);
            $checkMgr->close();
            if ($mgrCnt > 0) {
                sendResponse(false, "Chi nhánh này đã có quản lý.", null, 409);
            }
        }
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

    $updateNhanVienQuery = "UPDATE nhan_vien SET ID_CN = ?, CHUYEN_MON = ?, LOAI_NV = ? WHERE ID_TK = ?";
    $stmtNV = $conn->prepare($updateNhanVienQuery);
    if (!$stmtNV) {
        sendResponse(false, "Lỗi chuẩn bị câu lệnh: " . $conn->error, null, 500);
    }
    
    $stmtNV->bind_param("ssss", $id_cn, $chuyen_mon, $loai_nv, $id_tk);

    if (!$stmtNV->execute()) {
        sendResponse(false, "Không thể cập nhật nhân viên: " . $stmtNV->error, null, 500);
    }

    // Audit log: record before and after
    $admin_id = $_SESSION['ID_TK'] ?? 'unknown';
    $admin_role = $_SESSION['VAI_TRO'] ?? 'admin';
    $beforeJson = json_encode([
        'ID_TK' => $id_tk,
        'ID_CN' => $curBranch,
        'CHUYEN_MON' => $currentData['CUR_CHUYEN_MON'],
        'LOAI_NV' => $currentData['CUR_LOAI_NV'],
        'HO_TEN' => $currentData['CUR_HO_TEN'],
        'NGAY_SINH' => $currentData['CUR_NGAY_SINH'],
        'DIA_CHI' => $currentData['CUR_DIA_CHI'],
        'EMAIL' => $currentData['CUR_EMAIL'],
        'SDT' => $currentData['CUR_SDT'],
    ], JSON_UNESCAPED_UNICODE);

    $afterJson = json_encode([
        'ID_TK' => $id_tk,
        'ID_CN' => $id_cn,
        'CHUYEN_MON' => $chuyen_mon,
        'LOAI_NV' => $loai_nv,
        'HO_TEN' => $ho_ten,
        'NGAY_SINH' => $ngay_sinh,
        'DIA_CHI' => $dia_chi,
        'EMAIL' => $email,
        'SDT' => $sdt,
    ], JSON_UNESCAPED_UNICODE);

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $logStmt = $conn->prepare("INSERT INTO nhat_ky_he_thong (ACTOR_ID, VAI_TRO, HANH_DONG, DOI_TUONG, TRUOC_JSON, SAU_JSON, IP, USER_AGENT) VALUES (?, ?, 'UPDATE_EMPLOYEE', 'employee', ?, ?, ?, ?)");
    if ($logStmt) {
        // 6 placeholders: ACTOR_ID, VAI_TRO, TRUOC_JSON, SAU_JSON, IP, USER_AGENT
        $logStmt->bind_param('ssssss', $admin_id, $admin_role, $beforeJson, $afterJson, $ip, $userAgent);
        $logStmt->execute();
        $logStmt->close();
    }

    sendResponse(true, "Cập nhật thông tin nhân viên thành công!", ['ID_TK' => $id_tk]);
}

// ==================== DELETE EMPLOYEE (SOFT DELETE) ====================
if ($method === 'POST' && $action === 'delete') {
    $id_tk = $_POST['ID_TK'] ?? '';
    $admin_id = $_SESSION['ID_TK'] ?? null;
    $admin_role = $_SESSION['VAI_TRO'] ?? 'admin';
    
    // Verify admin is authenticated
    if (empty($admin_id)) {
        sendResponse(false, "Bạn cần đăng nhập để thực hiện hành động này.", null, 401);
    }

    if (empty($id_tk)) {
        sendResponse(false, "ID tài khoản không hợp lệ.", null, 400);
    }

    // Get employee details before deletion (for audit)
    $empQuery = "SELECT tk.HO_TEN, tk.EMAIL, nv.ID_CN, nv.LOAI_NV FROM nhan_vien nv 
                 JOIN tai_khoan tk ON nv.ID_TK = tk.ID_TK WHERE nv.ID_TK = ?";
    $empStmt = $conn->prepare($empQuery);
    
    if (!$empStmt) {
        sendResponse(false, "Lỗi chuẩn bị query: " . $conn->error, null, 500);
    }
    
    $empStmt->bind_param("s", $id_tk);
    
    if (!$empStmt->execute()) {
        sendResponse(false, "Lỗi truy vấn dữ liệu nhân viên: " . $empStmt->error, null, 500);
    }
    
    $empData = $empStmt->get_result()->fetch_assoc();
    
    if (!$empData) {
        sendResponse(false, "Nhân viên không tồn tại.", null, 404);
    }

    if ($isManager && $empData['ID_CN'] !== $managerBranchId) {
        sendResponse(false, "Bạn chỉ được phép xóa nhân viên thuộc chi nhánh của bạn.", null, 403);
    }

    // Check if employee has active assignments
    $checkAssignmentsQuery = "SELECT COUNT(*) as count FROM phan_cong_nhan_vien
                              WHERE ID_TK = ? AND THOI_GIAN_KET_THUC > NOW()";
    $stmt = $conn->prepare($checkAssignmentsQuery);
    
    if (!$stmt) {
        sendResponse(false, "Lỗi kiểm tra phân công: " . $conn->error, null, 500);
    }
    
    $stmt->bind_param("s", $id_tk);
    
    if (!$stmt->execute()) {
        sendResponse(false, "Lỗi truy vấn phân công: " . $stmt->error, null, 500);
    }
    
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
            throw new Exception("Không thể xóa nhân viên: " . $stmtDelete->error);
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
        
        if (!$logStmt) {
            throw new Exception("Lỗi chuẩn bị audit log: " . $conn->error);
        }
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $logStmt->bind_param("sssss", $admin_id, $admin_role, $truocJson, $ip, $userAgent);
        
        if (!$logStmt->execute()) {
            throw new Exception("Không thể ghi audit log: " . $logStmt->error);
        }

        $conn->commit();
        sendResponse(true, "Nhân viên đã được xóa khỏi hệ thống (xóa mềm). Dữ liệu sẽ được lưu trữ cho đến khi khôi phục thủ công.", ['ID_TK' => $id_tk]);
    } catch (Exception $e) {
        $conn->rollback();
        sendResponse(false, $e->getMessage(), null, 500);
    }
}

// ==================== SEARCH EMPLOYEES ====================
if ($method === 'GET' && $action === 'search') {
    $search = trim($_GET['search'] ?? '');
    $branch = trim($_GET['branch'] ?? '');
    $role = trim($_GET['role'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, (int)($_GET['limit'] ?? 6));
    $offset = ($page - 1) * $limit;

    if ($isManager) {
        // Branch managers can only view their own branch
        $branch = $managerBranchId;
    }

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
    WHERE nv.IS_DELETED = 0
    ";

    $params = [];
    $types = '';

    if (!empty($search)) {
        $search_param = "%$search%";
        $query .= " AND (tk.ID_TK LIKE ? OR tk.HO_TEN LIKE ? OR tk.EMAIL LIKE ? OR cn.TEN_CN LIKE ?)";
        $params = [$search_param, $search_param, $search_param, $search_param];
        $types = 'ssss';
    }

    if (!empty($branch)) {
        $query .= " AND nv.ID_CN = ?";
        $params[] = $branch;
        $types .= 's';
    }

    if (!empty($role)) {
        $query .= " AND nv.LOAI_NV = ?";
        $params[] = $role;
        $types .= 's';
    }

    // Get total count
    $countQuery = str_replace("SELECT nv.ID_TK, nv.ID_CN, nv.CHUYEN_MON, nv.LOAI_NV, tk.HO_TEN, tk.NGAY_SINH, tk.DIA_CHI, tk.EMAIL, tk.SDT, cn.TEN_CN", "SELECT COUNT(*) as total", $query);
    $countStmt = $conn->prepare($countQuery);
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $totalRecords = (int)$countStmt->get_result()->fetch_assoc()['total'];
    $totalPages = max(1, ceil($totalRecords / $limit));

    // Get data
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
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

// ==================== GET DELETED EMPLOYEES ====================
if ($method === 'GET' && $action === 'get_deleted') {
    
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $branch_id = isset($_GET['branch_id']) ? trim($_GET['branch_id']) : '';

    if ($isManager) {
        $branch_id = $managerBranchId;
    }

    $query = "
    SELECT 
        nv.ID_TK, 
        nv.ID_CN, 
        nv.CHUYEN_MON, 
        nv.LOAI_NV,
        nv.DELETED_AT,
        tk.HO_TEN, 
        tk.NGAY_SINH, 
        tk.DIA_CHI, 
        tk.EMAIL, 
        tk.SDT, 
        cn.TEN_CN
    FROM nhan_vien nv
    INNER JOIN tai_khoan tk ON nv.ID_TK = tk.ID_TK
    INNER JOIN chi_nhanh cn ON nv.ID_CN = cn.ID_CN
    WHERE nv.IS_DELETED = 1
    ";

    $params = [];
    $types = '';

    // Filter by branch if provided
    if (!empty($branch_id)) {
        $query .= " AND nv.ID_CN = ?";
        $params[] = $branch_id;
        $types .= 's';
    }

    if (!empty($search)) {
        $search_param = "%$search%";
        $query .= " AND (tk.ID_TK LIKE ? OR tk.HO_TEN LIKE ? OR tk.EMAIL LIKE ?)";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= 'sss';
    }

    // Get total count
    $countQuery = str_replace("SELECT nv.ID_TK, nv.ID_CN, nv.CHUYEN_MON, nv.LOAI_NV, nv.DELETED_AT, tk.HO_TEN, tk.NGAY_SINH, tk.DIA_CHI, tk.EMAIL, tk.SDT, cn.TEN_CN", "SELECT COUNT(*) as total", $query);
    $countStmt = $conn->prepare($countQuery);
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $totalRecords = (int)$countStmt->get_result()->fetch_assoc()['total'];
    $totalPages = max(1, ceil($totalRecords / $limit));

    // Get data
    $query .= " ORDER BY nv.DELETED_AT DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $employees = [];
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }

    sendResponse(true, "Lấy dữ liệu nhân viên đã xóa thành công.", [
        'employees' => $employees,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalRecords,
            'pages' => $totalPages
        ]
    ]);
}

// ==================== RESTORE DELETED EMPLOYEE ====================
if ($method === 'POST' && $action === 'restore') {
    $id_tk = trim($_POST['ID_TK'] ?? '');
    $admin_id = $_SESSION['ID_TK'] ?? null;
    $admin_role = $_SESSION['VAI_TRO'] ?? 'admin';
    
    // Verify admin is authenticated
    if (empty($admin_id)) {
        sendResponse(false, "Bạn cần đăng nhập để thực hiện hành động này.", null, 401);
    }

    if (empty($id_tk)) {
        sendResponse(false, "ID tài khoản không được để trống.", null, 400);
    }

    // Get employee details before restore
    $getStmt = $conn->prepare("
        SELECT nv.ID_TK, nv.DELETED_AT, tk.HO_TEN, tk.EMAIL, nv.ID_CN, nv.LOAI_NV
        FROM nhan_vien nv
        INNER JOIN tai_khoan tk ON nv.ID_TK = tk.ID_TK
        WHERE nv.ID_TK = ? AND nv.IS_DELETED = 1
    ");
    $getStmt->bind_param('s', $id_tk);
    $getStmt->execute();
    $empResult = $getStmt->get_result();

    if ($empResult->num_rows === 0) {
        sendResponse(false, "Nhân viên không tồn tại hoặc chưa bị xóa.", null, 404);
    }

    $empData = $empResult->fetch_assoc();

    if ($isManager && $empData['ID_CN'] !== $managerBranchId) {
        sendResponse(false, "Bạn chỉ được phép khôi phục nhân viên thuộc chi nhánh của bạn.", null, 403);
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Restore (soft delete undo)
        $restoreStmt = $conn->prepare("
            UPDATE nhan_vien 
            SET IS_DELETED = 0, DELETED_AT = NULL 
            WHERE ID_TK = ?
        ");
        $restoreStmt->bind_param('s', $id_tk);

        if (!$restoreStmt->execute()) {
            throw new Exception("Lỗi khi hoàn tác xóa nhân viên.");
        }

        // Log to audit table
        $actor_id = $admin_id;
        $vai_tro = $admin_role;
        $hanh_dong = 'RESTORE_EMPLOYEE';
        $doi_tuong = 'employee';
        $truoc_json = json_encode([
            'ID_TK' => $empData['ID_TK'],
            'HO_TEN' => $empData['HO_TEN'],
            'EMAIL' => $empData['EMAIL'],
            'DELETED_AT' => $empData['DELETED_AT'],
            'ID_CN' => $empData['ID_CN'],
            'LOAI_NV' => $empData['LOAI_NV'],
            'STATUS' => 'DELETED'
        ]);
        $sau_json = json_encode([
            'ID_TK' => $empData['ID_TK'],
            'HO_TEN' => $empData['HO_TEN'],
            'EMAIL' => $empData['EMAIL'],
            'ID_CN' => $empData['ID_CN'],
            'LOAI_NV' => $empData['LOAI_NV'],
            'STATUS' => 'ACTIVE'
        ]);
        $ip = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];

        $auditStmt = $conn->prepare("
            INSERT INTO nhat_ky_he_thong 
            (ACTOR_ID, VAI_TRO, HANH_DONG, DOI_TUONG, TRUOC_JSON, SAU_JSON, IP, USER_AGENT)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $auditStmt->bind_param('ssssssss', $actor_id, $vai_tro, $hanh_dong, $doi_tuong, $truoc_json, $sau_json, $ip, $user_agent);

        if (!$auditStmt->execute()) {
            throw new Exception("Lỗi khi ghi nhật ký hệ thống.");
        }

        $conn->commit();

        sendResponse(true, "Hoàn tác xóa nhân viên thành công.", [
            'ID_TK' => $id_tk,
            'HO_TEN' => $empData['HO_TEN'],
            'MESSAGE' => "Nhân viên {$empData['HO_TEN']} đã được khôi phục."
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        sendResponse(false, $e->getMessage(), null, 500);
    }
}

// Default: Invalid action
sendResponse(false, "Action không hợp lệ.", null, 400);
?>
