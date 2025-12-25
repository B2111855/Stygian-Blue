<!-- Thuê Trang Phục -->
<?php
include '../components/auth_state_boot.php';
include '../components/header.php';

$tab = $_GET['tab'] ?? 'items';
if ($tab === 'packages') {
	include '../components/goi_trang_phuc.php';
} else {
	include '../components/trangphuc.php';
}

include '../components/footer.php';
include '../components/chat_widget.php';
?>
