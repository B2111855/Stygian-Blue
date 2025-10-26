<?php
// File delete_assignment.php

include '../../../database/config.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $redirectUrl = "http://localhost:8080/stygianblue/app/admin/admin_dashboard.php?page=assignments";

    // Xóa phân công
    $delete_query = "DELETE FROM phan_cong_nhan_vien WHERE ID_LICHHEN = '$id'";
    if (mysqli_query($conn, $delete_query)) {
        // Chuyển hướng với thông báo thành công
        echo "<script>
            alert('Xóa phân công thành công!');
            window.location.href = '$redirectUrl';
        </script>";
    } else {
        // Chuyển hướng với thông báo lỗi
        $error = mysqli_error($conn);
        echo "<script>
            alert('Lỗi khi xóa: $error');
            window.location.href = '$redirectUrl';
        </script>";
    }
}
?>
