<?php

namespace App\Payments;

use DateTime;
use DateTimeZone;

class VNPayConfig
{
    public string $tmnCode;
    public string $hashSecret;
    public string $paymentUrl;
    public string $returnUrl;
    public ?string $ipnUrl;
    public string $apiUrl;
    public string $defaultLocale;
    public string $timeZone;

    public static function fromEnvironment(array $env): self
    {
        $config = new self();
        $config->tmnCode = trim($env['VNPAY_TMN_CODE'] ?? 'VXTH6A54');
        $config->hashSecret = trim($env['VNPAY_HASH_SECRET'] ?? 'DR3KYIIVG625CTBL0MDMJVEM5CWETY2B');
        $config->paymentUrl = trim($env['VNPAY_PAYMENT_URL'] ?? 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');
        $config->returnUrl = trim($env['VNPAY_RETURN_URL'] ?? 'http://localhost:8080/StygianBlue/app/Pages/Views/vnpay_return.php');
        $config->ipnUrl = isset($env['VNPAY_IPN_URL'])
            ? trim($env['VNPAY_IPN_URL'])
            : 'http://localhost:8080/StygianBlue/app/Pages/Controller/vnpay_ipn.php';
        $config->apiUrl = trim($env['VNPAY_API_URL'] ?? 'https://sandbox.vnpayment.vn/merchant_webapi/merchant.html');
        $config->defaultLocale = trim($env['VNPAY_DEFAULT_LOCALE'] ?? 'vn');
        $config->timeZone = trim($env['APP_TIMEZONE'] ?? 'Asia/Ho_Chi_Minh');
        return $config;
    }

    public function now(): DateTime
    {
        return new DateTime('now', new DateTimeZone($this->timeZone));
    }

    public function expireAt(int $minutes): DateTime
    {
        $current = $this->now();
        $current->modify('+' . $minutes . ' minutes');
        return $current;
    }
}