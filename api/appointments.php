<?php
/**
 * Appointments API
 * RESTful API endpoints for appointment management
 * 
 * Endpoints:
 * GET    /api/appointments              - List appointments with filters
 * GET    /api/appointments/{id}         - Get appointment details
 * POST   /api/appointments              - Create appointment
 * PUT    /api/appointments/{id}         - Update appointment
 * DELETE /api/appointments/{id}         - Delete appointment
 * PUT    /api/appointments/{id}/status  - Update status only
 * POST   /api/appointments/{id}/services - Add service to appointment
 * DELETE /api/appointments/{id}/services/{serviceId} - Remove service
 * POST   /api/appointments/{id}/notes   - Add note to appointment
 * DELETE /api/appointments/{id}/notes/{noteId} - Delete note
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

try {
    include '../../database/config.php';

    // Parse request
    $request_method = $_SERVER['REQUEST_METHOD'];
    $request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $path_parts = explode('/', trim($request_uri, '/'));
    
    // Extract endpoint parts (skip 'api' and 'appointments')
    $appointment_id = isset($path_parts[3]) && is_numeric($path_parts[3]) ? (int)$path_parts[3] : null;
    $sub_resource = isset($path_parts[4]) ? $path_parts[4] : null;
    $sub_resource_id = isset($path_parts[5]) && is_numeric($path_parts[5]) ? (int)$path_parts[5] : null;

    // Permission check function
    function checkPermission($conn, $appointment_id = null) {
        $user_id = isset($_SESSION['ID_TK']) ? (int)$_SESSION['ID_TK'] : null;
        $user_role = isset($_SESSION['ROLE']) ? $_SESSION['ROLE'] : null;
        
        if (!$user_id || !$user_role) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        // If checking specific appointment
        if ($appointment_id) {
            $query = "SELECT ID_CHINHANH FROM lich_hen WHERE ID_LICHHEN = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'i', $appointment_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $appointment = mysqli_fetch_assoc($result);

            if (!$appointment) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Appointment not found']);
                exit;
            }

            // Branch managers can only see their own branch
            if ($user_role === 'Branch Manager') {
                $query = "SELECT ID_CN FROM tai_khoan WHERE ID_TK = ?";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, 'i', $user_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $user = mysqli_fetch_assoc($result);

                if ($user && $user['ID_CN'] != $appointment['ID_CHINHANH']) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Forbidden: Access denied']);
                    exit;
                }
            }
        }

        return $user_id;
    }

    // GET - List appointments with filters
    if ($request_method === 'GET' && !$appointment_id) {
        $user_id = checkPermission($conn);

        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 10;
        $offset = ($page - 1) * $limit;

        // Build filter conditions
        $where = ["1=1"];
        $params = [];
        $types = '';

        if (!empty($_GET['customer'])) {
            $where[] = "tk.HO_TEN LIKE ?";
            $params[] = '%' . $_GET['customer'] . '%';
            $types .= 's';
        }

        if (!empty($_GET['service'])) {
            $where[] = "COALESCE(GROUP_CONCAT(dv.TEN_DV), dv_main.TEN_DV) LIKE ?";
            $params[] = '%' . $_GET['service'] . '%';
            $types .= 's';
        }

        if (!empty($_GET['date'])) {
            $where[] = "DATE(lh.THOI_GIAN_BAT_DAU) = ?";
            $params[] = $_GET['date'];
            $types .= 's';
        }

        if (!empty($_GET['status'])) {
            $where[] = "lh.TRANGTHAI = ?";
            $params[] = $_GET['status'];
            $types .= 's';
        }

        if (!empty($_GET['branch'])) {
            $where[] = "lh.ID_CHINHANH = ?";
            $params[] = (int)$_GET['branch'];
            $types .= 'i';
        }

        $where_clause = implode(' AND ', $where);

        // Count total
        $count_query = "
            SELECT COUNT(DISTINCT lh.ID_LICHHEN) as total
            FROM lich_hen lh
            INNER JOIN tai_khoan tk ON lh.ID_TK = tk.ID_TK
            INNER JOIN dich_vu dv_main ON lh.ID_DV = dv_main.ID_DV
            LEFT JOIN chi_nhanh cn ON lh.ID_CHINHANH = cn.ID_CN
            LEFT JOIN booking_item bi ON lh.ID_LICHHEN = bi.ID_LICHHEN AND bi.ITEM_TYPE = 'service'
            LEFT JOIN dich_vu dv ON dv.ID_DV = bi.REF_ID
            WHERE $where_clause
        ";

        $count_stmt = mysqli_prepare($conn, $count_query);
        if (!empty($params)) {
            mysqli_stmt_bind_param($count_stmt, $types, ...$params);
        }
        mysqli_stmt_execute($count_stmt);
        $count_result = mysqli_stmt_get_result($count_stmt);
        $count_row = mysqli_fetch_assoc($count_result);
        $total = $count_row['total'];
        $total_pages = ceil($total / $limit);

        // Get appointments
        $query = "
            SELECT DISTINCT
                lh.ID_LICHHEN,
                lh.THOI_GIAN_BAT_DAU,
                lh.DIA_CHI_HEN,
                lh.TRANGTHAI,
                tk.HO_TEN,
                dv_main.TEN_DV,
                dv_main.ID_DV,
                cn.TEN_CN,
                GROUP_CONCAT(dv.TEN_DV ORDER BY bi.ID_ITEM SEPARATOR ', ') as EXTRA_SERVICES
            FROM lich_hen lh
            INNER JOIN tai_khoan tk ON lh.ID_TK = tk.ID_TK
            INNER JOIN dich_vu dv_main ON lh.ID_DV = dv_main.ID_DV
            LEFT JOIN chi_nhanh cn ON lh.ID_CHINHANH = cn.ID_CN
            LEFT JOIN booking_item bi ON lh.ID_LICHHEN = bi.ID_LICHHEN AND bi.ITEM_TYPE = 'service'
            LEFT JOIN dich_vu dv ON dv.ID_DV = bi.REF_ID
            WHERE $where_clause
            GROUP BY lh.ID_LICHHEN
            ORDER BY lh.THOI_GIAN_BAT_DAU DESC
            LIMIT ?, ?
        ";

        $stmt = mysqli_prepare($conn, $query);
        $params[] = $offset;
        $params[] = $limit;
        $types .= 'ii';

        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $appointments = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $appointments[] = [
                'id' => (int)$row['ID_LICHHEN'],
                'customer_name' => $row['HO_TEN'],
                'primary_service' => $row['TEN_DV'],
                'service_id' => (int)$row['ID_DV'],
                'extra_services' => $row['EXTRA_SERVICES'] ? explode(', ', $row['EXTRA_SERVICES']) : [],
                'start_time' => $row['THOI_GIAN_BAT_DAU'],
                'address' => $row['DIA_CHI_HEN'],
                'branch_name' => $row['TEN_CN'] ?: 'Not assigned',
                'status' => $row['TRANGTHAI']
            ];
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $appointments,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $total_pages
            ]
        ]);
        exit;
    }

    // GET - Single appointment details
    if ($request_method === 'GET' && $appointment_id && !$sub_resource) {
        $user_id = checkPermission($conn, $appointment_id);

        $query = "
            SELECT 
                lh.ID_LICHHEN,
                lh.ID_TK,
                lh.ID_DV,
                lh.ID_CHINHANH,
                lh.THOI_GIAN_BAT_DAU,
                lh.DIA_CHI_HEN,
                lh.TRANGTHAI,
                tk.HO_TEN,
                dv.TEN_DV,
                cn.TEN_CN
            FROM lich_hen lh
            INNER JOIN tai_khoan tk ON lh.ID_TK = tk.ID_TK
            INNER JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV
            LEFT JOIN chi_nhanh cn ON lh.ID_CHINHANH = cn.ID_CN
            WHERE lh.ID_LICHHEN = ?
        ";

        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'i', $appointment_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $appointment = mysqli_fetch_assoc($result);

        if (!$appointment) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Appointment not found']);
            exit;
        }

        // Get additional services
        $services_query = "
            SELECT DISTINCT dv.ID_DV, dv.TEN_DV
            FROM booking_item bi
            JOIN dich_vu dv ON dv.ID_DV = bi.REF_ID
            WHERE bi.ID_LICHHEN = ? AND bi.ITEM_TYPE = 'service'
        ";
        $stmt = mysqli_prepare($conn, $services_query);
        mysqli_stmt_bind_param($stmt, 'i', $appointment_id);
        mysqli_stmt_execute($stmt);
        $services_result = mysqli_stmt_get_result($stmt);
        $services = [];
        while ($svc = mysqli_fetch_assoc($services_result)) {
            $services[] = ['id' => (int)$svc['ID_DV'], 'name' => $svc['TEN_DV']];
        }

        // Get notes
        $notes_query = "
            SELECT 
                ID_GHI_CHU,
                NOI_DUNG,
                CREATED_AT,
                ID_TK as created_by
            FROM ghi_chu
            WHERE ID_LICHHEN = ?
            ORDER BY CREATED_AT DESC
        ";
        $stmt = mysqli_prepare($conn, $notes_query);
        mysqli_stmt_bind_param($stmt, 'i', $appointment_id);
        mysqli_stmt_execute($stmt);
        $notes_result = mysqli_stmt_get_result($stmt);
        $notes = [];
        while ($note = mysqli_fetch_assoc($notes_result)) {
            $notes[] = [
                'id' => (int)$note['ID_GHI_CHU'],
                'text' => $note['NOI_DUNG'],
                'created_at' => $note['CREATED_AT'],
                'created_by' => (int)$note['created_by']
            ];
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => (int)$appointment['ID_LICHHEN'],
                'customer_id' => (int)$appointment['ID_TK'],
                'customer_name' => $appointment['HO_TEN'],
                'primary_service_id' => (int)$appointment['ID_DV'],
                'primary_service_name' => $appointment['TEN_DV'],
                'additional_services' => $services,
                'branch_id' => $appointment['ID_CHINHANH'] ? (int)$appointment['ID_CHINHANH'] : null,
                'branch_name' => $appointment['TEN_CN'] ?: null,
                'start_time' => $appointment['THOI_GIAN_BAT_DAU'],
                'address' => $appointment['DIA_CHI_HEN'],
                'status' => $appointment['TRANGTHAI'],
                'notes' => $notes
            ]
        ]);
        exit;
    }

    // POST - Create appointment
    if ($request_method === 'POST' && !$appointment_id) {
        $user_id = checkPermission($conn);

        $input = $_POST;

        // Validation
        $errors = [];

        if (empty($input['customer_id'])) {
            $errors[] = 'Customer ID is required';
        } else {
            $cust_query = "SELECT ID_TK FROM tai_khoan WHERE ID_TK = ?";
            $stmt = mysqli_prepare($conn, $cust_query);
            mysqli_stmt_bind_param($stmt, 'i', $input['customer_id']);
            mysqli_stmt_execute($stmt);
            if (mysqli_stmt_get_result($stmt)->num_rows === 0) {
                $errors[] = 'Customer does not exist';
            }
        }

        if (empty($input['service_id'])) {
            $errors[] = 'Service is required';
        } else {
            $svc_query = "SELECT ID_DV FROM dich_vu WHERE ID_DV = ?";
            $stmt = mysqli_prepare($conn, $svc_query);
            mysqli_stmt_bind_param($stmt, 'i', $input['service_id']);
            mysqli_stmt_execute($stmt);
            if (mysqli_stmt_get_result($stmt)->num_rows === 0) {
                $errors[] = 'Service does not exist';
            }
        }

        if (empty($input['start_time'])) {
            $errors[] = 'Start time is required';
        } elseif (strtotime($input['start_time']) <= time()) {
            $errors[] = 'Start time must be in the future';
        }

        if (empty($input['address'])) {
            $errors[] = 'Address is required';
        } elseif (strlen($input['address']) < 5) {
            $errors[] = 'Address must be at least 5 characters';
        }

        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
            exit;
        }

        // Insert appointment
        $insert_query = "
            INSERT INTO lich_hen (ID_TK, ID_DV, ID_CHINHANH, THOI_GIAN_BAT_DAU, DIA_CHI_HEN, TRANGTHAI)
            VALUES (?, ?, ?, ?, ?, 'Đang chờ')
        ";

        $stmt = mysqli_prepare($conn, $insert_query);
        $branch_id = !empty($input['branch_id']) ? $input['branch_id'] : null;
        
        mysqli_stmt_bind_param($stmt, 'iiis', 
            $input['customer_id'],
            $input['service_id'],
            $branch_id,
            $input['start_time']
        );

        if (!mysqli_stmt_execute($stmt)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to create appointment']);
            exit;
        }

        $new_id = mysqli_insert_id($conn);

        // Add address
        $addr_query = "UPDATE lich_hen SET DIA_CHI_HEN = ? WHERE ID_LICHHEN = ?";
        $stmt = mysqli_prepare($conn, $addr_query);
        mysqli_stmt_bind_param($stmt, 'si', $input['address'], $new_id);
        mysqli_stmt_execute($stmt);

        // Add additional services if provided
        if (!empty($input['additional_services']) && is_array($input['additional_services'])) {
            $booking_query = "INSERT INTO booking_item (ID_LICHHEN, ITEM_TYPE, REF_ID) VALUES (?, 'service', ?)";
            $stmt = mysqli_prepare($conn, $booking_query);

            foreach ($input['additional_services'] as $svc_id) {
                mysqli_stmt_bind_param($stmt, 'ii', $new_id, $svc_id);
                mysqli_stmt_execute($stmt);
            }
        }

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Appointment created successfully',
            'data' => ['id' => $new_id]
        ]);
        exit;
    }

    // PUT - Update appointment
    if ($request_method === 'PUT' && $appointment_id && !$sub_resource) {
        $user_id = checkPermission($conn, $appointment_id);

        // Get existing appointment
        $get_query = "SELECT * FROM lich_hen WHERE ID_LICHHEN = ?";
        $stmt = mysqli_prepare($conn, $get_query);
        mysqli_stmt_bind_param($stmt, 'i', $appointment_id);
        mysqli_stmt_execute($stmt);
        $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Appointment not found']);
            exit;
        }

        // Check if appointment is overdue and not already marked as no-show/completed/cancelled
        $appointmentTime = strtotime($existing['THOI_GIAN_BAT_DAU']);
        $isOverdue = $appointmentTime < time() && !in_array($existing['TRANGTHAI'], ['Không đến', 'Đã hoàn thành', 'Đã hủy']);
        
        // Parse PUT data
        parse_str(file_get_contents('php://input'), $input);

        // If overdue, only allow status change, not time/address
        if ($isOverdue && (isset($input['start_time']) || isset($input['address']))) {
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => 'Appointment is overdue. You can only change status, not time or address. Please mark as "Không đến" (No-show) or "Đã hoàn thành" (Completed).'
            ]);
            exit;
        }

        // Validation for updates
        $errors = [];

        if (isset($input['customer_id']) && !empty($input['customer_id'])) {
            $cust_query = "SELECT ID_TK FROM tai_khoan WHERE ID_TK = ?";
            $stmt = mysqli_prepare($conn, $cust_query);
            mysqli_stmt_bind_param($stmt, 'i', $input['customer_id']);
            mysqli_stmt_execute($stmt);
            if (mysqli_stmt_get_result($stmt)->num_rows === 0) {
                $errors[] = 'Customer does not exist';
            }
        }

        if (isset($input['service_id']) && !empty($input['service_id'])) {
            $svc_query = "SELECT ID_DV FROM dich_vu WHERE ID_DV = ?";
            $stmt = mysqli_prepare($conn, $svc_query);
            mysqli_stmt_bind_param($stmt, 'i', $input['service_id']);
            mysqli_stmt_execute($stmt);
            if (mysqli_stmt_get_result($stmt)->num_rows === 0) {
                $errors[] = 'Service does not exist';
            }
        }

        if (isset($input['start_time']) && !empty($input['start_time'])) {
            if (strtotime($input['start_time']) <= time()) {
                $errors[] = 'Start time must be in the future';
            }
        }

        if (isset($input['address']) && strlen($input['address']) < 5) {
            $errors[] = 'Address must be at least 5 characters';
        }

        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
            exit;
        }

        // Update appointment
        $updates = [];
        $params = [];
        $types = '';

        if (isset($input['customer_id'])) {
            $updates[] = "ID_TK = ?";
            $params[] = $input['customer_id'];
            $types .= 'i';
        }

        if (isset($input['service_id'])) {
            $updates[] = "ID_DV = ?";
            $params[] = $input['service_id'];
            $types .= 'i';
        }

        if (isset($input['start_time'])) {
            $updates[] = "THOI_GIAN_BAT_DAU = ?";
            $params[] = $input['start_time'];
            $types .= 's';
        }

        if (isset($input['address'])) {
            $updates[] = "DIA_CHI_HEN = ?";
            $params[] = $input['address'];
            $types .= 's';
        }

        if (isset($input['branch_id'])) {
            $updates[] = "ID_CHINHANH = ?";
            $params[] = !empty($input['branch_id']) ? $input['branch_id'] : null;
            $types .= 'i';
        }

        if (!empty($updates)) {
            $params[] = $appointment_id;
            $types .= 'i';

            $update_query = "UPDATE lich_hen SET " . implode(', ', $updates) . " WHERE ID_LICHHEN = ?";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Appointment updated successfully'
        ]);
        exit;
    }

    // DELETE - Delete appointment
    if ($request_method === 'DELETE' && $appointment_id && !$sub_resource) {
        $user_id = checkPermission($conn, $appointment_id);

        // Check if appointment exists
        $check_query = "SELECT ID_LICHHEN FROM lich_hen WHERE ID_LICHHEN = ?";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, 'i', $appointment_id);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_get_result($stmt)->num_rows === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Appointment not found']);
            exit;
        }

        // Update status to "Đã hủy" (soft delete)
        $delete_query = "UPDATE lich_hen SET TRANGTHAI = 'Đã hủy' WHERE ID_LICHHEN = ?";
        $stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($stmt, 'i', $appointment_id);
        mysqli_stmt_execute($stmt);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Appointment deleted successfully'
        ]);
        exit;
    }

    // PUT - Update status only
    if ($request_method === 'PUT' && $appointment_id && $sub_resource === 'status') {
        $user_id = checkPermission($conn, $appointment_id);

        parse_str(file_get_contents('php://input'), $input);

        if (empty($input['status'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Status is required']);
            exit;
        }

        $valid_statuses = ['Đang chờ', 'Đã xác nhận', 'Đã hoàn thành', 'Đã hủy'];
        if (!in_array($input['status'], $valid_statuses)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid status value']);
            exit;
        }

        $status_query = "UPDATE lich_hen SET TRANGTHAI = ? WHERE ID_LICHHEN = ?";
        $stmt = mysqli_prepare($conn, $status_query);
        mysqli_stmt_bind_param($stmt, 'si', $input['status'], $appointment_id);
        mysqli_stmt_execute($stmt);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Status updated successfully'
        ]);
        exit;
    }

    // POST - Add service to appointment
    if ($request_method === 'POST' && $appointment_id && $sub_resource === 'services') {
        $user_id = checkPermission($conn, $appointment_id);

        if (empty($_POST['service_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Service ID is required']);
            exit;
        }

        // Check service exists
        $svc_check = "SELECT ID_DV FROM dich_vu WHERE ID_DV = ?";
        $stmt = mysqli_prepare($conn, $svc_check);
        mysqli_stmt_bind_param($stmt, 'i', $_POST['service_id']);
        mysqli_stmt_execute($stmt);
        if (mysqli_stmt_get_result($stmt)->num_rows === 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Service does not exist']);
            exit;
        }

        // Check not already added
        $check_query = "SELECT ID_ITEM FROM booking_item WHERE ID_LICHHEN = ? AND REF_ID = ? AND ITEM_TYPE = 'service'";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, 'ii', $appointment_id, $_POST['service_id']);
        mysqli_stmt_execute($stmt);
        if (mysqli_stmt_get_result($stmt)->num_rows > 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Service already added to appointment']);
            exit;
        }

        // Add service
        $add_query = "INSERT INTO booking_item (ID_LICHHEN, ITEM_TYPE, REF_ID) VALUES (?, 'service', ?)";
        $stmt = mysqli_prepare($conn, $add_query);
        mysqli_stmt_bind_param($stmt, 'ii', $appointment_id, $_POST['service_id']);
        mysqli_stmt_execute($stmt);

        $new_id = mysqli_insert_id($conn);

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Service added successfully',
            'data' => ['booking_item_id' => $new_id]
        ]);
        exit;
    }

    // DELETE - Remove service from appointment
    if ($request_method === 'DELETE' && $appointment_id && $sub_resource === 'services' && $sub_resource_id) {
        $user_id = checkPermission($conn, $appointment_id);

        // Check if booking item exists
        $check_query = "SELECT ID_ITEM FROM booking_item WHERE ID_ITEM = ? AND ID_LICHHEN = ?";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, 'ii', $sub_resource_id, $appointment_id);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_get_result($stmt)->num_rows === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Service booking not found']);
            exit;
        }

        // Delete booking item
        $delete_query = "DELETE FROM booking_item WHERE ID_ITEM = ?";
        $stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($stmt, 'i', $sub_resource_id);
        mysqli_stmt_execute($stmt);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Service removed successfully'
        ]);
        exit;
    }

    // POST - Add note to appointment
    if ($request_method === 'POST' && $appointment_id && $sub_resource === 'notes') {
        $user_id = checkPermission($conn, $appointment_id);

        if (empty($_POST['note_text']) || strlen(trim($_POST['note_text'])) === 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Note text is required']);
            exit;
        }

        if (strlen($_POST['note_text']) > 1000) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Note must not exceed 1000 characters']);
            exit;
        }

        // Insert note
        $insert_query = "
            INSERT INTO ghi_chu (ID_LICHHEN, NOI_DUNG, CREATED_AT, ID_TK)
            VALUES (?, ?, NOW(), ?)
        ";
        $stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($stmt, 'isi', $appointment_id, $_POST['note_text'], $user_id);
        mysqli_stmt_execute($stmt);

        $new_id = mysqli_insert_id($conn);

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Note added successfully',
            'data' => ['note_id' => $new_id]
        ]);
        exit;
    }

    // DELETE - Delete note
    if ($request_method === 'DELETE' && $appointment_id && $sub_resource === 'notes' && $sub_resource_id) {
        $user_id = checkPermission($conn, $appointment_id);

        // Check if note exists and user has permission
        $check_query = "SELECT ID_GHI_CHU, ID_TK FROM ghi_chu WHERE ID_GHI_CHU = ? AND ID_LICHHEN = ?";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, 'ii', $sub_resource_id, $appointment_id);
        mysqli_stmt_execute($stmt);
        $note = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if (!$note) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Note not found']);
            exit;
        }

        // Check permission (creator or admin)
        $user_role = isset($_SESSION['ROLE']) ? $_SESSION['ROLE'] : null;
        if ($note['ID_TK'] != $user_id && $user_role !== 'Admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden: You can only delete your own notes']);
            exit;
        }

        // Delete note
        $delete_query = "DELETE FROM ghi_chu WHERE ID_GHI_CHU = ?";
        $stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($stmt, 'i', $sub_resource_id);
        mysqli_stmt_execute($stmt);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Note deleted successfully'
        ]);
        exit;
    }

    // 404 - Invalid endpoint
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Invalid endpoint']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
