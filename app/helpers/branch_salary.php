<?php
// Helper utilities supporting branch manager salary workflows.
if (!function_exists('ensureBranchSalaryMetaTable')) {
    function ensureBranchSalaryMetaTable(mysqli $conn): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS quanly_luong_chinhanh (
  ID INT AUTO_INCREMENT PRIMARY KEY,
  ID_TK_NV VARCHAR(20) NOT NULL,
  ID_TK_MANAGER VARCHAR(20) NOT NULL,
  ID_CN INT NOT NULL,
  THANG INT NOT NULL,
  NAM INT NOT NULL,
  BASE_TONG_LUONG INT NOT NULL DEFAULT 0,
  PHU_CAP INT NOT NULL DEFAULT 0,
  KHOAN_TRU INT NOT NULL DEFAULT 0,
  THUC_LINH INT NOT NULL DEFAULT 0,
  TRANG_THAI ENUM('cho_duyet','da_duyet','da_chi_tra') NOT NULL DEFAULT 'cho_duyet',
  GHI_CHU TEXT,
  NGAY_CAP_NHAT DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  NGAY_CHI_TRA DATETIME DEFAULT NULL,
  UNIQUE KEY uq_salary_employee_period (ID_TK_NV, THANG, NAM),
  KEY idx_branch_period (ID_CN, THANG, NAM),
  KEY idx_status (TRANG_THAI)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;
SQL;

        if (!$conn->query($sql)) {
            throw new RuntimeException('Không thể khởi tạo bảng phụ trợ lương: ' . $conn->error);
        }
    }
}

