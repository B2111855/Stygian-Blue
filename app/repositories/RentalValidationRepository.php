<?php

namespace App\Repositories;

use mysqli;
use RuntimeException;

class RentalValidationRepository
{
    private mysqli $connection;

    public function __construct(mysqli $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Kiểm tra trang phục có sẵn cho thuê trong khoảng thời gian
     * 
     * @param int $costumeId ID trang phục
     * @param string $startDate Ngày bắt đầu (YYYY-MM-DD HH:MM:SS)
     * @param string $endDate Ngày kết thúc (YYYY-MM-DD HH:MM:SS)
     * @return array ['available'=>bool, 'reason'=>string|null, 'conflicts'=>array]
     */
    public function isAvailableDuring(int $costumeId, string $startDate, string $endDate): array
    {
        // Kiểm tra trang phục tồn tại
        $costumeStmt = $this->connection->prepare(
            "SELECT ID_TRANG_PHUC, TRANG_THAI FROM trang_phuc WHERE ID_TRANG_PHUC = ?"
        );
        if (!$costumeStmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $costumeStmt->bind_param('i', $costumeId);
        if (!$costumeStmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $costumeResult = $costumeStmt->get_result()->fetch_assoc();
        $costumeStmt->close();
        
        if (!$costumeResult) {
            return [
                'available' => false,
                'reason' => 'Trang phục không tồn tại',
                'conflicts' => []
            ];
        }
        
        // Kiểm tra trạng thái trang phục
        if ($costumeResult['TRANG_THAI'] !== 'available') {
            return [
                'available' => false,
                'reason' => "Trang phục hiện đang ở trạng thái: {$costumeResult['TRANG_THAI']}",
                'conflicts' => []
            ];
        }
        
        // Kiểm tra xem có đơn thuê trùng lịch không
        $stmt = $this->connection->prepare(
            "SELECT dtp.ID_TTP, dtp.NGAY_NHAN, dtp.NGAY_TRA_DK, dtp.TRANG_THAI, k.TEN_KH
             FROM don_thue_trang_phuc dtp
             LEFT JOIN khach_hang k ON dtp.ID_KH = k.ID_KH
             WHERE dtp.ID_TRANG_PHUC = ?
             AND dtp.TRANG_THAI NOT IN ('huy', 'tra')
             AND dtp.NGAY_NHAN < ?
             AND dtp.NGAY_TRA_DK > ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('iss', $costumeId, $endDate, $startDate);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $result = $stmt->get_result();
        $conflicts = [];
        
        while ($row = $result->fetch_assoc()) {
            $conflicts[] = [
                'rental_id' => $row['ID_TTP'],
                'start_date' => $row['NGAY_NHAN'],
                'end_date' => $row['NGAY_TRA_DK'],
                'status' => $row['TRANG_THAI'],
                'customer_name' => $row['TEN_KH'] ?? 'N/A'
            ];
        }
        
        $stmt->close();
        
        if (!empty($conflicts)) {
            return [
                'available' => false,
                'reason' => 'Trang phục đã được đặt trong khoảng thời gian này',
                'conflicts' => $conflicts
            ];
        }
        
        return [
            'available' => true,
            'reason' => null,
            'conflicts' => []
        ];
    }

    /**
     * Kiểm tra một loại trang phục có sẵn số lượng
     * 
     * @param int $costumeTypeId ID loại trang phục
     * @param int $quantity Số lượng cần
     * @param string $startDate Ngày bắt đầu
     * @param string $endDate Ngày kết thúc
     * @return array ['available'=>bool, 'available_count'=>int, 'reason'=>string|null]
     */
    public function countAvailableByType(int $costumeTypeId, int $quantity, string $startDate, string $endDate): array
    {
        // Đếm trang phục của loại này
        $stmt = $this->connection->prepare(
            "SELECT COUNT(*) as total FROM trang_phuc WHERE ID_LOAI = ? AND TRANG_THAI = 'available'"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('i', $costumeTypeId);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $totalResult = $stmt->get_result()->fetch_assoc();
        $totalCount = (int)($totalResult['total'] ?? 0);
        $stmt->close();
        
        if ($totalCount === 0) {
            return [
                'available' => false,
                'available_count' => 0,
                'reason' => 'Không có trang phục nào của loại này'
            ];
        }
        
        // Đếm trang phục đang bị đặt trong khoảng thời gian
        $stmt = $this->connection->prepare(
            "SELECT COUNT(DISTINCT tp.ID_TRANG_PHUC) as busy_count
             FROM trang_phuc tp
             JOIN don_thue_trang_phuc dtp ON tp.ID_TRANG_PHUC = dtp.ID_TRANG_PHUC
             WHERE tp.ID_LOAI = ?
             AND dtp.TRANG_THAI NOT IN ('huy', 'tra')
             AND dtp.NGAY_NHAN < ?
             AND dtp.NGAY_TRA_DK > ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('iss', $costumeTypeId, $endDate, $startDate);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $busyResult = $stmt->get_result()->fetch_assoc();
        $busyCount = (int)($busyResult['busy_count'] ?? 0);
        $stmt->close();
        
        $availableCount = $totalCount - $busyCount;
        
        if ($availableCount < $quantity) {
            return [
                'available' => false,
                'available_count' => max(0, $availableCount),
                'reason' => "Chỉ có {$availableCount} trang phục khả dụng, cần {$quantity}"
            ];
        }
        
        return [
            'available' => true,
            'available_count' => $availableCount,
            'reason' => null
        ];
    }

    /**
     * Lấy danh sách trang phục khả dụng của một loại
     * 
     * @param int $costumeTypeId ID loại trang phục
     * @param string $startDate Ngày bắt đầu
     * @param string $endDate Ngày kết thúc
     * @return array Array of available costumes
     */
    public function findAvailableByType(int $costumeTypeId, string $startDate, string $endDate): array
    {
        $stmt = $this->connection->prepare(
            "SELECT DISTINCT tp.ID_TRANG_PHUC, tp.TEN, tp.SIZE, tp.MAU_SAC, tp.GIA_THUE
             FROM trang_phuc tp
             WHERE tp.ID_LOAI = ?
             AND tp.TRANG_THAI = 'available'
             AND tp.ID_TRANG_PHUC NOT IN (
                SELECT DISTINCT dtp.ID_TRANG_PHUC
                FROM don_thue_trang_phuc dtp
                WHERE dtp.TRANG_THAI NOT IN ('huy', 'tra')
                AND dtp.NGAY_NHAN < ?
                AND dtp.NGAY_TRA_DK > ?
             )
             ORDER BY tp.TEN ASC"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('iss', $costumeTypeId, $endDate, $startDate);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $result = $stmt->get_result();
        $costumes = [];
        
        while ($row = $result->fetch_assoc()) {
            $costumes[] = $row;
        }
        
        $stmt->close();
        return $costumes;
    }

    /**
     * Kiểm tra gói trang phục có đủ trang phục sẵn cho khoảng thời gian
     * 
     * @param int $packageId ID gói
     * @param string $startDate Ngày bắt đầu
     * @param string $endDate Ngày kết thúc
     * @return array ['available'=>bool, 'available_items'=>int, 'total_items'=>int, 'missing'=>array]
     */
    public function isPackageAvailable(int $packageId, string $startDate, string $endDate): array
    {
        // Lấy danh sách trang phục bắt buộc trong gói
        $stmt = $this->connection->prepare(
            "SELECT gtp.ID_TRANG_PHUC, gtp.SO_LUONG, tp.TEN, tp.ID_LOAI
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
        $mandatoryItems = [];
        
        while ($row = $result->fetch_assoc()) {
            $mandatoryItems[] = $row;
        }
        
        $stmt->close();
        
        $totalItems = count($mandatoryItems);
        $availableItems = 0;
        $missing = [];
        
        // Kiểm tra từng trang phục bắt buộc
        foreach ($mandatoryItems as $item) {
            $availResult = $this->isAvailableDuring(
                (int)$item['ID_TRANG_PHUC'],
                $startDate,
                $endDate
            );
            
            if ($availResult['available']) {
                $availableItems++;
            } else {
                $missing[] = [
                    'costume_id' => $item['ID_TRANG_PHUC'],
                    'costume_name' => $item['TEN'],
                    'reason' => $availResult['reason'],
                    'quantity' => $item['SO_LUONG']
                ];
            }
        }
        
        if ($availableItems < $totalItems) {
            return [
                'available' => false,
                'available_items' => $availableItems,
                'total_items' => $totalItems,
                'missing' => $missing
            ];
        }
        
        return [
            'available' => true,
            'available_items' => $availableItems,
            'total_items' => $totalItems,
            'missing' => []
        ];
    }

    /**
     * Lấy các đơn thuê xung đột với một khoảng thời gian
     * 
     * @param int $costumeId ID trang phục
     * @param string $startDate Ngày bắt đầu
     * @param string $endDate Ngày kết thúc
     * @return array Array of conflicting rentals
     */
    public function getConflictingRentals(int $costumeId, string $startDate, string $endDate): array
    {
        $stmt = $this->connection->prepare(
            "SELECT dtp.ID_TTP, dtp.NGAY_NHAN, dtp.NGAY_TRA_DK, dtp.TRANG_THAI,
                    k.ID_KH, k.TEN_KH, k.SDT
             FROM don_thue_trang_phuc dtp
             LEFT JOIN khach_hang k ON dtp.ID_KH = k.ID_KH
             WHERE dtp.ID_TRANG_PHUC = ?
             AND dtp.NGAY_NHAN < ?
             AND dtp.NGAY_TRA_DK > ?
             ORDER BY dtp.NGAY_NHAN ASC"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('iss', $costumeId, $endDate, $startDate);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $result = $stmt->get_result();
        $rentals = [];
        
        while ($row = $result->fetch_assoc()) {
            $rentals[] = $row;
        }
        
        $stmt->close();
        return $rentals;
    }

    /**
     * Kiểm tra trang phục có hạn sử dụng (hiệu lực)
     * 
     * @param int $costumeId ID trang phục
     * @param string $checkDate Ngày kiểm tra (mặc định hôm nay)
     * @return array ['valid'=>bool, 'reason'=>string|null, 'valid_from'=>string|null, 'valid_until'=>string|null]
     */
    public function checkValidity(int $costumeId, string $checkDate = ''): array
    {
        if (empty($checkDate)) {
            $checkDate = date('Y-m-d H:i:s');
        }
        
        $stmt = $this->connection->prepare(
            "SELECT HIEU_LUC_TU, HIEU_LUC_DEN FROM trang_phuc WHERE ID_TRANG_PHUC = ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('i', $costumeId);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$result) {
            return [
                'valid' => false,
                'reason' => 'Trang phục không tồn tại',
                'valid_from' => null,
                'valid_until' => null
            ];
        }
        
        $validFrom = $result['HIEU_LUC_TU'];
        $validUntil = $result['HIEU_LUC_DEN'];
        
        // Kiểm tra hạn từ
        if ($validFrom && $checkDate < $validFrom) {
            return [
                'valid' => false,
                'reason' => "Trang phục chưa hiệu lực (từ {$validFrom})",
                'valid_from' => $validFrom,
                'valid_until' => $validUntil
            ];
        }
        
        // Kiểm tra hạn đến
        if ($validUntil && $checkDate > $validUntil) {
            return [
                'valid' => false,
                'reason' => "Trang phục đã hết hạn sử dụng (đến {$validUntil})",
                'valid_from' => $validFrom,
                'valid_until' => $validUntil
            ];
        }
        
        return [
            'valid' => true,
            'reason' => null,
            'valid_from' => $validFrom,
            'valid_until' => $validUntil
        ];
    }

    /**
     * Kiểm tra trang phục có thể cho chi nhánh khác không (scope)
     * 
     * @param int $costumeId ID trang phục
     * @param int $rentalBranchId ID chi nhánh thuê
     * @return array ['allowed'=>bool, 'reason'=>string|null, 'scope_type'=>string, 'owner_branch'=>int|null]
     */
    public function checkBranchAccess(int $costumeId, int $rentalBranchId): array
    {
        $stmt = $this->connection->prepare(
            "SELECT SCOPE_TYPE, ID_CN_OWNER FROM trang_phuc WHERE ID_TRANG_PHUC = ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('i', $costumeId);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$result) {
            return [
                'allowed' => false,
                'reason' => 'Trang phục không tồn tại',
                'scope_type' => null,
                'owner_branch' => null
            ];
        }
        
        $scopeType = $result['SCOPE_TYPE'] ?? 'global';
        $ownerBranch = $result['ID_CN_OWNER'];
        
        // Nếu là global thì cho thuê được ở bất kỳ chi nhánh nào
        if ($scopeType === 'global') {
            return [
                'allowed' => true,
                'reason' => null,
                'scope_type' => $scopeType,
                'owner_branch' => $ownerBranch
            ];
        }
        
        // Nếu là local, chỉ cho thuê ở chi nhánh chủ quản
        if ($scopeType === 'local' && $ownerBranch !== $rentalBranchId) {
            return [
                'allowed' => false,
                'reason' => "Trang phục nội bộ chỉ có thể cho thuê tại chi nhánh {$ownerBranch}",
                'scope_type' => $scopeType,
                'owner_branch' => $ownerBranch
            ];
        }
        
        return [
            'allowed' => true,
            'reason' => null,
            'scope_type' => $scopeType,
            'owner_branch' => $ownerBranch
        ];
    }

    /**
     * Tính thời gian cho thuê bị trễ (nếu có)
     * 
     * @param int $rentalId ID đơn thuê
     * @return array ['is_overdue'=>bool, 'days_overdue'=>int, 'expected_return'=>string, 'actual_return'=>string|null]
     */
    public function calculateOverdue(int $rentalId): array
    {
        $stmt = $this->connection->prepare(
            "SELECT NGAY_TRA_DK, NGAY_TRA_THAT FROM don_thue_trang_phuc WHERE ID_TTP = ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('i', $rentalId);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$result) {
            return [
                'is_overdue' => false,
                'days_overdue' => 0,
                'expected_return' => null,
                'actual_return' => null
            ];
        }
        
        $expectedReturn = $result['NGAY_TRA_DK'];
        $actualReturn = $result['NGAY_TRA_THAT'];
        
        // Nếu chưa trả, kiểm tra so với hôm nay
        if (!$actualReturn) {
            $actualReturn = date('Y-m-d H:i:s');
        }
        
        $expectedTime = strtotime($expectedReturn);
        $actualTime = strtotime($actualReturn);
        
        if ($expectedTime === false || $actualTime === false) {
            return [
                'is_overdue' => false,
                'days_overdue' => 0,
                'expected_return' => $expectedReturn,
                'actual_return' => $actualReturn
            ];
        }
        
        $daysOverdue = max(0, (int)ceil(($actualTime - $expectedTime) / 86400));
        
        return [
            'is_overdue' => $daysOverdue > 0,
            'days_overdue' => $daysOverdue,
            'expected_return' => $expectedReturn,
            'actual_return' => $actualReturn
        ];
    }

    /**
     * Lấy lịch sử cho thuê của một trang phục
     * 
     * @param int $costumeId ID trang phục
     * @param int $limit Số đơn thuê gần nhất
     * @return array Array of rental history
     */
    public function getRentalHistory(int $costumeId, int $limit = 10): array
    {
        $stmt = $this->connection->prepare(
            "SELECT dtp.ID_TTP, dtp.NGAY_NHAN, dtp.NGAY_TRA_DK, dtp.NGAY_TRA_THAT, 
                    dtp.TRANG_THAI, k.TEN_KH, dtp.TONG_TIEN_DU_KIEN, dtp.PHI_TRE_HEN
             FROM don_thue_trang_phuc dtp
             LEFT JOIN khach_hang k ON dtp.ID_KH = k.ID_KH
             WHERE dtp.ID_TRANG_PHUC = ?
             ORDER BY dtp.NGAY_NHAN DESC
             LIMIT ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('ii', $costumeId, $limit);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $result = $stmt->get_result();
        $history = [];
        
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
        
        $stmt->close();
        return $history;
    }

    /**
     * Kiểm tra trang phục có thể thêm vào đơn thuê không (comprehensive check)
     * 
     * @param int $costumeId ID trang phục
     * @param int $branchId ID chi nhánh thuê
     * @param string $startDate Ngày bắt đầu
     * @param string $endDate Ngày kết thúc
     * @return array ['can_rent'=>bool, 'issues'=>array]
     */
    public function validateForRental(int $costumeId, int $branchId, string $startDate, string $endDate): array
    {
        $issues = [];
        
        // Kiểm tra hiệu lực
        $validityCheck = $this->checkValidity($costumeId);
        if (!$validityCheck['valid']) {
            $issues[] = ['type' => 'validity', 'message' => $validityCheck['reason']];
        }
        
        // Kiểm tra chi nhánh
        $branchCheck = $this->checkBranchAccess($costumeId, $branchId);
        if (!$branchCheck['allowed']) {
            $issues[] = ['type' => 'branch', 'message' => $branchCheck['reason']];
        }
        
        // Kiểm tra tính khả dụng
        $availCheck = $this->isAvailableDuring($costumeId, $startDate, $endDate);
        if (!$availCheck['available']) {
            $issues[] = ['type' => 'availability', 'message' => $availCheck['reason'], 'conflicts' => $availCheck['conflicts']];
        }
        
        return [
            'can_rent' => empty($issues),
            'issues' => $issues
        ];
    }

    /**
     * Đánh dấu trang phục bị hư hỏng (maintenance)
     * 
     * @param int $costumeId ID trang phục
     * @param string $reason Lý do bảo trì
     * @return bool True if successful
     */
    public function markForMaintenance(int $costumeId, string $reason = ''): bool
    {
        $stmt = $this->connection->prepare(
            "UPDATE trang_phuc SET TRANG_THAI = 'maintenance', GHI_CHU = ? WHERE ID_TRANG_PHUC = ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('si', $reason, $costumeId);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        return $affected > 0;
    }

    /**
     * Đánh dấu trang phục sẵn sàng (available)
     * 
     * @param int $costumeId ID trang phục
     * @return bool True if successful
     */
    public function markAsAvailable(int $costumeId): bool
    {
        $stmt = $this->connection->prepare(
            "UPDATE trang_phuc SET TRANG_THAI = 'available', GHI_CHU = NULL WHERE ID_TRANG_PHUC = ?"
        );
        
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        
        $stmt->bind_param('i', $costumeId);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }
        
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        return $affected > 0;
    }
}
