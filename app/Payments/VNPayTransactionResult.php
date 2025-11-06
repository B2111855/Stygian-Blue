<?php

namespace App\Payments;

class VNPayTransactionResult
{
    public function __construct(
        public array $payload,
        public bool $isValidSignature,
        public ?string $responseCode,
        public ?string $transactionStatus
    ) {
    }

    public function isSuccessful(): bool
    {
        if (!$this->isValidSignature) {
            return false;
        }

        if ($this->responseCode !== '00') {
            return false;
        }

        if ($this->transactionStatus === null) {
            return true;
        }

        return $this->transactionStatus === '00';
    }

    public function amount(): ?float
    {
        if (!isset($this->payload['vnp_Amount'])) {
            return null;
        }

        return ((float) $this->payload['vnp_Amount']) / 100;
    }

    public function orderId(): ?string
    {
        return $this->payload['vnp_TxnRef'] ?? null;
    }

    public function message(): string
    {
        if (!$this->isValidSignature) {
            return 'Chữ ký không hợp lệ';
        }

        if ($this->isSuccessful()) {
            return 'Giao dịch thành công';
        }

        $code = $this->responseCode ?? 'Không xác định';
        return 'Giao dịch không thành công (mã ' . $code . ')';
    }
}