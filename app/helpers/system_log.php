<?php

declare(strict_types=1);

if (!function_exists('record_system_log')) {
    /**
     * Ghi lại hoạt động vào bảng nhat_ky_he_thong.
     */
    function record_system_log(
        mysqli $conn,
        string $action,
        ?string $subject = null,
        mixed $before = null,
        mixed $after = null,
        ?string $actorId = null,
        ?string $role = null
    ): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $actorParam = $actorId;
        if ($actorParam === null || $actorParam === '') {
            $actorParam = $_SESSION['ID_TK'] ?? null;
        }

        $roleParam = $role;
        if ($roleParam === null || $roleParam === '') {
            $roleParam = $_SESSION['role'] ?? (isset($_SESSION['ID_QUYEN']) ? (string) $_SESSION['ID_QUYEN'] : null);
        }

        $beforeJson = normalize_system_log_payload($before);
        $afterJson  = normalize_system_log_payload($after);
        $ip         = get_system_log_client_ip();
        $userAgent  = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $userAgent  = substr($userAgent, 0, 255);

        try {
            $stmt = $conn->prepare(
                'INSERT INTO nhat_ky_he_thong '
                . '(ACTOR_ID, VAI_TRO, HANH_DONG, DOI_TUONG, TRUOC_JSON, SAU_JSON, IP, USER_AGENT) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );

            if (!$stmt) {
                throw new RuntimeException('[SystemLog] Prepare failed: ' . $conn->error);
            }

            $stmt->bind_param(
                'ssssssss',
                $actorParam,
                $roleParam,
                $action,
                $subject,
                $beforeJson,
                $afterJson,
                $ip,
                $userAgent
            );

            $stmt->execute();
            $stmt->close();
            return true;
        } catch (Throwable $th) {
            error_log('[SystemLog] ' . $th->getMessage());
            return false;
        }
    }
}

if (!function_exists('normalize_system_log_payload')) {
    function normalize_system_log_payload(mixed $payload): ?string
    {
        if ($payload === null) {
            return null;
        }

        if (is_string($payload)) {
            $trimmed = trim($payload);
            return $trimmed === '' ? null : $payload;
        }

        if (is_scalar($payload)) {
            return json_encode(['value' => $payload], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if ($payload instanceof JsonSerializable) {
            return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (is_array($payload) || is_object($payload)) {
            return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return null;
    }
}

if (!function_exists('get_system_log_client_ip')) {
    function get_system_log_client_ip(): ?string
    {
        $ipHeaders = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'HTTP_CF_CONNECTING_IP',
            'REMOTE_ADDR',
        ];

        foreach ($ipHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ipList = explode(',', (string) $_SERVER[$header]);
                return trim($ipList[0]);
            }
        }

        return null;
    }
}
