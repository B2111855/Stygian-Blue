<?php
// Controller: process booking by costume type (ID_LOAI) with quantity allocation
session_start();
require_once '../../../database/config.php';
require_once '../../../app/services/BookingService.php';

function tp_redirect_type(string $location): void {
    if (!headers_sent()) {
        header('Location: ' . $location);
    } else {
        echo '<script>window.location.href=' . json_encode($location) . ';</script>';
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    tp_redirect_type('../Views/trangphuc_loai_datthue.php');
}

$typeId    = $_POST['type_id'] ?? '';
$branchId  = $_POST['branch_id'] ?? '';
$quantity  = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
$rentFrom  = $_POST['rent_from'] ?? '';
$rentTo    = $_POST['rent_to'] ?? '';
$priceDay  = isset($_POST['price_per_day']) ? (int)$_POST['price_per_day'] : 0; // base type price
$returnTo  = trim($_POST['return_to'] ?? 'trangphuc_loai_chitiet.php?id=' . $typeId);

// Build safe redirect for error state
$queryParams = [];
foreach (['rent_from' => 'from','rent_to' => 'to','quantity' => 'qty'] as $k => $alias) {
    if (!empty($_POST[$k])) { $queryParams[$alias] = $_POST[$k]; }
}
$queryParams['id'] = $typeId;
$bookingView = '../Views/trangphuc_loai_datthue.php?' . http_build_query($queryParams);

if (!ctype_digit((string)$typeId)) {
    $_SESSION['message'] = 'Loại trang phục không hợp lệ.'; $_SESSION['message_type'] = 'error'; tp_redirect_type($bookingView);
}
if (!ctype_digit((string)$branchId) || (int)$branchId <= 0) {
    $_SESSION['message'] = 'Chi nhánh không hợp lệ.'; $_SESSION['message_type'] = 'error'; tp_redirect_type($bookingView);
}
$branchIdInt = (int)$branchId;
if (empty($_SESSION['ID_TK'])) {
    $_SESSION['message'] = 'Vui lòng đăng nhập để đặt thuê.'; $_SESSION['message_type'] = 'error'; tp_redirect_type('../../../login.php');
}

$fromDt = DateTime::createFromFormat('Y-m-d\TH:i', $rentFrom);
$toDt   = DateTime::createFromFormat('Y-m-d\TH:i', $rentTo);
if (!$fromDt || !$toDt || $toDt <= $fromDt) {
    $_SESSION['message'] = 'Thời gian không hợp lệ.'; $_SESSION['message_type'] = 'error'; tp_redirect_type($bookingView);
}
if ($fromDt < new DateTime()) { $_SESSION['message'] = 'Thời gian nhận phải trong tương lai.'; $_SESSION['message_type'] = 'error'; tp_redirect_type($bookingView); }

$days = (int)ceil(($toDt->getTimestamp() - $fromDt->getTimestamp()) / 86400);
if ($days <= 0 || $days > 30) { $_SESSION['message'] = 'Số ngày thuê phải từ 1 đến 30.'; $_SESSION['message_type'] = 'error'; tp_redirect_type($bookingView); }

$quantity = max(1, $quantity);

// Fetch type pricing & validate type active
$typeSql = "SELECT ID_LOAI, TEN_LOAI, GIA_THUE_CO_SO FROM trang_phuc_loai WHERE ID_LOAI=? AND TRANG_THAI='active' LIMIT 1";
$typeData = null;
if ($stmt = $conn->prepare($typeSql)) {
    $stmt->bind_param('i', $typeId);
    if ($stmt->execute()) { $res = $stmt->get_result(); $typeData = $res?->fetch_assoc(); }
    $stmt->close();
}
if (!$typeData) { $_SESSION['message'] = 'Loại trang phục không hoạt động.'; $_SESSION['message_type'] = 'error'; tp_redirect_type($bookingView); }

if ($priceDay <= 0) { $priceDay = (int)$typeData['GIA_THUE_CO_SO']; }
if ($priceDay <= 0) { $_SESSION['message'] = 'Không xác định đơn giá loại trang phục.'; $_SESSION['message_type'] = 'error'; tp_redirect_type($bookingView); }

$estTotal = $priceDay * $days * $quantity; $deposit = (int)round($estTotal * 0.3);

$note = trim($_POST['note'] ?? '');
$ghiChu = $note !== '' ? $note : null;

try {
    $conn->begin_transaction();

    // Create order
    $insertOrder = $conn->prepare("INSERT INTO don_thue_trang_phuc (ID_TK, ID_CN, NGAY_NHAN, NGAY_TRA_DK, TRANG_THAI, TIEN_COC, TONG_TIEN_DU_KIEN, GHI_CHU) VALUES (?,?,?,?, 'cho_duyet', ?, ?, ?)");
    if (!$insertOrder) { throw new Exception('Không thể tạo đơn: ' . $conn->error); }
    $userId = $_SESSION['ID_TK']; $fromStr = $fromDt->format('Y-m-d H:i:s'); $toStr = $toDt->format('Y-m-d H:i:s');
    $insertOrder->bind_param('sissiii', $userId, $branchIdInt, $fromStr, $toStr, $deposit, $estTotal, $ghiChu);
    if (!$insertOrder->execute()) { throw new Exception('Lỗi lưu đơn: ' . $insertOrder->error); }
    $orderId = $insertOrder->insert_id; $insertOrder->close();

    // Allocation
    $bookingService = new BookingService();
    $alloc = $bookingService->allocateItemsForType($conn, $orderId, (int)$typeId, $branchIdInt, $quantity, $fromStr, $toStr, $priceDay);
    if ($alloc['allocated'] !== $quantity) { throw new Exception('Phân bổ không đủ số lượng yêu cầu.'); }

    $conn->commit();

    $_SESSION['message'] = 'Đặt thuê loại trang phục thành công. Chờ duyệt.';
    $_SESSION['message_type'] = 'success';
    tp_redirect_type('../Views/' . $returnTo);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('Type booking error: ' . $e->getMessage());
    $_SESSION['message'] = 'Không thể đặt thuê: ' . (strpos($e->getMessage(),'Không đủ số lượng') !== false ? 'Thiếu số lượng khả dụng.' : 'Lỗi hệ thống.');
    $_SESSION['message_type'] = 'error';
    tp_redirect_type($bookingView);
}
