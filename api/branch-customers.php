<?php
header('Content-Type: application/json; charset=utf-8');

include '../database/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentAccount = $_SESSION['ID_TK'] ?? null;

if (!$currentAccount) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Không được phép truy cập']);
    exit;
}

// Get branch ID from session
$branchStmt = $conn->prepare('SELECT nv.ID_CN FROM nhan_vien nv WHERE nv.ID_TK = ? LIMIT 1');
$branchStmt->bind_param('s', $currentAccount);
$branchStmt->execute();
$branchStmt->bind_result($branchId);
$branchStmt->fetch();
$branchStmt->close();

if (!$branchId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Tài khoản chưa được gán chi nhánh']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ===== ACTION: save_note =====
if ($action === 'save_note' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId = trim($_POST['ID_TK'] ?? '');
    $note = trim($_POST['NOTE'] ?? '');
    $tag = $_POST['TAG'] ?? 'binh_thuong';
    $lastContact = normalizeDate($_POST['LAST_CONTACT'] ?? null);
    $nextAction = normalizeDate($_POST['NEXT_ACTION'] ?? null);
    
    $allowedTags = ['binh_thuong','tiem_nang','vip','nguy_co','can_cham_soc'];
    if (!in_array($tag, $allowedTags, true)) {
        $tag = 'binh_thuong';
    }

    if ($customerId === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Thiếu mã khách hàng']);
        exit;
    }

    // Verify customer belongs to this branch
    $checkStmt = $conn->prepare('SELECT 1 FROM lich_hen WHERE ID_TK = ? AND ID_CHINHANH = ? LIMIT 1');
    if ($checkStmt) {
        $checkStmt->bind_param('si', $customerId, $branchId);
        $checkStmt->execute();
        $checkStmt->store_result();
        if ($checkStmt->num_rows === 0) {
            $checkStmt->close();
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Khách hàng không thuộc chi nhánh của bạn']);
            exit;
        }
        $checkStmt->close();
    }

    // Ensure table exists
    ensureBranchCustomerNotesTable($conn);

    $stmt = $conn->prepare('
        INSERT INTO branch_customer_notes (ID_CN, ID_TK, NOTE, TAG, LAST_CONTACT, NEXT_ACTION)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE NOTE = VALUES(NOTE), TAG = VALUES(TAG), LAST_CONTACT = VALUES(LAST_CONTACT), NEXT_ACTION = VALUES(NEXT_ACTION)
    ');
    
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Lỗi server']);
        exit;
    }

    $stmt->bind_param('isssss', $branchId, $customerId, $note, $tag, $lastContact, $nextAction);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        echo json_encode(['success' => true, 'message' => 'Đã lưu ghi chú khách hàng']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Lưu ghi chú thất bại']);
    }
    exit;
}

// ===== ACTION: list_customers =====
if ($action === 'list_customers' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $filters = [
        'keyword' => trim($_GET['keyword'] ?? ''),
        'status'  => $_GET['status'] ?? '',
        'segment' => $_GET['segment'] ?? '',
        'rating'  => $_GET['rating'] ?? '',
        'from'    => normalizeDate($_GET['from'] ?? null),
        'to'      => normalizeDate($_GET['to'] ?? null),
        'sort'    => $_GET['sort'] ?? 'recent',
    ];
    
    $page = isset($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
    $perPage = 8;

    $appointments = fetchAppointments($conn, (int) $branchId, $filters);
    $revenueMap = fetchRevenueMap($conn, (int) $branchId, $filters);
    $ratingMap = fetchRatingMap($conn, (int) $branchId, $filters);
    $customers = aggregateCustomers($appointments, $revenueMap, $ratingMap);
    $customerIds = array_column($customers, 'ID_TK');
    $notesMap = fetchNotes($conn, (int) $branchId, $customerIds);

    // Apply status, segment, rating filters
    $filtered = array_filter($customers, function ($customer) use ($filters, $notesMap) {
        if ($filters['status'] && !isset($customer['status_code'])) {
            [$statusCode, $statusLabel] = determineStatus($customer);
            $customer['status_code'] = $statusCode;
        }
        
        if ($filters['status'] && ($customer['status_code'] ?? '') !== $filters['status']) {
            return false;
        }
        
        $segment = determineSegment($customer);
        $note = $notesMap[$customer['ID_TK']] ?? null;
        if ($note && ($note['TAG'] ?? '') === 'vip') {
            $segment = 'VIP (đánh dấu)';
        }
        
        if ($filters['segment'] && stripos($segment, $filters['segment']) === false) {
            return false;
        }
        
        if ($filters['rating']) {
            $rating = $customer['avg_rating'] ?? 0;
            if ($filters['rating'] === '4up' && $rating < 4) {
                return false;
            }
            if ($filters['rating'] === '5' && round($rating, 0) !== 5) {
                return false;
            }
        }
        
        return true;
    });

    // Apply sorting
    $sort = $filters['sort'];
    usort($filtered, function ($a, $b) use ($sort) {
        switch ($sort) {
            case 'value':
                return $b['total_revenue'] <=> $a['total_revenue'];
            case 'bookings':
                return $b['total_bookings'] <=> $a['total_bookings'];
            case 'name':
                return strcmp($a['HO_TEN'], $b['HO_TEN']);
            case 'recent':
            default:
                return strcmp($b['last_booking'] ?? '', $a['last_booking'] ?? '');
        }
    });

    $totalFiltered = count($filtered);
    $totalPages = max(1, (int) ceil($totalFiltered / $perPage));
    
    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $offset = ($page - 1) * $perPage;
    $pagedCustomers = array_slice($filtered, $offset, $perPage);

    // Enrich with status, segment, notes
    foreach ($pagedCustomers as &$customer) {
        [$statusCode, $statusLabel] = determineStatus($customer);
        $customer['status_code'] = $statusCode;
        $customer['status_label'] = $statusLabel;
        $segment = determineSegment($customer);
        $customer['segment'] = $segment;
        $note = $notesMap[$customer['ID_TK']] ?? null;
        if ($note) {
            $customer['note'] = $note;
            if (($note['TAG'] ?? '') === 'vip') {
                $customer['segment'] = 'VIP (đánh dấu)';
            }
        }
    }
    unset($customer);

    echo json_encode([
        'success' => true,
        'data' => $pagedCustomers,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $totalFiltered,
            'total_pages' => $totalPages,
        ]
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ']);
exit;

// ===== HELPER FUNCTIONS =====

function ensureBranchCustomerNotesTable(mysqli $conn): void
{
    $sql = "CREATE TABLE IF NOT EXISTS branch_customer_notes (
        ID_NOTE INT AUTO_INCREMENT PRIMARY KEY,
        ID_CN INT NOT NULL,
        ID_TK VARCHAR(20) NOT NULL,
        NOTE TEXT NULL,
        TAG ENUM('binh_thuong','tiem_nang','vip','nguy_co','can_cham_soc') DEFAULT 'binh_thuong',
        LAST_CONTACT DATE DEFAULT NULL,
        NEXT_ACTION DATE DEFAULT NULL,
        CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UPDATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_branch_customer (ID_CN, ID_TK),
        CONSTRAINT fk_branch_notes_branch FOREIGN KEY (ID_CN) REFERENCES chi_nhanh(ID_CN) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_branch_notes_customer FOREIGN KEY (ID_TK) REFERENCES khach_hang(ID_TK) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;";
    $conn->query($sql);
}

function normalizeDate(?string $value): ?string
{
    if ($value === null || trim($value) === '') {
        return null;
    }
    $value = trim($value);
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    return $dt ? $dt->format('Y-m-d') : null;
}

function bindDynamic(mysqli_stmt $stmt, string $types, array $params): void
{
    if ($types === '' || empty($params)) {
        return;
    }
    $bind = [$types];
    foreach ($params as $key => $value) {
        $bind[] = &$params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
}

function buildFilterData(int $branchId, array $filters): array
{
    $conditions = ['lh.ID_CHINHANH = ?'];
    $params = [$branchId];
    $types = 'i';

    if ($filters['keyword'] !== '') {
        $keyword = '%' . $filters['keyword'] . '%';
        $conditions[] = '(kh.HO_TEN LIKE ? OR kh.EMAIL LIKE ? OR kh.SDT LIKE ?)';
        $params[] = $keyword;
        $params[] = $keyword;
        $params[] = $keyword;
        $types .= 'sss';
    }

    if ($filters['from']) {
        $conditions[] = 'lh.THOI_GIAN_BAT_DAU >= ?';
        $params[] = $filters['from'] . ' 00:00:00';
        $types .= 's';
    }

    if ($filters['to']) {
        $conditions[] = 'lh.THOI_GIAN_BAT_DAU <= ?';
        $params[] = $filters['to'] . ' 23:59:59';
        $types .= 's';
    }

    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    return [$where, $types, $params];
}

function fetchAppointments(mysqli $conn, int $branchId, array $filters): array
{
    [$where, $types, $params] = buildFilterData($branchId, $filters);
    $sql = "
        SELECT
            lh.ID_LICHHEN,
            lh.ID_TK,
            lh.THOI_GIAN_BAT_DAU,
            lh.TRANGTHAI,
            lh.DIA_CHI_HEN,
            dv.TEN_DV,
            kh.HO_TEN,
            kh.EMAIL,
            kh.SDT,
            kh.DIA_CHI,
            kh.NGAY_SINH
        FROM lich_hen lh
        JOIN khach_hang kh ON kh.ID_TK = lh.ID_TK
        LEFT JOIN dich_vu dv ON dv.ID_DV = lh.ID_DV
        $where
        ORDER BY lh.THOI_GIAN_BAT_DAU DESC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    bindDynamic($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows ?: [];
}

function fetchRevenueMap(mysqli $conn, int $branchId, array $filters): array
{
    [$where, $types, $params] = buildFilterData($branchId, $filters);
    $sql = "
        SELECT lh.ID_TK, SUM(hd.TONG_TIEN) AS total_revenue
        FROM lich_hen lh
        JOIN hoa_don hd ON hd.ID_LICHHEN = lh.ID_LICHHEN AND hd.TRANGTHAI_THANHTOAN = 'Đã thanh toán'
        JOIN khach_hang kh ON kh.ID_TK = lh.ID_TK
        $where
        GROUP BY lh.ID_TK
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    bindDynamic($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    $map = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $map[$row['ID_TK']] = (float) $row['total_revenue'];
        }
    }
    $stmt->close();
    return $map;
}

function fetchRatingMap(mysqli $conn, int $branchId, array $filters): array
{
    [$where, $types, $params] = buildFilterData($branchId, $filters);
    $sql = "
        SELECT lh.ID_TK, AVG(ph.XEP_HANG_DV) AS avg_rating, COUNT(ph.ID_DV) AS feedback_count
        FROM phan_hoi_cua_khach_hang ph
        JOIN lich_hen lh ON lh.ID_TK = ph.ID_TK AND lh.ID_DV = ph.ID_DV
        JOIN khach_hang kh ON kh.ID_TK = lh.ID_TK
        $where
        GROUP BY lh.ID_TK
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    bindDynamic($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    $map = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $map[$row['ID_TK']] = [
                'avg_rating'    => $row['avg_rating'] !== null ? round((float) $row['avg_rating'], 1) : null,
                'feedback_count'=> (int) $row['feedback_count'],
            ];
        }
    }
    $stmt->close();
    return $map;
}

function aggregateCustomers(array $appointments, array $revenueMap, array $ratingMap): array
{
    $customers = [];
    foreach ($appointments as $row) {
        $id = $row['ID_TK'];
        if (!isset($customers[$id])) {
            $customers[$id] = [
                'ID_TK'          => $id,
                'HO_TEN'         => $row['HO_TEN'],
                'EMAIL'          => $row['EMAIL'],
                'SDT'            => $row['SDT'],
                'DIA_CHI'        => $row['DIA_CHI'],
                'NGAY_SINH'      => $row['NGAY_SINH'],
                'total_bookings' => 0,
                'completed_count'=> 0,
                'pending_count'  => 0,
                'upcoming_count' => 0,
                'first_booking'  => null,
                'last_booking'   => null,
                'next_booking'   => null,
                'services'       => [],
            ];
        }

        $customers[$id]['total_bookings']++;
        $ts = $row['THOI_GIAN_BAT_DAU'];
        if ($ts && (!$customers[$id]['first_booking'] || $ts < $customers[$id]['first_booking'])) {
            $customers[$id]['first_booking'] = $ts;
        }
        if ($ts && (!$customers[$id]['last_booking'] || $ts > $customers[$id]['last_booking'])) {
            $customers[$id]['last_booking'] = $ts;
        }
        $timestamp = $ts ? strtotime($ts) : null;
        if ($timestamp !== null && $timestamp >= time()) {
            $customers[$id]['upcoming_count']++;
            if (!$customers[$id]['next_booking'] || $ts < $customers[$id]['next_booking']) {
                $customers[$id]['next_booking'] = $ts;
            }
        }
        $status = $row['TRANGTHAI'];
        if (in_array($status, ['Đang chờ', 'Đã xác nhận'], true)) {
            $customers[$id]['pending_count']++;
        }
        if ($status === 'Đã hoàn thành') {
            $customers[$id]['completed_count']++;
        }
        if (!empty($row['TEN_DV'])) {
            $customers[$id]['services'][$row['TEN_DV']] = true;
        }
    }

    foreach ($customers as $id => &$customer) {
        $customer['services'] = $customer['services'] ? implode(', ', array_keys($customer['services'])) : '—';
        $customer['total_revenue'] = $revenueMap[$id] ?? 0.0;
        $customer['avg_rating'] = $ratingMap[$id]['avg_rating'] ?? null;
        $customer['feedback_count'] = $ratingMap[$id]['feedback_count'] ?? 0;
    }
    unset($customer);

    return array_values($customers);
}

function determineStatus(array $customer): array
{
    $now = new DateTimeImmutable('now');
    $last = $customer['last_booking'] ? new DateTimeImmutable($customer['last_booking']) : null;
    $days = $last ? $now->diff($last)->days : null;

    if ($customer['upcoming_count'] > 0) {
        return ['upcoming', 'Có lịch sắp tới'];
    }
    if ($last === null) {
        return ['no-history', 'Chưa từng đặt'];
    }
    if ($days !== null && $days <= 30) {
        return ['active', 'Quay lại gần đây'];
    }
    if ($days !== null && $days <= 90) {
        return ['cooldown', 'Ngưng tương tác'];
    }
    return ['at-risk', 'Nguy cơ rời đi'];
}

function determineSegment(array $customer): string
{
    if ($customer['total_bookings'] >= 5 || $customer['total_revenue'] >= 5000000) {
        return 'VIP';
    }
    if ($customer['total_bookings'] >= 3) {
        return 'Trung thành';
    }
    if ($customer['total_bookings'] >= 1) {
        return 'Tiềm năng';
    }
    return 'Khách mới';
}

function fetchNotes(mysqli $conn, int $branchId, array $customerIds): array
{
    if (!$customerIds) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($customerIds), '?'));
    $sql = "SELECT ID_TK, NOTE, TAG, LAST_CONTACT, NEXT_ACTION, UPDATED_AT FROM branch_customer_notes WHERE ID_CN = ? AND ID_TK IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $types = 'i' . str_repeat('s', count($customerIds));
    $params = array_merge([$branchId], $customerIds);
    bindDynamic($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    $notes = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $notes[$row['ID_TK']] = $row;
        }
    }
    $stmt->close();
    return $notes;
}
?>
