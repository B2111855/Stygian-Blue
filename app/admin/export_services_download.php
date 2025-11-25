<?php
declare(strict_types=1);

require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../../middlewares/require_admin.php';
require_once __DIR__ . '/../helpers/export_services.php';

try {
    $format = $_GET['export'] ?? $_GET['format'] ?? 'xlsx';
    $format = strtolower(is_string($format) ? $format : 'xlsx');
    if (!in_array($format, ['xlsx', 'csv'], true)) {
        $format = 'xlsx';
    }

    $search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
    $status = isset($_GET['status']) ? (string)$_GET['status'] : 'all';
    if (!in_array($status, ['all', 'active', 'draft', 'retired'], true)) {
        $status = 'all';
    }

    $minPrice = isset($_GET['min_price']) && $_GET['min_price'] !== ''
        ? max(0, (int)$_GET['min_price'])
        : null;
    $maxPrice = isset($_GET['max_price']) && $_GET['max_price'] !== ''
        ? max(0, (int)$_GET['max_price'])
        : null;

    $category = isset($_GET['category']) && $_GET['category'] !== ''
        ? (int)$_GET['category']
        : null;
    if ($category !== null && $category <= 0) {
        $category = null;
    }

    $tag = isset($_GET['tag']) && $_GET['tag'] !== ''
        ? (int)$_GET['tag']
        : null;
    if ($tag !== null && $tag <= 0) {
        $tag = null;
    }

    $filters = [
        'search' => $search,
        'status' => $status === 'all' ? null : $status,
        'min_price' => $minPrice,
        'max_price' => $maxPrice,
        'category' => $category,
        'tag' => $tag,
    ];

    $exporter = new ServiceExporter($conn);
    $filepath = $exporter->export($format, $filters);

    if (ob_get_level() > 0) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    $exporter->download($filepath, $format);
    exit;
} catch (\Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo 'Không thể xuất dữ liệu: ' . $e->getMessage();
    exit;
}
