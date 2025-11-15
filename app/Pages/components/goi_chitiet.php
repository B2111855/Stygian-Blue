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
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        body: ['Inter', 'system-ui', 'sans-serif'],
                        display: ['"Playfair Display"', 'serif'],
                    },
                    colors: {
                        night: {
                            900: '#0b1120',
                            800: '#111c33',
                            700: '#15213f',
                        },
                        accent: {
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                        },
                    },
                    boxShadow: {
                        glow: '0 25px 60px -25px rgba(14,165,233,0.55)',
                        frame: '0 0 0 1px rgba(148, 163, 184, 0.15) inset',
                    },
                },
            },
        };
    </script>

    <style>
        :root {
            color-scheme: dark;
        }
        .hero-overlay {
            background: radial-gradient(circle at 20% 25%, rgba(255,255,255,0.22) 0%, rgba(8,47,73,0.08) 45%, rgba(2,6,23,0.78) 100%);
        }
        .grain-overlay {
            background-image: linear-gradient(115deg, rgba(15,23,42,0.35) 0%, rgba(30,41,59,0.45) 45%, rgba(8,47,73,0.5) 100%);
            filter: saturate(1.1) contrast(1.05);
        }
        .section-card {
            background: rgba(12, 18, 32, 0.82);
            border: 1px solid rgba(148, 163, 184, 0.18);
            box-shadow: 0 30px 60px -40px rgba(14, 165, 233, 0.42);
            backdrop-filter: blur(16px);
        }
        .floating-panel {
            background: rgba(11, 17, 32, 0.9);
            border: 1px solid rgba(56, 189, 248, 0.2);
            backdrop-filter: blur(14px);
            box-shadow: 0 25px 40px -30px rgba(56, 189, 248, 0.55);
        }
        .film-frame {
            position: relative;
            border: 1px solid rgba(148, 163, 184, 0.1);
            background: linear-gradient(160deg, rgba(15,23,42,0.9) 0%, rgba(15,23,42,0.65) 100%);
            overflow: hidden;
            box-shadow: 0 15px 35px -25px rgba(20, 184, 166, 0.45);
        }
        .film-frame::before,
        .film-frame::after {
            content: '';
            position: absolute;
            inset: 12px;
            border: 1px dashed rgba(148, 163, 184, 0.16);
            pointer-events: none;
        }
        .service-card {
            background: rgba(10, 16, 30, 0.88);
            border: 1px solid rgba(94, 234, 212, 0.18);
            box-shadow: 0 30px 60px -40px rgba(16, 185, 129, 0.45);
            backdrop-filter: blur(12px);
        }
        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent 0%, rgba(14, 165, 233, 0.5) 50%, transparent 100%);
        }
    </style>
