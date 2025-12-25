<?php

namespace App\Repositories;

use mysqli;
use RuntimeException;

class CostumeTypeRepository
{
    private mysqli $connection;

    public function __construct(mysqli $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Lấy tất cả loại trang phục (có thể lọc theo trạng thái)
     * 
     * @param string|null $status Filter by status: 'active', 'inactive', or null for all
     * @param string $orderBy Order by column: 'THU_TU', 'TEN_LOAI', 'ID_NHOM'
     * @return array<int, array> Array of costume types
     */
    public function findAll(?string $status = 'active', string $orderBy = 'THU_TU'): array
    {
        $query = "SELECT ID_LOAI, TEN_LOAI, ID_NHOM, THU_TU, TRANG_THAI,
                         CREATED_BY, CREATED_AT, UPDATED_BY, UPDATED_AT
                  FROM trang_phuc_loai";
        
        if ($status) {
            $query .= " WHERE TRANG_THAI = ?";
        }
        
        $orderByMap = [
            'THU_TU' => 'THU_TU',
            'TEN_LOAI' => 'TEN_LOAI',
            'ID_NHOM' => 'ID_NHOM',
            'CREATED_AT' => 'CREATED_AT'
        ];
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
        $types = [];
        
        while ($row = $result->fetch_assoc()) {
            $types[] = $row;
        }
        
        $stmt->close();
        return $types;
    }

    /**
     * Lấy một loại trang phục theo ID
     * 
     * @param int $id ID của loại
     * @return array|null Costume type data or null if not found
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->connection->prepare(
            "SELECT ID_LOAI, TEN_LOAI, ID_NHOM, THU_TU, TRANG_THAI,
                    CREATED_BY, CREATED_AT, UPDATED_BY, UPDATED_AT
             FROM trang_phuc_loai WHERE ID_LOAI = ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('i', $id);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $type = $result->fetch_assoc();
        
        $stmt->close();
        return $type;
    }

    /**
     * Lấy tất cả loại trang phục trong một nhóm
     * 
     * @param int $groupId ID nhóm trang phục
     * @param string|null $status Filter by status
     * @return array Array of costume types in group
     */
    public function findByGroup(int $groupId, ?string $status = 'active'): array
    {
        $query = "SELECT ID_LOAI, TEN_LOAI, ID_NHOM, THU_TU, TRANG_THAI,
                         CREATED_BY, CREATED_AT, UPDATED_BY, UPDATED_AT
                  FROM trang_phuc_loai
                  WHERE ID_NHOM = ?";
        
        if ($status) {
            $query .= " AND TRANG_THAI = ?";
        }
        
        $query .= " ORDER BY THU_TU ASC";
        
        $stmt = $this->connection->prepare($query);
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        if ($status) {
            $stmt->bind_param('is', $groupId, $status);
        } else {
            $stmt->bind_param('i', $groupId);
        }
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $types = [];
        
        while ($row = $result->fetch_assoc()) {
            $types[] = $row;
        }
        
        $stmt->close();
        return $types;
    }

    /**
     * Tạo loại trang phục mới
     * 
     * @param string $name Tên loại
     * @param int $groupId ID nhóm cha
     * @param int $order Thứ tự hiển thị
     * @param string $createdBy ID người tạo
     * @return int ID của loại vừa tạo
     */
    public function create(string $name, int $groupId, int $order = 0, string $createdBy = ''): int
    {
        // Kiểm tra nhóm tồn tại
        $groupStmt = $this->connection->prepare(
            "SELECT ID_NHOM FROM trang_phuc_nhom WHERE ID_NHOM = ?"
        );
        if (!$groupStmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $groupStmt->bind_param('i', $groupId);
        if (!$groupStmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $groupStmt->error);
        }
        
        if ($groupStmt->get_result()->num_rows === 0) {
            $groupStmt->close();
            throw new RuntimeException('Nhóm trang phục không tồn tại');
        }
        $groupStmt->close();
        
        // Tạo loại mới
        $stmt = $this->connection->prepare(
            "INSERT INTO trang_phuc_loai (TEN_LOAI, ID_NHOM, THU_TU, TRANG_THAI, CREATED_BY, CREATED_AT)
             VALUES (?, ?, ?, 'active', ?, NOW())"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('sii', $name, $groupId, $order, $createdBy);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $stmt->error);
        }
        
        $id = $this->connection->insert_id;
        $stmt->close();
        
        return $id;
    }

    /**
     * Cập nhật loại trang phục
     * 
     * @param int $id ID loại
     * @param string $name Tên loại
     * @param int $groupId ID nhóm cha
     * @param int $order Thứ tự
     * @param string $updatedBy ID người cập nhật
     * @return bool True if updated successfully
     */
    public function update(int $id, string $name, int $groupId, int $order = 0, string $updatedBy = ''): bool
    {
        // Kiểm tra nhóm tồn tại
        $groupStmt = $this->connection->prepare(
            "SELECT ID_NHOM FROM trang_phuc_nhom WHERE ID_NHOM = ?"
        );
        if (!$groupStmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $groupStmt->bind_param('i', $groupId);
        if (!$groupStmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $groupStmt->error);
        }
        
        if ($groupStmt->get_result()->num_rows === 0) {
            $groupStmt->close();
            throw new RuntimeException('Nhóm trang phục không tồn tại');
        }
        $groupStmt->close();
        
        // Cập nhật loại
        $stmt = $this->connection->prepare(
            "UPDATE trang_phuc_loai 
             SET TEN_LOAI = ?, ID_NHOM = ?, THU_TU = ?, UPDATED_BY = ?, UPDATED_AT = NOW()
             WHERE ID_LOAI = ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('siis', $name, $groupId, $order, $updatedBy, $id);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $stmt->error);
        }
        
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        return $affected > 0;
    }

