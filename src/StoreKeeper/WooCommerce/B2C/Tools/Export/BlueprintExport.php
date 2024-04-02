<?php

namespace StoreKeeper\WooCommerce\B2C\Tools\Export;

class BlueprintExport
{
    /**
     * @var \WC_Product_Variable
     */
    private $product;

    /**
     * BlueprintExport constructor.
     */
    public function __construct(\WC_Product_Variable $product)
    {
        $this->product = $product;
    }

    public function getName(): string
    {
        $names = [];

        foreach ($this->getAttributes() as $attribute) {
            $names[] = $attribute['label'];
        }

        return implode(' ', $names);
    }

    public function getAlias(): string
    {
        $aliases = [];

        foreach ($this->getAttributes() as $attribute) {
            $aliases[] = $attribute['name'];
        }

        return 'wc-'.implode('-', $aliases);
    }

    public function getSkuPattern(): string
    {
        $values = array_map(
            function ($attribute) {
                $alias = $attribute['name'];

                return "{{content_vars[\"$alias\"][\"value\"]}}";
            },
            $this->getAttributes()
        );

        return '{{sku}}_'.implode('-', $values);
    }

    public function getTitlePattern(): string
    {
        $values = array_map(
            function ($attribute) {
                $alias = $attribute['name'];

                return "{{content_vars[\"$alias\"][\"value_label\"]}}";
            },
            $this->getAttributes()
        );

        return '{{title}} - '.implode(' ', $values);
    }

    /**
     * @var array
     */
    private $attributes;

    private function getAttributes(): array
    {
        if (!isset($this->attributes)) {
            $this->attributes = [];
            foreach ($this->product->get_attributes() as $attributeName => $attribute) {
                if ($attribute->get_variation()) {
                    $name = AttributeExport::getProductAttributeKey($attribute);
                    $sortableName = substr($name, 3);
                    $this->attributes[$sortableName] = [
                        'name' => $name,
                        'label' => wc_attribute_label($attributeName),
                    ];
                }
            }
            // Sort on the sortableName
            ksort($this->attributes);
        }

        return array_values($this->attributes);
    }
}
