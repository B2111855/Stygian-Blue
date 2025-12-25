<?php

namespace App\Services;

use App\Repositories\CostumeRepository;
use App\Repositories\RentalValidationRepository;
use App\Repositories\CostumePackageMasterRepository;
use App\Repositories\PackageCostumeRepository;
use mysqli;
use RuntimeException;

class RentalService
{
    private mysqli $connection;
    private CostumeRepository $costumeRepo;
    private RentalValidationRepository $validationRepo;
    private CostumePackageMasterRepository $packageRepo;
    private PackageCostumeRepository $pivotRepo;

    public function __construct(
        mysqli $connection,
        CostumeRepository $costumeRepo,
        RentalValidationRepository $validationRepo,
        CostumePackageMasterRepository $packageRepo,
        PackageCostumeRepository $pivotRepo
    ) {
        $this->connection = $connection;
        $this->costumeRepo = $costumeRepo;
        $this->validationRepo = $validationRepo;
        $this->packageRepo = $packageRepo;
        $this->pivotRepo = $pivotRepo;
    }

    /**
     * Tạo đơn thuê lẻ (single costume)
     * 
     * @param int $costumeId ID trang phục
     * @param int $customerId ID khách hàng
     * @param string $startDate Ngày bắt đầu (Y-m-d H:i:s)
     * @param string $endDate Ngày kết thúc (Y-m-d H:i:s)
     * @param int $branchId ID chi nhánh
     * @param int $quantity Số lượng
     * @param int $deposit Tiền cọc
     * @param string $userId User ID (CREATED_BY)
     * @return int New rental ID
     * @throws RuntimeException on validation failure
     */
    public function createCostumeRental(
        int $costumeId,
        int $customerId,
        string $startDate,
        string $endDate,
        int $branchId,
        int $quantity = 1,
        int $deposit = 0,
        string $userId = ''
    ): int {
        // Validate dates
        $this->validateRentalDates($startDate, $endDate);

        // Validate inventory availability
        $availCheck = $this->validationRepo->countAvailableByType(
            $costumeId,
            $quantity,
            $startDate,
            $endDate
        );

        if (!$availCheck['available']) {
            throw new RuntimeException($availCheck['reason']);
        }

        // Validate rental
        $rentalCheck = $this->validationRepo->validateForRental($costumeId, $branchId, $startDate, $endDate);
        if (!$rentalCheck['can_rent']) {
            $issues = implode('; ', array_map(function($issue) {
                return $issue['message'];
            }, $rentalCheck['issues']));
            throw new RuntimeException("Không thể cho thuê: {$issues}");
        }

        // Calculate rental cost
        $costume = $this->costumeRepo->findById($costumeId);
        if (!$costume) {
            throw new RuntimeException('Trang phục không tồn tại');
        }

        $days = $this->calculateRentalDays($startDate, $endDate);
        $basePrice = (int)$costume['GIA_THUE'] * $days * $quantity;

        // Create rental record
        $stmt = $this->connection->prepare(
            "INSERT INTO don_thue_trang_phuc 
            (ID_KH, ID_TRANG_PHUC, NGAY_NHAN, NGAY_TRA_DK, NGAY_DAT, TRANG_THAI, TIEN_COC, TONG_TIEN_DU_KIEN, CREATED_BY, UPDATED_BY, CREATED_AT, UPDATED_AT)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }

        $now = date('Y-m-d H:i:s');
        $status = 'pending';

        $stmt->bind_param(
            'iisssisssss',
            $customerId,
            $costumeId,
            $startDate,
            $endDate,
            $now,
            $status,
            $deposit,
            $basePrice,
            $userId,
            $userId,
            $now,
            $now
        );

        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }

        $rentalId = (int)$this->connection->insert_id;
        $stmt->close();