    /**
     * Cập nhật trạng thái loại trang phục
     * 
     * @param int $id ID loại
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
            "UPDATE trang_phuc_loai 
             SET TRANG_THAI = ?, UPDATED_BY = ?, UPDATED_AT = NOW()
             WHERE ID_LOAI = ?"
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
     * Xóa loại trang phục (soft delete - chỉ set status thành inactive)
     * 
     * @param int $id ID loại
     * @param string $updatedBy ID người xóa
     * @return bool True if deleted successfully
     */
    public function delete(int $id, string $updatedBy = ''): bool
    {
        return $this->updateStatus($id, 'inactive', $updatedBy);
    }

    /**
     * Đếm số lượng trang phục trong một loại
     * 
     * @param int $typeId ID loại
     * @return int Số lượng trang phục
     */
    public function countCostumes(int $typeId): int
    {
        $stmt = $this->connection->prepare(
            "SELECT COUNT(*) as cnt FROM trang_phuc WHERE ID_LOAI = ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('i', $typeId);
        
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
     * Kiểm tra có thể xóa loại hay không (nếu chưa có trang phục nào)
     * 
     * @param int $id ID loại
     * @return array Array with keys: 'can_delete' (bool), 'reason' (string|null)
     */
    public function canDelete(int $id): array
    {
        $costumeCount = $this->countCostumes($id);
        
        if ($costumeCount > 0) {
            return [
                'can_delete' => false,
                'reason' => "Loại này vẫn có {$costumeCount} trang phục. Vui lòng xóa hết trang phục trước."
            ];
        }
        
        return ['can_delete' => true, 'reason' => null];
    }

    /**
     * Lấy loại trang phục kèm thông tin nhóm và số lượng trang phục
     * 
     * @param int $id ID loại
     * @return array|null Type data with group info and costume count
     */
    public function findByIdWithDetails(int $id): ?array
    {
        $type = $this->findById($id);
        
        if (!$type) {
            return null;
        }
        
        // Thêm thông tin nhóm
        $groupStmt = $this->connection->prepare(
            "SELECT ID_NHOM, TEN_NHOM FROM trang_phuc_nhom WHERE ID_NHOM = ?"
        );
        if ($groupStmt) {
            $groupStmt->bind_param('i', $type['ID_NHOM']);
            $groupStmt->execute();
            $groupResult = $groupStmt->get_result()->fetch_assoc();
            $type['group'] = $groupResult;
            $groupStmt->close();
        }
        
        // Thêm số lượng trang phục
        $type['costume_count'] = $this->countCostumes($id);
        
        return $type;
    }

    /**
     * Lấy tất cả loại trong nhóm kèm số lượng trang phục
     * 
     * @param int $groupId ID nhóm
     * @param string|null $status Filter by status
     * @return array Array of types with costume counts
     */
    public function findByGroupWithCounts(int $groupId, ?string $status = 'active'): array
    {
        $types = $this->findByGroup($groupId, $status);
        
        foreach ($types as &$type) {
            $type['costume_count'] = $this->countCostumes((int)$type['ID_LOAI']);
        }
        
        return $types;
    }

    /**
     * Tìm kiếm loại theo tên
     * 
     * @param string $search Từ khóa tìm kiếm
     * @param int|null $groupId Lọc theo nhóm (optional)
     * @param string|null $status Filter by status
     * @return array Array of matching types
     */
    public function search(string $search, ?int $groupId = null, ?string $status = 'active'): array
    {
        $searchTerm = '%' . $search . '%';
        
        $query = "SELECT ID_LOAI, TEN_LOAI, ID_NHOM, THU_TU, TRANG_THAI,
                         CREATED_BY, CREATED_AT, UPDATED_BY, UPDATED_AT
                  FROM trang_phuc_loai
                  WHERE TEN_LOAI LIKE ?";
        
        if ($groupId !== null) {
            $query .= " AND ID_NHOM = ?";
        }
        
        if ($status) {
            $query .= " AND TRANG_THAI = ?";
        }
        
        $query .= " ORDER BY THU_TU ASC";
        
        $stmt = $this->connection->prepare($query);
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $params = [$searchTerm];
        $types = 's';
        
        if ($groupId !== null) {
            $params[] = $groupId;
            $types .= 'i';
        }
        
        if ($status) {
            $params[] = $status;
            $types .= 's';
        }
        
        $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $types = [];
        
        while ($row = $result->fetch_assoc()) {
            $types[] = $row;
        }
        
        $stmt->close();
        return $types;
    }

    /**
     * Cập nhật thứ tự hiển thị của loại
     * 
     * @param int $id ID loại
     * @param int $order Thứ tự mới
     * @param string $updatedBy ID người cập nhật
     * @return bool True if updated successfully
     */
    public function updateOrder(int $id, int $order, string $updatedBy = ''): bool
    {
        $stmt = $this->connection->prepare(
            "UPDATE trang_phuc_loai 
             SET THU_TU = ?, UPDATED_BY = ?, UPDATED_AT = NOW()
             WHERE ID_LOAI = ?"
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

    /**
     * Đếm tổng số loại trong một nhóm
     * 
     * @param int $groupId ID nhóm
     * @return int Số lượng loại
     */
    public function countByGroup(int $groupId): int
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
     * Di chuyển loại trang phục sang nhóm khác
     * 
     * @param int $typeId ID loại
     * @param int $newGroupId ID nhóm đích
     * @param string $updatedBy ID người cập nhật
     * @return bool True if moved successfully
     */
    public function moveToGroup(int $typeId, int $newGroupId, string $updatedBy = ''): bool
    {
        // Kiểm tra nhóm đích tồn tại
        $groupStmt = $this->connection->prepare(
            "SELECT ID_NHOM FROM trang_phuc_nhom WHERE ID_NHOM = ?"
        );
        if (!$groupStmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $groupStmt->bind_param('i', $newGroupId);
        if (!$groupStmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $groupStmt->error);
        }
        
        if ($groupStmt->get_result()->num_rows === 0) {
            $groupStmt->close();
            throw new RuntimeException('Nhóm đích không tồn tại');
        }
        $groupStmt->close();
        
        // Di chuyển loại
        $stmt = $this->connection->prepare(
            "UPDATE trang_phuc_loai 
             SET ID_NHOM = ?, UPDATED_BY = ?, UPDATED_AT = NOW()
             WHERE ID_LOAI = ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('iis', $newGroupId, $updatedBy, $typeId);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $stmt->error);
        }
        
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        return $affected > 0;
    }
}
