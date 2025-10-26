<?php
require '../../../vendor/autoload.php';
include '../../../database/config.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Lấy tham số truyền vào
$search = $_GET['search'] ?? '';
$month = isset($_GET['month']) ? (int)$_GET['month'] : null;
$year = isset($_GET['year']) ? (int)$_GET['year'] : null;

// Xây dựng điều kiện where
$conditions = [];
if (!empty($search)) {
    $escaped = mysqli_real_escape_string($conn, $search);
    $conditions[] = "tk.HO_TEN LIKE '%$escaped%'";
}
if ($month) $conditions[] = "lnv.THANG = $month";
if ($year) $conditions[] = "lnv.NAM = $year";
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Lấy danh sách dữ liệu
$query = "
    SELECT lnv.*, tk.HO_TEN
    FROM luong_nhan_vien lnv
    JOIN tai_khoan tk ON lnv.ID_TK = tk.ID_TK
    $where
    ORDER BY lnv.NAM DESC, lnv.THANG DESC
";
$result = mysqli_query($conn, $query);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Tiêu đề bảng
$sheet->mergeCells('A1:G1');
$sheet->setCellValue('A1', 'BẢNG LƯƠNG NHÂN VIÊN');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Đặt tiêu đề cột
$headers = ['Họ tên', 'Tháng', 'Năm', 'Lương cơ bản', 'Thưởng', 'Tổng lương', 'Ngày tính'];
$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue("{$col}2", $header);
    $sheet->getStyle("{$col}2")->getFont()->setBold(true);
    $sheet->getStyle("{$col}2")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getColumnDimension($col)->setAutoSize(true);
    $sheet->getStyle("{$col}2")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DDEEFF');
    $col++;
}

$rowIndex = 3;
while ($row = mysqli_fetch_assoc($result)) {
    $sheet->setCellValue("A$rowIndex", $row['HO_TEN']);
    $sheet->setCellValue("B$rowIndex", $row['THANG']);
    $sheet->setCellValue("C$rowIndex", $row['NAM']);
    $sheet->setCellValue("D$rowIndex", $row['LUONG_CO_BAN']);
    $sheet->setCellValue("E$rowIndex", $row['TONG_TIEN_THUONG']);
    $sheet->setCellValue("F$rowIndex", $row['TONG_LUONG']);
    $sheet->setCellValue("G$rowIndex", $row['NGAY_TINH']);
    $rowIndex++;
}

// Kẻ viền cho toàn bộ bảng
$lastRow = $rowIndex - 1;
$sheet->getStyle("A2:G$lastRow")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Xuất file
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="bang_luong.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;