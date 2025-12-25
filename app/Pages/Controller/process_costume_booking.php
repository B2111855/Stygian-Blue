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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    tp_redirect('../Views/trangphuc.php');
}

$costumeRepo = new CostumeRepository($conn);

$costumeId = $_POST['costume_id'] ?? '';
$returnTo  = trim($_POST['return_to'] ?? '');
$branchId  = $_POST['branch_id'] ?? '';
$priceFromForm = isset($_POST['price_per_day']) ? (int)$_POST['price_per_day'] : 0;

$bookingQuery = [];
foreach (['rent_from', 'rent_to', 'quantity'] as $param) {
    if (!empty($_POST[$param])) {
        $bookingQuery[$param === 'quantity' ? 'qty' : ($param === 'rent_from' ? 'from' : 'to')] = $_POST[$param];
    }
}
$bookingQuery['id'] = $costumeId;
$bookingView = '../Views/trangphuc_datthue.php?' . http_build_query($bookingQuery);

if (!ctype_digit((string)$costumeId)) {
    $_SESSION['message'] = 'Trang phục không hợp lệ.';
    $_SESSION['message_type'] = 'error';
    tp_redirect($bookingView);
}

if (empty($_SESSION['ID_TK'])) {
    $_SESSION['message'] = 'Vui lòng đăng nhập để đặt thuê trang phục.';
    $_SESSION['message_type'] = 'error';
    $loginRedirect = '../../../login.php?redirect=' . urlencode('app/Pages/Views/trangphuc_datthue.php?id=' . $costumeId);
    tp_redirect($loginRedirect);
}

$branchIdInt = ctype_digit((string)$branchId) ? (int)$branchId : 0;
if ($branchIdInt <= 0) {
    $_SESSION['message'] = 'Chi nhánh không hợp lệ.';
    $_SESSION['message_type'] = 'error';
    tp_redirect($bookingView);
}

$rentFrom = $_POST['rent_from'] ?? '';
$rentTo   = $_POST['rent_to'] ?? '';
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
$quantity = max(1, $quantity);

$fromDt = DateTime::createFromFormat('Y-m-d\TH:i', $rentFrom);
$toDt   = DateTime::createFromFormat('Y-m-d\TH:i', $rentTo);

if (!$fromDt || !$toDt) {
    $_SESSION['message'] = 'Vui lòng chọn thời gian nhận và trả hợp lệ.';
    $_SESSION['message_type'] = 'error';
    tp_redirect($bookingView);
}

if ($toDt <= $fromDt) {
    $_SESSION['message'] = 'Thời gian trả phải sau thời gian nhận.';
    $_SESSION['message_type'] = 'error';
    tp_redirect($bookingView);
}

$now = new DateTime();
if ($fromDt < $now) {
    $_SESSION['message'] = 'Thời gian nhận phải lớn hơn thời điểm hiện tại.';
    $_SESSION['message_type'] = 'error';
    tp_redirect($bookingView);
}

$days = (int)ceil(($toDt->getTimestamp() - $fromDt->getTimestamp()) / 86400);
if ($days <= 0) {
    $_SESSION['message'] = 'Vui lòng chọn tối thiểu một ngày thuê.';
    $_SESSION['message_type'] = 'error';
    tp_redirect($bookingView);
}

// New schema: GIA_THUE directly in trang_phuc table
$costumeSql = "SELECT tp.ID_TRANG_PHUC AS ID_TP, tp.ID_CN, tp.TEN AS TEN_TP, tp.TRANG_THAI AS TINH_TRANG, COALESCE(tp.GIA_THUE, 0) AS DON_GIA FROM trang_phuc tp WHERE tp.ID_TRANG_PHUC = ? LIMIT 1";
$costumeData = null;
if ($stmt = $conn->prepare($costumeSql)) {
    $costumeIdInt = (int)$costumeId;
    $stmt->bind_param('i', $costumeIdInt);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result) {
            $costumeData = $result->fetch_assoc();
        }
    }
    $stmt->close();
}

if (!$costumeData) {
    $_SESSION['message'] = 'Trang phục không còn khả dụng.';
    $_SESSION['message_type'] = 'error';
    tp_redirect($bookingView);
}

$scopeCheck = $costumeRepo->validateCostumeCanBeRented((int)$costumeId, $branchIdInt);
if (!$scopeCheck['allowed']) {
    $_SESSION['message'] = $scopeCheck['reason'] ?? 'Trang phục không thể thuê tại chi nhánh này.';
    $_SESSION['message_type'] = 'error';
    tp_redirect($bookingView);
}

if ((int)$costumeData['ID_CN'] !== $branchIdInt) {
    $_SESSION['message'] = 'Chi nhánh đặt thuê không khớp với trang phục.';
    $_SESSION['message_type'] = 'error';
    tp_redirect($bookingView);
}

