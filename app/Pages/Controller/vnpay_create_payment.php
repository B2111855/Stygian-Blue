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

// TTL cho giao dịch pending (phút)
$ttlMinutes = 20;
// Dọn dẹp các giao dịch pending đã quá hạn trước khi khởi tạo mới
$expireStmt = $conn->prepare("UPDATE thanh_toan_truc_tuyen SET TRANG_THAI='expired' WHERE TRANG_THAI='pending' AND CREATED_AT < (NOW() - INTERVAL ? MINUTE)");
if ($expireStmt) { $expireStmt->bind_param('i', $ttlMinutes); $expireStmt->execute(); $expireStmt->close(); }

$invoiceSql = "
    SELECT 
        h.ID_HD,
        h.TONG_TIEN,
        h.TRANGTHAI_THANHTOAN,
        h.PHUONGTHUC_THANHTOAN,
        h.ID_TTP,
        COALESCE(l.ID_TK, ttp.ID_TK) AS OWNER_ID,
        k.HO_TEN,
        k.EMAIL,
        k.SDT,
        ttp.TIEN_COC
    FROM hoa_don h
    LEFT JOIN lich_hen l ON h.ID_LICHHEN = l.ID_LICHHEN
    LEFT JOIN don_thue_trang_phuc ttp ON h.ID_TTP = ttp.ID_TTP
    LEFT JOIN khach_hang k ON k.ID_TK = COALESCE(l.ID_TK, ttp.ID_TK)
    WHERE h.ID_HD = ? AND (l.ID_TK = ? OR ttp.ID_TK = ?)
    LIMIT 1
";

$stmt = $conn->prepare($invoiceSql);
if (!$stmt) {
    $_SESSION['payment_notice'] = 'Không thể chuẩn bị truy vấn hóa đơn.';
    $_SESSION['payment_notice_type'] = 'error';
    header('Location: ../Views/hoa_don.php');
    exit;
}

$stmt->bind_param('iss', $invoiceId, $userId, $userId);
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

// Luôn cho phép tạo phiên mới; hết hạn mọi phiên pending cũ của hóa đơn này trước khi tạo
$expireInvoiceStmt = $conn->prepare("UPDATE thanh_toan_truc_tuyen SET TRANG_THAI='expired' WHERE ID_HD=? AND GATEWAY='vnpay' AND TRANG_THAI='pending'");
if ($expireInvoiceStmt) { $expireInvoiceStmt->bind_param('i', $invoiceId); $expireInvoiceStmt->execute(); $expireInvoiceStmt->close(); }

$isRental = !empty($invoice['ID_TTP']);
$amount = $isRental ? (float) ($invoice['TIEN_COC'] ?? 0) : (float) $invoice['TONG_TIEN'];
if ($amount <= 0) {
    $_SESSION['payment_notice'] = $isRental
        ? 'Đơn thuê này hiện không cần thanh toán tiền cọc.'
        : 'Số tiền không hợp lệ để thanh toán.';
    $_SESSION['payment_notice_type'] = 'error';
    header('Location: ../Views/hoa_don.php');
    exit;
}

$config = VNPayConfig::fromEnvironment(array_merge($_ENV, $_SERVER));
$service = new VNPayService($config);

// Xác định base URL (ngrok hoặc localhost)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
$baseUrl = $protocol . '://' . $host;
$projectPath = '/StygianBlue';

// Override từ .env nếu có (cho ngrok)
if (!empty($_ENV['VNPAY_BASE_URL'])) {
    $baseUrl = rtrim($_ENV['VNPAY_BASE_URL'], '/');
}

$orderId = ($isRental ? 'COC_TTP' . $invoice['ID_TTP'] : 'HD' . $invoiceId) . '_' . date('YmdHis');
$payload = [
    'orderId' => $orderId,
    'orderDescription' => $isRental ? ('Thanh toan tien coc don thue #' . $invoice['ID_TTP'] . ' (HD #' . $invoiceId . ')') : ('Thanh toan hoa don ' . $invoiceId),
    'orderType' => $isRental ? 'deposit' : 'billpayment',
    'amount' => $amount,
    'language' => $config->defaultLocale,
    'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
    'billing' => [
        'fullName' => $invoice['HO_TEN'] ?? '',
        'email' => $invoice['EMAIL'] ?? '',
        'mobile' => $invoice['SDT'] ?? '',
    ],
    // Truyền URL động cho ngrok
    'returnUrl' => $baseUrl . $projectPath . '/vnpay_php/vnpay_return.php',
    'ipnUrl' => $baseUrl . $projectPath . '/vnpay_php/vnpay_ipn.php',
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

// Chuyển hướng sang VNPay ngay để người dùng tiếp tục thanh toán
header('Location: ' . $paymentUrl);
exit;