<?php

namespace App\Services;

use App\Repositories\CostumePackageMasterRepository;
use App\Repositories\PackageCostumeRepository;
use App\Repositories\CostumeRepository;
use App\Repositories\RentalValidationRepository;
use mysqli;
use RuntimeException;

class CostumePackageService
{
    private mysqli $connection;
    private CostumePackageMasterRepository $masterRepo;
    private PackageCostumeRepository $pivotRepo;
    private CostumeRepository $costumeRepo;
    private RentalValidationRepository $validationRepo;

    public function __construct(
        mysqli $connection,
        CostumePackageMasterRepository $masterRepo,
        PackageCostumeRepository $pivotRepo,
        CostumeRepository $costumeRepo,
        RentalValidationRepository $validationRepo
    ) {
        $this->connection = $connection;
        $this->masterRepo = $masterRepo;
        $this->pivotRepo = $pivotRepo;
        $this->costumeRepo = $costumeRepo;
        $this->validationRepo = $validationRepo;
    }

    /**
     * Tạo gói trang phục mới
     * 
     * @param array $data {TEN_GOI, MO_TA, DISCOUNT_PERCENT, SCOPE_TYPE, ID_CN_OWNER}
     * @param string $userId User ID (CREATED_BY)
     * @param string $userRole User role (1=admin, 2=branch_manager)
     * @param int $userBranchId Branch ID nếu là branch manager
     * @return int New package ID
     * @throws RuntimeException on validation/permission failure
     */
    public function createPackage(array $data, string $userId, string $userRole, int $userBranchId = 0): int
    {
        // Permission check
        if ($userRole === '2') {
            $data['SCOPE_TYPE'] = 'local';
            $data['ID_CN_OWNER'] = $userBranchId;
        }

        // Validate required fields
        if (empty($data['TEN_GOI'])) {
            throw new RuntimeException('Tên gói không được để trống');
        }

        // Validate discount
        $discountPercent = isset($data['DISCOUNT_PERCENT']) ? (int)$data['DISCOUNT_PERCENT'] : 0;
        if ($discountPercent < 0 || $discountPercent > 100) {
            throw new RuntimeException('Phần trăm giảm giá phải từ 0 đến 100');
        }

        $data['DISCOUNT_PERCENT'] = $discountPercent;
        $data['SCOPE_TYPE'] = $data['SCOPE_TYPE'] ?? 'global';
        $data['CREATED_BY'] = $userId;
        $data['UPDATED_BY'] = $userId;
        $data['CREATED_AT'] = date('Y-m-d H:i:s');
        $data['UPDATED_AT'] = date('Y-m-d H:i:s');

        return $this->masterRepo->create($data);
    }

    /**
     * Cập nhật gói trang phục
     * 
     * @param int $packageId ID gói
     * @param array $data Fields to update
     * @param string $userId User ID
     * @param string $userRole User role
     * @param int $userBranchId Branch ID
     * @return bool True if successful
     */
    public function updatePackage(int $packageId, array $data, string $userId, string $userRole, int $userBranchId = 0): bool
    {
        $package = $this->masterRepo->findById($packageId);
        if (!$package) {
            throw new RuntimeException('Gói trang phục không tồn tại');
        }

        // Permission check
        if ($userRole === '2') {
            if ($package['SCOPE_TYPE'] === 'global') {
                throw new RuntimeException('Chi nhánh không có quyền sửa gói toàn cục');
            }
            if ($package['ID_CN_OWNER'] !== $userBranchId) {
                throw new RuntimeException('Chi nhánh không có quyền sửa gói của chi nhánh khác');
            }
        }

        // Validate discount if changed
        if (isset($data['DISCOUNT_PERCENT'])) {
            $discount = (int)$data['DISCOUNT_PERCENT'];
            if ($discount < 0 || $discount > 100) {
                throw new RuntimeException('Phần trăm giảm giá phải từ 0 đến 100');
            }
        }

        $data['UPDATED_BY'] = $userId;
        $data['UPDATED_AT'] = date('Y-m-d H:i:s');

        return $this->masterRepo->update($packageId, $data);
    }

