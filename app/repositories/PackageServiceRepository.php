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
                    g.GIA_GOI,
                    g.TRANG_THAI,
                    g.HIEU_LUC_TU,
                    g.HIEU_LUC_DEN,
                    gct.SO_LUONG,
                    gct.GHI_CHU
                FROM goi_dich_vu g
                INNER JOIN goi_dich_vu_chi_tiet gct ON g.ID_GOI = gct.ID_GOI
                WHERE gct.ID_DV = ?
                ORDER BY g.TRANG_THAI DESC, g.ID_GOI DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $serviceId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $packages = [];
        while ($row = $result->fetch_assoc()) {
            $packages[] = $row;
        }
        
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
        $stmt->bind_param('i', $serviceId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return (int)$result['count'] > 0;
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
        $stmt->bind_param('i', $serviceId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return [
            'total' => (int)($result['total_packages'] ?? 0),
            'active' => (int)($result['active_packages'] ?? 0),
            'inactive' => (int)($result['inactive_packages'] ?? 0)
        ];
    }
}
