<?php
namespace App\Repositories;

class PackageRepository {
    private \mysqli $conn;

    public function __construct(\mysqli $conn) { $this->conn = $conn; }

    public function countPackages(string $search=''): int {
        $sql = "SELECT COUNT(*) total FROM goi_dich_vu g WHERE (g.TEN_GOI LIKE ? OR g.MO_TA LIKE ?)";
        $stmt = $this->conn->prepare($sql);
        $like = '%'.$search.'%';
        $stmt->bind_param('ss', $like, $like);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return (int)($row['total'] ?? 0);
    }

    public function searchPackages(string $search='', int $limit=10, int $offset=0, ?int $branchScope=null): array {
        // branchScope: if provided, include global packages + local owned by branch
        // Join with promotion view if it exists
        $hasPromoView = false;
        $checkView = $this->conn->query("SHOW TABLES LIKE 'v_goi_dich_vu_gia_khuyen_mai'");
        if ($checkView && $checkView->num_rows > 0) { $hasPromoView = true; }
        
        if ($hasPromoView) {
            $sql = "SELECT g.*, 
                    vt.TONG_GIA_GOI,
                    vkm.ID_KM,
                    vkm.TEN_CHUONG_TRINH,
                    vkm.SO_TIEN_GIAM,
                    vkm.GIA_SAU_GIAM
                    FROM goi_dich_vu g
                    LEFT JOIN v_goi_dich_vu_tong_tien vt ON vt.ID_GOI = g.ID_GOI
                    LEFT JOIN v_goi_dich_vu_gia_khuyen_mai vkm ON vkm.ID_GOI = g.ID_GOI
                    WHERE (g.TEN_GOI LIKE ? OR g.MO_TA LIKE ?)";
        } else {
            $sql = "SELECT g.*, vt.TONG_GIA_GOI
                    FROM goi_dich_vu g
                    LEFT JOIN v_goi_dich_vu_tong_tien vt ON vt.ID_GOI = g.ID_GOI
                    WHERE (g.TEN_GOI LIKE ? OR g.MO_TA LIKE ?)";
        }
        
        $like = '%'.$search.'%';
        if ($branchScope !== null) {
            $sql .= " AND (g.SCOPE_TYPE='global' OR (g.SCOPE_TYPE='local' AND g.ID_CN_OWNER=?))";
        }
        $sql .= " ORDER BY g.ID_GOI DESC LIMIT ? OFFSET ?";
        if ($branchScope !== null) {
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param('ssiii', $like, $like, $branchScope, $limit, $offset);
        } else {
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param('ssii', $like, $like, $limit, $offset);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) { $rows[] = $r; }
        return $rows;
    }

