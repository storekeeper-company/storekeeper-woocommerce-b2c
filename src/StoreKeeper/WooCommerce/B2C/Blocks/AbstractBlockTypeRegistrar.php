<?php

namespace StoreKeeper\WooCommerce\B2C\Blocks;

use StoreKeeper\WooCommerce\B2C\I18N;
use Psr\Log\NullLogger;

abstract class AbstractBlockTypeRegistrar
{

    /**
     * Logger instance
     *
     * @var null|NullLogger
     */
    protected static $logger;

    /**
     * Retrieve block type name
     *
     * @return null|string
     */
    public static function getName()
    {
        $blockType = self::getBlockType();

        if ($blockType instanceof \WP_Block_Type && $blockType->name) {
            return $blockType->name;
        }

        if (is_string($blockType)) {
            if (file_exists($blockType)) {
                $metadataFile = (!\str_ends_with($blockType, 'block.json'))
                    ? \trailingslashit($blockType) . 'block.json'
                    : $blockType;

                $metadata = wp_json_file_decode($metadataFile, ['associative' => true]);
                if ($metadata && array_key_exists('name', $metadata)) {
                    return $metadata['name'];
                }
            } elseif (preg_match('/^[a-z0-9-]+\/[a-z0-9-]+$/', $blockType)) {
                return $blockType;
            }
        }

        return null;
    }

    /**
     * Register StoreKeeper block type
     *
     * @return void
     */
    final public static function register()
    {
        if (self::isEnabled() && null !== ($name = static::getName()) && ($namespace = strtok($name, '/')) &&
            $namespace === BlockTypesController::NAMESPACE) {
            if (false === register_block_type(static::getBlockType(), static::getArgs())) {
                static::getLogger()->debug(sprintf('Block type %s couldn\'t be registered.', $name));
            }
        }
    }

    /**
     * Retrieve StoreKeeper block type arguments
     *
     * @return array
     */
    public static function getArgs()
    {
        return [
            'name' => static::getName(),
            'category' => BlockTypesController::BLOCK_CATEGORY,
            'textdomain' => I18N::DOMAIN
        ];
    }

    /**
     * Retrieve StoreKeeper block type
     *
     * @return string|\WP_Block_Type
     */
    public static function getBlockType()
    {
        $blockType = substr(get_called_class(), strrpos(get_called_class(), '\\') + 1);
        return __DIR__ . DIRECTORY_SEPARATOR . 'BlockTypes' . DIRECTORY_SEPARATOR . $blockType
                    . DIRECTORY_SEPARATOR . 'block.json';
    }

    /**
     * Check whether block type is enabled
     *
     * @return bool
     */
    public static function isEnabled()
    {
        return true;
    }

    /**
     * Get logger
     *
     * @return NullLogger
     */
    protected function getLogger(): NullLogger
    {
        if (null === self::$logger) {
            self::$logger = new NullLogger;
        }

        return self::$logger;
    }
}
