<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../middlewares/require_staff_manager.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../database/config.php';
require_once __DIR__ . '/../../helpers/branch_salary.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$managerId = $_SESSION['ID_TK'] ?? null;
if (!$managerId) {
    exit('Thiếu thông tin đăng nhập.');
}

$branchId = getBranchIdForUser($conn, $managerId);
if (!$branchId) {
    exit('Không thể xác định chi nhánh.');
}

ensureBranchSalaryMetaTable($conn);

$month = isset($_GET['month']) ? max(1, min(12, (int)$_GET['month'])) : (int)date('n');
$year = isset($_GET['year']) ? max(2000, (int)$_GET['year']) : (int)date('Y');
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$statusFilter = in_array($statusFilter, ['cho_duyet', 'da_duyet', 'da_chi_tra'], true) ? $statusFilter : '';

$conditions = ['nv.ID_CN = ?', 'tk.ID_QUYEN = 2'];
$params = [$branchId];
$types = 'i';

if ($search !== '') {
    $conditions[] = 'tk.HO_TEN LIKE ?';
    $params[] = '%' . $search . '%';
    $types .= 's';
}

if ($statusFilter !== '') {
    $conditions[] = "COALESCE(meta.TRANG_THAI, 'cho_duyet') = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

$whereClause = implode(' AND ', $conditions);

$sql = "
    SELECT
        tk.HO_TEN,
        tk.SDT,
        nv.CHUYEN_MON,
        lnv.LUONG_CO_BAN,
        lnv.TONG_TIEN_THUONG,
        meta.PHU_CAP,
        meta.KHOAN_TRU,
        COALESCE(meta.THUC_LINH, lnv.TONG_LUONG) AS THUC_LINH,
        COALESCE(meta.TRANG_THAI, 'cho_duyet') AS TRANG_THAI,
        meta.GHI_CHU,
        meta.NGAY_CHI_TRA
    FROM nhan_vien nv
    INNER JOIN tai_khoan tk ON tk.ID_TK = nv.ID_TK
    LEFT JOIN luong_nhan_vien lnv ON lnv.ID_TK = nv.ID_TK AND lnv.THANG = ? AND lnv.NAM = ?
    LEFT JOIN quanly_luong_chinhanh meta ON meta.ID_TK_NV = nv.ID_TK AND meta.THANG = ? AND meta.NAM = ?
    WHERE $whereClause
    ORDER BY tk.HO_TEN ASC";

$stmt = $conn->prepare($sql);
$bindTypes = 'iiii' . $types;
$bindValues = [$month, $year, $month, $year, ...$params];
$bindParams = [$bindTypes];
foreach ($bindValues as $key => $value) {
    $bindParams[] = &$bindValues[$key];
}
call_user_func_array([$stmt, 'bind_param'], $bindParams);
$stmt->execute();
$result = $stmt->get_result();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$sheet->mergeCells('A1:I1');
$sheet->setCellValue('A1', 'Bảng lương chi nhánh tháng ' . $month . '/' . $year);
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$headers = ['Nhân viên', 'Chuyên môn', 'Lương cơ bản', 'Thưởng', 'Phụ cấp', 'Khấu trừ', 'Thực lĩnh', 'Trạng thái', 'Ngày chi trả', 'Ghi chú'];
$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . '2', $header);
    $sheet->getStyle($col . '2')->getFont()->setBold(true);
    $sheet->getStyle($col . '2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle($col . '2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3F4F6');
    $col++;
}

$rowIndex = 3;
while ($row = $result->fetch_assoc()) {
    $sheet->setCellValue('A' . $rowIndex, $row['HO_TEN']);
    $sheet->setCellValue('B' . $rowIndex, $row['CHUYEN_MON']);
    $sheet->setCellValue('C' . $rowIndex, $row['LUONG_CO_BAN']);
    $sheet->setCellValue('D' . $rowIndex, $row['TONG_TIEN_THUONG']);
    $sheet->setCellValue('E' . $rowIndex, $row['PHU_CAP']);
    $sheet->setCellValue('F' . $rowIndex, $row['KHOAN_TRU']);
    $sheet->setCellValue('G' . $rowIndex, $row['THUC_LINH']);
    switch ($row['TRANG_THAI']) {
        case 'da_duyet':
            $statusLabel = 'Đã duyệt';
            break;
        case 'da_chi_tra':
            $statusLabel = 'Đã chi trả';
            break;
        default:
            $statusLabel = 'Chờ duyệt';
    }
    $sheet->setCellValue('H' . $rowIndex, $statusLabel);
    $sheet->setCellValue('I' . $rowIndex, $row['NGAY_CHI_TRA']);
    $sheet->setCellValue('J' . $rowIndex, $row['GHI_CHU']);
    $rowIndex++;
}

foreach (range('A', 'J') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

$filename = sprintf('bang-luong-chi-nhanh-%d-%02d.xlsx', $year, $month);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
