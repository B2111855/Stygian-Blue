<?php

namespace App\Services;

use App\Repositories\ServiceRepository;
use mysqli;
use RuntimeException;

class ServiceService
{
    private mysqli $connection;
    private ServiceRepository $repo;

    public function __construct(mysqli $connection, ServiceRepository $repo)
    {
        $this->connection = $connection;
        $this->repo = $repo;
    }

    /**
     * Tạo dịch vụ mới
     * 
     * @param array $data {TEN_DV, ID_DANH_MUC, MO_TA, THOI_GIAN, GIA_DICH_VU, HINH_ANH, TRANG_THAI, SCOPE_TYPE, ID_CN_OWNER}
     * @param string $userId User ID (CREATED_BY)
     * @param string $userRole User role (1=admin, 2=branch_manager)
     * @param int $userBranchId Branch ID nếu là branch manager
     * @return int New service ID
     * @throws RuntimeException on validation/permission failure
     */
    public function createService(array $data, string $userId, string $userRole, int $userBranchId = 0): int
    {
        // Permission check
        if ($userRole === '2') {
            // Branch manager chỉ tạo local services
            $data['SCOPE_TYPE'] = 'local';
            $data['ID_CN_OWNER'] = $userBranchId;
        }

        // Validate required fields
        if (empty($data['TEN_DV'])) {
            throw new RuntimeException('Tên dịch vụ không được để trống');
        }

        if (!isset($data['GIA_DICH_VU']) || $data['GIA_DICH_VU'] < 0) {
            throw new RuntimeException('Giá dịch vụ phải >= 0');
        }

        // Add audit columns
        $data['CREATED_BY'] = $userId;
        $data['UPDATED_BY'] = $userId;
        $data['CREATED_AT'] = date('Y-m-d H:i:s');
        $data['UPDATED_AT'] = date('Y-m-d H:i:s');

        return $this->repo->create($data);
    }

    /**
     * Cập nhật dịch vụ
     * 
     * @param int $serviceId ID dịch vụ
     * @param array $data Fields to update
     * @param string $userId User ID
     * @param string $userRole User role
     * @param int $userBranchId Branch ID
     * @return bool True if successful
     */
    public function updateService(int $serviceId, array $data, string $userId, string $userRole, int $userBranchId = 0): bool
    {
        $service = $this->repo->findById($serviceId);
        if (!$service) {
            throw new RuntimeException('Dịch vụ không tồn tại');
        }

        // Permission check
        if ($userRole === '2') {
            // Branch manager chỉ sửa dịch vụ local của mình hoặc global
            if ($service['SCOPE_TYPE'] === 'local' && $service['ID_CN_OWNER'] !== $userBranchId) {
                throw new RuntimeException('Chi nhánh không có quyền sửa dịch vụ của chi nhánh khác');
            }
        }

        // Validate name if changed
        if (isset($data['TEN_DV']) && empty($data['TEN_DV'])) {
            throw new RuntimeException('Tên dịch vụ không được để trống');
        }

        // Validate price if changed
        if (isset($data['GIA_DICH_VU']) && $data['GIA_DICH_VU'] < 0) {
            throw new RuntimeException('Giá dịch vụ phải >= 0');
        }

        return $this->repo->update($serviceId, $data, $userId);
    }

    /**
     * Xóa dịch vụ
     * 
     * @param int $serviceId ID dịch vụ
     * @param string $userId User ID
     * @param string $userRole User role
     * @param int $userBranchId Branch ID
     * @return bool True if successful
     */
    public function deleteService(int $serviceId, string $userId, string $userRole, int $userBranchId = 0): bool
    {
        $service = $this->repo->findById($serviceId);
        if (!$service) {
            throw new RuntimeException('Dịch vụ không tồn tại');
        }

        // Permission check
        if ($userRole === '2') {
            if ($service['SCOPE_TYPE'] === 'global') {
                throw new RuntimeException('Chi nhánh không có quyền xóa dịch vụ toàn cục');
            }
            if ($service['ID_CN_OWNER'] !== $userBranchId) {
                throw new RuntimeException('Chi nhánh không có quyền xóa dịch vụ của chi nhánh khác');
            }
        }

        // Check if can delete
        if (!$this->repo->canDelete($serviceId)) {
            throw new RuntimeException('Không thể xóa dịch vụ đang được sử dụng');
        }

        return $this->repo->delete($serviceId);
    }

