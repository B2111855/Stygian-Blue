<?php
// goi_chitiet.php
// Trang chi tiết gói dịch vụ (combo) cho Stygian Blue Studio
// Phiên bản: lấy thiết bị trực tiếp từ DB thật

include '../../../database/config.php'; // chỉnh path nếu khác dự án của bạn
require_once __DIR__ . '/../../helpers/assets.php';
require_once __DIR__ . '/../../repositories/PackageRepository.php';

// ===== Helpers =====
function formatVND($n) {
    return number_format($n, 0, ',', '.') . "₫";
}
function formatDate($str) {
    if (!$str) return null;
    $d = new DateTime($str);
    return $d->format('d/m/Y');
}

// Ảnh: cố gắng tìm file trong project; nếu thiếu thì fallback placeholder
function goi_img_url($path) {
    $placeholder = 'public/images/placeholder.jpg';
    if (!$path) return sb_asset_href($placeholder);

    if (preg_match('~^https?://~i', $path)) {
        return $path; // đã là URL tuyệt đối
    }

    $normalized = ltrim(str_replace('\\', '/', $path), '/');
    $projectPath = rtrim(sb_project_root(), '/\\') . '/' . $normalized;
    $docRootPath = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\') . '/' . $normalized;

    if (is_file($projectPath)) return sb_asset_href($normalized);
    if (is_file($docRootPath)) return '/' . $normalized;

    // Nếu dữ liệu cũ còn prefix cache/, ta fallback về placeholder tránh 404
    return sb_asset_href($placeholder);
}

// Lấy ID gói từ URL (?id_goi=...)
$idGoi = isset($_GET['id_goi']) ? intval($_GET['id_goi']) : 0;

// Chuẩn bị biến
$goi = null;
$dichVuList = [];
$thietBiList = [];
$tongGiaGoi = 0;
$giaGoc = null;
$giaSauGiam = null;
$soTienGiam = null;
$tongThoiGian = 0;
$ratingAvg = null;
$feedbacks = [];
$pkgRepo = new \App\Repositories\PackageRepository($conn);

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
            g.TRANG_THAI,
            vt.TONG_GIA_GOI    AS GIA_GOC,
            km.GIA_SAU_GIAM    AS GIA_SAU_GIAM,
            km.SO_TIEN_GIAM    AS SO_TIEN_GIAM,
            km.TEN_CHUONG_TRINH AS TEN_KM,
            km.LOAI_GIAM,
            km.GIA_TRI_GIAM,
            km.GIAM_TOI_DA,
            km.TU_NGAY,
            km.DEN_NGAY
        FROM goi_dich_vu g
        LEFT JOIN v_goi_dich_vu_tong_tien vt ON vt.ID_GOI = g.ID_GOI
        LEFT JOIN v_goi_dich_vu_gia_khuyen_mai km ON km.ID_GOI = g.ID_GOI
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
        $giaGoc = isset($goi['GIA_GOC']) ? (int)$goi['GIA_GOC'] : null;
        $giaSauGiam = isset($goi['GIA_SAU_GIAM']) ? (int)$goi['GIA_SAU_GIAM'] : null;
        $soTienGiam = isset($goi['SO_TIEN_GIAM']) ? (int)$goi['SO_TIEN_GIAM'] : null;
    }

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
            // chọn ảnh hiển thị, tránh 404 nếu dữ liệu cũ lưu cache/
            $img = $row['COVER_URL'] ?? $row['FALLBACK_IMAGE'] ?? 'public/images/placeholder.jpg';
            $row['FINAL_IMAGE'] = goi_img_url($img);

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

        // Áp dụng khuyến mãi hợp lệ (hỗ trợ cả bảng mới lẫn bảng pivot cũ)
        $pricing = $pkgRepo->getPackagePromotionPricing($idGoi, 0.0);
        $giaGoc = $pricing['base_total'];
        $giaSauGiam = $pricing['subtotal'];
        $soTienGiam = $pricing['discount'];
        $tongGiaGoi = $giaGoc;
        if ($pricing['promotion']) {
            $goi['TEN_KM'] = $pricing['promotion']['TEN_CHUONG_TRINH']
                ?? $pricing['promotion']['TEN_KM']
                ?? ($goi['TEN_KM'] ?? null);
        }

        // Chuẩn hóa giá: ưu tiên giá gốc từ view; nếu chưa có thì dùng tổng tính từ chi tiết
        if ($giaGoc === null) $giaGoc = $tongGiaGoi;
        if ($giaSauGiam === null) $giaSauGiam = $giaGoc;
        if ($soTienGiam === null && $giaGoc !== null && $giaSauGiam !== null) {
            $soTienGiam = max(0, $giaGoc - $giaSauGiam);
        }
        if ($giaGoc !== null) {
            $tongGiaGoi = $giaGoc;
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
            $trangThaiColor = 'text-emerald-200 bg-emerald-500/10 border border-emerald-400/40';
            break;
        case 'nhap':
            $trangThaiLabel = 'Nháp / chưa công khai';
            $trangThaiColor = 'text-slate-300 bg-slate-500/10 border border-slate-400/30';
            break;
        case 'ngung':
        default:
            $trangThaiLabel = 'Ngừng cung cấp';
            $trangThaiColor = 'text-rose-200 bg-rose-500/10 border border-rose-400/40';
            break;
    }
}

