<?php

namespace App\Repositories;

use mysqli;
use RuntimeException;

class CostumeRepository
{
    private mysqli $connection;

    public function __construct(mysqli $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Lấy tất cả trang phục với các bộ lọc
     * 
     * @param array $filters Array with keys: type_id, group_id, status, scope_type, branch_id
     * @param string $orderBy Order by column: 'TEN', 'GIA_THUE', 'CREATED_AT'
     * @param int $limit Số bản ghi mỗi trang
     * @param int $offset Vị trí bắt đầu
     * @return array Array of costumes
     */
    public function findAll(array $filters = [], string $orderBy = 'TEN', int $limit = 100, int $offset = 0): array
    {
        $query = "SELECT tp.ID_TRANG_PHUC, tp.TEN, tp.SIZE, tp.MAU_SAC, tp.GIA_THUE,
                         tp.ID_LOAI, tp.SCOPE_TYPE, tp.ID_CN_OWNER, tp.HIEU_LUC_TU,
                         tp.HIEU_LUC_DEN, tp.TRANG_THAI, tp.CREATED_BY, tp.CREATED_AT,
                         tp.UPDATED_BY, tp.UPDATED_AT,
                         tl.TEN_LOAI, tpn.TEN_NHOM
                  FROM trang_phuc tp
                  LEFT JOIN trang_phuc_loai tl ON tp.ID_LOAI = tl.ID_LOAI
                  LEFT JOIN trang_phuc_nhom tpn ON tl.ID_NHOM = tpn.ID_NHOM
                  WHERE 1=1";
        
        $params = [];
        $types = '';
        
        // Lọc theo loại
        if (isset($filters['type_id'])) {
            $query .= " AND tp.ID_LOAI = ?";
            $params[] = $filters['type_id'];
            $types .= 'i';
        }
        
        // Lọc theo nhóm
        if (isset($filters['group_id'])) {
            $query .= " AND tl.ID_NHOM = ?";
            $params[] = $filters['group_id'];
            $types .= 'i';
        }
        
        // Lọc theo trạng thái
        if (isset($filters['status'])) {
            $query .= " AND tp.TRANG_THAI = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        // Lọc theo scope
        if (isset($filters['scope_type'])) {
            $query .= " AND tp.SCOPE_TYPE = ?";
            $params[] = $filters['scope_type'];
            $types .= 's';
        }
        
        // Lọc theo chi nhánh (cho local scope)
        if (isset($filters['branch_id'])) {
            $query .= " AND (tp.SCOPE_TYPE = 'global' OR tp.ID_CN_OWNER = ?)";
            $params[] = $filters['branch_id'];
            $types .= 'i';
        }
        
        // Lọc theo khoảng giá
        if (isset($filters['price_min']) && isset($filters['price_max'])) {
            $query .= " AND tp.GIA_THUE BETWEEN ? AND ?";
            $params[] = $filters['price_min'];
            $params[] = $filters['price_max'];
            $types .= 'dd';
        }
        
        // Lọc theo độ tuổi hiệu lực
        if (isset($filters['valid_date'])) {
            $query .= " AND (tp.HIEU_LUC_TU IS NULL OR tp.HIEU_LUC_TU <= ?)
                        AND (tp.HIEU_LUC_DEN IS NULL OR tp.HIEU_LUC_DEN >= ?)";
            $params[] = $filters['valid_date'];
            $params[] = $filters['valid_date'];
            $types .= 'ss';
        }
        
        // Sắp xếp
        $orderByMap = [
            'TEN' => 'tp.TEN',
            'GIA_THUE' => 'tp.GIA_THUE',
            'CREATED_AT' => 'tp.CREATED_AT',
            'SIZE' => 'tp.SIZE',
            'MAU_SAC' => 'tp.MAU_SAC'
        ];
        $orderColumn = $orderByMap[$orderBy] ?? 'tp.TEN';
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
            throw new RuntimeException('Execute failed: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $costumes = [];
        
        while ($row = $result->fetch_assoc()) {
            $costumes[] = $row;
        }
        
        $stmt->close();
        return $costumes;
    }

    /**
     * Lấy danh sách trang phục thuộc gói (bao gồm cả optional & bắt buộc)
     * @return array<int, array{ID_TRANG_PHUC:int,TEN:string,GIA_THUE:int,DISCOUNT_PERCENT:int,BAT_BUOC:int}>
     */
    public function getCostumesForPackage(int $packageId): array
    {
        $sql = "SELECT tp.ID_TRANG_PHUC, tp.TEN, tp.GIA_THUE, gtp.DISCOUNT_PERCENT, gtp.BAT_BUOC
                FROM GOI_TRANG_PHUC gtp
                JOIN TRANG_PHUC tp ON tp.ID_TRANG_PHUC = gtp.ID_TRANG_PHUC
                WHERE gtp.ID_GOI = ?
                ORDER BY COALESCE(gtp.THU_TU, tp.ID_TRANG_PHUC)";
        $stmt = $this->connection->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Không thể truy vấn danh sách trang phục của gói: ' . $this->connection->error);
        }
        $stmt->bind_param('i', $packageId);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows ?: [];
    }

    /**
     * Lấy giá gói (base price) từ view tổng tiền.
     */
    public function getPackageBasePrice(int $packageId): int
    {
        $sql = "SELECT TONG_GIA_GOI FROM v_goi_dich_vu_tong_tien WHERE ID_GOI = ? LIMIT 1";
        $stmt = $this->connection->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Không thể truy vấn giá gói: ' . $this->connection->error);
        }
        $stmt->bind_param('i', $packageId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return $row && isset($row['TONG_GIA_GOI']) ? (int)$row['TONG_GIA_GOI'] : 0;
    }

    /**
     * Trả về map ID_TRANG_PHUC -> GIA_THUE.
     * @param int[] $ids
     * @return array<int,int>
     */
    public function getCostumeBasePrices(array $ids): array
    {
        if (empty($ids)) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT ID_TRANG_PHUC, GIA_THUE FROM TRANG_PHUC WHERE ID_TRANG_PHUC IN ($placeholders)";
        $types = str_repeat('i', count($ids));
        $stmt = $this->connection->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Không thể truy vấn giá trang phục: ' . $this->connection->error);
        }
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $result = $stmt->get_result();
        $map = [];
        while ($row = $result->fetch_assoc()) {
            $map[(int)$row['ID_TRANG_PHUC']] = (int)$row['GIA_THUE'];
        }
        $stmt->close();
        return $map;
    }

    /**
     * @param int[] $ids
     * @return array<int, array{ID_TRANG_PHUC:int,TEN:string,GIA_THUE:int}>
     */
    public function getCostumesByIds(array $ids): array
    {
        if (empty($ids)) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT ID_TRANG_PHUC, TEN, GIA_THUE FROM TRANG_PHUC WHERE ID_TRANG_PHUC IN ($placeholders)";
        $types = str_repeat('i', count($ids));
        $stmt = $this->connection->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Không thể truy vấn thông tin trang phục: ' . $this->connection->error);
        }
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows ?: [];
    }

