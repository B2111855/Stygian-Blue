<?php
require_once '../../../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Payments\VNPayConfig;
use App\Payments\VNPayService;

if (file_exists('../../../.env')) {
    $dotenv = Dotenv::createImmutable('../../../');
    $dotenv->safeLoad();
}

require_once '../../../database/config.php';

$config = VNPayConfig::fromEnvironment(array_merge($_ENV, $_SERVER));
$service = new VNPayService($config);

$data = $_GET;
$response = $service->validateIpn($data);
$orderId = $data['vnp_TxnRef'] ?? null;

if ($orderId === null) {
    $response = ['RspCode' => '91', 'Message' => 'Missing order reference'];
} elseif ($response['RspCode'] === '00') {
    $transactionSql = "
        SELECT ID_TTTT, ID_HD, SO_TIEN
        FROM thanh_toan_truc_tuyen
        WHERE MA_THAM_CHIEU = ? AND GATEWAY = 'vnpay'
        LIMIT 1
    ";
    $transactionStmt = $conn->prepare($transactionSql);
    if ($transactionStmt) {
        $transactionStmt->bind_param('s', $orderId);
        $transactionStmt->execute();
        $transactionResult = $transactionStmt->get_result();
        $transactionRow = $transactionResult ? $transactionResult->fetch_assoc() : null;
        $transactionStmt->close();

        if (!$transactionRow) {
            $response = ['RspCode' => '01', 'Message' => 'Order not found'];
        } else {
            $invoiceId = (int) $transactionRow['ID_HD'];
            $newStatus = 'failed';
            $recordAmount = isset($data['vnp_Amount']) ? ((float) $data['vnp_Amount']) / 100 : 0;
            if ($recordAmount > 0 && isset($transactionRow['SO_TIEN']) && (float) $transactionRow['SO_TIEN'] !== (float) $recordAmount) {
                $response = ['RspCode' => '04', 'Message' => 'Amount mismatch'];
            } else {
                if (($data['vnp_ResponseCode'] ?? '') === '00' && (($data['vnp_TransactionStatus'] ?? '') === '' || ($data['vnp_TransactionStatus'] ?? '') === '00')) {
                    $newStatus = 'success';
                }

                $callbackJson = json_encode($data, JSON_UNESCAPED_UNICODE);
                $updateSql = "
                    UPDATE thanh_toan_truc_tuyen
                    SET TRANG_THAI = ?, RAW_CALLBACK = ?, UPDATED_AT = NOW()
                    WHERE ID_TTTT = ?
                ";
                $updateStmt = $conn->prepare($updateSql);
                if ($updateStmt) {
                    $updateStmt->bind_param('ssi', $newStatus, $callbackJson, $transactionRow['ID_TTTT']);
                    $updateStmt->execute();
                    $updateStmt->close();
                }

                if ($newStatus === 'success') {
                    $invoiceUpdateSql = "
                        UPDATE hoa_don
                        SET TRANGTHAI_THANHTOAN = 'Đã thanh toán',
                            PHUONGTHUC_THANHTOAN = 'VNPAY',
                            YEU_CAU_XAC_NHAN = 0
                        WHERE ID_HD = ?
                    ";
                    $invoiceUpdateStmt = $conn->prepare($invoiceUpdateSql);
                    if ($invoiceUpdateStmt) {
                        $invoiceUpdateStmt->bind_param('i', $invoiceId);
                        $invoiceUpdateStmt->execute();
                        $invoiceUpdateStmt->close();
                    }
                }
            }
        }
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($response, JSON_UNESCAPED_UNICODE);
$conn->close();