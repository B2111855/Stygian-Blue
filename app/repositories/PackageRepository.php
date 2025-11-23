<?php
namespace App\Repositories;

class PackageRepository {
    private \mysqli $conn;

    public function __construct(\mysqli $conn) { $this->conn = $conn; }

    public function countPackages(string $search=''): int {
        $sql = "SELECT COUNT(*) total FROM goi_dich_vu g WHERE (g.TEN_GOI LIKE ? OR g.MO_TA LIKE ?)";
        $stmt = $this->conn->prepare($sql);
        $like = '%'.$search.'%';
        $stmt->bind_param('ss', $like, $like);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return (int)($row['total'] ?? 0);
    }

    public function searchPackages(string $search='', int $limit=10, int $offset=0, ?int $branchScope=null): array {
        // branchScope: if provided, include global packages + local owned by branch
        $sql = "SELECT g.*, vt.TONG_GIA_GOI
                FROM goi_dich_vu g
                LEFT JOIN v_goi_dich_vu_tong_tien vt ON vt.ID_GOI = g.ID_GOI
                WHERE (g.TEN_GOI LIKE ? OR g.MO_TA LIKE ?)";
        $like = '%'.$search.'%';
        if ($branchScope !== null) {
            $sql .= " AND (g.SCOPE_TYPE='global' OR (g.SCOPE_TYPE='local' AND g.ID_CN_OWNER=?))";
        }
        $sql .= " ORDER BY g.ID_GOI DESC LIMIT ? OFFSET ?";
        if ($branchScope !== null) {
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param('ssiii', $like, $like, $branchScope, $limit, $offset);
        } else {
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param('ssii', $like, $like, $limit, $offset);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) { $rows[] = $r; }
        return $rows;
    }

