<?php
/**
 * Danh Sách Gói Trang Phục
 * 
 * Mục đích: Hiển thị gói dịch vụ có pagination
 * URL: /app/Pages/trangphuc_goi.php
 * Parameters: page (optional), sort (optional)
 */

session_start();
require_once __DIR__ . '/../../database/config.php';

// Pagination
$items_per_page = 8;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $items_per_page;
$selected_branch_id = $_SESSION['selected_branch_id'] ?? 1;

// Count total packages
$count_query = "
    SELECT COUNT(*) as total
    FROM goi_trang_phuc_master gm
    WHERE gm.ID_CN_OWNER = ?
";
$total_packages = 0;
if ($stmt = $conn->prepare($count_query)) {
    $stmt->bind_param('i', $selected_branch_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $count_row = $result->fetch_assoc();
        $total_packages = $count_row['total'];
    }
    $stmt->close();
}

$total_pages = ceil($total_packages / $items_per_page);
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $offset = ($page - 1) * $items_per_page;
}

// Get packages with item count
$query_packages = "
    SELECT 
        gm.ID_GOI,
        gm.TEN_GOI,
        gm.MO_TA,
        gm.GIA_GOI,
        COUNT(DISTINCT gc.ID_TRANG_PHUC) as item_count
    FROM goi_trang_phuc_master gm
    LEFT JOIN goi_trang_phuc_chi_tiet gc ON gc.ID_GOI = gm.ID_GOI
    WHERE gm.ID_CN_OWNER = ?
    GROUP BY gm.ID_GOI, gm.TEN_GOI, gm.MO_TA, gm.GIA_GOI
    ORDER BY gm.TEN_GOI ASC
    LIMIT ? OFFSET ?
";

$all_packages = [];
if ($stmt = $conn->prepare($query_packages)) {
    $stmt->bind_param('iii', $selected_branch_id, $items_per_page, $offset);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $all_packages = $result->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gói Trang Phục - StygianBlue</title>
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
            <div class="flex items-center gap-2 text-sm">
                <a href="/StygianBlue/index.php" class="text-indigo-600 hover:underline">Trang Chủ</a>
                <span class="text-gray-400">/</span>
                <span class="text-gray-900 font-semibold">Gói Trang Phục</span>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <section class="bg-gradient-to-r from-indigo-600 to-indigo-800 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-4xl font-bold mb-4">Gói Trang Phục</h1>
            <p class="text-lg text-indigo-100">
                Chọn gói dịch vụ phù hợp với nhu cầu của bạn
            </p>
        </div>
    </section>

    <!-- Packages Grid -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        
        <?php if (!empty($all_packages)): ?>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
                
                <?php foreach ($all_packages as $package): ?>
                    <a href="trangphuc_datthue.php?package_id=<?= (int)$package['ID_GOI'] ?>"
                       class="card-hover bg-white rounded-2xl shadow-md overflow-hidden transition-smooth group cursor-pointer flex flex-col h-full">
                        
                        <!-- Package Image Area -->
                        <div class="relative h-48 bg-gradient-to-br from-emerald-50 to-emerald-100 flex items-center justify-center overflow-hidden">
                            <svg class="w-16 h-16 text-emerald-300" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.797l.074.335h10.574l.074-.335A1 1 0 0116.847 2H18a1 1 0 011 1v2a1 1 0 01-.293.707L12.414 15h5.293a1 1 0 010 2h-16a1 1 0 010-2h5.293L2.293 5.707A1 1 0 012 5V3z"></path>
                            </svg>
                            
                            <!-- Popular Badge -->
                            <div class="absolute top-3 right-3 bg-emerald-600 text-white text-xs font-bold px-3 py-1 rounded-full">
                                <?= (int)$package['item_count'] ?> mục
                            </div>
                        </div>
                        
                        <!-- Package Content -->
                        <div class="p-6 space-y-4 flex flex-col flex-grow">
                            
                            <!-- Name -->
                            <h3 class="text-xl font-bold text-gray-900 leading-tight line-clamp-2">
                                <?= htmlspecialchars($package['TEN_GOI']) ?>
                            </h3>
                            
                            <!-- Description -->
                            <p class="text-sm text-gray-600 line-clamp-3 flex-grow">
                                <?= htmlspecialchars($package['MO_TA'] ?? 'Gói dịch vụ trang phục') ?>
                            </p>
                            
                            <!-- Price -->
                            <div class="border-t border-gray-200 pt-4">
                                <div class="text-sm text-gray-600 mb-1">Giá gói</div>
                                <div class="text-3xl font-bold text-emerald-600">
                                    ₫<?= number_format((int)$package['GIA_GOI'], 0, ',', '.') ?>
                                </div>
                            </div>
                            
                            <!-- CTA Button -->
                            <button class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 rounded-xl transition-colors mt-auto">
                                Xem Chi Tiết →
                            </button>
                            
                        </div>
                    </a>
                <?php endforeach; ?>
                
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="flex justify-center items-center gap-2 mt-16 pb-8">
                    
                    <!-- Previous Page -->
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>" 
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
                                <button class="px-3 py-2 bg-emerald-600 text-white rounded-lg font-medium">
                                    <?= $i ?>
                                </button>
                            <?php else: ?>
                                <a href="?page=<?= $i ?>" 
                                   class="px-3 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-700 transition-colors">
                                    <?= $i ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    
                    <!-- Next Page -->
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>" 
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
                    (<?= $total_packages ?> gói)
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-8 text-center">
                <p class="text-amber-800 text-lg">Hiện chưa có gói dịch vụ nào.</p>
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
