<?php

namespace App\Services;

use App\Repositories\CostumeRepository;
use App\Repositories\CostumeTypeRepository;
use App\Repositories\CostumeGroupRepository;
use App\Repositories\RentalValidationRepository;
use mysqli;
use RuntimeException;

class CostumeService
{
    private mysqli $connection;
    private CostumeRepository $costumeRepo;
    private CostumeTypeRepository $typeRepo;
    private CostumeGroupRepository $groupRepo;
    private RentalValidationRepository $validationRepo;

    public function __construct(
        mysqli $connection,
        CostumeRepository $costumeRepo,
        CostumeTypeRepository $typeRepo,
        CostumeGroupRepository $groupRepo,
        RentalValidationRepository $validationRepo
    ) {
        $this->connection = $connection;
        $this->costumeRepo = $costumeRepo;
        $this->typeRepo = $typeRepo;
        $this->groupRepo = $groupRepo;
        $this->validationRepo = $validationRepo;
    }

    /**
     * Tạo trang phục mới với validation, permission check, audit trail
     * 
     * @param array $data {TEN, GIA_THUE, ID_LOAI, SCOPE_TYPE, ID_CN_OWNER, SIZE, MAU_SAC, MO_TA, ...}
     * @param string $userId User ID từ session (CREATED_BY)
     * @param string $userRole User role (1=admin, 2=branch_manager)
     * @param int $userBranchId Branch ID nếu là branch manager
     * @return int New costume ID
     * @throws RuntimeException on validation/permission failure
     */
    public function createCostume(array $data, string $userId, string $userRole, int $userBranchId = 0): int
    {
        // Permission check: branch manager chỉ tạo local costume tại chi nhánh của mình
        $scopeType = $data['SCOPE_TYPE'] ?? 'local';
        if ($userRole === '2') {
            if ($scopeType === 'global') {
                throw new RuntimeException('Chi nhánh chỉ có thể tạo trang phục nội bộ');
            }
            $data['ID_CN_OWNER'] = $userBranchId;
        }

        // Validate required fields
        if (empty($data['TEN'])) {
            throw new RuntimeException('Tên trang phục không được để trống');
        }
        if (empty($data['ID_LOAI'])) {
            throw new RuntimeException('Loại trang phục không được để trống');
        }
        if (!isset($data['GIA_THUE']) || $data['GIA_THUE'] < 0) {
            throw new RuntimeException('Giá thuê phải >= 0');
        }

        // Validate type exists
        $type = $this->typeRepo->findById((int)$data['ID_LOAI']);
        if (!$type) {
            throw new RuntimeException('Loại trang phục không tồn tại');
        }

        // Validate group if SCOPE_TYPE is local
        if ($scopeType === 'local' && !empty($data['ID_CN_OWNER'])) {
            if ($data['ID_CN_OWNER'] <= 0) {
                throw new RuntimeException('Chi nhánh chủ quản không hợp lệ');
            }
        }

        // Validate dates if provided
        if (!empty($data['HIEU_LUC_TU']) && !empty($data['HIEU_LUC_DEN'])) {
            $startDate = strtotime($data['HIEU_LUC_TU']);
            $endDate = strtotime($data['HIEU_LUC_DEN']);
            if ($startDate === false || $endDate === false || $startDate > $endDate) {
                throw new RuntimeException('Ngày hiệu lực không hợp lệ');
            }
        }

        // Add audit columns
        $data['CREATED_BY'] = $userId;
        $data['UPDATED_BY'] = $userId;
        $data['CREATED_AT'] = date('Y-m-d H:i:s');
        $data['UPDATED_AT'] = date('Y-m-d H:i:s');
        $data['TRANG_THAI'] = $data['TRANG_THAI'] ?? 'available';

        return $this->costumeRepo->create($data);
    }

