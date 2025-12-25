<?php

namespace App\Repositories;

use mysqli;
use RuntimeException;

class CostumeGroupRepository
{
    private mysqli $connection;

    public function __construct(mysqli $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Lấy tất cả nhóm trang phục (có thể lọc theo trạng thái)
     * 
     * @param string|null $status Filter by status: 'active', 'inactive', or null for all
     * @param string $orderBy Order by column: 'THU_TU', 'TEN_NHOM', 'CREATED_AT'
     * @return array<int, array> Array of costume groups
     */
    public function findAll(?string $status = 'active', string $orderBy = 'THU_TU'): array
    {
        $query = "SELECT ID_NHOM, TEN_NHOM, THU_TU, MO_TA, TRANG_THAI, 
                         CREATED_BY, CREATED_AT, UPDATED_BY, UPDATED_AT
                  FROM trang_phuc_nhom";
        
        if ($status) {
            $query .= " WHERE TRANG_THAI = ?";
        }
        
        $orderByMap = ['THU_TU' => 'THU_TU', 'TEN_NHOM' => 'TEN_NHOM', 'CREATED_AT' => 'CREATED_AT'];
        $orderColumn = $orderByMap[$orderBy] ?? 'THU_TU';
        $query .= " ORDER BY {$orderColumn} ASC";
        
        $stmt = $this->connection->prepare($query);
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        if ($status) {
            $stmt->bind_param('s', $status);
        }
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $groups = [];
        
        while ($row = $result->fetch_assoc()) {
            $groups[] = $row;
        }
        
        $stmt->close();
        return $groups;
    }

    /**
     * Lấy một nhóm trang phục theo ID
     * 
     * @param int $id ID của nhóm
     * @return array|null Costume group data or null if not found
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->connection->prepare(
            "SELECT ID_NHOM, TEN_NHOM, THU_TU, MO_TA, TRANG_THAI, 
                    CREATED_BY, CREATED_AT, UPDATED_BY, UPDATED_AT
             FROM trang_phuc_nhom WHERE ID_NHOM = ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('i', $id);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $group = $result->fetch_assoc();
        
        $stmt->close();
        return $group;
    }

    /**
     * Tạo nhóm trang phục mới
     * 
     * @param string $name Tên nhóm
     * @param string|null $description Mô tả nhóm
     * @param int $order Thứ tự hiển thị
     * @param string $createdBy ID người tạo (user ID)
     * @return int ID của nhóm vừa tạo
     */
    public function create(string $name, ?string $description = null, int $order = 0, string $createdBy = ''): int
    {
        $stmt = $this->connection->prepare(
            "INSERT INTO trang_phuc_nhom (TEN_NHOM, MO_TA, THU_TU, TRANG_THAI, CREATED_BY, CREATED_AT)
             VALUES (?, ?, ?, 'active', ?, NOW())"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('ssii', $name, $description, $order, $createdBy);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $stmt->error);
        }
        
        $id = $this->connection->insert_id;
        $stmt->close();
        
        return $id;
    }

    /**
     * Cập nhật nhóm trang phục
     * 
     * @param int $id ID nhóm
     * @param string $name Tên nhóm
     * @param string|null $description Mô tả
     * @param int $order Thứ tự
     * @param string $updatedBy ID người cập nhật
     * @return bool True if updated successfully
     */
    public function update(int $id, string $name, ?string $description = null, int $order = 0, string $updatedBy = ''): bool
    {
        $stmt = $this->connection->prepare(
            "UPDATE trang_phuc_nhom 
             SET TEN_NHOM = ?, MO_TA = ?, THU_TU = ?, UPDATED_BY = ?, UPDATED_AT = NOW()
             WHERE ID_NHOM = ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('ssiis', $name, $description, $order, $updatedBy, $id);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $stmt->error);
        }
        
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        return $affected > 0;
    }

