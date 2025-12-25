<?php
// chitiet.php (phiên bản đồng bộ UI với goi_chitiet.php)

include '../../../database/config.php'; // chỉnh path nếu cần
require_once __DIR__ . '/../../helpers/assets.php';

// Helpers
function dv_img_url(?string $path): string {
    // Giải quyết đường dẫn ảnh lưu trong project hoặc lạc ra docroot
    if (!$path) return sb_asset_href('public/images/placeholder.jpg');
    if (preg_match('~^https?://~i', $path)) return $path;

    $normalized = ltrim(str_replace('\\', '/', $path), '/');
    $projectPath = rtrim(sb_project_root(), '/\\') . '/' . $normalized;
    $docRootPath = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\') . '/' . $normalized;

    if (is_file($projectPath)) {
        return sb_asset_href($normalized);
    }

    // Fallback: file nằm ngoài project ở docroot/public/...
    if (is_file($docRootPath)) {
        return '/' . $normalized;
    }

    // Nếu đã có prefix public/images thì dùng base project, ngược lại mặc định thư mục dịch vụ
    if (strpos($normalized, 'public/images/') === 0) {
        return sb_asset_href($normalized);
    }

    return sb_asset_href('public/images/dichvu/' . $normalized);
}

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
            $rowImg['URL'] = dv_img_url($rowImg['URL']);
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
// Ưu tiên ảnh bìa từ bảng dich_vu
if ($dv && !empty($dv['FALLBACK_IMAGE'])) {
    $heroImg = dv_img_url($dv['FALLBACK_IMAGE']);
} elseif (!empty($hinhAnhList)) {
    // fallback sang ảnh mẫu đầu tiên
    $heroImg = $hinhAnhList[0]['URL'];
} else {
    $heroImg = dv_img_url('public/images/placeholder.jpg');
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
    <?= sb_tailwind_link_tag(); ?>
    <link rel="stylesheet" href="../../admin/assets/css/detail-pages-light.css" />
</head>
<body class="relative bg-[var(--bg-primary)] text-[var(--text-primary)] font-body antialiased">

<?php if (!$idDv || !$dv): ?>
    <section class="relative min-h-screen flex items-center justify-center px-6 py-24">
        <div class="rounded-3xl border border-[var(--photo-border)] bg-white max-w-lg w-full px-10 py-12 text-center">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-[var(--accent-primary)]/15 text-3xl">
                😕
            </div>
            <?php if (!$idDv): ?>
                <h1 class="mt-6 text-2xl font-display text-[var(--text-primary)]">
                    Dịch vụ không hợp lệ
                </h1>
                <p class="mt-4 text-sm text-[var(--text-secondary)] leading-relaxed">
                    Thiếu tham số 
                    <code class="rounded-md bg-[var(--bg-secondary)] px-2 py-1 font-mono text-[var(--text-primary)]">id</code>
                    trên URL.<br/>
                    Ví dụ:
                    <span class="rounded-md bg-[var(--bg-secondary)] px-2 py-1 font-mono text-[var(--text-primary)]">
                        chitiet.php?id=1
                    </span>
                </p>
            <?php else: ?>
                <h1 class="mt-6 text-2xl font-display text-[var(--text-primary)]">
                    Không tìm thấy dịch vụ
                </h1>
                <p class="mt-4 text-sm text-[var(--text-secondary)] leading-relaxed">
                    Dịch vụ (ID <?= htmlspecialchars($idDv) ?>) không tồn tại hoặc đã bị gỡ.
                </p>
            <?php endif; ?>

            <a href="dichvu.php"
               class="mt-8 inline-block rounded-full bg-[var(--accent-primary)] px-6 py-3 text-sm font-semibold uppercase tracking-widest text-white transition hover:bg-[var(--accent-secondary)]">
                Quay về danh sách dịch vụ
            </a>
        </div>
    </section>

<?php else: ?>

    <section class="hero-section sb-hero relative flex min-h-[65vh] items-end overflow-hidden bg-[var(--bg-primary)] text-white">
        <img
            src="<?= htmlspecialchars($heroImg) ?>"
            alt="<?= htmlspecialchars($dv['TEN_DV']) ?>"
            class="hero-image absolute inset-0 h-full w-full object-cover"
        />
        

        <div class="relative z-10 w-full pb-20">
            <div class="mx-auto max-w-6xl px-6 lg:px-10">
                <nav data-crumbs class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.35em] text-white/80 drop-shadow">
                    <a href="dichvu.php" class="transition hover:text-white">Danh mục dịch vụ</a>
                    <span class="text-white/60">•</span>
                    <span class="text-white/80"><?= htmlspecialchars($dv['TEN_DV']) ?></span>
                </nav>

                <div class="mt-8 inline-flex items-center gap-3 rounded-full px-5 py-2 text-[11px] font-semibold uppercase tracking-[0.35em] bg-emerald-100 text-emerald-700 border border-emerald-300">
                    <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span>
                    <span class="text-current">
                        <?= htmlspecialchars($trangThaiLabel) ?>
                    </span>
                </div>

                <h1 class="hero-title mt-6 max-w-3xl text-4xl font-display leading-tight drop-shadow-[0_8px_18px_rgba(0,0,0,0.35)] sm:text-5xl">
                    <?= htmlspecialchars($dv['TEN_DV']) ?>
                </h1>

                <p class="hero-subtitle mt-4 max-w-2xl text-base drop-shadow-[0_6px_14px_rgba(0,0,0,0.35)]">
                    Một trải nghiệm nhiếp ảnh bespoke với hệ thống ánh sáng cinema, styling cá nhân hóa và quy trình hậu kỳ signature của Stygian Blue Studio.
                </p>

                <div class="mt-10 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-3xl border border-[var(--photo-border)] bg-white/96 backdrop-blur-md px-6 py-7 text-left shadow-xl shadow-black/15">
                        <span class="text-[11px] uppercase tracking-[0.35em] text-slate-800">Giá dịch vụ</span>
                        <p class="mt-3 text-2xl font-semibold text-slate-900 drop-shadow-[0_4px_10px_rgba(255,255,255,0.45)]">
                            <?= formatVND($giaHienHanh) ?>
                        </p>
                        <p class="mt-2 text-xs text-slate-700">Bao gồm hậu kỳ tiêu chuẩn studio</p>
                    </div>
                    <div class="rounded-3xl border border-[var(--photo-border)] bg-white/96 backdrop-blur-md px-6 py-7 text-left shadow-xl shadow-black/15">
                        <span class="text-[11px] uppercase tracking-[0.35em] text-slate-800">Thời lượng</span>
                        <p class="mt-3 text-2xl font-semibold text-slate-900 drop-shadow-[0_4px_10px_rgba(255,255,255,0.45)]">
                            ~<?= (int)$dv['THOI_GIAN'] ?> phút
                        </p>
                        <p class="mt-2 text-xs text-slate-700">Chuẩn bị set ánh sáng & brief trước buổi chụp</p>
                    </div>
                    <div class="rounded-3xl border border-[var(--photo-border)] bg-white/96 backdrop-blur-md px-6 py-7 text-left shadow-xl shadow-black/15">
                        <span class="text-[11px] uppercase tracking-[0.35em] text-slate-800">Trải nghiệm</span>
                        <p class="mt-3 text-2xl font-semibold text-slate-900 drop-shadow-[0_4px_10px_rgba(255,255,255,0.45)]">
                            <?php if ($ratingAvg): ?>★ <?= $ratingAvg ?>/5.0<?php else: ?>Đang cập nhật<?php endif; ?>
                        </p>
                        <p class="mt-2 text-xs text-slate-700">Chia sẻ bởi cộng đồng khách hàng thân thiết</p>
                    </div>
                </div>

                <div class="mt-12 flex flex-wrap items-center gap-4">
                    <a href="/StygianBlue/app/Pages/Views/lienhe.php?id_dv=<?= urlencode($dv['ID_DV']) ?>"
                       class="group inline-flex items-center gap-3 rounded-full bg-[var(--accent-primary)] px-7 py-3 text-xs font-semibold uppercase tracking-[0.4em] text-white shadow-md transition hover:bg-[var(--accent-secondary)]">
                        <span>Đặt lịch ngay</span>
                        <span class="text-lg transition group-hover:translate-x-1">›</span>
                    </a>
                    <a href="#gallery" class="inline-flex items-center gap-3 rounded-full border border-[var(--photo-border)] px-7 py-3 text-xs font-semibold uppercase tracking-[0.4em] text-[var(--text-primary)] transition hover:bg-[var(--bg-secondary)]">
                        Xem thư viện hình
                    </a>
                </div>
            </div>
        </div>
    </section>

    <main class="relative z-10 -mt-16 lg:-mt-24 pb-24 pt-10">
        <div class="mx-auto flex max-w-7xl flex-col gap-12 px-4 sm:px-6 lg:flex-row lg:gap-16 lg:px-10">
            <section class="flex-1 space-y-10" id="story">
                <article class="rounded-3xl border border-[var(--photo-border)] bg-white px-8 py-10 lg:px-12 lg:py-12">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <span class="text-[11px] uppercase tracking-[0.35em] text-[var(--text-tertiary)]">Creative Brief</span>
                            <h2 class="mt-2 text-3xl font-display text-[var(--text-primary)]">Câu chuyện dịch vụ</h2>
                        </div>
                        <span class="rounded-full border border-[var(--photo-border)] bg-[var(--bg-secondary)] px-4 py-1 text-[11px] font-semibold uppercase tracking-[0.35em] text-[var(--text-secondary)]">
                            <?= (int)$dv['THOI_GIAN'] ?> phút onsite
                        </span>
                    </div>
                    <div class="mt-6 h-px bg-[var(--photo-border)]"></div>
                    <p class="mt-6 text-sm leading-7 text-[var(--text-secondary)]">
                        <?= nl2br(htmlspecialchars($dv['MOTA_DV'])) ?>
                    </p>
                    <div class="mt-8 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl border border-[var(--photo-border)] bg-[var(--bg-secondary)] p-5">
                            <div class="text-[11px] uppercase tracking-[0.35em] text-[var(--text-tertiary)]">Phong cách gợi ý</div>
                            <p class="mt-2 text-sm text-[var(--text-secondary)]">Chân dung nghệ sĩ, profile doanh nhân, lookbook sáng tạo.</p>
                        </div>
                        <div class="rounded-2xl border border-[var(--photo-border)] bg-[var(--bg-secondary)] p-5">
                            <div class="text-[11px] uppercase tracking-[0.35em] text-[var(--text-tertiary)]">Đội ngũ đồng hành</div>
                            <p class="mt-2 text-sm text-[var(--text-secondary)]">Nhiếp ảnh gia chuyên nghiệp, stylist theo yêu cầu và hỗ trợ ánh sáng tại chỗ.</p>
                        </div>
                    </div>
                </article>

                <article class="rounded-3xl border border-[var(--photo-border)] bg-white px-8 py-10 lg:px-12 lg:py-12" id="gallery">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <span class="text-[11px] uppercase tracking-[0.35em] text-[var(--text-tertiary)]">Visual Reference</span>
                            <h2 class="mt-2 text-3xl font-display text-[var(--text-primary)]">Hình ảnh mẫu</h2>
                        </div>
                        <?php if (!empty($hinhAnhList)): ?>
                        <div class="text-xs font-medium uppercase tracking-[0.3em] text-[var(--text-tertiary)]">
                            <?= count($hinhAnhList) ?> khoảnh khắc tuyển chọn
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($hinhAnhList)): ?>
                        <div class="mt-8 rounded-2xl border border-dashed border-[var(--photo-border)] bg-[var(--bg-secondary)] px-6 py-10 text-center text-sm text-[var(--text-secondary)]">
                            Chưa có thư viện hình mẫu cho dịch vụ này. Đội ngũ Stygian Blue sẽ cập nhật ngay khi dự án mới hoàn thành.
                        </div>
                    <?php else: ?>
                        <div class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                            <?php foreach ($hinhAnhList as $ha): ?>
                                <figure class="rounded-3xl overflow-hidden border border-[var(--photo-border)]">
                                    <img
                                        src="<?= htmlspecialchars($ha['URL']) ?>"
                                        alt="<?= htmlspecialchars($ha['ALT_TEXT'] ?? $dv['TEN_DV']) ?>"
                                        loading="lazy"
                                        class="h-56 w-full object-cover transition duration-500 hover:scale-[1.03]"
                                    />
                                    <?php if (!empty($ha['ALT_TEXT'])): ?>
                                    <figcaption class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent px-4 py-3 text-[10px] uppercase tracking-[0.35em] text-white">
                                        <?= htmlspecialchars($ha['ALT_TEXT']) ?>
                                    </figcaption>
                                    <?php endif; ?>
                                </figure>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>

                <article class="rounded-3xl border border-[var(--photo-border)] bg-white px-8 py-10 lg:px-12 lg:py-12">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <span class="text-[11px] uppercase tracking-[0.35em] text-[var(--text-tertiary)]">Client Stories</span>
                            <h2 class="mt-2 text-3xl font-display text-[var(--text-primary)]">Phản hồi từ khách hàng</h2>
                        </div>
                        <?php if ($ratingAvg): ?>
                        <div class="inline-flex items-center gap-2 rounded-full border border-yellow-300 bg-yellow-50 px-4 py-1 text-sm font-semibold text-yellow-700">
                            ★ <?= $ratingAvg ?>/5.0
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($feedbacks)): ?>
                        <p class="mt-8 rounded-2xl border border-dashed border-[var(--photo-border)] bg-[var(--bg-secondary)] px-6 py-6 text-sm text-[var(--text-secondary)]">
                            Chưa có đánh giá công khai cho dịch vụ này. Hãy là người đầu tiên chia sẻ trải nghiệm cùng Stygian Blue Studio.
                        </p>
                    <?php else: ?>
                        <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-1">
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
                                        <?= formatDateTimeVN($fb['NGAY_GUI']) ?>
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
                            <h2 class="mt-2 text-3xl font-display text-[var(--text-primary)]">Trải nghiệm được cá nhân hóa</h2>
                            <p class="mt-4 text-sm leading-7 text-[var(--text-secondary)]">
                                Từ buổi pre-shooting consultation đến hậu kỳ, đội ngũ Stygian Blue đồng hành để mỗi khoảnh khắc đều kể đúng câu chuyện của bạn.
                            </p>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1 text-sm text-[var(--text-secondary)]">
                            <span class="inline-flex items-center gap-3 rounded-full border border-[var(--photo-border)] bg-[var(--bg-secondary)] px-4 py-2">
                                <span class="h-2 w-2 rounded-full bg-[var(--accent-primary)]"></span>
                                Briefing sáng tạo trước buổi chụp
                            </span>
                            <span class="inline-flex items-center gap-3 rounded-full border border-[var(--photo-border)] bg-[var(--bg-secondary)] px-4 py-2">
                                <span class="h-2 w-2 rounded-full bg-[var(--accent-primary)]"></span>
                                Styling & direction chuyên nghiệp tại studio
                            </span>
                            <span class="inline-flex items-center gap-3 rounded-full border border-[var(--photo-border)] bg-[var(--bg-secondary)] px-4 py-2">
                                <span class="h-2 w-2 rounded-full bg-[var(--accent-primary)]"></span>
                                Hậu kỳ màu sắc theo phong cách thương hiệu
                            </span>
                        </div>
                    </div>
                </article>
            </section>

            <aside class="lg:w-80 flex-shrink-0">
                <div class="sticky top-10 rounded-3xl border border-[var(--photo-border)] bg-white px-7 py-9">
                    <h3 class="text-lg font-display text-[var(--text-primary)]">Thông tin nhanh</h3>
                    <div class="mt-6 space-y-5 text-sm text-[var(--text-secondary)]">
                        <div class="flex items-center justify-between">
                            <span class="text-[var(--text-tertiary)]">Giá dịch vụ</span>
                            <span class="text-xl font-semibold text-[var(--accent-primary)]">
                                <?= formatVND($giaHienHanh) ?>
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-[var(--text-tertiary)]">Thời lượng dự kiến</span>
                            <span class="font-medium text-[var(--text-primary)]">
                                ~<?= (int)$dv['THOI_GIAN'] ?> phút
                            </span>
                        </div>
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
                              href="/StygianBlue/app/Pages/Views/lienhe.php?id_dv=<?= urlencode($dv['ID_DV']) ?>"
                       class="mt-10 inline-flex w-full items-center justify-center gap-3 rounded-full bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-3 text-xs font-bold uppercase tracking-[0.35em] text-white shadow-lg transition hover:from-indigo-700 hover:to-purple-700 hover:shadow-xl">
                        Đặt lịch ngay
                    </a>

                    <button type="button"
                        onclick="document.querySelector('.sb-ai-chat-toggle')?.click()"
                        class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-full border-2 border-sky-500 bg-white px-6 py-3 text-xs font-semibold uppercase tracking-[0.35em] text-sky-600 transition hover:border-sky-600 hover:bg-sky-50 hover:text-sky-700">
                        Tư vấn
                    </button>

                    <div class="mt-8 rounded-2xl border border-[var(--photo-border)] bg-[var(--bg-secondary)] px-5 py-5 text-xs leading-6 text-[var(--text-secondary)]">
                        <div class="font-semibold text-[var(--text-primary)]">Cam kết Stygian Blue</div>
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

    <footer class="border-t border-[var(--photo-border)] px-6 py-10 text-center text-[12px] text-[var(--text-tertiary)]">
        © <?= date('Y') ?> Stygian Blue Studio · Crafted for storytellers in light.
    </footer>

<?php endif; ?>

</body>
</html>
