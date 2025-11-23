<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../database/config.php';
if (file_exists(__DIR__ . '/../../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../../vendor/autoload.php';
}

$branchId = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;
if ($branchId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Chi nhánh không hợp lệ.']);
    exit;
}

try {
    $tableName = detectCostumeTable($conn);
    if ($tableName === null) {
        throw new RuntimeException('Không tìm thấy bảng trang phục trong cơ sở dữ liệu.');
    }

    $columnId = columnExists($conn, $tableName, 'ID_TRANG_PHUC') ? 'ID_TRANG_PHUC' : 'ID_TP';
    $columnName = columnExists($conn, $tableName, 'TEN') ? 'TEN' : 'TEN_TP';
    $columnSize = columnExists($conn, $tableName, 'SIZE') ? 'SIZE' : null;
    $columnColor = columnExists($conn, $tableName, 'MAU_SAC') ? 'MAU_SAC' : (columnExists($conn, $tableName, 'MAU') ? 'MAU' : null);
    $columnStatus = columnExists($conn, $tableName, 'TRANG_THAI') ? 'TRANG_THAI' : (columnExists($conn, $tableName, 'TINH_TRANG') ? 'TINH_TRANG' : null);
    $columnBranch = columnExists($conn, $tableName, 'ID_CN') ? 'ID_CN' : (columnExists($conn, $tableName, 'ID_CHI_NHANH') ? 'ID_CHI_NHANH' : null);
    $columnActive = columnExists($conn, $tableName, 'IS_ACTIVE') ? 'IS_ACTIVE' : null;
    $columnUpdated = columnExists($conn, $tableName, 'UPDATED_AT') ? 'UPDATED_AT' : null;
    $columnGia = columnExists($conn, $tableName, 'GIA_THUE') ? 'GIA_THUE' : null;

    $priceExpr = '0 AS GIA_THUE';
    $priceJoin = '';
    if ($columnGia !== null) {
        $priceExpr = "tp.$columnGia AS GIA_THUE";
    } elseif (tableExists($conn, 'don_gia_trang_phuc')) {
        $priceExpr = 'COALESCE(gia.DON_GIA, 0) AS GIA_THUE';
        $priceJoin = "LEFT JOIN (
            SELECT dg.ID_TP, dg.DON_GIA
            FROM don_gia_trang_phuc dg
            JOIN (
                SELECT ID_TP, MAX(NGAY_GIO) AS MAX_TIME
                FROM don_gia_trang_phuc
                GROUP BY ID_TP
            ) latest ON latest.ID_TP = dg.ID_TP AND latest.MAX_TIME = dg.NGAY_GIO
        ) gia ON gia.ID_TP = tp.$columnId";
    }

    $branchFilterable = $columnBranch !== null;
    $conditions = [];
    $paramTypes = '';
    $paramValues = [];
    if ($branchFilterable) {
        $conditions[] = "tp.$columnBranch = ?";
        $paramTypes .= 'i';
        $paramValues[] = $branchId;
    }
    if ($columnActive) {
        $conditions[] = "tp.$columnActive = 1";
    }
    if ($columnStatus) {
        $conditions[] = "tp.$columnStatus IN ('available','san_sang')";
    }
    $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $sizeSelect = $columnSize ? "tp.$columnSize AS SIZE" : 'NULL AS SIZE';
    $colorSelect = $columnColor ? "tp.$columnColor AS MAU_SAC" : 'NULL AS MAU_SAC';
    $statusSelect = $columnStatus ? "tp.$columnStatus AS TRANG_THAI" : 'NULL AS TRANG_THAI';
    $updatedSelect = $columnUpdated ? "tp.$columnUpdated AS UPDATED_AT" : 'NULL AS UPDATED_AT';
    $orderBy = $columnUpdated ? "tp.$columnUpdated DESC" : "tp.$columnId DESC";

    $sql = "SELECT tp.$columnId AS ID_TRANG_PHUC,
                   tp.$columnName AS TEN,
                   $sizeSelect,
                   $colorSelect,
                   $priceExpr,
                   $statusSelect,
                   $updatedSelect
                        FROM $tableName tp
                        $priceJoin
                        $whereClause
            ORDER BY $orderBy
            LIMIT 100";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Không thể chuẩn bị truy vấn trang phục: ' . $conn->error);
    }
        if ($paramTypes !== '') {
                $stmt->bind_param($paramTypes, ...$paramValues);
        }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    echo json_encode([
        'ok' => true,
        'branch_id' => $branchId,
        'branch_filter_applied' => $branchFilterable,
        'warning' => $branchFilterable ? null : 'Bảng trang phục chưa có cột chi nhánh; trả về danh sách chung.',
        'costumes' => $rows,
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'error' => 'Không thể tải trang phục.',
        'message' => $e->getMessage(),
    ]);
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
