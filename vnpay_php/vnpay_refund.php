<?php

use App\Payments\VNPayService;

/** @var VNPayService $service */
$service = require './config.php';
$config = $service->config();

$refundResponse = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = trim($_POST['orderid'] ?? '');
    $transactionType = trim($_POST['trantype'] ?? '02');
    $amount = (float) ($_POST['amount'] ?? 0);
    $paymentDate = trim($_POST['paymentdate'] ?? '');
    $createdBy = trim($_POST['mail'] ?? '');

    if ($orderId === '') {
        $errors[] = 'Vui lòng nhập mã đơn hàng cần hoàn.';
    }

    if ($amount <= 0) {
        $errors[] = 'Số tiền hoàn phải lớn hơn 0.';
    }

    if ($paymentDate === '') {
        $errors[] = 'Vui lòng nhập thời điểm thanh toán ban đầu (YmdHis).';
    }

    if (count($errors) === 0) {
        $refundUrl = $service->buildRefundUrl([
            'orderId' => $orderId,
            'transactionType' => $transactionType,
            'amount' => $amount,
            'paymentDate' => $paymentDate,
            'createdBy' => $createdBy,
            'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        ]);

        $client = curl_init($refundUrl);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($client, CURLOPT_HEADER, false);
        $refundResponse = curl_exec($client);
        curl_close($client);
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Hoàn tiền giao dịch VNPAY">
        <title>Hoàn tiền VNPAY</title>
        <link href="/vnpay_php/assets/bootstrap.min.css" rel="stylesheet"/>
        <link href="/vnpay_php/assets/jumbotron-narrow.css" rel="stylesheet">
    </head>
    <body>
        <div class="container">
            <div class="header clearfix">
                <h3 class="text-muted">Hoàn tiền giao dịch VNPAY</h3>
            </div>
            <?php if (count($errors) > 0): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <form action="/vnpay_php/vnpay_refund.php" method="post" class="mb-4">
                <div class="form-group">
                    <label for="orderid">Mã đơn hàng</label>
                    <input class="form-control" name="orderid" id="orderid" type="text" value="<?php echo htmlspecialchars($_POST['orderid'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required />
                </div>
                <div class="form-group">
                    <label for="trantype">Kiểu hoàn tiền</label>
                    <select name="trantype" id="trantype" class="form-control">
                        <option value="02" <?php echo (($_POST['trantype'] ?? '02') === '02') ? 'selected' : ''; ?>>Hoàn toàn phần</option>
                        <option value="03" <?php echo (($_POST['trantype'] ?? '') === '03') ? 'selected' : ''; ?>>Hoàn một phần</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="amount">Số tiền hoàn (VND)</label>
                    <input class="form-control" name="amount" id="amount" type="number" min="1" value="<?php echo htmlspecialchars($_POST['amount'] ?? '10000', ENT_QUOTES, 'UTF-8'); ?>" required />
                </div>
                <div class="form-group">
                    <label for="paymentdate">Thời điểm thanh toán ban đầu (YmdHis)</label>
                    <input class="form-control" name="paymentdate" id="paymentdate" type="text" value="<?php echo htmlspecialchars($_POST['paymentdate'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required />
                </div>
                <div class="form-group">
                    <label for="mail">Email người khởi tạo</label>
                    <input class="form-control" name="mail" id="mail" type="email" value="<?php echo htmlspecialchars($_POST['mail'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                </div>
                <button type="submit" class="btn btn-warning">Gửi yêu cầu hoàn tiền</button>
            </form>

            <?php if ($refundResponse !== null): ?>
                <div class="alert alert-info">
                    <strong>Phản hồi từ API:</strong>
                    <pre class="mt-2"><?php echo htmlspecialchars($refundResponse, ENT_QUOTES, 'UTF-8'); ?></pre>
                </div>
            <?php endif; ?>

            <footer class="footer">
                <p>&copy; VNPay Sandbox <?php echo $config->now()->format('Y'); ?></p>
            </footer>
        </div>
    </body>
</html>