<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../database/config.php';
if (file_exists(__DIR__ . '/../../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../../vendor/autoload.php';
}

$packageId = isset($_GET['package_id']) ? (int)$_GET['package_id'] : 0;
if ($packageId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Thiếu package_id']);
    exit;
}

try {
    $sql = "SELECT tp.ID_TRANG_PHUC, tp.TEN, tp.GIA_THUE, gtp.DISCOUNT_PERCENT, gtp.BAT_BUOC, COALESCE(gtp.THU_TU, tp.ID_TRANG_PHUC) AS THU_TU
            FROM GOI_TRANG_PHUC gtp
            JOIN TRANG_PHUC tp ON tp.ID_TRANG_PHUC = gtp.ID_TRANG_PHUC
            WHERE gtp.ID_GOI = ?
            ORDER BY THU_TU";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Lỗi truy vấn: ' . $conn->error);
    }
    $stmt->bind_param('i', $packageId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    echo json_encode([
        'ok' => true,
        'package_id' => $packageId,
        'costumes' => $rows,
    ]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Lỗi hệ thống', 'message' => $e->getMessage()]);
}
