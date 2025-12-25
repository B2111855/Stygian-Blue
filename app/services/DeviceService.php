<?php

namespace App\Services;

use App\Repositories\DeviceRepository;
use DateTime;
use mysqli;
use RuntimeException;
use function \record_system_log;

class DeviceService
{
    private mysqli $connection;
    private DeviceRepository $repo;
    private array $managerBranchCache = [];

    public function __construct(mysqli $connection, DeviceRepository $repo)
    {
        $this->connection = $connection;
        $this->repo = $repo;
    }

    public function paginateDevices(
        array $filters,
        int $limit,
        int $offset,
        string $sort,
        string $direction
    ): array {
        $normalized = $this->normalizeFilters($filters);
        $data = $this->repo->findAll($normalized, $limit, $offset, $sort, $direction);
        $total = $this->repo->count($normalized);

        return [
            'data' => $data,
            'total' => $total
        ];
    }

    public function getDevice(int $deviceId): ?array
    {
        return $this->repo->findById($deviceId);
    }

    public function getPriceHistory(int $deviceId, int $limit = 10, int $offset = 0): array
    {
        return $this->repo->getPriceHistory($deviceId, $limit, $offset);
    }

    public function getAuditLogs(int $deviceId, int $limit = 20, int $offset = 0): array
    {
        $subject = $this->buildSubject($deviceId);
        $stmt = $this->connection->prepare(
            'SELECT ID_LOG, ACTOR_ID, VAI_TRO, HANH_DONG, TRUOC_JSON, SAU_JSON, IP, USER_AGENT, CREATED_AT '
            . 'FROM nhat_ky_he_thong WHERE DOI_TUONG = ? ORDER BY CREATED_AT DESC LIMIT ? OFFSET ?'
        );

        if (!$stmt) {
            throw new RuntimeException('Không thể lấy lịch sử audit: ' . $this->connection->error);
        }

        $stmt->bind_param('sii', $subject, $limit, $offset);
        if (!$stmt->execute()) {
            throw new RuntimeException('Không thể truy vấn audit log: ' . $stmt->error);
        }

        $result = $stmt->get_result();
        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $row['TRUOC_JSON'] = $row['TRUOC_JSON'] ? json_decode($row['TRUOC_JSON'], true) : null;
            $row['SAU_JSON'] = $row['SAU_JSON'] ? json_decode($row['SAU_JSON'], true) : null;
            $logs[] = $row;
        }
        $stmt->close();

