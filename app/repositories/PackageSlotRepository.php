<?php
namespace App\Repositories;

class PackageSlotRepository {
    private \mysqli $conn;
    public function __construct(\mysqli $conn) { $this->conn = $conn; }

    // Slots CRUD
    public function listSlots(int $packageId): array {
        $sql = "SELECT ID_SLOT, ID_GOI, TEN_SLOT, NHOM, LOAI, SIZE, SO_LUONG, BAT_BUOC, ACTIVE, CREATED_AT, UPDATED_AT
                FROM goi_dich_vu_trang_phuc_slot WHERE ID_GOI=? ORDER BY ID_SLOT ASC";
        $stmt = $this->conn->prepare($sql); $stmt->bind_param('i',$packageId); $stmt->execute();
        $res = $stmt->get_result(); $rows=[]; while($r=$res->fetch_assoc()){ $rows[]=$r; } return $rows;
    }

    public function createSlot(array $data): int {
        $sql = "INSERT INTO goi_dich_vu_trang_phuc_slot (ID_GOI, TEN_SLOT, NHOM, LOAI, SIZE, SO_LUONG, BAT_BUOC, ACTIVE, CREATED_AT, UPDATED_AT)
                VALUES (?,?,?,?,?,?,?,?,?,?)";
        $stmt = $this->conn->prepare($sql);
        $now = date('Y-m-d H:i:s');
        $idGoi = (int)$data['ID_GOI'];
        $ten = (string)$data['TEN_SLOT'];
        $nhom = $data['NHOM'] ?? null;
        $loai = $data['LOAI'] ?? null;
        $size = $data['SIZE'] ?? null;
        $soLuong = max(1,(int)($data['SO_LUONG'] ?? 1));
        $batBuoc = !empty($data['BAT_BUOC']) ? 1 : 0;
        $active = array_key_exists('ACTIVE',$data) ? (int)!empty($data['ACTIVE']) : 1;
        $stmt->bind_param('issssiiiss', $idGoi, $ten, $nhom, $loai, $size, $soLuong, $batBuoc, $active, $now, $now);
        if(!$stmt->execute()) throw new \RuntimeException('Create slot failed: '.$stmt->error);
        return (int)$this->conn->insert_id;
    }

    public function updateSlot(array $data): bool {
        $sql = "UPDATE goi_dich_vu_trang_phuc_slot SET TEN_SLOT=?, NHOM=?, LOAI=?, SIZE=?, SO_LUONG=?, BAT_BUOC=?, ACTIVE=?, UPDATED_AT=? WHERE ID_SLOT=? AND ID_GOI=?";
        $stmt = $this->conn->prepare($sql);
        $now = date('Y-m-d H:i:s');
        $ten = (string)$data['TEN_SLOT'];
        $nhom = $data['NHOM'] ?? null;
        $loai = $data['LOAI'] ?? null;
        $size = $data['SIZE'] ?? null;
        $soLuong = max(1,(int)$data['SO_LUONG']);
        $batBuoc = !empty($data['BAT_BUOC']) ? 1 : 0;
        $active = !empty($data['ACTIVE']) ? 1 : 0;
        $idSlot = (int)$data['ID_SLOT'];
        $idGoi  = (int)$data['ID_GOI'];
        $stmt->bind_param('ssssiiisii',
            $ten, $nhom, $loai, $size, $soLuong, $batBuoc, $active, $now, $idSlot, $idGoi
        );
        return $stmt->execute();
    }

    public function deleteSlot(int $slotId, int $packageId): bool {
        $stmt = $this->conn->prepare("DELETE FROM goi_dich_vu_trang_phuc_slot WHERE ID_SLOT=? AND ID_GOI=?");
        $stmt->bind_param('ii', $slotId, $packageId); return $stmt->execute();
    }

    public function toggleSlotActive(int $slotId, int $packageId, bool $active): bool {
        $stmt = $this->conn->prepare("UPDATE goi_dich_vu_trang_phuc_slot SET ACTIVE=?, UPDATED_AT=? WHERE ID_SLOT=? AND ID_GOI=?");
        $now = date('Y-m-d H:i:s'); $act = $active ? 1 : 0;
        $stmt->bind_param('isii', $act, $now, $slotId, $packageId); return $stmt->execute();
    }

