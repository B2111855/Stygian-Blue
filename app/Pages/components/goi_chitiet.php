<?php
// goi_chitiet.php
// Trang chi tiết gói dịch vụ (combo) cho Stygian Blue Studio
// Phiên bản: lấy thiết bị trực tiếp từ DB thật

include '../../../database/config.php'; // chỉnh path nếu khác dự án của bạn

// ===== Helpers =====
function formatVND($n) {
    return number_format($n, 0, ',', '.') . "₫";
}
function formatDate($str) {
    if (!$str) return null;
    $d = new DateTime($str);
    return $d->format('d/m/Y');
}

// Lấy ID gói từ URL (?id_goi=...)
$idGoi = isset($_GET['id_goi']) ? intval($_GET['id_goi']) : 0;

// Chuẩn bị biến
$goi = null;
$dichVuList = [];
$thietBiList = [];
$tongGiaGoi = 0;
$tongThoiGian = 0;
$ratingAvg = null;
$feedbacks = [];

// Nếu không có id_goi -> mình sẽ render UI báo lỗi sau
if ($idGoi > 0) {
    // 1. Lấy thông tin gói dịch vụ
    $sqlGoi = "
        SELECT 
            g.ID_GOI,
            g.TEN_GOI,
            g.MO_TA,
            g.HINH_ANH,
            g.HIEU_LUC_TU,
            g.HIEU_LUC_DEN,
            g.TRANG_THAI
        FROM goi_dich_vu g
        WHERE g.ID_GOI = ?
        LIMIT 1
    ";
    $stmtGoi = $conn->prepare($sqlGoi);
    $stmtGoi->bind_param("i", $idGoi);
    $stmtGoi->execute();
    $resultGoi = $stmtGoi->get_result();
    $goi = $resultGoi->fetch_assoc();
    $stmtGoi->close();

    if ($goi) {
        // 2. Lấy danh sách dịch vụ con trong gói
        $sqlDVTrongGoi = "
            SELECT 
                gct.ID_DV,
                gct.SO_LUONG,
                gct.DON_GIA_AP_DUNG,
                gct.THU_TU,
                dv.TEN_DV,
                dv.MOTA_DV,
                dv.THOI_GIAN,
                dv.IMAGE AS FALLBACK_IMAGE,
                ha.URL AS COVER_URL
            FROM goi_dich_vu_chi_tiet gct
            JOIN dich_vu dv ON gct.ID_DV = dv.ID_DV
            LEFT JOIN dich_vu_hinh_anh ha 
                ON ha.ID_DV = dv.ID_DV 
                AND ha.IS_COVER = 1
            WHERE gct.ID_GOI = ?
            ORDER BY gct.THU_TU ASC
        ";
        $stmtDV = $conn->prepare($sqlDVTrongGoi);
        $stmtDV->bind_param("i", $idGoi);
        $stmtDV->execute();
        $resultDV = $stmtDV->get_result();

        while ($row = $resultDV->fetch_assoc()) {
            // chọn ảnh hiển thị
            $img = $row['COVER_URL'] ?? $row['FALLBACK_IMAGE'] ?? 'public/images/placeholder.jpg';
            $row['FINAL_IMAGE'] = $img;

            // tổng giá gói = sum đơn giá áp dụng * số lượng
            $tongGiaGoi += (int)$row['DON_GIA_AP_DUNG'] * (int)$row['SO_LUONG'];

            // tổng thời gian ước tính
            $tongThoiGian += (int)$row['THOI_GIAN'] * (int)$row['SO_LUONG'];

            $dichVuList[] = $row;
        }
        $stmtDV->close();

        // 3. Lấy danh sách thiết bị mà gói này cam kết bao gồm
        // (bảng mới goi_dich_vu_thiet_bi join trang_thiet_bi)
        $sqlTB = "
            SELECT 
                ttb.ID_TB,
                ttb.TEN_TB,
                ttb.TINH_TRANG,
                ttb.IMAGE,
                gdvtb.SO_LUONG
            FROM goi_dich_vu_thiet_bi gdvtb
            JOIN trang_thiet_bi ttb ON gdvtb.ID_TB = ttb.ID_TB
            WHERE gdvtb.ID_GOI = ?
            ORDER BY ttb.TEN_TB ASC
        ";
        $stmtTB = $conn->prepare($sqlTB);
        $stmtTB->bind_param("i", $idGoi);
        $stmtTB->execute();
        $resTB = $stmtTB->get_result();
        while ($rowTB = $resTB->fetch_assoc()) {
            $thietBiList[] = $rowTB;
        }
        $stmtTB->close();

        // 4. Tính rating trung bình dựa trên các dịch vụ con trong combo
        $idsDv = array_column($dichVuList, 'ID_DV');
        if (!empty($idsDv)) {
            $inClause = implode(',', array_fill(0, count($idsDv), '?'));
            $types = str_repeat('i', count($idsDv));

            // rating trung bình
            $sqlRating = "
                SELECT AVG(XEP_HANG_DV) AS avg_rating
                FROM phan_hoi_cua_khach_hang
                WHERE ID_DV IN ($inClause)
            ";
            $stmtRating = $conn->prepare($sqlRating);
            $stmtRating->bind_param($types, ...$idsDv);
            $stmtRating->execute();
            $resRating = $stmtRating->get_result();
            $ratingRow = $resRating->fetch_assoc();
            if ($ratingRow && $ratingRow['avg_rating'] !== null) {
                $ratingAvg = round($ratingRow['avg_rating'], 1);
            }
            $stmtRating->close();

            // feedback mẫu (3 feedback gần nhất)
            $sqlFb = "
                SELECT ID_TK, NOI_DUNG, XEP_HANG_DV, NGAY_GUI
                FROM phan_hoi_cua_khach_hang
                WHERE ID_DV IN ($inClause)
                ORDER BY NGAY_GUI DESC
                LIMIT 3
            ";
            $stmtFb = $conn->prepare($sqlFb);
            $stmtFb->bind_param($types, ...$idsDv);
            $stmtFb->execute();
            $resFb = $stmtFb->get_result();
            while ($fbRow = $resFb->fetch_assoc()) {
                $feedbacks[] = $fbRow;
            }
            $stmtFb->close();
        }
    }
}

