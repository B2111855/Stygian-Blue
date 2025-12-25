<?php
include '../../database/config.php';

$currentMonth = (int)date('m');
$currentYear = (int)date('Y');

// Xác định vai trò và chi nhánh của người dùng hiện tại
// Sử dụng session chuẩn từ login.php
$sessionRole = $_SESSION['role'] ?? null; // 'admin' | 'branch_manager' | 'staff' | 'customer'
$userId = $_SESSION['ID_TK'] ?? null;
$userBranchId = isset($_SESSION['branch_id']) ? (int)$_SESSION['branch_id'] : null;

// Xác định vai trò từ session
$isAdmin = ($sessionRole === 'admin');
$isManager = ($sessionRole === 'branch_manager');

// Chi nhánh đã có sẵn trong session khi đăng nhập; không truy vấn lại

function bindQueryParams(mysqli_stmt $stmt, string $types, array $params): void
{
    if ($types === '' || empty($params)) {
        return;
    }

    $bindValues = [$types];
    foreach ($params as $key => $value) {
        $bindValues[] = &$params[$key];
    }

    call_user_func_array([$stmt, 'bind_param'], $bindValues);
}

function buildSalaryFilters(?string $search, ?int $month, ?int $year, ?int $branch, ?int $userBranchId = null, bool $isManager = false): array
{
    $conditions = [];
    $types = '';
    $params = [];

    // Bỏ qua nhân viên đã xóa mềm
    $conditions[] = 'COALESCE(nv.IS_DELETED, 0) = 0';

    if ($search !== null && $search !== '') {
        $conditions[] = '(tk.HO_TEN LIKE ? OR tk.ID_TK LIKE ?)';
        $types .= 'ss';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    if ($month !== null) {
        $conditions[] = 'lnv.THANG = ?';
        $types .= 'i';
        $params[] = $month;
    }

    if ($year !== null) {
        $conditions[] = 'lnv.NAM = ?';
        $types .= 'i';
        $params[] = $year;
    }

    // Nếu là quản lý chi nhánh, tự động lọc theo chi nhánh của họ
    if ($isManager && $userBranchId !== null) {
        $conditions[] = 'nv.ID_CN = ?';
        $types .= 'i';
        $params[] = $userBranchId;
    } elseif ($branch !== null) {
        // Admin có thể chọn chi nhánh
        $conditions[] = 'nv.ID_CN = ?';
        $types .= 'i';
        $params[] = $branch;
    }

    $clause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

    return [$clause, $types, $params];
}

function buildQueryString(array $params): string
{
    $filtered = [];
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $filtered[$key] = $value;
    }

    return http_build_query($filtered);
}

function formatCurrency(int $amount): string
{
    return number_format($amount, 0, ',', '.') . ' VND';
}

function syncPayrollExpenses(mysqli $conn, int $month, int $year, ?int $branchId = null): void
{
    // Chuẩn bị dữ liệu tổng lương theo chi nhánh cho kỳ được chọn
    $subQuery = 'SELECT nv.ID_CN, SUM(lnv.TONG_LUONG) AS total_amount
                 FROM luong_nhan_vien lnv
                 JOIN nhan_vien nv ON nv.ID_TK = lnv.ID_TK
                 WHERE lnv.THANG = ? AND lnv.NAM = ?
                   AND COALESCE(nv.IS_DELETED, 0) = 0';

    if ($branchId !== null) {
        $subQuery .= ' AND nv.ID_CN = ?';
    }

    $subQuery .= ' GROUP BY nv.ID_CN';

    $periodDate = sprintf('%04d-%02d-01', $year, $month);

    // Cập nhật số tiền nếu bản ghi kỳ này đã tồn tại (upsert hành vi UPDATE trước)
    $updateSql = "UPDATE tai_chinh tc
                  JOIN ($subQuery) AS sub ON tc.ID_CN = sub.ID_CN
                    AND tc.LOAI_GIAO_DICH = 'chi phí'
                    AND tc.LOAI_CHI_TIET = 'Lương nhân viên'
                    AND MONTH(tc.NGAY_GIAO_DICH) = ?
                    AND YEAR(tc.NGAY_GIAO_DICH) = ?
                  SET tc.SO_TIEN = sub.total_amount,
                      tc.NGAY_GIAO_DICH = ?";

    $updateStmt = $conn->prepare($updateSql);
    if (!$updateStmt) {
        throw new RuntimeException('Không thể chuẩn bị UPDATE chi phí lương: ' . $conn->error);
    }

    $updateTypes = 'ii';
    $updateParams = [$month, $year];
    if ($branchId !== null) {
        $updateTypes .= 'i';
        $updateParams[] = $branchId;
    }
    $updateTypes .= 'iis';
    $updateParams[] = $month;
    $updateParams[] = $year;
    $updateParams[] = $periodDate;

    bindQueryParams($updateStmt, $updateTypes, $updateParams);
    $updateStmt->execute();
    $updateStmt->close();

    // Chèn mới cho những chi nhánh/kỳ chưa có bản ghi
        $insertSql = "INSERT INTO tai_chinh (LOAI_GIAO_DICH, LOAI_CHI_TIET, SO_TIEN, NGAY_GIAO_DICH, ID_CN)
                                    SELECT 'chi phí', 'Lương nhân viên', sub.total_amount, ?, sub.ID_CN
                                    FROM ($subQuery) AS sub
                                    WHERE NOT EXISTS (
                                            SELECT 1 FROM tai_chinh tc
                                            WHERE tc.ID_CN = sub.ID_CN
                                                AND tc.LOAI_GIAO_DICH = 'chi phí'
                                                AND tc.LOAI_CHI_TIET = 'Lương nhân viên'
                                                AND MONTH(tc.NGAY_GIAO_DICH) = ?
                                                AND YEAR(tc.NGAY_GIAO_DICH) = ?
                                    )";

    $insertStmt = $conn->prepare($insertSql);
    if (!$insertStmt) {
        throw new RuntimeException('Không thể chuẩn bị INSERT chi phí lương: ' . $conn->error);
    }

    $insertTypes = 'ii';
    $insertParams = [$month, $year];
    if ($branchId !== null) {
        $insertTypes .= 'i';
        $insertParams[] = $branchId;
    }
    $insertTypes .= 'sii';
    $insertParams[] = $periodDate;
    $insertParams[] = $month;
    $insertParams[] = $year;

    bindQueryParams($insertStmt, $insertTypes, $insertParams);
    $insertStmt->execute();
    $insertStmt->close();
}

