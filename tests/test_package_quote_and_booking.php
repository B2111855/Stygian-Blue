<?php
/**
 * Automated integration test (MVP) for package + costume flow.
 * Run from CLI: php tests/test_package_quote_and_booking.php
 *
 * Assumptions:
 * - DB connection config in database/config.php
 * - Feature flag ENABLE_PACKAGE_COSTUME enabled (export or putenv below)
 * - There exists at least one package in v_goi_dich_vu_tong_tien with mapped costumes in GOI_TRANG_PHUC.
 * - A user session (ID_TK) is available or we fake one.
 *
 * What it does:
 * 1. Finds a package with at least one mandatory and one optional costume (if possible).
 * 2. Calls quote_preview.php via include (POST fallback) capturing JSON.
 * 3. Performs a booking by faking $_POST and session then including process_schedule.php, suppressing exit.
 * 4. Verifies BOOKING_ITEM rows were created (package + mandatory + optional).
 * 5. Prints summary and exit code (0 success, >0 failures).
 */

putenv('ENABLE_PACKAGE_COSTUME=1');
define('CLI_TEST_MODE', true);

$exitFailures = 0;
$assertions = 0;
$failures = [];

function assertTrue($cond, $msg) {
    global $assertions, $failures, $exitFailures;
    $assertions++;
    if (!$cond) { $failures[] = $msg; $exitFailures++; }
}

function jsonDecodeSafe($raw) {
    $d = json_decode($raw, true);
    return is_array($d) ? $d : null;
}

require_once __DIR__ . '/../database/config.php';
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// 1. Pick package with costumes
$packageId = null;
$costumes = [];
$sql = "SELECT DISTINCT gtp.ID_GOI FROM GOI_TRANG_PHUC gtp LIMIT 10";
$rs = $conn->query($sql);
if ($rs) {
    while ($row = $rs->fetch_assoc()) {
        $pid = (int)$row['ID_GOI'];
        // Load its costumes
        $sqlC = "SELECT tp.ID_TRANG_PHUC, tp.TEN, tp.GIA_THUE, gtp.DISCOUNT_PERCENT, gtp.BAT_BUOC
                 FROM GOI_TRANG_PHUC gtp JOIN TRANG_PHUC tp ON tp.ID_TRANG_PHUC = gtp.ID_TRANG_PHUC
                 WHERE gtp.ID_GOI = $pid";
        $rsC = $conn->query($sqlC);
        $rows = $rsC ? $rsC->fetch_all(MYSQLI_ASSOC) : [];
        if ($rsC) { $rsC->close(); }
        $hasMandatory = false; $hasOptional = false;
        foreach ($rows as $r) {
            if ((int)$r['BAT_BUOC'] === 1) $hasMandatory = true; else $hasOptional = true;
        }
        if ($hasMandatory && $hasOptional) {
            $packageId = $pid; $costumes = $rows; break;
        }
        if ($packageId === null && !empty($rows)) { // fallback if not both
            $packageId = $pid; $costumes = $rows; // pick first available
        }
    }
    $rs->close();
}

assertTrue($packageId !== null, 'Không tìm được package có trang phục. Seed dữ liệu trước.');
if ($packageId === null) {
    goto report; // cannot continue tests
}

// Determine one optional costume to select
$optionalCostumeIds = [];
$mandatoryCostumeIds = [];
foreach ($costumes as $c) {
    if ((int)$c['BAT_BUOC'] === 1) $mandatoryCostumeIds[] = (int)$c['ID_TRANG_PHUC'];
    else $optionalCostumeIds[] = (int)$c['ID_TRANG_PHUC'];
}
$selectedOptional = $optionalCostumeIds ? [$optionalCostumeIds[0]] : [];

// 2. Quote preview test
$_POST = [
    'branch_id' => 1, // adjust if branch 1 not exists
    'package_id' => $packageId,
    'location_type' => 'branch',
    'costume_ids' => $selectedOptional,
];
ob_start();
include __DIR__ . '/../app/Pages/Controller/quote_preview.php';
$quoteRaw = ob_get_clean();
$quote = jsonDecodeSafe($quoteRaw);
assertTrue($quote !== null && isset($quote['total']) && $quote['mode'] === 'package', 'Quote preview package trả về không hợp lệ.');

