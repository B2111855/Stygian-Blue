<?php
// File delete_assignment.php
// FIXED: Using prepared statement to prevent SQL injection

include '../../../database/config.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']); // Validate input
    $redirectUrl = "http://localhost:8080/stygianblue/app/admin/admin_dashboard.php?page=assignments";

    // Xóa phân công sử dụng prepared statement
    $delete_query = "DELETE FROM phan_cong_nhan_vien WHERE ID_LICHHEN = ?";
    $stmt = mysqli_prepare($conn, $delete_query);
    
    if (!$stmt) {
        echo "<script>
            alert('Lỗi: Không thể chuẩn bị câu lệnh SQL');
            window.location.href = '$redirectUrl';
        </script>";
        exit;
    }

    mysqli_stmt_bind_param($stmt, "i", $id);
    
    if (mysqli_stmt_execute($stmt)) {
        // Kiểm tra có dòng nào bị xóa không
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            echo "<script>
                alert('Xóa phân công thành công!');
                window.location.href = '$redirectUrl';
            </script>";
        } else {
            echo "<script>
                alert('Không tìm thấy phân công để xóa');
                window.location.href = '$redirectUrl';
            </script>";
        }
    } else {
        // Lỗi khi thực thi
        $error = mysqli_error($conn);
        echo "<script>
            alert('Lỗi khi xóa: " . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . "');
            window.location.href = '$redirectUrl';
        </script>";
    }
    
    mysqli_stmt_close($stmt);
} else {
    // Không có ID được cung cấp
    header("Location: http://localhost:8080/stygianblue/app/admin/admin_dashboard.php?page=assignments");
    exit;
}
?>
