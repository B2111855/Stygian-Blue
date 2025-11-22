<?php
include '../../../database/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idYeuCau = isset($_POST['id_yeucau']) ? intval($_POST['id_yeucau']) : 0;
    $hanhDong = $_POST['action'] ?? '';

    if ($idYeuCau > 0 && in_array($hanhDong, ['approve', 'reject'])) {
        $trangThai = $hanhDong === 'approve' ? 'Đã duyệt' : 'Từ chối';

        // Lấy ID_LICHHEN và ID_TK từ yêu cầu đổi lịch
        $infoStmt = $conn->prepare("SELECT ID_LICHHEN, ID_TK FROM yeu_cau_thay_doi_lich WHERE ID_YEUCAU = ? LIMIT 1");
        $infoStmt->bind_param("i", $idYeuCau);
        $infoStmt->execute();
        $infoStmt->bind_result($idLichHen, $idTk);
        $found = $infoStmt->fetch();
        $infoStmt->close();

        // Cập nhật trạng thái yêu cầu
        $stmt = $conn->prepare("UPDATE yeu_cau_thay_doi_lich SET TRANGTHAI = ? WHERE ID_YEUCAU = ?");
        $stmt->bind_param("si", $trangThai, $idYeuCau);
        $stmt->execute();
        $stmt->close();

        // Nếu duyệt, xóa phân công cũ để admin có thể phân công lại
        if ($trangThai === 'Đã duyệt' && $found) {
            $delStmt = $conn->prepare("DELETE FROM phan_cong_nhan_vien WHERE ID_LICHHEN = ? AND ID_TK = ?");
            if ($delStmt) {
                $delStmt->bind_param("is", $idLichHen, $idTk);
                $delStmt->execute();
                $delStmt->close();
            }
        }
    }
}

$conn->close();
header("Location: ../admin_dashboard.php?page=assignments");
exit;

