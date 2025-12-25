<?php
/**
 * Helper: Initialize expense management tables
 * Run this once during setup to create necessary tables and seed default categories
 */

function initializeExpenseManagement(mysqli $conn): bool
{
    try {
        // 1. Tạo bảng loại chi phí
        $createCategoryTable = "
            CREATE TABLE IF NOT EXISTS loai_chi_phi_branch (
                ID_LOAI_CP INT AUTO_INCREMENT PRIMARY KEY,
                ID_CN INT NOT NULL,
                TEN_LOAI_CP VARCHAR(255) NOT NULL,
                ICON_LOAI_CP VARCHAR(10) DEFAULT '📌',
                MO_TA TEXT,
                THU_TU_HIEN_THI INT DEFAULT 999,
                CREATED_AT DATETIME DEFAULT CURRENT_TIMESTAMP,
                UPDATED_AT DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_branch_category (ID_CN, TEN_LOAI_CP),
                FOREIGN KEY (ID_CN) REFERENCES chi_nhanh(ID_CN) ON DELETE CASCADE ON UPDATE CASCADE,
                INDEX idx_branch (ID_CN),
                INDEX idx_order (THU_TU_HIEN_THI)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci
        ";
        
        if (!$conn->query($createCategoryTable)) {
            throw new Exception("Lỗi tạo bảng loại chi phí: " . $conn->error);
        }

        // 2. Kiểm tra và thêm cột ID_LOAI_CP vào bảng chi_phi_phat_sinh nếu chưa có
        $checkColumnQuery = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_NAME = 'chi_phi_phat_sinh' AND COLUMN_NAME = 'ID_LOAI_CP'";
        
        $result = $conn->query($checkColumnQuery);
        
        if ($result && $result->num_rows === 0) {
            // Cột chưa tồn tại, thêm vào
            $alterTableQuery = "
                ALTER TABLE chi_phi_phat_sinh 
                ADD COLUMN ID_LOAI_CP INT DEFAULT NULL,
                ADD CONSTRAINT fk_expense_category 
                FOREIGN KEY (ID_LOAI_CP) REFERENCES loai_chi_phi_branch(ID_LOAI_CP) 
                ON DELETE SET NULL ON UPDATE CASCADE
            ";
            
            if (!$conn->query($alterTableQuery)) {
                throw new Exception("Lỗi thêm cột ID_LOAI_CP: " . $conn->error);
            }
        }

        // 3. Seed categories mặc định cho tất cả chi nhánh
        $defaultCategories = [
            ['order' => 1, 'name' => 'Chi phí mặt bằng', 'icon' => '🏢', 'desc' => 'Tiền thuê, phí dịch vụ tòa nhà.'],
            ['order' => 2, 'name' => 'Chi phí điện nước', 'icon' => '💡', 'desc' => 'Điện, nước, internet, vệ sinh.'],
            ['order' => 3, 'name' => 'Chi phí marketing', 'icon' => '📣', 'desc' => 'Quảng cáo online/offline.'],
            ['order' => 4, 'name' => 'Chi phí bảo trì thiết bị', 'icon' => '🛠️', 'desc' => 'Sửa chữa, bảo dưỡng trang thiết bị.'],
            ['order' => 5, 'name' => 'Chi phí nhân sự hỗ trợ', 'icon' => '👥', 'desc' => 'CTV, bảo vệ, hỗ trợ sự kiện.'],
            ['order' => 6, 'name' => 'Chi phí vật tư - văn phòng phẩm', 'icon' => '📦', 'desc' => 'Vật tư tiêu hao, văn phòng phẩm.'],
        ];

        $insertCategoryQuery = "
            INSERT INTO loai_chi_phi_branch (ID_CN, TEN_LOAI_CP, ICON_LOAI_CP, MO_TA, THU_TU_HIEN_THI)
            SELECT ID_CN, ?, ?, ?, ? FROM chi_nhanh
            ON DUPLICATE KEY UPDATE 
                ICON_LOAI_CP = VALUES(ICON_LOAI_CP),
                MO_TA = VALUES(MO_TA),
                THU_TU_HIEN_THI = VALUES(THU_TU_HIEN_THI)
        ";

        $stmt = $conn->prepare($insertCategoryQuery);
        if (!$stmt) {
            throw new Exception("Lỗi prepare statement: " . $conn->error);
        }

        foreach ($defaultCategories as $cat) {
            $stmt->bind_param('sssi', $cat['name'], $cat['icon'], $cat['desc'], $cat['order']);
            if (!$stmt->execute()) {
                throw new Exception("Lỗi chèn category: " . $stmt->error);
            }
        }
        $stmt->close();

        return true;

    } catch (Exception $e) {
        error_log("initializeExpenseManagement error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get expense statistics for a branch
 */
function getExpenseStats(mysqli $conn, int $branchId, string $month): array
{
    $stats = [
        'total_expenses' => 0,
        'by_category' => [],
        'count' => 0,
    ];

    try {
        // Tổng chi phí
        $query = "SELECT 
            COUNT(*) as count,
            SUM(GIA_TRI) as total,
            ID_LOAI_CP,
            TEN_CP
        FROM chi_phi_phat_sinh 
        WHERE ID_CN = ? AND DATE_FORMAT(NGAY_GIO, '%Y-%m') = ?
        GROUP BY ID_LOAI_CP, TEN_CP";

        $stmt = $conn->prepare($query);
        $stmt->bind_param('is', $branchId, $month);
        $stmt->execute();
        $result = $stmt->get_result();

        $total = 0;
        while ($row = $result->fetch_assoc()) {
            $total += (float)$row['total'];
            $stats['by_category'][$row['ID_LOAI_CP'] ?? 'uncategorized'] = [
                'total' => (float)$row['total'],
                'count' => (int)$row['count'],
                'name' => $row['TEN_CP'],
            ];
        }

        $stats['total_expenses'] = $total;
        $stats['count'] = count($stats['by_category']);

    } catch (Exception $e) {
        error_log("getExpenseStats error: " . $e->getMessage());
    }

    return $stats;
}

/**
 * Get expense history for trend analysis
 */
function getExpenseHistory(mysqli $conn, int $branchId, int $months = 12): array
{
    $history = [];

    try {
        $query = "SELECT 
            DATE_FORMAT(NGAY_GIO, '%Y-%m') as month_key,
            ID_LOAI_CP,
            TEN_CP,
            SUM(GIA_TRI) as total_value,
            COUNT(*) as count_items
        FROM chi_phi_phat_sinh 
        WHERE ID_CN = ? 
        AND DATE_FORMAT(NGAY_GIO, '%Y-%m') >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
        GROUP BY month_key, ID_LOAI_CP, TEN_CP 
        ORDER BY month_key DESC";

        $stmt = $conn->prepare($query);
        $stmt->bind_param('ii', $branchId, $months);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $key = $row['month_key'] . '_' . ($row['ID_LOAI_CP'] ?? 'uncategorized');
            $history[$key] = $row;
        }

    } catch (Exception $e) {
        error_log("getExpenseHistory error: " . $e->getMessage());
    }

    return $history;
}

/**
 * Format currency Vietnamese
 */
function formatCurrencyVN($value): string
{
    return number_format((float)$value, 0, ',', '.');
}
