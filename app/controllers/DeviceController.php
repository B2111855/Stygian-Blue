<?php

namespace App\Controllers;

use App\Services\DeviceService;
use Exception;
use RuntimeException;

class DeviceController
{
    private DeviceService $service;

    public function __construct(DeviceService $service)
    {
        $this->service = $service;
    }

    public function index(): void
    {
        try {
            $filters = [
                'status' => $_GET['status'] ?? null,
                'branch_id' => isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : null,
                'search' => $_GET['search'] ?? null,
                'maintenance' => $_GET['maintenance'] ?? null
            ];

            $limit = $this->sanitizeLimit($_GET['limit'] ?? 20);
            $offset = max(0, (int)($_GET['offset'] ?? 0));
            $sort = $_GET['sort'] ?? 'id';
            $direction = $_GET['direction'] ?? 'desc';

            $result = $this->service->paginateDevices($filters, $limit, $offset, $sort, $direction);

            $this->jsonResponse([
                'success' => true,
                'data' => $result['data'],
                'meta' => [
                    'total' => $result['total'],
                    'limit' => $limit,
                    'offset' => $offset,
                    'sort' => $sort,
                    'direction' => strtolower((string)$direction) === 'asc' ? 'asc' : 'desc'
                ]
            ]);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    public function show(int $deviceId): void
    {
        try {
            $device = $this->service->getDevice($deviceId);
            if (!$device) {
                $this->jsonError('Thiết bị không tồn tại', 404);
                return;
            }

            $response = [
                'success' => true,
                'data' => $device
            ];

            if ($this->shouldInclude('price_history')) {
                $historyLimit = $this->sanitizeLimit($_GET['history_limit'] ?? 5, 50);
                $response['price_history'] = $this->service->getPriceHistory($deviceId, $historyLimit, 0);
            }

            $this->jsonResponse($response);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    public function store(): void
    {
        try {
            $this->requireAuth();
            $context = $this->getAuthContext();
            $payload = $this->getJsonInput();

            $deviceId = $this->service->createDevice(
                $payload,
                $context['userId'],
                $context['userRole'],
                $context['branchId']
            );

            $this->jsonResponse([
                'success' => true,
                'message' => 'Đã tạo thiết bị mới',
                'data' => ['ID_TB' => $deviceId]
            ], 201);
        } catch (RuntimeException $e) {
            $this->jsonError($e->getMessage(), 400);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    public function update(int $deviceId): void
    {
        try {
            $this->requireAuth();
            $context = $this->getAuthContext();
            $payload = $this->getJsonInput();

            $changed = $this->service->updateDevice(
                $deviceId,
                $payload,
                $context['userId'],
                $context['userRole'],
                $context['branchId']
            );

            $this->jsonResponse([
                'success' => true,
                'message' => $changed ? 'Đã cập nhật thiết bị' : 'Không có thay đổi nào'
            ]);
        } catch (RuntimeException $e) {
            $this->jsonError($e->getMessage(), 400);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    public function destroy(int $deviceId): void
    {
        try {
            $this->requireAuth();
            $context = $this->getAuthContext();
            $this->service->deleteDevice(
                $deviceId,
                $context['userId'],
                $context['userRole'],
                $context['branchId']
            );

            $this->jsonResponse([
                'success' => true,
                'message' => 'Đã xóa thiết bị'
            ]);
        } catch (RuntimeException $e) {
            $this->jsonError($e->getMessage(), 400);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    public function updateStatus(int $deviceId): void
    {
        try {
            $this->requireAuth();
            $context = $this->getAuthContext();
            $payload = $this->getJsonInput();

            if (!isset($payload['status'])) {
                $this->jsonError('Status là bắt buộc', 400);
                return;
            }

            $this->service->updateStatus(
                $deviceId,
                $payload['status'],
                $payload['maintenance_date'] ?? null,
                $context['userId'],
                $context['userRole'],
                $context['branchId']
            );

            $this->jsonResponse([
                'success' => true,
                'message' => 'Đã cập nhật trạng thái thiết bị'
            ]);
        } catch (RuntimeException $e) {
            $this->jsonError($e->getMessage(), 400);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    public function transfer(int $deviceId): void
    {
        try {
            $this->requireAuth();
            $context = $this->getAuthContext();
            $payload = $this->getJsonInput();

            if (!isset($payload['branch_id'])) {
                $this->jsonError('branch_id là bắt buộc', 400);
                return;
            }

            $this->service->transferDevice(
                $deviceId,
                (int)$payload['branch_id'],
                $context['userId'],
                $context['userRole']
            );

            $this->jsonResponse([
                'success' => true,
                'message' => 'Đã chuyển thiết bị sang chi nhánh mới'
            ]);
        } catch (RuntimeException $e) {
            $this->jsonError($e->getMessage(), 400);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    public function history(int $deviceId): void
    {
        try {
            $this->requireAuth();
            $this->getAuthContext();
            $limit = $this->sanitizeLimit($_GET['limit'] ?? 20, 100);
            $offset = max(0, (int)($_GET['offset'] ?? 0));

            $logs = $this->service->getAuditLogs($deviceId, $limit, $offset);

            $this->jsonResponse([
                'success' => true,
                'data' => $logs,
                'meta' => [
                    'limit' => $limit,
                    'offset' => $offset
                ]
            ]);
        } catch (RuntimeException $e) {
            $this->jsonError($e->getMessage(), 400);
        } catch (Exception $e) {
            $this->jsonError('Internal server error', 500);
        }
    }

    private function requireAuth(): void
    {
        if (!isset($_SESSION['ID_TK'])) {
            $this->jsonError('Không được phép truy cập', 401);
            exit;
        }
    }

    private function getAuthContext(): array
    {
        return [
            'userId' => $_SESSION['ID_TK'] ?? '',
            'userRole' => (string)($_SESSION['ID_QUYEN'] ?? ''),
            'branchId' => isset($_SESSION['branch_id']) ? (int)$_SESSION['branch_id'] : null
        ];
    }

    private function getJsonInput(): array
    {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw ?: '[]', true);
        return is_array($decoded) ? $decoded : [];
    }

    private function sanitizeLimit($value, int $max = 200): int
    {
        $limit = (int)$value;
        if ($limit <= 0) {
            $limit = 20;
        }
        return min($limit, $max);
    }

    private function shouldInclude(string $section): bool
    {
        if (!isset($_GET['include'])) {
            return false;
        }
        $parts = array_map('trim', explode(',', (string)$_GET['include']));
        return in_array($section, $parts, true);
    }

    private function jsonResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    private function jsonError(string $message, int $statusCode): void
    {
        $this->jsonResponse([
            'success' => false,
            'error' => $message
        ], $statusCode);
    }
}
