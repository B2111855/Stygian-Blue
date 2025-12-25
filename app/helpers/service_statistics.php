<?php

class ServiceStatistics {
    private \mysqli $conn;
    
    public function __construct(\mysqli $conn) {
        $this->conn = $conn;
    }
    
    /**
     * Get most booked services
     */
    public function getMostPopularServices(int $limit = 5): array {
        $sql = "SELECT 
                    dv.ID_DV,
                    dv.TEN_DV,
                    dv.IMAGE,
                    COUNT(lh.ID_LICHHEN) as booking_count,
                    SUM(CASE WHEN lh.TRANGTHAI = 'Đã hoàn thành' THEN 1 ELSE 0 END) as completed_count
                FROM dich_vu dv
                LEFT JOIN lich_hen lh ON dv.ID_DV = lh.ID_DV
                WHERE dv.IS_DELETED = 0 AND dv.TRANG_THAI = 'active'
                GROUP BY dv.ID_DV, dv.TEN_DV, dv.IMAGE
                ORDER BY booking_count DESC
                LIMIT ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $services = [];
        while ($row = $result->fetch_assoc()) {
            $services[] = $row;
        }
        return $services;
    }
    
    /**
     * Get service revenue statistics
     */
    public function getServiceRevenue(int $limit = 5): array {
        $sql = "SELECT 
                    dv.ID_DV,
                    dv.TEN_DV,
                    dv.IMAGE,
                    COALESCE(SUM(cthd.THANH_TIEN), 0) as total_revenue,
                    COUNT(DISTINCT cthd.ID_HOA_DON) as invoice_count
                FROM dich_vu dv
                LEFT JOIN chi_tiet_hoa_don cthd ON cthd.LOAI = 'service' AND cthd.ID_THAM_CHIEU = dv.ID_DV
                WHERE dv.IS_DELETED = 0
                GROUP BY dv.ID_DV, dv.TEN_DV, dv.IMAGE
                HAVING total_revenue > 0
                ORDER BY total_revenue DESC
                LIMIT ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $services = [];
        while ($row = $result->fetch_assoc()) {
            $services[] = $row;
        }
        return $services;
    }
    
    /**
     * Get overall service statistics
     */
    public function getOverallStats(): array {
        $stats = [];
        
        // Total services by status
        $sql = "SELECT 
                    TRANG_THAI,
                    COUNT(*) as count
                FROM dich_vu
                WHERE IS_DELETED = 0
                GROUP BY TRANG_THAI";
        $result = $this->conn->query($sql);
        $stats['by_status'] = [];
        while ($row = $result->fetch_assoc()) {
            $stats['by_status'][$row['TRANG_THAI']] = (int)$row['count'];
        }
        
        // Total bookings
        $sql = "SELECT COUNT(*) as total FROM lich_hen WHERE ID_DV IS NOT NULL";
        $result = $this->conn->query($sql);
        $stats['total_bookings'] = (int)$result->fetch_assoc()['total'];
        
        // Average service price
        $sql = "SELECT AVG(dgdv.DON_GIA) as avg_price
                FROM dich_vu dv
                LEFT JOIN (
                    SELECT ID_DV, DON_GIA
                    FROM don_gia_dich_vu d1
                    WHERE NGAY_GIO = (
                        SELECT MAX(NGAY_GIO) 
                        FROM don_gia_dich_vu d2 
                        WHERE d2.ID_DV = d1.ID_DV
                    )
                ) dgdv ON dv.ID_DV = dgdv.ID_DV
                WHERE dv.IS_DELETED = 0 AND dv.TRANG_THAI = 'active'";
        $result = $this->conn->query($sql);
        $stats['avg_price'] = (int)($result->fetch_assoc()['avg_price'] ?? 0);
        
        return $stats;
    }
    
    /**
     * Get services with no bookings
     */
    public function getUnusedServices(int $days = 30): array {
        $sql = "SELECT 
                    dv.ID_DV,
                    dv.TEN_DV,
                    dv.TRANG_THAI,
                    COALESCE(MAX(lh.NGAY_HEN), 'Chưa có lịch hẹn') as last_booking
                FROM dich_vu dv
                LEFT JOIN lich_hen lh ON dv.ID_DV = lh.ID_DV
                WHERE dv.IS_DELETED = 0 
                    AND dv.TRANG_THAI = 'active'
                    AND (lh.NGAY_HEN IS NULL OR lh.NGAY_HEN < DATE_SUB(NOW(), INTERVAL ? DAY))
                GROUP BY dv.ID_DV, dv.TEN_DV, dv.TRANG_THAI
                ORDER BY last_booking ASC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $days);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $services = [];
        while ($row = $result->fetch_assoc()) {
            $services[] = $row;
        }
        return $services;
    }
}
