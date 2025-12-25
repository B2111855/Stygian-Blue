<?php

if (!function_exists('sb_equipment_image_url')) {
    /**
     * Normalize stored equipment image paths so legacy entries still load correctly.
     */
    function sb_equipment_image_url(?string $rawPath): string
    {
        $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
        $projectRoot = rtrim(dirname(__DIR__, 2), '/\\');
        $basePaths = [];
        if ($docRoot !== '') {
            $basePaths[] = $docRoot;
        }
        $basePaths[] = $projectRoot;
        $candidates = [];

        if ($rawPath !== null && $rawPath !== '') {
            $clean = str_replace('\\', '/', trim($rawPath));
            $clean = preg_replace('#^\./+#', '', $clean);
            while (strpos($clean, '../') === 0) {
                $clean = substr($clean, 3);
            }

            if (strpos($clean, 'trang_thietbi') !== false) {
                $clean = str_replace('trang_thietbi', 'thietbi', $clean);
            }

            if (stripos($clean, 'public/') !== 0) {
                $clean = ltrim($clean, '/');
                $clean = 'public/' . $clean;
            }

            $candidates[] = $clean;
        }

        $default = 'public/images/thietbi/equipment-placeholder.svg';
        $candidates[] = $default;

        foreach ($candidates as $candidate) {
            foreach ($basePaths as $base) {
                $full = $base . '/' . ltrim($candidate, '/');
                if (is_file($full)) {
                    return $candidate;
                }
            }
        }

        return $default;
    }
}
