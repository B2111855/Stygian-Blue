<?php

namespace App\Repositories;

use mysqli;
use RuntimeException;

class CostumeRepository
{
    private mysqli $connection;

    public function __construct(mysqli $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Lấy danh sách trang phục thuộc gói (bao gồm cả optional & bắt buộc)
     * @return array<int, array{ID_TRANG_PHUC:int,TEN:string,GIA_THUE:int,DISCOUNT_PERCENT:int,BAT_BUOC:int}>
     */
    public function getCostumesForPackage(int $packageId): array
    {
        $sql = "SELECT tp.ID_TRANG_PHUC, tp.TEN, tp.GIA_THUE, gtp.DISCOUNT_PERCENT, gtp.BAT_BUOC
                FROM GOI_TRANG_PHUC gtp
                JOIN TRANG_PHUC tp ON tp.ID_TRANG_PHUC = gtp.ID_TRANG_PHUC
                WHERE gtp.ID_GOI = ?
                ORDER BY COALESCE(gtp.THU_TU, tp.ID_TRANG_PHUC)";
        $stmt = $this->connection->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Không thể truy vấn danh sách trang phục của gói: ' . $this->connection->error);
        }
        $stmt->bind_param('i', $packageId);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows ?: [];
    }

    /**
     * Lấy giá gói (base price) từ view tổng tiền.
     */
    public function getPackageBasePrice(int $packageId): int
    {
        $sql = "SELECT TONG_GIA_GOI FROM v_goi_dich_vu_tong_tien WHERE ID_GOI = ? LIMIT 1";
        $stmt = $this->connection->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Không thể truy vấn giá gói: ' . $this->connection->error);
        }
        $stmt->bind_param('i', $packageId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return $row && isset($row['TONG_GIA_GOI']) ? (int)$row['TONG_GIA_GOI'] : 0;
    }

    /**
     * Trả về map ID_TRANG_PHUC -> GIA_THUE.
     * @param int[] $ids
     * @return array<int,int>
     */
    public function getCostumeBasePrices(array $ids): array
    {
        if (empty($ids)) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT ID_TRANG_PHUC, GIA_THUE FROM TRANG_PHUC WHERE ID_TRANG_PHUC IN ($placeholders)";
        $types = str_repeat('i', count($ids));
        $stmt = $this->connection->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Không thể truy vấn giá trang phục: ' . $this->connection->error);
        }
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $result = $stmt->get_result();
        $map = [];
        while ($row = $result->fetch_assoc()) {
            $map[(int)$row['ID_TRANG_PHUC']] = (int)$row['GIA_THUE'];
        }
        $stmt->close();
        return $map;
    }

    /**
     * @param int[] $ids
     * @return array<int, array{ID_TRANG_PHUC:int,TEN:string,GIA_THUE:int}>
     */
    public function getCostumesByIds(array $ids): array
    {
        if (empty($ids)) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT ID_TRANG_PHUC, TEN, GIA_THUE FROM TRANG_PHUC WHERE ID_TRANG_PHUC IN ($placeholders)";
        $types = str_repeat('i', count($ids));
        $stmt = $this->connection->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Không thể truy vấn thông tin trang phục: ' . $this->connection->error);
        }
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows ?: [];
    }
}