    /**
     * Xóa gói trang phục
     * 
     * @param int $packageId ID gói
     * @param string $userId User ID
     * @param string $userRole User role
     * @param int $userBranchId Branch ID
     * @return bool True if successful
     * @throws RuntimeException if package has items or is in use
     */
    public function deletePackage(int $packageId, string $userId, string $userRole, int $userBranchId = 0): bool
    {
        $package = $this->masterRepo->findById($packageId);
        if (!$package) {
            throw new RuntimeException('Gói trang phục không tồn tại');
        }

        // Permission check
        if ($userRole === '2') {
            if ($package['SCOPE_TYPE'] === 'global') {
                throw new RuntimeException('Chi nhánh không có quyền xóa gói toàn cục');
            }
            if ($package['ID_CN_OWNER'] !== $userBranchId) {
                throw new RuntimeException('Chi nhánh không có quyền xóa gói của chi nhánh khác');
            }
        }

        // Check if package can be deleted
        $can = $this->masterRepo->canDelete($packageId);
        if (!(is_array($can) ? ($can['can_delete'] ?? false) : (bool)$can)) {
            $reason = is_array($can) ? ($can['reason'] ?? 'Gói trang phục đang được sử dụng hoặc chứa trang phục. Không thể xóa') : 'Gói trang phục đang được sử dụng hoặc chứa trang phục. Không thể xóa';
            throw new RuntimeException($reason);
        }

        return $this->masterRepo->delete($packageId);
    }

    /**
     * Lấy chi tiết gói
     * 
     * @param int $packageId ID gói
     * @return array|null Package details or null
     */
    public function getPackage(int $packageId): ?array
    {
        return $this->masterRepo->findById($packageId);
    }

    /**
     * Danh sách gói trang phục
     * 
     * @param int $limit Items per page
     * @param int $offset Offset
     * @return array List of packages
     */
    public function listPackages(int $limit = 100, int $offset = 0): array
    {
        // Use paginated list and filter active by default
        return $this->masterRepo->listAll(null, null, $limit, $offset, 'active');
    }

    /**
     * Lấy gói theo chi nhánh
     * 
     * @param int $branchId ID chi nhánh
     * @return array List of packages
     */
    public function getPackagesByBranch(int $branchId): array
    {
        return $this->masterRepo->findByBranch($branchId);
    }

    /**
     * Tìm kiếm gói
     * 
     * @param string $keyword Từ khóa
     * @return array Search results
     */
    public function searchPackages(string $keyword): array
    {
        return $this->masterRepo->search($keyword);
    }

    /**
     * Thêm trang phục vào gói
     * 
     * @param int $packageId ID gói
     * @param int $costumeId ID trang phục
     * @param int $quantity Số lượng
     * @param int $order Thứ tự
     * @param int $discountPercent Giảm giá
     * @param bool $mandatory Bắt buộc hay không
     * @param string $userId User ID
     * @param string $userRole User role
     * @param int $userBranchId Branch ID
     * @return bool True if successful
     */
    public function addCostumeToPackage(
        int $packageId,
        int $costumeId,
        int $quantity,
        int $order,
        int $discountPercent,
        bool $mandatory,
        string $userId,
        string $userRole,
        int $userBranchId = 0
    ): bool {
        // Validate package exists
        $package = $this->masterRepo->findById($packageId);
        if (!$package) {
            throw new RuntimeException('Gói trang phục không tồn tại');
        }

        // Permission check
        if ($userRole === '2' && $package['ID_CN_OWNER'] !== $userBranchId) {
            throw new RuntimeException('Chi nhánh không có quyền sửa gói này');
        }

        // Validate costume exists
        $costume = $this->costumeRepo->findById($costumeId);
        if (!$costume) {
            throw new RuntimeException('Trang phục không tồn tại');
        }

        // Validate costume not retired
        if ($costume['TRANG_THAI'] === 'retired') {
            throw new RuntimeException('Không thể thêm trang phục đã bị loại');
        }

        // Validate quantity
        if ($quantity < 1) {
            throw new RuntimeException('Số lượng phải >= 1');
        }

        // Validate discount
        $discountPercent = max(0, min(100, $discountPercent));

        // Validate order
        if ($order < 1) {
            throw new RuntimeException('Thứ tự phải >= 1');
        }

        return $this->pivotRepo->add(
            $packageId,
            $costumeId,
            $quantity,
            $order,
            $discountPercent,
            $mandatory ? 1 : 0,
            '',
            $userId
        );
    }

    /**
     * Xóa trang phục khỏi gói
     * 
     * @param int $packageId ID gói
     * @param int $costumeId ID trang phục
     * @param string $userId User ID
     * @param string $userRole User role
     * @param int $userBranchId Branch ID
     * @return bool True if successful
     */
    public function removeCostumeFromPackage(
        int $packageId,
        int $costumeId,
        string $userId,
        string $userRole,
        int $userBranchId = 0
    ): bool {
        $package = $this->masterRepo->findById($packageId);
        if (!$package) {
            throw new RuntimeException('Gói trang phục không tồn tại');
        }

        // Permission check
        if ($userRole === '2' && $package['ID_CN_OWNER'] !== $userBranchId) {
            throw new RuntimeException('Chi nhánh không có quyền sửa gói này');
        }

        return $this->pivotRepo->remove($packageId, $costumeId);
    }

