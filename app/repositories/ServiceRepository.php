<?php
namespace App\Repositories;

class ServiceRepository {
    private \mysqli $conn;

    public function __construct(\mysqli $conn) {
        $this->conn = $conn;
    }

    public function searchServices(
        string $search = '',
        int $limit = 10,
        int $offset = 0,
        ?string $status = null,
        ?int $minPrice = null,
        ?int $maxPrice = null,
        ?int $categoryId = null,
        ?int $tagId = null,
        string $sortBy = 'ID_DV',
        string $sortOrder = 'DESC'
    ): array {
        $clauses = [];
        $params = [];
        $types  = '';
        $clauses[] = 'dv.IS_DELETED = 0';
        $clauses[] = '(dv.TEN_DV LIKE ? OR dv.MOTA_DV LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like; $params[] = $like; $types .= 'ss';
        if ($status && in_array($status, ['active','draft','retired'], true)) {
            $clauses[] = 'dv.TRANG_THAI = ?';
            $params[] = $status; $types .= 's';
        }
        if ($minPrice !== null) { $clauses[] = 'COALESCE(dgdv.DON_GIA,0) >= ?'; $params[] = $minPrice; $types .= 'i'; }
        if ($maxPrice !== null) { $clauses[] = 'COALESCE(dgdv.DON_GIA,0) <= ?'; $params[] = $maxPrice; $types .= 'i'; }
        if ($categoryId !== null && $categoryId > 0) {
            $clauses[] = 'dv.ID_DANH_MUC = ?';
            $params[] = $categoryId; $types .= 'i';
        }
        if ($tagId !== null && $tagId > 0) {
            $clauses[] = 'EXISTS (SELECT 1 FROM dich_vu_the dvt WHERE dvt.ID_DV = dv.ID_DV AND dvt.ID_THE = ?)';
            $params[] = $tagId; $types .= 'i';
        }
        
        // Validate sort parameters
        $allowedSortFields = ['ID_DV', 'TEN_DV', 'THOI_GIAN', 'DON_GIA', 'TRANG_THAI'];
        $sortBy = in_array($sortBy, $allowedSortFields) ? $sortBy : 'ID_DV';
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
        
        // Handle DON_GIA sort
        $orderByField = $sortBy === 'DON_GIA' ? 'dgdv.DON_GIA' : 'dv.' . $sortBy;
        
        $params[] = $limit; $types .= 'i';
        $params[] = $offset; $types .= 'i';
        $where = implode(' AND ', $clauses);
        $sql = "SELECT dv.*, dgdv.DON_GIA
                FROM dich_vu dv
                LEFT JOIN (
                   SELECT d1.ID_DV, d1.DON_GIA
                   FROM don_gia_dich_vu d1
                   INNER JOIN (
                     SELECT ID_DV, MAX(NGAY_GIO) AS MAX_DATE
                     FROM don_gia_dich_vu
                     GROUP BY ID_DV
                   ) d2 ON d1.ID_DV = d2.ID_DV AND d1.NGAY_GIO = d2.MAX_DATE
                ) dgdv ON dv.ID_DV = dgdv.ID_DV
                WHERE $where
                ORDER BY $orderByField $sortOrder
                LIMIT ? OFFSET ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) { $rows[] = $row; }
        return $rows;
    }

    public function countServices(
        string $search = '',
        ?string $status = null,
        ?int $minPrice = null,
        ?int $maxPrice = null,
        ?int $categoryId = null,
        ?int $tagId = null
    ): int {
        $clauses = [];
        $params = [];
        $types  = '';
        $clauses[] = 'dv.IS_DELETED = 0';
        $clauses[] = '(dv.TEN_DV LIKE ? OR dv.MOTA_DV LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like; $params[] = $like; $types .= 'ss';
        if ($status && in_array($status, ['active','draft','retired'], true)) {
            $clauses[] = 'dv.TRANG_THAI = ?';
            $params[] = $status; $types .= 's';
        }
        if ($minPrice !== null) { $clauses[] = 'COALESCE(dgdv.DON_GIA,0) >= ?'; $params[] = $minPrice; $types .= 'i'; }
        if ($maxPrice !== null) { $clauses[] = 'COALESCE(dgdv.DON_GIA,0) <= ?'; $params[] = $maxPrice; $types .= 'i'; }
        if ($categoryId !== null && $categoryId > 0) {
            $clauses[] = 'dv.ID_DANH_MUC = ?';
            $params[] = $categoryId; $types .= 'i';
        }
        if ($tagId !== null && $tagId > 0) {
            $clauses[] = 'EXISTS (SELECT 1 FROM dich_vu_the dvt WHERE dvt.ID_DV = dv.ID_DV AND dvt.ID_THE = ?)';
            $params[] = $tagId; $types .= 'i';
        }
        $where = implode(' AND ', $clauses);
        $sql = "SELECT COUNT(*) AS total
            FROM dich_vu dv
            LEFT JOIN (
               SELECT d1.ID_DV, d1.DON_GIA
               FROM don_gia_dich_vu d1
               INNER JOIN (
                 SELECT ID_DV, MAX(NGAY_GIO) AS MAX_DATE
                 FROM don_gia_dich_vu
                 GROUP BY ID_DV
               ) d2 ON d1.ID_DV = d2.ID_DV AND d1.NGAY_GIO = d2.MAX_DATE
            ) dgdv ON dv.ID_DV = dgdv.ID_DV
            WHERE $where";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException('Prepare countServices failed: ' . $this->conn->error);
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        return (int)($res['total'] ?? 0);
    }

    public function findById(int $id): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM dich_vu WHERE ID_DV = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        return $res ?: null;
    }

    public function create(array $data, ?string $imagePath): array {
        $categoryId = isset($data['ID_DANH_MUC']) && $data['ID_DANH_MUC'] > 0 ? $data['ID_DANH_MUC'] : null;
        $stmt = $this->conn->prepare("INSERT INTO dich_vu (TEN_DV, MOTA_DV, IMAGE, THOI_GIAN, TRANG_THAI, ID_DANH_MUC) VALUES (?,?,?,?, 'active', ?)");
        $stmt->bind_param('sssii', $data['TEN_DV'], $data['MOTA_DV'], $imagePath, $data['THOI_GIAN'], $categoryId);
        if (!$stmt->execute()) { throw new \RuntimeException('Insert service failed: ' . $stmt->error); }
        $id = $this->conn->insert_id;
        if (isset($data['GIA']) && is_numeric($data['GIA']) && $data['GIA'] > 0) {
            $now = date('Y-m-d H:i:s');
            $this->ensureThoiDiem($now);
            $stmt2 = $this->conn->prepare("INSERT INTO don_gia_dich_vu (ID_DV, DON_GIA, NGAY_GIO) VALUES (?,?,?)");
            $stmt2->bind_param('iis', $id, $data['GIA'], $now);
            $stmt2->execute();
        }
        return $this->findById($id);
    }

    public function update(array $data, ?string $imagePath): bool {
        $fields = [ 'TEN_DV = ?', 'MOTA_DV = ?', 'THOI_GIAN = ?' ];
        $params = [ $data['TEN_DV'], $data['MOTA_DV'], $data['THOI_GIAN'] ];
        $types = 'ssi';
        if ($imagePath) { $fields[] = 'IMAGE = ?'; $params[] = $imagePath; $types .= 's'; }
        if (isset($data['ID_DANH_MUC'])) {
            $fields[] = 'ID_DANH_MUC = ?';
            $categoryId = $data['ID_DANH_MUC'] > 0 ? $data['ID_DANH_MUC'] : null;
            $params[] = $categoryId;
            $types .= 'i';
        }
        $types .= 'i';
        $params[] = $data['ID_DV'];
        $sql = 'UPDATE dich_vu SET ' . implode(', ', $fields) . ' WHERE ID_DV = ?';
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $ok = $stmt->execute();
        if (!$ok) { return false; }
        if (isset($data['GIA']) && is_numeric($data['GIA']) && $data['GIA'] > 0) {
            $now = date('Y-m-d H:i:s');
            $this->ensureThoiDiem($now);
            $stmt2 = $this->conn->prepare("INSERT INTO don_gia_dich_vu (ID_DV, DON_GIA, NGAY_GIO) VALUES (?,?,?)");
            $stmt2->bind_param('iis', $data['ID_DV'], $data['GIA'], $now);
            $stmt2->execute();
        }
        return true;
    }

    public function retire(int $id): bool {
        $stmt = $this->conn->prepare("UPDATE dich_vu SET TRANG_THAI='retired' WHERE ID_DV=? AND IS_DELETED=0");
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }

    public function purge(int $id): bool {
        $stmt = $this->conn->prepare("DELETE FROM dich_vu WHERE ID_DV=? LIMIT 1");
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }

    public function deletionBlockingReasons(int $id): array {
        $reasons = [];
        // Future / active appointments
        $q1 = $this->conn->prepare("SELECT COUNT(*) c FROM lich_hen WHERE ID_DV=? AND TRANGTHAI NOT IN ('Đã hoàn thành','Đã hủy')");
        $q1->bind_param('i', $id); $q1->execute(); $c1 = $q1->get_result()->fetch_assoc()['c'] ?? 0;
        if ($c1 > 0) { $reasons[] = ['code'=>'future_appointments','message'=>"Còn $c1 lịch hẹn đang xử lý."]; }
        // Active packages containing service
        $q2 = $this->conn->prepare("SELECT COUNT(*) c FROM goi_dich_vu_chi_tiet ct JOIN goi_dich_vu g ON g.ID_GOI=ct.ID_GOI WHERE ct.ID_DV=? AND g.TRANG_THAI='ban' AND (g.HIEU_LUC_TU IS NULL OR g.HIEU_LUC_TU<=NOW()) AND (g.HIEU_LUC_DEN IS NULL OR g.HIEU_LUC_DEN>=NOW())");
        $q2->bind_param('i', $id); $q2->execute(); $c2 = $q2->get_result()->fetch_assoc()['c'] ?? 0;
        if ($c2 > 0) { $reasons[] = ['code'=>'active_package','message'=>"Thuộc $c2 gói đang bán."]; }
        // Historical invoices (doanh thu) -> prefer retire vs purge
        $q3 = $this->conn->prepare("SELECT COUNT(*) c FROM chi_tiet_hoa_don WHERE LOAI='service' AND ID_THAM_CHIEU=?");
        $q3->bind_param('i', $id); $q3->execute(); $c3 = $q3->get_result()->fetch_assoc()['c'] ?? 0;
        if ($c3 > 0) { $reasons[] = ['code'=>'financial_history','message'=>"Đã có dữ liệu doanh thu ($c3 dòng). Nên ngừng thay vì xóa."]; }
        return $reasons;
    }

    /**
     * Lấy lịch sử giá của dịch vụ (mới nhất trước).
     * Trả về mảng mỗi phần tử: ['DON_GIA'=>int,'NGAY_GIO'=>datetime string]
     */
    public function getPriceHistory(int $idDv, int $limit = 50): array {
        $sql = "SELECT DON_GIA, NGAY_GIO FROM don_gia_dich_vu WHERE ID_DV=? ORDER BY NGAY_GIO DESC LIMIT ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ii', $idDv, $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) { $rows[] = $row; }
        return $rows;
    }

    private function ensureThoiDiem(string $dt): void {
        $stmt = $this->conn->prepare("INSERT IGNORE INTO thoi_diem (NGAY_GIO) VALUES (?)");
        $stmt->bind_param('s', $dt); $stmt->execute();
    }
}