    /**
     * Cập nhật trạng thái nhóm trang phục
     * 
     * @param int $id ID nhóm
     * @param string $status Trạng thái: 'active' hoặc 'inactive'
     * @param string $updatedBy ID người cập nhật
     * @return bool True if updated successfully
     */
    public function updateStatus(int $id, string $status, string $updatedBy = ''): bool
    {
        if (!in_array($status, ['active', 'inactive'])) {
            throw new RuntimeException('Invalid status: ' . $status);
        }
        
        $stmt = $this->connection->prepare(
            "UPDATE trang_phuc_nhom 
             SET TRANG_THAI = ?, UPDATED_BY = ?, UPDATED_AT = NOW()
             WHERE ID_NHOM = ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('ssi', $status, $updatedBy, $id);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $stmt->error);
        }
        
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        return $affected > 0;
    }

    /**
     * Xóa nhóm trang phục (soft delete - chỉ set status thành inactive)
     * 
     * @param int $id ID nhóm
     * @param string $updatedBy ID người xóa
     * @return bool True if deleted successfully
     */
    public function delete(int $id, string $updatedBy = ''): bool
    {
        return $this->updateStatus($id, 'inactive', $updatedBy);
    }

    /**
     * Đếm số lượng trang phục trong một nhóm
     * 
     * @param int $groupId ID nhóm
     * @return int Số lượng trang phục
     */
    public function countCostumes(int $groupId): int
    {
        $stmt = $this->connection->prepare(
            "SELECT COUNT(*) as cnt FROM trang_phuc t
             JOIN trang_phuc_loai tl ON t.ID_LOAI = tl.ID_LOAI
             WHERE tl.ID_NHOM = ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('i', $groupId);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $count = (int)($row['cnt'] ?? 0);
        
        $stmt->close();
        return $count;
    }

    /**
     * Đếm số loại trang phục trong một nhóm
     * 
     * @param int $groupId ID nhóm
     * @return int Số lượng loại
     */
    public function countTypes(int $groupId): int
    {
        $stmt = $this->connection->prepare(
            "SELECT COUNT(*) as cnt FROM trang_phuc_loai WHERE ID_NHOM = ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('i', $groupId);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $count = (int)($row['cnt'] ?? 0);
        
        $stmt->close();
        return $count;
    }

    /**
     * Kiểm tra có thể xóa nhóm hay không (nếu chưa có trang phục nào)
     * 
     * @param int $id ID nhóm
     * @return array Array with keys: 'can_delete' (bool), 'reason' (string|null)
     */
    public function canDelete(int $id): array
    {
        $costumeCount = $this->countCostumes($id);
        
        if ($costumeCount > 0) {
            return [
                'can_delete' => false,
                'reason' => "Nhóm này vẫn có {$costumeCount} trang phục. Vui lòng xóa hết trang phục trước."
            ];
        }
        
        return ['can_delete' => true, 'reason' => null];
    }

    /**
     * Lấy tất cả nhóm kèm thông tin số loại và số trang phục
     * 
     * @param string|null $status Filter by status
     * @return array Array of groups with count info
     */
    public function findAllWithCounts(?string $status = 'active'): array
    {
        $groups = $this->findAll($status);
        
        foreach ($groups as &$group) {
            $group['type_count'] = $this->countTypes((int)$group['ID_NHOM']);
            $group['costume_count'] = $this->countCostumes((int)$group['ID_NHOM']);
        }
        
        return $groups;
    }

    /**
     * Tìm kiếm nhóm theo tên
     * 
     * @param string $search Từ khóa tìm kiếm
     * @param string|null $status Filter by status
     * @return array Array of matching groups
     */
    public function search(string $search, ?string $status = 'active'): array
    {
        $searchTerm = '%' . $search . '%';
        
        $query = "SELECT ID_NHOM, TEN_NHOM, THU_TU, MO_TA, TRANG_THAI, 
                         CREATED_BY, CREATED_AT, UPDATED_BY, UPDATED_AT
                  FROM trang_phuc_nhom
                  WHERE TEN_NHOM LIKE ?";
        
        if ($status) {
            $query .= " AND TRANG_THAI = ?";
        }
        
        $query .= " ORDER BY THU_TU ASC";
        
        $stmt = $this->connection->prepare($query);
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        if ($status) {
            $stmt->bind_param('ss', $searchTerm, $status);
        } else {
            $stmt->bind_param('s', $searchTerm);
        }
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $groups = [];
        
        while ($row = $result->fetch_assoc()) {
            $groups[] = $row;
        }
        
        $stmt->close();
        return $groups;
    }

    /**
     * Cập nhật thứ tự hiển thị của nhóm
     * 
     * @param int $id ID nhóm
     * @param int $order Thứ tự mới
     * @param string $updatedBy ID người cập nhật
     * @return bool True if updated successfully
     */
    public function updateOrder(int $id, int $order, string $updatedBy = ''): bool
    {
        $stmt = $this->connection->prepare(
            "UPDATE trang_phuc_nhom 
             SET THU_TU = ?, UPDATED_BY = ?, UPDATED_AT = NOW()
             WHERE ID_NHOM = ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('iis', $order, $updatedBy, $id);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $stmt->error);
        }
        
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        return $affected > 0;
    }
}
