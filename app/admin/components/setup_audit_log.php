<?php
/**
 * Setup: Tạo bảng audit log cho chi phí phát sinh
 * File: app/admin/components/setup_audit_log.php
 * 
 * Bảng: chi_phi_audit_log
 * Mục đích: Ghi nhận mọi thay đổi của manager (create/update/delete)
 */

include '../../../database/config.php';

header('Content-Type: application/json');

try {
    // Tạo bảng audit log
    $createTableQuery = "CREATE TABLE IF NOT EXISTS chi_phi_audit_log (
        ID_AUDIT INT AUTO_INCREMENT PRIMARY KEY,
        ID_CP INT,
        ID_LOAI INT NOT NULL,
        ID_CN INT NOT NULL,
        THANG VARCHAR(7) NOT NULL COMMENT 'YYYY-MM',
        GIA_TRI_CU DECIMAL(15, 2),
        GIA_TRI_MOI DECIMAL(15, 2),
        HANH_DONG VARCHAR(20) NOT NULL COMMENT 'insert|update|delete',
        NGUOI_THAM_GIA VARCHAR(255),
        NGAY_GIO DATETIME DEFAULT CURRENT_TIMESTAMP,
        GHI_CHU VARCHAR(500),
        
        INDEX idx_loai (ID_LOAI),
        INDEX idx_cn (ID_CN),
        INDEX idx_thang (THANG),
        INDEX idx_ngay_gio (NGAY_GIO),
        INDEX idx_hanh_dong (HANH_DONG)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (mysqli_query($conn, $createTableQuery)) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Audit log table created successfully'
        ]);
    } else {
        throw new Exception(mysqli_error($conn));
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
