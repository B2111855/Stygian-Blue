<?php
// Audit + optional fix for multi-service appointment invoices
// Usage:
// - View report (last 180 days): /app/admin/tools/audit_multi_service_invoices.php
// - Change window: ?days=365
// - Fix one invoice details: ?fix=1&id_hd=63 (rebuild details from BOOKING_ITEM + TRAVEL_FEE)

if (!isset($conn)) {
  require_once __DIR__ . '/../../../database/config.php';
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Ho_Chi_Minh');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$days = isset($_GET['days']) ? max(1, (int)$_GET['days']) : 180;
$fix  = isset($_GET['fix']) ? (int)$_GET['fix'] : 0;
$fixId = isset($_GET['id_hd']) ? (int)$_GET['id_hd'] : 0;

// Detect chi_tiet_hoa_don schema
$hasDetailTable = false; $hasMoTaColumns = false;
if ($tblCheck = mysqli_query($conn, "SHOW TABLES LIKE 'chi_tiet_hoa_don'")) {
    $hasDetailTable = mysqli_num_rows($tblCheck) > 0; mysqli_free_result($tblCheck);
}
if ($hasDetailTable) {
    $colCheck = mysqli_query($conn, "SHOW COLUMNS FROM chi_tiet_hoa_don LIKE 'MO_TA'");
    $hasMoTaColumns = $colCheck && mysqli_num_rows($colCheck) > 0; if ($colCheck) mysqli_free_result($colCheck);
}

function fetchInvoiceItems(mysqli $conn, int $idLichHen): array {
    $items = [];
    $stmt = mysqli_prepare($conn, "SELECT dv.TEN_DV, bi.DON_GIA, bi.SO_LUONG FROM BOOKING_ITEM bi JOIN DICH_VU dv ON dv.ID_DV = bi.REF_ID WHERE bi.ID_LICHHEN = ? AND bi.ITEM_TYPE = 'service' ORDER BY bi.ID_ITEM ASC");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $idLichHen);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($r = mysqli_fetch_assoc($res))) {
            $r['DON_GIA'] = (float)($r['DON_GIA'] ?? 0);
            $r['SO_LUONG'] = (int)($r['SO_LUONG'] ?? 1);
            $r['THANH_TIEN'] = $r['DON_GIA'] * $r['SO_LUONG'];
            $items[] = $r;
        }
        mysqli_stmt_close($stmt);
    }
    return $items;
}

function fetchTravelFee(mysqli $conn, int $idLichHen): float {
    $fee = 0.0; $st = mysqli_prepare($conn, "SELECT COALESCE(TRAVEL_FEE,0) FROM lich_hen WHERE ID_LICHHEN = ?");
    if ($st) { mysqli_stmt_bind_param($st, 'i', $idLichHen); mysqli_stmt_execute($st); mysqli_stmt_bind_result($st, $fee); mysqli_stmt_fetch($st); mysqli_stmt_close($st); }
    return (float)$fee;
}

function formatMoney($v){ return number_format((float)$v, 0, ',', '.') . ' VND'; }

$report = [];

