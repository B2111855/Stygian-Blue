<?php
/**
 * DEPRECATED: This API has been consolidated with the expense management system.
 * 
 * This file is deprecated because the system now uses the existing 'loai_chi_phi' table
 * for managing expense categories, shared across the entire system. Expense categories 
 * are no longer branch-specific but are managed centrally.
 * 
 * For managing expense categories, use:
 * - api_chi_phi_loai.php (existing system API for expense category management)
 * - manager_expenses_v2.php (expense dashboard with category filtering)
 * 
 * Original Purpose (now handled by existing system):
 * - GET: Lấy danh sách loại chi phí của chi nhánh
 * - POST: Thêm loại chi phí mới
 * - PUT: Cập nhật loại chi phí
 * - DELETE: Xóa loại chi phí
 * 
 * Migration Notes:
 * - Replaced separate 'loai_chi_phi_branch' table with unified 'loai_chi_phi' table
 * - All expense items now use 'ID_LOAI' column to reference expense categories
 * - See create_expense_categories.sql for schema migration details
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
        handleGetExpenseCategories($conn, $idCn);
    } elseif ($method === 'POST') {
        handlePostExpenseCategory($conn, $idCn);
    } elseif ($method === 'PUT') {
        handlePutExpenseCategory($conn, $idCn);
    } elseif ($method === 'DELETE') {
        handleDeleteExpenseCategory($conn, $idCn);
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleGetExpenseCategories($conn, $idCn) {
    $query = "SELECT 
        ID_LOAI_CP, 
        TEN_LOAI_CP, 
        ICON_LOAI_CP, 
        MO_TA, 
        THU_TU_HIEN_THI,
        CREATED_AT,
        UPDATED_AT
    FROM loai_chi_phi_branch 
    WHERE ID_CN = ? 
    ORDER BY THU_TU_HIEN_THI ASC, CREATED_AT DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $idCn);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    http_response_code(200);
    echo json_encode(['success' => true, 'data' => $categories]);
}

function handlePostExpenseCategory($conn, $idCn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['name']) || empty(trim($input['name']))) {
        http_response_code(400);
        echo json_encode(['error' => 'Tên loại chi phí không được để trống']);
        return;
    }
    
    $name = trim($input['name']);
    $icon = $input['icon'] ?? '📌';
    $description = $input['description'] ?? '';
    $order = isset($input['order']) ? (int)$input['order'] : 999;
    
    $query = "INSERT INTO loai_chi_phi_branch (ID_CN, TEN_LOAI_CP, ICON_LOAI_CP, MO_TA, THU_TU_HIEN_THI, CREATED_AT, UPDATED_AT)
    VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('isssi', $idCn, $name, $icon, $description, $order);
    
    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(['success' => true, 'id' => $conn->insert_id, 'message' => 'Thêm loại chi phí thành công']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $stmt->error]);
    }
}

function handlePutExpenseCategory($conn, $idCn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID loại chi phí không được để trống']);
        return;
    }
    
    $id = (int)$input['id'];
    
    // Kiểm tra quyền sở hữu
    $check = $conn->prepare("SELECT ID_LOAI_CP FROM loai_chi_phi_branch WHERE ID_LOAI_CP = ? AND ID_CN = ?");
    $check->bind_param('ii', $id, $idCn);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Không có quyền chỉnh sửa loại chi phí này']);
        return;
    }
    
    $updates = [];
    $params = [];
    $types = '';
    
    if (isset($input['name']) && !empty(trim($input['name']))) {
        $updates[] = 'TEN_LOAI_CP = ?';
        $params[] = trim($input['name']);
        $types .= 's';
    }
    
    if (isset($input['icon'])) {
        $updates[] = 'ICON_LOAI_CP = ?';
        $params[] = $input['icon'];
        $types .= 's';
    }
    
    if (isset($input['description'])) {
        $updates[] = 'MO_TA = ?';
        $params[] = $input['description'];
        $types .= 's';
    }
    
    if (isset($input['order'])) {
        $updates[] = 'THU_TU_HIEN_THI = ?';
        $params[] = (int)$input['order'];
        $types .= 'i';
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['error' => 'Không có dữ liệu để cập nhật']);
        return;
    }
    
    $updates[] = 'UPDATED_AT = NOW()';
    $params[] = $id;
    $types .= 'i';
    
    $query = "UPDATE loai_chi_phi_branch SET " . implode(', ', $updates) . " WHERE ID_LOAI_CP = ? AND ID_CN = ?";
    $params[] = $idCn;
    $types .= 'i';
    
    $stmt = $conn->prepare($query);
    array_unshift($params, $types);
    call_user_func_array([$stmt, 'bind_param'], $params);
    
    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Cập nhật loại chi phí thành công']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $stmt->error]);
    }
}

function handleDeleteExpenseCategory($conn, $idCn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID loại chi phí không được để trống']);
        return;
    }
    
    $id = (int)$input['id'];
    
    // Kiểm tra quyền sở hữu
    $check = $conn->prepare("SELECT ID_LOAI_CP FROM loai_chi_phi_branch WHERE ID_LOAI_CP = ? AND ID_CN = ?");
    $check->bind_param('ii', $id, $idCn);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Không có quyền xóa loại chi phí này']);
        return;
    }
    
    $query = "DELETE FROM loai_chi_phi_branch WHERE ID_LOAI_CP = ? AND ID_CN = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $id, $idCn);
    
    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Xóa loại chi phí thành công']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $stmt->error]);
    }
}
