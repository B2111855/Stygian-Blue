<?php

use App\Payments\VNPayConfig;
use App\Payments\VNPayService;
use Dotenv\Dotenv;

require_once '../vendor/autoload.php';

if (!isset($_ENV['VNPAY_TMN_CODE']) && file_exists('../.env')) {
    Dotenv::createImmutable('..')->safeLoad();
}

$config = VNPayConfig::fromEnvironment($_ENV);
$service = new VNPayService($config);

return $service;