// Optional fix single invoice
if ($fix && $fixId > 0 && $hasDetailTable) {
    // Load invoice -> only for appointment invoices
    $st = mysqli_prepare($conn, "SELECT ID_HD, ID_LICHHEN FROM hoa_don WHERE ID_HD = ? AND ID_LICHHEN IS NOT NULL LIMIT 1");
    if ($st) {
        mysqli_stmt_bind_param($st, 'i', $fixId);
        mysqli_stmt_execute($st);
        mysqli_stmt_bind_result($st, $iid, $idLH);
        if (mysqli_stmt_fetch($st)) {
            mysqli_stmt_close($st);
            $items = fetchInvoiceItems($conn, $idLH);
            $fee = fetchTravelFee($conn, $idLH);
            if (!empty($items) || $fee > 0) {
                // Rebuild details
                $del = mysqli_prepare($conn, "DELETE FROM chi_tiet_hoa_don WHERE ID_HD = ?");
                if ($del) { mysqli_stmt_bind_param($del, 'i', $iid); mysqli_stmt_execute($del); mysqli_stmt_close($del); }
                if ($hasMoTaColumns) {
                    $ins = mysqli_prepare($conn, "INSERT INTO chi_tiet_hoa_don (ID_HD, MO_TA, SO_LUONG, DON_GIA, THANH_TIEN) VALUES (?, ?, ?, ?, ?)");
                    if ($ins) {
                        foreach ($items as $it) {
                            $moTa = 'Dịch vụ: ' . ($it['TEN_DV'] ?? 'DV');
                            $sl = (int)$it['SO_LUONG']; $dg = (float)$it['DON_GIA']; $tt = (float)$it['THANH_TIEN'];
                            mysqli_stmt_bind_param($ins, 'isidd', $iid, $moTa, $sl, $dg, $tt);
                            mysqli_stmt_execute($ins);
                        }
                        if ($fee > 0) {
                            mysqli_stmt_bind_param($ins, 'isidd', $iid, $tmp='Phụ phí di chuyển', $one=1, $fee, $fee);
                            mysqli_stmt_execute($ins);
                        }
                        mysqli_stmt_close($ins);
                    }
                } else {
                    $ins = mysqli_prepare($conn, "INSERT INTO chi_tiet_hoa_don (ID_HD, LOAI, ID_THAM_CHIEU, TEN_MUC, DON_GIA) VALUES (?, ?, ?, ?, ?)");
                    if ($ins) {
                        foreach ($items as $it) {
                            $loai='service'; $ref=0; $ten='Dịch vụ: '.($it['TEN_DV'] ?? 'DV'); $dg=(int)$it['DON_GIA'];
                            mysqli_stmt_bind_param($ins, 'isisi', $iid, $loai, $ref, $ten, $dg); mysqli_stmt_execute($ins);
                        }
                        if ($fee > 0) { $loai='travel_fee'; $ref=0; $ten='Phụ phí di chuyển'; $dg=(int)$fee; mysqli_stmt_bind_param($ins, 'isisi', $iid, $loai, $ref, $ten, $dg); mysqli_stmt_execute($ins); }
                        mysqli_stmt_close($ins);
                    }
                }
                $_SESSION['audit_fix_notice'] = 'Đã tái tạo chi tiết hóa đơn #' . $iid . ' từ BOOKING_ITEM.';
            } else {
                $_SESSION['audit_fix_notice'] = 'Không có dữ liệu BOOKING_ITEM/phụ phí để tái tạo chi tiết hóa đơn.';
            }
        } else {
            mysqli_stmt_close($st);
            $_SESSION['audit_fix_notice'] = 'Không tìm thấy hóa đơn lịch hẹn để sửa.';
        }
    }
    header('Location: audit_multi_service_invoices.php?days=' . $days);
    exit;
}

$sql = "
SELECT hd.ID_HD, hd.NGAY_GIO, hd.TONG_TIEN, lh.ID_LICHHEN, tk.HO_TEN, COALESCE(lh.TRAVEL_FEE,0) AS TRAVEL_FEE,
       COUNT(bi.ID_ITEM) AS SERVICE_COUNT
FROM hoa_don hd
JOIN lich_hen lh ON lh.ID_LICHHEN = hd.ID_LICHHEN
LEFT JOIN booking_item bi ON bi.ID_LICHHEN = lh.ID_LICHHEN AND bi.ITEM_TYPE = 'service'
LEFT JOIN tai_khoan tk ON tk.ID_TK = lh.ID_TK
WHERE hd.NGAY_GIO >= (NOW() - INTERVAL ? DAY)
GROUP BY hd.ID_HD
ORDER BY hd.NGAY_GIO DESC";

$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $days);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $idHD = (int)$row['ID_HD']; $idLH = (int)$row['ID_LICHHEN'];
        $items = fetchInvoiceItems($conn, $idLH);
        $fee = (float)$row['TRAVEL_FEE'];
        $expected = 0.0; foreach ($items as $it) { $expected += (float)$it['THANH_TIEN']; } $expected += $fee;
        $detailSum = null; $detailLines = 0;
        if ($hasDetailTable) {
            if ($hasMoTaColumns) {
                $sumSt = mysqli_prepare($conn, "SELECT COALESCE(SUM(THANH_TIEN),0), COUNT(*) FROM chi_tiet_hoa_don WHERE ID_HD = ?");
                if ($sumSt) { mysqli_stmt_bind_param($sumSt, 'i', $idHD); mysqli_stmt_execute($sumSt); mysqli_stmt_bind_result($sumSt, $detailSum, $detailLines); mysqli_stmt_fetch($sumSt); mysqli_stmt_close($sumSt); }
            } else {
                $sumSt = mysqli_prepare($conn, "SELECT COALESCE(SUM(DON_GIA),0), COUNT(*) FROM chi_tiet_hoa_don WHERE ID_HD = ?");
                if ($sumSt) { mysqli_stmt_bind_param($sumSt, 'i', $idHD); mysqli_stmt_execute($sumSt); mysqli_stmt_bind_result($sumSt, $detailSum, $detailLines); mysqli_stmt_fetch($sumSt); mysqli_stmt_close($sumSt); }
            }
        }
        $report[] = [
            'ID_HD' => $idHD,
            'ID_LH' => $idLH,
            'K_HO_TEN' => $row['HO_TEN'],
            'TONG_TIEN' => (float)$row['TONG_TIEN'],
            'EXPECTED' => $expected,
            'FEE' => $fee,
            'SERVICE_COUNT' => (int)$row['SERVICE_COUNT'],
            'DETAIL_SUM' => $detailSum,
            'DETAIL_LINES' => (int)$detailLines,
            'HAS_ITEMS' => !empty($items),
        ];
    }
    mysqli_stmt_close($stmt);
}