    public function find(int $id): ?array {
        $stmt = $this->conn->prepare("SELECT g.*, vt.TONG_GIA_GOI FROM goi_dich_vu g LEFT JOIN v_goi_dich_vu_tong_tien vt ON vt.ID_GOI=g.ID_GOI WHERE g.ID_GOI=? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    public function create(array $data, ?string $imagePath): int {
        // expects SCOPE_TYPE + ID_CN_OWNER optionally inside $data
        $scope = $data['SCOPE_TYPE'] ?? 'global';
        $owner = $data['ID_CN_OWNER'] ?? null;
        $stmt = $this->conn->prepare("INSERT INTO goi_dich_vu (TEN_GOI, MO_TA, HINH_ANH, HIEU_LUC_TU, HIEU_LUC_DEN, SCOPE_TYPE, ID_CN_OWNER, TRANG_THAI) VALUES (?,?,?,?,?,?,?, 'nhap')");
        // 7 placeholders -> 7 types: 6 strings + 1 int (nullable OK)
        $stmt->bind_param('ssssssi', $data['TEN_GOI'], $data['MO_TA'], $imagePath, $data['HIEU_LUC_TU'], $data['HIEU_LUC_DEN'], $scope, $owner);
        if (!$stmt->execute()) throw new \RuntimeException('Create package failed: '.$stmt->error);
        return (int)$this->conn->insert_id;
    }

    public function update(array $data, ?string $imagePath): bool {
        // Safely map fields; allow partial updates from form submissions
        $fields = [];
        $params = [];
        $types  = '';

        if (array_key_exists('TEN_GOI', $data)) { $fields[] = 'TEN_GOI = ?'; $params[] = (string)$data['TEN_GOI']; $types .= 's'; }
        if (array_key_exists('MO_TA', $data)) { $fields[] = 'MO_TA = ?'; $params[] = $data['MO_TA'] !== '' ? (string)$data['MO_TA'] : null; $types .= 's'; }
        if (array_key_exists('HIEU_LUC_TU', $data)) { $fields[] = 'HIEU_LUC_TU = ?'; $params[] = $data['HIEU_LUC_TU'] ?: null; $types .= 's'; }
        if (array_key_exists('HIEU_LUC_DEN', $data)) { $fields[] = 'HIEU_LUC_DEN = ?'; $params[] = $data['HIEU_LUC_DEN'] ?: null; $types .= 's'; }
        if (array_key_exists('SCOPE_TYPE', $data)) { $fields[] = 'SCOPE_TYPE = ?'; $params[] = (string)$data['SCOPE_TYPE']; $types .= 's'; }
        if (array_key_exists('ID_CN_OWNER', $data)) { $fields[] = 'ID_CN_OWNER = ?'; $params[] = $data['ID_CN_OWNER'] !== '' ? (int)$data['ID_CN_OWNER'] : null; $types .= 'i'; }
        if ($imagePath) { $fields[] = 'HINH_ANH = ?'; $params[] = $imagePath; $types .= 's'; }

        if (empty($fields)) { return true; }

        $sql = 'UPDATE goi_dich_vu SET '.implode(', ', $fields).' WHERE ID_GOI=?';
        $stmt = $this->conn->prepare($sql);
        $params[] = (int)$data['ID_GOI'];
        $types   .= 'i';
        $stmt->bind_param($types, ...$params);
        return $stmt->execute();
    }

    /**
     * CRITICAL FIX #S3: Check if package can be published (must have at least 1 service)
     * Empty Package Validation - prevents publishing empty packages
     */
    public function canPublishPackage(int $packageId): array {
        $stmt = $this->conn->prepare("SELECT COUNT(*) c FROM goi_dich_vu_chi_tiet WHERE ID_GOI=?");
        $stmt->bind_param('i', $packageId);
        $stmt->execute();
        $count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);

        if ($count === 0) {
            return [
                'can_publish' => false,
                'reason' => 'Gói phải có ít nhất 1 dịch vụ trước khi xuất bản.',
                'service_count' => 0,
                'code' => 'empty_package'
            ];
        }

        return [
            'can_publish' => true,
            'service_count' => $count,
            'code' => 'valid'
        ];
    }

