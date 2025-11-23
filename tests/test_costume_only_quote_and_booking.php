<?php
/**
 * Automated test for costume-only booking flow.
 * Run: php tests/test_costume_only_quote_and_booking.php
 */

putenv('ENABLE_PACKAGE_COSTUME=1');
define('CLI_TEST_MODE', true);

$assertions = 0;
$failures = [];

function assertCostume($cond, $msg) {
    global $assertions, $failures;
    $assertions++;
    if (!$cond) {
        $failures[] = $msg;
    }
}

function jsonDecodeCostume($raw) {
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

require_once __DIR__ . '/../database/config.php';
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

$costumeId = null;
$costumePrice = null;
$costumeQuery = $conn->query("SELECT ID_TRANG_PHUC, GIA_THUE FROM TRANG_PHUC WHERE TRANG_THAI = 'available' AND GIA_THUE > 0 LIMIT 1");
if ($costumeQuery === false) {
    assertCostume(false, 'Không thể truy vấn TRANG_PHUC: ' . $conn->error);
    goto report;
} elseif ($row = $costumeQuery->fetch_assoc()) {
    $costumeId = (int)$row['ID_TRANG_PHUC'];
    $costumePrice = (int)$row['GIA_THUE'];
    $costumeQuery->close();
} elseif ($costumeQuery) {
    $costumeQuery->close();
}

if ($costumeId === null) {
    $seedStmt = $conn->prepare("INSERT INTO TRANG_PHUC (TEN, GIA_THUE, TRANG_THAI) VALUES ('Trang phục tự động', 500000, 'available')");
    if ($seedStmt) {
        if ($seedStmt->execute()) {
            $costumeId = (int)$seedStmt->insert_id;
            $costumePrice = 500000;
        }
        $seedStmt->close();
    }
}

assertCostume($costumeId !== null, 'Không tìm được trang phục khả dụng để test.');
if ($costumeId === null) {
    goto report;
}

$_POST = [
    'branch_id' => 1,
    'booking_type' => 'costume',
    'costume_ids' => [$costumeId],
    'location_type' => 'branch',
];
ob_start();
include __DIR__ . '/../app/Pages/Controller/quote_preview.php';
$quoteRaw = ob_get_clean();
$quote = jsonDecodeCostume($quoteRaw);
assertCostume(is_array($quote) && ($quote['mode'] ?? '') === 'costume', 'Quote costume không hợp lệ.');
assertCostume(isset($quote['total']) && $quote['total'] >= $costumePrice, 'Quote costume không chứa tổng tiền hợp lệ.');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$userId = 'TEST_COSTUME_' . bin2hex(random_bytes(4));
$_SESSION['ID_TK'] = $userId;

$chkUser = $conn->prepare('SELECT ID_TK FROM tai_khoan WHERE ID_TK = ? LIMIT 1');
if ($chkUser) {
    $chkUser->bind_param('s', $userId);
    $chkUser->execute();
    $res = $chkUser->get_result();
    if (!$res || $res->num_rows === 0) {
        $ins = $conn->prepare('INSERT INTO tai_khoan (ID_TK, ID_QUYEN, HO_TEN, EMAIL, SDT, MAT_KHAU) VALUES (?, 1, "Test Costume", "costume@example.com", "0900000002", "x")');
        if ($ins) {
            $ins->bind_param('s', $userId);
            $ins->execute();
            $ins->close();
        }
    }
    $chkUser->close();
}

$today = (new DateTime('now'))->format('Y-m-d');
$_POST = [
    'branch_id' => 1,
    'ngayHen' => $today,
    'gioHen' => '16:00',
    'address' => 'Chi nhánh 1',
    'location_type' => 'branch',
    'booking_type' => 'costume',
    'costume_ids' => [$costumeId],
];

$result = include __DIR__ . '/../app/Pages/Controller/process_schedule.php';
assertCostume(is_array($result) && ($result['status'] ?? '') === 'success', 'process_schedule (costume) không thành công.');
$bookingId = $result['booking_id'] ?? null;
assertCostume($bookingId !== null, 'Không nhận được booking_id cho costume.');

if ($bookingId !== null) {
    $stmt = $conn->prepare('SELECT ID_DV, ID_GOI FROM lich_hen WHERE ID_LICHHEN = ?');
    if ($stmt) {
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        $stmt->bind_result($idDv, $idGoi);
        if ($stmt->fetch()) {
            assertCostume($idDv === null, 'Lich hẹn costume không nên có ID_DV.');
            assertCostume($idGoi === null, 'Lich hẹn costume không nên có ID_GOI.');
        }
        $stmt->close();
    }

    $itemStmt = $conn->prepare("SELECT ITEM_TYPE, REF_ID, DON_GIA FROM BOOKING_ITEM WHERE ID_LICHHEN = ?");
    $items = [];
    if ($itemStmt) {
        $itemStmt->bind_param('i', $bookingId);
        $itemStmt->execute();
        $res = $itemStmt->get_result();
        $items = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $itemStmt->close();
    }
    $hasCostumeLine = false;
    foreach ($items as $item) {
        if ($item['ITEM_TYPE'] === 'costume' && (int)$item['REF_ID'] === $costumeId) {
            $hasCostumeLine = true;
            assertCostume((int)$item['DON_GIA'] === $costumePrice, 'Line item costume không khớp đơn giá gốc.');
        }
    }
    assertCostume($hasCostumeLine, 'Không tạo được line item cho costume.');
}

report:
echo "\n===== TEST COSTUME-ONLY BOOKING =====\n";
if ($failures) {
    foreach ($failures as $failure) {
        echo "[FAIL] $failure\n";
    }
    echo "Tổng lỗi: " . count($failures) . " / Assertions: $assertions\n";
    exit(1);
}

echo "Tất cả bài test costume-only OK. Assertions: $assertions\n";
exit(0);
