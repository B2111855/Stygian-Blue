<?php
// Quote preview mở rộng: hỗ trợ service hoặc package + trang phục tuỳ chọn.
// Input JSON (service): { branch_id, service_id, location_type?, ext_lat?, ext_lng? }
// Input JSON (package): { branch_id, package_id, costume_ids:[], location_type?, ext_lat?, ext_lng? }
// Output: { total, items[], travel_fee, distance_km, ... }

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', '1');

try {
    require_once __DIR__ . '/../../../database/config.php';
    // PSR-4 autoload cho QuoteService / CostumeRepository
    if (file_exists(__DIR__ . '/../../../vendor/autoload.php')) {
        require_once __DIR__ . '/../../../vendor/autoload.php';
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) { $data = $_POST; }

    $branchId     = isset($data['branch_id']) ? (int)$data['branch_id'] : 0;
    $serviceId    = isset($data['service_id']) ? (int)$data['service_id'] : 0;
    $serviceIds   = [];
    if (isset($data['service_ids']) && is_array($data['service_ids'])) {
        foreach ($data['service_ids'] as $sid) {
            if (ctype_digit((string)$sid)) { $serviceIds[] = (int)$sid; }
        }
        $serviceIds = array_values(array_unique($serviceIds));
        if (!$serviceId && !empty($serviceIds)) {
            $serviceId = $serviceIds[0];
        }
    }
    $packageId    = isset($data['package_id']) ? (int)$data['package_id'] : 0;
    $bookingType  = isset($data['booking_type']) ? (string)$data['booking_type'] : 'service';
    $bookingType  = in_array($bookingType, ['service','package','costume'], true) ? $bookingType : 'service';
    $locationType = (isset($data['location_type']) && $data['location_type'] === 'external') ? 'external' : 'branch';
    $extLat       = isset($data['ext_lat']) ? (float)$data['ext_lat'] : null;
    $extLng       = isset($data['ext_lng']) ? (float)$data['ext_lng'] : null;
    $costumeIds   = [];
    if (isset($data['costume_ids']) && is_array($data['costume_ids'])) {
        foreach ($data['costume_ids'] as $cid) {
            if (ctype_digit((string)$cid)) { $costumeIds[] = (int)$cid; }
        }
    }

    // Feature flag (ENV) cho package + costume nếu cần rollback nhanh
    $enablePackageCostume = getenv('ENABLE_PACKAGE_COSTUME');
    $enablePackageCostume = $enablePackageCostume === false ? '1' : $enablePackageCostume; // default bật
    $costumeFeaturesEnabled = $enablePackageCostume !== '0';
    $usePackageFlow = $costumeFeaturesEnabled && $packageId > 0;
    $useCostumeOnlyFlow = $costumeFeaturesEnabled && $bookingType === 'costume';
    if ($bookingType === 'costume' && !$costumeFeaturesEnabled) {
        throw new RuntimeException('Tính năng trang phục đang tạm tắt.');
    }

    $resultPayload = [];
    if ($useCostumeOnlyFlow) {
        if (!class_exists('App\Services\QuoteService')) {
            throw new RuntimeException('Thiếu lớp QuoteService (autoload).');
        }
        $qs = new App\Services\QuoteService($conn);
        $resultPayload = $qs->buildCostumeOnlyQuote($costumeIds, $branchId, $locationType, $extLat, $extLng);
        $resultPayload['mode'] = 'costume';
        $resultPayload['booking_type'] = 'costume';
        $resultPayload['selected_costume_ids'] = $costumeIds;
    } elseif ($usePackageFlow) {
        if (!class_exists('App\\Services\\QuoteService')) {
            throw new RuntimeException('Thiếu lớp QuoteService (autoload).');
        }
        $qs = new App\Services\QuoteService($conn);
        $resultPayload = $qs->buildPackageQuote($packageId, $costumeIds, $branchId, $locationType, $extLat, $extLng);
        $resultPayload['mode'] = 'package';
        $resultPayload['package_id'] = $packageId;
        $resultPayload['booking_type'] = 'package';
        $resultPayload['selected_costume_ids'] = $costumeIds;
    } else {
        // Dịch vụ lẻ: hỗ trợ nhiều dịch vụ (service_ids) hoặc một (service_id)
        if ($bookingType === 'service' && !empty($serviceIds)) {
            // Inline logic: lấy đơn giá mới nhất cho từng dịch vụ, cộng lại + phụ phí di chuyển
            $items = [];
            $sumServices = 0;
            $stmt = $conn->prepare("SELECT TEN_DV FROM DICH_VU WHERE ID_DV = ? LIMIT 1");
            $stmtPrice = $conn->prepare("SELECT DON_GIA FROM DON_GIA_DICH_VU WHERE ID_DV = ? ORDER BY NGAY_GIO DESC LIMIT 1");
            foreach ($serviceIds as $sid) {
                $name = 'Dịch vụ';
                if ($stmt) {
                    $stmt->bind_param('i', $sid);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $row = $res ? $res->fetch_assoc() : null;
                    if ($row && isset($row['TEN_DV'])) { $name = $row['TEN_DV']; }
                }
                $price = 0;
                if ($stmtPrice) {
                    $stmtPrice->bind_param('i', $sid);
                    $stmtPrice->execute();
                    $resP = $stmtPrice->get_result();
                    $rowP = $resP ? $resP->fetch_assoc() : null;
                    if ($rowP && isset($rowP['DON_GIA'])) { $price = (int)$rowP['DON_GIA']; }
                }
                $sumServices += $price;
                $items[] = ['label' => $name, 'price' => $price, 'service_id' => $sid];
            }

            $travel = computeTravelFeeInline($conn, $branchId, $locationType, $extLat, $extLng);
            $total = $sumServices + $travel['fee'];
            $items[] = ['label' => 'Phụ phí di chuyển', 'price' => $travel['fee']];

            $resultPayload = [
                'ok' => true,
                'total' => $total,
                'items' => $items,
                'travel_fee' => $travel['fee'],
                'distance_km' => $travel['distance_km'],
                'mode' => 'service_multi',
                'booking_type' => 'service',
                'service_ids' => $serviceIds,
                'service_id' => $serviceId,
                'currency' => 'VND'
            ];
        } else {
        // Fallback service logic cũ (giữ nguyên hành vi nếu không có package)
        if (!class_exists('App\\Services\\QuoteService')) {
            // Logic inline cũ để không phụ thuộc lớp khi autoload chưa có
            $servicePrice = 0;
            if ($serviceId > 0) {
                $stmt = $conn->prepare("SELECT DON_GIA FROM DON_GIA_DICH_VU WHERE ID_DV = ? ORDER BY NGAY_GIO DESC LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('i', $serviceId);
                    $stmt->execute();
                    $r = $stmt->get_result();
                    $rw = $r ? $r->fetch_assoc() : null;
                    $servicePrice = $rw && isset($rw['DON_GIA']) ? (int)$rw['DON_GIA'] : 0;
                    $stmt->close();
                }
            }
            $travel = computeTravelFeeInline($conn, $branchId, $locationType, $extLat, $extLng);
            $total = $servicePrice + $travel['fee'];
            $resultPayload = [
                'total' => $total,
                'items' => [
                    ['label' => 'Dịch vụ', 'price' => $servicePrice],
                    ['label' => 'Phụ phí di chuyển', 'price' => $travel['fee']],
                ],
                'travel_fee' => $travel['fee'],
                'distance_km' => $travel['distance_km'],
                'mode' => 'service',
                'service_id' => $serviceId,
                'booking_type' => $bookingType,
            ];
        } else {
            $qs = new App\Services\QuoteService($conn);
            $serviceQuote = $qs->buildServiceQuote($serviceId, $branchId, $locationType, $extLat, $extLng);
            $serviceQuote['mode'] = 'service';
            $serviceQuote['service_id'] = $serviceId;
            $serviceQuote['booking_type'] = $bookingType;
            $resultPayload = $serviceQuote;
        }
        }
    }

    $resultPayload['ok'] = true;
    $resultPayload['currency'] = 'VND';
    echo json_encode($resultPayload);
} catch (Throwable $e) {
    echo json_encode([
        'total' => 0,
        'error' => 'Lỗi hệ thống',
        'message' => $e->getMessage(),
        'ok' => false
    ]);
}