    /**
     * Cập nhật số lượng trang phục trong gói
     * 
     * @param int $packageId ID gói
     * @param int $costumeId ID trang phục
     * @param int $quantity Số lượng mới
     * @param string $userId User ID
     * @param string $userRole User role
     * @param int $userBranchId Branch ID
     * @return bool True if successful
     */
    public function updateItemQuantity(
        int $packageId,
        int $costumeId,
        int $quantity,
        string $userId,
        string $userRole,
        int $userBranchId = 0
    ): bool {
        $package = $this->masterRepo->findById($packageId);
        if (!$package) {
            throw new RuntimeException('Gói trang phục không tồn tại');
        }

        if ($userRole === '2' && $package['ID_CN_OWNER'] !== $userBranchId) {
            throw new RuntimeException('Chi nhánh không có quyền sửa gói này');
        }

        if ($quantity < 1) {
            throw new RuntimeException('Số lượng phải >= 1');
        }

        return $this->pivotRepo->updateQuantity($packageId, $costumeId, $quantity);
    }

    /**
     * Cập nhật discount cho trang phục trong gói
     * 
     * @param int $packageId ID gói
     * @param int $costumeId ID trang phục
     * @param int $discountPercent Phần trăm giảm
     * @param string $userId User ID
     * @param string $userRole User role
     * @param int $userBranchId Branch ID
     * @return bool True if successful
     */
    public function updateItemDiscount(
        int $packageId,
        int $costumeId,
        int $discountPercent,
        string $userId,
        string $userRole,
        int $userBranchId = 0
    ): bool {
        $package = $this->masterRepo->findById($packageId);
        if (!$package) {
            throw new RuntimeException('Gói trang phục không tồn tại');
        }

        if ($userRole === '2' && $package['ID_CN_OWNER'] !== $userBranchId) {
            throw new RuntimeException('Chi nhánh không có quyền sửa gói này');
        }

        $discountPercent = max(0, min(100, $discountPercent));
        return $this->pivotRepo->updateDiscount($packageId, $costumeId, $discountPercent);
    }

    /**
     * Cập nhật flag bắt buộc cho trang phục
     * 
     * @param int $packageId ID gói
     * @param int $costumeId ID trang phục
     * @param bool $mandatory Có bắt buộc hay không
     * @param string $userId User ID
     * @param string $userRole User role
     * @param int $userBranchId Branch ID
     * @return bool True if successful
     */
    public function updateItemMandatory(
        int $packageId,
        int $costumeId,
        bool $mandatory,
        string $userId,
        string $userRole,
        int $userBranchId = 0
    ): bool {
        $package = $this->masterRepo->findById($packageId);
        if (!$package) {
            throw new RuntimeException('Gói trang phục không tồn tại');
        }

        if ($userRole === '2' && $package['ID_CN_OWNER'] !== $userBranchId) {
            throw new RuntimeException('Chi nhánh không có quyền sửa gói này');
        }

        return $this->pivotRepo->updateMandatory($packageId, $costumeId, $mandatory ? 1 : 0);
    }

    /**
     * Sắp xếp lại thứ tự trang phục trong gói
     * 
     * @param int $packageId ID gói
     * @param array $costumeIds Danh sách ID trang phục theo thứ tự mới
     * @param string $userId User ID
     * @param string $userRole User role
     * @param int $userBranchId Branch ID
     * @return bool True if successful
     */
    public function reorderItems(
        int $packageId,
        array $costumeIds,
        string $userId,
        string $userRole,
        int $userBranchId = 0
    ): bool {
        $package = $this->masterRepo->findById($packageId);
        if (!$package) {
            throw new RuntimeException('Gói trang phục không tồn tại');
        }

        if ($userRole === '2' && $package['ID_CN_OWNER'] !== $userBranchId) {
            throw new RuntimeException('Chi nhánh không có quyền sửa gói này');
        }

        if (empty($costumeIds)) {
            throw new RuntimeException('Danh sách trang phục không được để trống');
        }

        return $this->pivotRepo->reorder($packageId, $costumeIds);
    }

