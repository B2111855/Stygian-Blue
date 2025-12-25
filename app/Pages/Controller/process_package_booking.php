<?php

use App\Repositories\CostumeRepository;

session_start();
include '../../../database/config.php';
require_once __DIR__ . '/../../repositories/CostumeRepository.php';

function tp_redirect(string $location): void
{
    if (!headers_sent()) {
        header('Location: ' . $location);
    } else {
        echo '<script>window.location.href = ' . json_encode($location) . ';</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($location, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    }
    exit;
}
/**
 * Set flash (message + optional preserved form data) and redirect (PRG).
 */
function tp_flash_and_redirect(string $message, string $redirectUrl, array $formOld = [], string $type = 'error'): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
        'form_old' => $formOld,
    ];
    tp_redirect($redirectUrl);
}

function tp_wants_json(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xhr    = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return (stripos($accept, 'application/json') !== false) || strcasecmp($xhr, 'XMLHttpRequest') === 0;
}

function tp_json(array $payload, int $status = 200): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($status);
    }
    echo json_encode($payload);
    exit;
}

function sb_is_debug(): bool
{
    if (isset($_SESSION['ID_QUYEN']) && (string)$_SESSION['ID_QUYEN'] === '1') return true; // admin
    $env = getenv('SB_DEBUG');
    if ($env && ($env === '1' || strcasecmp($env, 'true') === 0)) return true;
    $dbg = $_REQUEST['debug'] ?? null;
    if ($dbg !== null && ($dbg === '1' || strcasecmp((string)$dbg, 'true') === 0)) return true;
    return false;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    tp_redirect('../Views/trangphuc.php?tab=packages');
}

$packageId = isset($_POST['package_id']) ? (int)$_POST['package_id'] : 0;
$returnTo  = trim($_POST['return_to'] ?? '');
$pricePerDayOverride = isset($_POST['price_per_day']) ? (int)$_POST['price_per_day'] : 0; // optional, not used in package

// booking common fields
$rentFrom = $_POST['rent_from'] ?? '';
$rentTo   = $_POST['rent_to'] ?? '';
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1; // applies to whole package
$quantity = max(1, $quantity);

$bookingQuery = [];
foreach (['rent_from', 'rent_to', 'quantity'] as $param) {
    if (!empty($_POST[$param])) {
        $bookingQuery[$param === 'quantity' ? 'qty' : ($param === 'rent_from' ? 'from' : 'to')] = $_POST[$param];
    }
}
$bookingQuery['id'] = $packageId;
// Luôn quay lại trang đặt thuê gói (PRG) khi có lỗi
$bookingView = '../Views/goi_datthue.php?' . http_build_query($bookingQuery);

if ($packageId <= 0) {
    if (tp_wants_json()) { tp_json(['ok' => false, 'message' => 'Gói trang phục không hợp lệ.']); }
    tp_flash_and_redirect('Gói trang phục không hợp lệ.', '../Views/trangphuc.php?tab=packages');
}

if (empty($_SESSION['ID_TK'])) {
    $_SESSION['flash'] = [
        'type' => 'error',
        'message' => 'Vui lòng đăng nhập để đặt thuê gói.',
    ];
    $loginRedirect = '../../../login.php?redirect=' . urlencode('app/Pages/Views/goi_datthue.php?id=' . $packageId);
    tp_redirect($loginRedirect);
}

$fromDt = DateTime::createFromFormat('Y-m-d\TH:i', $rentFrom);
$toDt   = DateTime::createFromFormat('Y-m-d\TH:i', $rentTo);
if (!$fromDt || !$toDt) {
    if (tp_wants_json()) { tp_json(['ok' => false, 'message' => 'Vui lòng chọn thời gian nhận và trả hợp lệ.']); }
    tp_flash_and_redirect('Vui lòng chọn thời gian nhận và trả hợp lệ.', $bookingView, [
        'rent_from' => $rentFrom,
        'rent_to'   => $rentTo,
        'quantity'  => $quantity,
    ]);
}
if ($toDt <= $fromDt) {
    if (tp_wants_json()) { tp_json(['ok' => false, 'message' => 'Thời gian trả phải sau thời gian nhận.']); }
    tp_flash_and_redirect('Thời gian trả phải sau thời gian nhận.', $bookingView, [
        'rent_from' => $rentFrom,
        'rent_to'   => $rentTo,
        'quantity'  => $quantity,
    ]);
}
$now = new DateTime();
if ($fromDt < $now) {
    if (tp_wants_json()) { tp_json(['ok' => false, 'message' => 'Thời gian nhận phải lớn hơn thời điểm hiện tại.']); }
    tp_flash_and_redirect('Thời gian nhận phải lớn hơn thời điểm hiện tại.', $bookingView, [
        'rent_from' => $rentFrom,
        'rent_to'   => $rentTo,
        'quantity'  => $quantity,
    ]);
}
$days = (int)ceil(($toDt->getTimestamp() - $fromDt->getTimestamp()) / 86400);
if ($days <= 0) {
    if (tp_wants_json()) { tp_json(['ok' => false, 'message' => 'Vui lòng chọn tối thiểu một ngày thuê.']); }
    tp_flash_and_redirect('Vui lòng chọn tối thiểu một ngày thuê.', $bookingView, [
        'rent_from' => $rentFrom,
        'rent_to'   => $rentTo,
        'quantity'  => $quantity,
        'rent_days' => 0,
    ]);
}

