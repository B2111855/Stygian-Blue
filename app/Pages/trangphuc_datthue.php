<?php
/**
 * Level 4: Unified Booking Form (Đặt Thuê Trang Phục)
 * 
 * Mục đích: Unified form cho tất cả scenarios (single item, type, package)
 * Người dùng: Điền thông tin thuê → xác nhận → thanh toán
 * 
 * URL: /app/Pages/trangphuc_datthue.php?item_id=15
 *      /app/Pages/trangphuc_datthue.php?loai_id=2
 *      /app/Pages/trangphuc_datthue.php?package_id=5
 * 
 * Parameters: item_id OR loai_id OR package_id
 */

session_start();
require_once __DIR__ . '/../../database/config.php';

// Get parameters
$item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
$loai_id = isset($_GET['loai_id']) ? (int)$_GET['loai_id'] : 0;
$package_id = isset($_GET['package_id']) ? (int)$_GET['package_id'] : 0;
$selected_branch_id = $_SESSION['selected_branch_id'] ?? 1;

// Determine mode
$mode = 'item'; // default
$booking_data = [];
$total_price = 0;
$error_message = '';

if ($item_id > 0) {
    $mode = 'item';
    // Get single item
    $query = "
        SELECT 
            tp.ID_TRANG_PHUC,
            tp.TEN,
            tp.MAU_SAC,
            tp.SIZE,
            tp.GIA_THUE,
            tp.TRANG_THAI,
            tl.TEN_LOAI,
            ng.TEN_NHOM
        FROM trang_phuc tp
        LEFT JOIN trang_phuc_loai tl ON tl.ID_LOAI = tp.ID_LOAI
        LEFT JOIN trang_phuc_nhom ng ON ng.ID_NHOM = tp.ID_NHOM
        WHERE tp.ID_TRANG_PHUC = ? AND tp.ID_CN = ?
    ";
    
    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param('ii', $item_id, $selected_branch_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($item = $result->fetch_assoc()) {
            $booking_data[] = $item;
            $total_price = (int)$item['GIA_THUE'];
        } else {
            $error_message = "Trang phục không tồn tại!";
        }
        $stmt->close();
    }
    
} elseif ($loai_id > 0) {
    $mode = 'type';
    // Get all items in type
    $query = "
        SELECT 
            tp.ID_TRANG_PHUC,
            tp.TEN,
            tp.MAU_SAC,
            tp.SIZE,
            tp.GIA_THUE,
            tp.TRANG_THAI,
            tl.TEN_LOAI,
            ng.TEN_NHOM
        FROM trang_phuc tp
        LEFT JOIN trang_phuc_loai tl ON tl.ID_LOAI = tp.ID_LOAI
        LEFT JOIN trang_phuc_nhom ng ON ng.ID_NHOM = tp.ID_NHOM
        WHERE tp.ID_LOAI = ? AND tp.ID_CN = ? AND tp.TRANG_THAI = 'available'
        ORDER BY tp.TEN ASC
        LIMIT 1
    ";
    
    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param('ii', $loai_id, $selected_branch_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($item = $result->fetch_assoc()) {
            $booking_data[] = $item;
            $total_price = (int)$item['GIA_THUE'];
        } else {
            $error_message = "Loại trang phục không tồn tại!";
        }
        $stmt->close();
    }
    
} elseif ($package_id > 0) {
    $mode = 'package';
    // Get package items
    $query = "
        SELECT 
            tp.ID_TRANG_PHUC,
            tp.TEN,
            tp.MAU_SAC,
            tp.SIZE,
            tp.GIA_THUE,
            tp.TRANG_THAI,
            gc.BUOC_BAT_BUOC,
            gm.GIA_GOI
        FROM goi_trang_phuc_master gm
        LEFT JOIN goi_trang_phuc_chi_tiet gc ON gc.ID_GOI = gm.ID_GOI
        LEFT JOIN trang_phuc tp ON tp.ID_TRANG_PHUC = gc.ID_TRANG_PHUC
        WHERE gm.ID_GOI = ? AND gm.ID_CN = ?
        ORDER BY gc.BUOC_BAT_BUOC DESC, tp.TEN ASC
    ";
    
    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param('ii', $package_id, $selected_branch_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($item = $result->fetch_assoc()) {
            if ($item['ID_TRANG_PHUC']) {
                $booking_data[] = $item;
            } else {
                $total_price = (int)$item['GIA_GOI'];
            }
        }
        $stmt->close();
    }
} else {
    $error_message = "Vui lòng chọn trang phục hoặc gói!";
}

