<!--Thông báo-->
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start(); // Khởi động session nếu chưa có
}

if (isset($_SESSION['message'])): ?>
    <!-- Nạp SweetAlert2 nếu chưa -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        window.addEventListener('DOMContentLoaded', function () {
            Swal.fire({
                title: '<?= $_SESSION['message_type'] === 'success' ? 'Thành công!' : 'Thất bại!'; ?>',
                text: '<?= addslashes($_SESSION['message']) ?>',
                icon: '<?= $_SESSION['message_type']; ?>',
                confirmButtonText: 'OK'
            });
        });
    </script>
    <?php
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
endif;
?>
