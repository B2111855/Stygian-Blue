<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
include '../../database/config.php';
require_once __DIR__ . '/../../helpers/assets.php';

function bindStatementParams(mysqli_stmt $stmt, string $types, array &$params): void
{
    if ($types === '' || empty($params)) {
        return;
    }

    $bindParams = [$types];
    foreach ($params as $key => $value) {
        $bindParams[] = &$params[$key];
    }

    call_user_func_array([$stmt, 'bind_param'], $bindParams);
}

function format_log_json(?string $payload): string
{
    if ($payload === null || $payload === '') {
        return 'Không có dữ liệu';
    }

    $decoded = json_decode($payload, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $payload = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    return htmlspecialchars($payload, ENT_QUOTES, 'UTF-8');
}

function highlight_match(string $value, string $search): string
{
    if ($search === '') {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    $escaped = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $pattern = '/' . preg_quote($search, '/') . '/i';
    return preg_replace($pattern, '<mark class="bg-yellow-300 text-black px-0.5">$0</mark>', $escaped) ?? $escaped;
}

$feedbackMessage = null;
$errorMessage = null;
$logs = [];
$totalLogs = 0;
$totalPages = 1;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purge_logs'])) {
        $retainDays = isset($_POST['retain_days']) ? (int) $_POST['retain_days'] : 30;
        $retainDays = max(1, min(3650, $retainDays));
        $deleteStmt = $conn->prepare('DELETE FROM nhat_ky_he_thong WHERE CREATED_AT < DATE_SUB(NOW(), INTERVAL ? DAY)');
        $deleteStmt->bind_param('i', $retainDays);
        $deleteStmt->execute();
        $purged = $deleteStmt->affected_rows;
        $deleteStmt->close();
        $feedbackMessage = sprintf('Đã xóa %d log cũ hơn %d ngày.', $purged, $retainDays);
    }

    $search = trim($_GET['q'] ?? '');
    $roleFilter = trim($_GET['role'] ?? '');
    $actionFilter = trim($_GET['action'] ?? '');
    $fromDate = trim($_GET['from'] ?? '');
    $toDate = trim($_GET['to'] ?? '');
    $perPage = (int) ($_GET['limit'] ?? 25);
    $perPageOptions = [10, 25, 50, 100];
    if (!in_array($perPage, $perPageOptions, true)) {
        $perPage = 25;
    }
    $page = max(1, (int) ($_GET['p'] ?? 1));
    $baseQuery = $_GET;
    unset($baseQuery['p']);
    $baseQuery['page'] = 'system_logs';

    // Check for export request EARLY - before any HTML output
    $exportFormat = strtolower(trim($_GET['export'] ?? ''));
    $isExporting = ($exportFormat === 'csv' || $exportFormat === 'json');

    $filterClauses = [];
    $filterTypes = '';
    $filterValues = [];

    if ($search !== '') {
        $filterClauses[] = '(ACTOR_ID LIKE ? OR HANH_DONG LIKE ? OR DOI_TUONG LIKE ? OR IP LIKE ?)';
        $like = '%' . $search . '%';
        for ($i = 0; $i < 4; $i++) {
            $filterTypes .= 's';
            $filterValues[] = $like;
        }
    }

    if ($roleFilter !== '') {
        $filterClauses[] = 'VAI_TRO = ?';
        $filterTypes .= 's';
        $filterValues[] = $roleFilter;
    }

    if ($actionFilter !== '') {
        $filterClauses[] = 'HANH_DONG = ?';
        $filterTypes .= 's';
        $filterValues[] = $actionFilter;
    }

    if ($fromDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
        $filterClauses[] = 'CREATED_AT >= ?';
        $filterTypes .= 's';
        $filterValues[] = $fromDate . ' 00:00:00';
    }

    if ($toDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
        $filterClauses[] = 'CREATED_AT <= ?';
        $filterTypes .= 's';
        $filterValues[] = $toDate . ' 23:59:59';
    }

    $whereSql = $filterClauses ? 'WHERE ' . implode(' AND ', $filterClauses) : '';

    $countSql = "SELECT COUNT(*) FROM nhat_ky_he_thong $whereSql";
    $countStmt = $conn->prepare($countSql);
    $countParams = $filterValues;
    if ($filterTypes !== '') {
        bindStatementParams($countStmt, $filterTypes, $countParams);
    }
    $countStmt->execute();
    $countStmt->bind_result($totalLogs);
    $countStmt->fetch();
    $countStmt->close();

    // Guard: nếu trang vượt quá totalPages sau khi tính lại offset sẽ trả về rỗng; điều chỉnh về trang cuối nếu cần
    $calculatedTotalPages = max(1, (int) ceil($totalLogs / $perPage));
    if ($page > $calculatedTotalPages) {
        $page = $calculatedTotalPages;
    }

    $totalPages = $calculatedTotalPages;
    $offset = ($page - 1) * $perPage;

    $listSql = "SELECT ID_LOG, ACTOR_ID, VAI_TRO, HANH_DONG, DOI_TUONG, IP, USER_AGENT, CREATED_AT
                    FROM nhat_ky_he_thong
                    $whereSql
                    ORDER BY CREATED_AT DESC
                    LIMIT ?, ?"; // lazy load JSON fields via AJAX
    $listStmt = $conn->prepare($listSql);
    $listParams = array_merge($filterValues, [$offset, $perPage]);
    $listTypes = $filterTypes . 'ii';
    bindStatementParams($listStmt, $listTypes, $listParams);
    $listStmt->execute();
    $result = $listStmt->get_result();
    $logs = $result->fetch_all(MYSQLI_ASSOC);
    $listStmt->close();

    // Handle export IMMEDIATELY after getting data, before any HTML
    if ($isExporting) {
        $exportMeta = [
            'page' => $page,
            'perPage' => $perPage,
            'totalLogs' => $totalLogs,
            'totalPages' => $totalPages,
        ];

        if ($exportFormat === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="system_logs_page_' . $page . '.csv"');
            echo "\xEF\xBB\xBF"; // BOM for Excel UTF-8 compatibility
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID_LOG', 'CREATED_AT', 'ACTOR_ID', 'VAI_TRO', 'HANH_DONG', 'DOI_TUONG', 'IP', 'USER_AGENT']);
            foreach ($logs as $row) {
                fputcsv($out, [
                    $row['ID_LOG'] ?? '',
                    $row['CREATED_AT'] ?? '',
                    $row['ACTOR_ID'] ?? '',
                    $row['VAI_TRO'] ?? '',
                    $row['HANH_DONG'] ?? '',
                    $row['DOI_TUONG'] ?? '',
                    $row['IP'] ?? '',
                    $row['USER_AGENT'] ?? '',
                ]);
            }
            fclose($out);
            exit;
        }

        // JSON export
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'pagination' => $exportMeta,
            'data' => $logs,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    $rolesResult = $conn->query("SELECT DISTINCT VAI_TRO FROM nhat_ky_he_thong WHERE VAI_TRO IS NOT NULL AND VAI_TRO <> '' ORDER BY VAI_TRO");
    $roleOptions = array_filter(array_column($rolesResult->fetch_all(MYSQLI_ASSOC), 'VAI_TRO'));

    $actionsResult = $conn->query("SELECT DISTINCT HANH_DONG FROM nhat_ky_he_thong ORDER BY HANH_DONG");
    $actionOptions = array_filter(array_column($actionsResult->fetch_all(MYSQLI_ASSOC), 'HANH_DONG'));
} catch (Throwable $th) {
    $errorMessage = 'Không thể tải nhật ký hệ thống: ' . $th->getMessage();
    $roleOptions = [];
    $actionOptions = [];
    }
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Quản lý nhật ký hệ thống</title>
        <?= sb_tailwind_link_tag(); ?>
        <!-- Font Awesome CDN without integrity (previous integrity mismatch blocked load). Consider self-hosting or updating to correct SRI hash. -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    </head>
    <body class="bg-slate-100 min-h-screen py-8 px-4">
        <div class="mx-auto max-w-7xl bg-white bg-opacity-90 backdrop-blur-md rounded-xl p-6 shadow-xl min-h-[80vh] flex flex-col gap-6">
            <div id="notification" class="bg-blue-100 text-blue-700 p-4 rounded mb-2 hidden shadow-md">
                <strong>🔔 Thông báo:</strong> Bạn có cập nhật mới!
            </div>
            <div class="flex flex-wrap items-start gap-4 justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-indigo-700">Nhật ký hệ thống</h1>
                    <p class="text-gray-600 mt-1">Theo dõi chi tiết mọi thao tác quan trọng trên nền tảng</p>
                </div>
                <div class="flex items-center gap-2 mt-2">
                    <button type="button" id="toggle-dark" class="text-xs px-3 py-1.5 rounded border border-gray-300 bg-white hover:bg-gray-100 font-semibold text-gray-700">Dark Mode</button>
                    <button type="button" id="collapse-filters" class="text-xs px-3 py-1.5 rounded border border-gray-300 bg-white hover:bg-gray-100 font-semibold text-gray-700" data-state="open">Ẩn bộ lọc</button>
                </div>
            </div>
            <form method="post" class="flex items-center gap-2 bg-white shadow rounded-lg px-4 py-2">
                <input type="hidden" name="purge_logs" value="1">
                <label class="text-sm text-gray-600">Xóa log cũ hơn</label>
                <input type="number" name="retain_days" min="1" max="3650" value="30" class="w-20 border-gray-300 rounded px-2 py-1 text-sm" />
                <span class="text-sm text-gray-600">ngày</span>
                <button type="submit" class="bg-red-500 hover:bg-red-600 text-white text-sm font-medium px-3 py-1.5 rounded transition"
                    onclick="return confirm('Bạn chắc chắn muốn xóa log cũ? Hành động này không thể hoàn tác.');">
                    <i class="fas fa-trash-alt mr-1"></i>Xóa
                </button>
            </form>
        

        <?php if ($feedbackMessage): ?>
            <div class="mb-4 p-4 rounded-lg bg-green-50 border border-green-200 text-green-700 shadow"> <?= htmlspecialchars($feedbackMessage, ENT_QUOTES, 'UTF-8') ?> </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="mb-4 p-4 rounded-lg bg-red-50 border border-red-200 text-red-700 shadow"> <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?> </div>
        <?php endif; ?>

        <form method="get" id="filter-form" class="bg-white rounded-xl shadow p-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <input type="hidden" name="page" value="system_logs">
            <div>
                <label class="text-sm text-gray-600">Từ khóa</label>
                <input type="text" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Tài khoản, hành động, IP..." class="w-full border border-gray-300 rounded-lg px-3 py-2 mt-1">
            </div>
            <div>
                <label class="text-sm text-gray-600">Vai trò</label>
                <select name="role" class="w-full border border-gray-300 rounded-lg px-3 py-2 mt-1">
                    <option value="">Tất cả</option>
                    <?php foreach ($roleOptions as $role): ?>
                        <option value="<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>" <?= $roleFilter === $role ? 'selected' : '' ?>><?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-sm text-gray-600">Hành động</label>
                <select name="action" class="w-full border border-gray-300 rounded-lg px-3 py-2 mt-1">
                    <option value="">Tất cả</option>
                    <?php foreach ($actionOptions as $action): ?>
                        <option value="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>" <?= $actionFilter === $action ? 'selected' : '' ?>><?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-sm text-gray-600">Từ ngày</label>
                    <input type="date" name="from" value="<?= htmlspecialchars($fromDate, ENT_QUOTES, 'UTF-8') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 mt-1">
                </div>
                <div>
                    <label class="text-sm text-gray-600">Đến ngày</label>
                    <input type="date" name="to" value="<?= htmlspecialchars($toDate, ENT_QUOTES, 'UTF-8') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 mt-1">
                </div>
            </div>
            <div>
                <label class="text-sm text-gray-600">Số dòng / trang</label>
                <select name="limit" class="w-full border border-gray-300 rounded-lg px-3 py-2 mt-1">
                    <?php foreach ($perPageOptions as $option): ?>
                        <option value="<?= $option ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= $option ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2 rounded-lg shadow transition">Lọc dữ liệu</button>
            </div>
            <div class="flex items-end">
                <a href="?page=system_logs" class="w-full text-center bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium px-4 py-2 rounded-lg border border-gray-300 transition">Xóa bộ lọc</a>
            </div>
        </form>

        <div class="bg-white rounded-xl shadow overflow-hidden" id="logs-container">
            <div class="flex flex-wrap items-center justify-between px-4 py-3 border-b border-gray-100 text-sm text-gray-600 gap-3">
                <div class="flex flex-col">
                    <span>Tổng số log: <strong><?= number_format($totalLogs) ?></strong></span>
                    <?php
                        $actionCounts = [];
                        foreach ($logs as $lg) { $a = $lg['HANH_DONG'] ?? ''; if ($a !== '') { $actionCounts[$a] = ($actionCounts[$a] ?? 0) + 1; } }
                        arsort($actionCounts);
                        $topSummary = implode(', ', array_map(
                            function($k,$v){ return $k.':'.$v;},
                            array_slice(array_keys($actionCounts),0,5),
                            array_slice(array_values($actionCounts),0,5)
                        ));
                    ?>
                    <span class="text-xs text-gray-500">Top hành động trang: <?= htmlspecialchars($topSummary, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <span>Trang <?= $page ?> / <?= $totalPages ?></span>
                <div class="flex gap-2 mt-2 w-full md:w-auto">
                    <?php $exportBase = $baseQuery; $exportBase['limit'] = $perPage; ?>
                    <a href="?<?= http_build_query(array_merge($exportBase,['export'=>'csv'])) ?>" class="px-3 py-1.5 rounded bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700">CSV trang hiện tại</a>
                    <a href="?<?= http_build_query(array_merge($exportBase,['export'=>'json'])) ?>" class="px-3 py-1.5 rounded bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700">JSON trang hiện tại</a>
                    <a href="system_logs_api.php?<?= http_build_query(array_merge($baseQuery,['format'=>'csv','limit'=>1000])) ?>" class="px-3 py-1.5 rounded bg-orange-600 text-white text-xs font-semibold hover:bg-orange-700" title="Xuất tối đa 1000 dòng phù hợp">CSV (tối đa 1000)</a>
                    <a href="system_logs_api.php?<?= http_build_query(array_merge($baseQuery,['format'=>'json','limit'=>1000])) ?>" class="px-3 py-1.5 rounded bg-fuchsia-600 text-white text-xs font-semibold hover:bg-fuchsia-700" title="Xuất tối đa 1000 dòng phù hợp">JSON (tối đa 1000)</a>
                </div>
            </div>
            <div class="overflow-x-auto">
                 <table class="min-w-full text-sm" aria-label="System logs table">
                    <thead class="bg-gray-100 text-gray-600 uppercase text-xs">
                        <tr>
                            <th class="px-4 py-2 text-left">Thời gian</th>
                            <th class="px-4 py-2 text-left">Tài khoản</th>
                            <th class="px-4 py-2 text-left">Vai trò</th>
                            <th class="px-4 py-2 text-left">Hành động</th>
                            <th class="px-4 py-2 text-left">Đối tượng</th>
                            <th class="px-4 py-2 text-left">IP</th>
                            <th class="px-4 py-2 text-left">Chi tiết</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($totalLogs > 0 && empty($logs)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-10 text-amber-600">
                                    <div class="flex flex-col items-center gap-2">
                                        <div class="text-4xl">⚠️</div>
                                        <div class="font-semibold">Có <?= (int)$totalLogs ?> log trong hệ thống nhưng trang này không tải được danh sách.</div>
                                        <div class="text-xs text-gray-500">Có thể do phân trang vượt giới hạn hoặc lỗi truy vấn. Đang thử tự động nạp lại...</div>
                                        <button type="button" id="logs-force-reload" class="mt-2 text-xs px-3 py-1.5 rounded bg-amber-600 text-white hover:bg-amber-700">Force reload</button>
                                    </div>
                                </td>
                            </tr>
                        <?php elseif (empty($logs)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-10 text-gray-500">
                                    <div class="flex flex-col items-center gap-2">
                                        <div class="text-4xl">🗒️</div>
                                        <div class="font-semibold">Chưa có dữ liệu phù hợp</div>
                                        <div class="text-xs text-gray-400">Thử thay đổi bộ lọc hoặc kiểm tra hệ thống ghi log.</div>
                                        <button type="button" id="logs-refresh-btn" class="mt-2 text-xs px-3 py-1.5 rounded bg-indigo-600 text-white hover:bg-indigo-700">Tải lại</button>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr class="border-b border-gray-100 hover:bg-indigo-50/40 transition">
                                    <td class="px-4 py-3 text-gray-700">
                                        <div class="font-semibold text-sm"><?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($log['CREATED_AT'])), ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-xs text-gray-500">#<?= (int) $log['ID_LOG'] ?></div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-indigo-700"><?= highlight_match($log['ACTOR_ID'] ?? 'Không xác định', $search) ?></div>
                                        <div class="text-xs text-gray-500 truncate max-w-[160px] flex items-center gap-1" title="<?= htmlspecialchars($log['USER_AGENT'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                            <span><?= highlight_match($log['USER_AGENT'] ?? '—', $search) ?></span>
                                            <?php if (!empty($log['USER_AGENT'])): ?><button type="button" class="text-[10px] px-1 py-0.5 bg-gray-200 hover:bg-gray-300 rounded copy-btn" data-copy="<?= htmlspecialchars($log['USER_AGENT'], ENT_QUOTES, 'UTF-8') ?>">Copy</button><?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full bg-indigo-100 text-indigo-700 text-xs font-semibold">
                                            <?= htmlspecialchars($log['VAI_TRO'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-gray-800"><?= highlight_match($log['HANH_DONG'], $search) ?></td>
                                    <td class="px-4 py-3 text-gray-700"><?= highlight_match($log['DOI_TUONG'] ?? '—', $search) ?></td>
                                    <td class="px-4 py-3 text-gray-600 flex items-center gap-1">
                                        <span class="font-mono text-sm"><?= highlight_match($log['IP'] ?? '—', $search) ?></span>
                                        <?php if (!empty($log['IP'])): ?><button type="button" class="text-[10px] px-1 py-0.5 bg-gray-200 hover:bg-gray-300 rounded copy-btn" data-copy="<?= htmlspecialchars($log['IP'], ENT_QUOTES, 'UTF-8') ?>">Copy</button><?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <details class="space-y-2 system-log-detail" data-log-id="<?= (int)$log['ID_LOG'] ?>">
                                            <summary class="cursor-pointer text-indigo-600 hover:text-indigo-800 text-sm font-semibold">Xem JSON + Diff</summary>
                                            <div class="text-xs text-gray-500" data-status>Đang tải khi mở...</div>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2" hidden data-panels>
                                                <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
                                                    <p class="text-xs font-semibold text-gray-500 mb-1">Trước</p>
                                                    <pre class="text-xs bg-white p-2 rounded border overflow-auto max-h-56" data-before></pre>
                                                </div>
                                                <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
                                                    <p class="text-xs font-semibold text-gray-500 mb-1">Sau</p>
                                                    <pre class="text-xs bg-white p-2 rounded border overflow-auto max-h-56" data-after></pre>
                                                </div>
                                                <div class="md:col-span-2 bg-gray-50 rounded-lg p-3 border border-gray-200">
                                                    <p class="text-xs font-semibold text-gray-500 mb-1 flex items-center justify-between">Diff
                                                        <span class="text-[10px] font-normal text-gray-400" data-diff-stats></span>
                                                    </p>
                                                    <pre class="text-xs bg-white p-2 rounded border overflow-auto max-h-64" data-diff></pre>
                                                </div>
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="flex flex-wrap items-center justify-center gap-2 mt-6">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php $baseQuery['p'] = $i; ?>
                    <?php if ($i === $page): ?>
                        <a href="?<?= http_build_query($baseQuery) ?>"
                           class="px-3 py-1.5 rounded-lg border border-indigo-600 bg-indigo-600 text-white">
                            <?= $i ?>
                        </a>
                    <?php else: ?>
                        <a href="?<?= http_build_query($baseQuery) ?>"
                           class="px-3 py-1.5 rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-100">
                            <?= $i ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div> <!-- end inner container -->
<script>
// ============== LOGS STATE MANAGER ==============
class LogsStateManager {
    constructor(apiUrl = 'system_logs_list_api.php') {
        this.apiUrl = apiUrl;
        this.state = {
            q: new URLSearchParams(window.location.search).get('q') || '',
            role: new URLSearchParams(window.location.search).get('role') || '',
            action: new URLSearchParams(window.location.search).get('action') || '',
            from: new URLSearchParams(window.location.search).get('from') || '',
            to: new URLSearchParams(window.location.search).get('to') || '',
            limit: parseInt(new URLSearchParams(window.location.search).get('limit')) || 25,
            p: parseInt(new URLSearchParams(window.location.search).get('p')) || 1,
        };
        this.isLoading = false;
    }

    getQueryString() {
        const params = new URLSearchParams();
        Object.entries(this.state).forEach(([key, val]) => {
            if (val !== '' && val !== 0) {
                params.set(key, val);
            }
        });
        params.set('page', 'system_logs');
        return params.toString();
    }

    updateState(updates) {
        this.state = { ...this.state, ...updates, p: 1 };
    }

    async fetchLogs() {
        if (this.isLoading) return null;
        this.isLoading = true;
        try {
            const resp = await fetch(`${this.apiUrl}?${this.getQueryString()}`);
            if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
            const data = await resp.json();
            this.isLoading = false;
            return data.success ? data : null;
        } catch (err) {
            this.isLoading = false;
            console.error('Logs fetch error:', err);
            return null;
        }
    }

    pushHistory() {
        const url = `?${this.getQueryString()}`;
        history.pushState(this.state, '', url);
    }
}

// Initialize state manager
const logsManager = new LogsStateManager('system_logs_list_api.php');

// ============== TABLE RENDERING ==============
function renderLogsTable(apiData) {
    const tbody = document.querySelector('#logs-container tbody');
    const headerStats = document.querySelector('.flex.flex-col');
    const paginationContainer = document.querySelector('.flex.flex-wrap.items-center.justify-center');

    if (!apiData || !apiData.data || apiData.data.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-10 text-gray-500">
                    <div class="flex flex-col items-center gap-2">
                        <div class="text-4xl">🗒️</div>
                        <div class="font-semibold">Chưa có dữ liệu phù hợp</div>
                        <div class="text-xs text-gray-400">Thử thay đổi bộ lọc hoặc kiểm tra hệ thống ghi log.</div>
                    </div>
                </td>
            </tr>`;
        return;
    }

    const rows = apiData.data.map(log => `
        <tr class="border-b border-gray-100 hover:bg-indigo-50/40 transition">
            <td class="px-4 py-3 text-gray-700">
                <div class="font-semibold text-sm">${log.CREATED_AT}</div>
                <div class="text-xs text-gray-500">#${log.ID_LOG}</div>
            </td>
            <td class="px-4 py-3">
                <div class="font-medium text-indigo-700">${log.ACTOR_ID}</div>
                <div class="text-xs text-gray-500 truncate max-w-[160px] flex items-center gap-1" title="${log.USER_AGENT}">
                    <span>${log.USER_AGENT || '—'}</span>
                    ${log.USER_AGENT ? `<button type="button" class="text-[10px] px-1 py-0.5 bg-gray-200 hover:bg-gray-300 rounded copy-btn" data-copy="${log.USER_AGENT}">Copy</button>` : ''}
                </div>
            </td>
            <td class="px-4 py-3 text-gray-700">
                <span class="inline-flex items-center px-2 py-1 rounded-full bg-indigo-100 text-indigo-700 text-xs font-semibold">
                    ${log.VAI_TRO}
                </span>
            </td>
            <td class="px-4 py-3 font-semibold text-gray-800">${log.HANH_DONG}</td>
            <td class="px-4 py-3 text-gray-700">${log.DOI_TUONG}</td>
            <td class="px-4 py-3 text-gray-600 flex items-center gap-1">
                <span class="font-mono text-sm">${log.IP}</span>
                ${log.IP !== '—' ? `<button type="button" class="text-[10px] px-1 py-0.5 bg-gray-200 hover:bg-gray-300 rounded copy-btn" data-copy="${log.IP}">Copy</button>` : ''}
            </td>
            <td class="px-4 py-3">
                <details class="space-y-2 system-log-detail" data-log-id="${log.ID_LOG}">
                    <summary class="cursor-pointer text-indigo-600 hover:text-indigo-800 text-sm font-semibold">Xem JSON + Diff</summary>
                    <div class="text-xs text-gray-500" data-status>Đang tải khi mở...</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2" hidden data-panels>
                        <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
                            <p class="text-xs font-semibold text-gray-500 mb-1">Trước</p>
                            <pre class="text-xs bg-white p-2 rounded border overflow-auto max-h-56" data-before></pre>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
                            <p class="text-xs font-semibold text-gray-500 mb-1">Sau</p>
                            <pre class="text-xs bg-white p-2 rounded border overflow-auto max-h-56" data-after></pre>
                        </div>
                        <div class="md:col-span-2 bg-gray-50 rounded-lg p-3 border border-gray-200">
                            <p class="text-xs font-semibold text-gray-500 mb-1 flex items-center justify-between">Diff
                                <span class="text-[10px] font-normal text-gray-400" data-diff-stats></span>
                            </p>
                            <pre class="text-xs bg-white p-2 rounded border overflow-auto max-h-64" data-diff></pre>
                        </div>
                    </div>
                </details>
            </td>
        </tr>
    `).join('');

    tbody.innerHTML = rows;

    // Update header stats
    const { topActions } = apiData.stats;
    const topSummary = Object.entries(topActions)
        .map(([action, count]) => `${action}:${count}`)
        .join(', ');

    if (headerStats) {
        headerStats.innerHTML = `
            <span>Tổng số log: <strong>${apiData.pagination.totalLogs.toLocaleString('vi-VN')}</strong></span>
            <span class="text-xs text-gray-500">Top hành động trang: ${topSummary}</span>`;
    }

    // Update pagination
    const { page, totalPages } = apiData.pagination;
    if (paginationContainer && totalPages > 1) {
        let paginationHtml = '';
        for (let i = 1; i <= totalPages; i++) {
            logsManager.state.p = i;
            const url = `?${logsManager.getQueryString()}`;
            const isActive = i === page;
            paginationHtml += `
                <a href="${url}" class="page-link px-3 py-1.5 rounded-lg border ${isActive ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-100'}">
                    ${i}
                </a>`;
        }
        logsManager.state.p = page; // Reset to current page
        paginationContainer.innerHTML = paginationHtml;
    }

    // Re-attach copy and detail handlers
    attachEventHandlers();
}

// ============== EVENT HANDLERS ==============
function attachEventHandlers() {
    // Copy buttons
    document.querySelectorAll('.copy-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const txt = btn.getAttribute('data-copy') || '';
            navigator.clipboard.writeText(txt).then(() => {
                const original = btn.textContent;
                btn.textContent = 'Copied';
                setTimeout(() => btn.textContent = original, 1500);
            });
        });
    });

    // Detail toggle (lazy load)
    document.querySelectorAll('.system-log-detail').forEach(d => {
        if (d._detailHandlerAttached) return;
        d._detailHandlerAttached = true;
        
        d.addEventListener('toggle', async () => {
            if (!d.open) return;
            if (d.dataset.loaded === '1') return;
            const id = d.getAttribute('data-log-id');
            const statusEl = d.querySelector('[data-status]');
            const panelsEl = d.querySelector('[data-panels]');
            try {
                statusEl.textContent = 'Đang tải...';
                const resp = await fetch('./components/system_log_fetch.php?id=' + encodeURIComponent(id));
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                const data = await resp.json();
                if (data.error) throw new Error(data.error);
                panelsEl.hidden = false;
                statusEl.remove();
                d.dataset.loaded = '1';
                d.querySelector('[data-before]').textContent = data.before_pretty || 'Không có dữ liệu';
                d.querySelector('[data-after]').textContent = data.after_pretty || 'Không có dữ liệu';
                const diffEl = d.querySelector('[data-diff]');
                diffEl.innerHTML = '';
                let adds = 0, dels = 0;
                (data.diff || []).forEach(part => {
                    const line = document.createElement('div');
                    line.style.whiteSpace = 'pre';
                    if (part.type === 'add') { line.style.background = '#ecfdf5'; line.style.color = '#065f46'; adds++; line.textContent = '+ ' + part.line; }
                    else if (part.type === 'del') { line.style.background = '#fef2f2'; line.style.color = '#991b1b'; dels++; line.textContent = '- ' + part.line; }
                    else { line.textContent = '  ' + part.line; line.style.color = '#475569'; }
                    diffEl.appendChild(line);
                });
                d.querySelector('[data-diff-stats]').textContent = `+${adds} -${dels}`;
            } catch (err) {
                statusEl.textContent = 'Lỗi tải: ' + err.message;
            }
        });
    });

    // Pagination links
    document.querySelectorAll('.page-link').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const url = new URL(link.href);
            const params = new URLSearchParams(url.search);
            logsManager.state.p = parseInt(params.get('p')) || 1;
            loadAndRender();
        });
    });
}

async function loadAndRender() {
    const apiData = await logsManager.fetchLogs();
    if (apiData) {
        renderLogsTable(apiData);
        logsManager.pushHistory();
    }
}

// ============== FILTER FORM HIJACKING ==============
document.addEventListener('DOMContentLoaded', () => {
    const filterForm = document.getElementById('filter-form');
    
    if (filterForm) {
        filterForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(filterForm);
            logsManager.updateState({
                q: formData.get('q') || '',
                role: formData.get('role') || '',
                action: formData.get('action') || '',
                from: formData.get('from') || '',
                to: formData.get('to') || '',
                limit: parseInt(formData.get('limit')) || 25,
            });
            await loadAndRender();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // Reset filter link
    const resetLink = document.querySelector('a[href*="page=system_logs"]');
    if (resetLink) {
        resetLink.addEventListener('click', (e) => {
            e.preventDefault();
            logsManager.state = {
                q: '', role: '', action: '', from: '', to: '',
                limit: 25, p: 1,
            };
            loadAndRender();
        });
    }

    // Browser back/forward support
    window.addEventListener('popstate', (e) => {
        if (e.state) {
            logsManager.state = e.state;
            renderLogsTable(logsManager.state._apiData || {});
        }
    });

    // Dark mode toggle
    const darkBtn = document.getElementById('toggle-dark');
    const root = document.documentElement;
    const applyDark = (enabled) => {
        if (enabled) { root.classList.add('dark'); darkBtn.textContent='Light Mode'; localStorage.setItem('sb_dark','1'); }
        else { root.classList.remove('dark'); darkBtn.textContent='Dark Mode'; localStorage.setItem('sb_dark','0'); }
    };
    applyDark(localStorage.getItem('sb_dark')==='1');
    darkBtn.addEventListener('click',()=>{ applyDark(!root.classList.contains('dark')); });
    
    // Collapse filters
    const collapseBtn = document.getElementById('collapse-filters');
    const filterFormEl = document.getElementById('filter-form');
    if (collapseBtn && filterFormEl) {
        collapseBtn.addEventListener('click',()=>{
            const open = collapseBtn.getAttribute('data-state')==='open';
            filterFormEl.style.display = open ? 'none' : '';
            collapseBtn.textContent = open ? 'Hiện bộ lọc' : 'Ẩn bộ lọc';
            collapseBtn.setAttribute('data-state', open ? 'closed':'open');
        });
    }

    // Attach handlers for server-rendered table on initial load
    attachEventHandlers();
});
</script>
</body>

</html>