    /**
     * Cập nhật trang phục
     * 
     * @param int $costumeId ID trang phục
     * @param array $data Fields to update
     * @param string $userId User ID (UPDATED_BY)
     * @param string $userRole User role
     * @param int $userBranchId Branch ID nếu là branch manager
     * @return bool True if successful
     * @throws RuntimeException on validation/permission failure
     */
    public function updateCostume(int $costumeId, array $data, string $userId, string $userRole, int $userBranchId = 0): bool
    {
        $costume = $this->costumeRepo->findById($costumeId);
        if (!$costume) {
            throw new RuntimeException('Trang phục không tồn tại');
        }

        // Permission check: branch manager không được sửa global costume
        if ($userRole === '2') {
            if ($costume['SCOPE_TYPE'] === 'global') {
                throw new RuntimeException('Chi nhánh không có quyền sửa trang phục toàn cục');
            }
            if ($costume['ID_CN_OWNER'] !== $userBranchId) {
                throw new RuntimeException('Chi nhánh không có quyền sửa trang phục của chi nhánh khác');
            }
        }

        // Validate scope change (if trying to change scope)
        if (isset($data['SCOPE_TYPE']) && $data['SCOPE_TYPE'] !== $costume['SCOPE_TYPE']) {
            $newScope = $data['SCOPE_TYPE'];
            $targetBranch = $data['ID_CN'] ?? $data['ID_CN_OWNER'] ?? null;
            $scopeCheck = $this->costumeRepo->validateScopeChangeAllowed($costumeId, $newScope, $targetBranch);
            if (!$scopeCheck['allowed']) {
                throw new RuntimeException($scopeCheck['reason']);
            }
        }

        // Normalize branch ownership for scope
        if (isset($data['SCOPE_TYPE'])) {
            $newScope = $data['SCOPE_TYPE'];
            if ($newScope === 'global') {
                $data['ID_CN'] = null;
                $data['ID_CN_OWNER'] = null;
            } else {
                $branch = $data['ID_CN'] ?? $data['ID_CN_OWNER'] ?? $costume['ID_CN_OWNER'] ?? null;
                $data['ID_CN'] = $branch;
                $data['ID_CN_OWNER'] = $branch;
            }
        }

        // If TRANG_THAI is provided from UI (TINH_TRANG), map it over
        if (isset($data['TINH_TRANG']) && !isset($data['TRANG_THAI'])) {
            $data['TRANG_THAI'] = $data['TINH_TRANG'];
            unset($data['TINH_TRANG']);
        }

        // Validate type if changed
        if (!empty($data['ID_LOAI']) && $data['ID_LOAI'] !== $costume['ID_LOAI']) {
            $type = $this->typeRepo->findById((int)$data['ID_LOAI']);
            if (!$type) {
                throw new RuntimeException('Loại trang phục không tồn tại');
            }
        }

        // Validate dates if changed
        if (!empty($data['HIEU_LUC_TU']) || !empty($data['HIEU_LUC_DEN'])) {
            $startDate = $data['HIEU_LUC_TU'] ?? $costume['HIEU_LUC_TU'];
            $endDate = $data['HIEU_LUC_DEN'] ?? $costume['HIEU_LUC_DEN'];
            if (!empty($startDate) && !empty($endDate)) {
                $start = strtotime($startDate);
                $end = strtotime($endDate);
                if ($start === false || $end === false || $start > $end) {
                    throw new RuntimeException('Ngày hiệu lực không hợp lệ');
                }
            }
        }

        // Add audit columns
        $data['UPDATED_BY'] = $userId;
        $data['UPDATED_AT'] = date('Y-m-d H:i:s');

        return $this->costumeRepo->update($costumeId, $data);
    }

    /**
     * Đánh dấu trang phục sẵn sàng
     * 
     * @param int $costumeId ID trang phục
     * @param string $userId User ID
     * @param string $userRole User role
     * @param int $userBranchId Branch ID
     * @return bool True if successful
     */
    public function markAvailable(int $costumeId, string $userId, string $userRole, int $userBranchId = 0): bool
    {
        $costume = $this->costumeRepo->findById($costumeId);
        if (!$costume) {
            throw new RuntimeException('Trang phục không tồn tại');
        }

        // Permission check
        if ($userRole === '2' && $costume['ID_CN_OWNER'] !== $userBranchId) {
            throw new RuntimeException('Chi nhánh không có quyền sửa trang phục này');
        }

        return $this->costumeRepo->updateStatus($costumeId, 'available');
    }

