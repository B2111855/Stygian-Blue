<?php
/**
 * Level 3: Danh Sách Chi Tiết Trang Phục Trong Loại
 * 
 * Mục đích: Hiển thị grid trang phục cụ thể, có filter & sort
 * Người dùng: Chọn trang phục → đi sang Level 4 (Booking)
 * 
 * URL: /app/Pages/trangphuc_list.php?loai_id=2
 * Parameters: loai_id (required), color, size, price, sort
 */

session_start();
require_once __DIR__ . '/../../database/config.php';

// Get parameters
$loai_id = isset($_GET['loai_id']) ? (int)$_GET['loai_id'] : 0;
$nhom_id = isset($_GET['nhom_id']) ? (int)$_GET['nhom_id'] : 0;
$selected_branch_id = $_SESSION['selected_branch_id'] ?? 1;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'name';

if ($loai_id <= 0) {
    header('Location: trangphuc_explore.php');
    exit;
}

// Get type info
$error_message = '';
$type_info = null;
$group_info = null;
$all_items = [];

$query_type = "
    SELECT tl.*, ng.TEN_NHOM, ng.ID_NHOM
    FROM trang_phuc_loai tl
    LEFT JOIN trang_phuc_nhom ng ON ng.ID_NHOM = tl.ID_NHOM
    WHERE tl.ID_LOAI = ?
";

if ($stmt = $conn->prepare($query_type)) {
    $stmt->bind_param('i', $loai_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $type_info = $result->fetch_assoc();
    $stmt->close();
}

if (!$type_info) {
    header('Location: trangphuc_explore.php');
    exit;
}

// Get group info if available
if ($type_info['ID_NHOM']) {
    $query_group = "SELECT * FROM trang_phuc_nhom WHERE ID_NHOM = ?";
    if ($stmt = $conn->prepare($query_group)) {
        $stmt->bind_param('i', $type_info['ID_NHOM']);
        $stmt->execute();
        $result = $stmt->get_result();
        $group_info = $result->fetch_assoc();
        $stmt->close();
    }
}

// Pagination
$items_per_page = 12;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $items_per_page;

// Build query for items with sorting
$order_clause = "ORDER BY tp.TEN ASC";
if ($sort_by === 'price_low') {
    $order_clause = "ORDER BY tp.GIA_THUE ASC, tp.TEN ASC";
} elseif ($sort_by === 'price_high') {
    $order_clause = "ORDER BY tp.GIA_THUE DESC, tp.TEN ASC";
} elseif ($sort_by === 'available') {
    $order_clause = "ORDER BY (tp.TRANG_THAI = 'available') DESC, tp.TEN ASC";
}

// Count total items
$count_query = "
    SELECT COUNT(*) as total
    FROM trang_phuc tp
    WHERE tp.ID_LOAI = ? AND tp.ID_CN = ?
";
$total_items = 0;
if ($stmt = $conn->prepare($count_query)) {
    $stmt->bind_param('ii', $loai_id, $selected_branch_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $count_row = $result->fetch_assoc();
        $total_items = $count_row['total'];
    }
    $stmt->close();
}

$total_pages = ceil($total_items / $items_per_page);
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $offset = ($page - 1) * $items_per_page;
}

$query_items = "
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
    LEFT JOIN trang_phuc_nhom ng ON ng.ID_NHOM = tl.ID_NHOM
    WHERE tp.ID_LOAI = ? AND tp.ID_CN = ?
    $order_clause
    LIMIT ? OFFSET ?
";

