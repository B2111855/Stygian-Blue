<?php
namespace App\Repositories;

class CostumePackageMasterRepository
{
    private \mysqli $conn;

    public function __construct(\mysqli $conn)
    {
        $this->conn = $conn;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->conn->prepare("DELETE FROM goi_trang_phuc_master WHERE ID_GOI = ?");
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }

    public function create(string $name, ?string $description, int $branchId, int $discountPercent = 0): ?int
    {
        $discountPercent = max(0, min(100, (int)$discountPercent));
        // Try insert with DISCOUNT_PERCENT column (new schema). Fallback to old schema if column missing.
        $sqlNew = "INSERT INTO goi_trang_phuc_master (TEN_GOI, MO_TA, ID_CN_OWNER, DISCOUNT_PERCENT) VALUES (?,?,?,?)";
        $stmt = $this->conn->prepare($sqlNew);
        if ($stmt) {
            $stmt->bind_param('ssii', $name, $description, $branchId, $discountPercent);
            if ($stmt->execute()) {
                return $this->conn->insert_id;
            }
        }
        // Fallback (old schema without DISCOUNT_PERCENT)
        $sqlOld = "INSERT INTO goi_trang_phuc_master (TEN_GOI, MO_TA, ID_CN_OWNER) VALUES (?,?,?)";
        $stmtOld = $this->conn->prepare($sqlOld);
        if (!$stmtOld) {
            return null;
        }
        $stmtOld->bind_param('ssi', $name, $description, $branchId);
        if (!$stmtOld->execute()) {
            return null;
        }
        return $this->conn->insert_id;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM goi_trang_phuc_master WHERE ID_GOI = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) return null;
        $res = $stmt->get_result();
        return $res->fetch_assoc() ?: null;
    }

    public function listAll(?int $branchId = null, ?string $search = null, int $limit = 20, int $offset = 0, ?string $status = null): array
    {
        $where = [];
        $params = [];
        $types = '';

        if ($branchId !== null) {
            $where[] = 'ID_CN_OWNER = ?';
            $types .= 'i';
            $params[] = $branchId;
        }
        if ($search !== null && $search !== '') {
            $where[] = 'TEN_GOI LIKE ?';
            $types .= 's';
            $params[] = '%' . $search . '%';
        }
        if ($status !== null && ($status === 'active' || $status === 'inactive')) {
            $where[] = 'TRANG_THAI = ?';
            $types .= 's';
            $params[] = $status;
        }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = "SELECT * FROM goi_trang_phuc_master $whereSql ORDER BY ID_GOI DESC LIMIT ? OFFSET ?";
        $types .= 'ii';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }

    public function countAll(?int $branchId = null, ?string $search = null, ?string $status = null): int
    {
        $where = [];
        $params = [];
        $types = '';
        if ($branchId !== null) {
            $where[] = 'ID_CN_OWNER = ?';
            $types .= 'i';
            $params[] = $branchId;
        }
        if ($search !== null && $search !== '') {
            $where[] = 'TEN_GOI LIKE ?';
            $types .= 's';
            $params[] = '%' . $search . '%';
        }
        if ($status !== null && ($status === 'active' || $status === 'inactive')) {
            $where[] = 'TRANG_THAI = ?';
            $types .= 's';
            $params[] = $status;
        }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $sql = "SELECT COUNT(*) AS total FROM goi_trang_phuc_master $whereSql";
        $stmt = $this->conn->prepare($sql);
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        return (int)($row['total'] ?? 0);
    }
    /**
     * Update status of a costume package (active/inactive)
     */
    public function updateStatus(int $id, string $status): bool
    {
        $status = ($status === 'inactive') ? 'inactive' : 'active';
        $stmt = $this->conn->prepare("UPDATE goi_trang_phuc_master SET TRANG_THAI = ? WHERE ID_GOI = ?");
        if (!$stmt) return false;
        $stmt->bind_param('si', $status, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
