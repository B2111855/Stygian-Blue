<?php
session_start();

require_once '../../../vendor/autoload.php';
require_once '../../../database/config.php';
require_once '../../../middlewares/require_admin.php';
require_once '../../helpers/system_log.php';

use Dotenv\Dotenv;
use App\Payments\VNPayConfig;
use App\Payments\VNPayService;

if (file_exists('../../../.env')) {
    $dotenv = Dotenv::createImmutable('../../../');
    $dotenv->safeLoad();
}

function redirect_with_notice(int $invoiceId, string $message, string $type = 'error'): void {
    $_SESSION['refund_notice'] = $message;
    $_SESSION['refund_notice_type'] = $type;
    header('Location: ../hoa_don_chi_tiet.php?id_hd=' . $invoiceId);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_notice(0, 'Phương thức không hợp lệ.');
}

$idHd = (int)($_POST['id_hd'] ?? 0);
$refundType = $_POST['refund_type'] ?? 'full';
$reason = trim((string)($_POST['reason'] ?? ''));
$amount = (float)($_POST['amount'] ?? 0);

if ($idHd <= 0 || $amount <= 0) {
    redirect_with_notice($idHd, 'Dữ liệu hoàn tiền không hợp lệ.');
}

$invoiceSql = 'SELECT ID_HD, TONG_TIEN, TRANGTHAI_THANHTOAN, PHUONGTHUC_THANHTOAN FROM hoa_don WHERE ID_HD = ? LIMIT 1';
$stmt = $conn->prepare($invoiceSql);
if (!$stmt) {
    redirect_with_notice($idHd, 'Không thể chuẩn bị truy vấn hóa đơn.');
}
$stmt->bind_param('i', $idHd);
$stmt->execute();
$result = $stmt->get_result();
$invoice = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$invoice) {
    redirect_with_notice($idHd, 'Không tìm thấy hóa đơn.');
}

$isPaid = ($invoice['TRANGTHAI_THANHTOAN'] ?? '') === 'Đã thanh toán';
$isVNPay = strtoupper((string)($invoice['PHUONGTHUC_THANHTOAN'] ?? '')) === 'VNPAY';
$total = (float)($invoice['TONG_TIEN'] ?? 0);

if (!$isPaid || !$isVNPay) {
    redirect_with_notice($idHd, 'Chỉ hỗ trợ hoàn tiền cho hóa đơn đã thanh toán qua VNPay.');
}

if ($refundType === 'full') {
    $amount = $total;
}
if ($amount > $total) {
    $amount = $total;
}

$ttSql = "SELECT ID_TTTT, MA_THAM_CHIEU, RAW_CALLBACK, CREATED_AT, UPDATED_AT\n          FROM thanh_toan_truc_tuyen\n          WHERE ID_HD = ? AND GATEWAY = 'vnpay' AND TRANG_THAI = 'success'\n          ORDER BY COALESCE(UPDATED_AT, CREATED_AT) DESC LIMIT 1";
$ttStmt = $conn->prepare($ttSql);
if (!$ttStmt) {
    redirect_with_notice($idHd, 'Không thể truy vấn giao dịch VNPay.');
}
$ttStmt->bind_param('i', $idHd);
$ttStmt->execute();
$ttRes = $ttStmt->get_result();
$ttRow = $ttRes ? $ttRes->fetch_assoc() : null;
$ttStmt->close();

if (!$ttRow) {
    redirect_with_notice($idHd, 'Không tìm thấy giao dịch VNPay thành công cho hóa đơn này.');
}

$orderId = (string)$ttRow['MA_THAM_CHIEU'];
$rawCallback = (string)($ttRow['RAW_CALLBACK'] ?? '');
$transDate = null;

if ($rawCallback !== '') {
    $json = json_decode($rawCallback, true);
    if (is_array($json) && !empty($json['vnp_PayDate'])) {
        $transDate = (string)$json['vnp_PayDate'];
    }
}

if ($transDate === null) {
    // Fallback: use created_at formatted as YmdHis
    $createdAt = $ttRow['CREATED_AT'] ?? null;
    if ($createdAt) {
        $transDate = date('YmdHis', strtotime($createdAt));
    }
}

if ($transDate === null) {
    redirect_with_notice($idHd, 'Thiếu thông tin thời điểm giao dịch để hoàn tiền.');
}

try {
    $config = App\Payments\VNPayConfig::fromEnvironment(array_merge($_ENV, $_SERVER));
    $service = new App\Payments\VNPayService($config);

    $transactionType = $refundType === 'partial' ? '03' : '02';
    $payload = [
        'orderId' => $orderId,
        'transactionType' => $transactionType,
        'amount' => $amount,
        'orderInfo' => 'Refund invoice #' . $idHd . ($reason !== '' ? (' - ' . $reason) : ''),
        'paymentDate' => $transDate,
        'createdBy' => (string)($_SESSION['ID_TK'] ?? 'admin'),
        'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
    ];

    $refundUrl = $service->buildRefundUrl($payload);

    $ch = curl_init($refundUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);

    $parsed = [];
    if (is_string($response)) {
        // VNPay may return query string; parse it
        parse_str($response, $parsed);
        if (!is_array($parsed) || empty($parsed)) {
            // Try JSON format
            $json = json_decode($response, true);
            if (is_array($json)) {
                $parsed = $json;
            }
        }
    }

    $respCode = $parsed['vnp_ResponseCode'] ?? ($parsed['RspCode'] ?? null);

    // Log the attempt
    $logAfter = [
        'request' => $payload,
        'response_raw' => $response,
        'response_parsed' => $parsed,
        'curl_error' => $curlErr,
    ];
    record_system_log($conn, 'refund', 'hoa_don', null, $logAfter, $_SESSION['ID_TK'] ?? null, (string)($_SESSION['ID_QUYEN'] ?? ''));

    if ((string)$respCode === '00') {
        redirect_with_notice($idHd, 'Hoàn tiền VNPay thành công.', 'success');
    }

    $msg = 'Hoàn tiền thất bại' . ($respCode ? (' (mã: ' . $respCode . ')') : '') . '. Vui lòng kiểm tra lại hoặc thử lại sau.';
    redirect_with_notice($idHd, $msg);
} catch (Throwable $th) {
    error_log('[Refund] ' . $th->getMessage());
    redirect_with_notice($idHd, 'Lỗi hệ thống khi xử lý hoàn tiền.');
}