</head>
<body class="relative bg-night-900 text-slate-100 font-body antialiased">
<div class="fixed inset-0 -z-10 opacity-[0.33] grain-overlay"></div>

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

    <section class="relative flex min-h-[70vh] items-end overflow-hidden">
        <img
            src="<?= htmlspecialchars($goi['HINH_ANH'] ?: 'public/images/combo/default_combo.jpg') ?>"
            alt="Ảnh gói dịch vụ"
            class="absolute inset-0 h-full w-full object-cover opacity-80"
        />
        <div class="absolute inset-0 bg-gradient-to-t from-night-900 via-night-900/70 to-transparent"></div>
        <div class="absolute inset-0 hero-overlay mix-blend-screen opacity-80"></div>

        <div class="relative z-10 w-full pb-24">
            <div class="mx-auto max-w-6xl px-6 lg:px-10">
                <nav class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.35em] text-slate-300/80">
                    <a href="goi.php" class="transition hover:text-white">Danh sách gói</a>
                    <span class="text-slate-500">•</span>
                    <span class="text-white/80"><?= htmlspecialchars($goi['TEN_GOI']) ?></span>
                </nav>

                <div class="mt-8 inline-flex items-center gap-3 rounded-full px-5 py-2 text-[11px] font-semibold uppercase tracking-[0.35em] <?= $trangThaiColor ?>">
                    <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-300"></span>
                    <span class="text-current">
                        <?= htmlspecialchars($trangThaiLabel) ?>
                    </span>
                </div>

                <h1 class="mt-6 max-w-4xl text-4xl font-display leading-tight text-white sm:text-5xl">
                    <?= htmlspecialchars($goi['TEN_GOI']) ?>
                </h1>

                <p class="mt-4 max-w-3xl text-base text-slate-200/80">
                    <?= nl2br(htmlspecialchars($goi['MO_TA'])) ?>
                </p>

                <div class="mt-10 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-3xl border border-white/15 bg-white/10 px-5 py-6 text-left shadow-frame">
                        <span class="text-[11px] uppercase tracking-[0.35em] text-slate-300">Giá trọn gói</span>
                        <p class="mt-3 text-2xl font-semibold text-white">
                            <?= formatVND($tongGiaGoi) ?>
                        </p>
                        <p class="mt-2 text-xs text-slate-300">Tiết kiệm so với đặt lẻ từng dịch vụ</p>
                    </div>
                    <div class="rounded-3xl border border-white/15 bg-white/10 px-5 py-6 text-left shadow-frame">
                        <span class="text-[11px] uppercase tracking-[0.35em] text-slate-300">Thời lượng tổng</span>
                        <p class="mt-3 text-2xl font-semibold text-white">
                            ~<?= $tongThoiGian ?> phút
                        </p>
                        <p class="mt-2 text-xs text-slate-300">Bao gồm chuẩn bị set và briefing trước buổi chụp</p>
                    </div>
                    <div class="rounded-3xl border border-white/15 bg-white/10 px-5 py-6 text-left shadow-frame">
                        <span class="text-[11px] uppercase tracking-[0.35em] text-slate-300">Thiết bị mang theo</span>
                        <p class="mt-3 text-2xl font-semibold text-white">
                            <?php if (!empty($thietBiList)): ?><?= count($thietBiList) ?> hạng mục<?php else: ?>Theo nhu cầu<?php endif; ?>
                        </p>
                        <p class="mt-2 text-xs text-slate-300">Danh sách thiết bị chuyên dụng chuẩn studio</p>
                    </div>
                </div>

                <div class="mt-12 flex flex-wrap items-center gap-4">
                    <a href="#booking" class="group inline-flex items-center gap-3 rounded-full bg-accent-500 px-7 py-3 text-xs font-semibold uppercase tracking-[0.4em] text-white shadow-glow transition hover:bg-accent-600">
                        <span>Đặt gói này</span>
                        <span class="text-lg transition group-hover:translate-x-1">›</span>
                    </a>
                    <a href="#combo-detail" class="inline-flex items-center gap-3 rounded-full border border-white/20 px-7 py-3 text-xs font-semibold uppercase tracking-[0.4em] text-slate-200 transition hover:border-white/40 hover:text-white">
                        Khám phá chi tiết
                    </a>
                </div>
            </div>
        </div>
    </section>

    <main class="relative z-10 -mt-16 lg:-mt-24 pb-24 pt-10">
        <div class="mx-auto flex max-w-6xl flex-col gap-12 px-6 lg:flex-row lg:px-10">
            <section class="flex-1 space-y-10" id="combo-detail">
                <article class="section-card rounded-3xl px-8 py-10 lg:px-10">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <span class="text-[11px] uppercase tracking-[0.35em] text-slate-400">Gói bao gồm</span>
                            <h2 class="mt-2 text-3xl font-display text-white">Dịch vụ trong combo</h2>
                        </div>
                        <span class="rounded-full border border-white/15 bg-white/5 px-4 py-1 text-[11px] font-semibold uppercase tracking-[0.35em] text-slate-200">
                            <?= count($dichVuList) ?> dịch vụ · ~<?= $tongThoiGian ?> phút
                        </span>
                    </div>
                    <div class="divider mt-6"></div>

                    <?php if (empty($dichVuList)): ?>
                        <div class="mt-8 rounded-2xl border border-dashed border-white/20 bg-night-800/60 px-6 py-10 text-center text-sm text-slate-300">
                            Gói này hiện chưa có dịch vụ con nào được cấu hình. Vui lòng liên hệ đội ngũ Stygian Blue để được tư vấn thêm.
                        </div>
                    <?php else: ?>
                        <div class="mt-8 space-y-6">
                            <?php foreach ($dichVuList as $dv): ?>
                                <?php $isRental = ($dv['ID_DV'] == 3); ?>
                                <article class="service-card rounded-3xl px-6 py-6 lg:px-8 lg:py-8">
                                    <div class="flex flex-col gap-6 md:flex-row">
                                        <div class="md:w-48">
                                            <div class="film-frame rounded-3xl">
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
                                                    <h3 class="text-2xl font-display text-white">
                                                        <?= htmlspecialchars($dv['TEN_DV']) ?>
                                                    </h3>
                                                    <p class="mt-2 text-xs uppercase tracking-[0.35em] text-slate-400">
                                                        x<?= (int)$dv['SO_LUONG'] ?> · <?= (int)$dv['THOI_GIAN'] * (int)$dv['SO_LUONG'] ?> phút dự kiến
                                                    </p>
                                                </div>
                                                <div class="text-right">
                                                    <?php if ($isRental): ?>
                                                        <div class="rounded-full border border-emerald-400/40 bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-200">
                                                            Đã bao gồm thiết bị
                                                        </div>
                                                        <div class="mt-2 text-[11px] uppercase tracking-[0.3em] text-slate-400">
                                                            Không phụ thu
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="text-2xl font-semibold text-white">
                                                            <?= formatVND($dv['DON_GIA_AP_DUNG']) ?>
                                                        </div>
                                                        <div class="mt-2 text-[11px] uppercase tracking-[0.3em] text-slate-400">
                                                            Giá ưu đãi trong gói
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <?php if ($isRental): ?>
                                                <div class="mt-6 space-y-5">
                                                    <p class="text-sm font-medium text-slate-200">
                                                        Thiết bị dự kiến được mang tới để đảm bảo chất lượng ánh sáng và chất lượng ảnh cao nhất:
                                                    </p>
                                                    <?php if (empty($thietBiList)): ?>
                                                        <p class="text-sm text-slate-300">
                                                            Gói bao gồm quyền sử dụng toàn bộ thiết bị chuẩn studio (máy ảnh full-frame, hệ thống đèn flash, modifier, phông nền cơ bản).
                                                        </p>
                                                    <?php else: ?>
                                                        <div class="grid gap-4 sm:grid-cols-2">
                                                            <?php foreach ($thietBiList as $tb): ?>
                                                                <div class="rounded-2xl border border-white/10 bg-night-800/70 px-4 py-4">
                                                                    <div class="flex items-center gap-3">
                                                                        <div class="h-14 w-14 overflow-hidden rounded-xl bg-night-900/80">
                                                                            <img
                                                                                src="<?= htmlspecialchars($tb['IMAGE'] ?: 'public/images/placeholder_equipment.jpg') ?>"
                                                                                alt="<?= htmlspecialchars($tb['TEN_TB']) ?>"
                                                                                loading="lazy"
                                                                                class="h-full w-full object-cover"
                                                                            />
                                                                        </div>
                                                                        <div class="flex-1">
                                                                            <div class="text-sm font-semibold text-slate-100">
                                                                                <?= htmlspecialchars($tb['TEN_TB']) ?>
                                                                                <?php if ((int)$tb['SO_LUONG'] > 1): ?>
                                                                                    <span class="text-xs font-normal text-slate-400">x<?= (int)$tb['SO_LUONG'] ?></span>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                            <div class="mt-1 text-[11px] uppercase tracking-[0.3em] text-slate-400">
                                                                                Trạng thái: <?= htmlspecialchars($tb['TINH_TRANG']) ?>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <p class="text-[11px] text-slate-400">
                                                        Danh sách có thể thay đổi bằng thiết bị tương đương nếu lịch studio kín. Thiết bị đặc biệt (flycam, livestream đa cam...) sẽ được báo giá riêng khi phát sinh yêu cầu.
                                                    </p>
                                                </div>
                                            <?php else: ?>
                                                <p class="mt-6 text-sm leading-7 text-slate-200">
                                                    <?= nl2br(htmlspecialchars($dv['MOTA_DV'])) ?>
                                                </p>
                                                <a href="chitiet.php?id=<?= urlencode($dv['ID_DV']) ?>" class="mt-6 inline-flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.35em] text-accent-300 transition hover:text-accent-200">
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

                <article class="section-card rounded-3xl px-8 py-10 lg:px-10">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <span class="text-[11px] uppercase tracking-[0.35em] text-slate-400">Ưu đãi & lịch trình</span>
                            <h2 class="mt-2 text-3xl font-display text-white">Hiệu lực gói dịch vụ</h2>
                        </div>
                        <?php if ($ratingAvg): ?>
                        <div class="inline-flex items-center gap-2 rounded-full border border-yellow-200/40 bg-yellow-400/10 px-4 py-1 text-sm font-semibold text-yellow-200">
                            ★ <?= $ratingAvg ?>/5.0
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="divider mt-6"></div>
                    <div class="mt-6 grid gap-5 md:grid-cols-2">
                        <div class="rounded-2xl border border-white/10 bg-night-800/70 px-5 py-5">
                            <div class="text-[11px] uppercase tracking-[0.35em] text-slate-400">Ưu đãi áp dụng</div>
                            <p class="mt-3 text-sm text-slate-200">
                                <?php if ($goi['HIEU_LUC_TU'] || $goi['HIEU_LUC_DEN']): ?>
                                    Có hiệu lực
                                    <?php if ($goi['HIEU_LUC_TU']): ?>từ <?= formatDate($goi['HIEU_LUC_TU']) ?><?php endif; ?>
                                    <?php if ($goi['HIEU_LUC_DEN']): ?> đến <?= formatDate($goi['HIEU_LUC_DEN']) ?><?php endif; ?>.
                                <?php else: ?>
                                    Linh hoạt theo lịch studio, vui lòng liên hệ để giữ ngày đẹp.
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-night-800/70 px-5 py-5">
                            <div class="text-[11px] uppercase tracking-[0.35em] text-slate-400">Trải nghiệm bao gồm</div>
                            <ul class="mt-3 space-y-2 text-sm text-slate-200">
                                <li>Pre-production meeting và moodboard theo brand.</li>
                                <li>Điều phối lịch trình giữa các hạng mục trong gói.</li>
                                <li>Bàn giao hình ảnh hậu kỳ theo tiêu chuẩn thương hiệu.</li>
                            </ul>
                        </div>
                    </div>
                </article>

                <article class="section-card rounded-3xl px-8 py-10 lg:px-10">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <span class="text-[11px] uppercase tracking-[0.35em] text-slate-400">Client Stories</span>
                            <h2 class="mt-2 text-3xl font-display text-white">Phản hồi từ khách hàng</h2>
                        </div>
                    </div>
                    <?php if (empty($feedbacks)): ?>
                        <p class="mt-8 rounded-2xl border border-dashed border-white/20 bg-night-800/60 px-6 py-6 text-sm text-slate-300">
                            Chưa có đánh giá công khai cho gói này. Chúng tôi sẽ cập nhật ngay khi có câu chuyện mới từ khách hàng.
                        </p>
                    <?php else: ?>
                        <div class="mt-8 grid gap-6 md:grid-cols-2">
                            <?php foreach ($feedbacks as $fb): ?>
                                <div class="rounded-3xl border border-white/15 bg-night-800/80 px-6 py-6 shadow-frame">
                                    <p class="text-sm leading-relaxed text-slate-100 italic">
                                        “<?= htmlspecialchars($fb['NOI_DUNG']) ?>”
                                    </p>
                                    <div class="mt-5 flex items-center justify-between text-xs text-slate-400">
                                        <span class="font-semibold text-slate-200">
                                            <?= htmlspecialchars($fb['ID_TK']) ?>
                                        </span>
                                        <span>⭐ <?= (int)$fb['XEP_HANG_DV'] ?>/5</span>
                                    </div>
                                    <?php if (!empty($fb['NGAY_GUI'])): ?>
                                    <div class="mt-2 text-[11px] text-right text-slate-500">
                                        <?= formatDate($fb['NGAY_GUI']) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>

                <article class="section-card rounded-3xl px-8 py-10 lg:px-10">
                    <div class="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                        <div>
                            <span class="text-[11px] uppercase tracking-[0.35em] text-slate-400">Studio Promise</span>
                            <h2 class="mt-2 text-3xl font-display text-white">Trọn gói, không bỏ sót khoảnh khắc</h2>
                            <p class="mt-4 text-sm leading-7 text-slate-200">
                                Mỗi gói combo của Stygian Blue được xây dựng cho một câu chuyện hoàn chỉnh: từ ý tưởng, hậu trường đến sản phẩm cuối cùng. Đội ngũ phụ trách xuyên suốt để đảm bảo không có chi tiết nào bị bỏ lỡ.
                            </p>
                        </div>
                        <div class="flex flex-col gap-3 text-sm text-slate-200">
                            <span class="inline-flex items-center gap-3 rounded-full border border-white/15 bg-white/5 px-4 py-2">
                                <span class="h-2 w-2 rounded-full bg-accent-500"></span>
                                Lịch trình chụp tối ưu cho từng hạng mục
                            </span>
                            <span class="inline-flex items-center gap-3 rounded-full border border-white/15 bg-white/5 px-4 py-2">
                                <span class="h-2 w-2 rounded-full bg-accent-500"></span>
                                Styling team đồng hành trong suốt buổi
                            </span>
                            <span class="inline-flex items-center gap-3 rounded-full border border-white/15 bg-white/5 px-4 py-2">
                                <span class="h-2 w-2 rounded-full bg-accent-500"></span>
                                Hậu kỳ màu sắc theo định hướng thương hiệu
                            </span>
                        </div>
                    </div>
                </article>
            </section>

            <aside class="xl:w-[360px] flex-shrink-0">
                <div class="floating-panel sticky top-10 rounded-3xl px-7 py-9">
                    <h3 class="text-lg font-display text-white">Thông tin gói</h3>
                    <div class="mt-6 space-y-5 text-sm text-slate-200">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Giá trọn gói</span>
                            <span class="text-xl font-semibold text-white">
                                <?= formatVND($tongGiaGoi) ?>
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Thời lượng dự kiến</span>
                            <span class="font-medium text-slate-100">
                                ~<?= $tongThoiGian ?> phút
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Số dịch vụ</span>
                            <span class="font-medium text-slate-100">
                                <?= count($dichVuList) ?> hạng mục
                            </span>
                        </div>
                        <?php if ($goi['HIEU_LUC_TU'] || $goi['HIEU_LUC_DEN']): ?>
                        <div>
                            <span class="text-slate-400">Hiệu lực ưu đãi</span>
                            <div class="mt-2 text-[11px] uppercase tracking-[0.35em] text-slate-300">
                                <?php if ($goi['HIEU_LUC_TU']): ?>Từ <?= formatDate($goi['HIEU_LUC_TU']) ?><?php endif; ?>
                                <?php if ($goi['HIEU_LUC_DEN']): ?> · Đến <?= formatDate($goi['HIEU_LUC_DEN']) ?><?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($ratingAvg): ?>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Đánh giá trung bình</span>
                            <span class="font-medium text-yellow-200">
                                ★ <?= $ratingAvg ?>/5.0
                            </span>
                        </div>
                        <?php endif; ?>
                        <div>
                            <span class="text-slate-400">Tình trạng</span>
                            <div class="mt-2 inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.35em] <?= $trangThaiColor ?>">
                                <span class="h-2 w-2 rounded-full bg-emerald-300"></span>
                                <?= htmlspecialchars($trangThaiLabel) ?>
                            </div>
                        </div>
                    </div>

                    <a id="booking"
                       href="datlich.php?id_goi=<?= urlencode($goi['ID_GOI']) ?>"
                       class="mt-10 inline-flex w-full items-center justify-center gap-3 rounded-full bg-accent-500 px-6 py-3 text-xs font-semibold uppercase tracking-[0.35em] text-white transition hover:bg-accent-600">
                        Đặt gói ngay
                    </a>

                    <button type="button"
                        class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-full border border-accent-500/40 px-6 py-3 text-xs font-semibold uppercase tracking-[0.35em] text-accent-300 transition hover:border-accent-500 hover:text-accent-200">
                        Đặt lịch tư vấn concept
                    </button>

                    <div class="mt-8 rounded-2xl border border-white/10 bg-white/5 px-5 py-5 text-xs leading-6 text-slate-300">
                        <div class="font-semibold text-slate-100">Cam kết Stygian Blue</div>
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

    <footer class="border-t border-white/5 px-6 py-10 text-center text-[12px] text-slate-500">
        © <?= date('Y') ?> Stygian Blue Studio · Crafted for storytellers in light.
    </footer>

<?php endif; ?>
</body>
</html>
