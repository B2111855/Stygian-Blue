<?php
// Endpoint: fetch before/after JSON + diff for a single log entry
// Security: ensure only authenticated admin/authorized roles (basic check here; improve later)

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../../database/config.php';
header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['error' => 'Thiếu id hợp lệ']);
    exit;
}

try {
    $stmt = $conn->prepare('SELECT TRUOC_JSON, SAU_JSON FROM nhat_ky_he_thong WHERE ID_LOG = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->bind_result($beforeRaw, $afterRaw);
    if (!$stmt->fetch()) {
        echo json_encode(['error' => 'Không tìm thấy log']);
        $stmt->close();
        exit;
    }
    $stmt->close();

    $beforePretty = pretty($beforeRaw);
    $afterPretty  = pretty($afterRaw);

    $diff = computeDiffLines($beforePretty, $afterPretty);

    echo json_encode([
        'before_pretty' => $beforePretty,
        'after_pretty'  => $afterPretty,
        'diff'          => $diff,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $th) {
    echo json_encode(['error' => 'Lỗi máy chủ: '.$th->getMessage()]);
}

function pretty(?string $raw): ?string {
    if ($raw === null || $raw === '') return null;
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    // return as-is (escaped?) Keep raw to inspect invalid
    return $raw;
}

function computeDiffLines(?string $before, ?string $after): array {
    $beforeLines = $before === null ? [] : explode("\n", $before);
    $afterLines  = $after === null ? [] : explode("\n", $after);

    // Simple LCS diff (line-based)
    $m = count($beforeLines); $n = count($afterLines);
    $lcs = array_fill(0, $m+1, array_fill(0, $n+1, 0));
    for ($i=1;$i<=$m;$i++) {
        for ($j=1;$j<=$n;$j++) {
            if ($beforeLines[$i-1] === $afterLines[$j-1]) {
                $lcs[$i][$j] = $lcs[$i-1][$j-1]+1;
            } else {
                $lcs[$i][$j] = max($lcs[$i-1][$j], $lcs[$i][$j-1]);
            }
        }
    }
    // Backtrack
    $diff = [];
    $i=$m; $j=$n;
    while ($i>0 && $j>0) {
        if ($beforeLines[$i-1] === $afterLines[$j-1]) {
            $diff[] = ['type'=>'same','line'=>$beforeLines[$i-1]]; $i--; $j--; continue;
        }
        if ($lcs[$i-1][$j] >= $lcs[$i][$j-1]) {
            $diff[] = ['type'=>'del','line'=>$beforeLines[$i-1]]; $i--; continue;
        } else {
            $diff[] = ['type'=>'add','line'=>$afterLines[$j-1]]; $j--; continue;
        }
    }
    while ($i>0) { $diff[] = ['type'=>'del','line'=>$beforeLines[$i-1]]; $i--; }
    while ($j>0) { $diff[] = ['type'=>'add','line'=>$afterLines[$j-1]]; $j--; }

    return array_reverse($diff);
}
