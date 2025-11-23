<?php
/**
 * Automated test for single service booking flow.
 * Run: php tests/test_service_quote_and_booking.php
 */

define('CLI_TEST_MODE', true);

$assertions = 0;
$failures = [];
function assertTrueService($cond, $msg) {
    global $assertions, $failures;
    $assertions++;
    if (!$cond) {
        $failures[] = $msg;
    }
}

require_once __DIR__ . '/../database/config.php';
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Locate a service with price entry
$serviceId = null;
$serviceName = null;
$sql = "SELECT dv.ID_DV, dv.TEN_DV FROM DICH_VU dv WHERE EXISTS (SELECT 1 FROM DON_GIA_DICH_VU dg WHERE dg.ID_DV = dv.ID_DV) LIMIT 1";
$rs = $conn->query($sql);
if ($rs && ($row = $rs->fetch_assoc())) {
    $serviceId = (int)$row['ID_DV'];
    $serviceName = $row['TEN_DV'];
}
if ($rs) { $rs->close(); }

assertTrueService($serviceId !== null, 'Không tìm được dịch vụ có đơn giá.');
if ($serviceId === null) goto report;

// Quote preview
$_POST = [
    'branch_id' => 1,
    'service_id' => $serviceId,
    'location_type' => 'branch',
];
ob_start();
include __DIR__ . '/../app/Pages/Controller/quote_preview.php';
$data = json_decode(ob_get_clean(), true);
assertTrueService(is_array($data) && ($data['mode'] ?? '') === 'service', 'Quote service trả về không hợp lệ.');
assertTrueService(isset($data['total']) && $data['total'] > 0, 'Quote service không có tổng tiền.');

// Prepare booking request
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$userId = 'TEST_SVC_' . bin2hex(random_bytes(4));
$_SESSION['ID_TK'] = $userId;

// Ensure tai_khoan row exists
$chk = $conn->prepare('SELECT ID_TK FROM tai_khoan WHERE ID_TK = ? LIMIT 1');
if ($chk) {
    $chk->bind_param('s', $userId);
    $chk->execute();
    $res = $chk->get_result();
    if (!$res || $res->num_rows === 0) {
        $ins = $conn->prepare('INSERT INTO tai_khoan (ID_TK, ID_QUYEN, HO_TEN, EMAIL, SDT, MAT_KHAU) VALUES (?, 1, "Test Service", "svc@example.com", "0900000001", "x")');
        if ($ins) { $ins->bind_param('s', $userId); $ins->execute(); $ins->close(); }
    }
    $chk->close();
}

$today = (new DateTime('now'))->format('Y-m-d');
$_POST = [
    'branch_id' => 1,
    'ngayHen' => $today,
    'gioHen' => '11:00',
    'address' => 'Chi nhánh 1',
    'location_type' => 'branch',
    'booking_type' => 'service',
    'service_id' => $serviceId,
];

$result = include __DIR__ . '/../app/Pages/Controller/process_schedule.php';
assertTrueService(is_array($result) && ($result['status'] ?? '') === 'success', 'process_schedule (service) không trả về thành công.');
$bookingId = $result['booking_id'] ?? null;
assertTrueService($bookingId !== null, 'Không nhận được booking_id từ service booking.');

if ($bookingId !== null) {
    $stmt = $conn->prepare('SELECT ID_DV, ID_GOI FROM lich_hen WHERE ID_LICHHEN = ?');
    if ($stmt) {
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        $stmt->bind_result($idDv, $idGoi);
        if ($stmt->fetch()) {
            assertTrueService((int)$idDv === $serviceId, 'Lich hẹn không lưu đúng ID_DV.');
            assertTrueService($idGoi === null, 'Lich hẹn dịch vụ không nên có ID_GOI.');
        }
        $stmt->close();
    }
}

report:
echo "\n===== TEST SERVICE BOOKING =====\n";
if ($failures) {
    foreach ($failures as $f) {
        echo "[FAIL] $f\n";
    }
    echo "Tổng lỗi: " . count($failures) . " / Assertions: $assertions\n";
    exit(1);
}

echo "Tất cả bài test dịch vụ OK. Assertions: $assertions\n";
exit(0);