// Load package master
$pkgSql = "SELECT ID_GOI, TEN_GOI, ID_CN_OWNER, DISCOUNT_PERCENT FROM goi_trang_phuc_master WHERE ID_GOI=? LIMIT 1";
$package = null;
if ($stmt = $conn->prepare($pkgSql)) {
    $stmt->bind_param('i', $packageId);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        if ($res) { $package = $res->fetch_assoc(); }
    }
    $stmt->close();
}
if (!$package) {
    if (tp_wants_json()) { tp_json(['ok' => false, 'message' => 'Không tìm thấy gói trang phục.']); }
    tp_flash_and_redirect('Không tìm thấy gói trang phục.', '../Views/trangphuc.php?tab=packages');
}
$branchIdInt = (int)($package['ID_CN_OWNER'] ?? 0);
if ($branchIdInt <= 0) {
    if (tp_wants_json()) { tp_json(['ok' => false, 'message' => 'Gói chưa gắn chi nhánh sở hữu, không thể đặt thuê.']); }
    tp_flash_and_redirect('Gói chưa gắn chi nhánh sở hữu, không thể đặt thuê.', $bookingView);
}

// Load package items
$items = [];
$itemSql = "SELECT c.ID_TRANG_PHUC, c.SO_LUONG, COALESCE(tp.GIA_THUE,0) AS DON_GIA, tp.TRANG_THAI, tp.ID_CN
            FROM goi_trang_phuc_chi_tiet c JOIN trang_phuc tp ON tp.ID_TRANG_PHUC=c.ID_TRANG_PHUC
            WHERE c.ID_GOI=?";
if ($iStmt = $conn->prepare($itemSql)) {
    $iStmt->bind_param('i', $packageId);
    if ($iStmt->execute()) {
        $r = $iStmt->get_result();
        while ($row = $r->fetch_assoc()) { $items[] = $row; }
    }
    $iStmt->close();
}
if (!$items) {
    if (tp_wants_json()) { tp_json(['ok' => false, 'message' => 'Gói này chưa có trang phục nào.']); }
    tp_flash_and_redirect('Gói này chưa có trang phục nào.', $bookingView);
}

$costumeRepo = new CostumeRepository($conn);

// Validate items branch/status and availability
foreach ($items as $it) {
    if ((int)$it['ID_CN'] !== $branchIdInt) {
        if (tp_wants_json()) { tp_json(['ok' => false, 'message' => 'Một số trang phục trong gói không cùng chi nhánh, không thể đặt.']); }
        tp_flash_and_redirect('Một số trang phục trong gói không cùng chi nhánh, không thể đặt.', $bookingView);
    }
    if (($it['TRANG_THAI'] ?? '') !== 'available') {
        if (tp_wants_json()) { tp_json(['ok' => false, 'message' => 'Một số trang phục trong gói đang không sẵn sàng để đặt thuê.']); }
        tp_flash_and_redirect('Một số trang phục trong gói đang không sẵn sàng để đặt thuê.', $bookingView);
    }
    $id = (int)$it['ID_TRANG_PHUC'];
    $ngayNhan = $fromDt->format('Y-m-d H:i:s');
    $ngayTra  = $toDt->format('Y-m-d H:i:s');
    if (!$costumeRepo->isAvailableDuring($id, $ngayNhan, $ngayTra, $branchIdInt)) {
        if (tp_wants_json()) { tp_json(['ok' => false, 'message' => 'Có trang phục trong gói đã có lịch thuê trùng thời gian. Vui lòng chọn thời gian khác.']); }
        tp_flash_and_redirect('Có trang phục trong gói đã có lịch thuê trùng thời gian. Vui lòng chọn thời gian khác.', $bookingView, [
            'rent_from' => $rentFrom,
            'rent_to'   => $rentTo,
            'quantity'  => $quantity,
            'rent_days' => $days,
        ]);
    }
}

// Compute pricing
$totalOriginal = 0;
foreach ($items as $it) {
    $qty = max(1, (int)$it['SO_LUONG']);
    $price = (int)$it['DON_GIA'];
    $totalOriginal += $price * $qty;
}
$discountPercent = (int)($package['DISCOUNT_PERCENT'] ?? 0);
$discountAmount = $discountPercent > 0 ? (int)round($totalOriginal * $discountPercent / 100) : 0;
$totalFinal = $totalOriginal - $discountAmount;