    public function findById(int $costumeId): ?array
    {
        $sql = "SELECT ID_TRANG_PHUC, TEN, MAU_SAC, SIZE, ID_LOAI, GIA_THUE, ID_CN, SCOPE_TYPE, ID_CN_OWNER, TRANG_THAI FROM TRANG_PHUC WHERE ID_TRANG_PHUC = ? LIMIT 1";
        $stmt = $this->connection->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Không thể tải thông tin trang phục: ' . $this->connection->error);
        }
        $stmt->bind_param('i', $costumeId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        if (!$row) {
            return null;
        }
        if (!isset($row['SCOPE_TYPE']) || $row['SCOPE_TYPE'] === null || $row['SCOPE_TYPE'] === '') {
            $row['SCOPE_TYPE'] = 'global';
        }
        return $row;
    }

    public function validateScopeChangeAllowed(int $costumeId, string $newScope, ?int $newBranchId = null): array
    {
        $current = $this->findById($costumeId);
        if (!$current) {
            return ['allowed' => false, 'reason' => 'Không tìm thấy trang phục.'];
        }

        $oldScope = $current['SCOPE_TYPE'] ?? 'global';
        $normalizedNewScope = strtolower($newScope) === 'local' ? 'local' : 'global';
        $targetBranch = $newBranchId ?? (isset($current['ID_CN_OWNER']) ? (int)$current['ID_CN_OWNER'] : null);

        $sql = "SELECT m.ID_GOI, m.ID_CN_OWNER FROM goi_trang_phuc_chi_tiet c JOIN goi_trang_phuc_master m ON m.ID_GOI = c.ID_GOI WHERE c.ID_TRANG_PHUC = ?";
        $stmt = $this->connection->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Không thể kiểm tra phạm vi trang phục: ' . $this->connection->error);
        }
        $stmt->bind_param('i', $costumeId);
        $stmt->execute();
        $result = $stmt->get_result();
        $packages = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        foreach ($packages as $package) {
            // gói không lưu scope, mặc định xem là local theo chi nhánh owner
            $packageScope = 'local';
            $packageBranch = isset($package['ID_CN_OWNER']) ? (int)$package['ID_CN_OWNER'] : null;
            $packageId = (int)($package['ID_GOI'] ?? 0);

            if ($oldScope === 'local' && $normalizedNewScope === 'global' && $packageScope === 'local') {
                return [
                    'allowed' => false,
                    'reason'  => "Không thể chuyển thành global vì đang thuộc gói nội bộ #{$packageId}.",
                ];
            }

            if ($oldScope === 'global' && $normalizedNewScope === 'local' && $packageScope === 'global') {
                return [
                    'allowed' => false,
                    'reason'  => "Không thể chuyển thành local vì đang thuộc gói global #{$packageId}.",
                ];
            }

            if ($normalizedNewScope === 'local' && $packageScope === 'local' && $targetBranch !== null && $packageBranch !== null && $targetBranch !== $packageBranch) {
                return [
                    'allowed' => false,
                    'reason'  => "Không thể đổi chi nhánh vì trang phục nằm trong gói chi nhánh #{$packageBranch}.",
                ];
            }
        }

