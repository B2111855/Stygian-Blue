<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../../database/config.php';

$staffId = $_SESSION['ID_TK'] ?? null;
if (!$staffId) {
    echo '<div class="rounded-xl bg-white p-6 text-center text-red-600 shadow">Bạn chưa đăng nhập.</div>';
    return;
}

function fetchAssignedServices(mysqli $conn, string $staffId): array
{
    $services = [];
    $stmt = $conn->prepare('
        SELECT DISTINCT dv.ID_DV, dv.TEN_DV
        FROM phan_cong_nhan_vien pc
        JOIN lich_hen lh ON lh.ID_LICHHEN = pc.ID_LICHHEN
        JOIN dich_vu dv ON dv.ID_DV = lh.ID_DV
        WHERE pc.ID_TK = ?
        ORDER BY dv.TEN_DV ASC
    ');

    if ($stmt) {
        $stmt->bind_param('s', $staffId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $services[] = [
                'id'   => (int)($row['ID_DV'] ?? 0),
                'name' => $row['TEN_DV'] ?? ''
            ];
        }
        $stmt->close();
    }

    return $services;
}

function buildFeedbackQuery(array $filters, string $staffId): array
{
    $conditions = ['pc.ID_TK = ?'];
    $params = [$staffId];
    $types = 's';

    if (!empty($filters['service'])) {
        $conditions[] = 'dv.ID_DV = ?';
        $params[] = (int)$filters['service'];
        $types .= 'i';
    }

    if (!empty($filters['rating_min'])) {
        $conditions[] = 'ph.XEP_HANG_DV >= ?';
        $params[] = (int)$filters['rating_min'];
        $types .= 'i';
    }

    if ($filters['has_comment'] === 'with') {
        $conditions[] = "(ph.NOI_DUNG IS NOT NULL AND ph.NOI_DUNG <> '')";
    } elseif ($filters['has_comment'] === 'without') {
        $conditions[] = "(ph.NOI_DUNG IS NULL OR ph.NOI_DUNG = '')";
    }

    if ($filters['keyword'] !== '') {
        $conditions[] = '(kh.HO_TEN LIKE ? OR ph.NOI_DUNG LIKE ? OR dv.TEN_DV LIKE ?)';
        $keyword = '%' . $filters['keyword'] . '%';
        $params[] = $keyword;
        $params[] = $keyword;
        $params[] = $keyword;
        $types .= 'sss';
    }

    $sql = '
        SELECT DISTINCT
            ph.NGAY_GUI,
            ph.XEP_HANG_DV,
            ph.NOI_DUNG,
            dv.TEN_DV,
            dv.ID_DV,
            kh.HO_TEN AS ten_khach_hang,
            lh.ID_LICHHEN,
            lh.THOI_GIAN_BAT_DAU
        FROM phan_cong_nhan_vien pc
        JOIN lich_hen lh ON lh.ID_LICHHEN = pc.ID_LICHHEN
        JOIN dich_vu dv ON dv.ID_DV = lh.ID_DV
        JOIN khach_hang kh ON kh.ID_TK = lh.ID_TK
        JOIN phan_hoi_cua_khach_hang ph ON ph.ID_TK = lh.ID_TK AND ph.ID_DV = lh.ID_DV
        WHERE ' . implode(' AND ', $conditions) . '
        ORDER BY ph.NGAY_GUI DESC, lh.THOI_GIAN_BAT_DAU DESC
    ';

    return [$sql, $types, $params];
}

function executeFeedbackQuery(mysqli $conn, string $sql, string $types, array $params): array
{
    $items = [];
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $items;
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();

    return $items;
}

function summarizeFeedback(array $items): array
{
    $total = count($items);
    if ($total === 0) {
        return [
            'total'          => 0,
            'average'        => null,
            'positiveRatio'  => null,
            'latestDate'     => null,
        ];
    }

    $sum = 0;
    $positive = 0;
    $latest = null;

    foreach ($items as $item) {
        $rating = (int)($item['XEP_HANG_DV'] ?? 0);
        $sum += $rating;
        if ($rating >= 4) {
            $positive++;
        }

        if (!empty($item['NGAY_GUI'])) {
            $time = strtotime($item['NGAY_GUI']);
            if ($time !== false && ($latest === null || $time > $latest)) {
                $latest = $time;
            }
        }
    }

    return [
        'total'          => $total,
        'average'        => $sum / $total,
        'positiveRatio'  => $total > 0 ? ($positive / $total) : null,
        'latestDate'     => $latest,
    ];
}

function formatDateTime(?string $value): string
{
    if (!$value) {
        return '—';
    }
    try {
        $dt = new DateTime($value);
        return $dt->format('d/m/Y H:i');
    } catch (Exception $e) {
        return $value;
    }
}

$filters = [
    'service'     => (int)($_GET['service'] ?? 0),
    'rating_min'  => isset($_GET['rating_min']) ? (int)$_GET['rating_min'] : 0,
    'has_comment' => $_GET['has_comment'] ?? '',
    'keyword'     => trim($_GET['keyword'] ?? ''),
];

// Normalize filters
if ($filters['service'] <= 0) {
    $filters['service'] = 0;
}
if (!in_array($filters['has_comment'], ['with', 'without'], true)) {
    $filters['has_comment'] = '';
}
if ($filters['rating_min'] < 1 || $filters['rating_min'] > 5) {
    $filters['rating_min'] = 0;
}

$services = fetchAssignedServices($conn, $staffId);
[$feedbackSql, $paramTypes, $paramValues] = buildFeedbackQuery($filters, $staffId);
$feedbackItems = executeFeedbackQuery($conn, $feedbackSql, $paramTypes, $paramValues);
$summary = summarizeFeedback($feedbackItems);
?>

<div class="space-y-8 rounded-2xl border border-gray-200 bg-white/90 p-8 shadow-xl fade-in">
    <header class="flex flex-col gap-2 text-center md:text-left">
        <h1 class="text-3xl font-semibold text-indigo-700">Đánh giá lịch hẹn của bạn</h1>
        <p class="text-sm text-gray-600">Chỉ hiển thị phản hồi từ những lịch hẹn bạn đã tham gia. Sử dụng bộ lọc để tìm nhanh theo dịch vụ, xếp hạng hoặc từ khóa.</p>
    </header>

    <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Tổng phản hồi</p>
            <p class="mt-2 text-3xl font-semibold text-gray-900"><?= $summary['total'] ?></p>
            <p class="mt-1 text-xs text-gray-400">Số phản hồi từ khách hàng của bạn.</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Điểm trung bình</p>
            <p class="mt-2 text-3xl font-semibold text-gray-900">
                <?= $summary['average'] !== null ? number_format($summary['average'], 2) : '—' ?>/5
            </p>
            <p class="mt-1 text-xs text-gray-400">Tính trên các phản hồi được ghi nhận.</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Tỉ lệ tích cực</p>
            <p class="mt-2 text-3xl font-semibold text-gray-900">
                <?= $summary['positiveRatio'] !== null ? number_format($summary['positiveRatio'] * 100, 0) . '%' : '—' ?>
            </p>
            <p class="mt-1 text-xs text-gray-400">Số phản hồi đạt 4-5 điểm.</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Phản hồi mới nhất</p>
            <p class="mt-2 text-lg font-semibold text-gray-900">
                <?= $summary['latestDate'] !== null ? date('d/m/Y H:i', $summary['latestDate']) : '—' ?>
            </p>
            <p class="mt-1 text-xs text-gray-400">Thời gian phản hồi gần nhất của khách hàng.</p>
        </div>
    </section>

    <form method="GET" class="rounded-2xl border border-gray-200 bg-slate-50 p-6 shadow-sm">
        <input type="hidden" name="page" value="feedback">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
            <label class="flex flex-col gap-2 text-sm font-medium text-gray-600">
                <span>Dịch vụ</span>
                <select name="service" class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    <option value="">Tất cả dịch vụ</option>
                    <?php foreach ($services as $service): ?>
                        <option value="<?= $service['id'] ?>" <?= $filters['service'] === $service['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($service['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="flex flex-col gap-2 text-sm font-medium text-gray-600">
                <span>Điểm tối thiểu</span>
                <select name="rating_min" class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    <option value="">Tất cả</option>
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <option value="<?= $i ?>" <?= $filters['rating_min'] === $i ? 'selected' : '' ?>>Từ <?= $i ?> điểm trở lên</option>
                    <?php endfor; ?>
                </select>
            </label>

            <label class="flex flex-col gap-2 text-sm font-medium text-gray-600">
                <span>Bình luận</span>
                <select name="has_comment" class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    <option value="" <?= $filters['has_comment'] === '' ? 'selected' : '' ?>>Tất cả</option>
                    <option value="with" <?= $filters['has_comment'] === 'with' ? 'selected' : '' ?>>Có nhận xét</option>
                    <option value="without" <?= $filters['has_comment'] === 'without' ? 'selected' : '' ?>>Chỉ có điểm số</option>
                </select>
            </label>

            <label class="xl:col-span-2 flex flex-col gap-2 text-sm font-medium text-gray-600">
                <span>Từ khóa</span>
                <input type="search" name="keyword" value="<?= htmlspecialchars($filters['keyword'], ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Tên khách, nội dung, dịch vụ..."
                       class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            </label>

            <div class="flex items-end gap-3 md:col-span-2 xl:col-span-5">
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Áp dụng bộ lọc
                </button>
                <a href="?page=feedback" class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-5 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-100">
                    Đặt lại
                </a>
            </div>
        </div>
    </form>

    <?php if (empty($feedbackItems)): ?>
        <div class="rounded-2xl border border-dashed border-amber-200 bg-amber-50 p-10 text-center text-amber-700">
            Chưa có phản hồi nào phù hợp với bộ lọc hiện tại.
        </div>
    <?php else: ?>
        <section class="space-y-4">
            <?php foreach ($feedbackItems as $item): ?>
                <article class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition hover:border-indigo-200">
                    <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($item['TEN_DV'] ?? '', ENT_QUOTES, 'UTF-8') ?></h2>
                            <p class="text-sm text-gray-500">Khách hàng: <?= htmlspecialchars($item['ten_khach_hang'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="text-sm text-gray-500">Lịch hẹn: #<?= (int)($item['ID_LICHHEN'] ?? 0) ?> • <?= htmlspecialchars(formatDateTime($item['THOI_GIAN_BAT_DAU'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <div class="text-right">
                            <span class="rounded-full bg-indigo-50 px-4 py-1 text-sm font-semibold text-indigo-600">Đánh giá: <?= (int)($item['XEP_HANG_DV'] ?? 0) ?>/5</span>
                            <p class="mt-2 text-xs text-gray-400">Gửi lúc <?= htmlspecialchars(formatDateTime($item['NGAY_GUI'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    </div>
                    <?php if (!empty($item['NOI_DUNG'])): ?>
                        <p class="mt-4 rounded-lg bg-slate-50 p-4 text-sm text-gray-700 leading-relaxed">
                            <?= nl2br(htmlspecialchars($item['NOI_DUNG'], ENT_QUOTES, 'UTF-8')) ?>
                        </p>
                    <?php else: ?>
                        <p class="mt-4 rounded-lg border border-dashed border-gray-200 p-4 text-sm text-gray-500">
                            Khách hàng chỉ để lại điểm số.
                        </p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</div>

<style>
@keyframes fade-in { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.fade-in { animation: fade-in 0.4s ease-out both; }
</style>