// Estimate by days and overall quantity multiplier
$estimatedTotal = $totalFinal * $days * $quantity;
$depositSuggested = (int)round($estimatedTotal * 0.3);

$styleNote    = trim($_POST['style_note'] ?? '');
$note         = trim($_POST['note'] ?? '');

// Lấy thông tin liên hệ từ session (tương tự lienhe.php)
$idTk = null;
if (isset($_SESSION['user']['ID_TK'])) {
    $idTk = $_SESSION['user']['ID_TK'];
} elseif (isset($_SESSION['ID_TK'])) {
    $idTk = $_SESSION['ID_TK'];
}
$contactName = '';
$contactPhone = '';
$contactEmail = '';
if ($idTk) {
    if ($st = $conn->prepare('SELECT HO_TEN, SDT, EMAIL FROM TAI_KHOAN WHERE ID_TK=?')) {
        $st->bind_param('s', $idTk);
        if ($st->execute()) { $st->bind_result($contactName, $contactPhone, $contactEmail); $st->fetch(); }
        $st->close();
    }
}
$notes = [];
if ($styleNote !== '') { $notes[] = 'Phối đồ: ' . $styleNote; }
if ($note !== '') { $notes[] = $note; }
$contactParts = array_filter([$contactName, $contactPhone, $contactEmail]);
if ($contactParts) { $notes[] = 'Liên hệ: ' . implode(' · ', $contactParts); }
$noteContent = $notes ? ('- ' . implode("\n- ", $notes)) : '';

try {
    $conn->begin_transaction();

    $insertOrder = $conn->prepare("INSERT INTO don_thue_trang_phuc (ID_TK, ID_CN, NGAY_NHAN, NGAY_TRA_DK, TRANG_THAI, TIEN_COC, TONG_TIEN_DU_KIEN, GHI_CHU) VALUES (?, ?, ?, ?, 'cho_duyet', ?, ?, ?)");
    if (!$insertOrder) { throw new Exception('Không thể tạo đơn thuê: ' . $conn->error); }

    $userId   = $_SESSION['ID_TK'];
    $ngayNhan = $fromDt->format('Y-m-d H:i:s');
    $ngayTra  = $toDt->format('Y-m-d H:i:s');

    $insertOrder->bind_param(
        'sissiis',
        $userId,
        $branchIdInt,
        $ngayNhan,
        $ngayTra,
        $depositSuggested,
        $estimatedTotal,
        $noteContent
    );
    if (!$insertOrder->execute()) { throw new Exception('Không thể lưu đơn thuê: ' . $insertOrder->error); }
    $orderId = $insertOrder->insert_id;
    $insertOrder->close();

    $insertDetail = $conn->prepare("INSERT INTO don_thue_trang_phuc_ct (ID_TTP, ID_TP, SO_LUONG, DON_GIA_AP_DUNG) VALUES (?, ?, ?, ?)");
    if (!$insertDetail) { throw new Exception('Không thể lưu chi tiết đơn thuê: ' . $conn->error); }

    foreach ($items as $it) {
        $id  = (int)$it['ID_TRANG_PHUC'];
        $qty = max(1, (int)$it['SO_LUONG']) * $quantity; // overall multiplier
        $price = (int)$it['DON_GIA'];
        $insertDetail->bind_param('iiii', $orderId, $id, $qty, $price);
        if (!$insertDetail->execute()) { throw new Exception('Không thể lưu chi tiết đơn thuê: ' . $insertDetail->error); }
    }
    $insertDetail->close();

    $conn->commit();

    $successMsg = 'Yêu cầu đặt thuê gói đã được gửi. Chúng tôi sẽ liên hệ xác nhận sớm nhất.';
    $_SESSION['flash'] = [ 'type' => 'success', 'message' => $successMsg ];
    $_SESSION['message'] = $successMsg;
    $_SESSION['message_type'] = 'success';

    // Luôn chuyển đến lịch hẹn sau khi đặt thành công
    $successRedirect = '../Views/xemLichhen.php';
    if (tp_wants_json()) {
        tp_json(['ok' => true, 'redirect' => $successRedirect]);
    }
    tp_redirect($successRedirect);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('Package booking error: ' . $e->getMessage());
    $baseMsg = 'Không thể hoàn tất đặt thuê gói. Vui lòng thử lại hoặc liên hệ hỗ trợ.';
    $detail  = $e->getMessage();
    $fullMsg = sb_is_debug() && $detail ? ($baseMsg . ' Chi tiết: ' . $detail) : $baseMsg;
    if (tp_wants_json()) { tp_json(['ok' => false, 'message' => $fullMsg]); }
    tp_flash_and_redirect($fullMsg, $bookingView, [
        'rent_from' => $rentFrom,
        'rent_to'   => $rentTo,
        'quantity'  => $quantity,
        'rent_days' => isset($days) ? $days : null,
    ]);
}