        return ['allowed' => true];
    }

    public function validateCostumeCanBeRented(int $costumeId, int $rentalBranchId): array
    {
        $costume = $this->findById($costumeId);
        if (!$costume) {
            return ['allowed' => false, 'reason' => 'Không tìm thấy trang phục.'];
        }

        $scope = $costume['SCOPE_TYPE'] ?? 'global';
        $ownerBranch = isset($costume['ID_CN_OWNER']) ? (int)$costume['ID_CN_OWNER'] : 0;

        if ($scope === 'local' && $ownerBranch && $ownerBranch !== $rentalBranchId) {
            return [
                'allowed' => false,
                'reason'  => 'Trang phục nội bộ chỉ có thể cho thuê tại chi nhánh chủ quản.',
            ];
        }

        return ['allowed' => true];
    }

    public function ensureAccessible(int $costumeId, ?string $userRole = null, ?string $staffType = null, ?int $userBranch = null): array
    {
        $role = $userRole ?? (string)($_SESSION['ID_QUYEN'] ?? '');
        $staffTypeValue = $staffType ?? (string)($_SESSION['STAFF_TYPE'] ?? '');
        $branch = $userBranch ?? (isset($_SESSION['branch_id']) ? (int)$_SESSION['branch_id'] : null);

        if ($role === '') {
            return ['accessible' => false, 'reason' => 'Bạn chưa đăng nhập.'];
        }

        $costume = $this->findById($costumeId);
        if (!$costume) {
            return ['accessible' => false, 'reason' => 'Trang phục không tồn tại hoặc đã bị xóa.'];
        }

        $scope = $costume['SCOPE_TYPE'] ?? 'global';
        $ownerBranch = isset($costume['ID_CN_OWNER']) ? (int)$costume['ID_CN_OWNER'] : 0;

        if ($role === '1') {
            return ['accessible' => true];
        }

        if ($role === '2' && $staffTypeValue === 'quan_ly' && $branch) {
            if ($scope === 'global') {
                return ['accessible' => false, 'reason' => 'Quản lý chi nhánh không thể chỉnh sửa trang phục global.'];
            }

            if ($ownerBranch !== $branch) {
                return ['accessible' => false, 'reason' => 'Bạn chỉ có thể chỉnh sửa trang phục thuộc chi nhánh của mình.'];
            }

            return ['accessible' => true];
        }

        return ['accessible' => false, 'reason' => 'Vai trò hiện tại không có quyền chỉnh sửa trang phục.'];
    }

    public function isAvailableDuring(int $costumeId, string $startDate, string $endDate, ?int $branchId = null): bool
    {
        if ($branchId !== null) {
            $scopeCheck = $this->validateCostumeCanBeRented($costumeId, $branchId);
            if (!$scopeCheck['allowed']) {
                return false;
            }
        }

        $sql = "SELECT 1 FROM don_thue_trang_phuc t JOIN don_thue_trang_phuc_ct c ON c.ID_TTP = t.ID_TTP WHERE c.ID_TP = ? AND t.TRANG_THAI <> 'huy' AND t.NGAY_NHAN < ? AND t.NGAY_TRA_DK > ? LIMIT 1";
        $stmt = $this->connection->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Không thể kiểm tra lịch thuê trang phục: ' . $this->connection->error);
        }
        $stmt->bind_param('iss', $costumeId, $endDate, $startDate);
        $stmt->execute();
        $stmt->store_result();
        $hasConflict = $stmt->num_rows > 0;
        $stmt->close();

        return !$hasConflict;
    }

    /**
     * Mark rental as returned and calculate late fees if overdue
     * @param int $rentalId ID_TTP (rental ID)
     * @param string|null $actualReturnDate Format: Y-m-d H:i:s (default: now)
     * @param int $dailyLateFeeAmount Fee per day overdue (default: 50000 VND)
     * @return array ['success'=>bool, 'overdue_days'=>int, 'late_fee'=>int, 'message'=>string]
     */
    public function markReturned(int $rentalId, ?string $actualReturnDate = null, int $dailyLateFeeAmount = 50000): array
    {
        $actualReturnDate = $actualReturnDate ?? date('Y-m-d H:i:s');
        
        // Get rental info
        $sql = "SELECT ID_TTP, NGAY_TRA_DK, NGAY_TRA_THAT, TRANG_THAI FROM don_thue_trang_phuc WHERE ID_TTP = ? LIMIT 1";
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            return ['success' => false, 'overdue_days' => 0, 'late_fee' => 0, 'message' => 'Lỗi query rental info'];
        }
        
        $stmt->bind_param('i', $rentalId);
        $stmt->execute();
        $rental = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$rental) {
            return ['success' => false, 'overdue_days' => 0, 'late_fee' => 0, 'message' => 'Không tìm thấy đơn thuê'];
        }
        
        // Calculate overdue days
        $expectedReturnTime = strtotime($rental['NGAY_TRA_DK']);
        $actualReturnTime = strtotime($actualReturnDate);
        
        if ($expectedReturnTime === false || $actualReturnTime === false) {
            return ['success' => false, 'overdue_days' => 0, 'late_fee' => 0, 'message' => 'Định dạng ngày tháng không hợp lệ'];
        }
        
        // Calculate overdue (in days)
        $overdueDays = max(0, (int)ceil(($actualReturnTime - $expectedReturnTime) / 86400));
        
        // Calculate late fee (only if overdue)
        $lateFee = $overdueDays > 0 ? $overdueDays * $dailyLateFeeAmount : 0;
        
        // Update rental: set actual return date, mark as returned, record late fee
        $updateSql = "UPDATE don_thue_trang_phuc SET 
                      NGAY_TRA_THAT = ?, 
                      TRANG_THAI = 'tra', 
                      PHI_TRE_HEN = ?, 
                      TRE_HEN_NGAY = ?,
                      UPDATED_AT = NOW()
                      WHERE ID_TTP = ?";
        
        $updateStmt = $this->connection->prepare($updateSql);
        if (!$updateStmt) {
            return ['success' => false, 'overdue_days' => $overdueDays, 'late_fee' => $lateFee, 'message' => 'Lỗi update rental'];
        }
        
        $updateStmt->bind_param('siii', $actualReturnDate, $lateFee, $overdueDays, $rentalId);
        $success = $updateStmt->execute();
        $updateStmt->close();
        
        if ($success) {
            $message = $overdueDays === 0 
                ? 'Đã đánh dấu trả trang phục thành công (không bị trễ hạn)'
                : "Đã đánh dấu trả trang phục (trễ {$overdueDays} ngày, phí: " . number_format($lateFee) . " VND)";
            
            return [
                'success' => true,
                'overdue_days' => $overdueDays,
                'late_fee' => $lateFee,
                'message' => $message
            ];
        }
        
        return ['success' => false, 'overdue_days' => $overdueDays, 'late_fee' => $lateFee, 'message' => 'Không thể cập nhật đơn thuê'];
    }

    /**
     * Get rental details including late fee information
     */
    public function getRentalWithLateFeeInfo(int $rentalId): ?array
    {
        $sql = "SELECT 
                    ID_TTP, 
                    NGAY_NHAN, 
                    NGAY_TRA_DK,
                    NGAY_TRA_THAT,
                    TRE_HEN_NGAY,
                    PHI_TRE_HEN,
                    TRANG_THAI,
                    TONG_TIEN_DU_KIEN,
                    TONG_TIEN_THUC_TE
                FROM don_thue_trang_phuc 
                WHERE ID_TTP = ? LIMIT 1";
        
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            return null;
        }
        
        $stmt->bind_param('i', $rentalId);
        $stmt->execute();
        $rental = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($rental && !$rental['NGAY_TRA_THAT']) {
            // Not returned yet - calculate what fee would be if returned today
            $expectedTime = strtotime($rental['NGAY_TRA_DK']);
            $nowTime = time();
            $overdueDays = max(0, (int)ceil(($nowTime - $expectedTime) / 86400));
            $potentialFee = $overdueDays * 50000;
            
            $rental['projected_overdue_days'] = $overdueDays;
            $rental['projected_late_fee'] = $potentialFee;
        }
        
        return $rental;
    }

    // ============================================================
    // CRUD SUPPORT FOR API (create/update/status)
    // ============================================================

    /**
     * Create a costume record.
     * Expected keys: TEN, GIA_THUE, ID_LOAI, SCOPE_TYPE, ID_CN (owner for local), SIZE, MAU_SAC, GHI_CHU, TRANG_THAI
     */
    public function create(array $data): int
    {
        $scope = strtolower($data['SCOPE_TYPE'] ?? 'global');
        $ownerBranch = null;
        if ($scope === 'local') {
            $ownerBranch = $data['ID_CN'] ?? $data['ID_CN_OWNER'] ?? null;
        }

        $sql = "INSERT INTO trang_phuc (TEN, GIA_THUE, ID_LOAI, SIZE, MAU_SAC, GHI_CHU, TRANG_THAI, SCOPE_TYPE, ID_CN, ID_CN_OWNER) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Không thể tạo trang phục: ' . $this->connection->error);
        }

        $ten = $data['TEN'] ?? '';
        $gia = (int)($data['GIA_THUE'] ?? 0);
        $idLoai = (int)($data['ID_LOAI'] ?? 0);
        $size = $data['SIZE'] ?? null;
        $mau = $data['MAU_SAC'] ?? null;
        $ghiChu = $data['GHI_CHU'] ?? null;
        $status = $data['TRANG_THAI'] ?? 'available';
        $scopeVal = $scope;
        $owner = $ownerBranch !== null ? (int)$ownerBranch : null;
        $stmt->bind_param('siisssssii', $ten, $gia, $idLoai, $size, $mau, $ghiChu, $status, $scopeVal, $owner, $owner);

        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Không thể tạo trang phục: ' . $err);
        }

        $newId = $stmt->insert_id;
        $stmt->close();
        return (int)$newId;
    }

    /**
     * Update costume fields (only whitelisted columns).
     */
    public function update(int $costumeId, array $data): bool
    {
        $allowed = [
            'TEN' => 's',
            'GIA_THUE' => 'i',
            'ID_LOAI' => 'i',
            'SIZE' => 's',
            'MAU_SAC' => 's',
            'GHI_CHU' => 's',
            'TRANG_THAI' => 's',
            'SCOPE_TYPE' => 's',
            'ID_CN' => 'i',
            'ID_CN_OWNER' => 'i'
        ];

        $setParts = [];
        $params = [];
        $types = '';

        foreach ($allowed as $field => $type) {
            if (array_key_exists($field, $data)) {
                $setParts[] = "$field = ?";
                $params[] = $data[$field];
                $types .= $type;
            }
        }

        if (empty($setParts)) {
            return true; // nothing to update
        }

        $sql = "UPDATE trang_phuc SET " . implode(', ', $setParts) . ", UPDATED_AT = NOW() WHERE ID_TRANG_PHUC = ?";
        $params[] = $costumeId;
        $types .= 'i';

        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Không thể cập nhật trang phục: ' . $this->connection->error);
        }

        $stmt->bind_param($types, ...$params);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Update status helper.
     */
    public function updateStatus(int $costumeId, string $status): bool
    {
        $stmt = $this->connection->prepare("UPDATE trang_phuc SET TRANG_THAI = ?, UPDATED_AT = NOW() WHERE ID_TRANG_PHUC = ?");
        if (!$stmt) {
            throw new RuntimeException('Không thể cập nhật trạng thái trang phục: ' . $this->connection->error);
        }
        $stmt->bind_param('si', $status, $costumeId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Find costumes by type.
     */
    public function findByType(int $typeId): array
    {
        $stmt = $this->connection->prepare("SELECT * FROM trang_phuc WHERE ID_LOAI = ?");
        if (!$stmt) {
            throw new RuntimeException('Không thể tải trang phục theo loại: ' . $this->connection->error);
        }
        $stmt->bind_param('i', $typeId);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows ?: [];
    }

    /**
     * Find costumes by branch (includes global scope).
     */
    public function findByBranch(int $branchId): array
    {
        $stmt = $this->connection->prepare("SELECT * FROM trang_phuc WHERE SCOPE_TYPE = 'global' OR ID_CN_OWNER = ?");
        if (!$stmt) {
            throw new RuntimeException('Không thể tải trang phục theo chi nhánh: ' . $this->connection->error);
        }
        $stmt->bind_param('i', $branchId);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows ?: [];
    }

    /**
     * Simple search by keyword across name, size, color.
     */
    public function search(string $keyword, int $limit = 100, int $offset = 0, array $filters = []): array
    {
        $like = '%' . $keyword . '%';
        $sql = "SELECT * FROM trang_phuc WHERE (TEN LIKE ? OR SIZE LIKE ? OR MAU_SAC LIKE ?)";
        $params = [$like, $like, $like];
        $types = 'sss';

        if (isset($filters['status'])) {
            $sql .= " AND TRANG_THAI = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        if (isset($filters['scope_type'])) {
            $sql .= " AND SCOPE_TYPE = ?";
            $params[] = $filters['scope_type'];
            $types .= 's';
        }
        if (isset($filters['branch_id'])) {
            $sql .= " AND (SCOPE_TYPE = 'global' OR ID_CN_OWNER = ?)";
            $params[] = $filters['branch_id'];
            $types .= 'i';
        }

        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';

        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Không thể tìm kiếm trang phục: ' . $this->connection->error);
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows ?: [];
    }

    /**
     * Count costumes by status.
     */
    public function countByStatus(string $status): int
    {
        $stmt = $this->connection->prepare("SELECT COUNT(*) AS cnt FROM trang_phuc WHERE TRANG_THAI = ?");
        if (!$stmt) {
            throw new RuntimeException('Không thể đếm trang phục theo trạng thái: ' . $this->connection->error);
        }
        $stmt->bind_param('s', $status);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : ['cnt' => 0];
        $stmt->close();
        return (int)($row['cnt'] ?? 0);
    }
}
