<?php

namespace StoreKeeper\WooCommerce\B2C\Blocks;

use StoreKeeper\WooCommerce\B2C\I18N;
use Psr\Log\LoggerAwareTrait;
use StoreKeeper\WooCommerce\B2C\Factories\LoggerFactory;

abstract class AbstractBlockTypeRegistrar implements BlockTypeRegistrarInterface
{

    use LoggerAwareTrait;

    /**
     * Initialize
     *
     * @return void
     */
    public function __construct()
    {
        $this->setLogger(LoggerFactory::create('block-type'));
    }

    /**
     * Retrieve block type name
     *
     * @return null|string
     */
    protected function getName()
    {
        $blockType = $this->getBlockType();

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
     * @inheritDoc
     */
    final public function register()
    {
        if ($this->isEnabled() && null !== ($name = $this->getName()) && ($namespace = strtok($name, '/')) &&
            $namespace === BlockTypesController::NAMESPACE) {
            $blockType = register_block_type($this->getBlockType(), $this->getArgs());

            if ($blockType instanceof \WP_Block_Type) {
                return $blockType;
            }

            $this->logger->error(sprintf('Block type %s couldn\'t be registered.', $name));
        }

        return false;
    }

    /**
     * Retrieve StoreKeeper block type arguments
     *
     * @return array
     */
    protected function getArgs()
    {
        return [
            'name' => $this->getName(),
            'category' => BlockTypesController::BLOCK_CATEGORY,
            'textdomain' => I18N::DOMAIN
        ];
    }

    /**
     * Retrieve StoreKeeper block type
     *
     * @return string|\WP_Block_Type
     */
    protected function getBlockType()
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
    protected function isEnabled()
    {
        return true;
    }
}