// map trạng thái để render màu
$trangThaiLabel = '';
$trangThaiColor = '';
if ($goi) {
    switch ($goi['TRANG_THAI']) {
        case 'ban':
            $trangThaiLabel = 'Đang mở bán';
            $trangThaiColor = 'text-green-600 bg-green-50';
            break;
        case 'nhap':
            $trangThaiLabel = 'Nháp / chưa công khai';
            $trangThaiColor = 'text-slate-600 bg-slate-100';
            break;
        case 'ngung':
        default:
            $trangThaiLabel = 'Ngừng cung cấp';
            $trangThaiColor = 'text-red-600 bg-red-50';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>
        <?php if ($goi): ?>
            <?= htmlspecialchars($goi['TEN_GOI']) ?> • Stygian Blue Studio
        <?php else: ?>
            Gói dịch vụ • Stygian Blue Studio
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

<?php if (!$idGoi || !$goi): ?>

    <!-- Trạng thái lỗi (không có id_goi hoặc không tìm thấy gói) -->
    <section class="min-h-screen flex items-center justify-center bg-slate-100 px-6">
        <div class="bg-white shadow-sm border border-slate-200 rounded-2xl max-w-md w-full p-8 text-center">
            <div class="text-4xl mb-4">😕</div>
            <?php if (!$idGoi): ?>
                <h1 class="text-xl font-semibold text-slate-800 mb-2">
                    Gói dịch vụ không hợp lệ
                </h1>
                <p class="text-slate-500 text-sm leading-relaxed">
                    Thiếu tham số 
                    <code class="font-mono text-slate-700 bg-slate-100 px-1 py-0.5 rounded">id_goi</code>
                    trên URL.<br/>
                    Ví dụ:
                    <span class="font-mono text-slate-700 bg-slate-100 px-1 py-0.5 rounded">
                        goi_chitiet.php?id_goi=1
                    </span>
                </p>
            <?php else: ?>
                <h1 class="text-xl font-semibold text-slate-800 mb-2">
                    Không tìm thấy gói dịch vụ
                </h1>
                <p class="text-slate-500 text-sm leading-relaxed">
                    Gói (ID <?= htmlspecialchars($idGoi) ?>) không tồn tại hoặc đã bị gỡ.
                </p>
            <?php endif; ?>

            <a href="goi.php"
               class="inline-block mt-6 bg-sky-500 hover:bg-sky-600 text-white font-semibold py-2 px-4 rounded-lg text-sm shadow">
                Quay về danh sách gói
            </a>
        </div>
    </section>

<?php else: ?>

    <!-- HERO -->
    <section class="relative w-full h-[50vh] flex items-center justify-center bg-slate-900 text-white overflow-hidden">
        <img 
            src="<?= htmlspecialchars($goi['HINH_ANH'] ?: 'public/images/combo/default_combo.jpg') ?>" 
            alt="Ảnh gói dịch vụ"
            class="absolute inset-0 w-full h-full object-cover brightness-[.45]"
        />
        <div class="absolute inset-0 hero-overlay mix-blend-screen"></div>

        <div class="relative max-w-3xl text-center px-6">
            <div class="inline-block text-xs font-medium px-3 py-1 rounded-full mb-4 <?= $trangThaiColor ?>">
                <?= htmlspecialchars($trangThaiLabel) ?>
            </div>

            <h1 class="text-4xl font-bold tracking-tight mb-4">
                <?= htmlspecialchars($goi['TEN_GOI']) ?>
            </h1>

            <p class="text-base md:text-lg text-slate-100/90 leading-relaxed max-w-2xl mx-auto">
                <?= nl2br(htmlspecialchars($goi['MO_TA'])) ?>
            </p>

            <?php if ($ratingAvg): ?>
            <div class="mt-5 flex items-center justify-center gap-2 text-sm text-yellow-300 font-medium">
                <span>★ <?= $ratingAvg ?>/5.0</span>
                <span class="text-slate-200/70">| Đánh giá thực từ khách hàng</span>
            </div>
            <?php endif; ?>

            <a href="#booking" 
               class="mt-8 inline-block bg-sky-500 hover:bg-sky-600 text-white font-semibold px-6 py-3 rounded-xl shadow-lg shadow-sky-900/40 transition">
                Đặt gói này
            </a>
        </div>
    </section>

    <main class="max-w-6xl mx-auto px-4 lg:px-8 py-10 lg:py-14">
        <div class="flex flex-col lg:flex-row gap-10">

            <!-- CỘT TRÁI -->
            <section class="flex-1 min-w-0">

                <!-- Danh sách dịch vụ trong gói -->
                <header class="mb-8">
                    <h2 class="text-2xl font-semibold text-slate-800 flex flex-wrap items-end gap-3">
                        Gói bao gồm
                        <span class="text-sm font-normal text-slate-500">
                            (<?= count($dichVuList) ?> dịch vụ / ~<?= $tongThoiGian ?> phút)
                        </span>
                    </h2>
                    <p class="text-slate-600 text-sm leading-relaxed mt-2 max-w-2xl">
                        Bạn có thể xem rõ từng hạng mục trong gói trước khi đặt lịch. 
                        Riêng phần thiết bị, chúng tôi minh bạch loại thiết bị sẽ mang đến buổi chụp.
                    </p>
                </header>

                <?php if (empty($dichVuList)): ?>
                    <div class="p-6 bg-slate-50 border border-dashed border-slate-300 rounded-xl text-center text-slate-500">
                        Gói này hiện chưa có dịch vụ con nào được cấu hình.
                    </div>
                <?php else: ?>
                    <div class="space-y-6">

                        <?php foreach ($dichVuList as $dv): ?>
                            <?php
                            // ID_DV đại diện cho dịch vụ "Thuê trang thiết bị" trong bảng dich_vu
                            // Chỉnh theo ID thật của bạn. Ví dụ: 3
                            $isRental = ($dv['ID_DV'] == 3);
                            ?>
                            <article class="rounded-xl border border-slate-200 shadow-sm bg-white overflow-hidden">
                                <div class="flex flex-col md:flex-row gap-0 md:gap-6">
                                    <!-- Hình ảnh dịch vụ -->
                                    <div class="md:w-40 shrink-0">
                                        <div class="w-full h-40 md:h-full bg-slate-100">
                                            <img 
                                                src="<?= htmlspecialchars($dv['FINAL_IMAGE']) ?>" 
                                                alt="<?= htmlspecialchars($dv['TEN_DV']) ?>"
                                                class="w-full h-full object-cover"
                                            />
                                        </div>
                                    </div>

                                    <!-- Nội dung -->
                                    <div class="flex-1 p-4 md:py-6 md:pr-6 md:pl-0 flex flex-col">
                                        <div class="flex flex-wrap items-start justify-between gap-4">
                                            <div>
                                                <h3 class="text-lg font-semibold text-slate-800 leading-tight flex flex-wrap items-center gap-2">
                                                    <?= htmlspecialchars($dv['TEN_DV']) ?>
                                                    <span class="text-[11px] font-medium text-sky-600 bg-sky-50 border border-sky-200/50 px-2 py-0.5 rounded-lg">
                                                        x<?= (int)$dv['SO_LUONG'] ?>
                                                    </span>
                                                </h3>
                                                <p class="text-xs text-slate-500 mt-1">
                                                    Dự kiến: <?= (int)$dv['THOI_GIAN'] * (int)$dv['SO_LUONG'] ?> phút
                                                </p>
                                            </div>

                                            <?php if ($isRental): ?>
                                                <div class="text-right">
                                                    <div class="text-emerald-600 font-semibold text-base leading-none">
                                                        Đã bao gồm
                                                    </div>
                                                    <div class="text-[11px] text-slate-400 leading-none mt-1">
                                                        Không phụ thu
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-right">
                                                    <div class="text-sky-600 font-semibold text-base leading-none">
                                                        <?= formatVND($dv['DON_GIA_AP_DUNG']) ?>
                                                    </div>
                                                    <div class="text-[11px] text-slate-400 leading-none mt-1">
                                                        Giá trong gói
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Nội dung chi tiết -->
                                        <?php if (!$isRental): ?>
                                            <p class="text-sm text-slate-600 leading-relaxed mt-4">
                                                <?= nl2br(htmlspecialchars($dv['MOTA_DV'])) ?>
                                            </p>

                                            <button
                                                class="text-xs text-sky-600 hover:text-sky-700 font-medium mt-4 underline underline-offset-2 w-fit">
                                                Xem phản hồi về dịch vụ này
                                            </button>

                                        <?php else: ?>
                                            <div class="mt-4">
                                                <p class="text-sm font-semibold text-slate-800 mb-2">
                                                    Thiết bị dự kiến sử dụng cho gói này:
                                                </p>

                                                <?php if (empty($thietBiList)): ?>
                                                    <p class="text-sm text-slate-500 leading-relaxed">
                                                        Gói này bao gồm quyền sử dụng trang thiết bị studio trong buổi chụp
                                                        (máy ảnh, ánh sáng, phụ kiện cơ bản). 
                                                    </p>
                                                <?php else: ?>
                                                    <ul class="space-y-3">
                                                        <?php foreach ($thietBiList as $tb): ?>
                                                            <li class="flex items-start gap-3 bg-slate-50 border border-slate-200 rounded-lg p-3">
                                                                <div class="w-16 h-16 rounded-md overflow-hidden bg-slate-100 flex-shrink-0">
                                                                    <img 
                                                                        src="<?= htmlspecialchars($tb['IMAGE'] ?: 'public/images/placeholder_equipment.jpg') ?>"
                                                                        alt="<?= htmlspecialchars($tb['TEN_TB']) ?>"
                                                                        class="w-full h-full object-cover"
                                                                    />
                                                                </div>
                                                                <div class="flex-1 min-w-0">
                                                                    <div class="flex flex-wrap items-start justify-between">
                                                                        <div class="font-medium text-slate-800 text-sm leading-snug">
                                                                            <?= htmlspecialchars($tb['TEN_TB']) ?>
                                                                            <?php if ((int)$tb['SO_LUONG'] > 1): ?>
                                                                                <span class="text-xs text-slate-500 font-normal">
                                                                                    x<?= (int)$tb['SO_LUONG'] ?>
                                                                                </span>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                        <div class="text-[11px] text-slate-500 leading-none">
                                                                            Trạng thái: 
                                                                            <span class="font-medium text-slate-700">
                                                                                <?= htmlspecialchars($tb['TINH_TRANG']) ?>
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php endif; ?>

                                                <p class="text-[11px] text-slate-400 mt-4 leading-relaxed">
                                                    Thiết bị có thể thay thế bằng mẫu tương đương nếu đang bận lịch khác.
                                                    Một số thiết bị cao cấp (flycam, livestream đa cam, lens đặc thù...) 
                                                    có thể yêu cầu phụ phí theo yêu cầu riêng.
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>

                    </div>
                <?php endif; ?>

                <!-- Feedback từ khách hàng -->
                <section class="mt-14">
                    <h2 class="text-xl font-semibold text-slate-800 mb-4">
                        Phản hồi từ khách hàng
                    </h2>

                    <?php if (empty($feedbacks)): ?>
                        <p class="text-sm text-slate-500 bg-slate-50 border border-slate-200 rounded-xl p-4">
                            Chưa có đánh giá công khai cho gói này.
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
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </section>

            <!-- SIDEBAR PHẢI -->
            <aside class="lg:w-[320px] flex-shrink-0">
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-6 shadow-sm sticky top-6">
                    <h3 class="text-lg font-semibold text-slate-800">
                        Thông tin gói
                    </h3>

                    <div class="mt-4 text-sm text-slate-700 space-y-3">
                        <div class="flex items-start justify-between">
                            <span class="text-slate-500">Giá trọn gói</span>
                            <span class="font-semibold text-sky-600">
                                <?= formatVND($tongGiaGoi) ?>
                            </span>
                        </div>

                        <div class="flex items-start justify-between">
                            <span class="text-slate-500">Thời lượng dự kiến</span>
                            <span class="font-medium text-slate-800">
                                ~<?= $tongThoiGian ?> phút
                            </span>
                        </div>

                        <?php if ($goi['HIEU_LUC_TU'] || $goi['HIEU_LUC_DEN']): ?>
                            <div class="text-[13px] leading-relaxed">
                                <div class="text-slate-500">Hiệu lực ưu đãi</div>
                                <div class="font-medium text-slate-800">
                                    <?php if ($goi['HIEU_LUC_TU']): ?>
                                        từ <?= formatDate($goi['HIEU_LUC_TU']) ?>
                                    <?php endif; ?>
                                    <?php if ($goi['HIEU_LUC_DEN']): ?>
                                        đến <?= formatDate($goi['HIEU_LUC_DEN']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

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
                       href="datlich.php?id_goi=<?= urlencode($goi['ID_GOI']) ?>"
                       class="block w-full text-center mt-8 bg-sky-500 hover:bg-sky-600 text-white font-semibold py-3 rounded-xl shadow-lg shadow-sky-900/20 transition">
                        Đặt gói này
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
                        <li>Ánh sáng và thiết bị chuyên dụng đúng cam kết.</li>
                        <li>Bảo mật thông tin cá nhân tuyệt đối.</li>
                    </ul>
                </div>
            </aside>
        </div>
    </main>

    <footer class="text-center text-[12px] text-slate-400 py-10">
        © <?= date('Y') ?> Stygian Blue Studio. All rights reserved.
    </footer>

<?php endif; ?>
</body>
</html>
