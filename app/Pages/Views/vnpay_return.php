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
require_once '../components/auth_state_boot.php';

$config = VNPayConfig::fromEnvironment(array_merge($_ENV, $_SERVER));
$service = new VNPayService($config);
$result = $service->interpretResponse($_GET);

$transactionRow = null;
$invoiceId = null;
$statusLabel = $result->isSuccessful() ? 'success' : 'error';
$message = $result->message();
$orderId = $result->orderId();
$amount = $result->amount();

if ($orderId !== null) {
    $transactionSql = "
        SELECT ID_TTTT, ID_HD, TRANG_THAI
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
    }
}

if ($transactionRow) {
    $invoiceId = (int) $transactionRow['ID_HD'];
    $newStatus = $result->isSuccessful() ? 'success' : 'failed';
    $callbackJson = json_encode($_GET, JSON_UNESCAPED_UNICODE);

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

    if ($result->isSuccessful()) {
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
        $_SESSION['payment_notice'] = 'Thanh toán VNPAY thành công cho hóa đơn #' . $invoiceId . '.';
        $_SESSION['payment_notice_type'] = 'success';
    } else {
        $_SESSION['payment_notice'] = 'Thanh toán VNPAY không thành công. Vui lòng thử lại hoặc chọn phương thức khác.';
        $_SESSION['payment_notice_type'] = 'error';
    }
} else {
    $_SESSION['payment_notice'] = 'Không tìm thấy giao dịch VNPAY tương ứng. Vui lòng kiểm tra lại.';
    $_SESSION['payment_notice_type'] = 'error';
}

include '../components/header.php';
?>

<main class="max-w-3xl mx-auto px-4 py-12">
    <section class="bg-white shadow-xl rounded-2xl p-8">
        <h1 class="text-3xl font-bold text-indigo-700 mb-6 text-center">Kết quả thanh toán VNPAY</h1>

        <div class="space-y-4">
            <p class="text-base text-gray-700">
                <span class="font-semibold">Trạng thái:</span>
                <span class="ml-2 <?php echo $statusLabel === 'success' ? 'text-green-600' : 'text-red-600'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </span>
            </p>

            <?php if ($orderId !== null): ?>
            <p class="text-base text-gray-700">
                <span class="font-semibold">Mã tham chiếu:</span>
                <span class="ml-2 text-gray-900"><?php echo htmlspecialchars($orderId); ?></span>
            </p>
            <?php endif; ?>

            <?php if ($invoiceId !== null): ?>
            <p class="text-base text-gray-700">
                <span class="font-semibold">Hóa đơn liên quan:</span>
                <span class="ml-2 text-gray-900">#<?php echo $invoiceId; ?></span>
            </p>
            <?php endif; ?>

            <?php if ($amount !== null): ?>
            <p class="text-base text-gray-700">
                <span class="font-semibold">Số tiền:</span>
                <span class="ml-2 text-gray-900"><?php echo number_format($amount, 0, ',', '.'); ?> VNĐ</span>
            </p>
            <?php endif; ?>

            <div class="mt-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <a href="./hoa_don.php" class="inline-flex justify-center items-center px-5 py-2.5 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition">
                    Quay lại danh sách hóa đơn
                </a>
                <?php if ($result->isSuccessful()): ?>
                <span class="text-sm text-green-600">Cảm ơn bạn đã thanh toán qua VNPAY.</span>
                <?php else: ?>
                <span class="text-sm text-gray-600">Nếu cần hỗ trợ, vui lòng liên hệ đội ngũ chăm sóc khách hàng.</span>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<?php
include '../components/footer.php';
include '../components/chat_widget.php';
$conn->close();
?>