    /**
     * Đánh dấu trang phục bảo trì
     * 
     * @param int $costumeId ID trang phục
     * @param string $reason Lý do bảo trì
     * @param string $userId User ID
     * @param string $userRole User role
     * @param int $userBranchId Branch ID
     * @return bool True if successful
     */
    public function markMaintenance(int $costumeId, string $reason, string $userId, string $userRole, int $userBranchId = 0): bool
    {
        $costume = $this->costumeRepo->findById($costumeId);
        if (!$costume) {
            throw new RuntimeException('Trang phục không tồn tại');
        }

        // Permission check
        if ($userRole === '2' && $costume['ID_CN_OWNER'] !== $userBranchId) {
            throw new RuntimeException('Chi nhánh không có quyền sửa trang phục này');
        }

        return $this->validationRepo->markForMaintenance($costumeId, $reason);
    }

    /**
     * Xóa trang phục (soft delete với status)
     * 
     * @param int $costumeId ID trang phục
     * @param string $userId User ID
     * @param string $userRole User role
     * @param int $userBranchId Branch ID
     * @return bool True if successful
     */
    public function deleteCostume(int $costumeId, string $userId, string $userRole, int $userBranchId = 0): bool
    {
        $costume = $this->costumeRepo->findById($costumeId);
        if (!$costume) {
            throw new RuntimeException('Trang phục không tồn tại');
        }

        // Permission check: only admin can delete
        if ($userRole !== '1') {
            throw new RuntimeException('Chỉ quản trị viên mới có thể xóa trang phục');
        }

        return $this->costumeRepo->updateStatus($costumeId, 'retired');
    }

    /**
     * Lấy chi tiết trang phục
     * 
     * @param int $costumeId ID trang phục
     * @return array|null Costume details or null
     */
    public function getCostume(int $costumeId): ?array
    {
        return $this->costumeRepo->findById($costumeId);
    }

    /**
     * Danh sách trang phục với filter
     * 
     * @param array $filters {type_id, group_id, status, scope_type, branch_id, price_min, price_max}
     * @param string $orderBy Column to order by
     * @param int $limit Items per page
     * @param int $offset Offset
     * @return array List of costumes
     */
    public function listCostumes(array $filters = [], string $orderBy = 'TEN', int $limit = 100, int $offset = 0): array
    {
        return $this->costumeRepo->findAll($filters, $orderBy, $limit, $offset);
    }

    /**
     * Tìm trang phục theo loại
     * 
     * @param int $typeId ID loại
     * @return array List of costumes
     */
    public function getCostumesByType(int $typeId): array
    {
        return $this->costumeRepo->findByType($typeId);
    }

    /**
     * Tìm trang phục theo chi nhánh
     * 
     * @param int $branchId ID chi nhánh
     * @return array List of costumes
     */
    public function getCostumesByBranch(int $branchId): array
    {
        return $this->costumeRepo->findByBranch($branchId);
    }

    /**
     * Tìm trang phục theo nhóm
     * 
     * @param int $groupId ID nhóm
     * @return array List of costumes
     */
    public function getCostumesByGroup(int $groupId): array
    {
        $filters = ['group_id' => $groupId];
        return $this->costumeRepo->findAll($filters);
    }

    /**
     * Tìm kiếm trang phục
     * 
     * @param string $keyword Từ khóa tìm kiếm
     * @param array $filters Additional filters
     * @return array Search results
     */
    public function searchCostumes(string $keyword, array $filters = []): array
    {
        return $this->costumeRepo->search($keyword, 100, 0, $filters);
    }

    /**
     * Kiểm tra trang phục có sẵn cho thuê trong khoảng thời gian
     * 
     * @param int $costumeId ID trang phục
     * @param string $startDate Ngày bắt đầu
     * @param string $endDate Ngày kết thúc
     * @return bool True if available
     */
    public function isAvailable(int $costumeId, string $startDate, string $endDate): bool
    {
        $result = $this->validationRepo->isAvailableDuring($costumeId, $startDate, $endDate);
        return $result['available'];
    }

    /**
     * Kiểm tra trang phục sẵn cho thuê (comprehensive)
     * 
     * @param int $costumeId ID trang phục
     * @param int $branchId ID chi nhánh
     * @param string $startDate Ngày bắt đầu
     * @param string $endDate Ngày kết thúc
     * @return array {can_rent: bool, issues: array}
     */
    public function checkAvailability(int $costumeId, int $branchId, string $startDate, string $endDate): array
    {
        return $this->validationRepo->validateForRental($costumeId, $branchId, $startDate, $endDate);
    }

