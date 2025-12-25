<?php
/**
 * get_booked_slots.php
 * 
 * Fetch booked time slots for a date range and branch
 * Supports both GET (single day) and POST (date range) requests
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Suppress default error output
error_reporting(0);
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Connect to database
    include '../../../database/config.php';

    if (!isset($conn) || !$conn) {
        throw new Exception('Database connection failed');
    }

    // Handle both GET (legacy) and POST (weekly calendar) requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // POST request - for weekly calendar (date range)
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Invalid JSON input');
        }

        $branchId = intval($input['branch_id'] ?? 0);
        $startDate = $input['start_date'] ?? '';
        $endDate = $input['end_date'] ?? '';
        $serviceIds = $input['service_ids'] ?? [];

        // Validate inputs - be permissive, return empty list instead of error
        if (!$startDate || !$endDate) {
            http_response_code(200);
            echo json_encode(['success' => true, 'booked_slots' => [], 'count' => 0, 'error' => 'Missing dates']);
            exit;
        }
        
        if (!$branchId) {
            http_response_code(200);
            echo json_encode(['success' => true, 'booked_slots' => [], 'count' => 0, 'error' => 'No branch selected']);
            exit;
        }

        // Build query to get booked slots
        $query = "SELECT 
            DATE_FORMAT(THOI_GIAN_BAT_DAU, '%Y-%m-%d') as date,
            DATE_FORMAT(THOI_GIAN_BAT_DAU, '%H:%i') as time,
            COALESCE(DURATION_MIN, 60) as duration_min
        FROM lich_hen
        WHERE ID_CHINHANH = ?
            AND DATE(THOI_GIAN_BAT_DAU) BETWEEN ? AND ?
            AND TRANGTHAI IN ('Đang chờ', 'Xác nhận', 'Đã duyệt')";

        // Add service filter if provided
        if (!empty($serviceIds)) {
            $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
            $query .= " AND ID_DICH_VU IN ($placeholders)";
        }

        $query .= " ORDER BY THOI_GIAN_BAT_DAU";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }

        // Bind parameters
        $types = 'iss';
        $params = [$branchId, $startDate, $endDate];
        
        if (!empty($serviceIds)) {
            $types .= str_repeat('i', count($serviceIds));
            $params = array_merge($params, array_map('intval', $serviceIds));
        }

        $stmt->bind_param($types, ...$params);

        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }

        $result = $stmt->get_result();
        $bookedSlots = [];

        while ($row = $result->fetch_assoc()) {
            $bookedSlots[] = [
                'date' => $row['date'],
                'time' => $row['time'],
                'duration_min' => intval($row['duration_min'] ?? 30)
            ];
        }

        $stmt->close();
        
        // Return success response
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'booked_slots' => $bookedSlots,
            'count' => count($bookedSlots)
        ]);

    } else {
        // GET request - legacy single-day support
        if (!isset($_GET['date']) || !isset($_GET['branch_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing date or branch_id parameter', 'booked_slots' => []]);
            exit;
        }

        $date = $_GET['date'];
        $branchId = intval($_GET['branch_id']);

        $sql = "SELECT 
            DATE_FORMAT(THOI_GIAN_BAT_DAU, '%H:%i') AS gio,
            COALESCE(DURATION_MIN, 60) as duration_min
        FROM lich_hen 
        WHERE DATE(THOI_GIAN_BAT_DAU) = ? 
            AND ID_CHINHANH = ? 
            AND TRANGTHAI IN ('Đang chờ', 'Xác nhận', 'Đã duyệt')
        ORDER BY THOI_GIAN_BAT_DAU";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }

        $stmt->bind_param("si", $date, $branchId);
        
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }

        $result = $stmt->get_result();
        $bookedSlots = [];

        while ($row = $result->fetch_assoc()) {
            $bookedSlots[] = [
                'date' => $date,
                'time' => $row['gio'],
                'duration_min' => intval($row['duration_min'])
            ];
        }

        $stmt->close();

        // Return success response
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'booked_slots' => $bookedSlots,
            'count' => count($bookedSlots)
        ]);
    }

    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'booked_slots' => []
    ]);
}
?>
