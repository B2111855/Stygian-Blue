<?php
/**
 * API lấy chi phí phát sinh và lịch sử của chi nhánh
 * GET: Lấy danh sách chi phí theo tháng với lịch sử
 * POST: Thêm/cập nhật chi phí
 * DELETE: Xóa chi phí
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../../database/config.php';

header('Content-Type: application/json');

$idTk = $_SESSION['ID_TK'] ?? null;

if (!$idTk) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Lấy chi nhánh của người dùng
$branchStmt = $conn->prepare("SELECT ID_CN FROM nhan_vien WHERE ID_TK = ? LIMIT 1");
$branchStmt->bind_param('i', $idTk);
$branchStmt->execute();
$branchData = $branchStmt->get_result()->fetch_assoc();

if (!$branchData) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden: Branch not found']);
    exit;
}

$idCn = (int)$branchData['ID_CN'];

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        handleGetExpenses($conn, $idCn);
    } elseif ($method === 'POST') {
        handlePostExpense($conn, $idCn);
    } elseif ($method === 'PUT') {
        handlePutExpense($conn, $idCn);
    } elseif ($method === 'DELETE') {
        handleDeleteExpense($conn, $idCn);
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleGetExpenses($conn, $idCn) {
    $month = $_GET['month'] ?? date('Y-m');
    $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
    
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        $month = date('Y-m');
    }
    
    // Lấy chi phí phát sinh của tháng hiện tại
    $query = "SELECT 
        ID_CP,
        TEN_CP,
        MOTA_CP,
        GIA_TRI,
        NGAY_GIO
    FROM chi_phi_phat_sinh 
    WHERE ID_CN = ? AND DATE_FORMAT(NGAY_GIO, '%Y-%m') = ?";
    
    $params = [$idCn, $month];
    $types = 'is';
    
    if ($categoryId) {
        $query .= " AND TEN_CP = ?";
        $params[] = $categoryId;
        $types .= 's';
    }
    
    $query .= " ORDER BY NGAY_GIO DESC";
    
    $stmt = $conn->prepare($query);
    array_unshift($params, $types);
    call_user_func_array([$stmt, 'bind_param'], $params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $expenses = [];
    while ($row = $result->fetch_assoc()) {
        $expenses[] = $row;
    }
    
    // Lấy lịch sử 12 tháng của từng loại chi phí
    $historyQuery = "SELECT 
        DATE_FORMAT(NGAY_GIO, '%Y-%m') AS month_key,
        ID_LOAI,
        TEN_CP,
        SUM(GIA_TRI) AS total_value,
        COUNT(*) AS count_items
    FROM chi_phi_phat_sinh 
    WHERE ID_CN = ? AND DATE_FORMAT(NGAY_GIO, '%Y-%m') >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)";
    
    if ($categoryId) {
        $historyQuery .= " AND ID_LOAI = ?";
    }
    
    $historyQuery .= " GROUP BY month_key, ID_LOAI, TEN_CP 
    ORDER BY month_key DESC";
    
    $histStmt = $conn->prepare($historyQuery);
    if ($categoryId) {
        $histStmt->bind_param('ii', $idCn, $categoryId);
    } else {
        $histStmt->bind_param('i', $idCn);
    }
    $histStmt->execute();
    $histResult = $histStmt->get_result();
    
    $history = [];
    while ($row = $histResult->fetch_assoc()) {
        $key = $row['month_key'] . '_' . ($row['ID_LOAI'] ?? 'uncategorized');
        $history[$key] = $row;
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'month' => $month,
        'expenses' => $expenses,
        'history' => $history
    ]);
}

function handlePostExpense($conn, $idCn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['name']) || empty(trim($input['name']))) {
        http_response_code(400);
        echo json_encode(['error' => 'Tên chi phí không được để trống']);
        return;
    }
    
    $name = trim($input['name']);
    $amount = isset($input['amount']) ? (float)$input['amount'] : 0;
    $description = $input['description'] ?? '';
    $categoryId = isset($input['category_id']) ? (int)$input['category_id'] : null;
    $date = $input['date'] ?? date('Y-m-d H:i:s');
    
    if ($amount <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Số tiền phải lớn hơn 0']);
        return;
    }
    
    $query = "INSERT INTO chi_phi_phat_sinh (ID_CN, TEN_CP, MOTA_CP, GIA_TRI, NGAY_GIO)
    VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('issds', $idCn, $name, $description, $amount, $date);
    
    if ($stmt->execute()) {
        // Cập nhật bảng tai_chinh
        $financeInsertStmt = $conn->prepare("INSERT INTO tai_chinh (LOAI_GIAO_DICH, SO_TIEN, NGAY_GIAO_DICH, ID_CN, LOAI_CHI_TIET) 
        VALUES ('chi phí', ?, ?, ?, ?)");
        $financeInsertStmt->bind_param('dsss', $amount, $date, $idCn, $name);
        $financeInsertStmt->execute();
        
        http_response_code(201);
        echo json_encode(['success' => true, 'id' => $conn->insert_id, 'message' => 'Thêm chi phí thành công']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $stmt->error]);
    }
}

function handlePutExpense($conn, $idCn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID chi phí không được để trống']);
        return;
    }
    
    $id = (int)$input['id'];
    
    // Kiểm tra quyền sở hữu
    $check = $conn->prepare("SELECT ID_CP FROM chi_phi_phat_sinh WHERE ID_CP = ? AND ID_CN = ?");
    $check->bind_param('ii', $id, $idCn);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Không có quyền chỉnh sửa chi phí này']);
        return;
    }
    
    $updates = [];
    $params = [];
    $types = '';
    
    if (isset($input['name']) && !empty(trim($input['name']))) {
        $updates[] = 'TEN_CP = ?';
        $params[] = trim($input['name']);
        $types .= 's';
    }
    
    if (isset($input['amount']) && (float)$input['amount'] > 0) {
        $updates[] = 'GIA_TRI = ?';
        $params[] = (float)$input['amount'];
        $types .= 'd';
    }
    
    if (isset($input['description'])) {
        $updates[] = 'MOTA_CP = ?';
        $params[] = $input['description'];
        $types .= 's';
    }
    
    if (isset($input['category_id'])) {
        $updates[] = 'ID_LOAI = ?';
        $params[] = $input['category_id'] ? (int)$input['category_id'] : null;
        $types .= 'i';
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['error' => 'Không có dữ liệu để cập nhật']);
        return;
    }
    
    $updates[] = 'UPDATED_AT = NOW()';
    $params[] = $id;
    $params[] = $idCn;
    $types .= 'ii';
    
    $query = "UPDATE chi_phi_phat_sinh SET " . implode(', ', $updates) . " WHERE ID_CP = ? AND ID_CN = ?";
    
    $stmt = $conn->prepare($query);
    array_unshift($params, $types);
    call_user_func_array([$stmt, 'bind_param'], $params);
    
    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Cập nhật chi phí thành công']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $stmt->error]);
    }
}

function handleDeleteExpense($conn, $idCn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID chi phí không được để trống']);
        return;
    }
    
    $id = (int)$input['id'];
    
    // Kiểm tra quyền sở hữu
    $check = $conn->prepare("SELECT ID_CP FROM chi_phi_phat_sinh WHERE ID_CP = ? AND ID_CN = ?");
    $check->bind_param('ii', $id, $idCn);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Không có quyền xóa chi phí này']);
        return;
    }
    
    $query = "DELETE FROM chi_phi_phat_sinh WHERE ID_CP = ? AND ID_CN = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $id, $idCn);
    
    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Xóa chi phí thành công']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $stmt->error]);
    }
}