if (!function_exists('getBranchIdForUser')) {
    function getBranchIdForUser(mysqli $conn, string $userId): ?int
    {
        $stmt = $conn->prepare('SELECT ID_CN FROM nhan_vien WHERE ID_TK = ? LIMIT 1');
        if (!$stmt) {
            throw new RuntimeException('Không thể chuẩn bị truy vấn chi nhánh: ' . $conn->error);
        }

        $stmt->bind_param('s', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return $row ? (int)$row['ID_CN'] : null;
    }
}

if (!function_exists('recalculateBranchSalaries')) {
    function recalculateBranchSalaries(
        mysqli $conn,
        int $branchId,
        int $month,
        int $year,
        string $managerId,
        int $baseSalary = 5000000,
        float $bonusRate = 0.15
    ): array {
        $month = max(1, min(12, $month));
        $year = max(2000, $year);

        $staffSql = 'SELECT tk.ID_TK FROM nhan_vien nv JOIN tai_khoan tk ON nv.ID_TK = tk.ID_TK WHERE nv.ID_CN = ? AND tk.ID_QUYEN = 2';
        $staffStmt = $conn->prepare($staffSql);
        if (!$staffStmt) {
            throw new RuntimeException('Không thể tải danh sách nhân viên: ' . $conn->error);
        }

        $staffStmt->bind_param('i', $branchId);
        $staffStmt->execute();
        $staffResult = $staffStmt->get_result();
        $staff = [];
        while ($row = $staffResult->fetch_assoc()) {
            $staff[] = $row['ID_TK'];
        }
        $staffStmt->close();

        if (empty($staff)) {
            return [
                'processed' => 0,
                'total_payroll' => 0,
                'total_bonus' => 0,
                'message' => 'Chi nhánh chưa có nhân viên nào để tính lương.',
            ];
        }

        $bonusSql = <<<SQL
SELECT COALESCE(SUM(dgdv.DON_GIA * ?), 0) AS tong_thuong
FROM phan_cong_nhan_vien pc
JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN
JOIN hoa_don hd ON hd.ID_LICHHEN = lh.ID_LICHHEN
JOIN don_gia_dich_vu dgdv ON dgdv.ID_DV = lh.ID_DV
WHERE pc.ID_TK = ?
  AND lh.TRANGTHAI = 'Đã hoàn thành'
  AND MONTH(hd.NGAY_GIO) = ?
  AND YEAR(hd.NGAY_GIO) = ?
  AND (lh.ID_CHINHANH = ? OR lh.ID_CHINHANH IS NULL)
SQL;
        $bonusStmt = $conn->prepare($bonusSql);
        if (!$bonusStmt) {
            throw new RuntimeException('Không thể chuẩn bị truy vấn thưởng: ' . $conn->error);
        }

        $checkStmt = $conn->prepare('SELECT ID_LUONG FROM luong_nhan_vien WHERE ID_TK = ? AND THANG = ? AND NAM = ? LIMIT 1');
        if (!$checkStmt) {
            throw new RuntimeException('Không thể kiểm tra bản ghi lương: ' . $conn->error);
        }

        $insertStmt = $conn->prepare('INSERT INTO luong_nhan_vien (ID_TK, THANG, NAM, LUONG_CO_BAN, PHAN_TRAM_THUONG, TONG_TIEN_THUONG, TONG_LUONG, NGAY_TINH) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())');
        $updateStmt = $conn->prepare('UPDATE luong_nhan_vien SET LUONG_CO_BAN = ?, PHAN_TRAM_THUONG = ?, TONG_TIEN_THUONG = ?, TONG_LUONG = ?, NGAY_TINH = CURDATE() WHERE ID_TK = ? AND THANG = ? AND NAM = ?');
        if (!$insertStmt || !$updateStmt) {
            throw new RuntimeException('Không thể chuẩn bị truy vấn ghi lương: ' . $conn->error);
        }

        $metaStmt = $conn->prepare(
            'INSERT INTO quanly_luong_chinhanh (ID_TK_NV, ID_TK_MANAGER, ID_CN, THANG, NAM, BASE_TONG_LUONG, THUC_LINH)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               ID_TK_MANAGER = VALUES(ID_TK_MANAGER),
               ID_CN = VALUES(ID_CN),
               BASE_TONG_LUONG = VALUES(BASE_TONG_LUONG),
               THUC_LINH = VALUES(BASE_TONG_LUONG) + PHU_CAP - KHOAN_TRU,
               NGAY_CAP_NHAT = CURRENT_TIMESTAMP'
        );
        if (!$metaStmt) {
            throw new RuntimeException('Không thể chuẩn bị truy vấn đồng bộ meta lương: ' . $conn->error);
        }

        $syncStmt = $conn->prepare(
            'UPDATE luong_nhan_vien l
             JOIN quanly_luong_chinhanh q ON q.ID_TK_NV = l.ID_TK AND q.THANG = l.THANG AND q.NAM = l.NAM
             SET l.TONG_LUONG = q.THUC_LINH
             WHERE l.ID_TK = ? AND l.THANG = ? AND l.NAM = ?'
        );
        if (!$syncStmt) {
            throw new RuntimeException('Không thể chuẩn bị truy vấn đồng bộ tổng lương: ' . $conn->error);
        }

        $totalPayroll = 0;
        $totalBonus = 0;
        $processed = 0;

        foreach ($staff as $employeeId) {
            $bonusStmt->bind_param('dsiii', $bonusRate, $employeeId, $month, $year, $branchId);
            $bonusStmt->execute();
            $bonusResult = $bonusStmt->get_result();
            $bonusRow = $bonusResult ? $bonusResult->fetch_assoc() : null;
            $bonusAmount = $bonusRow ? (int)$bonusRow['tong_thuong'] : 0;

            $total = (int)$baseSalary + $bonusAmount;

            $checkStmt->bind_param('sii', $employeeId, $month, $year);
            $checkStmt->execute();
            $exists = $checkStmt->get_result()->fetch_assoc();

            if ($exists) {
                $updateStmt->bind_param('idiisii', $baseSalary, $bonusRate, $bonusAmount, $total, $employeeId, $month, $year);
            } else {
                $insertStmt->bind_param('siiidii', $employeeId, $month, $year, $baseSalary, $bonusRate, $bonusAmount, $total);
            }

            if ($exists) {
                $updateStmt->execute();
            } else {
                $insertStmt->execute();
            }

            $metaStmt->bind_param('ssiiiii', $employeeId, $managerId, $branchId, $month, $year, $total, $total);
            $metaStmt->execute();

            $syncStmt->bind_param('sii', $employeeId, $month, $year);
            $syncStmt->execute();

            $totalPayroll += $total;
            $totalBonus += $bonusAmount;
            $processed++;
        }

        $bonusStmt->close();
        $checkStmt->close();
        $insertStmt->close();
        $updateStmt->close();
        $metaStmt->close();
        $syncStmt->close();

        return [
            'processed' => $processed,
            'total_payroll' => $totalPayroll,
            'total_bonus' => $totalBonus,
            'message' => sprintf('Đã tính lại lương cho %d nhân viên chi nhánh.', $processed),
        ];
    }
}