// Inline travel fee fallback (giữ tương thích nếu QuoteService không nạp được)
function computeTravelFeeInline($conn, $branchId, $locationType, $extLat, $extLng): array
{
    $distanceKm = null; $fee = 0;
    if ($locationType === 'external' && $branchId > 0 && is_finite((float)$extLat) && is_finite((float)$extLng)) {
        $check = $conn->query("SHOW COLUMNS FROM CHI_NHANH LIKE 'LATITUDE'");
        $hasCoords = $check && $check->num_rows > 0; if ($check) { $check->free_result(); }
        if ($hasCoords) {
            $stmt = $conn->prepare('SELECT LATITUDE, LONGITUDE FROM CHI_NHANH WHERE ID_CN = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i', $branchId);
                $stmt->execute();
                $bLat = null; $bLng = null;
                $stmt->bind_result($bLat, $bLng);
                if ($stmt->fetch() && $bLat !== null && $bLng !== null) {
                    $distanceKm = haversineKmInline((float)$bLat, (float)$bLng, (float)$extLat, (float)$extLng);
                    $fee = calcTravelFeeInline($distanceKm);
                }
                $stmt->close();
            }
        }
    }
    return [ 'fee' => $fee, 'distance_km' => $distanceKm !== null ? round($distanceKm, 2) : null ];
}

function haversineKmInline($lat1, $lng1, $lat2, $lng2)
{
    $R = 6371; $dLat = deg2rad($lat2 - $lat1); $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat/2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2) ** 2; $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $R * $c;
}

function calcTravelFeeInline($distanceKm)
{
    if (!is_finite((float)$distanceKm)) return 0; if ($distanceKm <= 20) return 0; return (int)ceil($distanceKm - 20) * 5000;
}
