<?php

namespace StoreKeeper\WooCommerce\B2C\Helpers;

class HtmlEscape
{
    public const ALLOWED_COMMON = [
        'div' => [
            'class' => [],
            'style' => [],
        ],
        'label' => [
            'class' => [],
            'style' => [],
        ],
        'br' => [],
        'small' => [
            'class' => [],
            'style' => [],
        ],
        'code' => [],
        'h2' => [
            'class' => [],
            'style' => [],
        ],
        'h3' => [
            'class' => [],
            'style' => [],
        ],
        'h4' => [
            'class' => [],
            'style' => [],
        ],
        'p' => [
            'class' => [],
            'style' => [],
        ],
        'i' => [
            'class' => [],
            'style' => [],
        ],
        'strong' => [
            'class' => [],
            'style' => [],
        ],
        'b' => [
            'class' => [],
            'style' => [],
        ],
        'span' => [
            'class' => [],
            'style' => [],
        ],
    ];

    public const ALLOWED_FORM = [
        'form' => [
            'method' => [],
            'class' => [],
            'action' => [],
        ],
    ] + self::ALLOWED_COMMON;

    public const ALLOWED_SELECT = [
        'select' => [
            'class' => [],
            'name' => [],
        ],
        'option' => [
            'value' => [],
            'selected' => [],
        ],
    ] + self::ALLOWED_COMMON;

    public const ALLOWED_INPUT = [
        'input' => [
            'type' => [],
            'class' => [],
            'name' => [],
            'value' => [],
            'placeholder' => [],
            'checked' => [],
            'disabled' => [],
        ],
    ] + self::ALLOWED_COMMON;

    public const ALLOWED_BUTTON = [
        'button' => [
            'type' => [],
            'class' => [],
            'name' => [],
            'value' => [],
        ],
    ] + self::ALLOWED_COMMON;

    public const ALLOWED_ANCHOR = [
        'a' => [
            'href' => [],
            'class' => [],
            'target' => [],
        ],
    ] + self::ALLOWED_COMMON;

    public const ALLOWED_ALL_KNOWN_INPUT =
        self::ALLOWED_INPUT +
        self::ALLOWED_SELECT +
        self::ALLOWED_BUTTON +
        self::ALLOWED_ANCHOR;

    public const ALLOWED_ALL_SAFE = self::ALLOWED_ALL_KNOWN_INPUT;

    /**
     * Transforms the components of a URL into an array.
     */
    public static function parseUrl(string $url)
    {
        return parse_url($url);
    }

    /**
     * Build URL from url components.
     * Array is compatible from the value returned from parseUrl.
     *
     * @see parseUrl
     */
    public static function buildUrl(array $urlComponents): string
    {
        $scheme = isset($urlComponents['scheme']) ? $urlComponents['scheme'].'://' : '';
        $host = $urlComponents['host'] ?? '';
        $port = isset($urlComponents['port']) ? ':'.$urlComponents['port'] : '';
        $user = $urlComponents['user'] ?? '';
        $pass = isset($urlComponents['pass']) ? ':'.$urlComponents['pass'] : '';
        $pass = ($user || $pass) ? "$pass@" : '';
        $path = $urlComponents['path'] ?? '';
        $query = isset($urlComponents['query']) ? '?'.$urlComponents['query'] : '';
        $fragment = isset($urlComponents['fragment']) ? '#'.$urlComponents['fragment'] : '';

        return "$scheme$user$pass$host$port$path$query$fragment";
    }
}
