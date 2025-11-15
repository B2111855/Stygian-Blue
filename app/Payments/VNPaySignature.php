<?php

namespace App\Payments;

class VNPaySignature
{
    public static function generate(array $params, string $secret): string
    {
        $data = self::buildDataString($params);
        return hash_hmac('sha512', $data, $secret);
    }

    public static function verify(array $params, string $secret, string $signature): bool
    {
        if ($signature === '') {
            return false;
        }

        $expected = self::generate($params, $secret);
        return hash_equals($expected, $signature);
    }

    public static function buildDataString(array $params): string
    {
        ksort($params);

        return http_build_query($params, '', '&', PHP_QUERY_RFC1738);
    }
}