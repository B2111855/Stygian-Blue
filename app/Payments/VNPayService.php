<?php

namespace App\Payments;

class VNPayService
{
    public function __construct(private VNPayConfig $config)
    {
    }

    public function config(): VNPayConfig
    {
        return $this->config;
    }

    public function buildPaymentUrl(array $payload): string
    {
        $params = $this->buildPaymentParams($payload);
        $signature = VNPaySignature::generate($params, $this->config->hashSecret);
        $params['vnp_SecureHash'] = $signature;

        return $this->config->paymentUrl . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC1738);
    }

    public function buildPaymentParams(array $payload): array
    {
        $now = $this->config->now();
        $expireMinutes = isset($payload['expireMinutes']) ? (int) $payload['expireMinutes'] : 15;
        $expireDate = $payload['expireAt'] ?? $this->config->expireAt($expireMinutes)->format('YmdHis');

        $amount = isset($payload['amount']) ? (float) $payload['amount'] : 0;
        $amount = $amount > 0 ? $amount : 0;

        $orderDescription = $payload['orderDescription'] ?? 'Thanh toan don hang';
        $orderInfo = $this->sanitizeOrderInfo($orderDescription);
        $orderType = $payload['orderType'] ?? 'other';
        $orderType = $this->sanitizeOrderType($orderType);

        $params = [
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => $this->config->tmnCode,
            'vnp_TxnRef' => $payload['orderId'] ?? $now->format('YmdHis'),
            'vnp_OrderInfo' => $orderInfo,
            'vnp_OrderType' => $orderType,
            'vnp_Amount' => (int) round($amount * 100),
            'vnp_Locale' => $payload['language'] ?? $this->config->defaultLocale,
            'vnp_IpAddr' => $payload['ipAddress'] ?? '127.0.0.1',
            'vnp_CreateDate' => $now->format('YmdHis'),
            'vnp_ExpireDate' => $expireDate,
            'vnp_ReturnUrl' => $this->config->returnUrl,
            'vnp_CurrCode' => 'VND'
        ];

        if (!empty($payload['bankCode'])) {
            $params['vnp_BankCode'] = $payload['bankCode'];
        }

        $this->appendBillingData($params, $payload['billing'] ?? []);
        $this->appendInvoiceData($params, $payload['invoice'] ?? []);

        return $params;
    }

    public function interpretResponse(array $query): VNPayTransactionResult
    {
        $payload = $this->extractVNPayParams($query);
        $signature = $query['vnp_SecureHash'] ?? '';
        $isValid = VNPaySignature::verify($payload, $this->config->hashSecret, $signature);
        $responseCode = $query['vnp_ResponseCode'] ?? null;
        $transactionStatus = $query['vnp_TransactionStatus'] ?? null;

        return new VNPayTransactionResult($query, $isValid, $responseCode, $transactionStatus);
    }

    public function buildQueryUrl(array $payload): string
    {
        $now = $this->config->now();
        $params = [
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'querydr',
            'vnp_TmnCode' => $this->config->tmnCode,
            'vnp_TxnRef' => $payload['orderId'] ?? '',
            'vnp_OrderInfo' => $payload['orderInfo'] ?? 'Truy van giao dich',
            'vnp_TransDate' => $payload['paymentDate'] ?? '',
            'vnp_CreateDate' => $now->format('YmdHis'),
            'vnp_IpAddr' => $payload['ipAddress'] ?? '127.0.0.1'
        ];

        $signature = VNPaySignature::generate($params, $this->config->hashSecret);
        $params['vnp_SecureHash'] = $signature;

        return $this->config->apiUrl . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function buildRefundUrl(array $payload): string
    {
        $now = $this->config->now();
        $amount = isset($payload['amount']) ? (float) $payload['amount'] : 0;
        $amount = $amount > 0 ? $amount : 0;

        $params = [
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'refund',
            'vnp_TmnCode' => $this->config->tmnCode,
            'vnp_TxnRef' => $payload['orderId'] ?? '',
            'vnp_TransactionType' => $payload['transactionType'] ?? '02',
            'vnp_Amount' => (int) round($amount * 100),
            'vnp_OrderInfo' => $payload['orderInfo'] ?? 'Hoan tien giao dich',
            'vnp_TransDate' => $payload['paymentDate'] ?? '',
            'vnp_CreateDate' => $now->format('YmdHis'),
            'vnp_CreateBy' => $payload['createdBy'] ?? '',
            'vnp_IpAddr' => $payload['ipAddress'] ?? '127.0.0.1',
        ];

        $signature = VNPaySignature::generate($params, $this->config->hashSecret);
        $params['vnp_SecureHash'] = $signature;

        return $this->config->apiUrl . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function validateIpn(array $query): array
    {
        $payload = $this->extractVNPayParams($query);
        $signature = $query['vnp_SecureHash'] ?? '';

        if (!VNPaySignature::verify($payload, $this->config->hashSecret, $signature)) {
            return [
                'RspCode' => '97',
                'Message' => 'Invalid signature',
                'isSuccess' => false,
                'payload' => $payload,
                'responseCode' => $query['vnp_ResponseCode'] ?? null,
                'transactionStatus' => $query['vnp_TransactionStatus'] ?? null,
            ];
        }

        $responseCode = $query['vnp_ResponseCode'] ?? null;
        $transactionStatus = $query['vnp_TransactionStatus'] ?? null;
        $isSuccess = $responseCode === '00' && ($transactionStatus === null || $transactionStatus === '00');

        return [
            'RspCode' => '00',
            'Message' => 'Confirm Success',
            'isSuccess' => $isSuccess,
            'payload' => $payload,
            'responseCode' => $responseCode,
            'transactionStatus' => $transactionStatus,
        ];
    }

    private function appendBillingData(array &$params, array $billing): void
    {
        $firstName = null;
        $lastName = null;

        if (!empty($billing['fullName'])) {
            $parts = explode(' ', trim($billing['fullName']));
            $firstName = array_shift($parts);
            $lastName = count($parts) > 0 ? implode(' ', $parts) : $firstName;
        }

        $mapping = [
            'vnp_Bill_Mobile' => $billing['mobile'] ?? null,
            'vnp_Bill_Email' => $billing['email'] ?? null,
            'vnp_Bill_Address' => $billing['address'] ?? null,
            'vnp_Bill_City' => $billing['city'] ?? null,
            'vnp_Bill_Country' => $billing['country'] ?? null,
            'vnp_Bill_State' => $billing['state'] ?? null,
        ];

        if ($firstName !== null) {
            $mapping['vnp_Bill_FirstName'] = $firstName;
            $mapping['vnp_Bill_LastName'] = $lastName ?? $firstName;
        }

        foreach ($mapping as $key => $value) {
            if ($value !== null && $value !== '') {
                $params[$key] = $value;
            }
        }
    }

    private function appendInvoiceData(array &$params, array $invoice): void
    {
        $mapping = [
            'vnp_Inv_Phone' => $invoice['phone'] ?? null,
            'vnp_Inv_Email' => $invoice['email'] ?? null,
            'vnp_Inv_Customer' => $invoice['customer'] ?? null,
            'vnp_Inv_Address' => $invoice['address'] ?? null,
            'vnp_Inv_Company' => $invoice['company'] ?? null,
            'vnp_Inv_Taxcode' => $invoice['taxCode'] ?? null,
            'vnp_Inv_Type' => $invoice['type'] ?? null,
        ];

        foreach ($mapping as $key => $value) {
            if ($value !== null && $value !== '') {
                $params[$key] = $value;
            }
        }
    }

    private function extractVNPayParams(array $query): array
    {
        $result = [];

        foreach ($query as $key => $value) {
            if (!str_starts_with($key, 'vnp_')) {
                continue;
            }

            if (in_array($key, ['vnp_SecureHash', 'vnp_SecureHashType'], true)) {
                continue;
            }

            if ($value !== null && $value !== '') {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function sanitizeOrderInfo(string $description): string
    {
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $description);
        if ($normalized === false) {
            $normalized = $description;
        }

        $normalized = preg_replace('/[^A-Za-z0-9\s]/', ' ', $normalized ?? '');
        $normalized = preg_replace('/\s+/', ' ', $normalized ?? '');
        $normalized = trim($normalized ?? '');

        return $normalized !== '' ? $normalized : 'Thanh toan don hang';
    }

    private function sanitizeOrderType(string $orderType): string
    {
        $normalized = preg_replace('/[^A-Za-z0-9_]/', '', $orderType);
        $normalized = substr($normalized ?? '', 0, 100);

        return $normalized !== '' ? $normalized : 'other';
    }
}