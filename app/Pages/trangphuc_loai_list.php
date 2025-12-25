<?php
/**
 * Level 2: Danh Sách Loại Trang Phục Trong Nhóm
 * 
 * Mục đích: Hiển thị tất cả loại trong nhóm đã chọn
 * Người dùng: Chọn loại → đi sang Level 3
 * 
 * URL: /app/Pages/trangphuc_loai_list.php?nhom_id=1
 * Parameters: nhom_id (required)
 */

session_start();
require_once __DIR__ . '/../../database/config.php';

// Get nhom_id from URL
$nhom_id = isset($_GET['nhom_id']) ? (int)$_GET['nhom_id'] : 0;
$selected_branch_id = $_SESSION['selected_branch_id'] ?? 1;

if ($nhom_id <= 0) {
    header('Location: trangphuc_explore.php');
    exit;
}

// Get group info
$error_message = '';
$group_info = null;
$all_types = [];

$query_group = "SELECT * FROM trang_phuc_nhom WHERE ID_NHOM = ?";
if ($stmt = $conn->prepare($query_group)) {
    $stmt->bind_param('i', $nhom_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $group_info = $result->fetch_assoc();
    $stmt->close();
}

if (!$group_info) {
    header('Location: trangphuc_explore.php');
    exit;
}

// Get all types in this group
$query_types = "
    SELECT 
        tl.ID_LOAI,
        tl.TEN_LOAI,
        tl.MO_TA,
        tl.THU_TU,
        COUNT(DISTINCT tp.ID_TRANG_PHUC) as total_items,
        SUM(CASE WHEN tp.TRANG_THAI = 'available' THEN 1 ELSE 0 END) as available_items
    FROM trang_phuc_loai tl
    LEFT JOIN trang_phuc tp ON tp.ID_LOAI = tl.ID_LOAI AND tp.ID_CN = ?
    WHERE tl.ID_NHOM = ?
    GROUP BY tl.ID_LOAI, tl.TEN_LOAI, tl.MO_TA, tl.THU_TU
    ORDER BY tl.THU_TU ASC, tl.TEN_LOAI ASC
";

if ($stmt = $conn->prepare($query_types)) {
    $stmt->bind_param('ii', $selected_branch_id, $nhom_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $all_types = $result->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($group_info['TEN_NHOM']) ?> - StygianBlue</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
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
            <div class="flex items-center gap-2 text-sm">
                <a href="/StygianBlue/index.php" class="text-indigo-600 hover:underline">Trang Chủ</a>
                <span class="text-gray-400">/</span>
                <a href="trangphuc_explore.php" class="text-indigo-600 hover:underline">Khám Phá</a>
                <span class="text-gray-400">/</span>
                <span class="text-gray-900 font-semibold"><?= htmlspecialchars($group_info['TEN_NHOM']) ?></span>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <section class="bg-white border-b border-gray-200 py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-2">
                <?= htmlspecialchars($group_info['TEN_NHOM']) ?>
            </h1>
            <p class="text-lg text-gray-600">
                <?= htmlspecialchars($group_info['MO_TA'] ?? '') ?>
            </p>
        </div>
    </section>

    <!-- Types Section -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        
        <?php if (!empty($all_types)): ?>
            <div class="space-y-6">
                
                <?php foreach ($all_types as $type): ?>
                    <div class="bg-white rounded-2xl shadow-md overflow-hidden transition-smooth">
                        
                        <!-- Type Header (Clickable) -->
                        <div class="card-hover cursor-pointer px-6 py-6 border-b border-gray-200 hover:bg-gray-50"
                             onclick="window.location='trangphuc_list.php?loai_id=<?= (int)$type['ID_LOAI'] ?>'">
                            <div class="flex items-center justify-between gap-4">
                                
                                <div class="flex-grow">
                                    <h3 class="text-2xl font-bold text-gray-900 mb-2">
                                        <?= htmlspecialchars($type['TEN_LOAI']) ?>
                                    </h3>
                                    <p class="text-sm text-gray-600">
                                        <?= htmlspecialchars($type['MO_TA'] ?? '') ?>
                                    </p>
                                </div>
                                
                                <!-- Type Stats (Right Side) -->
                                <div class="flex gap-6 text-right flex-shrink-0">
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-indigo-600">
                                            <?= (int)$type['total_items'] ?>
                                        </div>
                                        <div class="text-xs text-gray-600 mt-1">Trang phục</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-emerald-600">
                                            <?= (int)$type['available_items'] ?>
                                        </div>
                                        <div class="text-xs text-gray-600 mt-1">Sẵn có</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Type Action Button -->
                        <div class="px-6 py-4 bg-gray-50 text-right">
                            <a href="trangphuc_list.php?loai_id=<?= (int)$type['ID_LOAI'] ?>"
                               class="inline-block px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg transition-colors">
                                Xem <?= htmlspecialchars($type['TEN_LOAI']) ?> →
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
                
            </div>
        <?php else: ?>
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-8 text-center">
                <p class="text-amber-800 text-lg">
                    Nhóm "<?= htmlspecialchars($group_info['TEN_NHOM']) ?>" hiện chưa có loại trang phục nào.
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