    /**
     * Tìm trang phục sẵn sàng của một loại
     * 
     * @param int $typeId ID loại
     * @param string $startDate Ngày bắt đầu
     * @param string $endDate Ngày kết thúc
     * @return array List of available costumes
     */
    public function findAvailableByType(int $typeId, string $startDate, string $endDate): array
    {
        return $this->validationRepo->findAvailableByType($typeId, $startDate, $endDate);
    }

    /**
     * Đếm trang phục sẵn có của một loại
     * 
     * @param int $typeId ID loại
     * @param int $quantity Số lượng cần
     * @param string $startDate Ngày bắt đầu
     * @param string $endDate Ngày kết thúc
     * @return array {available: bool, available_count: int, reason: string|null}
     */
    public function countAvailableByType(int $typeId, int $quantity, string $startDate, string $endDate): array
    {
        return $this->validationRepo->countAvailableByType($typeId, $quantity, $startDate, $endDate);
    }

    /**
     * Tính giá thuê trang phục với discount
     * 
     * @param int $costumeId ID trang phục
     * @param int $days Số ngày thuê
     * @param int $discountPercent Phần trăm giảm giá
     * @return int Total rental price
     */
    public function calculateRentalPrice(int $costumeId, int $days, int $discountPercent = 0): int
    {
        $costume = $this->costumeRepo->findById($costumeId);
        if (!$costume) {
            throw new RuntimeException('Trang phục không tồn tại');
        }

        // Validate discount
        $discountPercent = max(0, min(100, (int)$discountPercent));

        $basePrice = (int)$costume['GIA_THUE'] * max(1, $days);
        $discount = (int)($basePrice * $discountPercent / 100);
        $finalPrice = $basePrice - $discount;

        return max(0, $finalPrice);
    }

    /**
     * Lấy giá cơ bản trang phục (với discount nếu có)
     * 
     * @param int $costumeId ID trang phục
     * @param int $discountPercent Optional discount percent
     * @return int Base price
     */
    public function getBasePriceWithDiscount(int $costumeId, int $discountPercent = 0): int
    {
        return $this->calculateRentalPrice($costumeId, 1, $discountPercent);
    }

    /**
     * Kiểm tra trang phục có thể cho chi nhánh khác không
     * 
     * @param int $costumeId ID trang phục
     * @param int $branchId ID chi nhánh
     * @return array {allowed: bool, reason: string|null, scope_type: string}
     */
    public function validateBranchAccess(int $costumeId, int $branchId): array
    {
        return $this->validationRepo->checkBranchAccess($costumeId, $branchId);
    }

    /**
     * Kiểm tra trang phục có hiệu lực không
     * 
     * @param int $costumeId ID trang phục
     * @param string $checkDate Ngày kiểm tra (default hôm nay)
     * @return array {valid: bool, reason: string|null}
     */
    public function checkValidity(int $costumeId, string $checkDate = ''): array
    {
        $check = $this->validationRepo->checkValidity($costumeId, $checkDate);
        return [
            'valid' => $check['valid'],
            'reason' => $check['reason']
        ];
    }

    /**
     * Lấy lịch sử cho thuê của trang phục
     * 
     * @param int $costumeId ID trang phục
     * @param int $limit Số đơn gần nhất
     * @return array Rental history
     */
    public function getRentalHistory(int $costumeId, int $limit = 10): array
    {
        return $this->validationRepo->getRentalHistory($costumeId, $limit);
    }

    /**
     * Lấy các đơn thuê xung đột
     * 
     * @param int $costumeId ID trang phục
     * @param string $startDate Ngày bắt đầu
     * @param string $endDate Ngày kết thúc
     * @return array Conflicting rentals
     */
    public function getConflictingRentals(int $costumeId, string $startDate, string $endDate): array
    {
        return $this->validationRepo->getConflictingRentals($costumeId, $startDate, $endDate);
    }

    /**
     * Đếm trang phục theo trạng thái
     * 
     * @param string $status Trạng thái (available, maintenance, retired)
     * @return int Count
     */
    public function countByStatus(string $status): int
    {
        return $this->costumeRepo->countByStatus($status);
    }
}
