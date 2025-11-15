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
$validation = $service->validateIpn($data);
$response = ['RspCode' => '00', 'Message' => 'Confirm Success'];

if (($validation['RspCode'] ?? '') !== '00') {
    $response = [
        'RspCode' => $validation['RspCode'] ?? '97',
        'Message' => $validation['Message'] ?? 'Invalid signature',
    ];
} else {
    $payload = $validation['payload'] ?? [];
    $orderId = $data['vnp_TxnRef'] ?? ($payload['vnp_TxnRef'] ?? null);

    if ($orderId === null) {
        $response = ['RspCode' => '91', 'Message' => 'Missing order reference'];
    } else {
        $transactionSql = "
            SELECT ID_TTTT, ID_HD, SO_TIEN, TRANG_THAI
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
                $currentStatus = trim((string) ($transactionRow['TRANG_THAI'] ?? ''));
                if (in_array($currentStatus, ['success', 'failed'], true)) {
                    $response = ['RspCode' => '02', 'Message' => 'Order already confirmed'];
                } else {
                    $storedAmount = isset($transactionRow['SO_TIEN']) ? (float) $transactionRow['SO_TIEN'] : 0.0;
                    $recordAmount = isset($payload['vnp_Amount']) ? ((float) $payload['vnp_Amount']) / 100 : 0.0;

                    if ($recordAmount > 0 && abs($storedAmount - $recordAmount) > 0.01) {
                        $response = ['RspCode' => '04', 'Message' => 'Amount mismatch'];
                    } else {
                        $newStatus = $validation['isSuccess'] ? 'success' : 'failed';
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
                            $invoiceId = (int) $transactionRow['ID_HD'];
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

                        $response = ['RspCode' => '00', 'Message' => 'Confirm Success'];
                    }
                }
            }
        }
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($response, JSON_UNESCAPED_UNICODE);
$conn->close();