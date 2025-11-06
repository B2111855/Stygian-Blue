<?php
session_start();

require_once '../../../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Payments\VNPayConfig;
use App\Payments\VNPayService;

if (file_exists('../../../.env')) {
    $dotenv = Dotenv::createImmutable('../../../');
    $dotenv->safeLoad();
}

require_once '../../../database/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['invoice_id'])) {
    $_SESSION['payment_notice'] = 'Yêu cầu thanh toán không hợp lệ.';
    $_SESSION['payment_notice_type'] = 'error';
    header('Location: ../Views/hoa_don.php');
    exit;
}

if (empty($_SESSION['ID_TK'])) {
    $_SESSION['payment_notice'] = 'Vui lòng đăng nhập để tiếp tục thanh toán.';
    $_SESSION['payment_notice_type'] = 'error';
    header('Location: ../Views/hoa_don.php');
    exit;
}

$invoiceId = (int) $_POST['invoice_id'];
$userId = $_SESSION['ID_TK'];

$invoiceSql = "
    SELECT h.ID_HD, h.TONG_TIEN, h.TRANGTHAI_THANHTOAN, h.PHUONGTHUC_THANHTOAN,
           k.HO_TEN, k.EMAIL, k.SDT
    FROM hoa_don h
    JOIN lich_hen l ON h.ID_LICHHEN = l.ID_LICHHEN
    JOIN khach_hang k ON l.ID_TK = k.ID_TK
    WHERE h.ID_HD = ? AND l.ID_TK = ?
    LIMIT 1
";

$stmt = $conn->prepare($invoiceSql);
if (!$stmt) {
    $_SESSION['payment_notice'] = 'Không thể chuẩn bị truy vấn hóa đơn.';
    $_SESSION['payment_notice_type'] = 'error';
    header('Location: ../Views/hoa_don.php');
    exit;
}

$stmt->bind_param('is', $invoiceId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$invoice = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$invoice) {
    $_SESSION['payment_notice'] = 'Không tìm thấy hóa đơn phù hợp.';
    $_SESSION['payment_notice_type'] = 'error';
    header('Location: ../Views/hoa_don.php');
    exit;
}

if ($invoice['TRANGTHAI_THANHTOAN'] === 'Đã thanh toán') {
    $_SESSION['payment_notice'] = 'Hóa đơn đã được thanh toán trước đó.';
    $_SESSION['payment_notice_type'] = 'error';
    header('Location: ../Views/hoa_don.php');
    exit;
}

$amount = (float) $invoice['TONG_TIEN'];
if ($amount <= 0) {
    $_SESSION['payment_notice'] = 'Số tiền không hợp lệ để thanh toán.';
    $_SESSION['payment_notice_type'] = 'error';
    header('Location: ../Views/hoa_don.php');
    exit;
}

$config = VNPayConfig::fromEnvironment(array_merge($_ENV, $_SERVER));
$service = new VNPayService($config);

$orderId = 'HD' . $invoiceId . '_' . date('YmdHis');
$payload = [
    'orderId' => $orderId,
    'orderDescription' => 'Thanh toán hóa đơn #' . $invoiceId,
    'orderType' => 'billpayment',
    'amount' => $amount,
    'language' => $config->defaultLocale,
    'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
    'billing' => [
        'fullName' => $invoice['HO_TEN'] ?? '',
        'email' => $invoice['EMAIL'] ?? '',
        'mobile' => $invoice['SDT'] ?? '',
    ],
];

$paymentUrl = $service->buildPaymentUrl($payload);

$insertSql = "
    INSERT INTO thanh_toan_truc_tuyen (ID_HD, GATEWAY, MA_THAM_CHIEU, SO_TIEN, CURRENCY, TRANG_THAI)
    VALUES (?, 'vnpay', ?, ?, 'VND', 'pending')
";
$insertStmt = $conn->prepare($insertSql);

if (!$insertStmt) {
    $_SESSION['payment_notice'] = 'Không thể lưu giao dịch thanh toán.';
    $_SESSION['payment_notice_type'] = 'error';
    header('Location: ../Views/hoa_don.php');
    exit;
}

$insertStmt->bind_param('isd', $invoiceId, $orderId, $amount);

if (!$insertStmt->execute()) {
    $insertStmt->close();
    $_SESSION['payment_notice'] = 'Không thể khởi tạo giao dịch thanh toán. Vui lòng thử lại sau.';
    $_SESSION['payment_notice_type'] = 'error';
    header('Location: ../Views/hoa_don.php');
    exit;
}

$insertStmt->close();
$conn->close();

header('Location: ' . $paymentUrl);
exit;