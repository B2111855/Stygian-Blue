<?php

namespace App\Controllers;

use Exception;

/**
 * Assignment Controller
 * Handles manager assignment (phân công nhân viên) operations
 */
class AssignmentController
{
    private $conn;
    private $branchId;

    public function __construct($conn, $branchId = null)
    {
        $this->conn = $conn;
        $this->branchId = $branchId;
    }

    /**
     * Get all assignments with filters
     * GET /api/assignments
     */
    public function index()
    {
        try {
            $filters = [
                'staff' => $_GET['staff'] ?? '',
                'service' => $_GET['service'] ?? '',
                'status' => $_GET['status'] ?? '',
                'date_from' => $_GET['date_from'] ?? '',
                'date_to' => $_GET['date_to'] ?? '',
                'scope' => $_GET['scope'] ?? 'upcoming',
                'page' => max(1, (int)($_GET['page'] ?? 1)),
                'perPage' => max(1, min(100, (int)($_GET['perPage'] ?? 10)))
            ];

            $offset = ($filters['page'] - 1) * $filters['perPage'];

            // Build query
            $fromClause = 'FROM phan_cong_nhan_vien pc
                JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN
                JOIN tai_khoan tk ON pc.ID_TK = tk.ID_TK
                LEFT JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV
                LEFT JOIN hoa_don hd ON lh.ID_LICHHEN = hd.ID_LICHHEN';

            $conditions = ['lh.ID_CHINHANH = ?'];
            $types = 'i';
            $params = [$this->branchId];

            if ($filters['staff'] !== '') {
                $conditions[] = 'tk.HO_TEN LIKE ?';
                $types .= 's';
                $params[] = '%' . $filters['staff'] . '%';
            }

            if ($filters['service'] !== '') {
                $conditions[] = 'dv.TEN_DV LIKE ?';
                $types .= 's';
                $params[] = '%' . $filters['service'] . '%';
            }

            if ($filters['status'] !== '') {
                $conditions[] = 'lh.TRANGTHAI = ?';
                $types .= 's';
                $params[] = $filters['status'];
            }

            if ($filters['date_from']) {
                $conditions[] = 'DATE(pc.THOI_GIAN_BAT_DAU) >= ?';
                $types .= 's';
                $params[] = $filters['date_from'];
            }

            if ($filters['date_to']) {
                $conditions[] = 'DATE(pc.THOI_GIAN_BAT_DAU) <= ?';
                $types .= 's';
                $params[] = $filters['date_to'];
            }

            switch ($filters['scope']) {
                case 'seven':
                    $conditions[] = 'pc.THOI_GIAN_BAT_DAU BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)';
                    break;
                case 'today':
                    $conditions[] = 'DATE(pc.THOI_GIAN_BAT_DAU) = CURDATE()';
                    break;
                case 'past':
                    $conditions[] = 'pc.THOI_GIAN_BAT_DAU < NOW()';
                    break;
                case 'all':
                    break;
                default: // 'upcoming'
                    $conditions[] = 'pc.THOI_GIAN_BAT_DAU >= NOW()';
            }

            $whereClause = 'WHERE ' . implode(' AND ', $conditions);

            // Get total count
            $countSql = 'SELECT COUNT(*) as total ' . $fromClause . ' ' . $whereClause;
            $countStmt = $this->conn->prepare($countSql);
            $this->bindParams($countStmt, $types, $params);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            $countRow = $countResult->fetch_assoc();
            $countStmt->close();
            $totalRows = $countRow['total'] ?? 0;

            // Get data
            $dataSql = 'SELECT pc.ID_LICHHEN, pc.ID_TK, pc.THOI_GIAN_BAT_DAU, pc.THOI_GIAN_KET_THUC, 
                        tk.HO_TEN AS STAFF_NAME, tk.SDT, tk.EMAIL, lh.DIA_CHI_HEN, lh.TRANGTHAI, 
                        dv.TEN_DV, hd.ID_HD ' . $fromClause . ' ' . $whereClause . ' 
                        ORDER BY pc.THOI_GIAN_BAT_DAU DESC LIMIT ? OFFSET ?';
            
            $dataStmt = $this->conn->prepare($dataSql);
            $dataTypes = $types . 'ii';
            $dataParams = array_merge($params, [$filters['perPage'], $offset]);
            $this->bindParams($dataStmt, $dataTypes, $dataParams);
            $dataStmt->execute();
            $dataResult = $dataStmt->get_result();
            $assignments = $dataResult ? $dataResult->fetch_all(MYSQLI_ASSOC) : [];
            $dataStmt->close();

            return $this->response(true, 'Assignments retrieved successfully', [
                'data' => $assignments,
                'pagination' => [
                    'total' => $totalRows,
                    'page' => $filters['page'],
                    'perPage' => $filters['perPage'],
                    'totalPages' => ceil($totalRows / $filters['perPage'])
                ]
            ]);
        } catch (Exception $e) {
            return $this->response(false, 'Error retrieving assignments', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get single assignment
     * GET /api/assignments/{scheduleId}/{employeeId}
     */
    public function show($scheduleId, $employeeId)
    {
        try {
            $scheduleId = (int)$scheduleId;
            $employeeId = trim($employeeId);

            $sql = 'SELECT pc.ID_LICHHEN, pc.ID_TK, pc.THOI_GIAN_BAT_DAU, pc.THOI_GIAN_KET_THUC,
                    tk.HO_TEN, tk.SDT, tk.EMAIL, lh.DIA_CHI_HEN, lh.TRANGTHAI, dv.TEN_DV, hd.ID_HD
                    FROM phan_cong_nhan_vien pc
                    JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN
                    JOIN tai_khoan tk ON pc.ID_TK = tk.ID_TK
                    LEFT JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV
                    LEFT JOIN hoa_don hd ON lh.ID_LICHHEN = hd.ID_LICHHEN
                    WHERE pc.ID_LICHHEN = ? AND pc.ID_TK = ? AND lh.ID_CHINHANH = ?
                    LIMIT 1';

            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param('isi', $scheduleId, $employeeId, $this->branchId);
            $stmt->execute();
            $result = $stmt->get_result();
            $assignment = $result->fetch_assoc();
            $stmt->close();

            if (!$assignment) {
                return $this->response(false, 'Assignment not found', [], 404);
            }

            return $this->response(true, 'Assignment retrieved', $assignment);
        } catch (Exception $e) {
            return $this->response(false, 'Error retrieving assignment', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Create new assignment
     * POST /api/assignments
     */
    public function store()
    {
        try {
            $input = $this->getJsonInput();

            $scheduleId = (int)($input['schedule_id'] ?? 0);
            $employeeId = trim($input['employee_id'] ?? '');
            $startTime = $this->normalizeDateTimeInput($input['start_time'] ?? '');
            $endTime = $this->normalizeDateTimeInput($input['end_time'] ?? '');

            // Validation
            if ($scheduleId <= 0 || $employeeId === '' || !$startTime || !$endTime) {
                return $this->response(false, 'Missing or invalid required fields', [], 400);
            }

            if (strtotime($endTime) < strtotime($startTime)) {
                return $this->response(false, 'End time must be greater than start time', [], 400);
            }

            // Check schedule exists and belongs to branch
            $scheduleStmt = $this->conn->prepare("SELECT 1 FROM lich_hen WHERE ID_LICHHEN = ? AND ID_CHINHANH = ? AND TRANGTHAI = 'Đã xác nhận' LIMIT 1");
            $scheduleStmt->bind_param('ii', $scheduleId, $this->branchId);
            $scheduleStmt->execute();
            $scheduleStmt->store_result();
            if ($scheduleStmt->num_rows === 0) {
                $scheduleStmt->close();
                return $this->response(false, 'Schedule not found or not confirmed', [], 404);
            }
            $scheduleStmt->close();

            // Check employee exists and belongs to branch
            $staffStmt = $this->conn->prepare('SELECT 1 FROM nhan_vien WHERE ID_TK = ? AND ID_CN = ? LIMIT 1');
            $staffStmt->bind_param('si', $employeeId, $this->branchId);
            $staffStmt->execute();
            $staffStmt->store_result();
            if ($staffStmt->num_rows === 0) {
                $staffStmt->close();
                return $this->response(false, 'Employee not found or not in this branch', [], 404);
            }
            $staffStmt->close();

            // Check schedule not already assigned
            $existStmt = $this->conn->prepare('SELECT 1 FROM phan_cong_nhan_vien WHERE ID_LICHHEN = ? LIMIT 1');
            $existStmt->bind_param('i', $scheduleId);
            $existStmt->execute();
            $existStmt->store_result();
            if ($existStmt->num_rows > 0) {
                $existStmt->close();
                return $this->response(false, 'Schedule already assigned', [], 409);
            }
            $existStmt->close();

            // Insert assignment
            $insertStmt = $this->conn->prepare('INSERT INTO phan_cong_nhan_vien (ID_TK, ID_LICHHEN, THOI_GIAN_BAT_DAU, THOI_GIAN_KET_THUC) VALUES (?, ?, ?, ?)');
            $insertStmt->bind_param('siss', $employeeId, $scheduleId, $startTime, $endTime);
            $ok = $insertStmt->execute();
            $insertStmt->close();

            if (!$ok) {
                return $this->response(false, 'Failed to create assignment', [], 500);
            }

            return $this->response(true, 'Assignment created successfully', ['schedule_id' => $scheduleId, 'employee_id' => $employeeId], 201);
        } catch (Exception $e) {
            return $this->response(false, 'Error creating assignment', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update assignment
     * PUT /api/assignments/{scheduleId}/{employeeId}
     */
    public function update($scheduleId, $employeeId)
    {
        try {
            $scheduleId = (int)$scheduleId;
            $employeeId = trim($employeeId);
            $input = $this->getJsonInput();

            $startTime = $this->normalizeDateTimeInput($input['start_time'] ?? '');
            $endTime = $this->normalizeDateTimeInput($input['end_time'] ?? '');

            if (!$startTime || !$endTime) {
                return $this->response(false, 'Missing or invalid time fields', [], 400);
            }

            if (strtotime($endTime) < strtotime($startTime)) {
                return $this->response(false, 'End time must be greater than start time', [], 400);
            }

            // Check assignment exists and get status
            $checkStmt = $this->conn->prepare('SELECT lh.TRANGTHAI FROM phan_cong_nhan_vien pc 
                JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN 
                WHERE pc.ID_LICHHEN = ? AND pc.ID_TK = ? AND lh.ID_CHINHANH = ? LIMIT 1');
            $checkStmt->bind_param('isi', $scheduleId, $employeeId, $this->branchId);
            $checkStmt->execute();
            $status = null;
            $checkStmt->bind_result($status);
            $found = $checkStmt->fetch();
            $checkStmt->close();
            
            if (!$found) {
                return $this->response(false, 'Assignment not found', [], 404);
            }
            
            // Prevent updating completed schedules
            if ($status === 'Đã hoàn thành') {
                return $this->response(false, 'Cannot modify assignment for completed schedules', [], 403);
            }

            // Update
            $updateStmt = $this->conn->prepare('UPDATE phan_cong_nhan_vien SET THOI_GIAN_BAT_DAU = ?, THOI_GIAN_KET_THUC = ? WHERE ID_LICHHEN = ? AND ID_TK = ?');
            $updateStmt->bind_param('ssis', $startTime, $endTime, $scheduleId, $employeeId);
            $ok = $updateStmt->execute();
            $updateStmt->close();

            if (!$ok) {
                return $this->response(false, 'Failed to update assignment', [], 500);
            }

            return $this->response(true, 'Assignment updated successfully', ['schedule_id' => $scheduleId, 'employee_id' => $employeeId]);
        } catch (Exception $e) {
            return $this->response(false, 'Error updating assignment', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete assignment
     * DELETE /api/assignments/{scheduleId}/{employeeId}
     */
    public function destroy($scheduleId, $employeeId)
    {
        try {
            $scheduleId = (int)$scheduleId;
            $employeeId = trim($employeeId);

            // Check assignment exists and get status
            $checkStmt = $this->conn->prepare('SELECT lh.TRANGTHAI FROM phan_cong_nhan_vien pc 
                JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN 
                WHERE pc.ID_LICHHEN = ? AND pc.ID_TK = ? AND lh.ID_CHINHANH = ? LIMIT 1');
            $checkStmt->bind_param('isi', $scheduleId, $employeeId, $this->branchId);
            $checkStmt->execute();
            $status = null;
            $checkStmt->bind_result($status);
            $found = $checkStmt->fetch();
            $checkStmt->close();
            
            if (!$found) {
                return $this->response(false, 'Assignment not found', [], 404);
            }
            
            // Prevent deleting completed schedules
            if ($status === 'Đã hoàn thành') {
                return $this->response(false, 'Cannot delete assignment for completed schedules', [], 403);
            }

            // Delete
            $deleteStmt = $this->conn->prepare('DELETE FROM phan_cong_nhan_vien WHERE ID_LICHHEN = ? AND ID_TK = ?');
            $deleteStmt->bind_param('is', $scheduleId, $employeeId);
            $ok = $deleteStmt->execute();
            $deleteStmt->close();

            if (!$ok) {
                return $this->response(false, 'Failed to delete assignment', [], 500);
            }

            return $this->response(true, 'Assignment deleted successfully', ['schedule_id' => $scheduleId]);
        } catch (Exception $e) {
            return $this->response(false, 'Error deleting assignment', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get unassigned schedules
     * GET /api/assignments/unassigned/list
     */
    public function getUnassigned()
    {
        try {
            $includeOverdue = (bool)($_GET['include_overdue'] ?? false);

            $sql = "SELECT lh.ID_LICHHEN, lh.THOI_GIAN_BAT_DAU, lh.DIA_CHI_HEN, dv.TEN_DV, 
                    dv.THOI_LUONG_PHUT, kh.HO_TEN AS TEN_KHACH_HANG, kh.SDT AS SDT_KHACH_HANG
                    FROM lich_hen lh
                    LEFT JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV
                    LEFT JOIN khach_hang kh ON lh.ID_TK = kh.ID_TK
                    WHERE lh.ID_CHINHANH = ? AND lh.TRANGTHAI = 'Đã xác nhận' 
                    AND NOT EXISTS (SELECT 1 FROM phan_cong_nhan_vien pc WHERE pc.ID_LICHHEN = lh.ID_LICHHEN)";

            if (!$includeOverdue) {
                $sql .= " AND lh.THOI_GIAN_BAT_DAU >= NOW()";
            }

            $sql .= " ORDER BY lh.THOI_GIAN_BAT_DAU ASC";

            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param('i', $this->branchId);
            $stmt->execute();
            $result = $stmt->get_result();
            $unassigned = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // Set default duration if missing
            foreach ($unassigned as &$item) {
                if (!isset($item['THOI_LUONG_PHUT']) || !$item['THOI_LUONG_PHUT']) {
                    $item['THOI_LUONG_PHUT'] = 60;
                }
            }

            return $this->response(true, 'Unassigned schedules retrieved', ['data' => $unassigned]);
        } catch (Exception $e) {
            return $this->response(false, 'Error retrieving unassigned schedules', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get employees for assignment
     * GET /api/assignments/employees/list
     */
    public function getEmployees()
    {
        try {
            $sql = 'SELECT tk.ID_TK, tk.HO_TEN, tk.SDT, tk.EMAIL, nv.CHUYEN_MON
                    FROM nhan_vien nv
                    JOIN tai_khoan tk ON nv.ID_TK = tk.ID_TK
                    WHERE nv.ID_CN = ?
                    ORDER BY tk.HO_TEN ASC';

            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param('i', $this->branchId);
            $stmt->execute();
            $result = $stmt->get_result();
            $employees = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // Add workload for today
            foreach ($employees as &$emp) {
                $wlStmt = $this->conn->prepare('SELECT COUNT(*) as count FROM phan_cong_nhan_vien pc WHERE pc.ID_TK = ? AND DATE(pc.THOI_GIAN_BAT_DAU) = CURDATE()');
                $wlStmt->bind_param('s', $emp['ID_TK']);
                $wlStmt->execute();
                $wlRes = $wlStmt->get_result();
                $wlRow = $wlRes->fetch_assoc();
                $wlStmt->close();
                $emp['CA_TRONG_NGAY'] = (int)($wlRow['count'] ?? 0);
            }

            return $this->response(true, 'Employees retrieved', ['data' => $employees]);
        } catch (Exception $e) {
            return $this->response(false, 'Error retrieving employees', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get assignment statistics
     * GET /api/assignments/stats
     */
    public function getStats()
    {
        try {
            $stats = [
                'total' => $this->singleValue('SELECT COUNT(*) FROM phan_cong_nhan_vien pc JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN WHERE lh.ID_CHINHANH = ?', 'i', [$this->branchId]),
                'upcoming' => $this->singleValue('SELECT COUNT(*) FROM phan_cong_nhan_vien pc JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN WHERE lh.ID_CHINHANH = ? AND pc.THOI_GIAN_BAT_DAU >= NOW()', 'i', [$this->branchId]),
                'week' => $this->singleValue('SELECT COUNT(*) FROM phan_cong_nhan_vien pc JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN WHERE lh.ID_CHINHANH = ? AND pc.THOI_GIAN_BAT_DAU BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)', 'i', [$this->branchId]),
                'completed' => $this->singleValue('SELECT COUNT(*) FROM phan_cong_nhan_vien pc JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN WHERE lh.ID_CHINHANH = ? AND lh.TRANGTHAI = "Đã hoàn thành"', 'i', [$this->branchId]),
                'cancelled' => $this->singleValue('SELECT COUNT(*) FROM phan_cong_nhan_vien pc JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN WHERE lh.ID_CHINHANH = ? AND lh.TRANGTHAI = "Đã hủy"', 'i', [$this->branchId]),
                'staff_count' => $this->singleValue('SELECT COUNT(*) FROM nhan_vien WHERE ID_CN = ?', 'i', [$this->branchId]),
                'unassigned_count' => $this->singleValue('SELECT COUNT(*) FROM lich_hen lh WHERE lh.ID_CHINHANH = ? AND lh.TRANGTHAI = "Đã xác nhận" AND NOT EXISTS (SELECT 1 FROM phan_cong_nhan_vien pc WHERE pc.ID_LICHHEN = lh.ID_LICHHEN) AND lh.THOI_GIAN_BAT_DAU >= NOW()', 'i', [$this->branchId]),
                'overdue_unassigned_count' => $this->singleValue('SELECT COUNT(*) FROM lich_hen lh WHERE lh.ID_CHINHANH = ? AND lh.TRANGTHAI = "Đã xác nhận" AND NOT EXISTS (SELECT 1 FROM phan_cong_nhan_vien pc WHERE pc.ID_LICHHEN = lh.ID_LICHHEN) AND lh.THOI_GIAN_BAT_DAU < NOW()', 'i', [$this->branchId]),
            ];

            return $this->response(true, 'Stats retrieved', $stats);
        } catch (Exception $e) {
            return $this->response(false, 'Error retrieving stats', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle schedule change request
     * POST /api/assignments/requests/decision
     */
    public function handleRequest()
    {
        try {
            $input = $this->getJsonInput();

            $requestId = (int)($input['request_id'] ?? 0);
            $decision = trim($input['decision'] ?? '');

            if ($requestId <= 0 || !in_array($decision, ['approve', 'reject'])) {
                return $this->response(false, 'Invalid request or decision', [], 400);
            }

            $decisionLabel = $decision === 'approve' ? 'Đã duyệt' : 'Từ chối';

            // Get request info
            $scheduleId = null;
            $staffId = null;
            $infoStmt = $this->conn->prepare('SELECT yc.ID_LICHHEN, yc.ID_TK FROM yeu_cau_thay_doi_lich yc 
                JOIN lich_hen lh ON yc.ID_LICHHEN = lh.ID_LICHHEN 
                WHERE yc.ID_YEUCAU = ? AND lh.ID_CHINHANH = ? LIMIT 1');
            $infoStmt->bind_param('ii', $requestId, $this->branchId);
            $infoStmt->execute();
            $infoStmt->bind_result($scheduleId, $staffId);
            $found = $infoStmt->fetch();
            $infoStmt->close();

            if (!$found) {
                return $this->response(false, 'Request not found', [], 404);
            }

            // Update request status
            $updateStmt = $this->conn->prepare('UPDATE yeu_cau_thay_doi_lich SET TRANGTHAI = ? WHERE ID_YEUCAU = ?');
            $updateStmt->bind_param('si', $decisionLabel, $requestId);
            $ok = $updateStmt->execute();
            $updateStmt->close();

            if (!$ok) {
                return $this->response(false, 'Failed to update request', [], 500);
            }

            // If approved, delete old assignment so manager can reassign
            if ($decision === 'approve') {
                $delStmt = $this->conn->prepare('DELETE FROM phan_cong_nhan_vien WHERE ID_LICHHEN = ? AND ID_TK = ?');
                if ($delStmt) {
                    $delStmt->bind_param('is', $scheduleId, $staffId);
                    $delStmt->execute();
                    $delStmt->close();
                }
            }

            $msg = $decision === 'approve' ? 'Request approved' : 'Request rejected';
            return $this->response(true, $msg, ['request_id' => $requestId, 'decision' => $decision]);
        } catch (Exception $e) {
            return $this->response(false, 'Error handling request', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get pending schedule change requests
     * GET /api/assignments/requests
     */
    public function getRequests()
    {
        try {
            $limit = min(100, (int)($_GET['limit'] ?? 10));

            $sql = "SELECT yc.ID_YEUCAU, yc.ID_LICHHEN, yc.ID_TK AS STAFF_ID, yc.NOI_DUNG, yc.NGAY_GUI, 
                    tkstaff.HO_TEN AS STAFF_NAME, lh.THOI_GIAN_BAT_DAU, lh.DIA_CHI_HEN, dv.TEN_DV
                    FROM yeu_cau_thay_doi_lich yc
                    JOIN lich_hen lh ON yc.ID_LICHHEN = lh.ID_LICHHEN
                    LEFT JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV
                    LEFT JOIN tai_khoan tkstaff ON yc.ID_TK = tkstaff.ID_TK
                    WHERE lh.ID_CHINHANH = ? AND yc.TRANGTHAI = 'Chờ duyệt'
                    ORDER BY yc.NGAY_GUI DESC LIMIT ?";

            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param('ii', $this->branchId, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            $requests = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            return $this->response(true, 'Requests retrieved', ['data' => $requests]);
        } catch (Exception $e) {
            return $this->response(false, 'Error retrieving requests', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get branch info
     * GET /api/assignments/branch
     */
    public function getBranchInfo()
    {
        try {
            $sql = 'SELECT nv.ID_CN, cn.TEN_CN, cn.DIA_CHI, cn.SDT
                    FROM nhan_vien nv
                    JOIN chi_nhanh cn ON nv.ID_CN = cn.ID_CN
                    WHERE nv.ID_CN = ?
                    LIMIT 1';

            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param('i', $this->branchId);
            $stmt->execute();
            $result = $stmt->get_result();
            $branch = $result->fetch_assoc();
            $stmt->close();

            if (!$branch) {
                return $this->response(false, 'Branch not found', [], 404);
            }

            return $this->response(true, 'Branch info retrieved', $branch);
        } catch (Exception $e) {
            return $this->response(false, 'Error retrieving branch info', ['error' => $e->getMessage()], 500);
        }
    }

    // ============ HELPER METHODS ============

    private function getJsonInput()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        return is_array($input) ? $input : [];
    }

    private function normalizeDateTimeInput($value)
    {
        if (!$value) {
            return null;
        }
        $value = trim(str_replace('T', ' ', $value));
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $value)) {
            return null;
        }
        return strlen($value) === 16 ? $value . ':00' : $value;
    }

    private function bindParams(&$stmt, $types, &$params)
    {
        if (!$stmt || $types === '' || empty($params)) {
            return;
        }
        $bind = [$types];
        foreach ($params as $key => $value) {
            $bind[] = &$params[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }

    private function singleValue($sql, $types = '', $params = [])
    {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return 0;
        }
        if ($types !== '' && !empty($params)) {
            $this->bindParams($stmt, $types, $params);
        }
        $stmt->execute();
        $value = null;
        $stmt->bind_result($value);
        $stmt->fetch();
        $stmt->close();
        return (int)($value ?? 0);
    }

    private function response($success, $message, $data = [], $statusCode = 200)
    {
        http_response_code($statusCode);
        return json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data
        ]);
    }
}