    /**
     * Kiểm tra gói có sẵn cho khoảng thời gian không
     * 
     * @param int $packageId ID gói
     * @param string $startDate Ngày bắt đầu
     * @param string $endDate Ngày kết thúc
     * @return array {available: bool, available_items: int, total_items: int, missing: array}
     */
    public function checkPackageAvailability(int $packageId, string $startDate, string $endDate): array
    {
        return $this->validationRepo->isPackageAvailable($packageId, $startDate, $endDate);
    }

    /**
     * Tính giá gói trang phục
     * 
     * @param int $packageId ID gói
     * @return int Total package price
     */
    public function calculatePackagePrice(int $packageId): int
    {
        $res = $this->masterRepo->calculatePackagePrice($packageId);
        return (int)($res['final_price'] ?? 0);
    }

    /**
     * Tính giá gói với giảm giá
     * 
     * @param int $packageId ID gói
     * @param int $additionalDiscount Phần trăm giảm thêm
     * @return int Total price with discount
     */
    public function calculatePackagePriceWithDiscount(int $packageId, int $additionalDiscount = 0): int
    {
        $basePrice = $this->calculatePackagePrice($packageId);
        $package = $this->masterRepo->findById($packageId);
        
        if (!$package) {
            return $basePrice;
        }

        // Apply package discount + additional discount
        $totalDiscount = min(100, ((int)$package['DISCOUNT_PERCENT'] ?? 0) + $additionalDiscount);
        $discount = (int)($basePrice * $totalDiscount / 100);
        
        return max(0, $basePrice - $discount);
    }

    /**
     * Lấy trang phục bắt buộc trong gói
     * 
     * @param int $packageId ID gói
     * @return array List of mandatory costumes
     */
    public function getMandatoryCostumes(int $packageId): array
    {
        return $this->pivotRepo->getMandatoryCostumes($packageId);
    }

    /**
     * Lấy trang phục tùy chọn trong gói
     * 
     * @param int $packageId ID gói
     * @return array List of optional costumes
     */
    public function getOptionalCostumes(int $packageId): array
    {
        return $this->pivotRepo->getOptionalCostumes($packageId);
    }

    /**
     * Lấy tất cả trang phục trong gói
     * 
     * @param int $packageId ID gói
     * @return array List of all costumes
     */
    public function getPackageItems(int $packageId): array
    {
        return $this->pivotRepo->findByPackage($packageId);
    }

    /**
     * Lấy tất cả trang phục với chi tiết giá
     * 
     * @param int $packageId ID gói
     * @return array List with pricing info
     */
    public function getPackageItemsWithPricing(int $packageId): array
    {
        return $this->pivotRepo->findByPackageWithPricing($packageId);
    }

    /**
     * Đếm trang phục trong gói
     * 
     * @param int $packageId ID gói
     * @return int Count of costumes
     */
    public function countItems(int $packageId): int
    {
        return $this->masterRepo->countCostumes($packageId);
    }

    /**
     * Đếm trang phục bắt buộc trong gói
     * 
     * @param int $packageId ID gói
     * @return int Count of mandatory costumes
     */
    public function countMandatoryItems(int $packageId): int
    {
        return $this->masterRepo->countMandatoryCostumes($packageId);
    }

    /**
     * Đếm trang phục tùy chọn trong gói
     * 
     * @param int $packageId ID gói
     * @return int Count of optional costumes
     */
    public function countOptionalItems(int $packageId): int
    {
        return $this->masterRepo->countOptionalCostumes($packageId);
    }

    /**
     * Kiểm tra gói có thể xóa được không
     * 
     * @param int $packageId ID gói
     * @return bool True if can delete
     */
    public function canDeletePackage(int $packageId): bool
    {
        $res = $this->masterRepo->canDelete($packageId);
        return is_array($res) ? (bool)($res['can_delete'] ?? false) : (bool)$res;
    }

    /**
     * Cập nhật status gói
     * 
     * @param int $packageId ID gói
     * @param string $status Status (active/inactive)
     * @param string $userId User ID
     * @param string $userRole User role
     * @param int $userBranchId Branch ID
     * @return bool True if successful
     */
    public function updateStatus(
        int $packageId,
        string $status,
        string $userId,
        string $userRole,
        int $userBranchId = 0
    ): bool {
        $package = $this->masterRepo->findById($packageId);
        if (!$package) {
            throw new RuntimeException('Gói trang phục không tồn tại');
        }

        if ($userRole === '2' && $package['ID_CN_OWNER'] !== $userBranchId) {
            throw new RuntimeException('Chi nhánh không có quyền sửa gói này');
        }

        return $this->masterRepo->updateStatus($packageId, $status);
    }
}