function recalculateSalaries(mysqli $conn, ?int $branchId, int $month, int $year): array
{
    $month = max(1, min(12, $month));
    $year = max(2000, $year);
    $bonusRate = 0.15;

    $employeeSql = 'SELECT tk.ID_TK, nv.ID_CN, COALESCE(lnv.LUONG_CO_BAN, 2000000) AS base_salary
                    FROM tai_khoan tk
                    JOIN nhan_vien nv ON nv.ID_TK = tk.ID_TK
                    LEFT JOIN luong_nhan_vien lnv ON lnv.ID_TK = tk.ID_TK 
                        AND lnv.THANG = ? AND lnv.NAM = ?
                    WHERE tk.ID_QUYEN = 2
                      AND COALESCE(nv.IS_DELETED, 0) = 0
                    GROUP BY tk.ID_TK, nv.ID_CN';

    if ($branchId !== null) {
        $employeeSql .= ' AND nv.ID_CN = ?';
    }

    $employeeStmt = $conn->prepare($employeeSql);
    if (!$employeeStmt) {
        throw new RuntimeException('Không thể chuẩn bị danh sách nhân viên: ' . $conn->error);
    }

    $bindTypes = 'ii';
    $bindParams = [$month, $year];
    if ($branchId !== null) {
        $bindTypes .= 'i';
        $bindParams[] = $branchId;
    }
    bindQueryParams($employeeStmt, $bindTypes, $bindParams);

    $employeeStmt->execute();
    $employeeResult = $employeeStmt->get_result();
    $employees = [];
    while ($employeeResult && $row = $employeeResult->fetch_assoc()) {
        $employees[] = $row;
    }
    $employeeStmt->close();

    if (!$employees) {
        return [
            'processed' => 0,
            'total_payroll' => 0,
            'total_bonus' => 0,
        ];
    }

    $bonusSql = "SELECT COALESCE(SUM(dgdv.DON_GIA * ?), 0) AS tong_thuong
                 FROM phan_cong_nhan_vien pc
                 JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN
                 JOIN hoa_don hd ON hd.ID_LICHHEN = lh.ID_LICHHEN
                 JOIN don_gia_dich_vu dgdv ON lh.ID_DV = dgdv.ID_DV
                 WHERE pc.ID_TK = ?
                   AND lh.TRANGTHAI = 'Đã hoàn thành'
                   AND MONTH(hd.NGAY_GIO) = ?
                   AND YEAR(hd.NGAY_GIO) = ?";

    $bonusStmt = $conn->prepare($bonusSql);
    if (!$bonusStmt) {
        throw new RuntimeException('Không thể chuẩn bị truy vấn thưởng: ' . $conn->error);
    }

    $checkStmt = $conn->prepare('SELECT ID_LUONG FROM luong_nhan_vien WHERE ID_TK = ? AND THANG = ? AND NAM = ? LIMIT 1');
    $insertStmt = $conn->prepare('INSERT INTO luong_nhan_vien (ID_TK, THANG, NAM, LUONG_CO_BAN, PHAN_TRAM_THUONG, TONG_TIEN_THUONG, TONG_LUONG, NGAY_TINH) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())');
    $updateStmt = $conn->prepare('UPDATE luong_nhan_vien SET LUONG_CO_BAN = ?, PHAN_TRAM_THUONG = ?, TONG_TIEN_THUONG = ?, TONG_LUONG = ?, NGAY_TINH = CURDATE() WHERE ID_TK = ? AND THANG = ? AND NAM = ?');

    if (!$checkStmt || !$insertStmt || !$updateStmt) {
        throw new RuntimeException('Không thể chuẩn bị truy vấn ghi dữ liệu lương: ' . $conn->error);
    }

    $processed = 0;
    $totalPayroll = 0;
    $totalBonus = 0;

    foreach ($employees as $employee) {
        $employeeId = $employee['ID_TK'];
        $baseSalary = (int)($employee['base_salary'] ?? 200000);

        $bonusStmt->bind_param('dsii', $bonusRate, $employeeId, $month, $year);
        $bonusStmt->execute();
        $bonusResult = $bonusStmt->get_result();
        $bonusRow = $bonusResult ? $bonusResult->fetch_assoc() : null;
        $bonusAmount = (int)($bonusRow['tong_thuong'] ?? 0);

        $checkStmt->bind_param('sii', $employeeId, $month, $year);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();

        $total = $baseSalary + $bonusAmount;

        if ($existing) {
            $updateStmt->bind_param('idiisii', $baseSalary, $bonusRate, $bonusAmount, $total, $employeeId, $month, $year);
            $updateStmt->execute();
        } else {
            $insertStmt->bind_param('siiidii', $employeeId, $month, $year, $baseSalary, $bonusRate, $bonusAmount, $total);
            $insertStmt->execute();
        }

        $processed++;
        $totalPayroll += $total;
        $totalBonus += $bonusAmount;
    }

    $bonusStmt->close();
    $checkStmt->close();
    $insertStmt->close();
    $updateStmt->close();

    syncPayrollExpenses($conn, $month, $year, $branchId);

    return [
        'processed' => $processed,
        'total_payroll' => $totalPayroll,
        'total_bonus' => $totalBonus,
    ];
}

