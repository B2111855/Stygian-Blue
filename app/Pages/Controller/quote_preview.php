<?php
// Quote preview for single service bookings
// Input: JSON { branch_id, date, time, service_id, device_ids: [] }
// Output: JSON { total: number, items?: array }

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', '1');

try {
    // Load DB config reliably
    require_once __DIR__ . '/../../../database/config.php';

    // Parse input (JSON preferred)
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = $_POST; // fallback
    }

    $serviceId    = isset($data['service_id']) ? (int)$data['service_id'] : 0;
    $branchId     = isset($data['branch_id']) ? (int)$data['branch_id'] : 0;
    $locationType = isset($data['location_type']) && $data['location_type'] === 'external' ? 'external' : 'branch';
    $extLat       = isset($data['ext_lat']) ? (float)$data['ext_lat'] : null;
    $extLng       = isset($data['ext_lng']) ? (float)$data['ext_lng'] : null;

    // Cho phép tính riêng phụ phí di chuyển nếu serviceId không có

    // Get latest unit price for service
    $sql = "SELECT DON_GIA FROM DON_GIA_DICH_VU WHERE ID_DV = ? ORDER BY NGAY_GIO DESC LIMIT 1";
    $servicePrice = 0;
    if ($serviceId > 0) {
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $serviceId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $servicePrice = $row && isset($row['DON_GIA']) ? (int)$row['DON_GIA'] : 0;
            $stmt->close();
        }
    }

    // For now, devices have no pricing table => sum to 0
    // Keep the structure to extend later if needed
    $deviceTotal = 0;

    // Travel fee calculation
    $travelFee  = 0;
    $distanceKm = null;

    $hasCoords = false;
    $hasValidExternalCoords = (
        $locationType === 'external' &&
        $branchId > 0 &&
        $extLat !== null &&
        $extLng !== null &&
        is_finite($extLat) &&
        is_finite($extLng)
    );

    if ($hasValidExternalCoords) {
        // Check if branch has LATITUDE/LONGITUDE columns
        $check = $conn->query("SHOW COLUMNS FROM CHI_NHANH LIKE 'LATITUDE'");
        $hasCoords = $check && $check->num_rows > 0;
        if ($check) { $check->free_result(); }

        if ($hasCoords) {
            $stmtB = $conn->prepare('SELECT LATITUDE, LONGITUDE FROM CHI_NHANH WHERE ID_CN = ? LIMIT 1');
            if ($stmtB) {
                $stmtB->bind_param('i', $branchId);
                if ($stmtB->execute()) {
                    $stmtB->bind_result($bLat, $bLng);
                    if ($stmtB->fetch() && $bLat !== null && $bLng !== null) {
                        $distanceKm = haversineKm((float)$bLat, (float)$bLng, (float)$extLat, (float)$extLng);
                        $travelFee  = calcTravelFee($distanceKm);
                    }
                }
                $stmtB->close();
            }
        }
    }

    $total = max(0, (int)$servicePrice + (int)$deviceTotal + (int)$travelFee);

    echo json_encode([
        'total' => $total,
        'items' => [
            [ 'label' => 'Dịch vụ', 'price' => (int)$servicePrice ],
            [ 'label' => 'Thiết bị', 'price' => (int)$deviceTotal ],
            [ 'label' => 'Phụ phí di chuyển', 'price' => (int)$travelFee ],
        ],
        'currency' => 'VND',
        'travel_fee' => (int)$travelFee,
        'distance_km' => $distanceKm !== null ? round($distanceKm, 2) : null,
        'ok' => true,
    ]);
} catch (Throwable $e) {
    echo json_encode([ 'total' => 0, 'error' => 'Lỗi hệ thống' ]);
}

// Helpers
function haversineKm($lat1, $lng1, $lat2, $lng2)
{
    $R = 6371; // km
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat/2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2) ** 2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $R * $c;
}

function calcTravelFee($distanceKm)
{
    if (!is_finite((float)$distanceKm)) return 0;
    if ($distanceKm <= 20) return 0;
    return (int)ceil($distanceKm - 20) * 5000;
}
