<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../repositories/ServiceRepository.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use App\Repositories\ServiceRepository;

class ServiceExporter {
    private $conn;
    private $repo;
    
    public function __construct(\mysqli $conn) {
        $this->conn = $conn;
        $this->repo = new ServiceRepository($conn);
    }
    
    /**
     * Export services to Excel or CSV
     * @param string $format 'xlsx' or 'csv'
     * @param array $filters Search filters
     * @return string Path to generated file
     */
    public function export(string $format = 'xlsx', array $filters = []): string {
        $search = $filters['search'] ?? '';
        $status = $filters['status'] ?? null;
        $minPrice = $filters['min_price'] ?? null;
        $maxPrice = $filters['max_price'] ?? null;
        $categoryId = isset($filters['category']) && $filters['category'] ? (int)$filters['category'] : null;
        $tagId = isset($filters['tag']) && $filters['tag'] ? (int)$filters['tag'] : null;
        
        // Get all services matching filters (no limit)
        $services = $this->repo->searchServices(
            $search, 
            9999, 
            0, 
            $status, 
            $minPrice, 
            $maxPrice, 
            $categoryId, 
            $tagId
        );
        
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set headers
        $headers = ['ID', 'Tên Dịch Vụ', 'Mô Tả', 'Thời Gian (phút)', 'Giá (VNĐ)', 'Trạng Thái', 'Hình Ảnh'];
        $sheet->fromArray($headers, null, 'A1');
        
        // Style header row
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];
        $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);
        
        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(50);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(18);
        $sheet->getColumnDimension('F')->setWidth(15);
        $sheet->getColumnDimension('G')->setWidth(40);
        
        // Add data
        $row = 2;
        foreach ($services as $service) {
            $sheet->setCellValue('A' . $row, $service['ID_DV']);
            $sheet->setCellValue('B' . $row, $service['TEN_DV']);
            $sheet->setCellValue('C' . $row, $service['MOTA_DV']);
            $sheet->setCellValue('D' . $row, (int)$service['THOI_GIAN']);
            $sheet->setCellValue('E' . $row, isset($service['DON_GIA']) ? (int)$service['DON_GIA'] : 0);
            $sheet->setCellValue('F' . $row, $service['TRANG_THAI'] ?? 'active');
            $sheet->setCellValue('G' . $row, $service['IMAGE'] ?? '');
            
            // Apply zebra striping
            if ($row % 2 == 0) {
                $sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3F4F6']]
                ]);
            }
            
            $row++;
        }
        
        // Apply borders to all data
        $sheet->getStyle('A1:G' . ($row - 1))->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]]
        ]);
        
        // Generate filename
        $timestamp = date('Y-m-d_His');
        $filename = "dich_vu_export_{$timestamp}";
        
        // Create temp directory if not exists
        $tempDir = $_SERVER['DOCUMENT_ROOT'] . '/tmp/exports/';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }
        
        // Write file
        if ($format === 'csv') {
            $writer = new Csv($spreadsheet);
            $writer->setDelimiter(',');
            $writer->setEnclosure('"');
            $writer->setSheetIndex(0);
            $filepath = $tempDir . $filename . '.csv';
        } else {
            $writer = new Xlsx($spreadsheet);
            $filepath = $tempDir . $filename . '.xlsx';
        }
        
        $writer->save($filepath);
        
        return $filepath;
    }
    
    /**
     * Send file to browser for download
     */
    public function download(string $filepath, string $format = 'xlsx'): void {
        if (!file_exists($filepath)) {
            throw new \Exception('File không tồn tại');
        }
        if (headers_sent($file, $line)) {
            throw new \RuntimeException('headers_sent:' . $file . ':' . $line);
        }
        
        $filename = basename($filepath);
        $contentType = $format === 'csv' ? 'text/csv' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: max-age=0');
        
        readfile($filepath);
        
        // Clean up file after download
        @unlink($filepath);
    }
}
