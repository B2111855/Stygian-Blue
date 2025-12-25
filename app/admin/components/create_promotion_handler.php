<?php
/**
 * Handler tạo khuyến mãi từ trang chính quản lý gói
 * (admin_dashboard.php?page=packages)
 * 
 * Dependencies:
 * - $conn (MySQLi connection)
 * - pkg_csrf_token(), pkg_require_csrf(), pkg_table_exists() functions
 * - $errorMessage, $successMessage globals
 */

// Ensure functions are defined
if (!function_exists('pkg_table_exists')) {
  function pkg_table_exists(mysqli $conn, string $table): bool {
    $tbl = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '".$tbl."'");
    return $res && $res->num_rows > 0;
  }
}

if (!function_exists('pkg_csrf_token')) {
  function pkg_csrf_token(): string {
    return $_SESSION['csrf_token'] ?? '';
  }
}

if (!function_exists('pkg_require_csrf')) {
  function pkg_require_csrf(): bool {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return true;
    $posted = $_POST['csrf'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';
    return ($posted !== '' && $stored !== '' && hash_equals($stored,$posted));
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promo_create_new'])) {
  // CSRF validation
  if (!pkg_require_csrf()) {
    $errorMessage = 'CSRF không hợp lệ.';
  } elseif (!pkg_table_exists($conn, 'khuyen_mai')) {
    $errorMessage = 'Bảng khuyến mãi chưa tạo.';
  } else {
    try {
      $tenKM = trim($_POST['TEN_KM'] ?? '');
      $kieuKM = (string)($_POST['KIEU_KM'] ?? '');
      $giaTriKM = (float)($_POST['GIA_TRI'] ?? 0);
      $ngayBatDau = ($_POST['NGAY_BAT_DAU'] ?? '') ?: null;
      $ngayKetThuc = ($_POST['NGAY_KET_THUC'] ?? '') ?: null;
      $trangThai = isset($_POST['TRANG_THAI']) ? 'active' : 'inactive';
      
      // Validation
      if (!$tenKM || $tenKM === '') {
        $errorMessage = 'Vui lòng nhập tên khuyến mãi.';
      } elseif (!in_array($kieuKM, ['percent', 'fixed'])) {
        $errorMessage = 'Vui lòng chọn loại khuyến mãi hợp lệ.';
      } elseif ($giaTriKM <= 0) {
        $errorMessage = 'Giá trị khuyến mãi phải lớn hơn 0.';
      } elseif (!$ngayBatDau || !$ngayKetThuc) {
        $errorMessage = 'Vui lòng chọn ngày bắt đầu và kết thúc.';
      } elseif (strtotime($ngayBatDau) >= strtotime($ngayKetThuc)) {
        $errorMessage = 'Ngày kết thúc phải sau ngày bắt đầu.';
      } else {
        // INSERT vào khuyen_mai
        $stmt = $conn->prepare('INSERT INTO khuyen_mai (TEN_KM, KIEU_KM, GIA_TRI, NGAY_BAT_DAU, NGAY_KET_THUC, TRANG_THAI) VALUES (?,?,?,?,?,?)');
        $stmt->bind_param('ssdsss', $tenKM, $kieuKM, $giaTriKM, $ngayBatDau, $ngayKetThuc, $trangThai);
        
        if ($stmt->execute()) {
          $newPromoId = $stmt->insert_id;
          $successMessage = 'Đã tạo khuyến mãi mới (#'.$newPromoId.'). Bây giờ bạn có thể gán nó vào các gói dịch vụ.';
          
          // Log system
          if (function_exists('record_system_log')) {
            record_system_log($conn, 'PROMO_CREATE', 'promo:'.$newPromoId, null, [
              'TEN_KM' => $tenKM,
              'KIEU_KM' => $kieuKM,
              'GIA_TRI' => $giaTriKM
            ]);
          }
        } else {
          $errorMessage = 'Không tạo được khuyến mãi: '.$conn->error;
        }
      }
    } catch (\Throwable $e) {
      $errorMessage = 'Lỗi tạo khuyến mãi: '.$e->getMessage();
    }
  }
}
?>
