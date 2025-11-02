<?php
// chitiet.php (phiên bản đồng bộ UI với goi_chitiet.php)

include '../../../database/config.php'; // chỉnh path nếu cần

// Helpers
function formatVND($n) {
    if ($n === null) return "Liên hệ";
    return number_format($n, 0, ',', '.') . "₫";
}
function formatDateTimeVN($str) {
    if (!$str) return null;
    $d = new DateTime($str);
    return $d->format('d/m/Y H:i');
}

// Lấy id dịch vụ từ URL
$idDv = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch thông tin dịch vụ
$dv = null;
$giaHienHanh = null;
$hinhAnhList = [];
$ratingAvg = null;
$feedbacks = [];

if ($idDv > 0) {

    // 1. Thông tin dịch vụ
    $sqlDv = "
        SELECT 
            dv.ID_DV,
            dv.TEN_DV,
            dv.MOTA_DV,
            dv.IMAGE AS FALLBACK_IMAGE,
            dv.THOI_GIAN
        FROM dich_vu dv
        WHERE dv.ID_DV = ?
        LIMIT 1
    ";
    $stmtDv = $conn->prepare($sqlDv);
    $stmtDv->bind_param("i", $idDv);
    $stmtDv->execute();
    $resDv = $stmtDv->get_result();
    $dv = $resDv->fetch_assoc();
    $stmtDv->close();

    if ($dv) {
        // 2. Lấy ảnh cover + gallery
        // cover ưu tiên IS_COVER=1
        $sqlImgs = "
            SELECT 
                ID_HA,
                URL,
                ALT_TEXT,
                IS_COVER
            FROM dich_vu_hinh_anh
            WHERE ID_DV = ?
            AND IS_ACTIVE = 1
            ORDER BY IS_COVER DESC, THU_TU ASC, ID_HA ASC
        ";
        $stmtImg = $conn->prepare($sqlImgs);
        $stmtImg->bind_param("i", $idDv);
        $stmtImg->execute();
        $resImg = $stmtImg->get_result();
        while ($rowImg = $resImg->fetch_assoc()) {
            $hinhAnhList[] = $rowImg;
        }
        $stmtImg->close();

        // 3. Giá áp dụng mới nhất (don_gia_dich_vu có thể nhiều dòng theo thời gian)
        // rule: lấy DON_GIA theo NGAY_GIO mới nhất cho ID_DV này
        $sqlGia = "
            SELECT dgdv.DON_GIA
            FROM don_gia_dich_vu dgdv
            WHERE dgdv.ID_DV = ?
            ORDER BY dgdv.NGAY_GIO DESC
            LIMIT 1
        ";
        $stmtGia = $conn->prepare($sqlGia);
        $stmtGia->bind_param("i", $idDv);
        $stmtGia->execute();
        $resGia = $stmtGia->get_result();
        $giaRow = $resGia->fetch_assoc();
        if ($giaRow) {
            $giaHienHanh = $giaRow['DON_GIA'];
        }
        $stmtGia->close();

        // 4. Đánh giá trung bình
        $sqlRating = "
            SELECT AVG(XEP_HANG_DV) AS avg_rating
            FROM phan_hoi_cua_khach_hang
            WHERE ID_DV = ?
        ";
        $stmtRating = $conn->prepare($sqlRating);
        $stmtRating->bind_param("i", $idDv);
        $stmtRating->execute();
        $resRating = $stmtRating->get_result();
        $ratingRow = $resRating->fetch_assoc();
        if ($ratingRow && $ratingRow['avg_rating'] !== null) {
            $ratingAvg = round($ratingRow['avg_rating'], 1);
        }
        $stmtRating->close();

        // 5. Feedback gần nhất
        $sqlFb = "
            SELECT ID_TK, NOI_DUNG, XEP_HANG_DV, NGAY_GUI
            FROM phan_hoi_cua_khach_hang
            WHERE ID_DV = ?
            ORDER BY NGAY_GUI DESC
            LIMIT 3
        ";
        $stmtFb = $conn->prepare($sqlFb);
        $stmtFb->bind_param("i", $idDv);
        $stmtFb->execute();
        $resFb = $stmtFb->get_result();
        while ($fbRow = $resFb->fetch_assoc()) {
            $feedbacks[] = $fbRow;
        }
        $stmtFb->close();
    }
}

// Xác định ảnh hero
$heroImg = null;
if (!empty($hinhAnhList)) {
    // lấy ảnh đầu tiên trong list (do đã sort để cover lên trước)
    $heroImg = $hinhAnhList[0]['URL'];
} elseif ($dv && !empty($dv['FALLBACK_IMAGE'])) {
    $heroImg = $dv['FALLBACK_IMAGE'];
} else {
    $heroImg = 'public/images/placeholder.jpg';
}