if ($costumeData['TINH_TRANG'] !== 'available') {
    $_SESSION['message'] = 'Trang phục đang không sẵn sàng để đặt thuê.';
    $_SESSION['message_type'] = 'error';
    tp_redirect($bookingView);
}

$pricePerDay = (int)($costumeData['DON_GIA'] ?? 0);
if ($pricePerDay <= 0 && $priceFromForm > 0) {
    $pricePerDay = $priceFromForm;
}
if ($pricePerDay <= 0) {
    $_SESSION['message'] = 'Không tìm thấy đơn giá áp dụng cho trang phục này.';
    $_SESSION['message_type'] = 'error';
    tp_redirect($bookingView);
}

$ngayNhan    = $fromDt->format('Y-m-d H:i:s');
$ngayTra     = $toDt->format('Y-m-d H:i:s');

if (!$costumeRepo->isAvailableDuring((int)$costumeId, $ngayNhan, $ngayTra, $branchIdInt)) {
    $_SESSION['message'] = 'Trang phục đã được đặt trong khoảng thời gian này. Vui lòng chọn thời gian khác.';
    $_SESSION['message_type'] = 'error';
    tp_redirect($bookingView);
}

$estimatedTotal = $pricePerDay * $days * $quantity;
$depositSuggested = (int)round($estimatedTotal * 0.3);

$styleNote    = trim($_POST['style_note'] ?? '');
$note         = trim($_POST['note'] ?? '');
$contactName  = trim($_POST['contact_name'] ?? '');
$contactPhone = trim($_POST['contact_phone'] ?? '');
$contactEmail = trim($_POST['contact_email'] ?? '');

$notes = [];
if ($styleNote !== '') {
    $notes[] = 'Phối đồ: ' . $styleNote;
}
if ($note !== '') {
    $notes[] = $note;
}
$contactParts = array_filter([$contactName, $contactPhone, $contactEmail]);
if ($contactParts) {
    $notes[] = 'Liên hệ: ' . implode(' · ', $contactParts);
}
$noteContent = '';
if ($notes) {
    $noteContent = '- ' . implode("\n- ", $notes);
}

try {
    $conn->begin_transaction();

    $insertOrder = $conn->prepare("INSERT INTO don_thue_trang_phuc (ID_TK, ID_CN, NGAY_NHAN, NGAY_TRA_DK, TRANG_THAI, TIEN_COC, TONG_TIEN_DU_KIEN, GHI_CHU) VALUES (?, ?, ?, ?, 'cho_duyet', ?, ?, ?)");
    if (!$insertOrder) {
        throw new Exception('Không thể tạo đơn thuê: ' . $conn->error);
    }

    $userId      = $_SESSION['ID_TK'];

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

    if (!$insertOrder->execute()) {
        throw new Exception('Không thể lưu đơn thuê: ' . $insertOrder->error);
    }

    $orderId = $insertOrder->insert_id;
    $insertOrder->close();

    $insertDetail = $conn->prepare("INSERT INTO don_thue_trang_phuc_ct (ID_TTP, ID_TP, SO_LUONG, DON_GIA_AP_DUNG) VALUES (?, ?, ?, ?)");
    if (!$insertDetail) {
        throw new Exception('Không thể lưu chi tiết đơn thuê: ' . $conn->error);
    }

    $selectedCostumeId = (int)$costumeId;
    $insertDetail->bind_param('iiii', $orderId, $selectedCostumeId, $quantity, $pricePerDay);
    if (!$insertDetail->execute()) {
        throw new Exception('Không thể lưu chi tiết đơn thuê: ' . $insertDetail->error);
    }
    $insertDetail->close();

    $conn->commit();

    $_SESSION['message'] = 'Yêu cầu đặt thuê đã được gửi. Chúng tôi sẽ liên hệ xác nhận trong thời gian sớm nhất.';
    $_SESSION['message_type'] = 'success';

    if ($returnTo === '' || preg_match('/[^a-zA-Z0-9_\-\.\?=&]/', $returnTo)) {
        $returnTo = 'trangphuc_chitiet.php?id=' . $costumeId;
    }

    $successRedirect = '../Views/' . $returnTo;
    tp_redirect($successRedirect);
} catch (Throwable $e) {
    $conn->rollback();
    $rawMessage = $e->getMessage();
    error_log('Costume booking error: ' . $rawMessage);

    $userMessage = 'Không thể hoàn tất đặt thuê. Vui lòng thử lại hoặc liên hệ hỗ trợ.';
    if (stripos($rawMessage, 'đặt/thuê trong khoảng thời gian này') !== false) {
        $userMessage = 'Trang phục đã có lịch thuê trùng thời gian bạn chọn. Vui lòng chọn khoảng thời gian khác.';
    }

    $_SESSION['message'] = $userMessage;
    $_SESSION['message_type'] = 'error';
    tp_redirect($bookingView);
}