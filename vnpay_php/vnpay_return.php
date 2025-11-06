<?php

use App\Payments\VNPayService;

/** @var VNPayService $service */
$service = require './config.php';
$config = $service->config();
$result = $service->interpretResponse($_GET);

$payload = $result->payload;
$amount = isset($payload['vnp_Amount']) ? number_format(((float) $payload['vnp_Amount']) / 100, 0, '.', ',') : '0';
$orderInfo = $payload['vnp_OrderInfo'] ?? '';
$transactionNo = $payload['vnp_TransactionNo'] ?? '';
$bankCode = $payload['vnp_BankCode'] ?? '';
$payDate = $payload['vnp_PayDate'] ?? '';
?>
<!DOCTYPE html>
<html lang="vi">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Kết quả thanh toán VNPAY">
        <title>Kết quả thanh toán</title>
        <link href="/vnpay_php/assets/bootstrap.min.css" rel="stylesheet"/>
        <link href="/vnpay_php/assets/jumbotron-narrow.css" rel="stylesheet">
    </head>
    <body>
        <div class="container">
            <div class="header clearfix">
                <h3 class="text-muted">Kết quả thanh toán VNPAY</h3>
            </div>
            <div class="table-responsive">
                <div class="form-group">
                    <label>Mã đơn hàng:</label>
                    <span><?php echo htmlspecialchars($result->orderId() ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="form-group">
                    <label>Số tiền:</label>
                    <span><?php echo htmlspecialchars($amount, ENT_QUOTES, 'UTF-8'); ?> VND</span>
                </div>
                <div class="form-group">
                    <label>Nội dung thanh toán:</label>
                    <span><?php echo htmlspecialchars($orderInfo, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="form-group">
                    <label>Mã phản hồi:</label>
                    <span><?php echo htmlspecialchars($payload['vnp_ResponseCode'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="form-group">
                    <label>Mã giao dịch tại VNPAY:</label>
                    <span><?php echo htmlspecialchars($transactionNo, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="form-group">
                    <label>Mã ngân hàng:</label>
                    <span><?php echo htmlspecialchars($bankCode, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="form-group">
                    <label>Thời gian thanh toán:</label>
                    <span><?php echo htmlspecialchars($payDate, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="form-group">
                    <label>Kết quả:</label>
                    <span class="font-weight-bold <?php echo $result->isSuccessful() ? 'text-success' : 'text-danger'; ?>"><?php echo htmlspecialchars($result->message(), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
            <footer class="footer">
                <p>&copy; VNPay Sandbox <?php echo $config->now()->format('Y'); ?></p>
            </footer>
        </div>
    </body>
</html>