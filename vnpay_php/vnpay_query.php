<?php

use App\Payments\VNPayService;

/** @var VNPayService $service */
$service = require './config.php';
$config = $service->config();

$queryResponse = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = trim($_POST['orderid'] ?? '');
    $paymentDate = trim($_POST['paymentdate'] ?? '');

    if ($orderId === '') {
        $errors[] = 'Vui lòng nhập mã đơn hàng.';
    }

    if ($paymentDate === '') {
        $errors[] = 'Vui lòng nhập thời điểm thanh toán (định dạng YmdHis).';
    }

    if (count($errors) === 0) {
        $queryUrl = $service->buildQueryUrl([
            'orderId' => $orderId,
            'paymentDate' => $paymentDate,
            'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        ]);

        $client = curl_init($queryUrl);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($client, CURLOPT_HEADER, false);
        $queryResponse = curl_exec($client);
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
        <meta name="description" content="Tra cứu giao dịch VNPAY">
        <title>Tra cứu giao dịch</title>
        <link href="/vnpay_php/assets/bootstrap.min.css" rel="stylesheet"/>
        <link href="/vnpay_php/assets/jumbotron-narrow.css" rel="stylesheet">
    </head>
    <body>
        <div class="container">
            <div class="header clearfix">
                <h3 class="text-muted">Tra cứu giao dịch VNPAY</h3>
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
            <form action="/vnpay_php/vnpay_query.php" method="post" class="mb-4">
                <div class="form-group">
                    <label for="orderid">Mã đơn hàng</label>
                    <input class="form-control" name="orderid" id="orderid" type="text" value="<?php echo htmlspecialchars($_POST['orderid'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required />
                </div>
                <div class="form-group">
                    <label for="paymentdate">Thời điểm thanh toán (YmdHis)</label>
                    <input class="form-control" name="paymentdate" id="paymentdate" type="text" value="<?php echo htmlspecialchars($_POST['paymentdate'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required />
                </div>
                <button type="submit" class="btn btn-primary">Tra cứu</button>
            </form>

            <?php if ($queryResponse !== null): ?>
                <div class="alert alert-info">
                    <strong>Phản hồi từ API:</strong>
                    <pre class="mt-2"><?php echo htmlspecialchars($queryResponse, ENT_QUOTES, 'UTF-8'); ?></pre>
                </div>
            <?php endif; ?>

            <footer class="footer">
                <p>&copy; VNPay Sandbox <?php echo $config->now()->format('Y'); ?></p>
            </footer>
        </div>
    </body>
</html>