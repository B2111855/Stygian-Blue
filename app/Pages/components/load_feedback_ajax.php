<?php
// ../components/load_feedback_ajax.php
include '../../../database/config.php';

$page  = max(1, (int)($_GET['page'] ?? 1));
$q     = trim($_GET['q'] ?? '');
$sort  = $_GET['sort'] ?? 'newest';
$limit = 6;
$start = ($page - 1) * $limit;

/*
  DB (theo stygianblue_dbv4.sql):
  phan_hoi_cua_khach_hang: ID_TK, ID_DV, NOI_DUNG, NGAY_GUI, XEP_HANG_DV
  tai_khoan:               ID_TK, HO_TEN, ...
  dich_vu:                 ID_DV, TEN_DV, ...
*/

$where = "WHERE 1=1";
$params = [];
$types  = '';

if ($q !== '') {
  $where .= " AND (
      PH.NOI_DUNG LIKE ?
      OR COALESCE(TK.HO_TEN, TK.ID_TK) LIKE ?
      OR COALESCE(DV.TEN_DV, '') LIKE ?
  )";
  $kw = "%$q%";
  $params[] = $kw; $types .= 's';
  $params[] = $kw; $types .= 's';
  $params[] = $kw; $types .= 's';
}

$order = "ORDER BY ";
if ($sort === 'highest') {
  $order .= "PH.XEP_HANG_DV DESC, ";
}
$order .= "PH.NGAY_GUI DESC";

$count_sql = "SELECT COUNT(*) AS c
              FROM phan_hoi_cua_khach_hang PH
              LEFT JOIN tai_khoan TK ON TK.ID_TK = PH.ID_TK
              LEFT JOIN dich_vu DV ON DV.ID_DV = PH.ID_DV
              $where";
$stmt_cnt = $conn->prepare($count_sql);
if ($types) $stmt_cnt->bind_param($types, ...$params);
$stmt_cnt->execute();
$total = (int)($stmt_cnt->get_result()->fetch_assoc()['c'] ?? 0);
$pages = max(1, (int)ceil($total / $limit));

$data_sql = "SELECT
    PH.ID_TK,
    COALESCE(TK.HO_TEN, PH.ID_TK) AS HO_TEN,
    PH.ID_DV,
    COALESCE(DV.TEN_DV,'') AS TEN_DV,
    PH.NOI_DUNG,
    PH.NGAY_GUI,
    PH.XEP_HANG_DV
  FROM phan_hoi_cua_khach_hang PH
  LEFT JOIN tai_khoan TK ON TK.ID_TK = PH.ID_TK
  LEFT JOIN dich_vu  DV ON DV.ID_DV = PH.ID_DV
  $where
  $order
  LIMIT ?, ?";

$stmt = $conn->prepare($data_sql);
if ($types) {
  $types2 = $types.'ii';
  $params2 = $params; $params2[] = $start; $params2[] = $limit;
  $stmt->bind_param($types2, ...$params2);
} else {
  $stmt->bind_param('ii', $start, $limit);
}
$stmt->execute();
$res = $stmt->get_result();

echo '<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">';
if ($res && $res->num_rows) {
  while ($r = $res->fetch_assoc()) {
    $name  = $r['HO_TEN'] ?: 'Khách hàng';
    $svc   = $r['TEN_DV'] ?: 'Dịch vụ';
    $svcId = (int)$r['ID_DV'];
    $txt   = $r['NOI_DUNG'] ?? '';
    $rate  = is_null($r['XEP_HANG_DV']) ? null : (float)$r['XEP_HANG_DV'];
    $dateISO = $r['NGAY_GUI'] ?? null;
    $dateTxt = $dateISO ? date('d/m/Y', strtotime($dateISO)) : '';

    $score = is_null($rate) ? '0' : number_format($rate,1,'.','');
    $stars = !is_null($rate)
      ? (str_repeat('★', (int)round($rate)) . str_repeat('☆', 5-(int)round($rate)))
      : '';

    echo '<article class="rounded-2xl bg-white/80 border border-slate-200 p-4 hover:shadow-lg transition text-left"
            data-fb-item data-score="'.htmlspecialchars($score,ENT_QUOTES).'"
            data-date="'.htmlspecialchars($dateISO??'',ENT_QUOTES).'" data-hasimg="0">';

    // Header: avatar đơn giản + TÊN KH + ngày + rating
    echo '  <div class="flex items-center gap-3">';
    echo '    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-cyan-100 to-fuchsia-100 flex items-center justify-center text-slate-600"><i class="far fa-user"></i></div>';
    echo '    <div class="flex-1">';
    echo '      <div class="font-semibold text-slate-900">'.htmlspecialchars($name).'</div>';
    echo '      <div class="text-xs text-slate-500">'.($dateTxt ?: '&nbsp;').'</div>';
    echo '    </div>';
    if ($stars !== '') echo '    <div class="ml-auto text-amber-500" title="'.$score.'/5">'.$stars.'</div>';
    echo '  </div>';

    // Badge DỊCH VỤ (link tới chi tiết)
    echo '  <div class="mt-3">';
    echo '    <a href="../views/dichvu_chitiet.php?id='.$svcId.'" class="inline-flex items-center gap-2 text-xs px-2.5 py-1 rounded-full bg-cyan-50 text-cyan-700 border border-cyan-200 hover:bg-cyan-100 transition" title="Xem dịch vụ">';
    echo '      <i class="fas fa-camera"></i> '.htmlspecialchars($svc).'</a>';
    echo '  </div>';

    // Nội dung
    if ($txt !== '') {
      echo '  <p class="mt-3 text-slate-700">'.nl2br(htmlspecialchars($txt)).'</p>';
    }

    echo '</article>';
  }
}
echo '</div>';

// Meta để JS vẽ phân trang
echo '<template id="fb-meta" data-total-pages="'.(int)$pages.'"></template>';
