<?php
/**
 * PromotionRepository
 * Quản lý CRUD cho bảng goi_dich_vu_khuyen_mai
 */

class PromotionRepository {
    private $conn;

    public function __construct($connection) {
        $this->conn = $connection;
    }

    /**
     * Lấy danh sách khuyến mãi của một gói
     * @param int $packageId ID gói dịch vụ
     * @param bool|null $activeOnly true=chỉ active, false=chỉ inactive, null=tất cả
     * @return array
     */
    public function listPromotions($packageId, $activeOnly = null) {
        $sql = "SELECT 
                    km.ID_KM,
                    km.ID_GOI,
                    km.TEN_CHUONG_TRINH,
                    km.MO_TA,
                    km.LOAI_GIAM,
                    km.GIA_TRI_GIAM,
                    km.GIAM_TOI_DA,
                    km.TU_NGAY,
                    km.DEN_NGAY,
                    km.ACTIVE,
                    km.CREATED_AT,
                    km.UPDATED_AT,
                    CASE 
                        WHEN NOW() BETWEEN km.TU_NGAY AND km.DEN_NGAY THEN 1
                        ELSE 0
                    END AS IS_CURRENT
                FROM goi_dich_vu_khuyen_mai km
                WHERE km.ID_GOI = ?";
        
        if ($activeOnly === true) {
            $sql .= " AND km.ACTIVE = 1";
        } elseif ($activeOnly === false) {
            $sql .= " AND km.ACTIVE = 0";
        }
        
        $sql .= " ORDER BY km.TU_NGAY DESC, km.ID_KM DESC";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->conn->error);
        }
        
        $stmt->bind_param("i", $packageId);
        $stmt->execute();
        $result = $stmt->get_result();
        $promotions = [];
        while ($row = $result->fetch_assoc()) {
            $promotions[] = $row;
        }
        $stmt->close();
        
        return $promotions;
    }

    /**
     * Tạo khuyến mãi mới
     * @param array $data ['ID_GOI', 'TEN_CHUONG_TRINH', 'MO_TA', 'LOAI_GIAM', 'GIA_TRI_GIAM', 'GIAM_TOI_DA', 'TU_NGAY', 'DEN_NGAY', 'ACTIVE']
     * @return int ID khuyến mãi mới
     */
    public function createPromotion($data) {
        $sql = "INSERT INTO goi_dich_vu_khuyen_mai 
                (ID_GOI, TEN_CHUONG_TRINH, MO_TA, LOAI_GIAM, GIA_TRI_GIAM, GIAM_TOI_DA, TU_NGAY, DEN_NGAY, ACTIVE)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->conn->error);
        }
        
        $id_goi = (int)$data['ID_GOI'];
        $ten = $data['TEN_CHUONG_TRINH'];
        $mo_ta = $data['MO_TA'] ?? null;
        $loai = $data['LOAI_GIAM'];
        $gia_tri = (float)$data['GIA_TRI_GIAM'];
        $giam_toi_da = isset($data['GIAM_TOI_DA']) && $data['GIAM_TOI_DA'] !== '' ? (float)$data['GIAM_TOI_DA'] : null;
        $tu_ngay = $data['TU_NGAY'];
        $den_ngay = $data['DEN_NGAY'];
        $active = isset($data['ACTIVE']) ? (int)$data['ACTIVE'] : 1;
        
        $stmt->bind_param("isssddssi", 
            $id_goi, $ten, $mo_ta, $loai, $gia_tri, $giam_toi_da, $tu_ngay, $den_ngay, $active
        );
        
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Insert failed: " . $error);
        }
        
        $newId = $stmt->insert_id;
        $stmt->close();
        
        return $newId;
    }

    /**
     * Cập nhật khuyến mãi
     * @param int $promotionId
     * @param array $data
     * @return bool
     */
    public function updatePromotion($promotionId, $data) {
        $sql = "UPDATE goi_dich_vu_khuyen_mai 
                SET TEN_CHUONG_TRINH = ?,
                    MO_TA = ?,
                    LOAI_GIAM = ?,
                    GIA_TRI_GIAM = ?,
                    GIAM_TOI_DA = ?,
                    TU_NGAY = ?,
                    DEN_NGAY = ?,
                    ACTIVE = ?
                WHERE ID_KM = ?";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->conn->error);
        }
        
        $ten = $data['TEN_CHUONG_TRINH'];
        $mo_ta = $data['MO_TA'] ?? null;
        $loai = $data['LOAI_GIAM'];
        $gia_tri = (float)$data['GIA_TRI_GIAM'];
        $giam_toi_da = isset($data['GIAM_TOI_DA']) && $data['GIAM_TOI_DA'] !== '' ? (float)$data['GIAM_TOI_DA'] : null;
        $tu_ngay = $data['TU_NGAY'];
        $den_ngay = $data['DEN_NGAY'];
        $active = isset($data['ACTIVE']) ? (int)$data['ACTIVE'] : 1;
        $id_km = (int)$promotionId;
        
        $stmt->bind_param("sssddssi", 
            $ten, $mo_ta, $loai, $gia_tri, $giam_toi_da, $tu_ngay, $den_ngay, $active, $id_km
        );
        
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
    }

    /**
     * Xóa khuyến mãi
     * @param int $promotionId
     * @return bool
     */
    public function deletePromotion($promotionId) {
        $sql = "DELETE FROM goi_dich_vu_khuyen_mai WHERE ID_KM = ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->conn->error);
        }
        
        $id_km = (int)$promotionId;
        $stmt->bind_param("i", $id_km);
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
    }

    /**
     * Bật/tắt khuyến mãi
     * @param int $promotionId
     * @param int $active 0 hoặc 1
     * @return bool
     */
    public function toggleActive($promotionId, $active) {
        $sql = "UPDATE goi_dich_vu_khuyen_mai SET ACTIVE = ? WHERE ID_KM = ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->conn->error);
        }
        
        $active_val = (int)$active;
        $id_km = (int)$promotionId;
        $stmt->bind_param("ii", $active_val, $id_km);
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
    }

    /**
     * Lấy khuyến mãi đang hiệu lực tốt nhất cho gói
     * @param int $packageId
     * @param float $originalPrice Giá gốc để tính toán
     * @return array|null
     */
    public function getActivePromotionForPackage($packageId, $originalPrice = 0) {
        $sql = "SELECT 
                    km.ID_KM,
                    km.TEN_CHUONG_TRINH,
                    km.LOAI_GIAM,
                    km.GIA_TRI_GIAM,
                    km.GIAM_TOI_DA,
                    km.TU_NGAY,
                    km.DEN_NGAY,
                    CASE 
                        WHEN km.LOAI_GIAM = 'phan_tram' THEN 
                            LEAST(? * km.GIA_TRI_GIAM / 100, COALESCE(km.GIAM_TOI_DA, 999999999))
                        WHEN km.LOAI_GIAM = 'so_tien' THEN km.GIA_TRI_GIAM
                        ELSE 0
                    END AS SO_TIEN_GIAM
                FROM goi_dich_vu_khuyen_mai km
                WHERE km.ID_GOI = ?
                  AND km.ACTIVE = 1
                  AND NOW() BETWEEN km.TU_NGAY AND km.DEN_NGAY
                ORDER BY SO_TIEN_GIAM DESC
                LIMIT 1";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->conn->error);
        }
        
        $price = (float)$originalPrice;
        $id_goi = (int)$packageId;
        $stmt->bind_param("di", $price, $id_goi);
        $stmt->execute();
        $result = $stmt->get_result();
        $promotion = $result->fetch_assoc();
        $stmt->close();
        
        return $promotion;
    }

    /**
     * Lấy chi tiết một khuyến mãi
     * @param int $promotionId
     * @return array|null
     */
    public function getPromotionById($promotionId) {
        $sql = "SELECT * FROM goi_dich_vu_khuyen_mai WHERE ID_KM = ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->conn->error);
        }
        
        $id_km = (int)$promotionId;
        $stmt->bind_param("i", $id_km);
        $stmt->execute();
        $result = $stmt->get_result();
        $promotion = $result->fetch_assoc();
        $stmt->close();
        
        return $promotion;
    }
}
