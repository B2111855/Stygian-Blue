<?php

declare(strict_types=1);

/**
 * Trả về thuộc tính class/style cho thẻ <body> của các trang xác thực.
 * Có thể truyền vào đường dẫn ảnh nền, overlay gradient, và độ mờ.
 *
 * @param array{
 *     image?: string,
 *     overlay?: string,
 *     blur?: string
 * } $options
 */
function buildAuthBodyAttributes(array $options = []): string
{
    $defaults = [
        'image'   => '',
        'overlay' => '',
        'blur'    => '',
    ];

    $options = array_merge($defaults, $options);

    $styles = [];

    if ($options['image'] !== '') {
        $styles[] = sprintf(
            "--auth-background-image: url('%s');",
            htmlspecialchars($options['image'], ENT_QUOTES, 'UTF-8')
        );
    }

    if ($options['overlay'] !== '') {
        $styles[] = sprintf(
            '--auth-background-overlay: %s;',
            htmlspecialchars($options['overlay'], ENT_QUOTES, 'UTF-8')
        );
    }

    if ($options['blur'] !== '') {
        $styles[] = sprintf(
            '--auth-background-blur: %s;',
            htmlspecialchars($options['blur'], ENT_QUOTES, 'UTF-8')
        );
    }

    $attributes = 'class="auth-body"';

    if (!empty($styles)) {
        $attributes .= ' style="' . implode(' ', $styles) . '"';
    }

    return $attributes;
}