$notice = $_SESSION['audit_fix_notice'] ?? ''; unset($_SESSION['audit_fix_notice']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Audit hóa đơn đa dịch vụ</title>
  <link rel="stylesheet" href="../public/assets/css/invoice-notifications.css">
  <style>
    body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;background:#f8fafc;margin:0;padding:24px}
    .card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.04)}
    .table{width:100%;border-collapse:separate;border-spacing:0}
    th,td{padding:10px 12px;border-bottom:1px solid #e5e7eb;font-size:13px}
    th{background:#f1f5f9;text-align:left;color:#334155}
    .tag{display:inline-flex;align-items:center;border-radius:9999px;padding:2px 8px;font-size:12px;font-weight:600}
    .ok{background:#ecfdf5;color:#065f46}
    .warn{background:#fff7ed;color:#9a3412}
    .bad{background:#fee2e2;color:#991b1b}
    .btn{display:inline-block;background:#4f46e5;color:#fff;padding:8px 12px;border-radius:8px;text-decoration:none}
    .btn-outline{background:#fff;border:1px solid #c7d2fe;color:#3730a3}
    .toolbar{display:flex;gap:12px;align-items:center;margin-bottom:16px}
    .small{font-size:12px;color:#64748b}
  </style>
</head>
<body>
  <div class="card" style="max-width:1200px;margin:0 auto;">
    <h1 style="margin:0 0 10px;font-size:20px;color:#111827">Audit hóa đơn đa dịch vụ (lịch hẹn)</h1>
    <p class="small">Khoảng thời gian: <?= (int)$days ?> ngày gần đây</p>
    <div class="toolbar">
      <a href="?days=180" class="btn btn-outline">180 ngày</a>
      <a href="?days=365" class="btn btn-outline">365 ngày</a>
      <a href="?days=30" class="btn btn-outline">30 ngày</a>
      <?php if ($notice): ?><span class="tag ok"><?= h($notice) ?></span><?php endif; ?>
    </div>
    <div style="overflow:auto">
      <table class="table">
        <thead>
          <tr>
            <th>#HD</th>
            <th>#LH</th>
            <th>Khách</th>
            <th>Dịch vụ</th>
            <th>Phụ phí</th>
            <th>Kỳ vọng</th>
            <th>Tổng hóa đơn</th>
            <th>Chi tiết hiện có</th>
            <th>Trạng thái</th>
            <th>Hành động</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($report)): ?>
            <tr><td colspan="10" style="text-align:center;color:#64748b">Không có dữ liệu trong khoảng thời gian chọn.</td></tr>
          <?php else: foreach ($report as $r):
            $svcBadge = $r['SERVICE_COUNT'] > 1 ? '<span class="tag ok">'.(int)$r['SERVICE_COUNT'].' dv</span>' : '<span class="tag">1 dv</span>';
            $feeStr = formatMoney($r['FEE']);
            $expectedStr = formatMoney($r['EXPECTED']);
            $totalStr = formatMoney($r['TONG_TIEN']);
            $detailStr = $r['DETAIL_LINES'] ? (int)$r['DETAIL_LINES'].' dòng' : '—';
            $status = 'OK'; $cls='ok';
            if (abs(($r['EXPECTED'] ?: 0) - (float)$r['TONG_TIEN']) > 1) { $status='Sai tổng'; $cls='bad'; }
            elseif ($r['HAS_ITEMS'] && (int)$r['DETAIL_LINES'] < ($r['SERVICE_COUNT'] + ($r['FEE']>0?1:0))) { $status='Thiếu chi tiết'; $cls='warn'; }
          ?>
            <tr>
              <td><strong>#<?= (int)$r['ID_HD'] ?></strong></td>
              <td>#<?= (int)$r['ID_LH'] ?></td>
              <td><?= h($r['K_HO_TEN'] ?? '—') ?></td>
              <td><?= $svcBadge ?></td>
              <td><?= h($feeStr) ?></td>
              <td><?= h($expectedStr) ?></td>
              <td><?= h($totalStr) ?></td>
              <td><?= h($detailStr) ?></td>
              <td><span class="tag <?= $cls ?>"><?= h($status) ?></span></td>
              <td>
                <?php if ($cls !== 'ok'): ?>
                  <a class="btn" href="?days=<?= (int)$days ?>&fix=1&id_hd=<?= (int)$r['ID_HD'] ?>" onclick="return confirm('Tái tạo chi tiết cho hóa đơn #<?= (int)$r['ID_HD'] ?>?');">Sửa chi tiết</a>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
