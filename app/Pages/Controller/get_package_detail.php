<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../../database/config.php';
if (file_exists(__DIR__ . '/../../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../../vendor/autoload.php';
}
require_once __DIR__ . '/../../Repositories/PackageRepository.php';

$packageId = isset($_GET['package_id']) ? (int)$_GET['package_id'] : 0;
$branchId  = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : null;

if ($packageId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Thiếu package_id']);
    exit;
}

try {
    // Fetch package basic info
    $pkgSql = "SELECT ID_GOI, TEN_GOI, MO_TA, HINH_ANH, TRANG_THAI FROM goi_dich_vu WHERE ID_GOI=? LIMIT 1";
    $pkgStmt = $conn->prepare($pkgSql);
    if (!$pkgStmt) {
        throw new RuntimeException('Lỗi chuẩn bị package query: ' . $conn->error);
    }
    $pkgStmt->bind_param('i', $packageId);
    $pkgStmt->execute();
    $pkgRes = $pkgStmt->get_result();
    $packageRow = $pkgRes ? $pkgRes->fetch_assoc() : null;
    $pkgStmt->close();

    if (!$packageRow) {
        echo json_encode(['ok' => false, 'error' => 'Gói không tồn tại']);
        exit;
    }

    // Pricing + promo snapshot: align with goi_chitiet.php by using shared repository pricing
    $pkgRepo = new \App\Repositories\PackageRepository($conn);
    $basePrice = 0;
    $finalPrice = 0;
    $promo = null;

    $pricing = $pkgRepo->getPackagePromotionPricing($packageId, 0.0);
    $basePrice = isset($pricing['base_total']) ? (int)$pricing['base_total'] : 0;
    $finalPrice = isset($pricing['subtotal']) ? (int)$pricing['subtotal'] : $basePrice;

    if (!empty($pricing['promotion'])) {
        $rawPromo = $pricing['promotion'];
        $promo = [
            'name' => $rawPromo['TEN_CHUONG_TRINH'] ?? $rawPromo['TEN_KM'] ?? null,
            'type' => $rawPromo['LOAI_GIAM'] ?? $rawPromo['KIEU_KM'] ?? null,
            'value' => isset($rawPromo['GIA_TRI_GIAM']) ? (float)$rawPromo['GIA_TRI_GIAM'] : (isset($rawPromo['GIA_TRI']) ? (float)$rawPromo['GIA_TRI'] : null),
            'max_discount' => isset($rawPromo['GIAM_TOI_DA']) ? (int)$rawPromo['GIAM_TOI_DA'] : null,
            'valid_from' => $rawPromo['TU_NGAY'] ?? $rawPromo['NGAY_BAT_DAU'] ?? null,
            'valid_to' => $rawPromo['DEN_NGAY'] ?? $rawPromo['NGAY_KET_THUC'] ?? null,
            'source' => $pricing['promotion_source'] ?? null,
            'price_after' => $finalPrice,
        ];
        $promo['computed_discount'] = (int)($pricing['discount'] ?? max(0, $basePrice - $finalPrice));
        if (!isset($promo['amount_off']) && $promo['computed_discount'] > 0) {
            $promo['amount_off'] = $promo['computed_discount'];
        }
    }

    // Fallback to view data only if repo lacks price or promo
    if ($basePrice <= 0 || !$promo) {
        $pricingStmt = $conn->prepare(
            "SELECT 
                vt.TONG_GIA_GOI AS BASE_PRICE_VIEW,
                km.GIA_SAU_GIAM,
                km.SO_TIEN_GIAM,
                km.TEN_CHUONG_TRINH,
                km.LOAI_GIAM,
                km.GIA_TRI_GIAM,
                km.GIAM_TOI_DA,
                km.TU_NGAY,
                km.DEN_NGAY
             FROM goi_dich_vu g
             LEFT JOIN v_goi_dich_vu_tong_tien vt ON vt.ID_GOI = g.ID_GOI
             LEFT JOIN v_goi_dich_vu_gia_khuyen_mai km ON km.ID_GOI = g.ID_GOI
             WHERE g.ID_GOI = ?
             LIMIT 1"
        );
        if ($pricingStmt) {
            $pricingStmt->bind_param('i', $packageId);
            $pricingStmt->execute();
            $pricingRes = $pricingStmt->get_result();
            if ($pricingRes && ($row = $pricingRes->fetch_assoc())) {
                $baseFromView = isset($row['BASE_PRICE_VIEW']) ? (int)$row['BASE_PRICE_VIEW'] : 0;
                if ($basePrice <= 0 && $baseFromView > 0) {
                    $basePrice = $baseFromView;
                }
                if (!$promo && (isset($row['GIA_SAU_GIAM']) || isset($row['SO_TIEN_GIAM']))) {
                    $promo = [
                        'name' => $row['TEN_CHUONG_TRINH'] ?? null,
                        'amount_off' => isset($row['SO_TIEN_GIAM']) ? (int)$row['SO_TIEN_GIAM'] : null,
                        'price_after' => isset($row['GIA_SAU_GIAM']) ? (int)$row['GIA_SAU_GIAM'] : null,
                        'type' => $row['LOAI_GIAM'] ?? null,
                        'value' => isset($row['GIA_TRI_GIAM']) ? (float)$row['GIA_TRI_GIAM'] : null,
                        'max_discount' => isset($row['GIAM_TOI_DA']) ? (int)$row['GIAM_TOI_DA'] : null,
                        'valid_from' => $row['TU_NGAY'] ?? null,
                        'valid_to' => $row['DEN_NGAY'] ?? null,
                    ];
                    if ($finalPrice <= 0 && isset($promo['price_after'])) {
                        $finalPrice = (int)$promo['price_after'];
                    }
                }
            }
            $pricingStmt->close();
        }
    }

    // Fallback base price from detail rows if view missing
    if ($basePrice <= 0) {
        $priceSql = "SELECT COALESCE(SUM(COALESCE(gct.DON_GIA_AP_DUNG,0) * COALESCE(gct.SO_LUONG,1)),0) AS BASE_PRICE
                     FROM goi_dich_vu_chi_tiet gct WHERE gct.ID_GOI=?";
        $priceStmt = $conn->prepare($priceSql);
        $priceStmt->bind_param('i', $packageId);
        $priceStmt->execute();
        $priceRow = $priceStmt->get_result()->fetch_assoc();
        $basePrice = (int)($priceRow['BASE_PRICE'] ?? 0);
        $priceStmt->close();
    }

    // Fallback: table goi_dich_vu_khuyen_mai (active & in-range) if promo still null
    if (!$promo) {
        $tablePromo = $conn->query("SHOW TABLES LIKE 'goi_dich_vu_khuyen_mai'");
        if ($tablePromo && $tablePromo->num_rows > 0) {
            $now = date('Y-m-d H:i:s');
            $stmtPromo = $conn->prepare("SELECT TEN_CHUONG_TRINH, LOAI_GIAM, GIA_TRI_GIAM, GIAM_TOI_DA, TU_NGAY, DEN_NGAY, ACTIVE FROM goi_dich_vu_khuyen_mai WHERE ID_GOI=? AND ACTIVE=1 AND (TU_NGAY IS NULL OR TU_NGAY<=?) AND (DEN_NGAY IS NULL OR DEN_NGAY>=?) ORDER BY DEN_NGAY ASC LIMIT 1");
            $stmtPromo->bind_param('iss', $packageId, $now, $now);
            $stmtPromo->execute();
            $pr = $stmtPromo->get_result();
            if ($pr && ($row = $pr->fetch_assoc())) {
                $promo = [
                    'name' => $row['TEN_CHUONG_TRINH'] ?? null,
                    'type' => $row['LOAI_GIAM'] ?? null,
                    'value' => isset($row['GIA_TRI_GIAM']) ? (float)$row['GIA_TRI_GIAM'] : null,
                    'max_discount' => isset($row['GIAM_TOI_DA']) ? (int)$row['GIAM_TOI_DA'] : null,
                    'valid_from' => $row['TU_NGAY'] ?? null,
                    'valid_to' => $row['DEN_NGAY'] ?? null,
                ];
            }
            $stmtPromo->close();
        }
    }

    // Calculate total duration from services in package
    $durationMin = 0;
    $durationStmt = $conn->prepare(
        "SELECT SUM(d.THOI_GIAN) as TOTAL_TIME 
         FROM goi_dich_vu_chi_tiet gdt 
         JOIN dich_vu d ON d.ID_DV = gdt.ID_DV 
         WHERE gdt.ID_GOI = ?"
    );
    if ($durationStmt) {
        $durationStmt->bind_param('i', $packageId);
        $durationStmt->execute();
        $durationRes = $durationStmt->get_result();
        if ($durationRes && ($durationRow = $durationRes->fetch_assoc())) {
            $durationMin = (int)($durationRow['TOTAL_TIME'] ?? 0);
        }
        $durationStmt->close();
    }

    // Fallback: mapping table goi_khuyen_mai -> khuyen_mai (percent/fixed)
    if (!$promo) {
        $mapExists = $conn->query("SHOW TABLES LIKE 'goi_khuyen_mai'");
        $kmExists = $conn->query("SHOW TABLES LIKE 'khuyen_mai'");
        if ($mapExists && $mapExists->num_rows > 0 && $kmExists && $kmExists->num_rows > 0) {
            $now = date('Y-m-d H:i:s');
            $sql = "SELECT km.ID_KHUYEN_MAI, km.TEN_KM, km.KIEU_KM, km.GIA_TRI, km.NGAY_BAT_DAU, km.NGAY_KET_THUC
                    FROM goi_khuyen_mai gkm
                    JOIN khuyen_mai km ON km.ID_KHUYEN_MAI = gkm.ID_PROMO
                    WHERE gkm.ID_GOI=? AND km.TRANG_THAI='active' AND km.NGAY_BAT_DAU<=? AND km.NGAY_KET_THUC>=?
                    ORDER BY gkm.THU_TU ASC, km.NGAY_KET_THUC ASC
                    LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('iss', $packageId, $now, $now);
            $stmt->execute();
            $rs = $stmt->get_result();
            if ($rs && ($row = $rs->fetch_assoc())) {
                $promo = [
                    'name' => $row['TEN_KM'] ?? null,
                    'type' => ($row['KIEU_KM'] === 'fixed') ? 'amount' : 'percent',
                    'value' => isset($row['GIA_TRI']) ? (float)$row['GIA_TRI'] : null,
                    'max_discount' => null,
                    'valid_from' => $row['NGAY_BAT_DAU'] ?? null,
                    'valid_to' => $row['NGAY_KET_THUC'] ?? null,
                ];
            }
            $stmt->close();
        }
    }

    // Compute final price (supports: view, phan_tram, so_tien)
    $finalPrice = $basePrice;
    if ($promo) {
        $discount = 0;
        $type = $promo['type'] ?? '';

        // If the view already provides price_after, trust it and derive discount from it
        if (isset($promo['price_after']) && $promo['price_after'] !== null) {
            $finalPrice = (int)$promo['price_after'];
            $discount = max(0, $basePrice - $finalPrice);
        } else {
            if ($type === 'percent' || $type === 'phan_tram') {
                if (isset($promo['value'])) {
                    $discount = (int)round($basePrice * ((float)$promo['value'] / 100));
                }
            } elseif ($type === 'amount' || $type === 'so_tien') {
                if (isset($promo['value'])) {
                    $discount = (int)$promo['value'];
                }
            } elseif (isset($promo['amount_off'])) {
                $discount = (int)$promo['amount_off'];
            }
            if (isset($promo['max_discount']) && $promo['max_discount'] !== null) {
                $discount = min($discount, (int)$promo['max_discount']);
            }
            $finalPrice = max(0, $basePrice - $discount);
            $promo['price_after'] = $finalPrice;
        }

        $promo['computed_discount'] = $discount;
    }

    // Services inside package
    $services = [];
    $svcSql = "SELECT gct.ID_DV, gct.SO_LUONG, gct.DON_GIA_AP_DUNG, gct.THU_TU,
                      dv.TEN_DV, dv.MOTA_DV, dv.THOI_GIAN
               FROM goi_dich_vu_chi_tiet gct
               JOIN dich_vu dv ON dv.ID_DV = gct.ID_DV
               WHERE gct.ID_GOI=?
               ORDER BY gct.THU_TU ASC, dv.TEN_DV ASC";
    $svcStmt = $conn->prepare($svcSql);
    $svcStmt->bind_param('i', $packageId);
    $svcStmt->execute();
    $svcRes = $svcStmt->get_result();
    while ($row = $svcRes->fetch_assoc()) {
        $services[] = [
            'id' => (int)$row['ID_DV'],
            'name' => $row['TEN_DV'],
            'description' => $row['MOTA_DV'],
            'duration' => isset($row['THOI_GIAN']) ? (int)$row['THOI_GIAN'] : null,
            'quantity' => (int)($row['SO_LUONG'] ?? 1),
            'unit_price' => (int)($row['DON_GIA_AP_DUNG'] ?? 0),
            'order' => (int)($row['THU_TU'] ?? 0),
        ];
    }
    $svcStmt->close();

    // Costume slot requirements (optional)
    $costumeSlots = [];
    $slotTableExists = $conn->query("SHOW TABLES LIKE 'goi_dich_vu_trang_phuc_slot'");
    if ($slotTableExists && $slotTableExists->num_rows > 0) {
        $slotSql = "SELECT ID_SLOT, TEN_SLOT, NHOM, LOAI, SIZE, SO_LUONG, BAT_BUOC, ACTIVE
                    FROM goi_dich_vu_trang_phuc_slot
                    WHERE ID_GOI=? AND ACTIVE=1
                    ORDER BY ID_SLOT ASC";
        $slotStmt = $conn->prepare($slotSql);
        $slotStmt->bind_param('i', $packageId);
        $slotStmt->execute();
        $slotRes = $slotStmt->get_result();
        while ($row = $slotRes->fetch_assoc()) {
            $costumeSlots[] = [
                'slot_id' => (int)$row['ID_SLOT'],
                'name' => $row['TEN_SLOT'],
                'group' => $row['NHOM'],
                'type' => $row['LOAI'],
                'size' => $row['SIZE'],
                'quantity' => (int)$row['SO_LUONG'],
                'required' => (int)$row['BAT_BUOC'] === 1,
            ];
        }
        $slotStmt->close();
    }

    // Fallback costume requirements from GOI_TRANG_PHUC (legacy mapping) if slot table missing or empty
    if (empty($costumeSlots)) {
        $mapTableExists = $conn->query("SHOW TABLES LIKE 'goi_trang_phuc'");
        if ($mapTableExists && $mapTableExists->num_rows > 0) {
            $legacySql = "SELECT gtp.ID_TRANG_PHUC, gtp.DISCOUNT_PERCENT, gtp.BAT_BUOC, gtp.THU_TU, tp.TEN, tp.SIZE
                           FROM goi_trang_phuc gtp
                           JOIN trang_phuc tp ON tp.ID_TRANG_PHUC = gtp.ID_TRANG_PHUC
                           WHERE gtp.ID_GOI=?
                           ORDER BY gtp.THU_TU ASC, gtp.ID_TRANG_PHUC ASC";
            $legacyStmt = $conn->prepare($legacySql);
            $legacyStmt->bind_param('i', $packageId);
            $legacyStmt->execute();
            $legacyRes = $legacyStmt->get_result();
            while ($row = $legacyRes->fetch_assoc()) {
                $costumeSlots[] = [
                    'slot_id' => (int)$row['ID_TRANG_PHUC'],
                    'name' => $row['TEN'],
                    'group' => null,
                    'type' => null,
                    'size' => $row['SIZE'] ?? null,
                    'quantity' => 1,
                    'required' => (int)($row['BAT_BUOC'] ?? 0) === 1,
                ];
            }
            $legacyStmt->close();
        }
    }

    // Fallback costume requirements from goi_yeu_cau pivot (preferred) or legacy goi_trang_phuc_yeu_cau (JSON list of costume IDs)
    if (empty($costumeSlots)) {
        $ycRows = [];
        $idPool = [];

        $pivotExists = $conn->query("SHOW TABLES LIKE 'goi_yeu_cau'");
        $ycExists   = $conn->query("SHOW TABLES LIKE 'goi_trang_phuc_yeu_cau'");

        if ($pivotExists && $pivotExists->num_rows > 0 && $ycExists && $ycExists->num_rows > 0) {
            // Prefer pivot: share YC across many packages
            $ycSql = "SELECT g.ID_YC, yc.ID_NHOM, yc.LOAI_ID, yc.LOAI_IDS_JSON, yc.SO_LUONG, yc.BAT_BUOC
                       FROM goi_yeu_cau g
                       JOIN goi_trang_phuc_yeu_cau yc ON yc.ID_YC = g.ID_YC
                       WHERE g.ID_GOI=?
                       ORDER BY g.THU_TU ASC, g.ID_YC ASC";
            $ycStmt = $conn->prepare($ycSql);
            $ycStmt->bind_param('i', $packageId);
            $ycStmt->execute();
            $ycRes = $ycStmt->get_result();
            while ($row = $ycRes->fetch_assoc()) {
                $ids = [];
                if (!empty($row['LOAI_IDS_JSON'])) {
                    $decoded = json_decode($row['LOAI_IDS_JSON'], true);
                    if (is_array($decoded)) {
                        $ids = array_filter(array_map('intval', $decoded));
                    }
                }
                $row['parsed_ids'] = $ids;
                $ycRows[] = $row;
                $idPool = array_merge($idPool, $ids);
            }
            $ycStmt->close();
        } elseif ($ycExists && $ycExists->num_rows > 0) {
            // Legacy: YC gắn trực tiếp vào gói qua cột ID_GOI
            $ycSql = "SELECT ID_YC, ID_NHOM, LOAI_ID, LOAI_IDS_JSON, SO_LUONG, BAT_BUOC FROM goi_trang_phuc_yeu_cau WHERE ID_GOI=?";
            $ycStmt = $conn->prepare($ycSql);
            $ycStmt->bind_param('i', $packageId);
            $ycStmt->execute();
            $ycRes = $ycStmt->get_result();
            while ($row = $ycRes->fetch_assoc()) {
                $ids = [];
                if (!empty($row['LOAI_IDS_JSON'])) {
                    $decoded = json_decode($row['LOAI_IDS_JSON'], true);
                    if (is_array($decoded)) {
                        $ids = array_filter(array_map('intval', $decoded));
                    }
                }
                $row['parsed_ids'] = $ids;
                $ycRows[] = $row;
                $idPool = array_merge($idPool, $ids);
            }
            $ycStmt->close();
        }

        if (!empty($idPool)) {
            $idPool = array_values(array_unique($idPool));
            $place = implode(',', array_fill(0, count($idPool), '?'));
            $types = str_repeat('i', count($idPool));
            $tpSql = "SELECT ID_TRANG_PHUC, TEN, SIZE, TRANG_THAI FROM trang_phuc WHERE ID_TRANG_PHUC IN ($place)";
            $tpStmt = $conn->prepare($tpSql);
            $tpStmt->bind_param($types, ...$idPool);
            $tpStmt->execute();
            $tpRes = $tpStmt->get_result();
            $tpMap = [];
            while ($r = $tpRes->fetch_assoc()) {
                $tpMap[(int)$r['ID_TRANG_PHUC']] = $r;
            }
            $tpStmt->close();

            foreach ($ycRows as $row) {
                foreach ($row['parsed_ids'] as $cid) {
                    $info = $tpMap[$cid] ?? null;
                    $costumeSlots[] = [
                        'slot_id' => $cid,
                        'name' => $info['TEN'] ?? ('Trang phục #' . $cid),
                        'group' => $row['ID_NHOM'] ?? null,
                        'type' => $row['LOAI_ID'] ?? null,
                        'size' => $info['SIZE'] ?? null,
                        'quantity' => (int)($row['SO_LUONG'] ?? 1),
                        'required' => (int)($row['BAT_BUOC'] ?? 0) === 1,
                        'status' => $info['TRANG_THAI'] ?? null,
                    ];
                }
            }
        }
    }

    // Costume mappings for branch (optional)
    $costumeMappings = [];
    if (!empty($costumeSlots) && $branchId) {
        $mapTableExists = $conn->query("SHOW TABLES LIKE 'goi_dich_vu_slot_branch_trang_phuc'");
        if ($mapTableExists && $mapTableExists->num_rows > 0) {
            $slotIds = array_column($costumeSlots, 'slot_id');
            $placeholders = implode(',', array_fill(0, count($slotIds), '?'));
            $types = str_repeat('i', count($slotIds) + 1); // +1 for branch id
            $sql = "SELECT m.ID_SLOT, m.ID_TRANG_PHUC, m.PRICE_ADJUSTMENT, m.ACTIVE, tp.TEN, tp.SIZE, tp.TRANG_THAI
                    FROM goi_dich_vu_slot_branch_trang_phuc m
                    LEFT JOIN trang_phuc tp ON tp.ID_TRANG_PHUC = m.ID_TRANG_PHUC
                    WHERE m.ID_CN=? AND m.ID_SLOT IN ($placeholders)";
            $stmt = $conn->prepare($sql);
            $params = array_merge([$branchId], $slotIds);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $slotId = (int)$row['ID_SLOT'];
                $costumeMappings[$slotId][] = [
                    'costume_id' => (int)$row['ID_TRANG_PHUC'],
                    'name' => $row['TEN'],
                    'size' => $row['SIZE'] ?? null,
                    'status' => $row['TRANG_THAI'] ?? null,
                    'price_adjustment' => isset($row['PRICE_ADJUSTMENT']) ? (int)$row['PRICE_ADJUSTMENT'] : 0,
                    'active' => (int)$row['ACTIVE'] === 1,
                ];
            }
            $stmt->close();
        }
    }

    echo json_encode([
        'ok' => true,
        'package' => [
            'id' => (int)$packageRow['ID_GOI'],
            'name' => $packageRow['TEN_GOI'],
            'description' => $packageRow['MO_TA'],
            'image' => $packageRow['HINH_ANH'] ?? null,
            'status' => $packageRow['TRANG_THAI'] ?? null,
            'base_price' => $basePrice,
            'final_price' => $finalPrice,
            'duration_min' => $durationMin,
        ],
        'promotion' => $promo,
        'services' => $services,
        'costume_slots' => $costumeSlots,
        'costume_mappings' => $costumeMappings,
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'error' => 'Lỗi hệ thống',
        'message' => $e->getMessage(),
    ]);
}
