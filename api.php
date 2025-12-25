<?php

/**
 * Main API entry point for costume rental system
 * Handles all REST API requests
 * 
 * Usage:
 * - Copy to htdocs root or api folder
 * - Route all /api/* requests to this file (via .htaccess or web server config)
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Ensure JSON response even on fatal errors
if (!function_exists('register_json_shutdown_handler')) {
    function register_json_shutdown_handler() {
        register_shutdown_function(function() {
            $err = error_get_last();
            if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                if (!headers_sent()) {
                    http_response_code(500);
                    header('Content-Type: application/json');
                }
                $payload = [
                    'success' => false,
                    'error' => 'Internal server error',
                    'message' => $err['message'] ?? 'Fatal error'
                ];
                echo json_encode($payload);
            }
        });
    }
}

register_json_shutdown_handler();

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include database connection (project-local)
require_once __DIR__ . '/database/config.php';

// PSR-4 Autoload
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

// Manual requires if no autoloader
if (!function_exists('require_app_file')) {
    function require_app_file(string $file) {
        $path = __DIR__ . '/' . ltrim($file, '/');
        if (file_exists($path)) {
            require_once $path;
        }
    }
}

// Load routing and controllers
require_app_file('app/helpers/system_log.php');
require_app_file('app/routing/ApiRouter.php');
require_app_file('app/repositories/CostumeRepository.php');
require_app_file('app/repositories/CostumeTypeRepository.php');
require_app_file('app/repositories/CostumeGroupRepository.php');
require_app_file('app/repositories/CostumePackageMasterRepository.php');
require_app_file('app/repositories/PackageCostumeRepository.php');
require_app_file('app/repositories/RentalValidationRepository.php');
require_app_file('app/repositories/ServiceRepository.php');
require_app_file('app/repositories/DeviceRepository.php');
require_app_file('app/services/CostumeService.php');
require_app_file('app/services/CostumePackageService.php');
require_app_file('app/services/RentalService.php');
require_app_file('app/services/ServiceService.php');
require_app_file('app/services/DeviceService.php');
require_app_file('app/controllers/CostumeController.php');
require_app_file('app/controllers/PackageController.php');
require_app_file('app/controllers/RentalController.php');
require_app_file('app/controllers/ServiceController.php');
require_app_file('app/controllers/AssignmentController.php');
require_app_file('app/controllers/DeviceController.php');

use App\Routing\ApiRouter;
use App\Repositories\CostumeRepository;
use App\Repositories\CostumeTypeRepository;
use App\Repositories\CostumeGroupRepository;
use App\Repositories\CostumePackageMasterRepository;
use App\Repositories\PackageCostumeRepository;
use App\Repositories\RentalValidationRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\DeviceRepository;
use App\Services\CostumeService;
use App\Services\CostumePackageService;
use App\Services\RentalService;
use App\Services\ServiceService;
use App\Services\DeviceService;
use App\Controllers\CostumeController;
use App\Controllers\PackageController;
use App\Controllers\RentalController;
use App\Controllers\ServiceController;
use App\Controllers\AssignmentController;
use App\Controllers\DeviceController;

try {
    // Initialize repositories
    $costumeRepo = new CostumeRepository($conn);
    $typeRepo = new CostumeTypeRepository($conn);
    $groupRepo = new CostumeGroupRepository($conn);
    $packageRepo = new CostumePackageMasterRepository($conn);
    $pivotRepo = new PackageCostumeRepository($conn);
    $validationRepo = new RentalValidationRepository($conn);
    $serviceRepo = new ServiceRepository($conn);
    $deviceRepo = new DeviceRepository($conn);

    // Initialize services
    $costumeService = new CostumeService($conn, $costumeRepo, $typeRepo, $groupRepo, $validationRepo);
    $packageService = new CostumePackageService($conn, $packageRepo, $pivotRepo, $costumeRepo, $validationRepo);
    $rentalService = new RentalService($conn, $costumeRepo, $validationRepo, $packageRepo, $pivotRepo);
    $serviceService = new ServiceService($conn, $serviceRepo);
    $deviceService = new DeviceService($conn, $deviceRepo);

    // Initialize controllers
    $costumeCtrl = new CostumeController($costumeService);
    $packageCtrl = new PackageController($packageService);
    $rentalCtrl = new RentalController($rentalService);
    $serviceCtrl = new ServiceController($serviceService);
    $deviceCtrl = new DeviceController($deviceService);
    
    // Get branch ID from session (for assignment controller)
    $branchId = null;
    if (isset($_SESSION['ID_TK'])) {
        $branchStmt = $conn->prepare('SELECT nv.ID_CN FROM nhan_vien nv WHERE nv.ID_TK = ? LIMIT 1');
        $branchStmt->bind_param('s', $_SESSION['ID_TK']);
        $branchStmt->execute();
        $branchStmt->bind_result($branchId);
        $branchStmt->fetch();
        $branchStmt->close();
    }
    $assignmentCtrl = new AssignmentController($conn, $branchId);

    // Setup router with dynamic base (supports subfolder deployments like /StygianBlue)
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $looksLikeApiDir = (substr($scriptDir, -4) === '/api') || (substr($scriptDir, -4) === '\\api');
    $apiBasePath = $looksLikeApiDir
        ? $scriptDir // Already includes /api
        : (($scriptDir === '' || $scriptDir === '/') ? '/api' : $scriptDir . '/api');

    $router = new ApiRouter($apiBasePath);

    // ========== ASSIGNMENT ROUTES ==========
    
    // List assignments with filters
    $router->get('/assignments', fn() => $assignmentCtrl->index());
    
    // Get single assignment
    $router->get('/assignments/{scheduleId}/{employeeId}', fn($scheduleId, $employeeId) => $assignmentCtrl->show($scheduleId, $employeeId));
    
    // Create assignment
    $router->post('/assignments', fn() => $assignmentCtrl->store());
    
    // Update assignment
    $router->put('/assignments/{scheduleId}/{employeeId}', fn($scheduleId, $employeeId) => $assignmentCtrl->update($scheduleId, $employeeId));
    
    // Delete assignment
    $router->delete('/assignments/{scheduleId}/{employeeId}', fn($scheduleId, $employeeId) => $assignmentCtrl->destroy($scheduleId, $employeeId));
    
    // Get unassigned schedules
    $router->get('/assignments/unassigned/list', fn() => $assignmentCtrl->getUnassigned());
    
    // Get employees for assignment
    $router->get('/assignments/employees/list', fn() => $assignmentCtrl->getEmployees());
    
    // Get assignment statistics
    $router->get('/assignments/stats', fn() => $assignmentCtrl->getStats());
    
    // Get schedule change requests
    $router->get('/assignments/requests', fn() => $assignmentCtrl->getRequests());
    
    // Handle request decision
    $router->post('/assignments/requests/decision', fn() => $assignmentCtrl->handleRequest());
    
    // Get branch info
    $router->get('/assignments/branch', fn() => $assignmentCtrl->getBranchInfo());

    // ========== SERVICE ROUTES ==========
    
    // List services with filters
    $router->get('/services', fn() => $serviceCtrl->index());
    
    // Get service details
    $router->get('/services/{id}', fn($id) => $serviceCtrl->show($id));
    
    // Create service
    $router->post('/services', fn() => $serviceCtrl->store());
    
    // Update service
    $router->put('/services/{id}', fn($id) => $serviceCtrl->update($id));
    
    // Delete service
    $router->delete('/services/{id}', fn($id) => $serviceCtrl->destroy($id));
    
    // Update service status
    $router->put('/services/{id}/status', fn($id) => $serviceCtrl->updateStatus($id));
    
    // Get services by branch
    $router->get('/services/by-branch/{branchId}', fn($branchId) => $serviceCtrl->getByBranch($branchId));
    
    // Get services by category
    $router->get('/services/by-category/{categoryId}', fn($categoryId) => $serviceCtrl->getByCategory($categoryId));
    
    // Search services
    $router->post('/services/search', fn() => $serviceCtrl->search());
    
    // Count services by status
    $router->get('/services/count-by-status/{status}', fn($status) => $serviceCtrl->countByStatus($status));
    
    // Get services by price range
    $router->get('/services/by-price-range', fn() => $serviceCtrl->getByPriceRange());
    
    // Check if service can be deleted
    $router->get('/services/{id}/can-delete', fn($id) => $serviceCtrl->canDelete($id));

    // ========== COSTUME ROUTES ==========
    
    // List costumes with filters
    $router->get('/costumes', fn() => $costumeCtrl->index());
    
    // Get costume details
    $router->get('/costumes/{id}', fn($id) => $costumeCtrl->show($id));
    
    // Create costume
    $router->post('/costumes', fn() => $costumeCtrl->store());
    
    // Update costume
    $router->put('/costumes/{id}', fn($id) => $costumeCtrl->update($id));
    
    // Delete costume (soft delete)
    $router->delete('/costumes/{id}', fn($id) => $costumeCtrl->destroy($id));
    
    // Mark costume as available
    $router->post('/costumes/{id}/available', fn($id) => $costumeCtrl->markAvailable($id));
    
    // Mark costume for maintenance
    $router->post('/costumes/{id}/maintenance', fn($id) => $costumeCtrl->markMaintenance($id));
    
    // Check costume availability
    $router->get('/costumes/check-availability', fn() => $costumeCtrl->checkAvailability());
    
    // Find available costumes by type
    $router->get('/costumes/available-by-type', fn() => $costumeCtrl->findAvailableByType());
    
    // Search costumes
    $router->post('/costumes/search', fn() => $costumeCtrl->search());

    // ========== PACKAGE ROUTES ==========
    
    // List packages
    $router->get('/packages', fn() => $packageCtrl->index());
    
    // Get package details
    $router->get('/packages/{id}', fn($id) => $packageCtrl->show($id));
    
    // Create package
    $router->post('/packages', fn() => $packageCtrl->store());
    
    // Update package
    $router->put('/packages/{id}', fn($id) => $packageCtrl->update($id));
    
    // Delete package
    $router->delete('/packages/{id}', fn($id) => $packageCtrl->destroy($id));
    
    // Add item to package
    $router->post('/packages/{id}/items', fn($id) => $packageCtrl->addItem($id));
    
    // Remove item from package
    $router->delete('/packages/{id}/items/{costumeId}', fn($id, $costumeId) => $packageCtrl->removeItem($id, $costumeId));
    
    // Update item in package
    $router->put('/packages/{id}/items/{costumeId}', fn($id, $costumeId) => $packageCtrl->updateItem($id, $costumeId));
    
    // Reorder items in package
    $router->post('/packages/{id}/reorder', fn($id) => $packageCtrl->reorderItems($id));
    
    // Get package items
    $router->get('/packages/{id}/items', fn($id) => $packageCtrl->getItems($id));
    
    // Check package availability
    $router->get('/packages/{id}/check-availability', fn($id) => $packageCtrl->checkAvailability($id));
    
    // Get package price
    $router->get('/packages/{id}/price', fn($id) => $packageCtrl->getPrice($id));
    
    // Search packages
    $router->post('/packages/search', fn() => $packageCtrl->search());

    // ========== SERVICE-PACKAGES ALIAS ROUTES ==========
    // Frontend may call /api/service-packages/*; map to PackageController
    $router->get('/service-packages', fn() => $packageCtrl->index());
    $router->get('/service-packages/{id}', fn($id) => $packageCtrl->show($id));
    $router->post('/service-packages', fn() => $packageCtrl->store());
    $router->put('/service-packages/{id}', fn($id) => $packageCtrl->update($id));
    $router->delete('/service-packages/{id}', fn($id) => $packageCtrl->destroy($id));
    $router->post('/service-packages/search', fn() => $packageCtrl->search());

    // ========== RENTAL ROUTES ==========
    
    // Get rental details
    $router->get('/rentals/{id}', fn($id) => $rentalCtrl->show($id));
    
    // Create costume-only rental
    $router->post('/rentals/costume', fn() => $rentalCtrl->createCostumeRental());
    
    // Create package rental
    $router->post('/rentals/package', fn() => $rentalCtrl->createPackageRental());
    
    // Confirm rental
    $router->post('/rentals/{id}/confirm', fn($id) => $rentalCtrl->confirmRental($id));
    
    // Process return
    $router->post('/rentals/{id}/return', fn($id) => $rentalCtrl->processReturn($id));
    
    // Cancel rental
    $router->post('/rentals/{id}/cancel', fn($id) => $rentalCtrl->cancelRental($id));
    
    // Get customer rental history
    $router->get('/rentals/customer/{customerId}', fn($customerId) => $rentalCtrl->getCustomerRentals($customerId));
    
    // Get overdue rentals
    $router->get('/rentals/overdue', fn() => $rentalCtrl->getOverdueRentals());
    
    // Find available costumes for rental
    $router->get('/rentals/available', fn() => $rentalCtrl->findAvailableCostumes());
    
    // Detect rental conflicts
    $router->get('/rentals/detect-conflicts', fn() => $rentalCtrl->detectConflicts());
    
    // Calculate rental cost
    $router->post('/rentals/calculate-cost', fn() => $rentalCtrl->calculateCost());
    
    // Calculate late fee
    $router->post('/rentals/calculate-late-fee', fn() => $rentalCtrl->calculateLateFee());
    
    // Count rentals by status
    $router->get('/rentals/count-by-status', fn() => $rentalCtrl->countByStatus());

    // ========== DEVICE ROUTES ==========

    $router->get('/devices', fn() => $deviceCtrl->index());
    $router->get('/devices/{id}', fn($id) => $deviceCtrl->show($id));
    $router->post('/devices', fn() => $deviceCtrl->store());
    $router->put('/devices/{id}', fn($id) => $deviceCtrl->update($id));
    $router->delete('/devices/{id}', fn($id) => $deviceCtrl->destroy($id));
    $router->put('/devices/{id}/status', fn($id) => $deviceCtrl->updateStatus($id));
    $router->post('/devices/{id}/transfer', fn($id) => $deviceCtrl->transfer($id));
    $router->get('/devices/{id}/history', fn($id) => $deviceCtrl->history($id));

    // Dispatch request
    $router->dispatch();

} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
}
