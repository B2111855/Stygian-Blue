<?php

namespace App\Controllers;

use App\Services\ServiceService;
use RuntimeException;
use Exception;

class ServiceController
{
    private ServiceService $service;

    public function __construct(ServiceService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /api/services - List all services
     * Query params: status, scope_type, branch_id, category_id, price_min, price_max, limit, offset
     */
    public function index(): void
    {
        try {
            $filters = [];
            
            if (isset($_GET['status'])) {
                $filters['status'] = $_GET['status'];
            }
            
            if (isset($_GET['scope_type'])) {
                $filters['scope_type'] = $_GET['scope_type'];
            }
            
            if (isset($_GET['branch_id'])) {
                $filters['branch_id'] = (int)$_GET['branch_id'];
            }
            
            if (isset($_GET['category_id'])) {
                $filters['category_id'] = (int)$_GET['category_id'];
            }
            
            if (isset($_GET['price_min']) && isset($_GET['price_max'])) {
                $filters['price_min'] = (int)$_GET['price_min'];
                $filters['price_max'] = (int)$_GET['price_max'];
            }
            
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            
            $services = $this->service->listServices($filters, 'TEN_DV', $limit, $offset);
            
            $this->jsonResponse([
                'success' => true,
                'data' => $services,
                'count' => count($services),
                'limit' => $limit,
                'offset' => $offset
            ]);
        } catch (Exception $e) {
            $this->jsonError('Internal server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/services/{id} - Get service by ID
     */
    public function show(int $id): void
    {
        try {
            $service = $this->service->getService($id);
            
            if (!$service) {
                $this->jsonError('Dịch vụ không tồn tại', 404);
                return;
            }
            
            $this->jsonResponse([
                'success' => true,
                'data' => $service
            ]);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * POST /api/services - Create new service
     * Body: {TEN_DV, ID_DANH_MUC, MO_TA, THOI_GIAN, GIA_DICH_VU, HINH_ANH, SCOPE_TYPE}
     */
    public function store(): void
    {
        try {
            $this->requireAuth();
            $data = $this->getJsonInput();
            
            $userId = $_SESSION['ID_TK'] ?? '';
            $userRole = $_SESSION['ID_QUYEN'] ?? '';
            $userBranchId = $_SESSION['branch_id'] ?? 0;
            
            $serviceId = $this->service->createService(
                $data,
                $userId,
                $userRole,
                (int)$userBranchId
            );
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Dịch vụ đã được tạo',
                'data' => ['ID_DV' => $serviceId]
            ], 201);
        } catch (RuntimeException $e) {
            $this->jsonError($e->getMessage(), 400);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * PUT /api/services/{id} - Update service
     * Body: {TEN_DV, ID_DANH_MUC, MO_TA, THOI_GIAN, GIA_DICH_VU, HINH_ANH}
     */
    public function update(int $id): void
    {
        try {
            $this->requireAuth();
            $data = $this->getJsonInput();
            
            $userId = $_SESSION['ID_TK'] ?? '';
            $userRole = $_SESSION['ID_QUYEN'] ?? '';
            $userBranchId = $_SESSION['branch_id'] ?? 0;
            
            $success = $this->service->updateService(
                $id,
                $data,
                $userId,
                $userRole,
                (int)$userBranchId
            );
            
            if (!$success) {
                $this->jsonError('Không thể cập nhật dịch vụ', 400);
                return;
            }
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Dịch vụ đã được cập nhật'
            ]);
        } catch (RuntimeException $e) {
            $this->jsonError($e->getMessage(), 400);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * DELETE /api/services/{id} - Delete service
     */
    public function destroy(int $id): void
    {
        try {
            $this->requireAuth();
            
            $userId = $_SESSION['ID_TK'] ?? '';
            $userRole = $_SESSION['ID_QUYEN'] ?? '';
            $userBranchId = $_SESSION['branch_id'] ?? 0;
            
            $success = $this->service->deleteService(
                $id,
                $userId,
                $userRole,
                (int)$userBranchId
            );
            
            if (!$success) {
                $this->jsonError('Không thể xóa dịch vụ', 400);
                return;
            }
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Dịch vụ đã được xóa'
            ]);
        } catch (RuntimeException $e) {
            $this->jsonError($e->getMessage(), 400);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * PUT /api/services/{id}/status - Update service status
     * Body: {status}
     */
    public function updateStatus(int $id): void
    {
        try {
            $this->requireAuth();
            $data = $this->getJsonInput();
            
            if (!isset($data['status'])) {
                $this->jsonError('Status là bắt buộc', 400);
                return;
            }
            
            $userId = $_SESSION['ID_TK'] ?? '';
            $userRole = $_SESSION['ID_QUYEN'] ?? '';
            $userBranchId = $_SESSION['branch_id'] ?? 0;
            
            $success = $this->service->updateServiceStatus(
                $id,
                $data['status'],
                $userId,
                $userRole,
                (int)$userBranchId
            );
            
            if (!$success) {
                $this->jsonError('Không thể cập nhật trạng thái', 400);
                return;
            }
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Trạng thái dịch vụ đã được cập nhật'
            ]);
        } catch (RuntimeException $e) {
            $this->jsonError($e->getMessage(), 400);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * GET /api/services/by-branch/{branchId} - Get services by branch
     * Query params: status
     */
    public function getByBranch(int $branchId): void
    {
        try {
            $status = $_GET['status'] ?? 'active';
            
            $services = $this->service->getServicesByBranch($branchId, $status);
            
            $this->jsonResponse([
                'success' => true,
                'data' => $services,
                'count' => count($services)
            ]);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * GET /api/services/by-category/{categoryId} - Get services by category
     * Query params: status
     */
    public function getByCategory(int $categoryId): void
    {
        try {
            $status = $_GET['status'] ?? 'active';
            
            $services = $this->service->getServicesByCategory($categoryId, $status);
            
            $this->jsonResponse([
                'success' => true,
                'data' => $services,
                'count' => count($services)
            ]);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * POST /api/services/search - Search services
     * Body: {keyword, limit, offset}
     */
    public function search(): void
    {
        try {
            $data = $this->getJsonInput();
            
            $keyword = $data['keyword'] ?? '';
            $limit = $data['limit'] ?? 50;
            $offset = $data['offset'] ?? 0;
            
            $services = $this->service->searchServices($keyword, $limit, $offset);
            
            $this->jsonResponse([
                'success' => true,
                'data' => $services,
                'count' => count($services),
                'keyword' => $keyword
            ]);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * GET /api/services/count-by-status/{status} - Count services by status
     */
    public function countByStatus(string $status): void
    {
        try {
            $count = $this->service->countServicesByStatus($status);
            
            $this->jsonResponse([
                'success' => true,
                'status' => $status,
                'count' => $count
            ]);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * GET /api/services/by-price-range - Get services by price range
     * Query params: price_min, price_max, status
     */
    public function getByPriceRange(): void
    {
        try {
            if (!isset($_GET['price_min']) || !isset($_GET['price_max'])) {
                $this->jsonError('price_min và price_max là bắt buộc', 400);
                return;
            }
            
            $minPrice = (int)$_GET['price_min'];
            $maxPrice = (int)$_GET['price_max'];
            $status = $_GET['status'] ?? 'active';
            
            $services = $this->service->getServicesByPriceRange($minPrice, $maxPrice, $status);
            
            $this->jsonResponse([
                'success' => true,
                'data' => $services,
                'count' => count($services),
                'price_range' => ['min' => $minPrice, 'max' => $maxPrice]
            ]);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * GET /api/services/{id}/can-delete - Check if service can be deleted
     */
    public function canDelete(int $id): void
    {
        try {
            $canDelete = $this->service->canDeleteService($id);
            
            $this->jsonResponse([
                'success' => true,
                'service_id' => $id,
                'can_delete' => $canDelete
            ]);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * Helper: Require authentication
     */
    private function requireAuth(): void
    {
        if (!isset($_SESSION['ID_TK'])) {
            $this->jsonError('Không được phép truy cập', 401);
            exit;
        }
    }

    /**
     * Helper: Get JSON input
     */
    private function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Helper: Send JSON response
     */
    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    /**
     * Helper: Send JSON error
     */
    private function jsonError(string $message, int $statusCode = 400): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $message
        ]);
    }
}
