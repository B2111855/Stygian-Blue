<?php
include '../../../database/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_lichhen = $_POST['id_lichhen'] ?? null;
    $id_tk = $_SESSION['ID_TK'] ?? null;

    if ($id_lichhen && $id_tk) {
        $stmt = $conn->prepare("DELETE FROM yeu_cau_thay_doi_lich WHERE ID_LICHHEN = ? AND ID_TK = ?");
        $stmt->bind_param("is", $id_lichhen, $id_tk);
        $stmt->execute();
    }
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}
?>