$hasPromo = ($giaGoc !== null && $giaSauGiam !== null && $giaSauGiam < $giaGoc);
$promoLabel = $goi['TEN_KM'] ?? '';
$discountPercent = ($hasPromo && $giaGoc > 0) ? round((1 - ($giaSauGiam / $giaGoc)) * 100) : 0;
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
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet" />
    <?= sb_tailwind_link_tag(); ?>

    
    <!-- Light detail page stylesheet -->
    <link rel="stylesheet" href="../../admin/assets/css/detail-pages-light.css">
</head>
<body class="relative bg-[var(--bg-primary)] text-[var(--text-primary)] font-body antialiased">
<div class="fixed inset-0 -z-10 opacity-[0.06] bg-[var(--bg-secondary)]"></div>

<?php if (!$idGoi || !$goi): ?>

    <section class="relative min-h-screen flex items-center justify-center px-6 py-24">
        <div class="section-card max-w-lg w-full rounded-3xl px-10 py-12 text-center">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-accent-500/20 text-3xl">
                😕
            </div>
            <?php if (!$idGoi): ?>
                <h1 class="mt-6 text-2xl font-display text-white">
                    Gói dịch vụ không hợp lệ
                </h1>
                <p class="mt-4 text-sm text-slate-300 leading-relaxed">
                    Thiếu tham số
                    <code class="rounded-md bg-night-800 px-2 py-1 font-mono text-slate-100">id_goi</code>
                    trên URL.<br/>
                    Ví dụ:
                    <span class="rounded-md bg-night-800 px-2 py-1 font-mono text-slate-100">
                        goi_chitiet.php?id_goi=1
                    </span>
                </p>
            <?php else: ?>
                <h1 class="mt-6 text-2xl font-display text-white">
                    Không tìm thấy gói dịch vụ
                </h1>
                <p class="mt-4 text-sm text-slate-300 leading-relaxed">
                    Gói (ID <?= htmlspecialchars($idGoi) ?>) không tồn tại hoặc đã bị gỡ.
                </p>
            <?php endif; ?>

            <a href="goi.php"
               class="mt-8 inline-block rounded-full bg-accent-500 px-6 py-3 text-sm font-semibold uppercase tracking-widest text-white transition hover:bg-accent-600">
                Quay về danh sách gói
            </a>
        </div>
    </section>

