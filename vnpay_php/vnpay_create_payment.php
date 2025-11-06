<?php

use App\Payments\VNPayService;

/** @var VNPayService $service */
$service = require './config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['code' => '99', 'message' => 'Method not allowed']);
    exit;
}

$orderId = trim($_POST['order_id'] ?? '');
$amount = (float) ($_POST['amount'] ?? 0);

if ($orderId === '' || $amount <= 0) {
    http_response_code(422);
    echo json_encode(['code' => '01', 'message' => 'Thiếu thông tin bắt buộc']);
    exit;
}

$payload = [
    'orderId' => $orderId,
    'amount' => $amount,
    'orderDescription' => trim($_POST['order_desc'] ?? 'Thanh toan don hang'),
    'orderType' => trim($_POST['order_type'] ?? 'other'),
    'bankCode' => trim($_POST['bank_code'] ?? ''),
    'language' => trim($_POST['language'] ?? $service->config()->defaultLocale),
    'expireAt' => $_POST['txtexpire'] ?? null,
    'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
    'billing' => [
        'fullName' => $_POST['txt_billing_fullname'] ?? null,
        'email' => $_POST['txt_billing_email'] ?? null,
        'mobile' => $_POST['txt_billing_mobile'] ?? null,
        'address' => $_POST['txt_billing_addr1'] ?? null,
        'city' => $_POST['txt_bill_city'] ?? null,
        'country' => $_POST['txt_bill_country'] ?? null,
        'state' => $_POST['txt_bill_state'] ?? null,
    ],
    'invoice' => [
        'phone' => $_POST['txt_inv_mobile'] ?? null,
        'email' => $_POST['txt_inv_email'] ?? null,
        'customer' => $_POST['txt_inv_customer'] ?? null,
        'address' => $_POST['txt_inv_addr1'] ?? null,
        'company' => $_POST['txt_inv_company'] ?? null,
        'taxCode' => $_POST['txt_inv_taxcode'] ?? null,
        'type' => $_POST['cbo_inv_type'] ?? null,
    ],
];

$paymentUrl = $service->buildPaymentUrl($payload);
$response = ['code' => '00', 'message' => 'success', 'data' => $paymentUrl];

if (isset($_POST['redirect'])) {
    header('Location: ' . $paymentUrl);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($response);
