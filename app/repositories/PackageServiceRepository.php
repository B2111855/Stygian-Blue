<?php
namespace App\Repositories;

class PackageServiceRepository {
    private \mysqli $conn;
    
    public function __construct(\mysqli $conn) {
        $this->conn = $conn;
    }
    
    /**
     * Get all packages that contain a specific service
     */
    public function getPackagesContainingService(int $serviceId): array {
        $sql = "SELECT 
                    g.ID_GOI,
                    g.TEN_GOI,
                    g.MO_TA,
                    COALESCE(v.TONG_GIA_GOI, 0) AS GIA_GOI,
                    g.TRANG_THAI,
                    g.HIEU_LUC_TU,
                    g.HIEU_LUC_DEN,
                    gct.SO_LUONG,
                    NULL AS GHI_CHU
                FROM goi_dich_vu g
                INNER JOIN goi_dich_vu_chi_tiet gct ON g.ID_GOI = gct.ID_GOI
                LEFT JOIN v_goi_dich_vu_tong_tien v ON v.ID_GOI = g.ID_GOI
                WHERE gct.ID_DV = ?
                ORDER BY g.TRANG_THAI DESC, g.ID_GOI DESC";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException('Prepare failed: ' . $this->conn->error);
        }

        $stmt->bind_param('i', $serviceId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new \RuntimeException('Execute failed: ' . $this->conn->error);
        }
        $result = $stmt->get_result();
        
        $packages = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $packages[] = $row;
            }
        }
        
        $stmt->close();
        return $packages;
    }
    
    /**
     * Check if service is in any active package
     */
    public function isServiceInActivePackage(int $serviceId): bool {
        $sql = "SELECT COUNT(*) as count
                FROM goi_dich_vu g
                INNER JOIN goi_dich_vu_chi_tiet gct ON g.ID_GOI = gct.ID_GOI
                WHERE gct.ID_DV = ?
                AND g.TRANG_THAI = 'ban'
                AND (g.HIEU_LUC_TU IS NULL OR g.HIEU_LUC_TU <= NOW())
                AND (g.HIEU_LUC_DEN IS NULL OR g.HIEU_LUC_DEN >= NOW())";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException('Prepare failed: ' . $this->conn->error);
        }

        $stmt->bind_param('i', $serviceId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new \RuntimeException('Execute failed: ' . $this->conn->error);
        }
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : ['count' => 0];
        $stmt->close();
        
        return (int)($row['count'] ?? 0) > 0;
    }
    
    /**
     * Get package statistics for a service
     */
    public function getServicePackageStats(int $serviceId): array {
        $sql = "SELECT 
                    COUNT(DISTINCT g.ID_GOI) as total_packages,
                    SUM(CASE WHEN g.TRANG_THAI = 'ban' THEN 1 ELSE 0 END) as active_packages,
                    SUM(CASE WHEN g.TRANG_THAI = 'ngung' THEN 1 ELSE 0 END) as inactive_packages
                FROM goi_dich_vu g
                INNER JOIN goi_dich_vu_chi_tiet gct ON g.ID_GOI = gct.ID_GOI
                WHERE gct.ID_DV = ?";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException('Prepare failed: ' . $this->conn->error);
        }

        $stmt->bind_param('i', $serviceId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new \RuntimeException('Execute failed: ' . $this->conn->error);
        }
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : [];
        $stmt->close();
        
        return [
            'total' => (int)($row['total_packages'] ?? 0),
            'active' => (int)($row['active_packages'] ?? 0),
            'inactive' => (int)($row['inactive_packages'] ?? 0)
        ];
    }
}