$searchTerm = trim($_GET['search'] ?? '');
$monthFilter = isset($_GET['month']) && $_GET['month'] !== '' ? max(1, min(12, (int)$_GET['month'])) : $currentMonth;
$yearInput = $_GET['year'] ?? '';
$yearFilter = ($yearInput !== '') ? max(2000, (int)$yearInput) : $currentYear;
$branchFilter = isset($_GET['branch']) && $_GET['branch'] !== '' ? max(0, (int)$_GET['branch']) : null;
$sort = $_GET['sort'] ?? 'recent';
$limit = 10;
$pageNumber = max(1, (int)($_GET['p'] ?? 1));
$offset = ($pageNumber - 1) * $limit;

$alert = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salary_action'])) {
    $logFile = __DIR__ . '/../../logs/salary_debug.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . 'Salary action POST: ' . $_POST['salary_action'] . ' | Data: ' . json_encode($_POST) . "\n", FILE_APPEND);
    if ($_POST['salary_action'] === 'recalculate') {
        // Chỉ admin được phép tính lại lương
        if (!$isAdmin) {
            $alert = [
                'type' => 'error',
                'message' => 'Bạn không có quyền thực hiện thao tác này.',
            ];
        } else {
            $targetMonth = isset($_POST['target_month']) ? max(1, min(12, (int)$_POST['target_month'])) : $currentMonth;
            $targetYear = isset($_POST['target_year']) ? max(2000, (int)$_POST['target_year']) : $currentYear;
            $targetBranch = isset($_POST['target_branch']) && $_POST['target_branch'] !== '' ? max(0, (int)$_POST['target_branch']) : null;

            try {
                $stats = recalculateSalaries($conn, $targetBranch ?: null, $targetMonth, $targetYear);

                if ($stats['processed'] === 0) {
                    $alert = [
                        'type' => 'warning',
                        'message' => 'Không tìm thấy nhân viên phù hợp để tính lương cho kỳ đã chọn.',
                    ];
                } else {
                    $alert = [
                        'type' => 'success',
                        'message' => sprintf(
                            'Đã tính lại lương cho %d nhân viên (%s, thưởng %s).',
                            $stats['processed'],
                            formatCurrency($stats['total_payroll']),
                            formatCurrency($stats['total_bonus'])
                        ),
                    ];
                }
            } catch (Throwable $exception) {
                $alert = [
                    'type' => 'error',
                    'message' => 'Lỗi khi tính lại lương: ' . $exception->getMessage(),
                ];
            }
        }
    } elseif ($_POST['salary_action'] === 'update_base_salary') {
        $employeeId = trim($_POST['employee_id'] ?? '');
        $salaryMonth = isset($_POST['salary_month']) ? max(1, min(12, (int)$_POST['salary_month'])) : null;
        $salaryYear = isset($_POST['salary_year']) ? max(2000, (int)$_POST['salary_year']) : null;
        $newBaseSalary = isset($_POST['new_base_salary']) ? max(0, (int)$_POST['new_base_salary']) : 0;

        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Update base salary: emp=$employeeId, month=$salaryMonth, year=$salaryYear, newBase=$newBaseSalary\n", FILE_APPEND);

        if (!$employeeId || $salaryMonth === null || $salaryYear === null) {
            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Validation failed\n", FILE_APPEND);
            $alert = [
                'type' => 'error',
                'message' => 'Dữ liệu không hợp lệ.',
            ];
        } else {
            try {
                // Nếu là quản lý chi nhánh, kiểm tra xem nhân viên có phải của chi nhánh họ không
                if ($isManager && $userBranchId !== null) {
                    $employeeBranchSql = 'SELECT ID_CN FROM nhan_vien WHERE ID_TK = ? LIMIT 1';
                    $employeeBranchStmt = $conn->prepare($employeeBranchSql);
                    if ($employeeBranchStmt) {
                        $employeeBranchStmt->bind_param('s', $employeeId);
                        $employeeBranchStmt->execute();
                        $employeeBranchResult = $employeeBranchStmt->get_result()->fetch_assoc();
                        $employeeBranchStmt->close();

                        $employeeBranch = $employeeBranchResult ? (int)$employeeBranchResult['ID_CN'] : null;
                        if ($employeeBranch !== $userBranchId) {
                            $alert = [
                                'type' => 'error',
                                'message' => 'Bạn chỉ có thể cập nhật lương cho nhân viên trong chi nhánh của bạn.',
                            ];
                            throw new RuntimeException('Permission denied');
                        }
                    }
                }

                // Fetch existing row to preserve bonus and rate if present
                $existingRowSql = 'SELECT PHAN_TRAM_THUONG, TONG_TIEN_THUONG FROM luong_nhan_vien WHERE ID_TK = ? AND THANG = ? AND NAM = ? LIMIT 1';
                $existingStmt = $conn->prepare($existingRowSql);
                if (!$existingStmt) {
                    throw new RuntimeException('Không thể chuẩn bị câu lệnh: ' . $conn->error);
                }
                
                $existingStmt->bind_param('sii', $employeeId, $salaryMonth, $salaryYear);
                $existingStmt->execute();
                $existingRow = $existingStmt->get_result()->fetch_assoc();
                $existingStmt->close();

                error_log("Existing row: " . json_encode($existingRow));

                $currentBonus = (int)($existingRow['TONG_TIEN_THUONG'] ?? 0);
                $currentBonusRate = isset($existingRow['PHAN_TRAM_THUONG']) ? (float)$existingRow['PHAN_TRAM_THUONG'] : 0.15;

                // Compute new total
                $newTotal = $newBaseSalary + $currentBonus;

                // Try update first
                $updateBaseSql = 'UPDATE luong_nhan_vien 
                                  SET LUONG_CO_BAN = ?, TONG_LUONG = ?, NGAY_TINH = CURDATE()
                                  WHERE ID_TK = ? AND THANG = ? AND NAM = ?';
                
                $updateStmt = $conn->prepare($updateBaseSql);
                if (!$updateStmt) {
                    throw new RuntimeException('Không thể chuẩn bị câu lệnh cập nhật: ' . $conn->error);
                }

                $updateStmt->bind_param('iisii', $newBaseSalary, $newTotal, $employeeId, $salaryMonth, $salaryYear);
                if (!$updateStmt->execute()) {
                    throw new RuntimeException('Lỗi thực thi UPDATE: ' . $updateStmt->error);
                }

                $rowsAffected = $updateStmt->affected_rows;
                error_log("Update affected rows: $rowsAffected");
                $updateStmt->close();

                if ($rowsAffected <= 0) {
                    // If row does not exist, insert a new one (upsert behavior)
                    error_log("No rows affected, inserting new row");
                    $insertSql = 'INSERT INTO luong_nhan_vien (ID_TK, THANG, NAM, LUONG_CO_BAN, PHAN_TRAM_THUONG, TONG_TIEN_THUONG, TONG_LUONG, NGAY_TINH)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())';
                    $insertStmt = $conn->prepare($insertSql);
                    if (!$insertStmt) {
                        throw new RuntimeException('Không thể chuẩn bị câu lệnh thêm mới: ' . $conn->error);
                    }
                    // Correct bind_param: s(ID_TK), i(THANG), i(NAM), i(LUONG_CO_BAN), d(PHAN_TRAM_THUONG), i(TONG_TIEN_THUONG), i(TONG_LUONG)
                    $insertStmt->bind_param('siiidii', $employeeId, $salaryMonth, $salaryYear, $newBaseSalary, $currentBonusRate, $currentBonus, $newTotal);
                    if (!$insertStmt->execute()) {
                        throw new RuntimeException('Lỗi thêm mới: ' . $insertStmt->error);
                    }
                    error_log("Insert successful for $employeeId");
                    $insertStmt->close();
                }

                // Success notice
                $alert = [
                    'type' => 'success',
                    'message' => 'Đã cập nhật lương cơ bản thành công.',
                ];
                error_log("Update/Insert success");
            } catch (Throwable $exception) {
                error_log("Exception in update_base_salary: " . $exception->getMessage());
                $alert = [
                    'type' => 'error',
                    'message' => 'Lỗi cập nhật: ' . $exception->getMessage(),
                ];
            }
        }
    }
}

