<?php
if (!function_exists('report_json')) {
    function report_json(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }
}

if (!function_exists('report_parse_branch')) {
    function report_parse_branch(?string $branchParam): ?int
    {
        if (!$branchParam) {
            return null;
        }
        if (preg_match('/^cn(\d+)$/i', $branchParam, $matches)) {
            return (int)$matches[1];
        }
        return null;
    }
}

if (!function_exists('report_resolve_range')) {
    function report_resolve_range(string $filter, ?string $monthParam = null): array
    {
        $filter = strtolower($filter);
        $now = new DateTime('now');

        if ($monthParam && preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
            $start = DateTime::createFromFormat('Y-m', $monthParam) ?: new DateTime('first day of this month');
            $start->setTime(0, 0, 0);
        } else {
            $start = new DateTime('first day of this month');
        }

        $end = clone $start;
        switch ($filter) {
            case 'week':
                $end->modify('+7 days');
                break;
            case 'quarter':
                $end->modify('+3 months');
                break;
            case 'month':
            default:
                $filter = 'month';
                $end->modify('+1 month');
                break;
        }

        return [$filter, $start, $end];
    }
}

if (!function_exists('report_resolve_relative_range')) {
    function report_resolve_relative_range(string $filter): array
    {
        $filter = strtolower($filter);
        $end = new DateTime('now');
        $start = clone $end;

        switch ($filter) {
            case 'week':
                $start->modify('-7 days');
                break;
            case 'quarter':
                $filter = 'quarter';
                $currentMonth = (int)$end->format('n');
                $quarterStartMonth = (int)(floor(($currentMonth - 1) / 3) * 3 + 1);
                $start = new DateTime($end->format('Y') . '-' . str_pad($quarterStartMonth, 2, '0', STR_PAD_LEFT) . '-01 00:00:00');
                $end = clone $start;
                $end->modify('+3 months -1 second');
                break;
            case 'year':
                $filter = 'year';
                $start = new DateTime('first day of january this year');
                $start->setTime(0, 0, 0);
                $end = new DateTime('last day of december this year');
                $end->setTime(23, 59, 59);
                break;
            case 'month':
            default:
                $filter = 'month';
                $start = new DateTime('first day of this month');
                $start->setTime(0, 0, 0);
                $end = new DateTime('last day of this month');
                $end->setTime(23, 59, 59);
                break;
        }

        return [$filter, $start, $end];
    }
}

if (!function_exists('report_format_currency')) {
    function report_format_currency(float $value): string
    {
        return number_format($value, 0, ',', '.') . ' VND';
    }
}

if (!function_exists('report_format_duration')) {
    function report_format_duration(?float $minutes): string
    {
        if ($minutes === null || $minutes <= 0) {
            return 'Chưa có dữ liệu';
        }
        $hours = floor($minutes / 60);
        $mins = (int)round($minutes % 60);
        if ($hours <= 0) {
            return $mins . ' phút/buổi';
        }
        return sprintf('%dh %02d/phút', $hours, $mins);
    }
}

if (!function_exists('report_stmt_bind_params')) {
    function report_stmt_bind_params(mysqli_stmt $stmt, string $types, array $params): void
    {
        if ($types === '' || empty($params)) {
            return;
        }
        $bindArgs = [];
        $bindArgs[] = &$types;
        foreach ($params as $key => $value) {
            $bindArgs[] = &$params[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $bindArgs);
    }
}
