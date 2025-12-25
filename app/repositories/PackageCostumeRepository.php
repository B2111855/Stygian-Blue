<?php

namespace App\Repositories;

use mysqli;
use RuntimeException;

class PackageCostumeRepository
{
    private mysqli $connection;

    public function __construct(mysqli $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Lấy tất cả trang phục trong một gói
     * 
     * @param int $packageId ID gói
     * @return array<int, array> Array of package items with costume info
     */
    public function findByPackage(int $packageId): array
    {
        $stmt = $this->connection->prepare(
            "SELECT gtp.ID_GOI, gtp.ID_TRANG_PHUC, gtp.SO_LUONG, gtp.THU_TU, 
                    gtp.GHI_CHU, gtp.DISCOUNT_PERCENT, gtp.BA_CHI_TIEU,
                    gtp.CREATED_BY, gtp.CREATED_AT, gtp.UPDATED_BY, gtp.UPDATED_AT,
                    tp.TEN, tp.SIZE, tp.MAU_SAC, tp.GIA_THUE, tp.TRANG_THAI
             FROM goi_trang_phuc_chi_tiet gtp
             JOIN trang_phuc tp ON gtp.ID_TRANG_PHUC = tp.ID_TRANG_PHUC
             WHERE gtp.ID_GOI = ?
             ORDER BY gtp.THU_TU ASC, gtp.ID_TRANG_PHUC ASC"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('i', $packageId);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $result = $stmt->get_result();
        $items = [];
        
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        
        $stmt->close();
        return $items;
    }

    /**
     * Lấy chi tiết một trang phục trong gói
     * 
     * @param int $packageId ID gói
     * @param int $costumeId ID trang phục
     * @return array|null Package item detail or null if not found
     */
    public function findById(int $packageId, int $costumeId): ?array
    {
        $stmt = $this->connection->prepare(
            "SELECT gtp.ID_GOI, gtp.ID_TRANG_PHUC, gtp.SO_LUONG, gtp.THU_TU, 
                    gtp.GHI_CHU, gtp.DISCOUNT_PERCENT, gtp.BA_CHI_TIEU,
                    gtp.CREATED_BY, gtp.CREATED_AT, gtp.UPDATED_BY, gtp.UPDATED_AT,
                    tp.TEN, tp.SIZE, tp.MAU_SAC, tp.GIA_THUE, tp.TRANG_THAI
             FROM goi_trang_phuc_chi_tiet gtp
             JOIN trang_phuc tp ON gtp.ID_TRANG_PHUC = tp.ID_TRANG_PHUC
             WHERE gtp.ID_GOI = ? AND gtp.ID_TRANG_PHUC = ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('ii', $packageId, $costumeId);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();
        
        $stmt->close();
        return $item;
    }

    /**
     * Thêm trang phục vào gói
     * 
     * @param int $packageId ID gói
     * @param int $costumeId ID trang phục
     * @param int $quantity Số lượng
     * @param int $order Thứ tự (mặc định 0)
     * @param int $discountPercent Phần trăm giảm giá cho item (mặc định 0)
     * @param int $mandatory Bắt buộc (1) hay tùy chọn (0)
     * @param string|null $notes Ghi chú
     * @param string $createdBy ID người thêm
     * @return bool True if added successfully
     */
    public function add(
        int $packageId,
        int $costumeId,
        int $quantity = 1,
        int $order = 0,
        int $discountPercent = 0,
        int $mandatory = 1,
        ?string $notes = null,
        string $createdBy = ''
    ): bool {
        // Kiểm tra gói tồn tại
        $packageStmt = $this->connection->prepare(
            "SELECT ID_GOI FROM goi_trang_phuc_master WHERE ID_GOI = ?"
        );
        if (!$packageStmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $packageStmt->bind_param('i', $packageId);
        if (!$packageStmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        if ($packageStmt->get_result()->num_rows === 0) {
            $packageStmt->close();
            throw new RuntimeException('Gói trang phục không tồn tại');
        }
        $packageStmt->close();
        
        // Kiểm tra trang phục tồn tại
        $costumeStmt = $this->connection->prepare(
            "SELECT ID_TRANG_PHUC FROM trang_phuc WHERE ID_TRANG_PHUC = ?"
        );
        if (!$costumeStmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $costumeStmt->bind_param('i', $costumeId);
        if (!$costumeStmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        if ($costumeStmt->get_result()->num_rows === 0) {
            $costumeStmt->close();
            throw new RuntimeException('Trang phục không tồn tại');
        }
        $costumeStmt->close();
        
        // Kiểm tra discount hợp lệ
        if ($discountPercent < 0 || $discountPercent > 100) {
            throw new RuntimeException('Phần trăm giảm giá phải từ 0-100');
        }
        
        // Kiểm tra trang phục đã có trong gói chưa
        $checkStmt = $this->connection->prepare(
            "SELECT ID_TRANG_PHUC FROM goi_trang_phuc_chi_tiet WHERE ID_GOI = ? AND ID_TRANG_PHUC = ?"
        );
        if (!$checkStmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $checkStmt->bind_param('ii', $packageId, $costumeId);
        if (!$checkStmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        if ($checkStmt->get_result()->num_rows > 0) {
            $checkStmt->close();
            throw new RuntimeException('Trang phục đã tồn tại trong gói này');
        }
        $checkStmt->close();
        
        // Thêm trang phục vào gói
        $stmt = $this->connection->prepare(
            "INSERT INTO goi_trang_phuc_chi_tiet 
             (ID_GOI, ID_TRANG_PHUC, SO_LUONG, THU_TU, DISCOUNT_PERCENT, BA_CHI_TIEU, GHI_CHU, CREATED_BY, CREATED_AT)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('iiiiiss', $packageId, $costumeId, $quantity, $order, $discountPercent, $mandatory, $notes, $createdBy);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $stmt->close();
        return true;
    }

    /**
     * Lấy danh sách trang phục trong gói (tương thích với code cũ)
     */
    public function listCostumes(int $packageId): array {
        return $this->findByPackage($packageId);
    }

    /**
     * Thêm trang phục vào gói (tương thích với code cũ)
     */
    public function addCostume(int $packageId, int $costumeId, int $quantity=1, int $order=1, ?string $note=null): bool {
        return $this->add($packageId, $costumeId, $quantity, $order, 0, 1, $note, '');
    }

    /**
     * Xóa trang phục khỏi gói
     */
    public function removeCostume(int $packageId, int $costumeId): bool {
        return $this->remove($packageId, $costumeId);
    }

    /**
     * Sắp xếp lại các trang phục trong gói
     */
    public function reorder(int $packageId, array $costumeIds): bool {
        if(empty($costumeIds)) return false;
        
        $this->connection->begin_transaction();
        try {
            $stmt = $this->connection->prepare("UPDATE goi_trang_phuc_chi_tiet SET THU_TU=? WHERE ID_GOI=? AND ID_TRANG_PHUC=?");
            $seq=1;
            foreach($costumeIds as $cid){
                $cid=(int)$cid;
                $stmt->bind_param('iii',$seq,$packageId,$cid);
                if(!$stmt->execute()) throw new RuntimeException('Fail reorder');
                $seq++;
            }
            $this->connection->commit();
            return true;
        } catch(\Throwable $e){
            $this->connection->rollback();
            return false;
        }
    }

    /**
     * Thay thế toàn bộ các trang phục trong gói
     */
    public function bulkReplace(int $packageId, array $items): bool {
        $this->connection->begin_transaction();
        try {
            $del = $this->connection->prepare("DELETE FROM goi_trang_phuc_chi_tiet WHERE ID_GOI=?");
            $del->bind_param('i',$packageId);
            if(!$del->execute()) throw new RuntimeException('Delete old');
            
            $ins = $this->connection->prepare("INSERT INTO goi_trang_phuc_chi_tiet (ID_GOI, ID_TRANG_PHUC, SO_LUONG, THU_TU, DISCOUNT_PERCENT, BA_CHI_TIEU, GHI_CHU) VALUES (?,?,?,?,?,?,?)");
            foreach($items as $it){
                if(!isset($it['ID_TRANG_PHUC'])) continue;
                $cid = (int)$it['ID_TRANG_PHUC'];
                $qty = max(1,(int)($it['SO_LUONG'] ?? 1));
                $order = max(1,(int)($it['THU_TU'] ?? 1));
                $discount = max(0, min(100, (int)($it['DISCOUNT_PERCENT'] ?? 0)));
                $mandatory = isset($it['BA_CHI_TIEU']) ? max(0, min(1, (int)$it['BA_CHI_TIEU'])) : 1;
                $note = isset($it['GHI_CHU']) && $it['GHI_CHU']!=='' ? (string)$it['GHI_CHU'] : null;
                $ins->bind_param('iiiiiss',$packageId,$cid,$qty,$order,$discount,$mandatory,$note);
                if(!$ins->execute()) throw new RuntimeException('Insert pivot failed');
            }
            $this->connection->commit();
            return true;
        } catch(\Throwable $e){
            $this->connection->rollback();
            return false;
        }
    }

    /**
     * Cập nhật chi tiết trang phục trong gói
     * 
     * @param int $packageId ID gói
     * @param int $costumeId ID trang phục
     * @param int $quantity Số lượng
     * @param int $order Thứ tự
     * @param int $discountPercent Phần trăm giảm giá
     * @param int $mandatory Bắt buộc hay tùy chọn
     * @param string|null $notes Ghi chú
     * @param string $updatedBy ID người cập nhật
     * @return bool True if updated successfully
     */
    public function update(
        int $packageId,
        int $costumeId,
        int $quantity = 1,
        int $order = 0,
        int $discountPercent = 0,
        int $mandatory = 1,
        ?string $notes = null,
        string $updatedBy = ''
    ): bool {
        if ($discountPercent < 0 || $discountPercent > 100) {
            throw new RuntimeException('Phần trăm giảm giá phải từ 0-100');
        }
        
        $stmt = $this->connection->prepare(
            "UPDATE goi_trang_phuc_chi_tiet 
             SET SO_LUONG = ?, THU_TU = ?, DISCOUNT_PERCENT = ?, BA_CHI_TIEU = ?, 
                 GHI_CHU = ?, UPDATED_BY = ?, UPDATED_AT = NOW()
             WHERE ID_GOI = ? AND ID_TRANG_PHUC = ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('iiiiisii', $quantity, $order, $discountPercent, $mandatory, $notes, $updatedBy, $packageId, $costumeId);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        return $affected > 0;
    }

    /**
     * Xóa trang phục khỏi gói (internal method)
     */
    public function remove(int $packageId, int $costumeId): bool
    {
        $stmt = $this->connection->prepare(
            "DELETE FROM goi_trang_phuc_chi_tiet WHERE ID_GOI = ? AND ID_TRANG_PHUC = ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('ii', $packageId, $costumeId);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        return $affected > 0;
    }

    /**
     * Cập nhật số lượng trang phục trong gói
     * 
     * @param int $packageId ID gói
     * @param int $costumeId ID trang phục
     * @param int $quantity Số lượng mới
     * @param string $updatedBy ID người cập nhật
     * @return bool True if updated successfully
     */
    public function updateQuantity(int $packageId, int $costumeId, int $quantity, string $updatedBy = ''): bool
    {
        if ($quantity < 1) {
            throw new RuntimeException('Số lượng phải >= 1');
        }
        
        $stmt = $this->connection->prepare(
            "UPDATE goi_trang_phuc_chi_tiet 
             SET SO_LUONG = ?, UPDATED_BY = ?, UPDATED_AT = NOW()
             WHERE ID_GOI = ? AND ID_TRANG_PHUC = ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('isii', $quantity, $updatedBy, $packageId, $costumeId);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        return $affected > 0;
    }

    /**
     * Cập nhật phần trăm giảm giá cho trang phục trong gói
     * 
     * @param int $packageId ID gói
     * @param int $costumeId ID trang phục
     * @param int $discountPercent Phần trăm giảm giá
     * @param string $updatedBy ID người cập nhật
     * @return bool True if updated successfully
     */
    public function updateDiscount(int $packageId, int $costumeId, int $discountPercent, string $updatedBy = ''): bool
    {
        if ($discountPercent < 0 || $discountPercent > 100) {
            throw new RuntimeException('Phần trăm giảm giá phải từ 0-100');
        }
        
        $stmt = $this->connection->prepare(
            "UPDATE goi_trang_phuc_chi_tiet 
             SET DISCOUNT_PERCENT = ?, UPDATED_BY = ?, UPDATED_AT = NOW()
             WHERE ID_GOI = ? AND ID_TRANG_PHUC = ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('isii', $discountPercent, $updatedBy, $packageId, $costumeId);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        return $affected > 0;
    }

    /**
     * Cập nhật flag bắt buộc/tùy chọn
     * 
     * @param int $packageId ID gói
     * @param int $costumeId ID trang phục
     * @param int $mandatory 1 = bắt buộc, 0 = tùy chọn
     * @param string $updatedBy ID người cập nhật
     * @return bool True if updated successfully
     */
    public function updateMandatory(int $packageId, int $costumeId, int $mandatory, string $updatedBy = ''): bool
    {
        if ($mandatory !== 0 && $mandatory !== 1) {
            throw new RuntimeException('Giá trị bắt buộc phải là 0 hoặc 1');
        }
        
        $stmt = $this->connection->prepare(
            "UPDATE goi_trang_phuc_chi_tiet 
             SET BA_CHI_TIEU = ?, UPDATED_BY = ?, UPDATED_AT = NOW()
             WHERE ID_GOI = ? AND ID_TRANG_PHUC = ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('isii', $mandatory, $updatedBy, $packageId, $costumeId);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        return $affected > 0;
    }

    /**
     * Cập nhật thứ tự hiển thị
     * 
     * @param int $packageId ID gói
     * @param int $costumeId ID trang phục
     * @param int $order Thứ tự
     * @param string $updatedBy ID người cập nhật
     * @return bool True if updated successfully
     */
    public function updateOrder(int $packageId, int $costumeId, int $order, string $updatedBy = ''): bool
    {
        $stmt = $this->connection->prepare(
            "UPDATE goi_trang_phuc_chi_tiet 
             SET THU_TU = ?, UPDATED_BY = ?, UPDATED_AT = NOW()
             WHERE ID_GOI = ? AND ID_TRANG_PHUC = ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('isii', $order, $updatedBy, $packageId, $costumeId);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        return $affected > 0;
    }

    /**
     * Đếm số lượng trang phục bắt buộc
     * 
     * @param int $packageId ID gói
     * @return int Số lượng trang phục bắt buộc
     */
    public function countMandatory(int $packageId): int
    {
        $stmt = $this->connection->prepare(
            "SELECT COUNT(*) as cnt FROM goi_trang_phuc_chi_tiet WHERE ID_GOI = ? AND BA_CHI_TIEU = 1"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('i', $packageId);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $count = (int)($row['cnt'] ?? 0);
        
        $stmt->close();
        return $count;
    }

    /**
     * Đếm số lượng trang phục tùy chọn
     * 
     * @param int $packageId ID gói
     * @return int Số lượng trang phục tùy chọn
     */
    public function countOptional(int $packageId): int
    {
        $stmt = $this->connection->prepare(
            "SELECT COUNT(*) as cnt FROM goi_trang_phuc_chi_tiet WHERE ID_GOI = ? AND BA_CHI_TIEU = 0"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('i', $packageId);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $count = (int)($row['cnt'] ?? 0);
        
        $stmt->close();
        return $count;
    }

    /**
     * Lấy danh sách trang phục bắt buộc
     * 
     * @param int $packageId ID gói
     * @return array Array of mandatory items
     */
    public function getMandatoryCostumes(int $packageId): array
    {
        $stmt = $this->connection->prepare(
            "SELECT gtp.ID_TRANG_PHUC, gtp.SO_LUONG, gtp.DISCOUNT_PERCENT,
                    tp.TEN, tp.GIA_THUE, tp.SIZE, tp.MAU_SAC
             FROM goi_trang_phuc_chi_tiet gtp
             JOIN trang_phuc tp ON gtp.ID_TRANG_PHUC = tp.ID_TRANG_PHUC
             WHERE gtp.ID_GOI = ? AND gtp.BA_CHI_TIEU = 1
             ORDER BY gtp.THU_TU ASC"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('i', $packageId);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $result = $stmt->get_result();
        $items = [];
        
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        
        $stmt->close();
        return $items;
    }

    /**
     * Lấy danh sách trang phục tùy chọn
     * 
     * @param int $packageId ID gói
     * @return array Array of optional items
     */
    public function getOptionalCostumes(int $packageId): array
    {
        $stmt = $this->connection->prepare(
            "SELECT gtp.ID_TRANG_PHUC, gtp.SO_LUONG, gtp.DISCOUNT_PERCENT,
                    tp.TEN, tp.GIA_THUE, tp.SIZE, tp.MAU_SAC
             FROM goi_trang_phuc_chi_tiet gtp
             JOIN trang_phuc tp ON gtp.ID_TRANG_PHUC = tp.ID_TRANG_PHUC
             WHERE gtp.ID_GOI = ? AND gtp.BA_CHI_TIEU = 0
             ORDER BY gtp.THU_TU ASC"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('i', $packageId);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $result = $stmt->get_result();
        $items = [];
        
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        
        $stmt->close();
        return $items;
    }

    /**
     * Tính tổng giá tiền các trang phục trong gói (với discount)
     * 
     * @param int $packageId ID gói
     * @return int Tổng giá
     */
    public function calculateTotalPrice(int $packageId): int
    {
        $stmt = $this->connection->prepare(
            "SELECT SUM(tp.GIA_THUE * gtp.SO_LUONG * (1 - gtp.DISCOUNT_PERCENT/100)) as total
             FROM goi_trang_phuc_chi_tiet gtp
             JOIN trang_phuc tp ON gtp.ID_TRANG_PHUC = tp.ID_TRANG_PHUC
             WHERE gtp.ID_GOI = ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('i', $packageId);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total = (int)($row['total'] ?? 0);
        
        $stmt->close();
        return $total;
    }

    /**
     * Xóa tất cả trang phục khỏi gói
     * 
     * @param int $packageId ID gói
     * @return bool True if successful
     */
    public function removeAllCostumes(int $packageId): bool
    {
        $stmt = $this->connection->prepare(
            "DELETE FROM goi_trang_phuc_chi_tiet WHERE ID_GOI = ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('i', $packageId);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $stmt->close();
        return true;
    }

    /**
     * Lấy danh sách trang phục trong gói kèm thông tin giá chi tiết
     * 
     * @param int $packageId ID gói
     * @return array Array with detailed pricing info
     */
    public function findByPackageWithPricing(int $packageId): array
    {
        $items = $this->findByPackage($packageId);
        
        foreach ($items as &$item) {
            $basePrice = (int)$item['GIA_THUE'] * (int)$item['SO_LUONG'];
            $discountAmount = $basePrice * (int)$item['DISCOUNT_PERCENT'] / 100;
            $finalPrice = $basePrice - $discountAmount;
            
            $item['base_price'] = $basePrice;
            $item['discount_amount'] = (int)$discountAmount;
            $item['final_price'] = (int)$finalPrice;
            $item['status_label'] = $item['BA_CHI_TIEU'] === '1' || $item['BA_CHI_TIEU'] === 1 ? 'Bắt buộc' : 'Tùy chọn';
        }
        
        return $items;
    }
}