    public function find(int $id): ?array {
        $stmt = $this->conn->prepare("SELECT g.*, vt.TONG_GIA_GOI FROM goi_dich_vu g LEFT JOIN v_goi_dich_vu_tong_tien vt ON vt.ID_GOI=g.ID_GOI WHERE g.ID_GOI=? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    public function create(array $data, ?string $imagePath): int {
        // expects SCOPE_TYPE + ID_CN_OWNER optionally inside $data
        $scope = $data['SCOPE_TYPE'] ?? 'global';
        $owner = $data['ID_CN_OWNER'] ?? null;
        $stmt = $this->conn->prepare("INSERT INTO goi_dich_vu (TEN_GOI, MO_TA, HINH_ANH, HIEU_LUC_TU, HIEU_LUC_DEN, SCOPE_TYPE, ID_CN_OWNER, TRANG_THAI) VALUES (?,?,?,?,?,?,?, 'nhap')");
        $stmt->bind_param('ssssssii', $data['TEN_GOI'], $data['MO_TA'], $imagePath, $data['HIEU_LUC_TU'], $data['HIEU_LUC_DEN'], $scope, $owner);
        if (!$stmt->execute()) throw new \RuntimeException('Create package failed: '.$stmt->error);
        return (int)$this->conn->insert_id;
    }

    public function update(array $data, ?string $imagePath): bool {
        $fields = ['TEN_GOI = ?', 'MO_TA = ?', 'HIEU_LUC_TU = ?', 'HIEU_LUC_DEN = ?'];
        $params = [$data['TEN_GOI'], $data['MO_TA'], $data['HIEU_LUC_TU'], $data['HIEU_LUC_DEN']];
        if (isset($data['SCOPE_TYPE'])) { $fields[] = 'SCOPE_TYPE = ?'; $params[] = $data['SCOPE_TYPE']; }
        if (array_key_exists('ID_CN_OWNER', $data)) { $fields[] = 'ID_CN_OWNER = ?'; $params[] = $data['ID_CN_OWNER']; }
        $types = 'ssss';
        if ($imagePath) { $fields[] = 'HINH_ANH = ?'; $params[] = $imagePath; $types .= 's'; }
        // add types for scope / owner dynamic
        foreach ($fields as $f) {
            if (strpos($f, 'SCOPE_TYPE') !== false) { $types .= 's'; }
            if (strpos($f, 'ID_CN_OWNER') !== false) { $types .= 'i'; }
        }
        $types .= 'i';
        $params[] = $data['ID_GOI'];
        $sql = 'UPDATE goi_dich_vu SET '.implode(', ', $fields).' WHERE ID_GOI=?';
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        return $stmt->execute();
    }

    public function changeStatus(int $id, string $status): bool {
        $allowed = ['nhap','ban','ngung'];
        if (!in_array($status, $allowed, true)) return false;
        $stmt = $this->conn->prepare("UPDATE goi_dich_vu SET TRANG_THAI=? WHERE ID_GOI=?");
        $stmt->bind_param('si', $status, $id);
        return $stmt->execute();
    }

    public function addService(int $idGoi, int $idDv, int $soLuong=1, ?int $donGiaOverride=null, int $thuTu=1): bool {
        $stmt = $this->conn->prepare("REPLACE INTO goi_dich_vu_chi_tiet (ID_GOI, ID_DV, SO_LUONG, DON_GIA_AP_DUNG, THU_TU) VALUES (?,?,?,?,?)");
        $stmt->bind_param('iiiii', $idGoi, $idDv, $soLuong, $donGiaOverride, $thuTu);
        return $stmt->execute();
    }

    public function removeService(int $idGoi, int $idDv): bool {
        $stmt = $this->conn->prepare("DELETE FROM goi_dich_vu_chi_tiet WHERE ID_GOI=? AND ID_DV=? LIMIT 1");
        $stmt->bind_param('ii', $idGoi, $idDv);
        return $stmt->execute();
    }

    public function listServices(int $idGoi): array {
        $sql = "SELECT ct.*, dv.TEN_DV, dv.THOI_GIAN FROM goi_dich_vu_chi_tiet ct JOIN dich_vu dv ON dv.ID_DV=ct.ID_DV WHERE ct.ID_GOI=? ORDER BY COALESCE(ct.THU_TU,1), ct.ID_DV";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $idGoi);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) { $rows[] = $r; }
        return $rows;
    }

    /**
     * Bulk reorder services inside a package by array of service IDs (new sequence starting at 1).
     */
    public function reorderServices(int $idGoi, array $orderedIds): bool {
        if (empty($orderedIds)) return false;
        $this->conn->begin_transaction();
        try {
            $seq = 1;
            $stmt = $this->conn->prepare("UPDATE goi_dich_vu_chi_tiet SET THU_TU=? WHERE ID_GOI=? AND ID_DV=?");
            foreach ($orderedIds as $idDv) {
                $idDv = (int)$idDv;
                $stmt->bind_param('iii', $seq, $idGoi, $idDv);
                if (!$stmt->execute()) throw new \RuntimeException('Update order failed for service '.$idDv);
                $seq++;
            }
            $this->conn->commit();
            return true;
        } catch (\Throwable $e) {
            $this->conn->rollback();
            return false;
        }
    }

    public function deletionBlockingReasons(int $idGoi): array {
        $reasons = [];
        // Active period & selling
        $stmt = $this->conn->prepare("SELECT TRANG_THAI, HIEU_LUC_TU, HIEU_LUC_DEN FROM goi_dich_vu WHERE ID_GOI=?");
        $stmt->bind_param('i', $idGoi); $stmt->execute(); $pkg = $stmt->get_result()->fetch_assoc();
        if (!$pkg) { return []; }
        $now = date('Y-m-d H:i:s');
        $activeWindow = (!$pkg['HIEU_LUC_TU'] || $pkg['HIEU_LUC_TU'] <= $now) && (!$pkg['HIEU_LUC_DEN'] || $pkg['HIEU_LUC_DEN'] >= $now);
        if ($pkg['TRANG_THAI'] === 'ban' && $activeWindow) {
            $reasons[] = ['code'=>'selling_active','message'=>'Gói đang bán trong thời gian hiệu lực.'];
        }
        // Future / processing appointments referencing package
        $q = $this->conn->prepare("SELECT COUNT(*) c FROM lich_hen WHERE ID_GOI=? AND TRANGTHAI NOT IN ('Đã hoàn thành','Đã hủy')");
        $q->bind_param('i', $idGoi); $q->execute(); $c1 = $q->get_result()->fetch_assoc()['c'] ?? 0;
        if ($c1 > 0) { $reasons[] = ['code'=>'future_appointments','message'=>"Có $c1 lịch hẹn chưa kết thúc."]; }
        return $reasons;
    }

    public function retire(int $idGoi): bool { return $this->changeStatus($idGoi,'ngung'); }

    /**
     * Bulk replace all services of a package. Items format:
     * [ ['ID_DV'=>int,'SO_LUONG'=>int,'DON_GIA_AP_DUNG'=>int|null,'THU_TU'=>int], ...]
     */
    public function bulkReplaceServices(int $idGoi, array $items): bool {
        $this->conn->begin_transaction();
        try {
            $del = $this->conn->prepare("DELETE FROM goi_dich_vu_chi_tiet WHERE ID_GOI=?");
            $del->bind_param('i', $idGoi);
            if (!$del->execute()) throw new \RuntimeException('Delete old details failed');
            $ins = $this->conn->prepare("INSERT INTO goi_dich_vu_chi_tiet (ID_GOI, ID_DV, SO_LUONG, DON_GIA_AP_DUNG, THU_TU) VALUES (?,?,?,?,?)");
            foreach ($items as $it) {
                if (!isset($it['ID_DV'])) continue;
                $idDv = (int)$it['ID_DV'];
                $sl   = max(1,(int)($it['SO_LUONG'] ?? 1));
                $gia  = isset($it['DON_GIA_AP_DUNG']) && $it['DON_GIA_AP_DUNG']!=='' ? (int)$it['DON_GIA_AP_DUNG'] : null;
                $thuTu= max(1,(int)($it['THU_TU'] ?? 1));
                $ins->bind_param('iiiii', $idGoi, $idDv, $sl, $gia, $thuTu);
                if (!$ins->execute()) throw new \RuntimeException('Insert detail failed for service '.$idDv);
            }
            $this->conn->commit();
            return true;
        } catch (\Throwable $e) {
            $this->conn->rollback();
            return false;
        }
    }
}
