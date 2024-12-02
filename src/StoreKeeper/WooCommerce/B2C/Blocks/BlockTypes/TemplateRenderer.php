<?php

namespace StoreKeeper\WooCommerce\B2C\Blocks\BlockTypes;

class TemplateRenderer
{

    /**
     * Default render template name
     */
    public const DEFAULT_TEMPLATE_NAME = 'template.php';

    /**
     * Render dynamic block type
     *
     * Load order:
     * theme/<block vendor>/blocks/<block name>/$templateName
     * $templatePath/$template_name
     *
     * @param array $attributes
     * @param string $content
     * @param \WP_Block $block
     * @param array $args
     * @param string $templatePath
     * @param string $templateName
     * @return string
     */
    public static function render(
        $attributes,
        $content,
        $block,
        $renderer,
        $args = [],
        $templatePath = '',
        $templateName = ''
    ) {
        list($namespace, $module) = explode('/', $block->name, 2);

        if (!$templatePath) {
            $reflector = new \ReflectionClass($renderer);
            $templatePath = dirname($reflector->getFileName()) . DIRECTORY_SEPARATOR;

            unset($reflector);
        }

        if (!$templateName) {
            $templateName = self::DEFAULT_TEMPLATE_NAME;
        }

        return wc_get_template_html(
            $templateName,
            array_merge(
                $args,
                [
                    'attributes' => $attributes,
                    'content' => $content,
                    'block' => $block,
                    'renderer' => $renderer
                ]
            ),
            sprintf('%s/blocks/%s', $namespace, $module),
            $templatePath
        );
    }
}