$sortMap = [
    'recent' => 'lnv.NAM DESC, lnv.THANG DESC, lnv.NGAY_TINH DESC',
    'salary_desc' => 'lnv.TONG_LUONG DESC',
    'salary_asc' => 'lnv.TONG_LUONG ASC',
    'name' => 'tk.HO_TEN ASC',
];

if (!array_key_exists($sort, $sortMap)) {
    $sort = 'recent';
}

$branchList = [];
$branchQuery = $conn->query('SELECT ID_CN, TEN_CN FROM chi_nhanh ORDER BY TEN_CN');
if ($branchQuery) {
    while ($branchRow = $branchQuery->fetch_assoc()) {
        $branchList[] = $branchRow;
    }
}

// Nếu là quản lý chi nhánh, không cho phép thay đổi bộ lọc chi nhánh
$effectiveBranchFilter = $branchFilter;
if ($isManager && $userBranchId !== null) {
    $effectiveBranchFilter = $userBranchId;
}

[$whereClause, $whereTypes, $whereParams] = buildSalaryFilters($searchTerm, $monthFilter, $yearFilter, $effectiveBranchFilter, $userBranchId, $isManager);

$countSql = 'SELECT COUNT(*) AS total
             FROM luong_nhan_vien lnv
             JOIN tai_khoan tk ON lnv.ID_TK = tk.ID_TK
             LEFT JOIN nhan_vien nv ON nv.ID_TK = lnv.ID_TK
             ' . $whereClause;

