<?php

namespace App\Repositories;

use mysqli;
use RuntimeException;

class ServiceRepository
{
    private mysqli $connection;

    public function __construct(mysqli $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Lấy tất cả dịch vụ với các bộ lọc
     * 
     * @param array $filters Array with keys: category_id
     * @param string $orderBy Order by column: 'TEN_DV', 'THOI_GIAN'
     * @param int $limit Số bản ghi mỗi trang
     * @param int $offset Vị trí bắt đầu
     * @return array Array of services
     */
    public function findAll(array $filters = [], string $orderBy = 'TEN_DV', int $limit = 100, int $offset = 0): array
    {
        $query = "SELECT dv.ID_DV, dv.TEN_DV, dv.ID_DANH_MUC, dv.MOTA_DV, dv.THOI_GIAN, dv.IMAGE,
                         dm.TEN_DANH_MUC
                  FROM dich_vu dv
                  LEFT JOIN danh_muc_dich_vu dm ON dv.ID_DANH_MUC = dm.ID_DANH_MUC
                  WHERE 1=1";
        
        $params = [];
        $types = '';
        
        // Lọc theo danh mục
        if (isset($filters['category_id'])) {
            $query .= " AND dv.ID_DANH_MUC = ?";
            $params[] = $filters['category_id'];
            $types .= 'i';
        }
        
        // Sắp xếp
        $orderByMap = [
            'TEN_DV' => 'dv.TEN_DV',
            'THOI_GIAN' => 'dv.THOI_GIAN'
        ];
        $orderColumn = $orderByMap[$orderBy] ?? 'dv.TEN_DV';
        $query .= " ORDER BY {$orderColumn} ASC";
        
        // Pagination
        $query .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
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
        $services = [];
        
        while ($row = $result->fetch_assoc()) {
            $services[] = $row;
        }
        
        $stmt->close();
        return $services;
    }

    /**
     * Lấy một dịch vụ theo ID
     * 
     * @param int $id ID của dịch vụ
     * @return array|null Service data or null if not found
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->connection->prepare(
            "SELECT dv.ID_DV, dv.TEN_DV, dv.ID_DANH_MUC, dv.MOTA_DV, dv.THOI_GIAN, dv.IMAGE, dv.TRANG_THAI,
                    (SELECT dgdv.DON_GIA FROM don_gia_dich_vu dgdv WHERE dgdv.ID_DV = dv.ID_DV ORDER BY dgdv.NGAY_GIO DESC LIMIT 1) AS DON_GIA
             FROM dich_vu dv WHERE dv.ID_DV = ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('i', $id);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $result = $stmt->get_result();
        $service = $result->fetch_assoc();
        
        $stmt->close();
        return $service;
    }

    /**
     * Lấy các dịch vụ của một chi nhánh
     * 
     * @param int $branchId ID chi nhánh
     * @param string|null $status Filter by status (not used - no status field in dich_vu)
     * @return array Array of services
     */
    public function findByBranch(int $branchId, ?string $status = 'active'): array
    {
        $query = "SELECT ID_DV, TEN_DV, ID_DANH_MUC, MOTA_DV, THOI_GIAN, IMAGE
                  FROM dich_vu
                  ORDER BY TEN_DV ASC";
        
        $stmt = $this->connection->prepare($query);
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $result = $stmt->get_result();
        $services = [];
        
        while ($row = $result->fetch_assoc()) {
            $services[] = $row;
        }
        
        $stmt->close();
        return $services;
    }

    /**
     * Lấy các dịch vụ theo danh mục
     * 
     * @param int $categoryId ID danh mục
     * @param string|null $status Filter by status (not used - no status field)
     * @return array Array of services
     */
    public function findByCategory(int $categoryId, ?string $status = 'active'): array
    {
        $query = "SELECT ID_DV, TEN_DV, ID_DANH_MUC, MOTA_DV, THOI_GIAN, IMAGE
                  FROM dich_vu
                  WHERE ID_DANH_MUC = ?
                  ORDER BY TEN_DV ASC";
        
        $stmt = $this->connection->prepare($query);
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('i', $categoryId);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $result = $stmt->get_result();
        $services = [];
        
        while ($row = $result->fetch_assoc()) {
            $services[] = $row;
        }
        
        $stmt->close();
        return $services;
    }

    /**
     * Tạo dịch vụ mới
     * NOTE: Price management uses don_gia_dich_vu table
     * 
     * @param array $data {TEN_DV, ID_DANH_MUC, MOTA_DV, THOI_GIAN, IMAGE}
     * @return int New service ID
     * @throws RuntimeException on validation failure
     */
    public function create(array $data): int
    {
        // Validate required fields
        if (empty($data['TEN_DV'])) {
            throw new RuntimeException('Tên dịch vụ không được để trống');
        }
        
        // Validate category if provided
        if (isset($data['ID_DANH_MUC']) && $data['ID_DANH_MUC']) {
            $catStmt = $this->connection->prepare(
                "SELECT ID_DANH_MUC FROM danh_muc_dich_vu WHERE ID_DANH_MUC = ?"
            );
            if (!$catStmt) {
                throw new RuntimeException('Prepare failed: ' . $this->connection->error);
            }
            
            $catStmt->bind_param('i', $data['ID_DANH_MUC']);
            if (!$catStmt->execute()) {
                throw new RuntimeException('Execute failed: ' . $this->connection->error);
            }
            
            if ($catStmt->get_result()->num_rows === 0) {
                $catStmt->close();
                throw new RuntimeException('Danh mục dịch vụ không tồn tại');
            }
            $catStmt->close();
        }
        
        // Set defaults
        $tenDV = $data['TEN_DV'];
        $idDanhMuc = $data['ID_DANH_MUC'] ?? null;
        $moTa = $data['MOTA_DV'] ?? '';
        $thoiGian = $data['THOI_GIAN'] ?? '';
        $image = $data['IMAGE'] ?? null;
        $status = $data['TRANG_THAI'] ?? 'active';
        
        $stmt = $this->connection->prepare(
            "INSERT INTO dich_vu (TEN_DV, ID_DANH_MUC, MOTA_DV, THOI_GIAN, IMAGE, TRANG_THAI, CREATED_AT, UPDATED_AT)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param(
            'sissss',
            $tenDV, $idDanhMuc, $moTa, $thoiGian, $image, $status
        );
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $newId = $this->connection->insert_id;
        $stmt->close();

        // Optional: initial price snapshot if provided via admin form
        if (isset($data['GIA']) && (int)$data['GIA'] > 0) {
            $now = date('Y-m-d H:i:s');
            // Ensure thoi_diem row exists due to FK
            $td = $this->connection->prepare("INSERT IGNORE INTO thoi_diem (NGAY_GIO) VALUES (?)");
            if ($td) { $td->bind_param('s', $now); $td->execute(); $td->close(); }
            $ps = $this->connection->prepare("INSERT INTO don_gia_dich_vu (ID_DV, NGAY_GIO, DON_GIA) VALUES (?, ?, ?)");
            if ($ps) { $price = (int)$data['GIA']; $ps->bind_param('isi', $newId, $now, $price); $ps->execute(); $ps->close(); }
        }
        
        return (int)$newId;
    }

    /**
     * Cập nhật dịch vụ
     * 
     * @param int $id ID dịch vụ
     * @param array $data Fields to update (không bao gồm ID_DV)
     * @param string $updatedBy ID người cập nhật
     * @return bool True if updated successfully
     * @throws RuntimeException on validation failure
     */
    public function update(int $id, array $data, string $updatedBy = ''): bool
    {
        $service = $this->findById($id);
        if (!$service) {
            throw new RuntimeException('Dịch vụ không tồn tại');
        }
        
        // Validate name if changed
        if (isset($data['TEN_DV']) && empty($data['TEN_DV'])) {
            throw new RuntimeException('Tên dịch vụ không được để trống');
        }
        
        // Validate price if changed
        if (isset($data['GIA_DICH_VU']) && $data['GIA_DICH_VU'] < 0) {
            throw new RuntimeException('Giá dịch vụ phải >= 0');
        }
        
        // Validate category if changed
        if (isset($data['ID_DANH_MUC']) && $data['ID_DANH_MUC']) {
            $catStmt = $this->connection->prepare(
                "SELECT ID_DANH_MUC FROM danh_muc_dich_vu WHERE ID_DANH_MUC = ?"
            );
            if (!$catStmt) {
                throw new RuntimeException('Prepare failed: ' . $this->connection->error);
            }
            
            $catStmt->bind_param('i', $data['ID_DANH_MUC']);
            if (!$catStmt->execute()) {
                throw new RuntimeException('Execute failed: ' . $this->connection->error);
            }
            
            if ($catStmt->get_result()->num_rows === 0) {
                $catStmt->close();
                throw new RuntimeException('Danh mục dịch vụ không tồn tại');
            }
            $catStmt->close();
        }
        
        // Build update query dynamically
        $updateFields = [];
        $params = [];
        $types = '';
        
        $allowedFields = ['TEN_DV', 'ID_DANH_MUC', 'MO_TA', 'THOI_GIAN', 'GIA_DICH_VU', 'HINH_ANH', 'TRANG_THAI'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateFields[] = "{$field} = ?";
                $params[] = $data[$field];
                $types .= match($field) {
                    'ID_DANH_MUC', 'GIA_DICH_VU' => 'i',
                    default => 's'
                };
            }
        }
        
        if (empty($updateFields)) {
            return true; // Nothing to update
        }
        
        // Add audit columns
        $updateFields[] = "UPDATED_BY = ?";
        $updateFields[] = "UPDATED_AT = NOW()";
        $params[] = $updatedBy;
        $types .= 's';
        
        $query = "UPDATE dich_vu SET " . implode(', ', $updateFields) . " WHERE ID_DV = ?";
        $params[] = $id;
        $types .= 'i';
        
        $stmt = $this->connection->prepare($query);
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        return $affected > 0;
    }

