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
$trangThaiColor = 'text-emerald-200 bg-emerald-500/10 border border-emerald-400/40';

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
            background: radial-gradient(circle at 25% 20%, rgba(255,255,255,0.18) 0%, rgba(8,47,73,0.05) 38%, rgba(2,6,23,0.75) 100%);
        }
        .grain-overlay {
            background-image: linear-gradient(120deg, rgba(15,23,42,0.35) 0%, rgba(30,41,59,0.45) 45%, rgba(8,47,73,0.5) 100%);
            filter: saturate(1.1) contrast(1.05);
        }
        .section-card {
            background: rgba(12, 18, 32, 0.82);
            border: 1px solid rgba(148, 163, 184, 0.18);
            box-shadow: 0 30px 60px -40px rgba(15, 118, 110, 0.45);
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
        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent 0%, rgba(14, 165, 233, 0.5) 50%, transparent 100%);
        }
    </style>
</head>
<body class="relative bg-night-900 text-slate-100 font-body antialiased">
<div class="fixed inset-0 -z-10 opacity-[0.33] grain-overlay"></div>

<?php if (!$idDv || !$dv): ?>
    <section class="relative min-h-screen flex items-center justify-center px-6 py-24">
        <div class="section-card max-w-lg w-full rounded-3xl px-10 py-12 text-center">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-accent-500/15 text-3xl">
                😕
            </div>
            <?php if (!$idDv): ?>
                <h1 class="mt-6 text-2xl font-display text-white">
                    Dịch vụ không hợp lệ
                </h1>
                <p class="mt-4 text-sm text-slate-300 leading-relaxed">
                    Thiếu tham số 
                    <code class="rounded-md bg-night-800 px-2 py-1 font-mono text-slate-100">id</code>
                    trên URL.<br/>
                    Ví dụ:
                    <span class="rounded-md bg-night-800 px-2 py-1 font-mono text-slate-100">
                        chitiet.php?id=1
                    </span>
                </p>
            <?php else: ?>
                <h1 class="mt-6 text-2xl font-display text-white">
                    Không tìm thấy dịch vụ
                </h1>
                <p class="mt-4 text-sm text-slate-300 leading-relaxed">
                    Dịch vụ (ID <?= htmlspecialchars($idDv) ?>) không tồn tại hoặc đã bị gỡ.
                </p>
            <?php endif; ?>

            <a href="dichvu.php"
               class="mt-8 inline-block rounded-full bg-accent-500 px-6 py-3 text-sm font-semibold uppercase tracking-widest text-white transition hover:bg-accent-600">
                Quay về danh sách dịch vụ
            </a>
        </div>
    </section>

