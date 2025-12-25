<?php

namespace App\Repositories;

use mysqli;
use mysqli_stmt;
use RuntimeException;

class CostumePackageMasterRepository
{
    private mysqli $connection;

    public function __construct(mysqli $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Lấy tất cả gói trang phục (có thể lọc theo trạng thái và scope)
     * 
     * @param string|null $status Filter by status: 'active', 'inactive'
     * @param string|null $scopeType Filter by scope: 'global', 'local'
     * @param int|null $branchId ID chi nhánh (bắt buộc khi lọc scope local)
     * @param string $orderBy Order by column: 'TEN_GOI', 'CREATED_AT', 'DISCOUNT_PERCENT'
     * @return array<int, array> Array of packages
     */
    public function findAll(?string $status = 'active', ?string $scopeType = null, ?int $branchId = null, string $orderBy = 'TEN_GOI'): array
    {
        $query = "SELECT ID_GOI, TEN_GOI, MO_TA, DISCOUNT_PERCENT, ID_CN_OWNER,
                         TRANG_THAI, CREATED_AT, UPDATED_AT
                  FROM goi_trang_phuc_master
                  WHERE 1=1";
        
        $params = [];
        $types = '';
        
        if ($status) {
            $query .= " AND TRANG_THAI = ?";
            $params[] = $status;
            $types .= 's';
        }
        
        if ($branchId !== null) {
            $query .= " AND ID_CN_OWNER = ?";
            $params[] = $branchId;
            $types .= 'i';
        }
        
        $orderByMap = [
            'TEN_GOI' => 'TEN_GOI',
            'CREATED_AT' => 'CREATED_AT',
            'DISCOUNT_PERCENT' => 'DISCOUNT_PERCENT'
        ];
        $orderColumn = $orderByMap[$orderBy] ?? 'TEN_GOI';
        $query .= " ORDER BY {$orderColumn} ASC";
        
        $stmt = $this->connection->prepare($query);
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        if (!empty($types)) {
            $this->bindParams($stmt, $types, $params);
        }
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $result = $stmt->get_result();
        $packages = [];
        
        while ($row = $result->fetch_assoc()) {
            $packages[] = $row;
        }
        
        $stmt->close();
        return $packages;
    }

    /**
     * Lấy một gói trang phục theo ID
     * 
     * @param int $id ID của gói
     * @return array|null Package data or null if not found
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->connection->prepare(
            "SELECT ID_GOI, TEN_GOI, MO_TA, DISCOUNT_PERCENT, ID_CN_OWNER,
                    TRANG_THAI, CREATED_AT, UPDATED_AT
             FROM goi_trang_phuc_master WHERE ID_GOI = ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('i', $id);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $result = $stmt->get_result();
        $package = $result->fetch_assoc();
        
        $stmt->close();
        return $package;
    }

    /**
     * Alias for findById() - find a package by ID
     * 
     * @param int $id ID của gói
     * @return array|null Package data or null if not found
     */
    public function find(int $id): ?array
    {
        return $this->findById($id);
    }

    /**
     * Lấy các gói của một chi nhánh (global + local cho chi nhánh đó)
     * 
     * @param int $branchId ID chi nhánh
     * @param string|null $status Filter by status
     * @return array Array of packages
     */
    public function findByBranch(int $branchId, ?string $status = 'active'): array
    {
        $query = "SELECT ID_GOI, TEN_GOI, MO_TA, DISCOUNT_PERCENT, ID_CN_OWNER,
                         TRANG_THAI, CREATED_AT, UPDATED_AT
                  FROM goi_trang_phuc_master
                  WHERE ID_CN_OWNER = ?";
        
        if ($status) {
            $query .= " AND TRANG_THAI = ?";
        }
        
        $query .= " ORDER BY TEN_GOI ASC";
        
        $stmt = $this->connection->prepare($query);
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        if ($status) {
            $stmt->bind_param('is', $branchId, $status);
        } else {
            $stmt->bind_param('i', $branchId);
        }
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $result = $stmt->get_result();
        $packages = [];
        
        while ($row = $result->fetch_assoc()) {
            $packages[] = $row;
        }
        
        $stmt->close();
        return $packages;
    }

    /**
     * Tạo gói trang phục mới
     * 
     * @param string $name Tên gói
     * @param string|null $description Mô tả
     * @param int $discountPercent Phần trăm giảm giá (0-100)
     * @param string $scopeType Scope: 'global' hoặc 'local'
     * @param int|null $branchId ID chi nhánh (bắt buộc nếu scope là local)
     * @param string $createdBy ID người tạo
     * @return int ID gói vừa tạo
     */
    public function create(
        string|array $name,
        ?string $description = null,
        int $discountPercent = 0,
        string $scopeType = 'local',
        ?int $branchId = null,
        string $createdBy = ''
    ): int {
        // Allow array form from service layer
        if (is_array($name)) {
            $data = $name;
            $name = (string)($data['TEN_GOI'] ?? '');
            $description = $data['MO_TA'] ?? null;
            $discountPercent = (int)($data['DISCOUNT_PERCENT'] ?? 0);
            $branchId = isset($data['ID_CN_OWNER']) ? (int)$data['ID_CN_OWNER'] : null;
            $createdBy = (string)($data['CREATED_BY'] ?? '');
        }
        // Kiểm tra discount percentage hợp lệ
        if ($discountPercent < 0 || $discountPercent > 100) {
            throw new RuntimeException('Phần trăm giảm giá phải từ 0-100');
        }
        
        // Kiểm tra branch
        if ($branchId === null) {
            throw new RuntimeException('Chi nhánh sở hữu là bắt buộc');
        }
        
        $stmt = $this->connection->prepare(
            "INSERT INTO goi_trang_phuc_master (TEN_GOI, MO_TA, DISCOUNT_PERCENT, 
                                               ID_CN_OWNER, TRANG_THAI, CREATED_AT)
             VALUES (?, ?, ?, ?, 'active', NOW())"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('ssii', $name, $description, $discountPercent, $branchId);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $id = $this->connection->insert_id;
        $stmt->close();
        
        return $id;
    }

    /**
     * Cập nhật thông tin gói trang phục
     * 
     * @param int $id ID gói
     * @param string $name Tên gói
     * @param string|null $description Mô tả
     * @param int $discountPercent Phần trăm giảm giá
     * @param string $updatedBy ID người cập nhật
     * @return bool True if updated successfully
     */
    public function update(
        int $id,
        string|array $name,
        ?string $description = null,
        int $discountPercent = 0,
        string $updatedBy = ''
    ): bool {
        // Allow array form from service layer
        if (is_array($name)) {
            $data = $name;
            $name = (string)($data['TEN_GOI'] ?? '');
            $description = $data['MO_TA'] ?? null;
            $discountPercent = (int)($data['DISCOUNT_PERCENT'] ?? 0);
            $updatedBy = (string)($data['UPDATED_BY'] ?? '');
        }
        // Kiểm tra discount percentage hợp lệ
        if ($discountPercent < 0 || $discountPercent > 100) {
            throw new RuntimeException('Phần trăm giảm giá phải từ 0-100');
        }
        
        $stmt = $this->connection->prepare(
            "UPDATE goi_trang_phuc_master 
             SET TEN_GOI = ?, MO_TA = ?, DISCOUNT_PERCENT = ?, UPDATED_AT = NOW()
             WHERE ID_GOI = ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('ssii', $name, $description, $discountPercent, $id);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        return $affected > 0;
    }

    /**
     * Cập nhật trạng thái gói
     * 
     * @param int $id ID gói
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
            "UPDATE goi_trang_phuc_master 
             SET TRANG_THAI = ?, UPDATED_AT = NOW()
             WHERE ID_GOI = ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('si', $status, $id);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        return $affected > 0;
    }

    /**
     * Xóa gói trang phục (soft delete - set status thành inactive)
     * 
     * @param int $id ID gói
     * @param string $updatedBy ID người xóa
     * @return bool True if deleted successfully
     */
    public function delete(int $id, string $updatedBy = ''): bool
    {
        return $this->updateStatus($id, 'inactive', $updatedBy);
    }

    /**
     * Xóa gói trang phục hoàn toàn khỏi database (hard delete)
     * Chỉ nên sử dụng khi gói chưa có trang phục nào
     * 
     * @param int $id ID gói
     * @return bool True if deleted successfully
     */
    public function hardDelete(int $id): bool
    {
        // First check if package has any costumes
        $costumeCount = $this->countCostumes($id);
        if ($costumeCount > 0) {
            throw new RuntimeException('Không thể xóa gói đang có trang phục. Vui lòng xóa hết trang phục trước.');
        }

        $stmt = $this->connection->prepare(
            "DELETE FROM goi_trang_phuc_master WHERE ID_GOI = ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('i', $id);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        return $affected > 0;
    }

    /**
     * Cập nhật phần trăm giảm giá của gói
     * 
     * @param int $id ID gói
     * @param int $discountPercent Phần trăm giảm giá (0-100)
     * @param string $updatedBy ID người cập nhật
     * @return bool True if updated successfully
     */
    public function updateDiscount(int $id, int $discountPercent, string $updatedBy = ''): bool
    {
        if ($discountPercent < 0 || $discountPercent > 100) {
            throw new RuntimeException('Phần trăm giảm giá phải từ 0-100');
        }
        
        $stmt = $this->connection->prepare(
            "UPDATE goi_trang_phuc_master 
             SET DISCOUNT_PERCENT = ?, UPDATED_AT = NOW()
             WHERE ID_GOI = ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('ii', $discountPercent, $id);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        return $affected > 0;
    }

    /**
     * Đếm số trang phục trong gói
     * 
     * @param int $packageId ID gói
     * @return int Số lượng trang phục
     */
    public function countCostumes(int $packageId): int
    {
        $stmt = $this->connection->prepare(
            "SELECT COUNT(*) as cnt FROM goi_trang_phuc_chi_tiet WHERE ID_GOI = ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('i', $packageId);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $count = (int)($row['cnt'] ?? 0);
        
        $stmt->close();
        return $count;
    }

    /**
     * Đếm số trang phục bắt buộc trong gói
     * 
     * @param int $packageId ID gói
     * @return int Số lượng trang phục bắt buộc
     */
    public function countMandatoryCostumes(int $packageId): int
    {
        $stmt = $this->connection->prepare(
            "SELECT COUNT(*) as cnt FROM goi_trang_phuc_chi_tiet WHERE ID_GOI = ? AND BA_CHI_TIEU = 1"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('i', $packageId);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $count = (int)($row['cnt'] ?? 0);
        
        $stmt->close();
        return $count;
    }

    /**
     * Đếm số trang phục tùy chọn trong gói
     * 
     * @param int $packageId ID gói
     * @return int Số lượng trang phục tùy chọn
     */
    public function countOptionalCostumes(int $packageId): int
    {
        $stmt = $this->connection->prepare(
            "SELECT COUNT(*) as cnt FROM goi_trang_phuc_chi_tiet WHERE ID_GOI = ? AND BA_CHI_TIEU = 0"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('i', $packageId);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $count = (int)($row['cnt'] ?? 0);
        
        $stmt->close();
        return $count;
    }

    /**
     * Kiểm tra có thể xóa gói hay không (nếu chưa được sử dụng)
     * 
     * @param int $id ID gói
     * @return array Array with keys: 'can_delete' (bool), 'reason' (string|null)
     */
    public function canDelete(int $id): array
    {
        $costumeCount = $this->countCostumes($id);
        
        if ($costumeCount > 0) {
            return [
                'can_delete' => false,
                'reason' => "Gói này vẫn có {$costumeCount} trang phục. Vui lòng xóa hết trang phục trước."
            ];
        }
        // Không kiểm tra liên kết đơn thuê vì bảng don_thue_trang_phuc không lưu cột ID_GOI.
        // Nếu sau này có liên kết, cần cập nhật kiểm tra tại đây theo bảng/phép nối phù hợp.
        return ['can_delete' => true, 'reason' => null];
    }

    /**
     * Lấy gói kèm thông tin chi tiết (số trang phục, giá tiền)
     * 
     * @param int $id ID gói
     * @return array|null Package data with details
     */
    public function findByIdWithDetails(int $id): ?array
    {
        $package = $this->findById($id);
        
        if (!$package) {
            return null;
        }
        
        // Thêm thông tin số lượng trang phục
        $package['total_costumes'] = $this->countCostumes($id);
        $package['mandatory_costumes'] = $this->countMandatoryCostumes($id);
        $package['optional_costumes'] = $this->countOptionalCostumes($id);
        
        // Tính tổng giá (từ các trang phục trong gói)
        $stmt = $this->connection->prepare(
            "SELECT SUM(tp.GIA_THUE * gtp.SO_LUONG * (1 - gtp.DISCOUNT_PERCENT/100)) as total_price
             FROM goi_trang_phuc_chi_tiet gtp
             JOIN trang_phuc tp ON gtp.ID_TRANG_PHUC = tp.ID_TRANG_PHUC
             WHERE gtp.ID_GOI = ?"
        );
        
        if ($stmt) {
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $package['package_price'] = (int)($row['total_price'] ?? 0);
            }
            $stmt->close();
        }
        
        return $package;
    }

    /**
     * Tìm kiếm gói theo tên
     * 
     * @param string $search Từ khóa tìm kiếm
     * @param int|null $branchId ID chi nhánh (để lọc scope)
     * @param string|null $status Filter by status
     * @return array Array of matching packages
     */
    public function search(string $search, ?int $branchId = null, ?string $status = 'active'): array
    {
        $searchTerm = '%' . $search . '%';
        
        $query = "SELECT ID_GOI, TEN_GOI, MO_TA, DISCOUNT_PERCENT, ID_CN_OWNER,
                         TRANG_THAI, CREATED_AT, UPDATED_AT
                  FROM goi_trang_phuc_master
                  WHERE TEN_GOI LIKE ?";
        
        if ($branchId !== null) {
            $query .= " AND ID_CN_OWNER = ?";
        }
        
        if ($status) {
            $query .= " AND TRANG_THAI = ?";
        }
        
        $query .= " ORDER BY TEN_GOI ASC";
        
        $stmt = $this->connection->prepare($query);
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $params = [$searchTerm];
        $types = 's';
        
        if ($branchId !== null) {
            $params[] = $branchId;
            $types .= 'i';
        }
        
        if ($status) {
            $params[] = $status;
            $types .= 's';
        }
        
        $this->bindParams($stmt, $types, $params);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $result = $stmt->get_result();
        $packages = [];
        
        while ($row = $result->fetch_assoc()) {
            $packages[] = $row;
        }
        
        $stmt->close();
        return $packages;
    }

    /**
     * Lấy giá gói với discount đã tính
     * 
     * @param int $packageId ID gói
     * @return array Array with keys: 'base_price', 'discount_amount', 'final_price'
     */
    public function calculatePackagePrice(int $packageId): array
    {
        $stmt = $this->connection->prepare(
            "SELECT 
                SUM(tp.GIA_THUE * gtp.SO_LUONG) as base_price,
                SUM(tp.GIA_THUE * gtp.SO_LUONG * gtp.DISCOUNT_PERCENT / 100) as item_discount,
                m.DISCOUNT_PERCENT as package_discount
             FROM goi_trang_phuc_chi_tiet gtp
             JOIN trang_phuc tp ON gtp.ID_TRANG_PHUC = tp.ID_TRANG_PHUC
             JOIN goi_trang_phuc_master m ON gtp.ID_GOI = m.ID_GOI
             WHERE gtp.ID_GOI = ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('i', $packageId);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        $basePrice = (int)($row['base_price'] ?? 0);
        $itemDiscount = (int)($row['item_discount'] ?? 0);
        $packageDiscountPercent = (int)($row['package_discount'] ?? 0);
        
        $priceAfterItemDiscount = $basePrice - $itemDiscount;
        $packageDiscountAmount = (int)($priceAfterItemDiscount * $packageDiscountPercent / 100);
        $finalPrice = $priceAfterItemDiscount - $packageDiscountAmount;
        
        return [
            'base_price' => $basePrice,
            'item_discount' => $itemDiscount,
            'package_discount_percent' => $packageDiscountPercent,
            'package_discount_amount' => $packageDiscountAmount,
            'total_discount' => $itemDiscount + $packageDiscountAmount,
            'final_price' => max(0, $finalPrice)
        ];
    }

    /**
     * Di chuyển gói sang chi nhánh khác (chỉ cho gói local)
     * 
     * @param int $packageId ID gói
     * @param int $newBranchId ID chi nhánh đích
     * @param string $updatedBy ID người cập nhật
     * @return bool True if moved successfully
     */
    public function moveToBranch(int $packageId, int $newBranchId, string $updatedBy = ''): bool
    {
        $package = $this->findById($packageId);
        
        if (!$package) {
            throw new RuntimeException('Gói không tồn tại');
        }
        
        $stmt = $this->connection->prepare(
            "UPDATE goi_trang_phuc_master 
             SET ID_CN_OWNER = ?, UPDATED_AT = NOW()
             WHERE ID_GOI = ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('ii', $newBranchId, $packageId);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        return $affected > 0;
    }

    /**
     * Count total packages with optional filters
     * 
     * @param int|null $branchId Filter by branch ID (not currently used)
     * @param string|null $search Search by package name or description
     * @param string|null $status Filter by status: 'active', 'inactive'
     * @return int Total count of packages
     */
    public function countAll(?int $branchId = null, ?string $search = null, ?string $status = null): int
    {
        $query = "SELECT COUNT(*) as total FROM goi_trang_phuc_master WHERE 1=1";
        $params = [];
        $types = '';
        
        // Filter by branch if provided
        if ($branchId !== null) {
            $query .= " AND ID_CN_OWNER = ?";
            $params[] = $branchId;
            $types .= 'i';
        }
        
        if ($search) {
            $query .= " AND (TEN_GOI LIKE ? OR MO_TA LIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $types .= 'ss';
        }
        
        if ($status) {
            $query .= " AND TRANG_THAI = ?";
            $params[] = $status;
            $types .= 's';
        }
        
        $stmt = $this->connection->prepare($query);
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return (int)($row['total'] ?? 0);
    }

    /**
     * List all packages with pagination and optional filters
     * 
     * @param int|null $branchId Filter by branch ID (not currently used)
     * @param string|null $search Search by package name or description
     * @param int $limit Items per page
     * @param int $offset Pagination offset
     * @param string|null $status Filter by status: 'active', 'inactive'
     * @return array<int, array> Array of packages
     */
    public function listAll(?int $branchId = null, ?string $search = null, int $limit = 10, int $offset = 0, ?string $status = null): array
    {
        $query = "SELECT ID_GOI, TEN_GOI, MO_TA, DISCOUNT_PERCENT, ID_CN_OWNER,
                         TRANG_THAI, CREATED_AT, UPDATED_AT
                  FROM goi_trang_phuc_master
                  WHERE 1=1";
        $params = [];
        $types = '';
        
        // Filter by branch if provided
        if ($branchId !== null) {
            $query .= " AND ID_CN_OWNER = ?";
            $params[] = $branchId;
            $types .= 'i';
        }
        
        if ($search) {
            $query .= " AND (TEN_GOI LIKE ? OR MO_TA LIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $types .= 'ss';
        }
        
        if ($status) {
            $query .= " AND TRANG_THAI = ?";
            $params[] = $status;
            $types .= 's';
        }
        
        $query .= " ORDER BY CREATED_AT DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $this->connection->prepare($query);
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $result = $stmt->get_result();
        $packages = [];
        
        while ($row = $result->fetch_assoc()) {
            $packages[] = $row;
        }
        
        $stmt->close();
        return $packages;
    }

    /**
     * Bind params helper to ensure pass-by-reference semantics with splat unpacking.
     */
    private function bindParams(mysqli_stmt $stmt, string $types, array $params): void
    {
        if ($types === '' || empty($params)) {
            return;
        }

        $refs = [];
        foreach ($params as $key => $value) {
            $refs[$key] = &$params[$key];
        }

        $stmt->bind_param($types, ...$refs);
    }
}