    /**
     * Lấy dịch vụ theo ID
     * 
     * @param int $serviceId ID dịch vụ
     * @return array|null Service data
     */
    public function getService(int $serviceId): ?array
    {
        return $this->repo->findById($serviceId);
    }

    /**
     * Lấy danh sách dịch vụ
     * 
     * @param array $filters Filter options
     * @param string $orderBy Order by column
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array List of services
     */
    public function listServices(array $filters = [], string $orderBy = 'TEN_DV', int $limit = 100, int $offset = 0): array
    {
        return $this->repo->findAll($filters, $orderBy, $limit, $offset);
    }

    /**
     * Lấy dịch vụ của chi nhánh
     * 
     * @param int $branchId ID chi nhánh
     * @param string|null $status Status filter
     * @return array List of services
     */
    public function getServicesByBranch(int $branchId, ?string $status = 'active'): array
    {
        return $this->repo->findByBranch($branchId, $status);
    }

    /**
     * Lấy dịch vụ theo danh mục
     * 
     * @param int $categoryId ID danh mục
     * @param string|null $status Status filter
     * @return array List of services
     */
    public function getServicesByCategory(int $categoryId, ?string $status = 'active'): array
    {
        return $this->repo->findByCategory($categoryId, $status);
    }

    /**
     * Tìm kiếm dịch vụ
     * 
     * @param string $keyword Từ khóa
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array Search results
     */
    public function searchServices(string $keyword, int $limit = 50, int $offset = 0): array
    {
        return $this->repo->search($keyword, $limit, $offset);
    }

    /**
     * Cập nhật status dịch vụ
     * 
     * @param int $serviceId ID dịch vụ
     * @param string $status Trạng thái mới (active/inactive)
     * @param string $userId User ID
     * @param string $userRole User role
     * @param int $userBranchId Branch ID
     * @return bool True if successful
     */
    public function updateServiceStatus(int $serviceId, string $status, string $userId, string $userRole, int $userBranchId = 0): bool
    {
        $service = $this->repo->findById($serviceId);
        if (!$service) {
            throw new RuntimeException('Dịch vụ không tồn tại');
        }

        // Permission check
        if ($userRole === '2') {
            if ($service['SCOPE_TYPE'] === 'global') {
                throw new RuntimeException('Chi nhánh không có quyền cập nhật dịch vụ toàn cục');
            }
            if ($service['ID_CN_OWNER'] !== $userBranchId) {
                throw new RuntimeException('Chi nhánh không có quyền cập nhật dịch vụ của chi nhánh khác');
            }
        }

        return $this->repo->updateStatus($serviceId, $status, $userId);
    }

    /**
     * Đếm dịch vụ theo status
     * 
     * @param string $status Status to count
     * @return int Count
     */
    public function countServicesByStatus(string $status): int
    {
        return $this->repo->countByStatus($status);
    }

    /**
     * Đếm tất cả dịch vụ
     * 
     * @param array $filters Optional filters
     * @return int Total count
     */
    public function countServices(array $filters = []): int
    {
        return $this->repo->count($filters);
    }

    /**
     * Lấy dịch vụ theo khoảng giá
     * 
     * @param int $minPrice Giá tối thiểu
     * @param int $maxPrice Giá tối đa
     * @param string|null $status Status filter
     * @return array List of services
     */
    public function getServicesByPriceRange(int $minPrice, int $maxPrice, ?string $status = 'active'): array
    {
        return $this->repo->findByPriceRange($minPrice, $maxPrice, $status);
    }

    /**
     * Kiểm tra dịch vụ có thể xóa
     * 
     * @param int $serviceId ID dịch vụ
     * @return bool True if can delete
     */
    public function canDeleteService(int $serviceId): bool
    {
        return $this->repo->canDelete($serviceId);
    }
}