<?php else: ?>

    <section class="hero-section sb-hero relative flex min-h-[70vh] items-end overflow-hidden bg-[var(--bg-primary)]">
        <img
            src="<?= htmlspecialchars(goi_img_url($goi['HINH_ANH'] ?: 'public/images/combo/default_combo.jpg')) ?>"
            alt="Ảnh gói dịch vụ"
            class="hero-image absolute inset-0 h-full w-full object-cover"
        />
        

        <div class="relative z-10 w-full pb-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-10">
                <nav data-crumbs class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.35em]">
                    <a href="goi.php" class="transition hover:opacity-100 opacity-90">Danh sách gói</a>
                    <span class="opacity-80">•</span>
                    <span class="opacity-90"><?= htmlspecialchars($goi['TEN_GOI']) ?></span>
                </nav>

                <div class="mt-8 inline-flex items-center gap-3 rounded-full px-5 py-2 text-[11px] font-semibold uppercase tracking-[0.35em] bg-emerald-100 text-emerald-700 border border-emerald-300">
                    <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span>
                    <span class="text-current">
                        <?= htmlspecialchars($trangThaiLabel) ?>
                    </span>
                </div>

                <h1 class="hero-title mt-6 max-w-4xl text-4xl font-display leading-tight sm:text-5xl">
                    <?= htmlspecialchars($goi['TEN_GOI']) ?>
                </h1>

                <p class="hero-subtitle mt-4 max-w-3xl text-base">
                    <?= nl2br(htmlspecialchars($goi['MO_TA'])) ?>
                </p>

                <div class="mt-10 grid gap-4 sm:grid-cols-3">
                                        <div class="rounded-3xl border border-[var(--photo-border)] bg-white px-5 py-6 text-left shadow-sm">
                                                <span class="text-[11px] uppercase tracking-[0.35em] text-[var(--text-tertiary)]">Giá trọn gói</span>
                                                <?php if ($hasPromo): ?>
                                                    <p class="mt-3 text-3xl font-semibold text-[var(--accent-primary)]">
                                                        <?= formatVND($giaSauGiam) ?>
                                                    </p>
                                                    <div class="mt-1 text-sm text-[var(--text-tertiary)] line-through">
                                                        <?= formatVND($giaGoc) ?>
                                                    </div>
                                                    <p class="mt-2 text-xs text-[var(--text-secondary)]">
                                                        Tiết kiệm <?= formatVND($soTienGiam) ?> (<?= $discountPercent ?>%)<?php if ($promoLabel): ?> • <?= htmlspecialchars($promoLabel) ?><?php endif; ?>
                                                    </p>
                                                <?php else: ?>
                                                    <p class="mt-3 text-2xl font-semibold text-[var(--accent-primary)]">
                                                        <?= formatVND($giaGoc) ?>
                                                    </p>
                                                    <p class="mt-2 text-xs text-[var(--text-secondary)]">Tiết kiệm so với đặt lẻ từng dịch vụ</p>
                                                <?php endif; ?>
                                        </div>
                    <div class="rounded-3xl border border-[var(--photo-border)] bg-white px-5 py-6 text-left shadow-sm">
                        <span class="text-[11px] uppercase tracking-[0.35em] text-[var(--text-tertiary)]">Thời lượng tổng</span>
                        <p class="mt-3 text-2xl font-semibold text-[var(--text-primary)]">
                            ~<?= $tongThoiGian ?> phút
                        </p>
                        <p class="mt-2 text-xs text-[var(--text-secondary)]">Bao gồm chuẩn bị set và briefing trước buổi chụp</p>
                    </div>
                    <div class="rounded-3xl border border-[var(--photo-border)] bg-white px-5 py-6 text-left shadow-sm">
                        <span class="text-[11px] uppercase tracking-[0.35em] text-[var(--text-tertiary)]">Thiết bị mang theo</span>
                        <p class="mt-3 text-2xl font-semibold text-[var(--text-primary)]">
                            <?php if (!empty($thietBiList)): ?><?= count($thietBiList) ?> hạng mục<?php else: ?>Theo nhu cầu<?php endif; ?>
                        </p>
                        <p class="mt-2 text-xs text-[var(--text-secondary)]">Danh sách thiết bị chuyên dụng chuẩn studio</p>
                    </div>
                </div>

                <div class="mt-12 flex flex-wrap items-center gap-4">
                    <a href="#booking" class="group inline-flex items-center gap-3 rounded-full bg-[var(--accent-primary)] px-7 py-3 text-xs font-semibold uppercase tracking-[0.4em] text-white shadow-md transition hover:bg-[var(--accent-secondary)]">
                        <span>Đặt gói này</span>
                        <span class="text-lg transition group-hover:translate-x-1">›</span>
                    </a>
                    <a href="#combo-detail" class="inline-flex items-center gap-3 rounded-full border border-[var(--photo-border)] px-7 py-3 text-xs font-semibold uppercase tracking-[0.4em] text-[var(--text-primary)] transition hover:bg-[var(--bg-secondary)]">
                        Khám phá chi tiết
                    </a>
                </div>
            </div>
        </div>
    </section>

    <main class="relative z-10 -mt-16 lg:-mt-24 pb-24 pt-10">
        <div class="mx-auto flex max-w-7xl flex-col gap-12 px-4 sm:px-6 lg:flex-row lg:gap-16 lg:px-10">
            <section class="flex-1 space-y-10" id="combo-detail">
                <article class="rounded-3xl border border-[var(--photo-border)] bg-white px-8 py-10 lg:px-12 lg:py-12">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <span class="text-[11px] uppercase tracking-[0.35em] text-[var(--text-tertiary)]">Gói bao gồm</span>
                            <h2 class="mt-2 text-3xl font-display text-[var(--text-primary)]">Dịch vụ trong combo</h2>
                        </div>
                        <span class="rounded-full border border-[var(--photo-border)] bg-[var(--bg-secondary)] px-4 py-1 text-[11px] font-semibold uppercase tracking-[0.35em] text-[var(--text-secondary)]">
                            <?= count($dichVuList) ?> dịch vụ · ~<?= $tongThoiGian ?> phút
                        </span>
                    </div>
                    <div class="mt-6 h-px bg-[var(--photo-border)]"></div>

                    <?php if (empty($dichVuList)): ?>
                        <div class="mt-8 rounded-2xl border border-dashed border-[var(--photo-border)] bg-[var(--bg-secondary)] px-6 py-10 text-center text-sm text-[var(--text-secondary)]">
                            Gói này hiện chưa có dịch vụ con nào được cấu hình. Vui lòng liên hệ đội ngũ Stygian Blue để được tư vấn thêm.
                        </div>
                    <?php else: ?>
                        <div class="mt-8 space-y-6">
                            <?php foreach ($dichVuList as $dv): ?>
                                <?php $isRental = ($dv['ID_DV'] == 3); ?>
                                <article class="rounded-3xl border border-[var(--photo-border)] bg-[var(--bg-secondary)] px-6 py-6 lg:px-8 lg:py-8">
                                    <div class="flex flex-col gap-6 md:flex-row">
                                        <div class="md:w-48">
                                            <div class="rounded-3xl overflow-hidden border border-[var(--photo-border)]">
                                                <img
                                                    src="<?= htmlspecialchars($dv['FINAL_IMAGE']) ?>"
                                                    alt="<?= htmlspecialchars($dv['TEN_DV']) ?>"
                                                    loading="lazy"
                                                    class="h-40 w-full rounded-3xl object-cover md:h-full"
                                                />
                                            </div>
                                        </div>
                                        <div class="flex-1">
                                            <div class="flex flex-wrap items-start justify-between gap-4">
                                                <div>
                                                    <h3 class="text-2xl font-display text-[var(--text-primary)]">
                                                        <?= htmlspecialchars($dv['TEN_DV']) ?>
                                                    </h3>
                                                    <p class="mt-2 text-xs uppercase tracking-[0.35em] text-[var(--text-tertiary)]">
                                                        Số lượng: x<?= (int)$dv['SO_LUONG'] ?> · Thời gian: <?= (int)$dv['THOI_GIAN'] * (int)$dv['SO_LUONG'] ?> phút
                                                    </p>
                                                </div>
                                                <div class="text-right">
                                                    <?php if ($isRental): ?>
                                                        <div class="rounded-full border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700">
                                                            Đã bao gồm thiết bị
                                                        </div>
                                                        <div class="mt-2 text-[11px] uppercase tracking-[0.3em] text-[var(--text-tertiary)]">
                                                            Không phụ thu
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="text-2xl font-semibold text-[var(--accent-primary)]">
                                                            <?= formatVND($dv['DON_GIA_AP_DUNG']) ?>
                                                        </div>
                                                        <div class="mt-2 text-[11px] uppercase tracking-[0.3em] text-[var(--text-tertiary)]">
                                                            Giá ưu đãi trong gói
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <?php if ($isRental): ?>
                                                <div class="mt-6 space-y-5">
                                                    <p class="text-sm font-medium text-[var(--text-secondary)]">
                                                        Thiết bị dự kiến được mang tới để đảm bảo chất lượng ánh sáng và chất lượng ảnh cao nhất:
                                                    </p>
                                                    <?php if (empty($thietBiList)): ?>
                                                        <p class="text-sm text-[var(--text-secondary)]">
                                                            Gói bao gồm quyền sử dụng toàn bộ thiết bị chuẩn studio (máy ảnh full-frame, hệ thống đèn flash, modifier, phông nền cơ bản).
                                                        </p>
                                                    <?php else: ?>
                                                        <div class="grid gap-4 sm:grid-cols-2">
                                                            <?php foreach ($thietBiList as $tb): ?>
                                                                <div class="rounded-2xl border border-[var(--photo-border)] bg-white px-4 py-4">
                                                                    <div class="flex items-center gap-3">
                                                                        <div class="h-14 w-14 overflow-hidden rounded-xl bg-[var(--bg-secondary)]">
                                                                            <img
                                                                                src="<?= htmlspecialchars(goi_img_url($tb['IMAGE'] ?: 'public/images/placeholder_equipment.jpg')) ?>"
                                                                                alt="<?= htmlspecialchars($tb['TEN_TB']) ?>"
                                                                                loading="lazy"
                                                                                class="h-full w-full object-cover"
                                                                            />
                                                                        </div>
                                                                        <div class="flex-1">
                                                                            <div class="text-sm font-semibold text-[var(--text-primary)]">
                                                                                <?= htmlspecialchars($tb['TEN_TB']) ?>
                                                                                <?php if ((int)$tb['SO_LUONG'] > 1): ?>
                                                                                    <span class="text-xs font-normal text-[var(--text-tertiary)]">x<?= (int)$tb['SO_LUONG'] ?></span>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                            <div class="mt-1 text-[11px] uppercase tracking-[0.3em] text-[var(--text-tertiary)]">
                                                                                Trạng thái: <?= htmlspecialchars($tb['TINH_TRANG']) ?>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <p class="text-[11px] text-[var(--text-tertiary)]">
                                                        Danh sách có thể thay đổi bằng thiết bị tương đương nếu lịch studio kín. Thiết bị đặc biệt (flycam, livestream đa cam...) sẽ được báo giá riêng khi phát sinh yêu cầu.
                                                    </p>
                                                </div>
                                            <?php else: ?>
                                                <p class="mt-6 text-sm leading-7 text-[var(--text-secondary)]">
                                                    <?= nl2br(htmlspecialchars($dv['MOTA_DV'])) ?>
                                                </p>
                                                <a href="chitiet.php?id=<?= urlencode($dv['ID_DV']) ?>" class="mt-6 inline-flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.35em] text-[var(--accent-primary)] transition hover:text-[var(--accent-secondary)]">
                                                    Xem chi tiết dịch vụ
                                                    <span class="text-base">→</span>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>

                <article class="rounded-3xl border border-[var(--photo-border)] bg-white px-8 py-10 lg:px-12 lg:py-12">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <span class="text-[11px] uppercase tracking-[0.35em] text-[var(--text-tertiary)]">Ưu đãi & lịch trình</span>
                            <h2 class="mt-2 text-3xl font-display text-[var(--text-primary)]">Hiệu lực gói dịch vụ</h2>
                        </div>
                        <?php if ($ratingAvg): ?>
                        <div class="inline-flex items-center gap-2 rounded-full border border-yellow-300 bg-yellow-50 px-4 py-1 text-sm font-semibold text-yellow-700">
                            ★ <?= $ratingAvg ?>/5.0
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="mt-6 h-px bg-[var(--photo-border)]"></div>
                    <div class="mt-6 grid gap-5 md:grid-cols-2">
                        <div class="rounded-2xl border border-[var(--photo-border)] bg-[var(--bg-secondary)] px-5 py-5">
                            <div class="text-[11px] uppercase tracking-[0.35em] text-[var(--text-tertiary)]">Ưu đãi áp dụng</div>
                            <p class="mt-3 text-sm text-[var(--text-secondary)]">
                                <?php if ($goi['HIEU_LUC_TU'] || $goi['HIEU_LUC_DEN']): ?>
                                    Có hiệu lực
                                    <?php if ($goi['HIEU_LUC_TU']): ?>từ <?= formatDate($goi['HIEU_LUC_TU']) ?><?php endif; ?>
                                    <?php if ($goi['HIEU_LUC_DEN']): ?> đến <?= formatDate($goi['HIEU_LUC_DEN']) ?><?php endif; ?>.
                                <?php else: ?>
                                    Linh hoạt theo lịch studio, vui lòng liên hệ để giữ ngày đẹp.
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="rounded-2xl border border-[var(--photo-border)] bg-[var(--bg-secondary)] px-5 py-5">
                            <div class="text-[11px] uppercase tracking-[0.35em] text-[var(--text-tertiary)]">Trải nghiệm bao gồm</div>
                            <ul class="mt-3 space-y-2 text-sm text-[var(--text-secondary)]">
                                <li>Pre-production meeting và moodboard theo brand.</li>
                                <li>Điều phối lịch trình giữa các hạng mục trong gói.</li>
                                <li>Bàn giao hình ảnh hậu kỳ theo tiêu chuẩn thương hiệu.</li>
                            </ul>
                        </div>
                    </div>
                </article>

                <article class="rounded-3xl border border-[var(--photo-border)] bg-white px-8 py-10 lg:px-12 lg:py-12">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <span class="text-[11px] uppercase tracking-[0.35em] text-[var(--text-tertiary)]">Client Stories</span>
                            <h2 class="mt-2 text-3xl font-display text-[var(--text-primary)]">Phản hồi từ khách hàng</h2>
                        </div>
                    </div>
                    <?php if (empty($feedbacks)): ?>
                        <p class="mt-8 rounded-2xl border border-dashed border-[var(--photo-border)] bg-[var(--bg-secondary)] px-6 py-6 text-sm text-[var(--text-secondary)]">
                            Chưa có đánh giá công khai cho gói này. Chúng tôi sẽ cập nhật ngay khi có câu chuyện mới từ khách hàng.
                        </p>
                    <?php else: ?>
                        <div class="mt-8 grid gap-6 md:grid-cols-2">
                            <?php foreach ($feedbacks as $fb): ?>
                                <div class="rounded-3xl border border-[var(--photo-border)] bg-white px-6 py-6 shadow-sm">
                                    <p class="text-sm leading-relaxed text-[var(--text-secondary)] italic">
                                        “<?= htmlspecialchars($fb['NOI_DUNG']) ?>”
                                    </p>
                                    <div class="mt-5 flex items-center justify-between text-xs text-[var(--text-tertiary)]">
                                        <span class="font-semibold text-[var(--text-primary)]">
                                            <?= htmlspecialchars($fb['ID_TK']) ?>
                                        </span>
                                        <span>⭐ <?= (int)$fb['XEP_HANG_DV'] ?>/5</span>
                                    </div>
                                    <?php if (!empty($fb['NGAY_GUI'])): ?>
                                    <div class="mt-2 text-[11px] text-right text-[var(--text-tertiary)]">
                                        <?= formatDate($fb['NGAY_GUI']) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>

                <article class="rounded-3xl border border-[var(--photo-border)] bg-white px-8 py-10 lg:px-12 lg:py-12">
                    <div class="flex flex-col gap-8">
                        <div>
                            <span class="text-[11px] uppercase tracking-[0.35em] text-[var(--text-tertiary)]">Studio Promise</span>
                            <h2 class="mt-2 text-3xl font-display text-[var(--text-primary)]">Trọn gói, không bỏ sót khoảnh khắc</h2>
                            <p class="mt-4 text-sm leading-7 text-[var(--text-secondary)]">
                                Mỗi gói combo của Stygian Blue được xây dựng cho một câu chuyện hoàn chỉnh: từ ý tưởng, hậu trường đến sản phẩm cuối cùng. Đội ngũ phụ trách xuyên suốt để đảm bảo không có chi tiết nào bị bỏ lỡ.
                            </p>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1 text-sm text-[var(--text-secondary)]">
                            <span class="inline-flex items-center gap-3 rounded-full border border-[var(--photo-border)] bg-[var(--bg-secondary)] px-4 py-2">
                                <span class="h-2 w-2 rounded-full bg-[var(--accent-primary)]"></span>
                                Lịch trình chụp tối ưu cho từng hạng mục
                            </span>
                            <span class="inline-flex items-center gap-3 rounded-full border border-[var(--photo-border)] bg-[var(--bg-secondary)] px-4 py-2">
                                <span class="h-2 w-2 rounded-full bg-[var(--accent-primary)]"></span>
                                Styling team đồng hành trong suốt buổi
                            </span>
                            <span class="inline-flex items-center gap-3 rounded-full border border-[var(--photo-border)] bg-[var(--bg-secondary)] px-4 py-2">
                                <span class="h-2 w-2 rounded-full bg-[var(--accent-primary)]"></span>
                                Hậu kỳ màu sắc theo định hướng thương hiệu
                            </span>
                        </div>
                    </div>
                </article>
            </section>

            <aside class="lg:w-80 flex-shrink-0">
                <div class="sticky top-10 rounded-3xl border border-[var(--photo-border)] bg-white px-7 py-9">
                    <h3 class="text-lg font-display text-[var(--text-primary)]">Thông tin gói</h3>
                    <div class="mt-6 space-y-5 text-sm text-[var(--text-secondary)]">
                        <div class="flex items-center justify-between">
                            <span class="text-[var(--text-tertiary)]">Giá trọn gói</span>
                            <span class="text-xl font-semibold text-[var(--accent-primary)]">
                                <?= formatVND($giaSauGiam) ?>
                            </span>
                        </div>
                        <?php if ($hasPromo): ?>
                        <div class="flex items-center justify-between text-xs text-[var(--text-tertiary)]">
                            <span>Giá gốc</span>
                            <span class="line-through"><?= formatVND($giaGoc) ?></span>
                        </div>
                        <div class="flex items-center justify-between text-xs text-[var(--text-secondary)]">
                            <span>Giảm</span>
                            <span>-<?= formatVND($soTienGiam) ?> (<?= $discountPercent ?>%)<?php if ($promoLabel): ?> • <?= htmlspecialchars($promoLabel) ?><?php endif; ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="flex items-center justify-between">
                            <span class="text-[var(--text-tertiary)]">Thời lượng dự kiến</span>
                            <span class="font-medium text-[var(--text-primary)]">
                                ~<?= $tongThoiGian ?> phút
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-[var(--text-tertiary)]">Số dịch vụ</span>
                            <span class="font-medium text-[var(--text-primary)]">
                                <?= count($dichVuList) ?> hạng mục
                            </span>
                        </div>
                        <?php if ($goi['HIEU_LUC_TU'] || $goi['HIEU_LUC_DEN']): ?>
                        <div>
                            <span class="text-[var(--text-tertiary)]">Hiệu lực ưu đãi</span>
                            <div class="mt-2 text-[11px] uppercase tracking-[0.35em] text-[var(--text-tertiary)]">
                                <?php if ($goi['HIEU_LUC_TU']): ?>Từ <?= formatDate($goi['HIEU_LUC_TU']) ?><?php endif; ?>
                                <?php if ($goi['HIEU_LUC_DEN']): ?> · Đến <?= formatDate($goi['HIEU_LUC_DEN']) ?><?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($ratingAvg): ?>
                        <div class="flex items-center justify-between">
                            <span class="text-[var(--text-tertiary)]">Đánh giá trung bình</span>
                            <span class="font-medium text-yellow-600">
                                ★ <?= $ratingAvg ?>/5.0
                            </span>
                        </div>
                        <?php endif; ?>
                        <div>
                            <span class="text-[var(--text-tertiary)]">Tình trạng</span>
                            <div class="mt-2 inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.35em] bg-emerald-100 text-emerald-700 border border-emerald-300">
                                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                <?= htmlspecialchars($trangThaiLabel) ?>
                            </div>
                        </div>
                    </div>

                          <a id="booking"
                              href="/StygianBlue/app/Pages/Views/lienhe.php?id_goi=<?= urlencode($goi['ID_GOI']) ?>"
                       class="mt-10 inline-flex w-full items-center justify-center gap-3 rounded-full bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-3 text-xs font-bold uppercase tracking-[0.35em] text-white shadow-lg transition hover:from-indigo-700 hover:to-purple-700 hover:shadow-xl">
                        Đặt gói ngay
                    </a>

                    <button type="button"
                        onclick="document.querySelector('.sb-ai-chat-toggle')?.click()"
                        class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-full border-2 border-sky-500 bg-white px-6 py-3 text-xs font-semibold uppercase tracking-[0.35em] text-sky-600 transition hover:border-sky-600 hover:bg-sky-50 hover:text-sky-700">
                        Tư vấn
                    </button>

                    <div class="mt-8 rounded-2xl border border-[var(--photo-border)] bg-[var(--bg-secondary)] px-5 py-5 text-xs leading-6 text-[var(--text-secondary)]">
                        <div class="font-semibold text-[var(--text-primary)]">Cam kết Stygian Blue</div>
                        <ul class="mt-3 space-y-2">
                            <li>Điều phối creative director & ekip trọn gói.</li>
                            <li>Thiết bị ánh sáng & camera chuẩn điện ảnh.</li>
                            <li>Hậu kỳ màu sắc signature, bàn giao đúng tiến độ.</li>
                        </ul>
                    </div>
                </div>
            </aside>
        </div>
    </main>

    <footer class="border-t border-[var(--photo-border)] px-6 py-10 text-center text-[12px] text-[var(--text-tertiary)]">
        © <?= date('Y') ?> Stygian Blue Studio · Crafted for storytellers in light.
    </footer>

<?php endif; ?>
</body>
</html>