$countStmt = $conn->prepare($countSql);
if (!$countStmt) {
    throw new RuntimeException('Không thể tải tổng bản ghi: ' . $conn->error);
}
bindQueryParams($countStmt, $whereTypes, $whereParams);
$countStmt->execute();
$countResult = $countStmt->get_result()->fetch_assoc();
$totalRows = (int)($countResult['total'] ?? 0);
$countStmt->close();

$summarySql = 'SELECT 
                    COUNT(DISTINCT lnv.ID_TK) AS employee_count,
                    COALESCE(SUM(lnv.TONG_LUONG), 0) AS total_payroll,
                    COALESCE(SUM(lnv.TONG_TIEN_THUONG), 0) AS total_bonus,
                    MAX(lnv.NGAY_TINH) AS last_calculated
                FROM luong_nhan_vien lnv
                JOIN tai_khoan tk ON lnv.ID_TK = tk.ID_TK
                LEFT JOIN nhan_vien nv ON nv.ID_TK = lnv.ID_TK
                ' . $whereClause;

$summaryStmt = $conn->prepare($summarySql);
if (!$summaryStmt) {
    throw new RuntimeException('Không thể tải số liệu tổng quan: ' . $conn->error);
}
bindQueryParams($summaryStmt, $whereTypes, $whereParams);
$summaryStmt->execute();
$summary = $summaryStmt->get_result()->fetch_assoc() ?: [];
$summaryStmt->close();

$listSql = 'SELECT lnv.*, tk.HO_TEN, nv.ID_CN, cn.TEN_CN
            FROM luong_nhan_vien lnv
            JOIN tai_khoan tk ON lnv.ID_TK = tk.ID_TK
            LEFT JOIN nhan_vien nv ON nv.ID_TK = lnv.ID_TK
            LEFT JOIN chi_nhanh cn ON cn.ID_CN = nv.ID_CN
            ' . $whereClause . '
            ORDER BY ' . $sortMap[$sort] . '
            LIMIT ? OFFSET ?';

$listStmt = $conn->prepare($listSql);
if (!$listStmt) {
    throw new RuntimeException('Không thể tải dữ liệu bảng lương: ' . $conn->error);
}

$listTypes = $whereTypes . 'ii';
$listParams = array_merge($whereParams, [$limit, $offset]);
bindQueryParams($listStmt, $listTypes, $listParams);
$listStmt->execute();
$listResult = $listStmt->get_result();
$salaryRows = $listResult ? $listResult->fetch_all(MYSQLI_ASSOC) : [];
$listStmt->close();

$totalPages = max(1, (int)ceil($totalRows / $limit));
$employeeCount = (int)($summary['employee_count'] ?? 0);
$totalPayroll = (int)($summary['total_payroll'] ?? 0);
$totalBonus = (int)($summary['total_bonus'] ?? 0);
$avgSalary = $employeeCount > 0 ? (int)round($totalPayroll / $employeeCount) : 0;
$lastCalculated = $summary['last_calculated'] ?? null;

$filterQueryBase = [
    'page' => 'salaries',
    'search' => $searchTerm !== '' ? $searchTerm : null,
    'month' => $monthFilter,
    'year' => $yearFilter,
    'branch' => $branchFilter,
    'sort' => $sort !== 'recent' ? $sort : null,
];

$previousMonthDate = new DateTime('first day of last month');
$prevMonth = (int)$previousMonthDate->format('m');
$prevYear = (int)$previousMonthDate->format('Y');
?>