        return $rentalId;
    }

    /**
     * Tạo đơn thuê từ gói trang phục
     * 
     * @param int $packageId ID gói
     * @param int $customerId ID khách hàng
     * @param string $startDate Ngày bắt đầu
     * @param string $endDate Ngày kết thúc
     * @param int $branchId ID chi nhánh
     * @param array $selectedCostumeIds Optional specific costumes (subset)
     * @param int $deposit Tiền cọc
     * @param string $userId User ID
     * @return int New rental ID
     */
    public function createPackageRental(
        int $packageId,
        int $customerId,
        string $startDate,
        string $endDate,
        int $branchId,
        array $selectedCostumeIds = [],
        int $deposit = 0,
        string $userId = ''
    ): int {
        // Validate dates
        $this->validateRentalDates($startDate, $endDate);

        // Check package availability
        $pkgAvail = $this->validationRepo->isPackageAvailable($packageId, $startDate, $endDate);
        if (!$pkgAvail['available']) {
            $missing = implode(', ', array_map(function($item) {
                return $item['costume_name'];
            }, $pkgAvail['missing']));
            throw new RuntimeException("Gói không sẵn sàng. Trang phục thiếu: {$missing}");
        }

        // Get all package costumes
        $packageItems = $this->pivotRepo->findByPackage($packageId);
        if (empty($packageItems)) {
            throw new RuntimeException('Gói trang phục không có trang phục nào');
        }

        // Calculate total price
        $totalPrice = $this->pivotRepo->calculateTotalPrice($packageId);

        // Create rental
        $stmt = $this->connection->prepare(
            "INSERT INTO don_thue_trang_phuc 
            (ID_KH, ID_GOI, NGAY_NHAN, NGAY_TRA_DK, NGAY_DAT, TRANG_THAI, TIEN_COC, TONG_TIEN_DU_KIEN, CREATED_BY, UPDATED_BY, CREATED_AT, UPDATED_AT)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }

        $now = date('Y-m-d H:i:s');
        $status = 'pending';

        $stmt->bind_param(
            'iisssisssss',
            $customerId,
            $packageId,
            $startDate,
            $endDate,
            $now,
            $status,
            $deposit,
            $totalPrice,
            $userId,
            $userId,
            $now,
            $now
        );

        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }

        $rentalId = (int)$this->connection->insert_id;
        $stmt->close();

        return $rentalId;
    }

    /**
     * Xác nhận đơn thuê (pending → confirmed)
     * 
     * @param int $rentalId ID đơn thuê
     * @param string $userId User ID
     * @return bool True if successful
     */
    public function confirmRental(int $rentalId, string $userId = ''): bool
    {
        $stmt = $this->connection->prepare(
            "UPDATE don_thue_trang_phuc 
            SET TRANG_THAI = 'confirmed', UPDATED_BY = ?, UPDATED_AT = ? 
            WHERE ID_TTP = ? AND TRANG_THAI = 'pending'"
        );

        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }

        $now = date('Y-m-d H:i:s');
        $stmt->bind_param('ssi', $userId, $now, $rentalId);

        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }

        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected > 0;
    }

    /**
     * Xử lý trả trang phục
     * 
     * @param int $rentalId ID đơn thuê
     * @param string $actualReturnDate Ngày trả thực tế
     * @param int $finalAmount Số tiền thanh toán cuối cùng
     * @param string $userId User ID
     * @return array {success: bool, overdue_days: int, late_fee: int}
     */
    public function processReturn(
        int $rentalId,
        string $actualReturnDate,
        int $finalAmount,
        string $userId = ''
    ): array {
        // Get rental info
        $stmt = $this->connection->prepare(
            "SELECT ID_TTP, NGAY_TRA_DK, NGAY_TRA_THAT FROM don_thue_trang_phuc WHERE ID_TTP = ?"
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
            throw new RuntimeException('Đơn thuê không tồn tại');
        }

        // Calculate overdue
        $overdueResult = $this->validationRepo->calculateOverdue($rentalId);
        $lateFee = $this->calculateLateFee($overdueResult['days_overdue']);

        // Update rental with return info
        $updateStmt = $this->connection->prepare(
            "UPDATE don_thue_trang_phuc 
            SET NGAY_TRA_THAT = ?, PHI_TRE_HEN = ?, TONG_TIEN_THUC_TE = ?, TRANG_THAI = 'tra', UPDATED_BY = ?, UPDATED_AT = ? 
            WHERE ID_TTP = ?"
        );

        if (!$updateStmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }

        $now = date('Y-m-d H:i:s');
        $updateStmt->bind_param('siissi', $actualReturnDate, $lateFee, $finalAmount, $userId, $now, $rentalId);

        if (!$updateStmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }

        $updateStmt->close();

        return [
            'success' => true,
            'overdue_days' => $overdueResult['days_overdue'],
            'late_fee' => $lateFee
        ];
    }

    /**
     * Hủy đơn thuê
     * 
     * @param int $rentalId ID đơn thuê
     * @param string $reason Lý do hủy
     * @param string $userId User ID
     * @return bool True if successful
     */
    public function cancelRental(int $rentalId, string $reason = '', string $userId = ''): bool
    {
        $stmt = $this->connection->prepare(
            "UPDATE don_thue_trang_phuc 
            SET TRANG_THAI = 'huy', GHI_CHU = ?, UPDATED_BY = ?, UPDATED_AT = ? 
            WHERE ID_TTP = ? AND TRANG_THAI IN ('pending', 'confirmed')"
        );

        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }

        $now = date('Y-m-d H:i:s');
        $stmt->bind_param('sssi', $reason, $userId, $now, $rentalId);

        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }

        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected > 0;
    }

    /**
     * Tìm trang phục sẵn sàng cho thuê
     * 
     * @param int $typeId ID loại trang phục
     * @param string $startDate Ngày bắt đầu
     * @param string $endDate Ngày kết thúc
     * @return array List of available costumes
     */
    public function findAvailableCostumes(int $typeId, string $startDate, string $endDate): array
    {
        return $this->validationRepo->findAvailableByType($typeId, $startDate, $endDate);
    }

    /**
     * Kiểm tra có xung đột lịch không
     * 
     * @param int $costumeId ID trang phục
     * @param string $startDate Ngày bắt đầu
     * @param string $endDate Ngày kết thúc
     * @return array List of conflicting rentals
     */
    public function detectConflicts(int $costumeId, string $startDate, string $endDate): array
    {
        return $this->validationRepo->getConflictingRentals($costumeId, $startDate, $endDate);
    }

    /**
     * Tính giá thuê trang phục
     * 
     * @param int $costumeId ID trang phục
     * @param string $startDate Ngày bắt đầu
     * @param string $endDate Ngày kết thúc
     * @param int $quantity Số lượng
     * @return int Total rental cost
     */
    public function calculateRentalCost(
        int $costumeId,
        string $startDate,
        string $endDate,
        int $quantity = 1
    ): int {
        $costume = $this->costumeRepo->findById($costumeId);
        if (!$costume) {
            throw new RuntimeException('Trang phục không tồn tại');
        }

        $days = $this->calculateRentalDays($startDate, $endDate);
        $basePrice = (int)$costume['GIA_THUE'];

        return $basePrice * $days * $quantity;
    }

    /**
     * Tính phí trễ hạn
     * 
     * @param int $overdayCount Số ngày trễ
     * @return int Late fee in VND (up to 50000/day)
     */
    public function calculateLateFee(int $overdayCount): int
    {
        // Mỗi ngày trễ: 50000 VND tối đa
        $feePerDay = 50000;
        return min($overdayCount * $feePerDay, $overdayCount * $feePerDay);
    }

    /**
     * Tính tổng tiền (rent + late fee)
     * 
     * @param int $rentalCost Giá thuê cơ bản
     * @param int $lateFee Phí trễ hạn
     * @param int $deposit Tiền cọc
     * @return int Total amount to pay
     */
    public function calculateTotalAmount(int $rentalCost, int $lateFee, int $deposit): int
    {
        $total = $rentalCost + $lateFee - $deposit;
        return max(0, $total);
    }

    /**
     * Lấy lịch sử cho thuê của khách hàng
     * 
     * @param int $customerId ID khách hàng
     * @param int $limit Số bản ghi
     * @return array Rental history
     */
    public function getCustomerRentals(int $customerId, int $limit = 20): array
    {
        $stmt = $this->connection->prepare(
            "SELECT dtp.*, tp.TEN as COSTUME_NAME 
            FROM don_thue_trang_phuc dtp
            LEFT JOIN trang_phuc tp ON dtp.ID_TRANG_PHUC = tp.ID_TRANG_PHUC
            WHERE dtp.ID_KH = ?
            ORDER BY dtp.NGAY_DAT DESC
            LIMIT ?"
        );

        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }

        $stmt->bind_param('ii', $customerId, $limit);

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
     * Lấy danh sách đơn thuê quá hạn
     * 
     * @return array List of overdue rentals
     */
    public function getOverdueRentals(): array
    {
        $stmt = $this->connection->prepare(
            "SELECT dtp.*, tp.TEN as COSTUME_NAME, k.TEN_KH 
            FROM don_thue_trang_phuc dtp
            LEFT JOIN trang_phuc tp ON dtp.ID_TRANG_PHUC = tp.ID_TRANG_PHUC
            LEFT JOIN khach_hang k ON dtp.ID_KH = k.ID_KH
            WHERE dtp.TRANG_THAI = 'confirmed' 
            AND dtp.NGAY_TRA_DK < NOW()
            ORDER BY dtp.NGAY_TRA_DK ASC"
        );

        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }

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
     * Lấy chi tiết đơn thuê
     * 
     * @param int $rentalId ID đơn thuê
     * @return array|null Rental details or null
     */
    public function getRental(int $rentalId): ?array
    {
        $stmt = $this->connection->prepare(
            "SELECT * FROM don_thue_trang_phuc WHERE ID_TTP = ?"
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

        return $result;
    }

    /**
     * Validate rental dates
     * 
     * @param string $startDate Ngày bắt đầu
     * @param string $endDate Ngày kết thúc
     * @throws RuntimeException if dates invalid
     */
    private function validateRentalDates(string $startDate, string $endDate): void
    {
        $start = strtotime($startDate);
        $end = strtotime($endDate);
        $now = time();

        if ($start === false || $end === false) {
            throw new RuntimeException('Ngày không hợp lệ');
        }

        if ($start > $end) {
            throw new RuntimeException('Ngày bắt đầu phải trước ngày kết thúc');
        }

        if ($start <= $now) {
            throw new RuntimeException('Ngày bắt đầu không được là quá khứ');
        }
    }

    /**
     * Calculate rental days (inclusive)
     * 
     * @param string $startDate Ngày bắt đầu
     * @param string $endDate Ngày kết thúc
     * @return int Number of days
     */
    private function calculateRentalDays(string $startDate, string $endDate): int
    {
        $start = strtotime($startDate);
        $end = strtotime($endDate);
        $days = (int)ceil(($end - $start) / 86400);
        return max(1, $days);
    }

    /**
     * Kiểm tra trang phục có thể cho chi nhánh khác không
     * 
     * @param int $costumeId ID trang phục
     * @param int $branchId ID chi nhánh
     * @return bool True if allowed
     */
    public function validateBranchAccess(int $costumeId, int $branchId): bool
    {
        $check = $this->validationRepo->checkBranchAccess($costumeId, $branchId);
        return $check['allowed'];
    }

    /**
     * Kiểm tra trang phục có hiệu lực
     * 
     * @param int $costumeId ID trang phục
     * @return bool True if valid
     */
    public function isValidCostume(int $costumeId): bool
    {
        $check = $this->validationRepo->checkValidity($costumeId);
        return $check['valid'];
    }

    /**
     * Đếm đơn thuê theo trạng thái
     * 
     * @param string $status Status (pending, confirmed, tra, huy)
     * @return int Count
     */
    public function countByStatus(string $status): int
    {
        $stmt = $this->connection->prepare(
            "SELECT COUNT(*) as total FROM don_thue_trang_phuc WHERE TRANG_THAI = ?"
        );

        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }

        $stmt->bind_param('s', $status);

        if (!$stmt->execute()) {
            throw new RuntimeException('Execute failed: ' . $this->connection->error);
        }

        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int)($result['total'] ?? 0);
    }
}
