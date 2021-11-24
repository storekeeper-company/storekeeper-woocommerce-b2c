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
}