// Tính trạng thái hiển thị (dịch vụ lẻ mình tạm cho luôn là "Có sẵn đặt lịch")
$trangThaiLabel = 'Có sẵn để đặt lịch';
$trangThaiColor = 'text-green-600 bg-green-50';

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>
        <?php if ($dv): ?>
            <?= htmlspecialchars($dv['TEN_DV']) ?> • Stygian Blue Studio
        <?php else: ?>
            Dịch vụ • Stygian Blue Studio
        <?php endif; ?>
    </title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .hero-overlay {
            background: radial-gradient(circle at 30% 30%, rgba(255,255,255,.15) 0%, rgba(0,0,0,0) 60%);
        }
    </style>
</head>
<body class="bg-white text-slate-800 antialiased">

<?php if (!$idDv || !$dv): ?>
    <!-- Lỗi: không có id, hoặc không tìm thấy dịch vụ -->
    <section class="min-h-screen flex items-center justify-center bg-slate-100 px-6">
        <div class="bg-white shadow-sm border border-slate-200 rounded-2xl max-w-md w-full p-8 text-center">
            <div class="text-4xl mb-4">😕</div>
            <?php if (!$idDv): ?>
                <h1 class="text-xl font-semibold text-slate-800 mb-2">
                    Dịch vụ không hợp lệ
                </h1>
                <p class="text-slate-500 text-sm leading-relaxed">
                    Thiếu tham số 
                    <code class="font-mono text-slate-700 bg-slate-100 px-1 py-0.5 rounded">id</code> 
                    trên URL.<br/>
                    Ví dụ: 
                    <span class="font-mono text-slate-700 bg-slate-100 px-1 py-0.5 rounded">
                        chitiet.php?id=1
                    </span>
                </p>
            <?php else: ?>
                <h1 class="text-xl font-semibold text-slate-800 mb-2">
                    Không tìm thấy dịch vụ
                </h1>
                <p class="text-slate-500 text-sm leading-relaxed">
                    Dịch vụ (ID <?= htmlspecialchars($idDv) ?>) không tồn tại hoặc đã bị gỡ.
                </p>
            <?php endif; ?>

            <a href="dichvu.php"
               class="inline-block mt-6 bg-sky-500 hover:bg-sky-600 text-white font-semibold py-2 px-4 rounded-lg text-sm shadow">
                Quay về danh sách dịch vụ
            </a>
        </div>
    </section>