    /**
     * Xóa dịch vụ
     * 
     * @param int $id ID dịch vụ
     * @return bool True if deleted successfully
     * @throws RuntimeException if service is used in packages
     */
    public function delete(int $id): bool
    {
        // Check if service is used in any package
        $checkStmt = $this->connection->prepare(
            "SELECT ID_DETAIL FROM goi_dich_vu_chi_tiet WHERE ID_DV = ? LIMIT 1"
        );
        
        if (!$checkStmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $checkStmt->bind_param('i', $id);
        if (!$checkStmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        if ($checkStmt->get_result()->num_rows > 0) {
            $checkStmt->close();
            throw new RuntimeException('Không thể xóa dịch vụ đang được sử dụng trong gói dịch vụ');
        }
        $checkStmt->close();
        
        // Check if service is used in any booking
        $bookingStmt = $this->connection->prepare(
            "SELECT ID_BOOKING_ITEM FROM booking_item WHERE ITEM_ID = ? AND ITEM_TYPE = 'service' LIMIT 1"
        );
        
        if (!$bookingStmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $bookingStmt->bind_param('i', $id);
        if (!$bookingStmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        if ($bookingStmt->get_result()->num_rows > 0) {
            $bookingStmt->close();
            throw new RuntimeException('Không thể xóa dịch vụ đang được sử dụng trong booking');
        }
        $bookingStmt->close();
        
        // Delete service
        $stmt = $this->connection->prepare("DELETE FROM dich_vu WHERE ID_DV = ?");
        
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
     * Tìm kiếm dịch vụ
     * 
     * @param string $keyword Từ khóa tìm kiếm
     * @param int $limit Số bản ghi tối đa
     * @param int $offset Vị trí bắt đầu
     * @return array Array of matching services
     */
    public function search(string $keyword, int $limit = 50, int $offset = 0): array
    {
        $keyword = '%' . $keyword . '%';
        
        $stmt = $this->connection->prepare(
            "SELECT ID_DV, TEN_DV, ID_DANH_MUC, MOTA_DV, THOI_GIAN, IMAGE
             FROM dich_vu
             WHERE TEN_DV LIKE ? OR MOTA_DV LIKE ?
             ORDER BY TEN_DV ASC
             LIMIT ? OFFSET ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('ssii', $keyword, $keyword, $limit, $offset);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $result = $stmt->get_result();
        $services = [];
        
        while ($row = $result->fetch_assoc()) {
            $services[] = $row;
        }
        
        $stmt->close();
        return $services;
    }

    /**
     * Đếm tất cả dịch vụ
     * 
     * @param array $filters Optional filters
     * @return int Total count
     */
    public function count(array $filters = []): int
    {
        $query = "SELECT COUNT(*) as count FROM dich_vu WHERE 1=1";
        $params = [];
        $types = '';
        
        if (isset($filters['category_id'])) {
            $query .= " AND ID_DANH_MUC = ?";
            $params[] = $filters['category_id'];
            $types .= 'i';
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
        
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return (int)($result['count'] ?? 0);
    }

    /**
     * Lấy các dịch vụ có giá trong khoảng
     * 
     * @param int $minPrice Giá tối thiểu
     * @param int $maxPrice Giá tối đa
     * @param string|null $status Filter by status
     * @return array Array of services
     */
    public function findByPriceRange(int $minPrice, int $maxPrice, ?string $status = 'active'): array
    {
        $query = "SELECT ID_DV, TEN_DV, ID_DANH_MUC, MO_TA, THOI_GIAN, GIA_DICH_VU,
                         HINH_ANH, TRANG_THAI, SCOPE_TYPE, ID_CN_OWNER, CREATED_BY,
                         CREATED_AT, UPDATED_BY, UPDATED_AT
                  FROM dich_vu
                  WHERE GIA_DICH_VU BETWEEN ? AND ?";
        
        if ($status) {
            $query .= " AND TRANG_THAI = ?";
        }
        
        $query .= " ORDER BY GIA_DICH_VU ASC";
        
        $stmt = $this->connection->prepare($query);
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        if ($status) {
            $stmt->bind_param('iis', $minPrice, $maxPrice, $status);
        } else {
            $stmt->bind_param('ii', $minPrice, $maxPrice);
        }
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $result = $stmt->get_result();
        $services = [];
        
        while ($row = $result->fetch_assoc()) {
            $services[] = $row;
        }
        
        $stmt->close();
        return $services;
    }

    /**
     * Kiểm tra dịch vụ có thể xóa không
     * 
     * @param int $id ID dịch vụ
     * @return bool True if can delete
     */
    public function canDelete(int $id): bool
    {
        // Check packages
        $pkgStmt = $this->connection->prepare(
            "SELECT ID_DETAIL FROM goi_dich_vu_chi_tiet WHERE ID_DV = ? LIMIT 1"
        );
        
        if (!$pkgStmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $pkgStmt->bind_param('i', $id);
        if (!$pkgStmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        if ($pkgStmt->get_result()->num_rows > 0) {
            $pkgStmt->close();
            return false;
        }
        $pkgStmt->close();
        
        // Check bookings
        $bookingStmt = $this->connection->prepare(
            "SELECT ID_BOOKING_ITEM FROM booking_item WHERE ITEM_ID = ? AND ITEM_TYPE = 'service' LIMIT 1"
        );
        
        if (!$bookingStmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $bookingStmt->bind_param('i', $id);
        if (!$bookingStmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        if ($bookingStmt->get_result()->num_rows > 0) {
            $bookingStmt->close();
            return false;
        }
        $bookingStmt->close();
        
        return true;
    }

    /**
     * Count services with optional filters
     * 
     * @param string|null $search Search keyword
     * @param string|null $status Filter by status (not used - dich_vu has no status field)
     * @param int|null $minPrice Minimum price (not used - price is in don_gia_dich_vu table)
     * @param int|null $maxPrice Maximum price (not used - price is in don_gia_dich_vu table)
     * @param int|null $categoryId Category filter
     * @param string|null $tag Tag filter (not used - no tag field in dich_vu)
     * @return int Total count
     */
    public function countServices(?string $search = null, ?string $status = null, ?int $minPrice = null, 
                                  ?int $maxPrice = null, ?int $categoryId = null, ?string $tag = null): int
    {
        $query = "SELECT COUNT(*) as count FROM dich_vu WHERE 1=1";
        $params = [];
        $types = '';
        
        if ($search) {
            $query .= " AND (dv.TEN_DV LIKE ? OR dv.MOTA_DV LIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $types .= 'ss';
        }
        
        if ($categoryId !== null) {
            $query .= " AND dv.ID_DANH_MUC = ?";
            $params[] = $categoryId;
            $types .= 'i';
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
        
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return (int)($result['count'] ?? 0);
    }

    /**
     * Search services with advanced filters
     * 
     * @param string|null $search Search keyword
     * @param int $limit Items per page
     * @param int $offset Pagination offset
     * @param string|null $status Filter by status (not used - dich_vu has no status)
     * @param int|null $minPrice Minimum price (not used - price is in don_gia_dich_vu)
     * @param int|null $maxPrice Maximum price (not used - price is in don_gia_dich_vu)
     * @param int|null $categoryId Category filter
     * @param string|null $tag Tag filter (not used - no tag field)
     * @param string $sortBy Sort column
     * @param string $sortOrder Sort direction
     * @return array Array of services
     */
    public function searchServices(?string $search = null, int $limit = 50, int $offset = 0, ?string $status = null, 
                                   ?int $minPrice = null, ?int $maxPrice = null, ?int $categoryId = null, 
                                   ?string $tag = null, string $sortBy = 'TEN_DV', string $sortOrder = 'ASC'): array
    {
        $query = "SELECT dv.ID_DV, dv.TEN_DV, dv.ID_DANH_MUC, dv.MOTA_DV, dv.THOI_GIAN, dv.IMAGE, dv.TRANG_THAI,
                 (SELECT dgdv.DON_GIA FROM don_gia_dich_vu dgdv WHERE dgdv.ID_DV = dv.ID_DV ORDER BY dgdv.NGAY_GIO DESC LIMIT 1) AS DON_GIA
              FROM dich_vu dv
                  WHERE 1=1";
        $params = [];
        $types = '';
        
        if ($search) {
            $query .= " AND (TEN_DV LIKE ? OR MOTA_DV LIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $types .= 'ss';
        }
        
        if ($categoryId !== null) {
            $query .= " AND ID_DANH_MUC = ?";
            $params[] = $categoryId;
            $types .= 'i';
        }

        if ($status !== null) {
            $query .= " AND dv.TRANG_THAI = ?";
            $params[] = $status;
            $types .= 's';
        }
        
        // Validate sort parameters
        $allowedSort = ['ID_DV', 'TEN_DV', 'THOI_GIAN'];
        $sortBy = in_array($sortBy, $allowedSort) ? $sortBy : 'TEN_DV';
        $sortOrder = strtoupper($sortOrder) === 'DESC' ? 'DESC' : 'ASC';
        
        $query .= " ORDER BY dv." . $sortBy . " " . $sortOrder . " LIMIT ? OFFSET ?";
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
        $services = [];
        
        while ($row = $result->fetch_assoc()) {
            $services[] = $row;
        }
        
        $stmt->close();
        return $services;
    }

    /**
     * Lấy lịch sử giá của dịch vụ
     *
     * @param int $serviceId ID dịch vụ
     * @param int $limit Số bản ghi tối đa
     * @return array Danh sách các lần cập nhật giá
     */
    public function getPriceHistory(int $serviceId, int $limit = 50): array
    {
        $stmt = $this->connection->prepare(
            "SELECT ID_DV, NGAY_GIO, DON_GIA
             FROM don_gia_dich_vu
             WHERE ID_DV = ?
             ORDER BY NGAY_GIO DESC
             LIMIT ?"
        );

        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }

        $stmt->bind_param('ii', $serviceId, $limit);

        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }

        $result = $stmt->get_result();
        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }

        $stmt->close();
        return $history;
    }

    /**
     * Soft-retire a service by setting status to 'retired'.
     * Does not hard-delete; preserves historical relations.
     */
    public function retire(int $serviceId): bool
    {
        $stmt = $this->connection->prepare(
            "UPDATE dich_vu SET TRANG_THAI = 'retired', UPDATED_AT = NOW() WHERE ID_DV = ?"
        );
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }

        $stmt->bind_param('i', $serviceId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }

        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected > 0;
    }

    /**
     * Return array of blocking reasons preventing deleting/retiring a service.
     * Structure: [ ['code'=>string,'message'=>string], ... ]
     * Codes:
     *  - future_appointments: Service has ongoing/future appointments
     *  - draft_package: Service belongs to draft packages
     *  - active_package: Service belongs to published active packages
     *  - financial_history: Service appears in invoice line items
     */
    public function deletionBlockingReasons(int $serviceId): array
    {
        $reasons = [];

        // 1) Future or processing appointments referencing this service
        // Treat the following as terminal (not blocking): Đã hoàn thành, Đã hủy, Không đến (no-show)
        if ($stmt = $this->connection->prepare(
            "SELECT COUNT(*) AS c FROM lich_hen WHERE ID_DV = ? AND TRANGTHAI NOT IN ('Đã hoàn thành','Đã hủy','Không đến')"
        )) {
            $stmt->bind_param('i', $serviceId);
            if ($stmt->execute()) {
                $count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
                if ($count > 0) {
                    $reasons[] = [
                        'code' => 'future_appointments',
                        'message' => "Có $count lịch hẹn chưa kết thúc."
                    ];
                }
            }
            $stmt->close();
        }

        // 2) Draft packages containing this service
        if ($stmt = $this->connection->prepare(
            "SELECT COUNT(*) AS c
             FROM goi_dich_vu_chi_tiet ct
             JOIN goi_dich_vu g ON g.ID_GOI = ct.ID_GOI
             WHERE ct.ID_DV = ? AND g.IS_DELETED = 0 AND g.TRANG_THAI = 'nhap'"
        )) {
            $stmt->bind_param('i', $serviceId);
            if ($stmt->execute()) {
                $count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
                if ($count > 0) {
                    $reasons[] = [
                        'code' => 'draft_package',
                        'message' => 'Dịch vụ đang nằm trong gói (nháp).'
                    ];
                }
            }
            $stmt->close();
        }

        // 3) Published and currently active packages containing this service
        if ($stmt = $this->connection->prepare(
            "SELECT COUNT(*) AS c
             FROM goi_dich_vu_chi_tiet ct
             JOIN goi_dich_vu g ON g.ID_GOI = ct.ID_GOI
             WHERE ct.ID_DV = ? AND g.IS_DELETED = 0 AND g.TRANG_THAI = 'ban'
               AND (g.HIEU_LUC_TU IS NULL OR g.HIEU_LUC_TU <= NOW())
               AND (g.HIEU_LUC_DEN IS NULL OR g.HIEU_LUC_DEN >= NOW())"
        )) {
            $stmt->bind_param('i', $serviceId);
            if ($stmt->execute()) {
                $count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
                if ($count > 0) {
                    $reasons[] = [
                        'code' => 'active_package',
                        'message' => 'Dịch vụ đang nằm trong gói đang bán.'
                    ];
                }
            }
            $stmt->close();
        }

        // 4) Financial history in invoice line items (schema with LOAI/ID_THAM_CHIEU)
        //    If table does not exist in this deployment, skip gracefully.
        $tableExists = false;
        if ($rs = $this->connection->query("SHOW TABLES LIKE 'chi_tiet_hoa_don'")) {
            $tableExists = $rs->num_rows > 0; $rs->close();
        }
        if ($tableExists) {
            if ($stmt = $this->connection->prepare(
                "SELECT COUNT(*) AS c FROM chi_tiet_hoa_don WHERE LOAI = 'service' AND ID_THAM_CHIEU = ?"
            )) {
                $stmt->bind_param('i', $serviceId);
                if ($stmt->execute()) {
                    $count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
                    if ($count > 0) {
                        $reasons[] = [
                            'code' => 'financial_history',
                            'message' => 'Dịch vụ có lịch sử tài chính (hóa đơn).'
                        ];
                    }
                }
                $stmt->close();
            }
        }

        return $reasons;
    }
}
