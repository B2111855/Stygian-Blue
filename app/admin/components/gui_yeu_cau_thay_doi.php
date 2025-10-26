<?php
session_start();
include '../../../database/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_lichhen = $_POST['id_lichhen'];
    $noidung = trim($_POST['noidung']);
    $id_tk = $_SESSION['ID_TK'];

    $stmt = $conn->prepare("INSERT INTO yeu_cau_thay_doi_lich (ID_LICHHEN, ID_TK, NOI_DUNG) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $id_lichhen, $id_tk, $noidung);

    if ($stmt->execute()) {
        echo "<script>alert('Đã gửi yêu cầu thành công!'); window.history.back();</script>";
    } else {
        echo "<script>alert('Lỗi khi gửi yêu cầu: " . $stmt->error . "'); window.history.back();</script>";
    }

    $stmt->close();
    $conn->close();
}
?>
