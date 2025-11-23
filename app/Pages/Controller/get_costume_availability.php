<?php
// MVP: kiểm tra xem trang phục đã được dùng trong booking khác cùng ngày chưa.
header('Content-Type: application/json');
require_once __DIR__ . '/../../../database/config.php';

$costumeId = isset($_GET['costume_id']) ? (int)$_GET['costume_id'] : 0;
$branchId = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;
$date = isset($_GET['date']) ? $_GET['date'] : '';
if ($costumeId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['ok' => false, 'error' => 'Thiếu hoặc sai tham số']);
    exit;
}

try {
    $branchValidation = [
        'requested_branch_id' => $branchId,
        'branch_match' => null,
        'notes' => null,
    ];

    if ($branchId > 0) {
        $tableName = detectCostumeTable($conn);
        if ($tableName) {
            $columnId = columnExists($conn, $tableName, 'ID_TRANG_PHUC') ? 'ID_TRANG_PHUC' : 'ID_TP';
            $columnBranch = columnExists($conn, $tableName, 'ID_CN') ? 'ID_CN' : (columnExists($conn, $tableName, 'ID_CHINHANH') ? 'ID_CHINHANH' : null);
            if ($columnBranch) {
                $safeTable = backtick($tableName);
                $safeId = backtick($columnId);
                $safeBranch = backtick($columnBranch);
                $sql = "SELECT $safeBranch AS branch_id FROM $safeTable WHERE $safeId = ? LIMIT 1";
                $stmt = $conn->prepare($sql);
                if ($stmt === false) {
                    throw new RuntimeException('Không thể kiểm tra chi nhánh trang phục: ' . $conn->error);
                }
                $stmt->bind_param('i', $costumeId);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result ? $result->fetch_assoc() : null;
                $stmt->close();
                if (!$row) {
                    echo json_encode(['ok' => false, 'error' => 'Trang phục không tồn tại.']);
                    exit;
                }
                $branchValidation['branch_match'] = (int) $row['branch_id'] === $branchId;
                if (!$branchValidation['branch_match']) {
                    echo json_encode([
                        'ok' => false,
                        'error' => 'Trang phục không thuộc chi nhánh đã chọn.',
                        'branch_validation' => $branchValidation,
                    ]);
                    exit;
                }
            } else {
                $branchValidation['notes'] = 'Bảng trang phục chưa có cột chi nhánh, bỏ qua kiểm tra.';
            }
        } else {
            $branchValidation['notes'] = 'Không tìm thấy bảng trang phục.';
        }
    }

    // Kiểm tra nếu bảng BOOKING_ITEM tồn tại
    $tblCheck = $conn->query("SHOW TABLES LIKE 'BOOKING_ITEM'");
    if (!$tblCheck || $tblCheck->num_rows === 0) {
        echo json_encode([
            'ok' => true,
            'available' => true,
            'warning' => 'TABLE_BOOKING_ITEM_NOT_FOUND',
            'branch_validation' => $branchValidation,
        ]);
        exit;
    }

    $sql = "SELECT COUNT(*) AS cnt
            FROM BOOKING_ITEM bi
            JOIN lich_hen lh ON lh.ID_LICHHEN = bi.ID_LICHHEN
            WHERE bi.ITEM_TYPE = 'costume'
              AND bi.REF_ID = ?
              AND DATE(lh.THOI_GIAN_BAT_DAU) = ?
              AND lh.TRANGTHAI NOT IN ('Đã huỷ','cancelled')";
    if ($branchId > 0) {
        $sql .= " AND (lh.ID_CHINHANH = ? OR lh.ID_CHINHANH IS NULL)";
    }
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Lỗi truy vấn: ' . $conn->error);
    }
    if ($branchId > 0) {
        $stmt->bind_param('isi', $costumeId, $date, $branchId);
    } else {
        $stmt->bind_param('is', $costumeId, $date);
    }
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    $available = $count === 0; // đơn giản: nếu đã xuất hiện thì coi là không available
    echo json_encode([
        'ok' => true,
        'available' => $available,
        'costume_id' => $costumeId,
        'date' => $date,
        'branch_validation' => $branchValidation,
    ]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Lỗi hệ thống', 'message' => $e->getMessage()]);
}

function tableExists(mysqli $conn, string $name): bool
{
    $safe = $conn->real_escape_string($name);
    $sql = "SHOW TABLES LIKE '$safe'";
    if ($rs = $conn->query($sql)) {
        $exists = $rs->num_rows > 0;
        $rs->close();
        return $exists;
    }
    return false;
}

function columnExists(mysqli $conn, string $table, string $column): bool
{
    $safeTable = str_replace('`', '', $table);
    $safeColumn = $conn->real_escape_string($column);
    $sql = "SHOW COLUMNS FROM `$safeTable` LIKE '$safeColumn'";
    if ($rs = $conn->query($sql)) {
        $exists = $rs->num_rows > 0;
        $rs->close();
        return $exists;
    }
    return false;
}

function detectCostumeTable(mysqli $conn): ?string
{
    if (tableExists($conn, 'TRANG_PHUC')) {
        return 'TRANG_PHUC';
    }
    if (tableExists($conn, 'trang_phuc')) {
        return 'trang_phuc';
    }
    return null;
}

function backtick(string $identifier): string
{
    return '`' . str_replace('`', '', $identifier) . '`';
}
