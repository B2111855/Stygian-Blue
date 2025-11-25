<?php
namespace App\Repositories;

class ServiceCategoryRepository {
    private \mysqli $conn;
    
    public function __construct(\mysqli $conn) {
        $this->conn = $conn;
    }
    
    public function getAll(): array {
        $sql = "SELECT * FROM danh_muc_dich_vu WHERE TRANG_THAI='active' ORDER BY THU_TU ASC, TEN_DANH_MUC ASC";
        $result = $this->conn->query($sql);
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        return $categories;
    }
    
    public function findById(int $id): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM danh_muc_dich_vu WHERE ID_DANH_MUC=? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ?: null;
    }
    
    public function create(string $name, string $description = '', int $order = 0): array {
        $stmt = $this->conn->prepare("INSERT INTO danh_muc_dich_vu (TEN_DANH_MUC, MO_TA, THU_TU) VALUES (?,?,?)");
        $stmt->bind_param('ssi', $name, $description, $order);
        $stmt->execute();
        $id = $this->conn->insert_id;
        return $this->findById($id);
    }
}

class ServiceTagRepository {
    private \mysqli $conn;
    
    public function __construct(\mysqli $conn) {
        $this->conn = $conn;
    }
    
    public function getAll(): array {
        $sql = "SELECT * FROM the_dich_vu ORDER BY TEN_THE ASC";
        $result = $this->conn->query($sql);
        $tags = [];
        while ($row = $result->fetch_assoc()) {
            $tags[] = $row;
        }
        return $tags;
    }
    
    public function findById(int $id): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM the_dich_vu WHERE ID_THE=? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ?: null;
    }
    
    public function getTagsForService(int $serviceId): array {
        $sql = "SELECT t.* FROM the_dich_vu t 
                INNER JOIN dich_vu_the dvt ON t.ID_THE = dvt.ID_THE 
                WHERE dvt.ID_DV = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $serviceId);
        $stmt->execute();
        $result = $stmt->get_result();
        $tags = [];
        while ($row = $result->fetch_assoc()) {
            $tags[] = $row;
        }
        return $tags;
    }
    
    public function setTagsForService(int $serviceId, array $tagIds): bool {
        // Remove existing tags
        $stmt = $this->conn->prepare("DELETE FROM dich_vu_the WHERE ID_DV=?");
        $stmt->bind_param('i', $serviceId);
        $stmt->execute();
        
        // Add new tags
        if (!empty($tagIds)) {
            $stmt = $this->conn->prepare("INSERT INTO dich_vu_the (ID_DV, ID_THE) VALUES (?,?)");
            foreach ($tagIds as $tagId) {
                $stmt->bind_param('ii', $serviceId, $tagId);
                $stmt->execute();
            }
        }
        
        return true;
    }
    
    public function create(string $name, string $color = '#6B7280'): array {
        $stmt = $this->conn->prepare("INSERT INTO the_dich_vu (TEN_THE, MAU_SAC) VALUES (?,?)");
        $stmt->bind_param('ss', $name, $color);
        $stmt->execute();
        $id = $this->conn->insert_id;
        return $this->findById($id);
    }
}
