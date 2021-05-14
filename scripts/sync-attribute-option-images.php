<?php

require_once __DIR__.'/WordPressHelpers.php';

if (!WordPressHelpers::isCli()) {
    exit();
}

use StoreKeeper\WooCommerce\B2C\Tools\Attributes;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;

WordPressHelpers::echoLogs('Starting WordPress');
WordPressHelpers::setupAndRequireWordpress();

if (!WordPressHelpers::isStoreKeeperConnected()) {
    exit('No Backoffice API connected.');
}

if (!array_key_exists('woo-variation-swatches/woo-variation-swatches.php', get_plugins())) {
    exit('Missing active plugin: woo-variation-swatches/woo-variation-swatches.php');
}

/**
 * Mapping attributes
 * Example: $attribute_ids = [
 *  woocommerce_attribute_id => backend_attribute_id
 * ].
 */
$attribute_ids = [];
foreach (wc_get_attribute_taxonomies() as $attribute_taxonomy) {
    if ('image' === $attribute_taxonomy->attribute_type) {
        $attribute_ids[$attribute_taxonomy->attribute_id] =
            \StoreKeeper\WooCommerce\B2C\Tools\WooCommerceAttributeMetadata::getMetadata(
                $attribute_taxonomy->attribute_id,
                'storekeeper_id',
                true
            );
    }
}

// Getting database prefix for later use.
global $wpdb;
$prefix = $wpdb->prefix;

// Setup API
$api = StoreKeeperApi::getApiByAuthName();
$BlogModule = $api->getModule('BlogModule');
$limit = 100;

foreach ($attribute_ids as $woo_attribute_id => $sk_attribute_id) {
    $start = 0;
    $fetchMore = true;
    while ($fetchMore) {
        WordPressHelpers::echoLogs("Fetch listTranslatedAttributeOptions $start");
        $response = $BlogModule->listTranslatedAttributeOptions(
            0,
            $start,
            $limit,
            [
                [
                    'name' => 'id',
                    'dir' => 'asc',
                ],
            ],
            [
                [
                    'name' => 'attribute_id__=',
                    'val' => $sk_attribute_id,
                ],
                [
                    'name' => 'image_url__!=',
                    'val' => '',
                ],
                [
                    'name' => 'image_url__notnull',
                    'val' => '1',
                ],
            ]
        );
        $responseData = &$response['data'];
        $responseCount = &$response['count'];
        $start += $responseCount - 1; // Update start.

        WordPressHelpers::echoLogs("Found $responseCount");

        // Loop over all attributes options
        foreach ($responseData as $item) {
            $option = new \Adbar\Dot($item);

            // Preparing the attribute name, This is how we do it
            $sql = <<<SQL
SELECT term.term_id
FROM `{$prefix}term_taxonomy` AS term

JOIN `{$prefix}termmeta` AS meta
ON term.term_id=meta.term_id

WHERE term.taxonomy=%s
AND meta.meta_key='storekeeper_id'
AND meta.meta_value=%d
SQL;

            $preparedSql = $wpdb->prepare(
                $sql,
                Attributes::createWooCommerceAttributeName($option->get('attribute.slug')),
                $option->get('id')
            );
            $term_id = $wpdb->get_var($preparedSql);

            // Check if attribute option is found
            if (isset($term_id)) {
                if ($image_url = $option->get('image_url', false)) {
                    Attributes::setAttributeOptionImage($term_id, $image_url);
                } else {
                    Attributes::unsetAttributeOptionImage($term_id);
                }
            } else {
                $name = $option->get('attribute.label');
                var_dump("Attribute option \"$name\" not found");
            }
        }

        // Check if there is more to fetch!
        $fetchMore = $responseCount > 0 && $limit === $responseCount;
    }
}
