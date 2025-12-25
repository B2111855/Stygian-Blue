<?php

namespace App\Repositories;

use mysqli;
use RuntimeException;

class DeviceRepository
{
    private mysqli $connection;
    private bool $hasPriceHistory;

    public function __construct(mysqli $connection)
    {
        $this->connection = $connection;
        $this->hasPriceHistory = $this->detectPriceHistory();
    }

    public function hasPriceHistory(): bool
    {
        return $this->hasPriceHistory;
    }

    /**
     * Fetch paginated devices with filters applied.
     */
    public function findAll(
        array $filters = [],
        int $limit = 20,
        int $offset = 0,
        string $sort = 'id',
        string $direction = 'desc'
    ): array {
        [$whereSql, $types, $params] = $this->buildFilterClause($filters);
        $orderSql = $this->buildOrderBy($sort, $direction);
        $priceSql = $this->priceSelectSql();
        $maintenanceSql = $this->maintenanceDiffSql();

        $sql = "
            SELECT
                tb.ID_TB,
                tb.TEN_TB,
                tb.ID_CN,
                tb.TINH_TRANG,
                NULLIF(tb.NGAY_BAO_TRI, '0000-00-00') AS NGAY_BAO_TRI,
                tb.IMAGE,
                cn.TEN_CN,
                {$priceSql},
                {$maintenanceSql}
            FROM trang_thiet_bi tb
            LEFT JOIN chi_nhanh cn ON cn.ID_CN = tb.ID_CN
            {$whereSql}
            ORDER BY {$orderSql}
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Không thể chuẩn bị truy vấn danh sách thiết bị: ' . $this->connection->error);
        }

        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';

        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            throw new RuntimeException('Không thể lấy danh sách thiết bị: ' . $this->connection->error);
        }

        $result = $stmt->get_result();
        $devices = [];
        while ($row = $result->fetch_assoc()) {
            $devices[] = $row;
        }
        $stmt->close();

        return $devices;
    }

    /**
     * Count devices with the same filters used for listing.
     */
    public function count(array $filters = []): int
    {
        [$whereSql, $types, $params] = $this->buildFilterClause($filters);
        $sql = "
            SELECT COUNT(*) AS total
            FROM trang_thiet_bi tb
            LEFT JOIN chi_nhanh cn ON cn.ID_CN = tb.ID_CN
            {$whereSql}
        ";

        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Không thể chuẩn bị truy vấn đếm thiết bị: ' . $this->connection->error);
        }

        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            throw new RuntimeException('Không thể đếm thiết bị: ' . $this->connection->error);
        }

        $result = $stmt->get_result();
        $count = (int)($result->fetch_assoc()['total'] ?? 0);
        $stmt->close();

        return $count;
    }

    /**
     * Fetch a single device by ID.
     */
    public function findById(int $deviceId): ?array
    {
        $priceSql = $this->priceSelectSql();
        $maintenanceSql = $this->maintenanceDiffSql();

        $sql = "
            SELECT
                tb.ID_TB,
                tb.TEN_TB,
                tb.ID_CN,
                tb.TINH_TRANG,
                NULLIF(tb.NGAY_BAO_TRI, '0000-00-00') AS NGAY_BAO_TRI,
                tb.IMAGE,
                cn.TEN_CN,
                {$priceSql},
                {$maintenanceSql}
            FROM trang_thiet_bi tb
            LEFT JOIN chi_nhanh cn ON cn.ID_CN = tb.ID_CN
            WHERE tb.ID_TB = ?
            LIMIT 1
        ";

        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Không thể chuẩn bị truy vấn thiết bị: ' . $this->connection->error);
        }

        $stmt->bind_param('i', $deviceId);
        if (!$stmt->execute()) {
            throw new RuntimeException('Không thể lấy thiết bị: ' . $this->connection->error);
        }

        $result = $stmt->get_result();
        $device = $result->fetch_assoc() ?: null;
        $stmt->close();

        return $device;
    }

    public function create(array $data): int
    {
        $sql = 'INSERT INTO trang_thiet_bi (TEN_TB, ID_CN, TINH_TRANG, NGAY_BAO_TRI, IMAGE) VALUES (?, ?, ?, ?, ?)';
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Không thể chuẩn bị câu lệnh thêm thiết bị: ' . $this->connection->error);
        }

        $name = $data['TEN_TB'];
        $branchId = (int)$data['ID_CN'];
        $status = $data['TINH_TRANG'];
        $maintenanceDate = $this->normalizeDate($data['NGAY_BAO_TRI'] ?? null);
        $image = $data['IMAGE'] ?? null;

        $stmt->bind_param('sisss', $name, $branchId, $status, $maintenanceDate, $image);
        if (!$stmt->execute()) {
            throw new RuntimeException('Không thể thêm thiết bị: ' . $stmt->error);
        }

        $newId = (int)$stmt->insert_id;
        $stmt->close();

        return $newId;
    }

    public function update(int $deviceId, array $data): bool
    {
        if (empty($data)) {
            return true;
        }

        $fields = [];
        $params = [];
        $types = '';
        $allowed = ['TEN_TB', 'ID_CN', 'TINH_TRANG', 'NGAY_BAO_TRI', 'IMAGE'];

        foreach ($allowed as $column) {
            if (!array_key_exists($column, $data)) {
                continue;
            }

            $value = $data[$column];
            if ($column === 'NGAY_BAO_TRI') {
                $value = $this->normalizeDate($value);
            }
            $fields[] = $column . ' = ?';
            if ($column === 'ID_CN') {
                $types .= 'i';
                $params[] = (int)$value;
            } else {
                $types .= 's';
                $params[] = $value;
            }
        }

        if (!$fields) {
            return true;
        }

        $params[] = $deviceId;
        $types .= 'i';

        $sql = 'UPDATE trang_thiet_bi SET ' . implode(', ', $fields) . ' WHERE ID_TB = ?';
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Không thể chuẩn bị câu lệnh cập nhật thiết bị: ' . $this->connection->error);
        }

        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            throw new RuntimeException('Không thể cập nhật thiết bị: ' . $stmt->error);
        }

        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected >= 0;
    }

    public function delete(int $deviceId): bool
    {
        $stmt = $this->connection->prepare('DELETE FROM trang_thiet_bi WHERE ID_TB = ?');
        if (!$stmt) {
            throw new RuntimeException('Không thể chuẩn bị câu lệnh xóa thiết bị: ' . $this->connection->error);
        }

        $stmt->bind_param('i', $deviceId);
        if (!$stmt->execute()) {
            throw new RuntimeException('Không thể xóa thiết bị: ' . $stmt->error);
        }
        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected > 0;
    }

    public function removePriceHistory(int $deviceId): void
    {
        if (!$this->hasPriceHistory) {
            return;
        }

        $stmt = $this->connection->prepare('DELETE FROM don_gia_trang_thiet_bi WHERE ID_TB = ?');
        if ($stmt) {
            $stmt->bind_param('i', $deviceId);
            $stmt->execute();
            $stmt->close();
        }
    }

    public function recordPrice(int $deviceId, int $price): void
    {
        if (!$this->hasPriceHistory) {
            return;
        }
        $stmt = $this->connection->prepare('INSERT INTO don_gia_trang_thiet_bi (ID_TB, NGAY_GIO, DON_GIA) VALUES (?, NOW(), ?)');
        if (!$stmt) {
            throw new RuntimeException('Không thể chuẩn bị câu lệnh lưu giá thiết bị: ' . $this->connection->error);
        }
        $stmt->bind_param('ii', $deviceId, $price);
        if (!$stmt->execute()) {
            throw new RuntimeException('Không thể lưu giá thiết bị: ' . $stmt->error);
        }
        $stmt->close();
    }

    public function getPriceHistory(int $deviceId, int $limit = 10, int $offset = 0): array
    {
        if (!$this->hasPriceHistory) {
            return [];
        }

        $stmt = $this->connection->prepare(
            'SELECT ID_TB, DON_GIA, NGAY_GIO FROM don_gia_trang_thiet_bi WHERE ID_TB = ? ORDER BY NGAY_GIO DESC LIMIT ? OFFSET ?'
        );
        if (!$stmt) {
            throw new RuntimeException('Không thể chuẩn bị truy vấn lịch sử giá: ' . $this->connection->error);
        }

        $stmt->bind_param('iii', $deviceId, $limit, $offset);
        if (!$stmt->execute()) {
            throw new RuntimeException('Không thể lấy lịch sử giá: ' . $stmt->error);
        }

        $result = $stmt->get_result();
        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
        $stmt->close();

        return $history;
    }

    public function hasConfirmedAppointments(int $deviceId): bool
    {
        $sql = '
            SELECT 1
            FROM lich_hen_thiet_bi lhtb
            JOIN lich_hen lh ON lh.ID_LICHHEN = lhtb.ID_LICHHEN
            WHERE lhtb.ID_TB = ? AND lh.TRANGTHAI = "Đã xác nhận"
            LIMIT 1
        ';
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Không thể kiểm tra lịch hẹn thiết bị: ' . $this->connection->error);
        }

        $stmt->bind_param('i', $deviceId);
        $stmt->execute();
        $stmt->store_result();
        $has = $stmt->num_rows > 0;
        $stmt->close();

        return $has;
    }

    public function branchExists(int $branchId): bool
    {
        $stmt = $this->connection->prepare('SELECT 1 FROM chi_nhanh WHERE ID_CN = ? LIMIT 1');
        if (!$stmt) {
            throw new RuntimeException('Không thể kiểm tra chi nhánh: ' . $this->connection->error);
        }

        $stmt->bind_param('i', $branchId);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();

        return $exists;
    }

    public function transfer(int $deviceId, int $branchId): bool
    {
        $stmt = $this->connection->prepare('UPDATE trang_thiet_bi SET ID_CN = ? WHERE ID_TB = ?');
        if (!$stmt) {
            throw new RuntimeException('Không thể chuyển chi nhánh thiết bị: ' . $this->connection->error);
        }

        $stmt->bind_param('ii', $branchId, $deviceId);
        if (!$stmt->execute()) {
            throw new RuntimeException('Không thể cập nhật chi nhánh thiết bị: ' . $stmt->error);
        }
        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected >= 0;
    }

    private function detectPriceHistory(): bool
    {
        $result = $this->connection->query("SHOW TABLES LIKE 'don_gia_trang_thiet_bi'");
        if (!$result) {
            return false;
        }
        $has = $result->num_rows > 0;
        $result->free();
        return $has;
    }

    private function priceSelectSql(): string
    {
        if (!$this->hasPriceHistory) {
            return 'NULL AS DON_GIA_MOI_NHAT';
        }
        return "(SELECT d.DON_GIA FROM don_gia_trang_thiet_bi d WHERE d.ID_TB = tb.ID_TB ORDER BY d.NGAY_GIO DESC LIMIT 1) AS DON_GIA_MOI_NHAT";
    }

    private function maintenanceDiffSql(): string
    {
        return "CASE WHEN tb.NGAY_BAO_TRI IS NULL OR tb.NGAY_BAO_TRI = '0000-00-00' THEN NULL ELSE DATEDIFF(CURDATE(), tb.NGAY_BAO_TRI) END AS DAYS_FROM_MAINTENANCE";
    }

    private function buildOrderBy(string $sort, string $direction): string
    {
        $map = [
            'id' => 'tb.ID_TB',
            'name' => 'tb.TEN_TB',
            'status' => 'tb.TINH_TRANG',
            'maintenance_date' => 'tb.NGAY_BAO_TRI',
            'branch' => 'cn.TEN_CN',
            'price' => $this->hasPriceHistory ? 'DON_GIA_MOI_NHAT' : 'tb.ID_TB'
        ];

        $key = strtolower($sort);
        $column = $map[$key] ?? $map['id'];
        $dir = strtolower($direction) === 'asc' ? 'ASC' : 'DESC';

        return $column . ' ' . $dir;
    }

    private function buildFilterClause(array $filters): array
    {
        $conditions = [];
        $params = [];
        $types = '';

        if (!empty($filters['branch_id'])) {
            $conditions[] = 'tb.ID_CN = ?';
            $params[] = (int)$filters['branch_id'];
            $types .= 'i';
        }

        if (!empty($filters['status'])) {
            $conditions[] = 'tb.TINH_TRANG = ?';
            $params[] = $filters['status'];
            $types .= 's';
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(tb.TEN_TB LIKE ? OR tb.TINH_TRANG LIKE ? OR cn.TEN_CN LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $types .= 'sss';
        }

        if (!empty($filters['maintenance'])) {
            if ($filters['maintenance'] === 'overdue') {
                $conditions[] = '(tb.NGAY_BAO_TRI IS NULL OR tb.NGAY_BAO_TRI <= DATE_SUB(CURDATE(), INTERVAL 30 DAY))';
            } elseif ($filters['maintenance'] === 'recent') {
                $conditions[] = 'tb.NGAY_BAO_TRI >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
            }
        }

        $whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return [$whereSql, $types, $params];
    }

    private function normalizeDate(?string $value): ?string
    {
        if ($value === null || $value === '' || $value === '0000-00-00') {
            return null;
        }
        return $value;
    }
}
