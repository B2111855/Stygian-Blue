<?php

namespace App\Controllers;

use App\Services\CostumeService;
use App\Repositories\CostumeRepository;
use App\Repositories\CostumeTypeRepository;
use App\Repositories\CostumeGroupRepository;
use App\Repositories\RentalValidationRepository;
use RuntimeException;

class CostumeController
{
    private CostumeService $service;

    public function __construct(CostumeService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /api/costumes - List all costumes with filters
     * Query params: type_id, group_id, status, scope_type, branch_id, price_min, price_max, order_by, page, limit
     */
    public function index(): void
    {
        try {
            $filters = [];
            
            if (!empty($_GET['type_id'])) {
                $filters['type_id'] = (int)$_GET['type_id'];
            }
            if (!empty($_GET['group_id'])) {
                $filters['group_id'] = (int)$_GET['group_id'];
            }
            if (!empty($_GET['status'])) {
                $filters['status'] = $_GET['status'];
            }
            if (!empty($_GET['scope_type'])) {
                $filters['scope_type'] = $_GET['scope_type'];
            }
            if (!empty($_GET['branch_id'])) {
                $filters['branch_id'] = (int)$_GET['branch_id'];
            }
            if (!empty($_GET['price_min'])) {
                $filters['price_min'] = (int)$_GET['price_min'];
            }
            if (!empty($_GET['price_max'])) {
                $filters['price_max'] = (int)$_GET['price_max'];
            }

            $orderBy = $_GET['order_by'] ?? 'TEN';
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;

            $costumes = $this->service->listCostumes($filters, $orderBy, $limit, $offset);

            $this->jsonResponse([
                'success' => true,
                'data' => $costumes,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => count($costumes)
                ]
            ]);
        } catch (RuntimeException $e) {
            $this->jsonError($e->getMessage(), 400);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * GET /api/costumes/{id} - Get costume details
     */
    public function show(int $id): void
    {
        try {
            $costume = $this->service->getCostume($id);

            if (!$costume) {
                $this->jsonError('Trang phục không tồn tại', 404);
                return;
            }

            $this->jsonResponse([
                'success' => true,
                'data' => $costume
            ]);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * POST /api/costumes - Create new costume
     * Body: {TEN, GIA_THUE, ID_LOAI, SCOPE_TYPE, SIZE, MAU_SAC, MO_TA, ...}
     */
    public function store(): void
    {
        try {
            $this->requireAuth();
            $data = $this->getJsonInput();

            $userId = $_SESSION['ID_TK'] ?? '';
            $userRole = $_SESSION['ID_QUYEN'] ?? '';
            $userBranchId = $_SESSION['branch_id'] ?? 0;

            $costumeId = $this->service->createCostume(
                $data,
                $userId,
                $userRole,
                (int)$userBranchId
            );

            $this->jsonResponse([
                'success' => true,
                'message' => 'Trang phục đã được tạo',
                'data' => ['ID_TRANG_PHUC' => $costumeId]
            ], 201);
        } catch (RuntimeException $e) {
            $this->jsonError($e->getMessage(), 400);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * PUT /api/costumes/{id} - Update costume
     * Body: {TEN?, GIA_THUE?, ID_LOAI?, SIZE?, MAU_SAC?, MO_TA?, ...}
     */
    public function update(int $id): void
    {
        try {
            $this->requireAuth();
            $data = $this->getJsonInput();

            $userId = $_SESSION['ID_TK'] ?? '';
            $userRole = $_SESSION['ID_QUYEN'] ?? '';
            $userBranchId = $_SESSION['branch_id'] ?? 0;

            $success = $this->service->updateCostume(
                $id,
                $data,
                $userId,
                $userRole,
                (int)$userBranchId
            );

            if (!$success) {
                $this->jsonError('Không thể cập nhật trang phục', 400);
                return;
            }

            $this->jsonResponse([
                'success' => true,
                'message' => 'Trang phục đã được cập nhật'
            ]);
        } catch (RuntimeException $e) {
            $this->jsonError($e->getMessage(), 400);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * DELETE /api/costumes/{id} - Delete (soft delete) costume
     */
    public function destroy(int $id): void
    {
        try {
            $this->requireAuth();
            $this->requireAdmin();

            $userId = $_SESSION['ID_TK'] ?? '';
            $userRole = $_SESSION['ID_QUYEN'] ?? '';
            $userBranchId = $_SESSION['branch_id'] ?? 0;

            $success = $this->service->deleteCostume(
                $id,
                $userId,
                $userRole,
                (int)$userBranchId
            );

            if (!$success) {
                $this->jsonError('Không thể xóa trang phục', 400);
                return;
            }

            $this->jsonResponse([
                'success' => true,
                'message' => 'Trang phục đã được xóa'
            ]);
        } catch (RuntimeException $e) {
            $this->jsonError($e->getMessage(), 403);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * POST /api/costumes/{id}/available - Mark costume as available
     */
    public function markAvailable(int $id): void
    {
        try {
            $this->requireAuth();

            $userId = $_SESSION['ID_TK'] ?? '';
            $userRole = $_SESSION['ID_QUYEN'] ?? '';
            $userBranchId = $_SESSION['branch_id'] ?? 0;

            $success = $this->service->markAvailable(
                $id,
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
                'message' => 'Trang phục đã được đánh dấu sẵn sàng'
            ]);
        } catch (RuntimeException $e) {
            $this->jsonError($e->getMessage(), 400);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * POST /api/costumes/{id}/maintenance - Mark costume for maintenance
     * Body: {reason}
     */
    public function markMaintenance(int $id): void
    {
        try {
            $this->requireAuth();
            $data = $this->getJsonInput();

            $userId = $_SESSION['ID_TK'] ?? '';
            $userRole = $_SESSION['ID_QUYEN'] ?? '';
            $userBranchId = $_SESSION['branch_id'] ?? 0;
            $reason = $data['reason'] ?? '';

            $success = $this->service->markMaintenance(
                $id,
                $reason,
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
                'message' => 'Trang phục đã được đánh dấu bảo trì'
            ]);
        } catch (RuntimeException $e) {
            $this->jsonError($e->getMessage(), 400);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * GET /api/costumes/check-availability - Check costume availability
     * Query params: costume_id, branch_id, start_date, end_date
     */
    public function checkAvailability(): void
    {
        try {
            $costumeId = (int)($_GET['costume_id'] ?? 0);
            $branchId = (int)($_GET['branch_id'] ?? 0);
            $startDate = $_GET['start_date'] ?? '';
            $endDate = $_GET['end_date'] ?? '';

            if ($costumeId <= 0 || $branchId <= 0 || empty($startDate) || empty($endDate)) {
                $this->jsonError('Tham số không đủ', 400);
                return;
            }

            $result = $this->service->checkAvailability($costumeId, $branchId, $startDate, $endDate);

            $this->jsonResponse([
                'success' => true,
                'data' => $result
            ]);
        } catch (RuntimeException $e) {
            $this->jsonError($e->getMessage(), 400);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * GET /api/costumes/available-by-type - Find available costumes by type
     * Query params: type_id, start_date, end_date
     */
    public function findAvailableByType(): void
    {
        try {
            $typeId = (int)($_GET['type_id'] ?? 0);
            $startDate = $_GET['start_date'] ?? '';
            $endDate = $_GET['end_date'] ?? '';

            if ($typeId <= 0 || empty($startDate) || empty($endDate)) {
                $this->jsonError('Tham số không đủ', 400);
                return;
            }

            $costumes = $this->service->findAvailableByType($typeId, $startDate, $endDate);

            $this->jsonResponse([
                'success' => true,
                'data' => $costumes,
                'count' => count($costumes)
            ]);
        } catch (RuntimeException $e) {
            $this->jsonError($e->getMessage(), 400);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * POST /api/costumes/search - Search costumes
     * Body: {keyword, filters}
     */
    public function search(): void
    {
        try {
            $data = $this->getJsonInput();
            $keyword = $data['keyword'] ?? '';
            $filters = $data['filters'] ?? [];

            if (empty($keyword)) {
                $this->jsonError('Từ khóa tìm kiếm không được để trống', 400);
                return;
            }

            $costumes = $this->service->searchCostumes($keyword, $filters);

            $this->jsonResponse([
                'success' => true,
                'data' => $costumes,
                'count' => count($costumes)
            ]);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    // Helper methods

    private function requireAuth(): void
    {
        if (empty($_SESSION['ID_TK'])) {
            $this->jsonError('Unauthorized', 401);
            exit;
        }
    }

    private function requireAdmin(): void
    {
        if (($_SESSION['ID_QUYEN'] ?? '') !== '1') {
            $this->jsonError('Forbidden: Admin only', 403);
            exit;
        }
    }

    private function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        return is_array($data) ? $data : [];
    }

    private function jsonResponse(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    private function jsonError(string $message, int $code = 400): void
    {
        $this->jsonResponse([
            'success' => false,
            'error' => $message
        ], $code);
    }
}