        return $logs;
    }

    public function createDevice(
        array $payload,
        string $userId,
        string $userRole,
        ?int $userBranchId = null
    ): int {
        $this->assertCanManage($userRole);
        $normalized = $this->normalizeCreatePayload($payload, $userRole, $userBranchId, $userId);
        $this->ensureBranchExists($normalized['db']['ID_CN']);

        $deviceId = $this->repo->create($normalized['db']);

        if ($normalized['price'] !== null) {
            $this->repo->recordPrice($deviceId, $normalized['price']);
        }

        $this->logAction('DEVICE_CREATE', $deviceId, null, $this->repo->findById($deviceId), $userId, $userRole);

        return $deviceId;
    }

    public function updateDevice(
        int $deviceId,
        array $payload,
        string $userId,
        string $userRole,
        ?int $userBranchId = null
    ): bool {
        $this->assertCanManage($userRole);
        $existing = $this->repo->findById($deviceId);
        if (!$existing) {
            throw new RuntimeException('Thiết bị không tồn tại');
        }

        $this->ensureManagerOwnsDevice($existing, $userRole, $userBranchId, $userId);
        $normalized = $this->normalizeUpdatePayload($payload, $userRole, $userBranchId, $userId, $existing);

        $changed = false;

        if (!empty($normalized['db'])) {
            $this->repo->update($deviceId, $normalized['db']);
            $changed = true;
        }

        if ($normalized['price'] !== null) {
            $this->repo->recordPrice($deviceId, $normalized['price']);
            $changed = true;
        }

        if ($changed) {
            $latest = $this->repo->findById($deviceId);
            $this->logAction('DEVICE_UPDATE', $deviceId, $existing, $latest, $userId, $userRole);
        }

        return $changed;
    }

    public function updateStatus(
        int $deviceId,
        string $status,
        ?string $maintenanceDate,
        string $userId,
        string $userRole,
        ?int $userBranchId = null
    ): bool {
        return $this->updateDevice(
            $deviceId,
            [
                'status' => $status,
                'maintenance_date' => $maintenanceDate
            ],
            $userId,
            $userRole,
            $userBranchId
        );
    }

    public function transferDevice(int $deviceId, int $targetBranchId, string $userId, string $userRole): bool
    {
        if ($userRole !== '1') {
            throw new RuntimeException('Chỉ quản trị viên mới được chuyển thiết bị giữa các chi nhánh');
        }
        if ($targetBranchId <= 0) {
            throw new RuntimeException('Chi nhánh mục tiêu không hợp lệ');
        }

        $existing = $this->repo->findById($deviceId);
        if (!$existing) {
            throw new RuntimeException('Thiết bị không tồn tại');
        }

        if ((int)$existing['ID_CN'] === $targetBranchId) {
            return true;
        }

        $this->ensureBranchExists($targetBranchId);
        $this->repo->transfer($deviceId, $targetBranchId);
        $latest = $this->repo->findById($deviceId);
        $this->logAction('DEVICE_TRANSFER', $deviceId, $existing, $latest, $userId, $userRole);

        return true;
    }

    public function deleteDevice(
        int $deviceId,
        string $userId,
        string $userRole,
        ?int $userBranchId = null
    ): bool {
        $this->assertCanManage($userRole);
        $existing = $this->repo->findById($deviceId);
        if (!$existing) {
            throw new RuntimeException('Thiết bị không tồn tại');
        }

        $this->ensureManagerOwnsDevice($existing, $userRole, $userBranchId, $userId);

        if ($this->repo->hasConfirmedAppointments($deviceId)) {
            throw new RuntimeException('Thiết bị đang được gắn với lịch hẹn đã xác nhận, không thể xóa');
        }

        $this->repo->delete($deviceId);
        $this->repo->removePriceHistory($deviceId);
        $this->logAction('DEVICE_DELETE', $deviceId, $existing, null, $userId, $userRole);

        return true;
    }

    private function normalizeFilters(array $filters): array
    {
        $normalized = [];

        if (isset($filters['branch_id'])) {
            $branchId = (int)$filters['branch_id'];
            if ($branchId > 0) {
                $normalized['branch_id'] = $branchId;
            }
        }

        if (isset($filters['status'])) {
            $status = trim((string)$filters['status']);
            if ($status !== '') {
                $normalized['status'] = $status;
            }
        }

        if (isset($filters['search'])) {
            $search = trim((string)$filters['search']);
            if ($search !== '') {
                $normalized['search'] = $search;
            }
        }

        if (isset($filters['maintenance']) && in_array($filters['maintenance'], ['overdue', 'recent'], true)) {
            $normalized['maintenance'] = $filters['maintenance'];
        }

        return $normalized;
    }

    private function normalizeCreatePayload(
        array $payload,
        string $userRole,
        ?int $userBranchId,
        string $userId
    ): array {
        $name = trim((string)($payload['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Tên thiết bị không được bỏ trống');
        }

        $status = $this->normalizeStatus($payload['status'] ?? null);
        $maintenanceDate = $this->normalizeDate($payload['maintenance_date'] ?? null);
        $image = isset($payload['image']) ? trim((string)$payload['image']) : null;
        if ($image === '') {
            $image = null;
        }

        $branchId = isset($payload['branch_id']) ? (int)$payload['branch_id'] : null;
        if ($userRole === '2') {
            $branchId = $this->resolveManagerBranch($userBranchId, $userId);
        }

        if (!$branchId) {
            throw new RuntimeException('Chi nhánh là bắt buộc');
        }

        $price = null;
        if (array_key_exists('price', $payload) && $payload['price'] !== null) {
            $price = (int)$payload['price'];
            if ($price < 0) {
                throw new RuntimeException('Đơn giá phải lớn hơn hoặc bằng 0');
            }
        }

        return [
            'db' => [
                'TEN_TB' => $name,
                'ID_CN' => $branchId,
                'TINH_TRANG' => $status,
                'NGAY_BAO_TRI' => $maintenanceDate,
                'IMAGE' => $image
            ],
            'price' => $price
        ];
    }

    private function normalizeUpdatePayload(
        array $payload,
        string $userRole,
        ?int $userBranchId,
        string $userId,
        array $existing
    ): array {
        $updates = [];
        $price = null;

        if (array_key_exists('name', $payload)) {
            $name = trim((string)$payload['name']);
            if ($name === '') {
                throw new RuntimeException('Tên thiết bị không được bỏ trống');
            }
            $updates['TEN_TB'] = $name;
        }

        if (array_key_exists('status', $payload)) {
            $updates['TINH_TRANG'] = $this->normalizeStatus($payload['status']);
        }

        if (array_key_exists('maintenance_date', $payload)) {
            $updates['NGAY_BAO_TRI'] = $this->normalizeDate($payload['maintenance_date']);
        }

        if (array_key_exists('image', $payload)) {
            $image = isset($payload['image']) ? trim((string)$payload['image']) : null;
            $updates['IMAGE'] = $image === '' ? null : $image;
        }

        if (array_key_exists('branch_id', $payload)) {
            if ($userRole !== '1') {
                throw new RuntimeException('Chỉ quản trị viên được phép đổi chi nhánh thiết bị');
            }
            $branchId = (int)$payload['branch_id'];
            if ($branchId <= 0) {
                throw new RuntimeException('Chi nhánh mục tiêu không hợp lệ');
            }
            $updates['ID_CN'] = $branchId;
        }

        if (array_key_exists('price', $payload)) {
            $priceValue = $payload['price'];
            if ($priceValue !== null) {
                $price = (int)$priceValue;
                if ($price < 0) {
                    throw new RuntimeException('Đơn giá phải lớn hơn hoặc bằng 0');
                }
            } else {
                $price = null;
            }
        }

        if (isset($updates['ID_CN'])) {
            $this->ensureBranchExists($updates['ID_CN']);
        }

        return [
            'db' => $updates,
            'price' => $price
        ];
    }

    private function normalizeStatus(?string $status): string
    {
        $value = trim((string)$status);
        if ($value === '') {
            return 'Đang hoạt động';
        }
        return $this->clipString($value, 50);
    }

    private function normalizeDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $trimmed = trim((string)$value);
        if ($trimmed === '') {
            return null;
        }
        $dt = DateTime::createFromFormat('Y-m-d', $trimmed);
        if (!$dt) {
            throw new RuntimeException('Ngày bảo trì phải theo định dạng YYYY-MM-DD');
        }
        return $dt->format('Y-m-d');
    }

    private function assertCanManage(?string $userRole): void
    {
        if (!in_array($userRole, ['1', '2'], true)) {
            throw new RuntimeException('Tài khoản không có quyền quản lý thiết bị');
        }
    }

    private function ensureBranchExists(int $branchId): void
    {
        if ($branchId <= 0 || !$this->repo->branchExists($branchId)) {
            throw new RuntimeException('Chi nhánh không tồn tại');
        }
    }

    private function ensureManagerOwnsDevice(
        array $device,
        string $userRole,
        ?int $userBranchId,
        string $userId
    ): void {
        if ($userRole !== '2') {
            return;
        }
        $managerBranch = $this->resolveManagerBranch($userBranchId, $userId);
        if ((int)$device['ID_CN'] !== $managerBranch) {
            throw new RuntimeException('Chi nhánh không có quyền thao tác thiết bị này');
        }
    }

    private function resolveManagerBranch(?int $sessionBranchId, string $userId): int
    {
        if ($sessionBranchId) {
            return $sessionBranchId;
        }
        if ($userId === '') {
            throw new RuntimeException('Không xác định được tài khoản hiện tại');
        }
        if (isset($this->managerBranchCache[$userId])) {
            return $this->managerBranchCache[$userId];
        }

        $stmt = $this->connection->prepare('SELECT ID_CN FROM nhan_vien WHERE ID_TK = ? LIMIT 1');
        if (!$stmt) {
            throw new RuntimeException('Không thể xác định chi nhánh của nhân viên');
        }
        $stmt->bind_param('s', $userId);
        $stmt->execute();
        $stmt->bind_result($branchId);
        $stmt->fetch();
        $stmt->close();

        $branchId = (int)($branchId ?? 0);
        if ($branchId <= 0) {
            throw new RuntimeException('Tài khoản chưa được gán chi nhánh');
        }

        $this->managerBranchCache[$userId] = $branchId;
        return $branchId;
    }

    private function logAction(
        string $action,
        int $deviceId,
        mixed $before,
        mixed $after,
        ?string $actorId,
        ?string $role
    ): void {
        if (!function_exists('record_system_log')) {
            return;
        }

        $beforePayload = $before ? ['device' => $before] : null;
        $afterPayload = $after ? ['device' => $after] : null;

        \record_system_log(
            $this->connection,
            $action,
            $this->buildSubject($deviceId),
            $beforePayload,
            $afterPayload,
            $actorId,
            $role
        );
    }

    private function buildSubject(int $deviceId): string
    {
        return 'device:' . $deviceId;
    }

    private function clipString(string $value, int $length): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $length);
        }

        return substr($value, 0, $length);
    }
}
