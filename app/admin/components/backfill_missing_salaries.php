<?php
// Backfill zero salary rows for employees without current month/year salary
// Usage: visit this file in browser when logged in as admin/manager

require_once __DIR__ . '/../../../database/config.php';

header('Content-Type: text/html; charset=UTF-8');

$currentMonth = (int)date('n');
$currentYear = (int)date('Y');

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

try {
    // Get all employees (staff role)
    $empSql = "SELECT tk.ID_TK, nv.ID_CN, tk.HO_TEN
               FROM tai_khoan tk
               JOIN nhan_vien nv ON nv.ID_TK = tk.ID_TK
               WHERE tk.ID_QUYEN = 2";
    $empRes = $conn->query($empSql);
    if (!$empRes) {
        throw new RuntimeException('Không thể tải danh sách nhân viên: ' . $conn->error);
    }

    $insertCount = 0;
    $alreadyCount = 0;
    $errors = [];

    $checkStmt = $conn->prepare('SELECT 1 FROM luong_nhan_vien WHERE ID_TK = ? AND THANG = ? AND NAM = ? LIMIT 1');
    $insertStmt = $conn->prepare('INSERT INTO luong_nhan_vien (ID_TK, THANG, NAM, LUONG_CO_BAN, PHAN_TRAM_THUONG, TONG_TIEN_THUONG, TONG_LUONG, NGAY_TINH) VALUES (?, ?, ?, 0, 0, 0, 0, CURDATE())');
    if (!$checkStmt || !$insertStmt) {
        throw new RuntimeException('Không thể chuẩn bị truy vấn: ' . $conn->error);
    }

    while ($row = $empRes->fetch_assoc()) {
        $id = $row['ID_TK'];
        $checkStmt->bind_param('sii', $id, $currentMonth, $currentYear);
        $checkStmt->execute();
        $exists = $checkStmt->get_result()->fetch_assoc();
        if ($exists) {
            $alreadyCount++;
            continue;
        }
        $insertStmt->bind_param('sii', $id, $currentMonth, $currentYear);
        if (!$insertStmt->execute()) {
            $errors[] = 'Lỗi thêm lương cho ID ' . h($id) . ': ' . h($conn->error);
            continue;
        }
        $insertCount++;
    }

    $checkStmt->close();
    $insertStmt->close();

    echo '<div style="font-family:system-ui,Segoe UI; max-width:720px; margin:24px auto; padding:16px; border:1px solid #e5e7eb; border-radius:12px; background:#fff">';
    echo '<h2 style="margin:0 0 12px; color:#111827">Bổ sung bảng lương tháng ' . h($currentMonth) . '/' . h($currentYear) . '</h2>';
    echo '<p style="color:#374151">Đã thêm mới: <strong style="color:#10b981">' . h($insertCount) . '</strong> · Đã tồn tại: <strong>' . h($alreadyCount) . '</strong></p>';
    if ($errors) {
        echo '<div style="margin-top:12px; padding:8px; background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; border-radius:8px">';
        echo '<div style="font-weight:600; margin-bottom:6px">Các lỗi khi thêm:</div><ul style="margin:0 0 0 18px;">';
        foreach ($errors as $e) echo '<li>' . $e . '</li>';
        echo '</ul></div>';
    }
    echo '<div style="margin-top:16px">';
    echo '<a href="../admin_dashboard.php?page=salaries&month=' . h($currentMonth) . '&year=' . h($currentYear) . '" style="display:inline-block; padding:8px 12px; background:#4f46e5; color:#fff; border-radius:8px; text-decoration:none">Mở trang Quản lý bảng lương</a>';
    echo '</div>';
    echo '</div>';

} catch (Throwable $ex) {
    http_response_code(500);
    echo '<div style="font-family:system-ui,Segoe UI; max-width:720px; margin:24px auto; padding:16px; border:1px solid #fecaca; border-radius:12px; background:#fef2f2">';
    echo '<h2 style="margin:0 0 12px; color:#b91c1c">Lỗi bổ sung dữ liệu</h2>';
    echo '<p style="color:#7f1d1d">' . h($ex->getMessage()) . '</p>';
    echo '</div>';
}