<div class="space-y-6">
    <h1 class="text-3xl font-extrabold text-indigo-700 text-center flex items-center justify-center gap-3">
        Quản lý bảng lương
    </h1>

    <?php if ($alert): ?>
        <?php
        $alertClasses = [
            'success' => 'bg-green-50 text-green-700 border-green-200',
            'error' => 'bg-red-50 text-red-700 border-red-200',
            'warning' => 'bg-yellow-50 text-yellow-700 border-yellow-200',
            'info' => 'bg-blue-50 text-blue-700 border-blue-200',
        ];
        $className = $alertClasses[$alert['type']] ?? 'bg-blue-50 text-blue-700 border-blue-200';
        ?>
        <div class="rounded-xl border px-4 py-3 text-sm <?= $className ?>">
            <?= htmlspecialchars($alert['message']) ?>
        </div>
    <?php endif; ?>

    <form method="GET" class="bg-white rounded-2xl shadow p-4 lg:p-6 space-y-4">
        <input type="hidden" name="page" value="salaries">
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-6">
            <div class="space-y-1 md:col-span-2">
                <label class="text-sm font-semibold text-gray-600">Tìm kiếm</label>
                <input type="text"
                       name="search"
                       value="<?= htmlspecialchars($searchTerm) ?>"
                       placeholder="Nhập tên/ID nhân viên"
                       class="w-full rounded-lg border border-gray-200 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500" />
            </div>
            <?php if (!$isManager): ?>
                <div class="space-y-1">
                    <label class="text-sm font-semibold text-gray-600">Chi nhánh</label>
                    <select name="branch" class="w-full rounded-lg border border-gray-200 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tất cả</option>
                        <?php foreach ($branchList as $branch): ?>
                            <option value="<?= $branch['ID_CN'] ?>" <?= ($branchFilter === (int)$branch['ID_CN']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($branch['TEN_CN']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <div class="space-y-1">
                    <label class="text-sm font-semibold text-gray-600">Chi nhánh</label>
                    <input type="text" class="w-full rounded-lg border border-gray-200 px-4 py-2 bg-gray-50" value="<?php 
                        $currentBranch = array_values(array_filter($branchList, function($b) use ($userBranchId) { return (int)$b['ID_CN'] === (int)$userBranchId; }));
                        echo htmlspecialchars($currentBranch ? ($currentBranch[0]['TEN_CN'] ?? 'Chi nhánh của bạn') : 'Chi nhánh của bạn');
                    ?>" readonly />
                    <input type="hidden" name="branch" value="<?= (int)$userBranchId ?>" />
                </div>
            <?php endif; ?>
            <div class="space-y-1">
                <label class="text-sm font-semibold text-gray-600">Tháng</label>
                <select name="month" class="w-full rounded-lg border border-gray-200 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Tất cả</option>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= ($monthFilter === $m) ? 'selected' : '' ?>>Tháng <?= $m ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="space-y-1">
                <label class="text-sm font-semibold text-gray-600">Năm</label>
                <select name="year" class="w-full rounded-lg border border-gray-200 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Tất cả</option>
                    <?php for ($y = 2022; $y <= date('Y'); $y++): ?>
                        <option value="<?= $y ?>" <?= ($yearFilter === $y) ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="space-y-1">
                <label class="text-sm font-semibold text-gray-600">Sắp xếp</label>
                <select name="sort" class="w-full rounded-lg border border-gray-200 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Mới nhất</option>
                    <option value="salary_desc" <?= $sort === 'salary_desc' ? 'selected' : '' ?>>Cao → Thấp</option>
                    <option value="salary_asc" <?= $sort === 'salary_asc' ? 'selected' : '' ?>>Thấp → Cao</option>
                    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Theo tên A-Z</option>
                </select>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-6 py-2 font-semibold text-white transition hover:bg-indigo-700">
                Áp dụng
            </button>
            <a href="?page=salaries"
               class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 transition hover:border-indigo-300 hover:text-indigo-600">
                Đặt lại
            </a>
        </div>
    </form>

    <div class="flex flex-wrap items-center gap-3 text-sm">
        <span class="text-gray-500 font-semibold">Bộ lọc nhanh:</span>
        <a href="?<?= buildQueryString(array_merge($filterQueryBase, ['month' => $currentMonth, 'year' => $currentYear, 'p' => 1])) ?>"
           class="rounded-full border border-gray-200 px-4 py-1.5 text-gray-700 transition hover:border-indigo-400 hover:text-indigo-600">
            Tháng này
        </a>
        <a href="?<?= buildQueryString(array_merge($filterQueryBase, ['month' => $prevMonth, 'year' => $prevYear, 'p' => 1])) ?>"
           class="rounded-full border border-gray-200 px-4 py-1.5 text-gray-700 transition hover:border-indigo-400 hover:text-indigo-600">
            Tháng trước
        </a>
        <a href="?<?= buildQueryString(['page' => 'salaries']) ?>"
           class="rounded-full border border-gray-200 px-4 py-1.5 text-gray-700 transition hover:border-indigo-400 hover:text-indigo-600">
            Xem toàn bộ
        </a>
        <?php if ($isAdmin): ?>
            <form method="POST" class="inline-flex items-center gap-2" onsubmit="return confirm('Tính lại lương và đồng bộ chi phí lương vào tài chính cho kỳ đang lọc?');">
                <input type="hidden" name="salary_action" value="recalculate">
                <input type="hidden" name="target_month" value="<?= $monthFilter ?>">
                <input type="hidden" name="target_year" value="<?= $yearFilter ?>">
                <input type="hidden" name="target_branch" value="<?= $effectiveBranchFilter ?>">
                <button type="submit" class="rounded-full bg-indigo-600 px-4 py-1.5 text-sm font-semibold text-white shadow hover:bg-indigo-700">
                    Tính lại & đồng bộ
                </button>
            </form>
        <?php endif; ?>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-sm font-semibold text-gray-500">Tổng quỹ lương</p>
            <p class="mt-2 text-2xl font-bold text-gray-900"><?= formatCurrency($totalPayroll) ?></p>
            <p class="text-xs text-gray-400">Nguồn dữ liệu theo bộ lọc hiện tại.</p>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-sm font-semibold text-gray-500">Tổng thưởng</p>
            <p class="mt-2 text-2xl font-bold text-emerald-600"><?= formatCurrency($totalBonus) ?></p>
            <p class="text-xs text-gray-400">Bao gồm 15% giá dịch vụ đã thanh toán.</p>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-sm font-semibold text-gray-500">Nhân sự nhận lương</p>
            <p class="mt-2 text-2xl font-bold text-gray-900"><?= $employeeCount ?></p>
            <p class="text-xs text-gray-400">Nhân viên xuất hiện trong bảng dữ liệu.</p>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-sm font-semibold text-gray-500">Thu nhập trung bình</p>
            <p class="mt-2 text-2xl font-bold text-indigo-600"><?= formatCurrency($avgSalary) ?></p>
            <p class="text-xs text-gray-400">Tính dựa trên tổng lương / nhân viên.</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
            <div>
                <h2 class="text-lg font-semibold text-gray-800">Danh sách bảng lương</h2>
                <p class="text-sm text-gray-500">Cập nhật gần nhất: <?= $lastCalculated ? date('d/m/Y', strtotime($lastCalculated)) : 'Chưa có dữ liệu' ?></p>
            </div>
            <?php 
                // Managers export is constrained to their branch; Admin can export any (current filters)
                $exportBranch = $isManager && $userBranchId !== null ? $userBranchId : $effectiveBranchFilter;
                $exportQuery = buildQueryString([
                    'search' => $searchTerm !== '' ? $searchTerm : null,
                    'month' => $monthFilter,
                    'year' => $yearFilter,
                    'branch' => $exportBranch,
                ]);
            ?>
            <a href="components/export_salary_excel.php?<?= $exportQuery ?>"
               class="inline-flex items-center gap-2 rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-600">
                Xuất Excel<?= $isManager ? ' (chi nhánh của bạn)' : '' ?>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-indigo-50 text-xs font-semibold uppercase tracking-wider text-gray-600">
                <tr>
                    <th class="px-4 py-3 text-left">Nhân viên</th>
                    <th class="px-4 py-3 text-left">Chi nhánh</th>
                    <th class="px-4 py-3 text-center">Tháng/Năm</th>
                    <th class="px-4 py-3 text-right">Lương cơ bản</th>
                    <th class="px-4 py-3 text-right">Thưởng</th>
                    <th class="px-4 py-3 text-right">Tổng nhận</th>
                    <th class="px-4 py-3 text-center">Cập nhật</th>
                    <th class="px-4 py-3 text-center">Thao tác</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                <?php if (empty($salaryRows)): ?>
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-gray-500">
                            Không tìm thấy bản ghi nào khớp với bộ lọc.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($salaryRows as $row): ?>
                        <?php
                        $detailPayload = htmlspecialchars(json_encode([
                            'name' => $row['HO_TEN'],
                            'branch' => $row['TEN_CN'] ?: 'Chưa gán',
                            'month' => $row['THANG'],
                            'year' => $row['NAM'],
                            'employee_id' => $row['ID_TK'],
                            'base' => (int)$row['LUONG_CO_BAN'],
                            'bonus' => formatCurrency((int)$row['TONG_TIEN_THUONG']),
                            'percent' => round((float)$row['PHAN_TRAM_THUONG'] * 100, 2) . '%',
                            'total' => formatCurrency((int)$row['TONG_LUONG']),
                            'updated' => $row['NGAY_TINH'] ? date('d/m/Y', strtotime($row['NGAY_TINH'])) : 'Chưa cập nhật',
                        ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr class="hover:bg-gray-50/80">
                            <td class="px-4 py-3 font-semibold text-gray-800">
                                <?= htmlspecialchars($row['HO_TEN']) ?>
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                <?= htmlspecialchars($row['TEN_CN'] ?? 'Chưa gán') ?>
                            </td>
                            <td class="px-4 py-3 text-center font-semibold text-gray-700">
                                <?= $row['THANG'] ?>/<?= $row['NAM'] ?>
                            </td>
                            <td class="px-4 py-3 text-right text-gray-700">
                                <?= formatCurrency((int)$row['LUONG_CO_BAN']) ?>
                            </td>
                            <td class="px-4 py-3 text-right text-emerald-600 font-semibold">
                                <?= formatCurrency((int)$row['TONG_TIEN_THUONG']) ?>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span class="rounded-full bg-indigo-50 px-3 py-1 text-sm font-bold text-indigo-700">
                                    <?= formatCurrency((int)$row['TONG_LUONG']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center text-gray-500">
                                <?= $row['NGAY_TINH'] ? date('d/m/Y', strtotime($row['NGAY_TINH'])) : '—' ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button type="button"
                                        class="rounded-lg border border-gray-200 px-3 py-1 text-xs font-semibold text-gray-600 transition hover:border-indigo-400 hover:text-indigo-600"
                                        data-salary-detail="<?= $detailPayload ?>">
                                    Chi tiết
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-gray-100 px-4 py-3 text-sm text-gray-600">
            <span>Hiển thị <?= count($salaryRows) ?> / <?= $totalRows ?> bản ghi</span>
            <div class="flex flex-wrap items-center gap-2">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php
                    $isActive = $i === $pageNumber;
                    $pageLinkClass = $isActive
                        ? 'rounded-lg border px-3 py-1 border-indigo-500 bg-indigo-50 text-indigo-600 font-semibold'
                        : 'rounded-lg border px-3 py-1 border-gray-200 text-gray-600 hover:border-indigo-300 hover:text-indigo-600';
                    ?>
                    <a href="?<?= buildQueryString(array_merge($filterQueryBase, ['p' => $i])) ?>"
                       class="<?= $pageLinkClass ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</div>

<div id="salaryDetailModal" class="fixed inset-0 z-30 hidden items-center justify-center bg-gray-900/60 px-4">
    <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
        <div class="flex items-start justify-between">
            <div>
                <h3 class="text-xl font-bold text-gray-800" data-field="name"></h3>
                <p class="text-sm text-gray-500" data-field="branch"></p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600" data-close-modal>&times;</button>
        </div>
        <div class="mt-4 grid gap-3 text-sm text-gray-700">
            <div class="flex justify-between"><span>Tháng/Năm</span><strong data-field="period"></strong></div>
            <div class="flex justify-between"><span>Thưởng</span><strong data-field="bonus"></strong></div>
            <div class="flex justify-between"><span>Tỷ lệ thưởng</span><strong data-field="percent"></strong></div>
            <div class="flex justify-between text-indigo-700"><span>Tổng nhận</span><strong data-field="total"></strong></div>
            <div class="flex justify-between text-gray-500"><span>Cập nhật lần cuối</span><strong data-field="updated"></strong></div>
        </div>
        <form id="updateBaseSalaryForm" method="POST" class="mt-6 space-y-3 border-t border-gray-200 pt-4">
            <input type="hidden" name="salary_action" value="update_base_salary">
            <input type="hidden" name="employee_id" data-field="employee_id" value="">
            <input type="hidden" name="salary_month" data-field="salary_month" value="">
            <input type="hidden" name="salary_year" data-field="salary_year" value="">
            <div class="space-y-2">
                <label class="block text-sm font-semibold text-gray-700">Cập nhật lương cơ bản (VND)</label>
                <input type="number" name="new_base_salary" id="newBaseSalaryInput" min="0" step="100000" class="w-full rounded-lg border border-gray-200 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Nhập lương cơ bản mới" required>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="flex-1 rounded-lg bg-emerald-500 py-2 text-sm font-semibold text-white transition hover:bg-emerald-600">Cập nhật</button>
                <button type="button" class="flex-1 rounded-lg border border-gray-200 py-2 text-sm font-semibold text-gray-600" data-close-modal>Hủy</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const modal = document.getElementById('salaryDetailModal');
        if (!modal) return;

        const nameField = modal.querySelector('[data-field="name"]');
        const branchField = modal.querySelector('[data-field="branch"]');
        const periodField = modal.querySelector('[data-field="period"]');
        const bonusField = modal.querySelector('[data-field="bonus"]');
        const percentField = modal.querySelector('[data-field="percent"]');
        const totalField = modal.querySelector('[data-field="total"]');
        const updatedField = modal.querySelector('[data-field="updated"]');
        const form = modal.querySelector('#updateBaseSalaryForm');
        const employeeIdField = form.querySelector('[data-field="employee_id"]');
        const salaryMonthField = form.querySelector('[data-field="salary_month"]');
        const salaryYearField = form.querySelector('[data-field="salary_year"]');
        const newBaseSalaryInput = modal.querySelector('#newBaseSalaryInput');

        const closeModal = () => {
            modal.classList.add('hidden');
        };

        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal();
            }
        });

        modal.querySelectorAll('[data-close-modal]').forEach((button) => {
            button.addEventListener('click', closeModal);
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            
            // Validate form data
            const employeeId = form.querySelector('[name="employee_id"]').value;
            const salaryMonth = form.querySelector('[name="salary_month"]').value;
            const salaryYear = form.querySelector('[name="salary_year"]').value;
            const newBaseSalary = form.querySelector('[name="new_base_salary"]').value;
            
            if (!employeeId || !salaryMonth || !salaryYear || !newBaseSalary) {
                alert('Vui lòng điền đầy đủ thông tin.');
                console.log('Form validation failed:', { employeeId, salaryMonth, salaryYear, newBaseSalary });
                return;
            }
            
            const formData = new FormData(form);
            formData.append('page', 'salaries');
            
            console.log('Submitting form with:', {
                salary_action: formData.get('salary_action'),
                employee_id: formData.get('employee_id'),
                salary_month: formData.get('salary_month'),
                salary_year: formData.get('salary_year'),
                new_base_salary: formData.get('new_base_salary'),
            });
            
            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ Đang cập nhật...';
            
            try {
                const currentUrl = window.location.pathname + '?page=salaries';
                console.log('Current pathname:', window.location.pathname);
                console.log('Fetch URL:', currentUrl);
                const response = await fetch(currentUrl, {
                    method: 'POST',
                    body: formData
                });
                const responseText = await response.text();
                
                // Check for success in response
                const hasSuccess = responseText.includes('success') || 
                                   responseText.includes('Đã cập nhật') ||
                                   responseText.includes('thành công');
                
                if (hasSuccess || response.ok) {
                    // Close modal and reload
                    closeModal();
                    document.body.insertAdjacentHTML('beforeend', '<div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50"><div class="bg-white rounded-lg p-6 shadow-xl"><p class="text-gray-700">Đang cập nhật dữ liệu...</p></div></div>');
                    setTimeout(() => window.location.href = window.location.href, 800);
                } else {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                    alert('Cập nhật thất bại. Vui lòng kiểm tra lại.');
                    console.error('Response preview:', responseText.substring(0, 1000));
                }
            } catch (error) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
                alert('Lỗi kết nối: ' + error.message);
                console.error('Error:', error);
            }
        });

        document.querySelectorAll('[data-salary-detail]').forEach((button) => {
            button.addEventListener('click', () => {
                const detail = button.getAttribute('data-salary-detail');
                if (!detail) return;

                const payload = JSON.parse(detail);
                nameField.textContent = payload.name;
                branchField.textContent = payload.branch;
                periodField.textContent = `${payload.month}/${payload.year}`;
                bonusField.textContent = payload.bonus;
                percentField.textContent = payload.percent;
                totalField.textContent = payload.total;
                updatedField.textContent = payload.updated;

                employeeIdField.value = payload.employee_id;
                salaryMonthField.value = payload.month;
                salaryYearField.value = payload.year;
                newBaseSalaryInput.value = payload.base;

                modal.classList.remove('hidden');
            });
        });
    });
</script>