// 3. Booking test (simulate form submit)
// Fake user session
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$_SESSION['ID_TK'] = 'TEST_USER_' . bin2hex(random_bytes(4));
// Insert tai_khoan record minimal if needed
$chkUser = $conn->prepare('SELECT ID_TK FROM tai_khoan WHERE ID_TK = ? LIMIT 1');
if ($chkUser) {
    $chkUser->bind_param('s', $_SESSION['ID_TK']);
    $chkUser->execute();
    $rUser = $chkUser->get_result();
    if (!$rUser || $rUser->num_rows === 0) {
        $ins = $conn->prepare('INSERT INTO tai_khoan (ID_TK, ID_QUYEN, HO_TEN, EMAIL, SDT, MAT_KHAU) VALUES (?, 1, "Test User", "test@example.com", "0900000000", "x")');
        if ($ins) { $ins->bind_param('s', $_SESSION['ID_TK']); $ins->execute(); $ins->close(); }
    }
    $chkUser->close();
}

// Need representative serviceId from package (from goi_dich_vu_chi_tiet)
$serviceFromPackage = null;
$svcStmt = $conn->prepare('SELECT ID_DV FROM goi_dich_vu_chi_tiet WHERE ID_GOI = ? ORDER BY COALESCE(THU_TU,1) LIMIT 1');
if ($svcStmt) { $svcStmt->bind_param('i', $packageId); $svcStmt->execute(); $svcStmt->bind_result($sid); if ($svcStmt->fetch()) { $serviceFromPackage = (int)$sid; } $svcStmt->close(); }

assertTrue($serviceFromPackage !== null, 'Không lấy được service đại diện của gói.');

$today = (new DateTime('now'))->format('Y-m-d');
$_POST = [
    'branch_id' => 1,
    'ngayHen' => $today,
    'gioHen' => '13:00',
    'address' => 'Chi nhánh 1',
    'location_type' => 'branch',
    'booking_type' => 'package',
    'package_id' => $packageId,
    'service_id' => $serviceFromPackage,
    'costume_ids' => $selectedOptional,
];

// Include process_schedule; it will header redirect and exit.
// We trap exit by overriding exit/die temporarily (hack) using runkit not available; use output buffer and ignore headers.
// Simpler: run in subprocess? For MVP keep include; redirection stops script. So run before checking DB state.

$processResult = include __DIR__ . '/../app/Pages/Controller/process_schedule.php';
assertTrue(is_array($processResult) && ($processResult['status'] ?? '') === 'success', 'process_schedule không trả về trạng thái thành công.');
$latestBookingId = $processResult['booking_id'] ?? null;
if ($latestBookingId === null) {
    $lhStmt = $conn->prepare('SELECT ID_LICHHEN FROM lich_hen WHERE ID_TK = ? ORDER BY ID_LICHHEN DESC LIMIT 1');
    if ($lhStmt) { $lhStmt->bind_param('s', $_SESSION['ID_TK']); $lhStmt->execute(); $lhStmt->bind_result($bid); if ($lhStmt->fetch()) { $latestBookingId = (int)$bid; } $lhStmt->close(); }
}
assertTrue($latestBookingId !== null, 'Không tạo được booking package.');

// Inspect line items
$items = [];
if ($latestBookingId !== null) {
    $itStmt = $conn->prepare('SELECT ITEM_TYPE, REF_ID, DON_GIA, DISCOUNT_PERCENT FROM BOOKING_ITEM WHERE ID_LICHHEN = ?');
    if ($itStmt) { $itStmt->bind_param('i', $latestBookingId); $itStmt->execute(); $r = $itStmt->get_result(); $items = $r ? $r->fetch_all(MYSQLI_ASSOC) : []; $itStmt->close(); }
}

$hasPackageItem = false; $mandatoryCostumeItem = false; $optionalCostumeItem = false;
foreach ($items as $it) {
    if ($it['ITEM_TYPE'] === 'package') $hasPackageItem = true;
    if ($it['ITEM_TYPE'] === 'costume') {
        if ((int)$it['DISCOUNT_PERCENT'] === 100) $mandatoryCostumeItem = true; else $optionalCostumeItem = true;
    }
}
assertTrue($hasPackageItem, 'Thiếu line item package.');
assertTrue($mandatoryCostumeItem, 'Thiếu line item trang phục bắt buộc.');
if ($selectedOptional) {
    assertTrue($optionalCostumeItem, 'Thiếu line item trang phục tuỳ chọn đã chọn.');
}

report:

echo "\n===== KẾT QUẢ TEST PACKAGE + COSTUME =====\n";
foreach ($failures as $f) { echo "[FAIL] $f\n"; }
if (empty($failures)) {
    echo "Tất cả kiểm thử thông qua. Assertions: $assertions\n";
} else {
    echo "Có lỗi: " . count($failures) . " / Assertions: $assertions\n";
}

// Exit code for CI usage
exit($exitFailures > 0 ? 1 : 0);
