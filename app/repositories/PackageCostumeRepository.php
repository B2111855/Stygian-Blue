<?php
namespace App\Repositories;

class PackageCostumeRepository {
    private \mysqli $conn;
    public function __construct(\mysqli $conn) { $this->conn = $conn; }

    public function listCostumes(int $packageId): array {
        $sql = "SELECT gtp.*, tp.TEN, tp.SIZE, tp.MAU_SAC, tp.GIA_THUE, tp.TRANG_THAI
                FROM goi_trang_phuc_chi_tiet gtp
                JOIN trang_phuc tp ON tp.ID_TRANG_PHUC = gtp.ID_TRANG_PHUC
                WHERE gtp.ID_GOI=?
                ORDER BY gtp.THU_TU, tp.TEN";
        $stmt = $this->conn->prepare($sql); $stmt->bind_param('i',$packageId); $stmt->execute();
        $res = $stmt->get_result(); $rows=[]; while($r=$res->fetch_assoc()){ $rows[]=$r; } return $rows;
    }

    public function addCostume(int $packageId, int $costumeId, int $quantity=1, int $order=1, ?string $note=null): bool {
        $stmt = $this->conn->prepare("REPLACE INTO goi_trang_phuc_chi_tiet (ID_GOI, ID_TRANG_PHUC, SO_LUONG, THU_TU, GHI_CHU) VALUES (?,?,?,?,?)");
        $stmt->bind_param('iiiis', $packageId, $costumeId, $quantity, $order, $note);
        return $stmt->execute();
    }

    public function removeCostume(int $packageId, int $costumeId): bool {
        $stmt = $this->conn->prepare("DELETE FROM goi_trang_phuc_chi_tiet WHERE ID_GOI=? AND ID_TRANG_PHUC=? LIMIT 1");
        $stmt->bind_param('ii',$packageId,$costumeId); return $stmt->execute();
    }

    public function reorder(int $packageId, array $costumeIds): bool {
        if(empty($costumeIds)) return false; $this->conn->begin_transaction();
        try {
            $stmt = $this->conn->prepare("UPDATE goi_trang_phuc_chi_tiet SET THU_TU=? WHERE ID_GOI=? AND ID_TRANG_PHUC=?");
            $seq=1; foreach($costumeIds as $cid){ $cid=(int)$cid; $stmt->bind_param('iii',$seq,$packageId,$cid); if(!$stmt->execute()) throw new \RuntimeException('Fail reorder'); $seq++; }
            $this->conn->commit(); return true;
        } catch(\Throwable $e){ $this->conn->rollback(); return false; }
    }

    public function bulkReplace(int $packageId, array $items): bool {
        $this->conn->begin_transaction();
        try {
            $del = $this->conn->prepare("DELETE FROM goi_trang_phuc_chi_tiet WHERE ID_GOI=?");
            $del->bind_param('i',$packageId); if(!$del->execute()) throw new \RuntimeException('Delete old');
            $ins = $this->conn->prepare("INSERT INTO goi_trang_phuc_chi_tiet (ID_GOI, ID_TRANG_PHUC, SO_LUONG, THU_TU, GHI_CHU) VALUES (?,?,?,?,?)");
            foreach($items as $it){
                if(!isset($it['ID_TRANG_PHUC'])) continue;
                $cid = (int)$it['ID_TRANG_PHUC'];
                $qty = max(1,(int)($it['SO_LUONG'] ?? 1));
                $order = max(1,(int)($it['THU_TU'] ?? 1));
                $note = isset($it['GHI_CHU']) && $it['GHI_CHU']!=='' ? (string)$it['GHI_CHU'] : null;
                $ins->bind_param('iiiis',$packageId,$cid,$qty,$order,$note);
                if(!$ins->execute()) throw new \RuntimeException('Insert pivot failed');
            }
            $this->conn->commit(); return true;
        } catch(\Throwable $e){ $this->conn->rollback(); return false; }
    }
}
?>