    /**
     * CRITICAL FIX #S4: Lock package prices on publish
     * Price Locking - prevents future service price changes from affecting published packages
     * 
     * Algorithm:
     *  1. Find all services without override price (DON_GIA_AP_DUNG IS NULL)
     *  2. For each: Get latest price from don_gia_dich_vu table
     *  3. Set DON_GIA_AP_DUNG = latest price (snapshot)
     *  4. Mark PRICE_LOCKED=1 for tracking
     * 
     * @param int $packageId
     * @return int Number of service prices locked
     */
    public function lockPackagePrices(int $packageId): int {
        // Ensure PRICE_LOCKED column exists (migration fallback)
        $this->ensurePriceLockedColumn();
        
        // Step 1: Find unlocked services (no override price set)
        $stmt = $this->conn->prepare("SELECT ID_DV FROM goi_dich_vu_chi_tiet WHERE ID_GOI=? AND DON_GIA_AP_DUNG IS NULL");
        $stmt->bind_param('i', $packageId);
        $stmt->execute();
        $result = $stmt->get_result();
        $serviceIds = [];
        while ($row = $result->fetch_assoc()) {
            $serviceIds[] = (int)$row['ID_DV'];
        }
        
        if (empty($serviceIds)) {
            return 0; // All prices already locked/overridden
        }

        // Step 2 & 3: Get latest price and update
        $priceStmt = $this->conn->prepare("SELECT DON_GIA FROM don_gia_dich_vu WHERE ID_DV=? ORDER BY NGAY_GIO DESC LIMIT 1");
        
        // Try PRICE_LOCKED column first, fallback without it
        $colExists = $this->columnExists('goi_dich_vu_chi_tiet', 'PRICE_LOCKED');
        $updateSql = $colExists 
            ? "UPDATE goi_dich_vu_chi_tiet SET DON_GIA_AP_DUNG=?, PRICE_LOCKED=1 WHERE ID_GOI=? AND ID_DV=?"
            : "UPDATE goi_dich_vu_chi_tiet SET DON_GIA_AP_DUNG=? WHERE ID_GOI=? AND ID_DV=?";
        
        $updateStmt = $this->conn->prepare($updateSql);
        if (!$priceStmt || !$updateStmt) {
            throw new \RuntimeException('Prepare lockPackagePrices failed: ' . $this->conn->error);
        }

        $locked = 0;
        foreach ($serviceIds as $serviceId) {
            $priceStmt->bind_param('i', $serviceId);
            $priceStmt->execute();
            $priceRow = $priceStmt->get_result()->fetch_assoc();
            if (!$priceRow || !isset($priceRow['DON_GIA'])) {
                continue;
            }
            $price = (int)$priceRow['DON_GIA'];
            $updateStmt->bind_param('iii', $price, $packageId, $serviceId);
            if ($updateStmt->execute()) {
                $locked++;
            }
        }

        return $locked;
    }
    
    /**
     * Migration helper: Ensure PRICE_LOCKED column exists in goi_dich_vu_chi_tiet
     */
    private function ensurePriceLockedColumn(): void {
        if ($this->columnExists('goi_dich_vu_chi_tiet', 'PRICE_LOCKED')) {
            return; // Column exists
        }
        // Add column if missing
        $sql = "ALTER TABLE goi_dich_vu_chi_tiet ADD COLUMN PRICE_LOCKED INT DEFAULT 0";
        try {
            $this->conn->query($sql);
            error_log('[PackageRepository] Added PRICE_LOCKED column to goi_dich_vu_chi_tiet');
        } catch (\Throwable $e) {
            error_log('[PackageRepository] Failed to add PRICE_LOCKED: ' . $e->getMessage());
        }
    }
    
    /**
     * Helper: Check if column exists in table
     */
    private function columnExists(string $table, string $column): bool {
        $result = $this->conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $result && $result->num_rows > 0;
    }

    private function tableExists(string $table): bool {
        $tbl = $this->conn->real_escape_string($table);
        $res = $this->conn->query("SHOW TABLES LIKE '$tbl'");
        return $res && $res->num_rows > 0;
    }

