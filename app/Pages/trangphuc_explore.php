<?php
/**
 * Level 1: Khám Phá Các Nhóm Trang Phục
 * 
 * Mục đích: Hiển thị tất cả nhóm trang phục
 * Người dùng: Chọn nhóm → đi sang Level 2
 * 
 * URL: /app/Pages/trangphuc_explore.php
 * Parameters: (none)
 */

session_start();
require_once __DIR__ . '/../../database/config.php';

// Initialize
$error_message = '';
$selected_branch_id = $_SESSION['selected_branch_id'] ?? 1;
$all_groups = [];

// Get all groups with stats
$query = "
    SELECT 
        ng.ID_NHOM,
        ng.TEN_NHOM,
        ng.MO_TA,
        ng.THU_TU,
        COUNT(DISTINCT tl.ID_LOAI) as type_count,
        COUNT(DISTINCT tp.ID_TRANG_PHUC) as item_count,
        SUM(CASE WHEN tp.TRANG_THAI = 'available' THEN 1 ELSE 0 END) as available_count
    FROM trang_phuc_nhom ng
    LEFT JOIN trang_phuc_loai tl ON tl.ID_NHOM = ng.ID_NHOM
    LEFT JOIN trang_phuc tp ON tp.ID_LOAI = tl.ID_LOAI AND tp.ID_CN = ?
    WHERE ng.TRANG_THAI = 'active'
    GROUP BY ng.ID_NHOM, ng.TEN_NHOM, ng.MO_TA, ng.THU_TU
    ORDER BY ng.THU_TU ASC, ng.TEN_NHOM ASC
";

if ($stmt = $conn->prepare($query)) {
    $stmt->bind_param('i', $selected_branch_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $all_groups = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $error_message = "Lỗi tải nhóm trang phục: " . $conn->error;
    }
    $stmt->close();
} else {
    $error_message = "Lỗi chuẩn bị truy vấn: " . $conn->error;
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Khám Phá Trang Phục - StygianBlue</title>
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
                <nav class="flex items-center gap-8">
                    <a href="/StygianBlue/index.php" class="text-gray-700 hover:text-indigo-600">Trang Chủ</a>
                    <a href="trangphuc_explore.php" class="text-indigo-600 font-medium">Khám Phá</a>
                </nav>
                <button class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Đăng Nhập</button>
            </div>
        </div>
    </header>

    <!-- Page Header -->
    <section class="bg-gradient-to-br from-indigo-50 to-indigo-100 py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-5xl font-bold text-gray-900 mb-4">Khám Phá Trang Phục</h1>
            <p class="text-xl text-gray-700 max-w-2xl">
                Chọn nhóm trang phục phù hợp với dịp lễ, cưới, sự kiện của bạn
            </p>
        </div>
    </section>

    <!-- Error Message -->
    <?php if ($error_message): ?>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-8">
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-800">
                <?= htmlspecialchars($error_message) ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Groups List (Tab/List Style) -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        
        <?php if (!empty($all_groups)): ?>
            <div class="space-y-3">
                
                <?php foreach ($all_groups as $group): ?>
                    <a href="trangphuc_loai_list.php?nhom_id=<?= (int)$group['ID_NHOM'] ?>" 
                       class="block bg-white rounded-2xl shadow-md hover:shadow-lg transition-all p-6 border-l-4 border-indigo-600 hover:bg-indigo-50">
                        
                        <div class="flex items-center justify-between gap-6">
                            
                            <!-- Left: Name & Description -->
                            <div class="flex-grow">
                                <h3 class="text-2xl font-bold text-gray-900 mb-2">
                                    <?= htmlspecialchars($group['TEN_NHOM']) ?>
                                </h3>
                                <p class="text-gray-600 text-sm max-w-2xl">
                                    <?= htmlspecialchars($group['MO_TA'] ?? 'Bộ sưu tập trang phục tuyệt vời') ?>
                                </p>
                            </div>
                            
                            <!-- Right: Stats -->
                            <div class="flex-shrink-0 flex gap-6 text-right min-w-max items-center">
                                
                                <div>
                                    <div class="text-3xl font-bold text-indigo-600">
                                        <?= (int)$group['item_count'] ?>
                                    </div>
                                    <div class="text-xs text-gray-600 mt-1">Trang phục</div>
                                </div>
                                
                                <div>
                                    <div class="text-3xl font-bold text-emerald-600">
                                        <?= (int)$group['available_count'] ?>
                                    </div>
                                    <div class="text-xs text-gray-600 mt-1">Sẵn có</div>
                                </div>
                                
                                <div>
                                    <button class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl transition-colors whitespace-nowrap">
                                        Xem →
                                    </button>
                                </div>
                                
                            </div>
                            
                        </div>
                    </a>
                <?php endforeach; ?>
                
            </div>
        <?php else: ?>
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-8 text-center">
                <p class="text-amber-800 text-lg">Hiện chưa có nhóm trang phục nào.</p>
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
