<?php

namespace App\Controllers;

use App\Services\CostumePackageService;
use RuntimeException;

class PackageController
{
    private CostumePackageService $service;

    public function __construct(CostumePackageService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /api/packages - List all costume packages
     * Query params: branch_id, page, limit, order_by
     */
    public function index(): void
    {
        try {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;

            $packages = $this->service->listPackages($limit, $offset);

            // If branch_id provided, filter
            if (!empty($_GET['branch_id'])) {
                $branchId = (int)$_GET['branch_id'];
                $packages = array_filter($packages, function($pkg) use ($branchId) {
                    return ($pkg['ID_CN_OWNER'] ?? 0) === $branchId;
                });
            }

            $this->jsonResponse([
                'success' => true,
                'data' => array_values($packages),
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => count($packages)
                ]
            ]);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * GET /api/packages/{id} - Get package details
     */
    public function show(int $id): void
    {
        try {
            $package = $this->service->getPackage($id);

            if (!$package) {
                $this->jsonError('Gói trang phục không tồn tại', 404);
                return;
            }

            $this->jsonResponse([
                'success' => true,
                'data' => $package
            ]);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * POST /api/packages - Create new costume package
     * Body: {TEN_GOI, MO_TA, DISCOUNT_PERCENT, SCOPE_TYPE}
     */
    public function store(): void
    {
        try {
            $this->requireAuth();
            $data = $this->getJsonInput();

            $userId = $_SESSION['ID_TK'] ?? '';
            $userRole = $_SESSION['ID_QUYEN'] ?? '';
            $userBranchId = $_SESSION['branch_id'] ?? 0;

            $packageId = $this->service->createPackage(
                $data,
                $userId,
                $userRole,
                (int)$userBranchId
            );

            $this->jsonResponse([
                'success' => true,
                'message' => 'Gói trang phục đã được tạo',
                'data' => ['ID_GOI' => $packageId]
            ], 201);
        } catch (RuntimeException $e) {
            $this->jsonError($e->getMessage(), 400);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * PUT /api/packages/{id} - Update costume package
     * Body: {TEN_GOI?, MO_TA?, DISCOUNT_PERCENT?}
     */
    public function update(int $id): void
    {
        try {
            $this->requireAuth();
            $data = $this->getJsonInput();

            $userId = $_SESSION['ID_TK'] ?? '';
            $userRole = $_SESSION['ID_QUYEN'] ?? '';
            $userBranchId = $_SESSION['branch_id'] ?? 0;

            $success = $this->service->updatePackage(
                $id,
                $data,
                $userId,
                $userRole,
                (int)$userBranchId
            );

            if (!$success) {
                $this->jsonError('Không thể cập nhật gói', 400);
                return;
            }

            $this->jsonResponse([
                'success' => true,
                'message' => 'Gói trang phục đã được cập nhật'
            ]);
        } catch (RuntimeException $e) {
            $this->jsonError($e->getMessage(), 400);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * DELETE /api/packages/{id} - Delete package
     */
    public function destroy(int $id): void
    {
        try {
            $this->requireAuth();

            $userId = $_SESSION['ID_TK'] ?? '';
            $userRole = $_SESSION['ID_QUYEN'] ?? '';
            $userBranchId = $_SESSION['branch_id'] ?? 0;

            $success = $this->service->deletePackage(
                $id,
                $userId,
                $userRole,
                (int)$userBranchId
            );

            if (!$success) {
                $this->jsonError('Không thể xóa gói', 400);
                return;
            }

            $this->jsonResponse([
                'success' => true,
                'message' => 'Gói trang phục đã được xóa'
            ]);
        } catch (RuntimeException $e) {
            $this->jsonError($e->getMessage(), 400);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * POST /api/packages/{id}/items - Add costume to package
     * Body: {costume_id, quantity, order, discount_percent, mandatory}
     */
    public function addItem(int $id): void
    {
        try {
            $this->requireAuth();
            $data = $this->getJsonInput();

            $userId = $_SESSION['ID_TK'] ?? '';
            $userRole = $_SESSION['ID_QUYEN'] ?? '';
            $userBranchId = $_SESSION['branch_id'] ?? 0;

            $costumeId = (int)($data['costume_id'] ?? 0);
            $quantity = (int)($data['quantity'] ?? 1);
            $order = (int)($data['order'] ?? 1);
            $discountPercent = (int)($data['discount_percent'] ?? 0);
            $mandatory = (bool)($data['mandatory'] ?? true);

            if ($costumeId <= 0) {
                $this->jsonError('Trang phục không hợp lệ', 400);
                return;
            }

            $success = $this->service->addCostumeToPackage(
                $id,
                $costumeId,
                $quantity,
                $order,
                $discountPercent,
                $mandatory,
                $userId,
                $userRole,
                (int)$userBranchId
            );

            if (!$success) {
                $this->jsonError('Không thể thêm trang phục', 400);
                return;
            }

            $this->jsonResponse([
                'success' => true,
                'message' => 'Trang phục đã được thêm vào gói'
            ]);
        } catch (RuntimeException $e) {
            $this->jsonError($e->getMessage(), 400);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * DELETE /api/packages/{id}/items/{costumeId} - Remove costume from package
     */
    public function removeItem(int $id, int $costumeId): void
    {
        try {
            $this->requireAuth();

            $userId = $_SESSION['ID_TK'] ?? '';
            $userRole = $_SESSION['ID_QUYEN'] ?? '';
            $userBranchId = $_SESSION['branch_id'] ?? 0;

            $success = $this->service->removeCostumeFromPackage(
                $id,
                $costumeId,
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
                'message' => 'Trang phục đã được xóa khỏi gói'
            ]);
        } catch (RuntimeException $e) {
            $this->jsonError($e->getMessage(), 400);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * PUT /api/packages/{id}/items/{costumeId} - Update item in package
     * Body: {quantity?, discount_percent?, mandatory?}
     */
    public function updateItem(int $id, int $costumeId): void
    {
        try {
            $this->requireAuth();
            $data = $this->getJsonInput();

            $userId = $_SESSION['ID_TK'] ?? '';
            $userRole = $_SESSION['ID_QUYEN'] ?? '';
            $userBranchId = $_SESSION['branch_id'] ?? 0;

            // Update quantity if provided
            if (isset($data['quantity'])) {
                $this->service->updateItemQuantity(
                    $id,
                    $costumeId,
                    (int)$data['quantity'],
                    $userId,
                    $userRole,
                    (int)$userBranchId
                );
            }

            // Update discount if provided
            if (isset($data['discount_percent'])) {
                $this->service->updateItemDiscount(
                    $id,
                    $costumeId,
                    (int)$data['discount_percent'],
                    $userId,
                    $userRole,
                    (int)$userBranchId
                );
            }

            // Update mandatory if provided
            if (isset($data['mandatory'])) {
                $this->service->updateItemMandatory(
                    $id,
                    $costumeId,
                    (bool)$data['mandatory'],
                    $userId,
                    $userRole,
                    (int)$userBranchId
                );
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
     * POST /api/packages/{id}/reorder - Reorder items in package
     * Body: {costume_ids: [id1, id2, id3, ...]}
     */
    public function reorderItems(int $id): void
    {
        try {
            $this->requireAuth();
            $data = $this->getJsonInput();

            $userId = $_SESSION['ID_TK'] ?? '';
            $userRole = $_SESSION['ID_QUYEN'] ?? '';
            $userBranchId = $_SESSION['branch_id'] ?? 0;

            $costumeIds = $data['costume_ids'] ?? [];
            if (empty($costumeIds) || !is_array($costumeIds)) {
                $this->jsonError('Danh sách trang phục không hợp lệ', 400);
                return;
            }

            $success = $this->service->reorderItems(
                $id,
                $costumeIds,
                $userId,
                $userRole,
                (int)$userBranchId
            );

            if (!$success) {
                $this->jsonError('Không thể sắp xếp lại', 400);
                return;
            }

            $this->jsonResponse([
                'success' => true,
                'message' => 'Thứ tự đã được cập nhật'
            ]);
        } catch (RuntimeException $e) {
            $this->jsonError($e->getMessage(), 400);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * GET /api/packages/{id}/items - Get package items
     */
    public function getItems(int $id): void
    {
        try {
            $items = $this->service->getPackageItems($id);

            $this->jsonResponse([
                'success' => true,
                'data' => $items,
                'count' => count($items)
            ]);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * GET /api/packages/{id}/check-availability - Check package availability
     * Query params: start_date, end_date
     */
    public function checkAvailability(int $id): void
    {
        try {
            $startDate = $_GET['start_date'] ?? '';
            $endDate = $_GET['end_date'] ?? '';

            if (empty($startDate) || empty($endDate)) {
                $this->jsonError('Tham số không đủ', 400);
                return;
            }

            $result = $this->service->checkPackageAvailability($id, $startDate, $endDate);

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
     * GET /api/packages/{id}/price - Calculate package price
     */
    public function getPrice(int $id): void
    {
        try {
            $price = $this->service->calculatePackagePrice($id);

            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'package_id' => $id,
                    'base_price' => $price
                ]
            ]);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * POST /api/packages/search - Search packages
     * Body: {keyword}
     */
    public function search(): void
    {
        try {
            $data = $this->getJsonInput();
            $keyword = $data['keyword'] ?? '';

            if (empty($keyword)) {
                $this->jsonError('Từ khóa tìm kiếm không được để trống', 400);
                return;
            }

            $packages = $this->service->searchPackages($keyword);

            $this->jsonResponse([
                'success' => true,
                'data' => $packages,
                'count' => count($packages)
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