    public function changeStatus(int $id, string $status): bool {
        $allowed = ['nhap','ban','ngung'];
        if (!in_array($status, $allowed, true)) return false;

        if ($status === 'ban') {
            $check = $this->canPublishPackage($id);
            if (!$check['can_publish']) {
                throw new \RuntimeException($check['reason']);
            }
            $lockedCount = $this->lockPackagePrices($id);
            error_log(sprintf('[PackageRepository] Locked %d service price(s) for package %d', $lockedCount, $id));
        }

        $now = date('Y-m-d H:i:s');
        $fields = ['TRANG_THAI = ?'];
        $params = [$status];
        $types = 's';

        $hasUpdatedAt   = $this->columnExists('goi_dich_vu', 'UPDATED_AT');
        $hasPublishedAt = $this->columnExists('goi_dich_vu', 'PUBLISHED_AT');

        if ($hasUpdatedAt) {
            $fields[] = 'UPDATED_AT = ?';
            $params[] = $now;
            $types   .= 's';
        }
        if ($status === 'ban' && $hasPublishedAt) {
            $fields[] = 'PUBLISHED_AT = ?';
            $params[] = $now;
            $types   .= 's';
        }

        $types .= 'i';
        $params[] = $id;

        $sql = 'UPDATE goi_dich_vu SET ' . implode(', ', $fields) . ' WHERE ID_GOI=?';
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException('Prepare changeStatus failed: ' . $this->conn->error);
        }
        $stmt->bind_param($types, ...$params);
        return $stmt->execute();
    }

    public function addService(int $idGoi, int $idDv, int $soLuong=1, ?int $donGiaOverride=null, int $thuTu=1): bool {
        $stmt = $this->conn->prepare("REPLACE INTO goi_dich_vu_chi_tiet (ID_GOI, ID_DV, SO_LUONG, DON_GIA_AP_DUNG, THU_TU) VALUES (?,?,?,?,?)");
        $stmt->bind_param('iiiii', $idGoi, $idDv, $soLuong, $donGiaOverride, $thuTu);
        return $stmt->execute();
    }

    public function canRemoveService(int $packageId, int $serviceId): array {
        $stmt = $this->conn->prepare("SELECT COUNT(*) c FROM goi_dich_vu_chi_tiet WHERE ID_GOI=?");
        $stmt->bind_param('i', $packageId);
        $stmt->execute();
        $count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);

        if ($count <= 1) {
            $pkgStmt = $this->conn->prepare("SELECT TRANG_THAI FROM goi_dich_vu WHERE ID_GOI=? LIMIT 1");
            $pkgStmt->bind_param('i', $packageId);
            $pkgStmt->execute();
            $pkg = $pkgStmt->get_result()->fetch_assoc();
            if ($pkg && in_array($pkg['TRANG_THAI'], ['nhap', 'ban'], true)) {
                return [
                    'can_remove' => false,
                    'reason' => 'Cannot remove the last service from a draft or published package.',
                ];
            }
        }

        return ['can_remove' => true];
    }

    public function removeService(int $idGoi, int $idDv): bool {
        $check = $this->canRemoveService($idGoi, $idDv);
        if (!$check['can_remove']) {
            throw new \RuntimeException($check['reason']);
        }

        $stmt = $this->conn->prepare("DELETE FROM goi_dich_vu_chi_tiet WHERE ID_GOI=? AND ID_DV=? LIMIT 1");
        $stmt->bind_param('ii', $idGoi, $idDv);
        return $stmt->execute();
    }

    public function listServices(int $idGoi): array {
        $sql = "SELECT ct.*, dv.TEN_DV, dv.THOI_GIAN FROM goi_dich_vu_chi_tiet ct JOIN dich_vu dv ON dv.ID_DV=ct.ID_DV WHERE ct.ID_GOI=? ORDER BY COALESCE(ct.THU_TU,1), ct.ID_DV";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $idGoi);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) { $rows[] = $r; }
        return $rows;
    }

    /**
     * Bulk reorder services inside a package by array of service IDs (new sequence starting at 1).
     */
    public function reorderServices(int $idGoi, array $orderedIds): bool {
        if (empty($orderedIds)) return false;
        $this->conn->begin_transaction();
        try {
            $seq = 1;
            $stmt = $this->conn->prepare("UPDATE goi_dich_vu_chi_tiet SET THU_TU=? WHERE ID_GOI=? AND ID_DV=?");
            foreach ($orderedIds as $idDv) {
                $idDv = (int)$idDv;
                $stmt->bind_param('iii', $seq, $idGoi, $idDv);
                if (!$stmt->execute()) throw new \RuntimeException('Update order failed for service '.$idDv);
                $seq++;
            }
            $this->conn->commit();
            return true;
        } catch (\Throwable $e) {
            $this->conn->rollback();
            return false;
        }
    }

    public function deletionBlockingReasons(int $idGoi): array {
        $reasons = [];
        // Active period & selling
        $stmt = $this->conn->prepare("SELECT TRANG_THAI, HIEU_LUC_TU, HIEU_LUC_DEN FROM goi_dich_vu WHERE ID_GOI=?");
        $stmt->bind_param('i', $idGoi); $stmt->execute(); $pkg = $stmt->get_result()->fetch_assoc();
        if (!$pkg) { return []; }
        $now = date('Y-m-d H:i:s');
        $activeWindow = (!$pkg['HIEU_LUC_TU'] || $pkg['HIEU_LUC_TU'] <= $now) && (!$pkg['HIEU_LUC_DEN'] || $pkg['HIEU_LUC_DEN'] >= $now);
        if ($pkg['TRANG_THAI'] === 'ban' && $activeWindow) {
            $reasons[] = ['code'=>'selling_active','message'=>'Gói đang bán trong thời gian hiệu lực.'];
        }
        // Future / processing appointments referencing package
        $q = $this->conn->prepare("SELECT COUNT(*) c FROM lich_hen WHERE ID_GOI=? AND TRANGTHAI NOT IN ('Đã hoàn thành','Đã hủy')");
        $q->bind_param('i', $idGoi); $q->execute(); $c1 = $q->get_result()->fetch_assoc()['c'] ?? 0;
        if ($c1 > 0) { $reasons[] = ['code'=>'future_appointments','message'=>"Có $c1 lịch hẹn chưa kết thúc."]; }
        return $reasons;
    }

    public function retire(int $idGoi): bool { return $this->changeStatus($idGoi,'ngung'); }

    public function delete(int $idGoi): bool {
        $stmt = $this->conn->prepare("DELETE FROM goi_dich_vu WHERE ID_GOI=?");
        $stmt->bind_param('i', $idGoi);
        return $stmt->execute();
    }

    /**
     * Get package total price with promotion applied
     * Returns: ['base_total'=>int, 'discount'=>int, 'subtotal'=>int, 'tax'=>int, 'final_total'=>int, 'promotion'=>array|null]
     */
    public function getPackageTotalWithPromotion(int $packageId, float $taxRate = 0.1): array {
        return $this->getPackagePromotionPricing($packageId, $taxRate);
    }

    /**
     * Get base total price of package (sum of all services)
     */
    public function getPackageTotalPrice(int $packageId): int {
        $sql = "SELECT COALESCE(SUM(
                    COALESCE(gct.DON_GIA_AP_DUNG, dgdv.DON_GIA, 0) * COALESCE(gct.SO_LUONG, 1)
                ), 0) AS total
                FROM goi_dich_vu_chi_tiet gct
                LEFT JOIN (
                    SELECT d1.ID_DV, d1.DON_GIA
                    FROM don_gia_dich_vu d1
                    INNER JOIN (
                        SELECT ID_DV, MAX(NGAY_GIO) AS MAX_DATE
                        FROM don_gia_dich_vu
                        GROUP BY ID_DV
                    ) d2 ON d1.ID_DV = d2.ID_DV AND d1.NGAY_GIO = d2.MAX_DATE
                ) dgdv ON gct.ID_DV = dgdv.ID_DV
                WHERE gct.ID_GOI = ?";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException('Prepare getPackageTotalPrice failed: ' . $this->conn->error);
        }
        $stmt->bind_param('i', $packageId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return (int)($result['total'] ?? 0);
    }

    /**
     * Unified pricing helper: applies both new package-level promos (goi_dich_vu_khuyen_mai)
     * and legacy global promos (khuyen_mai + goi_khuyen_mai). Picks the largest valid discount.
     */
    public function getPackagePromotionPricing(int $packageId, float $taxRate = 0.0): array {
        $baseTotal = $this->getPackageTotalPrice($packageId);
        $promotions = $this->collectActivePromotions($packageId, $baseTotal);
        usort($promotions, function ($a, $b) {
            return ($b['discount'] ?? 0) <=> ($a['discount'] ?? 0);
        });

        $best = $promotions[0] ?? ['discount' => 0, 'data' => null, 'source' => null];
        $discount = (int)($best['discount'] ?? 0);
        $subtotal = max(0, $baseTotal - $discount);
        $tax = (int)round($subtotal * $taxRate);

        return [
            'base_total' => $baseTotal,
            'discount' => $discount,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'final_total' => $subtotal + $tax,
            'promotion' => $best['data'],
            'promotion_source' => $best['source']
        ];
    }

    private function collectActivePromotions(int $packageId, int $baseTotal): array {
        $list = [];
        $list = array_merge($list, $this->fetchPackagePromotionsNew($packageId, $baseTotal));
        $list = array_merge($list, $this->fetchPackagePromotionsLegacy($packageId, $baseTotal));
        return $list;
    }

    private function fetchPackagePromotionsNew(int $packageId, int $baseTotal): array {
        if (!$this->tableExists('goi_dich_vu_khuyen_mai')) return [];
        $now = date('Y-m-d H:i:s');
        $stmt = $this->conn->prepare("SELECT * FROM goi_dich_vu_khuyen_mai WHERE ID_GOI=? AND ACTIVE=1 AND TU_NGAY <= ? AND DEN_NGAY >= ?");
        if (!$stmt) return [];
        $stmt->bind_param('iss', $packageId, $now, $now);
        $stmt->execute();
        $res = $stmt->get_result();
        $out = [];
        while ($row = $res->fetch_assoc()) {
            $discount = $this->computeDiscountFromRule($row['LOAI_GIAM'] ?? '', (float)($row['GIA_TRI_GIAM'] ?? 0), $baseTotal, $row['GIAM_TOI_DA'] !== null ? (float)$row['GIAM_TOI_DA'] : null);
            $out[] = ['discount' => $discount, 'data' => $row, 'source' => 'goi_dich_vu_khuyen_mai'];
        }
        return $out;
    }

    private function fetchPackagePromotionsLegacy(int $packageId, int $baseTotal): array {
        if (!$this->tableExists('khuyen_mai') || !$this->tableExists('goi_khuyen_mai')) return [];
        $now = date('Y-m-d H:i:s');
        $sql = "SELECT km.* FROM khuyen_mai km JOIN goi_khuyen_mai gkm ON gkm.ID_PROMO = km.ID_KHUYEN_MAI WHERE gkm.ID_GOI=? AND km.TRANG_THAI='active' AND km.NGAY_BAT_DAU <= ? AND km.NGAY_KET_THUC >= ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param('iss', $packageId, $now, $now);
        $stmt->execute();
        $res = $stmt->get_result();
        $out = [];
        while ($row = $res->fetch_assoc()) {
            $discount = $this->computeDiscountFromRule($row['KIEU_KM'] ?? '', (float)($row['GIA_TRI'] ?? 0), $baseTotal, null);
            $out[] = ['discount' => $discount, 'data' => $row, 'source' => 'khuyen_mai'];
        }
        return $out;
    }

    private function computeDiscountFromRule(string $type, float $value, int $baseTotal, ?float $maxCap): int {
        if ($baseTotal <= 0 || $value <= 0) return 0;
        $discount = 0;
        $normalized = strtolower($type);
        if (in_array($normalized, ['phan_tram','percent'], true)) {
            $discount = (int)floor($baseTotal * ($value / 100));
            if ($maxCap !== null) {
                $discount = min($discount, (int)$maxCap);
            }
        } elseif (in_array($normalized, ['so_tien','fixed'], true)) {
            $discount = (int)$value;
        }
        return max(0, min($discount, $baseTotal));
    }

    /**
     * Get all applicable promotions for a package
     * Filters by: date range, status, coupon requirements
     */
    private function getApplicablePromotions(int $packageId): array {
        $now = date('Y-m-d H:i:s');
        
        $sql = "SELECT 
                    km.ID_KM,
                    km.TEN_CHUONG_TRINH,
                    km.LOAI_GIAM,
                    km.MUC_DO_GIAM,
                    km.NGAY_BAT_DAU,
                    km.NGAY_KET_THUC,
                    km.TRANG_THAI,
                    km.YEU_CAU_MA_GIAM_GIA
                FROM khuyen_mai km
                WHERE EXISTS (
                    SELECT 1 FROM goi_khuyen_mai gkm
                    WHERE gkm.ID_KM = km.ID_KM AND gkm.ID_GOI = ?
                )
                AND km.TRANG_THAI = 'active'
                AND (km.NGAY_BAT_DAU IS NULL OR km.NGAY_BAT_DAU <= ?)
                AND (km.NGAY_KET_THUC IS NULL OR km.NGAY_KET_THUC >= ?)
                ORDER BY km.MUC_DO_GIAM DESC";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('iss', $packageId, $now, $now);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $promotions = [];
        while ($row = $result->fetch_assoc()) {
            $promotions[] = $row;
        }
        return $promotions;
    }

    /**
     * Calculate discount amount for a promotion
     */
    private function calculatePromotionDiscount(array $promo, int $baseTotal): int {
        if (empty($promo) || $baseTotal <= 0) {
            return 0;
        }
        
        $loaiGiam = $promo['LOAI_GIAM'] ?? '';
        $mucDoGiam = (int)($promo['MUC_DO_GIAM'] ?? 0);
        
        if ($loaiGiam === 'percent') {
            // Percentage discount: price × (percent/100)
            $discount = (int)($baseTotal * $mucDoGiam / 100);
        } elseif ($loaiGiam === 'fixed') {
            // Fixed discount: capped at base total
            $discount = min($mucDoGiam, $baseTotal);
        } else {
            return 0;
        }
        
        return max(0, $discount);
    }

    /**
     * Bulk replace all services of a package. Items format:
     * [ ['ID_DV'=>int,'SO_LUONG'=>int,'DON_GIA_AP_DUNG'=>int|null,'THU_TU'=>int], ...]
     */
    public function bulkReplaceServices(int $idGoi, array $items): bool {
        $this->conn->begin_transaction();
        try {
            $del = $this->conn->prepare("DELETE FROM goi_dich_vu_chi_tiet WHERE ID_GOI=?");
            $del->bind_param('i', $idGoi);
            if (!$del->execute()) throw new \RuntimeException('Delete old details failed');
            $ins = $this->conn->prepare("INSERT INTO goi_dich_vu_chi_tiet (ID_GOI, ID_DV, SO_LUONG, DON_GIA_AP_DUNG, THU_TU) VALUES (?,?,?,?,?)");
            foreach ($items as $it) {
                if (!isset($it['ID_DV'])) continue;
                $idDv = (int)$it['ID_DV'];
                $sl   = max(1,(int)($it['SO_LUONG'] ?? 1));
                $gia  = isset($it['DON_GIA_AP_DUNG']) && $it['DON_GIA_AP_DUNG']!=='' ? (int)$it['DON_GIA_AP_DUNG'] : null;
                $thuTu= max(1,(int)($it['THU_TU'] ?? 1));
                $ins->bind_param('iiiii', $idGoi, $idDv, $sl, $gia, $thuTu);
                if (!$ins->execute()) throw new \RuntimeException('Insert detail failed for service '.$idDv);
            }
            $this->conn->commit();
            return true;
        } catch (\Throwable $e) {
            $this->conn->rollback();
            return false;
        }
    }
}
