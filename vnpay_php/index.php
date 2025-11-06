<?php

use App\Payments\VNPayService;

/** @var VNPayService $service */
$service = require './config.php';
$config = $service->config();

$errors = [];
$generatedUrl = null;
$orderIdDefault = $config->now()->format('YmdHis');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = trim($_POST['order_id'] ?? '');
    $amount = (float) ($_POST['amount'] ?? 0);
    $description = trim($_POST['order_desc'] ?? '');
    $bankCode = trim($_POST['bank_code'] ?? '');
    $language = trim($_POST['language'] ?? $config->defaultLocale);

    if ($orderId === '') {
        $errors[] = 'Vui lòng nhập mã đơn hàng.';
    }

    if ($amount <= 0) {
        $errors[] = 'Số tiền phải lớn hơn 0.';
    }

    if (count($errors) === 0) {
        $params = [
            'orderId' => $orderId,
            'amount' => $amount,
            'orderDescription' => $description !== '' ? $description : 'Thanh toan don hang',
            'bankCode' => $bankCode !== '' ? $bankCode : null,
            'language' => $language,
            'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        ];

        $generatedUrl = $service->buildPaymentUrl($params);

        if (isset($_POST['redirect'])) {
            header('Location: ' . $generatedUrl);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="VNPay sandbox demo">
        <title>Tạo mới đơn hàng VNPAY</title>
        <link href="/vnpay_php/assets/bootstrap.min.css" rel="stylesheet"/>
        <link href="/vnpay_php/assets/jumbotron-narrow.css" rel="stylesheet">
    </head>

    <body>
        <div class="container">
            <div class="header clearfix">
                <h3 class="text-muted">VNPay Sandbox</h3>
            </div>
            <h3 class="mb-3">Tạo mới yêu cầu thanh toán</h3>

            <?php if (count($errors) > 0): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="/vnpay_php/index.php" method="post" class="mb-4">
                <div class="form-group">
                    <label for="order_id">Mã đơn hàng</label>
                    <input class="form-control" id="order_id" name="order_id" type="text" value="<?php echo htmlspecialchars($_POST['order_id'] ?? $orderIdDefault, ENT_QUOTES, 'UTF-8'); ?>" required />
                </div>
                <div class="form-group">
                    <label for="amount">Số tiền (VND)</label>
                    <input class="form-control" id="amount" name="amount" type="number" min="1" step="1" value="<?php echo htmlspecialchars($_POST['amount'] ?? '100000', ENT_QUOTES, 'UTF-8'); ?>" required />
                </div>
                <div class="form-group">
                    <label for="order_desc">Nội dung thanh toán</label>
                    <textarea class="form-control" id="order_desc" name="order_desc" rows="2" placeholder="Ví dụ: Thanh toán đơn hàng SB001"><?php echo htmlspecialchars($_POST['order_desc'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="bank_code">Ngân hàng</label>
                    <select name="bank_code" id="bank_code" class="form-control">
                        <option value="">Không chọn (cổng mặc định)</option>
                        <option value="NCB" <?php echo (($_POST['bank_code'] ?? '') === 'NCB') ? 'selected' : ''; ?>>NCB</option>
                        <option value="AGRIBANK" <?php echo (($_POST['bank_code'] ?? '') === 'AGRIBANK') ? 'selected' : ''; ?>>Agribank</option>
                        <option value="SCB" <?php echo (($_POST['bank_code'] ?? '') === 'SCB') ? 'selected' : ''; ?>>SCB</option>
                        <option value="SACOMBANK" <?php echo (($_POST['bank_code'] ?? '') === 'SACOMBANK') ? 'selected' : ''; ?>>SacomBank</option>
                        <option value="VIETCOMBANK" <?php echo (($_POST['bank_code'] ?? '') === 'VIETCOMBANK') ? 'selected' : ''; ?>>Vietcombank</option>
                        <option value="VIETINBANK" <?php echo (($_POST['bank_code'] ?? '') === 'VIETINBANK') ? 'selected' : ''; ?>>Vietinbank</option>
                        <option value="BIDV" <?php echo (($_POST['bank_code'] ?? '') === 'BIDV') ? 'selected' : ''; ?>>BIDV</option>
                        <option value="TECHCOMBANK" <?php echo (($_POST['bank_code'] ?? '') === 'TECHCOMBANK') ? 'selected' : ''; ?>>Techcombank</option>
                        <option value="MBBANK" <?php echo (($_POST['bank_code'] ?? '') === 'MBBANK') ? 'selected' : ''; ?>>MBBank</option>
                        <option value="ACB" <?php echo (($_POST['bank_code'] ?? '') === 'ACB') ? 'selected' : ''; ?>>ACB</option>
                        <option value="VPBANK" <?php echo (($_POST['bank_code'] ?? '') === 'VPBANK') ? 'selected' : ''; ?>>VPBank</option>
                        <option value="HDBANK" <?php echo (($_POST['bank_code'] ?? '') === 'HDBANK') ? 'selected' : ''; ?>>HDBank</option>
                        <option value="TPBANK" <?php echo (($_POST['bank_code'] ?? '') === 'TPBANK') ? 'selected' : ''; ?>>TPBank</option>
                        <option value="VISA" <?php echo (($_POST['bank_code'] ?? '') === 'VISA') ? 'selected' : ''; ?>>VISA / MASTER</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="language">Ngôn ngữ</label>
                    <select name="language" id="language" class="form-control">
                        <option value="vn" <?php echo (($_POST['language'] ?? $config->defaultLocale) === 'vn') ? 'selected' : ''; ?>>Tiếng Việt</option>
                        <option value="en" <?php echo (($_POST['language'] ?? '') === 'en') ? 'selected' : ''; ?>>English</option>
                    </select>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary" name="preview">Tạo liên kết thanh toán</button>
                    <button type="submit" class="btn btn-success" name="redirect" value="1">Chuyển tới VNPAY ngay</button>
                </div>
            </form>

            <?php if ($generatedUrl !== null): ?>
                <div class="alert alert-info">
                    <p class="mb-1">Liên kết thanh toán đã tạo:</p>
                    <a href="<?php echo htmlspecialchars($generatedUrl, ENT_QUOTES, 'UTF-8'); ?>" class="break-all" target="_blank" rel="noopener"><?php echo htmlspecialchars($generatedUrl, ENT_QUOTES, 'UTF-8'); ?></a>
                </div>
            <?php endif; ?>

            <footer class="footer">
                <p>&copy; VNPay Sandbox <?php echo $config->now()->format('Y'); ?></p>
            </footer>
        </div>
    </body>
</html>