    // Branch mappings
    public function listMappingsForBranch(int $packageId, int $branchId): array {
        $sql = "SELECT s.ID_SLOT, s.TEN_SLOT, s.SO_LUONG, s.BAT_BUOC, s.ACTIVE as SLOT_ACTIVE,
                       m.ID_MAP, m.ID_CN, m.ID_TRANG_PHUC, m.PRICE_ADJUSTMENT, m.ACTIVE as MAP_ACTIVE,
                       tp.TEN as TEN_TRANG_PHUC, tp.SIZE as SIZE_TRANG_PHUC, tp.TRANG_THAI
                FROM goi_dich_vu_trang_phuc_slot s
                LEFT JOIN goi_dich_vu_slot_branch_trang_phuc m ON m.ID_SLOT = s.ID_SLOT AND m.ID_CN = ?
                LEFT JOIN trang_phuc tp ON tp.ID_TRANG_PHUC = m.ID_TRANG_PHUC
                WHERE s.ID_GOI=?
                ORDER BY s.ID_SLOT ASC, tp.TEN";
        $stmt = $this->conn->prepare($sql); $stmt->bind_param('ii',$branchId,$packageId); $stmt->execute();
        $res = $stmt->get_result(); $rows=[]; while($r=$res->fetch_assoc()){ $rows[]=$r; } return $rows;
    }

    /**
     * Replace mappings for a slot in a branch. Items format:
     * [ ['ID_TRANG_PHUC'=>int, 'PRICE_ADJUSTMENT'=>int|null, 'ACTIVE'=>bool], ... ]
     */
    public function setMappings(int $slotId, int $branchId, array $items): bool {
        $this->conn->begin_transaction();
        try {
            $del = $this->conn->prepare("DELETE FROM goi_dich_vu_slot_branch_trang_phuc WHERE ID_SLOT=? AND ID_CN=?");
            $del->bind_param('ii',$slotId,$branchId); if(!$del->execute()) throw new \RuntimeException('Delete old mappings failed');
            if (!empty($items)) {
                $ins = $this->conn->prepare("INSERT INTO goi_dich_vu_slot_branch_trang_phuc (ID_SLOT, ID_CN, ID_TRANG_PHUC, PRICE_ADJUSTMENT, ACTIVE, CREATED_AT, UPDATED_AT) VALUES (?,?,?,?,?, ?, ?)");
                $now = date('Y-m-d H:i:s');
                foreach($items as $it){
                    if(!isset($it['ID_TRANG_PHUC'])) continue;
                    $idTp = (int)$it['ID_TRANG_PHUC'];
                    $adjVar  = isset($it['PRICE_ADJUSTMENT']) ? (int)$it['PRICE_ADJUSTMENT'] : null;
                    $actVar  = !empty($it['ACTIVE']) ? 1 : 1; // default active
                    $ins->bind_param('iiiiiss', $slotId, $branchId, $idTp, $adjVar, $actVar, $now, $now);
                    if(!$ins->execute()) throw new \RuntimeException('Insert mapping failed');
                }
            }
            $this->conn->commit(); return true;
        } catch(\Throwable $e){ $this->conn->rollback(); return false; }
    }

    public function countMissingRequiredMappingsForBranch(int $packageId, int $branchId): int {
        $sql = "SELECT COUNT(*) AS missing
                FROM goi_dich_vu_trang_phuc_slot s
                LEFT JOIN (
                    SELECT ID_SLOT FROM goi_dich_vu_slot_branch_trang_phuc WHERE ID_CN=? AND ACTIVE=1 GROUP BY ID_SLOT
                ) x ON x.ID_SLOT = s.ID_SLOT
                WHERE s.ID_GOI=? AND s.BAT_BUOC=1 AND s.ACTIVE=1 AND x.ID_SLOT IS NULL";
        $stmt = $this->conn->prepare($sql); $stmt->bind_param('ii',$branchId,$packageId); $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc(); return (int)($row['missing'] ?? 0);
    }
}
