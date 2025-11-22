<?php
// system_logs_api.php - REST-style export & listing for system logs
// Filters: q, role, action, from, to, limit (<=1000), format=json|csv, raw=1(include before/after JSON)
// NOTE: Should restrict access (TODO: integrate require_admin.php)

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../../database/config.php';
header('X-Endpoint: system_logs_api');

$search      = trim($_GET['q'] ?? '');
$roleFilter  = trim($_GET['role'] ?? '');
$actionFilter= trim($_GET['action'] ?? '');
$fromDate    = trim($_GET['from'] ?? '');
$toDate      = trim($_GET['to'] ?? '');
$limit       = (int) ($_GET['limit'] ?? 100);
$format      = strtolower($_GET['format'] ?? 'json');
$includeRaw  = isset($_GET['raw']) && $_GET['raw'] === '1';

$limit = max(1, min(1000, $limit));

$filterClauses = [];
$filterTypes = '';
$filterValues = [];

if ($search !== '') {
  $filterClauses[] = '(ACTOR_ID LIKE ? OR HANH_DONG LIKE ? OR DOI_TUONG LIKE ? OR IP LIKE ?)';
  $like = '%'.$search.'%';
  for ($i=0;$i<4;$i++){ $filterTypes.='s'; $filterValues[] = $like; }
}
if ($roleFilter !== '') { $filterClauses[]='VAI_TRO = ?'; $filterTypes.='s'; $filterValues[]=$roleFilter; }
if ($actionFilter !== '') { $filterClauses[]='HANH_DONG = ?'; $filterTypes.='s'; $filterValues[]=$actionFilter; }
if ($fromDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$fromDate)) { $filterClauses[]='CREATED_AT >= ?'; $filterTypes.='s'; $filterValues[]=$fromDate.' 00:00:00'; }
if ($toDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$toDate)) { $filterClauses[]='CREATED_AT <= ?'; $filterTypes.='s'; $filterValues[]=$toDate.' 23:59:59'; }

$whereSql = $filterClauses ? 'WHERE '.implode(' AND ',$filterClauses) : '';

$selectExtra = $includeRaw ? ', TRUOC_JSON, SAU_JSON' : '';
$sql = "SELECT ID_LOG, CREATED_AT, ACTOR_ID, VAI_TRO, HANH_DONG, DOI_TUONG, IP, USER_AGENT $selectExtra FROM nhat_ky_he_thong $whereSql ORDER BY CREATED_AT DESC LIMIT ?";
$filterTypes .= 'i';
$filterValues[] = $limit;

try {
  $stmt = $conn->prepare($sql);
  $bindParams = $filterValues;
  if ($filterTypes !== '') {
    // dynamic bind
    $refs = [$filterTypes];
    foreach ($bindParams as $k=>$v){ $refs[] = &$bindParams[$k]; }
    call_user_func_array([$stmt,'bind_param'],$refs);
  }
  $stmt->execute();
  $result = $stmt->get_result();
  $rows = $result->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
} catch (Throwable $th) {
  http_response_code(500);
  echo json_encode(['error'=>'Query error: '.$th->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

if ($format === 'csv') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="system_logs_export_'.date('Ymd_His').'_'.$limit.'.csv"');
  $out = fopen('php://output','w');
  $baseColumns = ['ID_LOG','CREATED_AT','ACTOR_ID','VAI_TRO','HANH_DONG','DOI_TUONG','IP','USER_AGENT'];
  if ($includeRaw) { $baseColumns[]='TRUOC_JSON'; $baseColumns[]='SAU_JSON'; }
  fputcsv($out,$baseColumns);
  foreach ($rows as $r) {
    $line = [];
    foreach ($baseColumns as $c) { $line[] = isset($r[$c]) ? $r[$c] : ''; }
    fputcsv($out,$line);
  }
  fclose($out);
  exit;
}

// Default JSON
header('Content-Type: application/json; charset=utf-8');
$payload = [
  'count' => count($rows),
  'limit' => $limit,
  'format' => 'json',
  'filters' => [
    'q'=>$search,'role'=>$roleFilter,'action'=>$actionFilter,'from'=>$fromDate,'to'=>$toDate
  ],
  'data' => $rows
];

echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