<?php else: ?>

    <!-- HERO giống kiểu gói -->
    <section class="relative w-full h-[50vh] flex items-center justify-center bg-slate-900 text-white overflow-hidden">
        <img 
            src="<?= htmlspecialchars($heroImg) ?>" 
            alt="<?= htmlspecialchars($dv['TEN_DV']) ?>"
            class="absolute inset-0 w-full h-full object-cover brightness-[.45]"
        />
        <div class="absolute inset-0 hero-overlay mix-blend-screen"></div>

        <div class="relative max-w-3xl text-center px-6">
            <div class="inline-block text-xs font-medium px-3 py-1 rounded-full mb-4 <?= $trangThaiColor ?>">
                <?= htmlspecialchars($trangThaiLabel) ?>
            </div>

            <h1 class="text-4xl font-bold tracking-tight mb-4">
                <?= htmlspecialchars($dv['TEN_DV']) ?>
            </h1>

            <?php if ($ratingAvg): ?>
            <div class="mt-2 flex items-center justify-center gap-2 text-sm text-yellow-300 font-medium">
                <span>★ <?= $ratingAvg ?>/5.0</span>
                <span class="text-slate-200/70">| Đánh giá thực từ khách hàng</span>
            </div>
            <?php endif; ?>

            <a href="#booking" 
               class="mt-8 inline-block bg-sky-500 hover:bg-sky-600 text-white font-semibold px-6 py-3 rounded-xl shadow-lg shadow-sky-900/40 transition">
                Đặt lịch dịch vụ này
            </a>
        </div>
    </section>

    <!-- MAIN CONTENT (2 cột giống gói) -->
    <main class="max-w-6xl mx-auto px-4 lg:px-8 py-10 lg:py-14">
        <div class="flex flex-col lg:flex-row gap-10">

            <!-- Cột trái -->
            <section class="flex-1 min-w-0">
                <!-- Mô tả dịch vụ -->
                <header class="mb-8">
                    <h2 class="text-2xl font-semibold text-slate-800 flex flex-wrap items-end gap-3">
                        Mô tả dịch vụ
                        <span class="text-sm font-normal text-slate-500">
                            (thời lượng khoảng <?= (int)$dv['THOI_GIAN'] ?> phút)
                        </span>
                    </h2>
                    <p class="text-slate-600 text-sm leading-relaxed mt-2 max-w-2xl">
                        <?= nl2br(htmlspecialchars($dv['MOTA_DV'])) ?>
                    </p>
                </header>

                <!-- Gallery ảnh -->
                <section class="mb-12">
                    <h3 class="text-xl font-semibold text-slate-800 mb-4">
                        Hình ảnh mẫu
                    </h3>

                    <?php if (empty($hinhAnhList)): ?>
                        <div class="p-6 bg-slate-50 border border-dashed border-slate-300 rounded-xl text-center text-slate-500">
                            Chưa có thư viện hình mẫu cho dịch vụ này.
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <?php foreach ($hinhAnhList as $ha): ?>
                                <div class="rounded-xl overflow-hidden bg-slate-100 border border-slate-200 shadow-sm">
                                    <img 
                                        src="<?= htmlspecialchars($ha['URL']) ?>" 
                                        alt="<?= htmlspecialchars($ha['ALT_TEXT'] ?? $dv['TEN_DV']) ?>"
                                        class="w-full h-40 object-cover"
                                    />
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- Feedback -->
                <section class="mt-10">
                    <h3 class="text-xl font-semibold text-slate-800 mb-4">
                        Phản hồi từ khách hàng
                    </h3>

                    <?php if (empty($feedbacks)): ?>
                        <p class="text-sm text-slate-500 bg-slate-50 border border-slate-200 rounded-xl p-4">
                            Chưa có đánh giá công khai cho dịch vụ này.
                        </p>
                    <?php else: ?>
                        <div class="grid md:grid-cols-2 gap-6">
                            <?php foreach ($feedbacks as $fb): ?>
                            <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                                <p class="text-slate-700 text-sm leading-relaxed italic">
                                    “<?= htmlspecialchars($fb['NOI_DUNG']) ?>”
                                </p>
                                <div class="flex items-center justify-between mt-3 text-xs text-slate-500">
                                    <span class="font-medium">
                                        <?= htmlspecialchars($fb['ID_TK']) ?>
                                    </span>
                                    <span>
                                        ⭐ <?= (int)$fb['XEP_HANG_DV'] ?>/5
                                    </span>
                                </div>
                                <?php if (!empty($fb['NGAY_GUI'])): ?>
                                <div class="text-[11px] text-right text-slate-400 mt-1">
                                    <?= formatDateTimeVN($fb['NGAY_GUI']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </section>

            <!-- Sidebar phải -->
            <aside class="lg:w-[320px] flex-shrink-0">
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-6 shadow-sm sticky top-6">
                    <h3 class="text-lg font-semibold text-slate-800">
                        Thông tin dịch vụ
                    </h3>

                    <div class="mt-4 text-sm text-slate-700 space-y-3">
                        <div class="flex items-start justify-between">
                            <span class="text-slate-500">Giá dịch vụ</span>
                            <span class="font-semibold text-sky-600">
                                <?= formatVND($giaHienHanh) ?>
                            </span>
                        </div>

                        <div class="flex items-start justify-between">
                            <span class="text-slate-500">Thời lượng dự kiến</span>
                            <span class="font-medium text-slate-800">
                                ~<?= (int)$dv['THOI_GIAN'] ?> phút
                            </span>
                        </div>

                        <?php if ($ratingAvg): ?>
                        <div class="flex items-start justify-between">
                            <span class="text-slate-500">Đánh giá trung bình</span>
                            <span class="font-medium text-yellow-600">
                                ★ <?= $ratingAvg ?>/5.0
                            </span>
                        </div>
                        <?php endif; ?>

                        <div class="text-[13px]">
                            <div class="text-slate-500">Tình trạng</div>
                            <div class="font-medium <?= $trangThaiColor ?> inline-block px-2 py-1 rounded-lg text-[12px]">
                                <?= htmlspecialchars($trangThaiLabel) ?>
                            </div>
                        </div>
                    </div>

                    <a id="booking"
                       href="datlich.php?id_dv=<?= urlencode($dv['ID_DV']) ?>"
                       class="block w-full text-center mt-8 bg-sky-500 hover:bg-sky-600 text-white font-semibold py-3 rounded-xl shadow-lg shadow-sky-900/20 transition">
                        Đặt lịch dịch vụ này
                    </a>

                    <button
                        class="block w-full text-center mt-3 text-sky-600 hover:text-sky-700 text-sm font-medium underline underline-offset-2">
                        Nhờ tư vấn viên hỗ trợ trước
                    </button>
                </div>

                <div class="mt-6 text-[13px] text-slate-500 leading-relaxed bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
                    <div class="font-semibold text-slate-700 mb-1">
                        Cam kết Stygian Blue
                    </div>
                    <ul class="list-disc pl-5 space-y-1">
                        <li>Hình ảnh bàn giao độ phân giải cao.</li>
                        <li>Hậu kỳ chỉnh màu tiêu chuẩn studio.</li>
                        <li>Bảo mật thông tin cá nhân tuyệt đối.</li>
                    </ul>
                </div>
            </aside>
        </div>
    </main>

    <!-- Footer giống gói -->
    <footer class="text-center text-[12px] text-slate-400 py-10">
        © <?= date('Y') ?> Stygian Blue Studio. All rights reserved.
    </footer>

<?php endif; ?>

</body>
</html>