// Default dates
$default_from_date = date('Y-m-d', strtotime('tomorrow'));
$default_to_date = date('Y-m-d', strtotime('+3 days'));

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt Thuê Trang Phục - StygianBlue</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .transition-smooth {
            transition: all 0.3s ease;
        }
    </style>
</head>
<body class="bg-gray-50">
    
    <!-- Header -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <a href="/StygianBlue/index.php" class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center">
                        <span class="text-white font-bold">S</span>
                    </div>
                    <span class="font-bold text-lg text-gray-900">StygianBlue</span>
                </a>
                <button class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Đăng Nhập</button>
            </div>
        </div>
    </header>

    <!-- Breadcrumb -->
    <nav class="bg-white border-b border-gray-200 py-4">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center gap-2 text-sm">
                <a href="/StygianBlue/index.php" class="text-indigo-600 hover:underline">Trang Chủ</a>
                <span class="text-gray-400">/</span>
                <span class="text-gray-900 font-semibold">Đặt Thuê</span>
            </div>
        </div>
    </nav>

    <!-- Page Container -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        
        <!-- Error Alert -->
        <?php if ($error_message): ?>
            <div class="mb-8 bg-red-50 border border-red-200 rounded-lg p-6 text-red-800">
                <?= htmlspecialchars($error_message) ?>
                <br>
                <a href="/StygianBlue/index.php" class="text-red-600 hover:underline font-semibold">← Quay lại trang chủ</a>
            </div>
        <?php else: ?>
        
        <div class="grid lg:grid-cols-3 gap-8">
            
            <!-- Left: Booking Form -->
            <div class="lg:col-span-2 space-y-8">
                
                <!-- Step 1: Chọn Trang Phục -->
                <div class="bg-white rounded-2xl shadow-md p-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">Trang Phục Đã Chọn</h2>
                    
                    <?php if (!empty($booking_data)): ?>
                        <div class="space-y-4">
                            <?php foreach ($booking_data as $item): ?>
                                <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h4 class="font-semibold text-gray-900"><?= htmlspecialchars($item['TEN']) ?></h4>
                                            <p class="text-sm text-gray-600">
                                                <?php if (isset($item['TEN_NHOM'])): ?>
                                                    <?= htmlspecialchars($item['TEN_NHOM']) ?> / 
                                                <?php endif; ?>
                                                <?= htmlspecialchars($item['TEN_LOAI'] ?? '') ?>
                                            </p>
                                            <div class="flex gap-2 mt-2 text-xs">
                                                <?php if (!empty($item['MAU_SAC'])): ?>
                                                    <span class="px-2 py-1 bg-white rounded">Màu: <?= htmlspecialchars($item['MAU_SAC']) ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($item['SIZE'])): ?>
                                                    <span class="px-2 py-1 bg-white rounded">Size: <?= htmlspecialchars($item['SIZE']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-2xl font-bold text-indigo-600">
                                                ₫<?= number_format((int)($item['GIA_THUE'] ?? 0), 0, ',', '.') ?>
                                            </div>
                                            <div class="text-xs text-gray-600">/ngày</div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Step 2: Chọn Thời Gian -->
                <div class="bg-white rounded-2xl shadow-md p-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">Chọn Thời Gian Thuê</h2>
                    
                    <form id="bookingForm" class="space-y-6">
                        
                        <div class="grid sm:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-900 mb-2">Từ Ngày</label>
                                <input 
                                    type="date" 
                                    name="from_date" 
                                    value="<?= $default_from_date ?>"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-600"
                                    required
                                    min="<?= date('Y-m-d') ?>"
                                >
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-900 mb-2">Đến Ngày</label>
                                <input 
                                    type="date" 
                                    name="to_date" 
                                    value="<?= $default_to_date ?>"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-600"
                                    required
                                    min="<?= date('Y-m-d') ?>"
                                >
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-900 mb-2">Số Lượng</label>
                            <input 
                                type="number" 
                                name="qty" 
                                value="1"
                                min="1"
                                max="5"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-600"
                                required
                            >
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-900 mb-2">Ghi Chú (Tùy Chọn)</label>
                            <textarea 
                                name="notes"
                                rows="3"
                                placeholder="Ghi chú thêm nếu có..."
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-600"
                            ></textarea>
                        </div>
                        
                    </form>
                </div>
                
                <!-- Step 3: Thông Tin Khách Hàng -->
                <div class="bg-white rounded-2xl shadow-md p-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">Thông Tin Khách Hàng</h2>
                    
                    <form id="customerForm" class="space-y-4">
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-900 mb-2">Họ Tên</label>
                            <input 
                                type="text" 
                                name="customer_name"
                                placeholder="Nhập họ tên..."
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-600"
                                required
                            >
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-900 mb-2">Email</label>
                            <input 
                                type="email" 
                                name="customer_email"
                                placeholder="Nhập email..."
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-600"
                                required
                            >
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-900 mb-2">Điện Thoại</label>
                            <input 
                                type="tel" 
                                name="customer_phone"
                                placeholder="Nhập số điện thoại..."
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-600"
                                required
                            >
                        </div>
                        
                        <div class="pt-4 border-t border-gray-200">
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="checkbox" name="agree_terms" class="mt-1" required>
                                <span class="text-sm text-gray-600">
                                    Tôi đồng ý với <a href="#" class="text-indigo-600 hover:underline">điều khoản &amp; điều kiện</a> của StygianBlue
                                </span>
                            </label>
                        </div>
                        
                    </form>
                </div>
                
            </div>
            
            <!-- Right: Booking Summary (Sticky) -->
            <div class="lg:col-span-1">
                
                <div class="bg-white rounded-2xl shadow-md p-8 sticky top-24 space-y-6">
                    <h2 class="text-2xl font-bold text-gray-900">Tóm Tắt Đơn Hàng</h2>
                    
                    <!-- Price Breakdown -->
                    <div class="space-y-4">
                        <div class="flex justify-between text-gray-600">
                            <span>Giá trang phục (1 ngày):</span>
                            <span>₫<?= number_format($total_price, 0, ',', '.') ?></span>
                        </div>
                        <div class="flex justify-between text-gray-600">
                            <span>Số ngày thuê:</span>
                            <span id="rental_days">3</span>
                        </div>
                        <div class="flex justify-between text-gray-600">
                            <span>Số lượng:</span>
                            <span id="rental_qty">1</span>
                        </div>
                        <div class="border-t border-gray-200 pt-4">
                            <div class="flex justify-between font-bold text-lg text-gray-900 mb-4">
                                <span>Tổng tiền:</span>
                                <span id="total_amount">₫<?= number_format($total_price * 3, 0, ',', '.') ?></span>
                            </div>
                        </div>
                        <div class="bg-indigo-50 rounded-lg p-4">
                            <div class="flex justify-between text-indigo-900">
                                <span>Tiền cọc (30%):</span>
                                <span id="deposit_amount">₫<?= number_format((int)($total_price * 3 * 0.3), 0, ',', '.') ?></span>
                            </div>
                            <div class="text-xs text-indigo-700 mt-2">
                                Còn lại thanh toán khi nhận hàng
                            </div>
                        </div>
                    </div>
                    
                    <!-- CTA Buttons -->
                    <div class="space-y-3 pt-4">
                        <button 
                            onclick="document.getElementById('bookingForm').submit()"
                            class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-4 rounded-xl transition-colors">
                            Xác Nhận & Thanh Toán
                        </button>
                        <a href="/StygianBlue/index.php" 
                           class="block w-full text-center px-6 py-4 border-2 border-gray-300 text-gray-700 font-semibold rounded-xl hover:bg-gray-50 transition-colors">
                            Tiếp Tục Mua Sắm
                        </a>
                    </div>
                    
                </div>
                
            </div>
            
        </div>
        
        <?php endif; ?>
        
    </div>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-300 py-8 mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <p>&copy; 2025 StygianBlue. Tất cả quyền được bảo lưu.</p>
        </div>
    </footer>

    <script>
        // Calculate rental days and update summary
        function updateSummary() {
            const fromDate = new Date(document.querySelector('input[name="from_date"]').value);
            const toDate = new Date(document.querySelector('input[name="to_date"]').value);
            const qty = parseInt(document.querySelector('input[name="qty"]').value) || 1;
            
            const days = Math.max(1, Math.ceil((toDate - fromDate) / (1000 * 60 * 60 * 24)));
            const totalPrice = <?= $total_price ?>;
            const totalAmount = totalPrice * days * qty;
            const deposit = Math.round(totalAmount * 0.3);
            
            document.getElementById('rental_days').textContent = days;
            document.getElementById('rental_qty').textContent = qty;
            document.getElementById('total_amount').textContent = '₫' + totalAmount.toLocaleString('vi-VN');
            document.getElementById('deposit_amount').textContent = '₫' + deposit.toLocaleString('vi-VN');
        }
        
        // Listen to date/qty changes
        document.querySelectorAll('input[name="from_date"], input[name="to_date"], input[name="qty"]').forEach(el => {
            el.addEventListener('change', updateSummary);
        });
        
        // Initial calculation
        updateSummary();
    </script>

</body>
</html>
