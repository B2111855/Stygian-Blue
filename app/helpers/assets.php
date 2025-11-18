<?php

if (!function_exists('sb_project_root')) {
    function sb_project_root(): string
    {
        static $root = null;
        if ($root === null) {
            $root = dirname(__DIR__, 2);
        }
        return $root;
    }
}

if (!function_exists('sb_asset_href')) {
    function sb_asset_href(string $path, string $fallback = ''): string
    {
        $candidate = trim($path !== '' ? $path : $fallback);
        if ($candidate === '') {
            return '';
        }

        if (preg_match('#^(?:[a-z]+:)?//#i', $candidate) || strpos($candidate, 'data:') === 0) {
            return $candidate;
        }

        $normalized = ltrim(str_replace('\\', '/', $candidate), '/');
        $docRoot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\'));
        $projectRoot = str_replace('\\', '/', rtrim(sb_project_root(), '/\\'));

        $base = '';
        if ($docRoot !== '' && strpos($projectRoot, $docRoot) === 0) {
            $base = substr($projectRoot, strlen($docRoot));
        }

        $base = trim($base, '/');
        $prefix = $base === '' ? '' : '/' . $base;

        return $prefix . '/' . $normalized;
    }
}

if (!function_exists('sb_versioned_asset')) {
    function sb_versioned_asset(string $path): string
    {
        $href = sb_asset_href($path);
        if ($href === '') {
            return '';
        }

        $fullPath = rtrim(sb_project_root(), '/\\') . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($path, '/\\'));
        if (is_file($fullPath)) {
            $separator = strpos($href, '?') === false ? '?' : '&';
            $href .= $separator . 'v=' . filemtime($fullPath);
        }

        return $href;
    }
}

if (!function_exists('sb_tailwind_href')) {
    function sb_tailwind_href(): string
    {
        static $href = null;
        if ($href === null) {
            $href = sb_versioned_asset('public/css/tailwind.css');
        }
        return $href;
    }
}

if (!function_exists('sb_tailwind_link_tag')) {
    function sb_tailwind_link_tag(array $attributes = []): string
    {
        $attributes = array_merge([
            'rel' => 'stylesheet',
            'href' => sb_tailwind_href(),
        ], $attributes);

        $pairs = [];
        foreach ($attributes as $key => $value) {
            if ($value === null) {
                continue;
            }
            $pairs[] = sprintf('%s="%s"', htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8'), htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'));
        }

        return '<link ' . implode(' ', $pairs) . ' />';
    }
}