<?php else: ?>

    <section class="relative flex min-h-[65vh] items-end overflow-hidden">
        <img
            src="<?= htmlspecialchars($heroImg) ?>"
            alt="<?= htmlspecialchars($dv['TEN_DV']) ?>"
            class="absolute inset-0 h-full w-full object-cover opacity-80"
        />
        <div class="absolute inset-0 bg-gradient-to-t from-night-900 via-night-900/70 to-transparent"></div>
        <div class="absolute inset-0 hero-overlay mix-blend-screen opacity-80"></div>

        <div class="relative z-10 w-full pb-20">
            <div class="mx-auto max-w-6xl px-6 lg:px-10">
                <nav class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.35em] text-slate-300/80">
                    <a href="dichvu.php" class="transition hover:text-white">Danh mục dịch vụ</a>
                    <span class="text-slate-500">•</span>
                    <span class="text-white/80"><?= htmlspecialchars($dv['TEN_DV']) ?></span>
                </nav>

                <div class="mt-8 inline-flex items-center gap-3 rounded-full px-5 py-2 text-[11px] font-semibold uppercase tracking-[0.35em] <?= $trangThaiColor ?>">
                    <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-300"></span>
                    <span class="text-current">
                        <?= htmlspecialchars($trangThaiLabel) ?>
                    </span>
                </div>

                <h1 class="mt-6 max-w-3xl text-4xl font-display leading-tight text-white sm:text-5xl">
                    <?= htmlspecialchars($dv['TEN_DV']) ?>
                </h1>

                <p class="mt-4 max-w-2xl text-base text-slate-200/80">
                    Một trải nghiệm nhiếp ảnh bespoke với hệ thống ánh sáng cinema, styling cá nhân hóa và quy trình hậu kỳ signature của Stygian Blue Studio.
                </p>

                <div class="mt-10 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-3xl border border-white/15 bg-white/10 px-5 py-6 text-left shadow-frame">
                        <span class="text-[11px] uppercase tracking-[0.35em] text-slate-300">Giá dịch vụ</span>
                        <p class="mt-3 text-2xl font-semibold text-white">
                            <?= formatVND($giaHienHanh) ?>
                        </p>
                        <p class="mt-2 text-xs text-slate-300">Bao gồm hậu kỳ tiêu chuẩn studio</p>
                    </div>
                    <div class="rounded-3xl border border-white/15 bg-white/10 px-5 py-6 text-left shadow-frame">
                        <span class="text-[11px] uppercase tracking-[0.35em] text-slate-300">Thời lượng</span>
                        <p class="mt-3 text-2xl font-semibold text-white">
                            ~<?= (int)$dv['THOI_GIAN'] ?> phút
                        </p>
                        <p class="mt-2 text-xs text-slate-300">Chuẩn bị set ánh sáng & brief trước buổi chụp</p>
                    </div>
                    <div class="rounded-3xl border border-white/15 bg-white/10 px-5 py-6 text-left shadow-frame">
                        <span class="text-[11px] uppercase tracking-[0.35em] text-slate-300">Trải nghiệm</span>
                        <p class="mt-3 text-2xl font-semibold text-white">
                            <?php if ($ratingAvg): ?>★ <?= $ratingAvg ?>/5.0<?php else: ?>Đang cập nhật<?php endif; ?>
                        </p>
                        <p class="mt-2 text-xs text-slate-300">Chia sẻ bởi cộng đồng khách hàng thân thiết</p>
                    </div>
                </div>

                <div class="mt-12 flex flex-wrap items-center gap-4">
                    <a href="#booking" class="group inline-flex items-center gap-3 rounded-full bg-accent-500 px-7 py-3 text-xs font-semibold uppercase tracking-[0.4em] text-white shadow-glow transition hover:bg-accent-600">
                        <span>Đặt lịch ngay</span>
                        <span class="text-lg transition group-hover:translate-x-1">›</span>
                    </a>
                    <a href="#gallery" class="inline-flex items-center gap-3 rounded-full border border-white/20 px-7 py-3 text-xs font-semibold uppercase tracking-[0.4em] text-slate-200 transition hover:border-white/40 hover:text-white">
                        Xem thư viện hình
                    </a>
                </div>
            </div>
        </div>
    </section>

    <main class="relative z-10 -mt-16 lg:-mt-24 pb-24 pt-10">
        <div class="mx-auto flex max-w-6xl flex-col gap-12 px-6 lg:flex-row lg:px-10">
            <section class="flex-1 space-y-10" id="story">
                <article class="section-card rounded-3xl px-8 py-10 lg:px-10">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <span class="text-[11px] uppercase tracking-[0.35em] text-slate-400">Creative Brief</span>
                            <h2 class="mt-2 text-3xl font-display text-white">Câu chuyện dịch vụ</h2>
                        </div>
                        <span class="rounded-full border border-white/15 bg-white/5 px-4 py-1 text-[11px] font-semibold uppercase tracking-[0.35em] text-slate-200">
                            <?= (int)$dv['THOI_GIAN'] ?> phút onsite
                        </span>
                    </div>
                    <div class="divider mt-6"></div>
                    <p class="mt-6 text-sm leading-7 text-slate-200">
                        <?= nl2br(htmlspecialchars($dv['MOTA_DV'])) ?>
                    </p>
                    <div class="mt-8 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl border border-white/10 bg-night-800/80 p-5">
                            <div class="text-[11px] uppercase tracking-[0.35em] text-slate-400">Phong cách gợi ý</div>
                            <p class="mt-2 text-sm text-slate-200">Chân dung nghệ sĩ, profile doanh nhân, lookbook sáng tạo.</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-night-800/80 p-5">
                            <div class="text-[11px] uppercase tracking-[0.35em] text-slate-400">Đội ngũ đồng hành</div>
                            <p class="mt-2 text-sm text-slate-200">Nhiếp ảnh gia chuyên nghiệp, stylist theo yêu cầu và hỗ trợ ánh sáng tại chỗ.</p>
                        </div>
                    </div>
                </article>

                <article class="section-card rounded-3xl px-8 py-10 lg:px-10" id="gallery">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <span class="text-[11px] uppercase tracking-[0.35em] text-slate-400">Visual Reference</span>
                            <h2 class="mt-2 text-3xl font-display text-white">Hình ảnh mẫu</h2>
                        </div>
                        <?php if (!empty($hinhAnhList)): ?>
                        <div class="text-xs font-medium uppercase tracking-[0.3em] text-slate-300">
                            <?= count($hinhAnhList) ?> khoảnh khắc tuyển chọn
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($hinhAnhList)): ?>
                        <div class="mt-8 rounded-2xl border border-dashed border-white/20 bg-night-800/60 px-6 py-10 text-center text-sm text-slate-300">
                            Chưa có thư viện hình mẫu cho dịch vụ này. Đội ngũ Stygian Blue sẽ cập nhật ngay khi dự án mới hoàn thành.
                        </div>
                    <?php else: ?>
                        <div class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                            <?php foreach ($hinhAnhList as $ha): ?>
                                <figure class="film-frame rounded-3xl">
                                    <img
                                        src="<?= htmlspecialchars($ha['URL']) ?>"
                                        alt="<?= htmlspecialchars($ha['ALT_TEXT'] ?? $dv['TEN_DV']) ?>"
                                        loading="lazy"
                                        class="h-56 w-full object-cover transition duration-500 hover:scale-[1.03]"
                                    />
                                    <?php if (!empty($ha['ALT_TEXT'])): ?>
                                    <figcaption class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent px-4 py-3 text-[10px] uppercase tracking-[0.35em] text-slate-100">
                                        <?= htmlspecialchars($ha['ALT_TEXT']) ?>
                                    </figcaption>
                                    <?php endif; ?>
                                </figure>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>

                <article class="section-card rounded-3xl px-8 py-10 lg:px-10">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <span class="text-[11px] uppercase tracking-[0.35em] text-slate-400">Client Stories</span>
                            <h2 class="mt-2 text-3xl font-display text-white">Phản hồi từ khách hàng</h2>
                        </div>
                        <?php if ($ratingAvg): ?>
                        <div class="inline-flex items-center gap-2 rounded-full border border-yellow-200/40 bg-yellow-400/10 px-4 py-1 text-sm font-semibold text-yellow-200">
                            ★ <?= $ratingAvg ?>/5.0
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($feedbacks)): ?>
                        <p class="mt-8 rounded-2xl border border-dashed border-white/20 bg-night-800/60 px-6 py-6 text-sm text-slate-300">
                            Chưa có đánh giá công khai cho dịch vụ này. Hãy là người đầu tiên chia sẻ trải nghiệm cùng Stygian Blue Studio.
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
                                        <?= formatDateTimeVN($fb['NGAY_GUI']) ?>
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
                            <h2 class="mt-2 text-3xl font-display text-white">Trải nghiệm được cá nhân hóa</h2>
                            <p class="mt-4 text-sm leading-7 text-slate-200">
                                Từ buổi pre-shooting consultation đến hậu kỳ, đội ngũ Stygian Blue đồng hành để mỗi khoảnh khắc đều kể đúng câu chuyện của bạn.
                            </p>
                        </div>
                        <div class="flex flex-col gap-3 text-sm text-slate-200">
                            <span class="inline-flex items-center gap-3 rounded-full border border-white/15 bg-white/5 px-4 py-2">
                                <span class="h-2 w-2 rounded-full bg-accent-500"></span>
                                Briefing sáng tạo trước buổi chụp
                            </span>
                            <span class="inline-flex items-center gap-3 rounded-full border border-white/15 bg-white/5 px-4 py-2">
                                <span class="h-2 w-2 rounded-full bg-accent-500"></span>
                                Styling & direction chuyên nghiệp tại studio
                            </span>
                            <span class="inline-flex items-center gap-3 rounded-full border border-white/15 bg-white/5 px-4 py-2">
                                <span class="h-2 w-2 rounded-full bg-accent-500"></span>
                                Hậu kỳ màu sắc theo phong cách thương hiệu
                            </span>
                        </div>
                    </div>
                </article>
            </section>

            <aside class="xl:w-[340px] flex-shrink-0">
                <div class="floating-panel sticky top-10 rounded-3xl px-7 py-9">
                    <h3 class="text-lg font-display text-white">Thông tin nhanh</h3>
                    <div class="mt-6 space-y-5 text-sm text-slate-200">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Giá dịch vụ</span>
                            <span class="text-xl font-semibold text-white">
                                <?= formatVND($giaHienHanh) ?>
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Thời lượng dự kiến</span>
                            <span class="font-medium text-slate-100">
                                ~<?= (int)$dv['THOI_GIAN'] ?> phút
                            </span>
                        </div>
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
                       href="datlich.php?id_dv=<?= urlencode($dv['ID_DV']) ?>"
                       class="mt-10 inline-flex w-full items-center justify-center gap-3 rounded-full bg-accent-500 px-6 py-3 text-xs font-semibold uppercase tracking-[0.35em] text-white transition hover:bg-accent-600">
                        Đặt lịch dịch vụ này
                    </a>

                    <button type="button"
                        class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-full border border-accent-500/40 px-6 py-3 text-xs font-semibold uppercase tracking-[0.35em] text-accent-300 transition hover:border-accent-500 hover:text-accent-200">
                        Nhờ tư vấn viên trước
                    </button>

                    <div class="mt-8 rounded-2xl border border-white/10 bg-white/5 px-5 py-5 text-xs leading-6 text-slate-300">
                        <div class="font-semibold text-slate-100">Cam kết Stygian Blue</div>
                        <ul class="mt-3 space-y-2">
                            <li>Ánh sáng studio và thiết bị chuẩn điện ảnh.</li>
                            <li>Hậu kỳ chỉnh màu signature của Stygian Blue.</li>
                            <li>Bảo mật dữ liệu và quyền riêng tư tuyệt đối.</li>
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
