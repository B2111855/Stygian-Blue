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

    $totalPages = max(1, (int) ceil($totalLogs / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;

    $listSql = "SELECT ID_LOG, ACTOR_ID, VAI_TRO, HANH_DONG, DOI_TUONG, TRUOC_JSON, SAU_JSON, IP, USER_AGENT, CREATED_AT
                FROM nhat_ky_he_thong
                $whereSql
                ORDER BY CREATED_AT DESC
                LIMIT ?, ?";
    $listStmt = $conn->prepare($listSql);
    $listParams = array_merge($filterValues, [$offset, $perPage]);
    $listTypes = $filterTypes . 'ii';
    bindStatementParams($listStmt, $listTypes, $listParams);
    $listStmt->execute();
    $result = $listStmt->get_result();
    $logs = $result->fetch_all(MYSQLI_ASSOC);
    $listStmt->close();

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
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-M9N3jdjM8JrIoVNewc19hXtOD87bwo4V/mQJu1nvLK5j1WFJsbgx5caX5/C/PObbIVdQydb9h9NP7VDaRaoo2Q==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    </head>
    <body class="bg-slate-100 min-h-screen py-8 px-4">
            <div>
                <h1 class="text-3xl font-bold text-indigo-700">📜 Nhật ký hệ thống</h1>
                <p class="text-gray-600 mt-1">Theo dõi chi tiết mọi thao tác quan trọng trên nền tảng</p>
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
        </div>

        <?php if ($feedbackMessage): ?>
            <div class="mb-4 p-4 rounded-lg bg-green-50 border border-green-200 text-green-700 shadow"> <?= htmlspecialchars($feedbackMessage, ENT_QUOTES, 'UTF-8') ?> </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="mb-4 p-4 rounded-lg bg-red-50 border border-red-200 text-red-700 shadow"> <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?> </div>
        <?php endif; ?>

        <form method="get" class="bg-white rounded-xl shadow p-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
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

        <div class="bg-white rounded-xl shadow overflow-hidden">
            <div class="flex flex-wrap items-center justify-between px-4 py-3 border-b border-gray-100 text-sm text-gray-600">
                <span>Tổng số log: <strong><?= number_format($totalLogs) ?></strong></span>
                <span>Trang <?= $page ?> / <?= $totalPages ?></span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
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
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-6 text-gray-500">Chưa có dữ liệu phù hợp.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr class="border-b border-gray-100 hover:bg-indigo-50/40 transition">
                                    <td class="px-4 py-3 text-gray-700">
                                        <div class="font-semibold text-sm"><?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($log['CREATED_AT'])), ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-xs text-gray-500">#<?= (int) $log['ID_LOG'] ?></div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-indigo-700"><?= htmlspecialchars($log['ACTOR_ID'] ?? 'Không xác định', ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-xs text-gray-500 truncate max-w-[160px]" title="<?= htmlspecialchars($log['USER_AGENT'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($log['USER_AGENT'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full bg-indigo-100 text-indigo-700 text-xs font-semibold">
                                            <?= htmlspecialchars($log['VAI_TRO'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-gray-800"><?= htmlspecialchars($log['HANH_DONG'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="px-4 py-3 text-gray-700"><?= htmlspecialchars($log['DOI_TUONG'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="px-4 py-3 text-gray-600">
                                        <span class="font-mono text-sm"><?= htmlspecialchars($log['IP'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <details class="space-y-2">
                                            <summary class="cursor-pointer text-indigo-600 hover:text-indigo-800 text-sm font-semibold">Xem JSON</summary>
                                            <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
                                                <p class="text-xs font-semibold text-gray-500 mb-1">Trước</p>
                                                <pre class="text-xs bg-white p-2 rounded border overflow-auto max-h-40"><?= format_log_json($log['TRUOC_JSON']) ?></pre>
                                            </div>
                                            <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
                                                <p class="text-xs font-semibold text-gray-500 mb-1">Sau</p>
                                                <pre class="text-xs bg-white p-2 rounded border overflow-auto max-h-40"><?= format_log_json($log['SAU_JSON']) ?></pre>
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
    </div>
</body>

</html>
