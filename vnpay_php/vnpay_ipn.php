<?php

use App\Payments\VNPayService;

/** @var VNPayService $service */
$service = require './config.php';

try {
    $response = $service->validateIpn($_GET);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['RspCode' => '99', 'Message' => 'Internal Error']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($response);
