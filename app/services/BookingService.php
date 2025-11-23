<?php
/** BookingService: allocation logic for renting multiple costume items by type. */

class BookingService
{
    /**
     * Allocate physical costume items for a booking based on a type & quantity.
     *
     * @param mysqli $conn          Active mysqli connection (autocommit disabled outside or inside this method)
     * @param int    $orderId       Newly created don_thue_trang_phuc.ID_TTP
     * @param int    $typeId        trang_phuc_loai.ID_LOAI requested
     * @param int    $branchId      chi_nhanh ID_CN to constrain inventory
     * @param int    $quantity      Number of physical items needed
     * @param string $fromDateTime  Rental start (Y-m-d H:i:s)
     * @param string $toDateTime    Rental end   (Y-m-d H:i:s)
     * @param int    $unitPrice     Price per day applied to each item
     * @return array{allocated:int,items:array<int,int>} Summary of allocation
     * @throws Exception on insufficient inventory or database errors
     */
    public function allocateItemsForType(mysqli $conn, int $orderId, int $typeId, int $branchId, int $quantity, string $fromDateTime, string $toDateTime, int $unitPrice): array
    {
        if ($quantity < 1) {
            throw new Exception('Số lượng phải >= 1');
        }

        // Lock candidate items: not overlapping existing rentals with status cho_duyet/da_duyet/dang_thue
        $sql = "SELECT tp.ID_TRANG_PHUC AS id
                FROM trang_phuc tp
                WHERE tp.ID_LOAI = ? AND tp.ID_CN = ? AND tp.TRANG_THAI = 'available'
                  AND tp.ID_TRANG_PHUC NOT IN (
                     SELECT ct.ID_TP FROM don_thue_trang_phuc_ct ct
                     JOIN don_thue_trang_phuc t ON t.ID_TTP = ct.ID_TTP
                     WHERE t.TRANG_THAI IN ('cho_duyet','da_duyet','dang_thue')
                       AND NOT (t.NGAY_TRA_DK <= ? OR t.NGAY_NHAN >= ?)
                  )
                ORDER BY tp.ID_TRANG_PHUC ASC
                LIMIT ? FOR UPDATE"; // row-level locks

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Không chuẩn bị được truy vấn kho: ' . $conn->error);
        }
        $stmt->bind_param('iissi', $typeId, $branchId, $fromDateTime, $toDateTime, $quantity);
        if (!$stmt->execute()) {
            throw new Exception('Không thể lấy danh sách trang phục: ' . $stmt->error);
        }
        $res = $stmt->get_result();
        $items = [];
        while ($row = $res->fetch_assoc()) {
            $items[] = (int)$row['id'];
        }
        $stmt->close();

        if (count($items) < $quantity) {
            throw new Exception('Không đủ số lượng trang phục khả dụng cho loại đã chọn. Có ' . count($items) . '/' . $quantity);
        }

        // Insert details for each allocated item (SO_LUONG = 1 per row)
        $detailSql = "INSERT INTO don_thue_trang_phuc_ct (ID_TTP, ID_TP, SO_LUONG, DON_GIA_AP_DUNG) VALUES (?,?,1,?)";
        $detailStmt = $conn->prepare($detailSql);
        if (!$detailStmt) {
            throw new Exception('Không thể chuẩn bị insert chi tiết: ' . $conn->error);
        }
        foreach ($items as $itemId) {
            $detailStmt->bind_param('iii', $orderId, $itemId, $unitPrice);
            if (!$detailStmt->execute()) {
                throw new Exception('Lỗi ghi chi tiết cho ID_TP=' . $itemId . ': ' . $detailStmt->error);
            }
        }
        $detailStmt->close();

        return ['allocated' => count($items), 'items' => $items];
    }
}
