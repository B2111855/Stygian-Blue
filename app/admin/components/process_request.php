<?php
include '../../../database/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idYeuCau = isset($_POST['id_yeucau']) ? intval($_POST['id_yeucau']) : 0;
    $hanhDong = $_POST['action'] ?? '';

    if ($idYeuCau > 0 && in_array($hanhDong, ['approve', 'reject'])) {
        $trangThai = $hanhDong === 'approve' ? 'Đã duyệt' : 'Từ chối';

        $stmt = $conn->prepare("UPDATE yeu_cau_thay_doi_lich SET TRANGTHAI = ? WHERE ID_YEUCAU = ?");
        $stmt->bind_param("si", $trangThai, $idYeuCau);
        $stmt->execute();
        $stmt->close();
    }
}

$conn->close();
header("Location: ../admin_dashboard.php?page=assignments");
exit;

