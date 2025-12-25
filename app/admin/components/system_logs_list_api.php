<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
header('Content-Type: application/json; charset=utf-8');

include '../../database/config.php';

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

function highlight_match(string $value, string $search): string
{
    if ($search === '') {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    $escaped = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $pattern = '/' . preg_quote($search, '/') . '/i';
    return preg_replace($pattern, '<mark class="bg-yellow-300 text-black px-0.5">$0</mark>', $escaped) ?? $escaped;
}

try {
    $search = trim($_GET['q'] ?? '');
    $roleFilter = trim($_GET['role'] ?? '');
    $actionFilter = trim($_GET['action'] ?? '');
    $fromDate = trim($_GET['from'] ?? '');
    $toDate = trim($_GET['to'] ?? '');
    $perPage = (int) ($_GET['limit'] ?? 25);
    $page = max(1, (int) ($_GET['p'] ?? 1));

    $perPageOptions = [10, 25, 50, 100];
    if (!in_array($perPage, $perPageOptions, true)) {
        $perPage = 25;
    }

    // Build WHERE clause
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

    // Count total logs
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

    // Fetch logs
    $listSql = "SELECT ID_LOG, ACTOR_ID, VAI_TRO, HANH_DONG, DOI_TUONG, IP, USER_AGENT, CREATED_AT
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

    // Get filter options (roles and actions)
    $rolesResult = $conn->query("SELECT DISTINCT VAI_TRO FROM nhat_ky_he_thong WHERE VAI_TRO IS NOT NULL AND VAI_TRO <> '' ORDER BY VAI_TRO");
    $roleOptions = array_filter(array_column($rolesResult->fetch_all(MYSQLI_ASSOC), 'VAI_TRO'));

    $actionsResult = $conn->query("SELECT DISTINCT HANH_DONG FROM nhat_ky_he_thong ORDER BY HANH_DONG");
    $actionOptions = array_filter(array_column($actionsResult->fetch_all(MYSQLI_ASSOC), 'HANH_DONG'));

    // Calculate action counts for this page
    $actionCounts = [];
    foreach ($logs as $lg) {
        $a = $lg['HANH_DONG'] ?? '';
        if ($a !== '') {
            $actionCounts[$a] = ($actionCounts[$a] ?? 0) + 1;
        }
    }
    arsort($actionCounts);

    // Format logs for JSON response
    $formattedLogs = array_map(function ($log) use ($search) {
        return [
            'ID_LOG' => (int) $log['ID_LOG'],
            'ACTOR_ID' => highlight_match($log['ACTOR_ID'] ?? 'Không xác định', $search),
            'VAI_TRO' => htmlspecialchars($log['VAI_TRO'] ?? '—', ENT_QUOTES, 'UTF-8'),
            'HANH_DONG' => highlight_match($log['HANH_DONG'], $search),
            'DOI_TUONG' => highlight_match($log['DOI_TUONG'] ?? '—', $search),
            'IP' => highlight_match($log['IP'] ?? '—', $search),
            'USER_AGENT' => htmlspecialchars($log['USER_AGENT'] ?? '', ENT_QUOTES, 'UTF-8'),
            'CREATED_AT' => htmlspecialchars(date('d/m/Y H:i:s', strtotime($log['CREATED_AT'])), ENT_QUOTES, 'UTF-8'),
            'CREATED_AT_RAW' => $log['CREATED_AT'],
        ];
    }, $logs);

    $response = [
        'success' => true,
        'data' => $formattedLogs,
        'pagination' => [
            'page' => $page,
            'totalPages' => $totalPages,
            'perPage' => $perPage,
            'totalLogs' => $totalLogs,
        ],
        'stats' => [
            'topActions' => array_slice($actionCounts, 0, 5, true),
        ],
        'filters' => [
            'roleOptions' => $roleOptions,
            'actionOptions' => $actionOptions,
        ],
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $th) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Không thể tải nhật ký hệ thống: ' . $th->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
