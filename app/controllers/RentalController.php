<?php

namespace App\Controllers;

use App\Services\RentalService;
use RuntimeException;

class RentalController
{
    private RentalService $service;

    public function __construct(RentalService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /api/rentals/{id} - Get rental details
     */
    public function show(int $id): void
    {
        try {
            $rental = $this->service->getRental($id);

            if (!$rental) {
                $this->jsonError('Đơn thuê không tồn tại', 404);
                return;
            }

            $this->jsonResponse([
                'success' => true,
                'data' => $rental
            ]);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * POST /api/rentals/costume - Create costume-only rental
     * Body: {costume_id, customer_id, start_date, end_date, branch_id, quantity, deposit}
     */
    public function createCostumeRental(): void
    {
        try {
            $this->requireAuth();
            $data = $this->getJsonInput();

            $userId = $_SESSION['ID_TK'] ?? '';

            $costumeId = (int)($data['costume_id'] ?? 0);
            $customerId = (int)($data['customer_id'] ?? 0);
            $startDate = $data['start_date'] ?? '';
            $endDate = $data['end_date'] ?? '';
            $branchId = (int)($data['branch_id'] ?? 0);
            $quantity = (int)($data['quantity'] ?? 1);
            $deposit = (int)($data['deposit'] ?? 0);

            if ($costumeId <= 0 || $customerId <= 0 || $branchId <= 0 || empty($startDate) || empty($endDate)) {
                $this->jsonError('Tham số không đủ', 400);
                return;
            }

            $rentalId = $this->service->createCostumeRental(
                $costumeId,
                $customerId,
                $startDate,
                $endDate,
                $branchId,
                $quantity,
                $deposit,
                $userId
            );

            $this->jsonResponse([
                'success' => true,
                'message' => 'Đơn thuê đã được tạo',
                'data' => ['ID_TTP' => $rentalId]
            ], 201);
        } catch (RuntimeException $e) {
            $this->jsonError($e->getMessage(), 400);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * POST /api/rentals/package - Create package rental
     * Body: {package_id, customer_id, start_date, end_date, branch_id, selected_costume_ids, deposit}
     */
    public function createPackageRental(): void
    {
        try {
            $this->requireAuth();
            $data = $this->getJsonInput();

            $userId = $_SESSION['ID_TK'] ?? '';

            $packageId = (int)($data['package_id'] ?? 0);
            $customerId = (int)($data['customer_id'] ?? 0);
            $startDate = $data['start_date'] ?? '';
            $endDate = $data['end_date'] ?? '';
            $branchId = (int)($data['branch_id'] ?? 0);
            $selectedCostumeIds = $data['selected_costume_ids'] ?? [];
            $deposit = (int)($data['deposit'] ?? 0);

            if ($packageId <= 0 || $customerId <= 0 || $branchId <= 0 || empty($startDate) || empty($endDate)) {
                $this->jsonError('Tham số không đủ', 400);
                return;
            }

            $rentalId = $this->service->createPackageRental(
                $packageId,
                $customerId,
                $startDate,
                $endDate,
                $branchId,
                is_array($selectedCostumeIds) ? $selectedCostumeIds : [],
                $deposit,
                $userId
            );

            $this->jsonResponse([
                'success' => true,
                'message' => 'Đơn thuê gói đã được tạo',
                'data' => ['ID_TTP' => $rentalId]
            ], 201);
        } catch (RuntimeException $e) {
            $this->jsonError($e->getMessage(), 400);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * POST /api/rentals/{id}/confirm - Confirm rental (pending → confirmed)
     */
    public function confirmRental(int $id): void
    {
        try {
            $this->requireAuth();

            $userId = $_SESSION['ID_TK'] ?? '';

            $success = $this->service->confirmRental($id, $userId);

            if (!$success) {
                $this->jsonError('Không thể xác nhận đơn thuê', 400);
                return;
            }

            $this->jsonResponse([
                'success' => true,
                'message' => 'Đơn thuê đã được xác nhận'
            ]);
        } catch (RuntimeException $e) {
            $this->jsonError($e->getMessage(), 400);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * POST /api/rentals/{id}/return - Process costume return
     * Body: {actual_return_date, final_amount}
     */
    public function processReturn(int $id): void
    {
        try {
            $this->requireAuth();
            $data = $this->getJsonInput();

            $userId = $_SESSION['ID_TK'] ?? '';
            $actualReturnDate = $data['actual_return_date'] ?? date('Y-m-d H:i:s');
            $finalAmount = (int)($data['final_amount'] ?? 0);

            $result = $this->service->processReturn(
                $id,
                $actualReturnDate,
                $finalAmount,
                $userId
            );

            $this->jsonResponse([
                'success' => true,
                'message' => 'Đơn thuê đã được xử lý trả',
                'data' => $result
            ]);
        } catch (RuntimeException $e) {
            $this->jsonError($e->getMessage(), 400);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * POST /api/rentals/{id}/cancel - Cancel rental
     * Body: {reason}
     */
    public function cancelRental(int $id): void
    {
        try {
            $this->requireAuth();
            $data = $this->getJsonInput();

            $userId = $_SESSION['ID_TK'] ?? '';
            $reason = $data['reason'] ?? '';

            $success = $this->service->cancelRental($id, $reason, $userId);

            if (!$success) {
                $this->jsonError('Không thể hủy đơn thuê', 400);
                return;
            }

            $this->jsonResponse([
                'success' => true,
                'message' => 'Đơn thuê đã được hủy'
            ]);
        } catch (RuntimeException $e) {
            $this->jsonError($e->getMessage(), 400);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * GET /api/rentals/customer/{customerId} - Get customer rental history
     * Query params: limit
     */
    public function getCustomerRentals(int $customerId): void
    {
        try {
            $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));

            $rentals = $this->service->getCustomerRentals($customerId, $limit);

            $this->jsonResponse([
                'success' => true,
                'data' => $rentals,
                'count' => count($rentals)
            ]);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * GET /api/rentals/overdue - Get overdue rentals
     */
    public function getOverdueRentals(): void
    {
        try {
            $this->requireAuth();

            $rentals = $this->service->getOverdueRentals();

            $this->jsonResponse([
                'success' => true,
                'data' => $rentals,
                'count' => count($rentals)
            ]);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * GET /api/rentals/available - Find available costumes for rental
     * Query params: type_id, start_date, end_date
     */
    public function findAvailableCostumes(): void
    {
        try {
            $typeId = (int)($_GET['type_id'] ?? 0);
            $startDate = $_GET['start_date'] ?? '';
            $endDate = $_GET['end_date'] ?? '';

            if ($typeId <= 0 || empty($startDate) || empty($endDate)) {
                $this->jsonError('Tham số không đủ', 400);
                return;
            }

            $costumes = $this->service->findAvailableCostumes($typeId, $startDate, $endDate);

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
     * GET /api/rentals/detect-conflicts - Detect rental conflicts
     * Query params: costume_id, start_date, end_date
     */
    public function detectConflicts(): void
    {
        try {
            $costumeId = (int)($_GET['costume_id'] ?? 0);
            $startDate = $_GET['start_date'] ?? '';
            $endDate = $_GET['end_date'] ?? '';

            if ($costumeId <= 0 || empty($startDate) || empty($endDate)) {
                $this->jsonError('Tham số không đủ', 400);
                return;
            }

            $conflicts = $this->service->detectConflicts($costumeId, $startDate, $endDate);

            $this->jsonResponse([
                'success' => true,
                'data' => $conflicts,
                'has_conflicts' => count($conflicts) > 0
            ]);
        } catch (RuntimeException $e) {
            $this->jsonError($e->getMessage(), 400);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * POST /api/rentals/calculate-cost - Calculate rental cost
     * Body: {costume_id, start_date, end_date, quantity}
     */
    public function calculateCost(): void
    {
        try {
            $data = $this->getJsonInput();

            $costumeId = (int)($data['costume_id'] ?? 0);
            $startDate = $data['start_date'] ?? '';
            $endDate = $data['end_date'] ?? '';
            $quantity = (int)($data['quantity'] ?? 1);

            if ($costumeId <= 0 || empty($startDate) || empty($endDate)) {
                $this->jsonError('Tham số không đủ', 400);
                return;
            }

            $cost = $this->service->calculateRentalCost($costumeId, $startDate, $endDate, $quantity);

            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'costume_id' => $costumeId,
                    'quantity' => $quantity,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'total_cost' => $cost
                ]
            ]);
        } catch (RuntimeException $e) {
            $this->jsonError($e->getMessage(), 400);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * POST /api/rentals/calculate-late-fee - Calculate late fee
     * Body: {overday_count}
     */
    public function calculateLateFee(): void
    {
        try {
            $data = $this->getJsonInput();

            $overdayCount = (int)($data['overday_count'] ?? 0);

            if ($overdayCount < 0) {
                $this->jsonError('Số ngày trễ không hợp lệ', 400);
                return;
            }

            $lateFee = $this->service->calculateLateFee($overdayCount);

            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'overday_count' => $overdayCount,
                    'late_fee' => $lateFee,
                    'fee_per_day' => 50000
                ]
            ]);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    /**
     * GET /api/rentals/count-by-status - Count rentals by status
     * Query params: status
     */
    public function countByStatus(): void
    {
        try {
            $status = $_GET['status'] ?? 'pending';

            $count = $this->service->countByStatus($status);

            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'status' => $status,
                    'count' => $count
                ]
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