if ($stmt = $conn->prepare($query_items)) {
    $stmt->bind_param('iiii', $loai_id, $selected_branch_id, $items_per_page, $offset);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $all_items = $result->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($type_info['TEN_LOAI']) ?> - StygianBlue</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
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
            <div class="flex items-center gap-2 text-sm flex-wrap">
                <a href="/StygianBlue/index.php" class="text-indigo-600 hover:underline">Trang Chủ</a>
                <span class="text-gray-400">/</span>
                <a href="trangphuc_explore.php" class="text-indigo-600 hover:underline">Khám Phá</a>
                <?php if ($group_info): ?>
                    <span class="text-gray-400">/</span>
                    <a href="trangphuc_loai_list.php?nhom_id=<?= (int)$group_info['ID_NHOM'] ?>" class="text-indigo-600 hover:underline">
                        <?= htmlspecialchars($group_info['TEN_NHOM']) ?>
                    </a>
                <?php endif; ?>
                <span class="text-gray-400">/</span>
                <span class="text-gray-900 font-semibold"><?= htmlspecialchars($type_info['TEN_LOAI']) ?></span>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <section class="bg-white border-b border-gray-200 py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-2">
                <?= htmlspecialchars($type_info['TEN_LOAI']) ?>
            </h1>
            <p class="text-lg text-gray-600 mb-6">
                <?= htmlspecialchars($type_info['MO_TA'] ?? '') ?>
            </p>
            
            <!-- Filter & Sort Bar -->
            <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center">
                <span class="text-sm text-gray-600">Sắp xếp:</span>
                <div class="flex gap-2 flex-wrap">
                    <a href="?loai_id=<?= $loai_id ?>&sort=name" 
                       class="px-4 py-2 rounded-lg <?= $sort_by === 'name' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?> transition-colors">
                        A-Z
                    </a>
                    <a href="?loai_id=<?= $loai_id ?>&sort=price_low" 
                       class="px-4 py-2 rounded-lg <?= $sort_by === 'price_low' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?> transition-colors">
                        Giá (Thấp)
                    </a>
                    <a href="?loai_id=<?= $loai_id ?>&sort=price_high" 
                       class="px-4 py-2 rounded-lg <?= $sort_by === 'price_high' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?> transition-colors">
                        Giá (Cao)
                    </a>
                    <a href="?loai_id=<?= $loai_id ?>&sort=available" 
                       class="px-4 py-2 rounded-lg <?= $sort_by === 'available' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?> transition-colors">
                        Sẵn có
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Items Grid (LEVEL 3) -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        
        <?php if (!empty($all_items)): ?>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
                
                <?php foreach ($all_items as $item): ?>
                    <div class="card-hover bg-white rounded-2xl shadow-md overflow-hidden transition-smooth group cursor-pointer"
                         onclick="window.location='trangphuc_datthue.php?item_id=<?= (int)$item['ID_TRANG_PHUC'] ?>'">
                        
                        <!-- Item Image Area -->
                        <div class="relative h-56 bg-gradient-to-br from-indigo-50 to-indigo-100 flex items-center justify-center overflow-hidden">
                            <svg class="w-20 h-20 text-indigo-300" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M5 5a2 2 0 012-2h6a2 2 0 012 2v6h2a2 2 0 012 2v5a2 2 0 01-2 2H3a2 2 0 01-2-2v-5a2 2 0 012-2h2V5z"></path>
                            </svg>
                            
                            <!-- Status Badge -->
                            <?php if ($item['TRANG_THAI'] === 'available'): ?>
                                <div class="absolute top-3 right-3 bg-emerald-600 text-white text-xs font-bold px-3 py-1 rounded-full">
                                    Sẵn Có
                                </div>
                            <?php elseif ($item['TRANG_THAI'] === 'rented'): ?>
                                <div class="absolute top-3 right-3 bg-amber-600 text-white text-xs font-bold px-3 py-1 rounded-full">
                                    Đang Thuê
                                </div>
                            <?php else: ?>
                                <div class="absolute top-3 right-3 bg-gray-600 text-white text-xs font-bold px-3 py-1 rounded-full">
                                    Bảo Trì
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Item Content -->
                        <div class="p-4 space-y-3 flex flex-col h-full">
                            
                            <!-- Breadcrumb (Type/Group) -->
                            <div class="text-xs text-gray-500">
                                <?php if ($group_info): ?>
                                    <?= htmlspecialchars($group_info['TEN_NHOM']) ?> 
                                    <span class="text-gray-400">/ </span>
                                <?php endif; ?>
                                <?= htmlspecialchars($item['TEN_LOAI'] ?? '') ?>
                            </div>
                            
                            <!-- Item Name -->
                            <h4 class="font-semibold text-gray-900 leading-tight line-clamp-2">
                                <?= htmlspecialchars($item['TEN']) ?>
                            </h4>
                            
                            <!-- Item Details -->
                            <div class="flex gap-2 text-sm text-gray-600 flex-wrap">
                                <?php if (!empty($item['MAU_SAC'])): ?>
                                    <span class="px-2 py-1 bg-gray-100 rounded text-xs">
                                        <?= htmlspecialchars($item['MAU_SAC']) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($item['SIZE'])): ?>
                                    <span class="px-2 py-1 bg-gray-100 rounded text-xs">
                                        Size <?= htmlspecialchars($item['SIZE']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Price (Flex Grow) -->
                            <div class="pt-2 border-t border-gray-200 mt-auto">
                                <div class="text-sm text-gray-600">Giá</div>
                                <div class="text-2xl font-bold text-indigo-600">
                                    ₫<?= number_format((int)($item['GIA_THUE'] ?? 0), 0, ',', '.') ?>
                                </div>
                                <div class="text-xs text-gray-600">/ngày</div>
                            </div>
                            
                            <!-- CTA Button -->
                            <button class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 rounded-lg transition-colors text-sm mt-3">
                                Xem Chi Tiết
                            </button>
                            
                        </div>
                    </div>
                <?php endforeach; ?>
                
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="flex justify-center items-center gap-2 mt-12 pb-8">
                    
                    <!-- Previous Page -->
                    <?php if ($page > 1): ?>
                        <a href="?loai_id=<?= $loai_id ?>&sort=<?= htmlspecialchars($sort_by) ?>&page=<?= $page - 1 ?>" 
                           class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-700 font-medium transition-colors">
                            ← Trước
                        </a>
                    <?php else: ?>
                        <button disabled class="px-4 py-2 bg-gray-100 border border-gray-200 rounded-lg text-gray-400 cursor-not-allowed">
                            ← Trước
                        </button>
                    <?php endif; ?>
                    
                    <!-- Page Numbers -->
                    <div class="flex gap-1">
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <?php if ($i === $page): ?>
                                <button class="px-3 py-2 bg-indigo-600 text-white rounded-lg font-medium">
                                    <?= $i ?>
                                </button>
                            <?php else: ?>
                                <a href="?loai_id=<?= $loai_id ?>&sort=<?= htmlspecialchars($sort_by) ?>&page=<?= $i ?>" 
                                   class="px-3 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-700 transition-colors">
                                    <?= $i ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    
                    <!-- Next Page -->
                    <?php if ($page < $total_pages): ?>
                        <a href="?loai_id=<?= $loai_id ?>&sort=<?= htmlspecialchars($sort_by) ?>&page=<?= $page + 1 ?>" 
                           class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-700 font-medium transition-colors">
                            Sau →
                        </a>
                    <?php else: ?>
                        <button disabled class="px-4 py-2 bg-gray-100 border border-gray-200 rounded-lg text-gray-400 cursor-not-allowed">
                            Sau →
                        </button>
                    <?php endif; ?>
                    
                </div>
                
                <!-- Page Info -->
                <div class="text-center text-sm text-gray-600 pb-4">
                    Trang <?= $page ?> của <?= $total_pages ?> 
                    (<?= $total_items ?> trang phục)
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-8 text-center">
                <p class="text-amber-800 text-lg">
                    Loại "<?= htmlspecialchars($type_info['TEN_LOAI']) ?>" hiện chưa có trang phục nào.
                </p>
            </div>
        <?php endif; ?>
        
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-300 py-8 mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <p>&copy; 2025 StygianBlue. Tất cả quyền được bảo lưu.</p>
        </div>
    </footer>

</body>
</html>
