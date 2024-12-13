<?php
/**
 * Template meant for displaying location block.
 *
 * @var array $attributes
 * @var string $content
 * @var \WP_Block $block
 * @var array $location
 * @var array $address
 * @var array $opening_hours
 * @var array $opening_special_hours
 */
use StoreKeeper\WooCommerce\B2C\Helpers\Location\AddressHelper;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Helpers\Location\OpeningSpecialHour as OpeningSpecialHourHelper;

global $wp_locale;

$extraAttributes = [
    'class' => implode(
        ' ',
        [
            'location',
            sprintf('location-%d', $location['id']),
            sprintf('location-storekeeper-%d', $location['storekeeper_id']),
            sprintf('location-%s', sanitize_title($location['name']))
        ]
    ),
    'itemtype' => 'https://schema.org/Place',
    'itemscope' => ''
];
?>
<div <?php echo get_block_wrapper_attributes($extraAttributes) ?>>
    <h3 itemprop="name"><?php echo esc_html($location['name']) ?></h3>

    <?php if (isset($attributes['show_address']) && $attributes['show_address'] && !empty($address)): ?>
    <address>
        <span itemprop="address" itemscope itemtype="https://schema.org/PostalAddress">
            <?php echo esc_html(AddressHelper::getFormattedAddress($address, ', ')) ?>
        </span>

        <?php if (isset($address['url']) && null !== $address['url'] && '' !== ($website = trim($address['url'])) &&
            wp_http_validate_url($website)): ?>
        <a class="url" itemprop="url" href="<?php echo esc_url($website) ?>" target="_blank" rel="noopener noreferrer">
            <?php echo esc_html($website) ?>
        </a>
        <?php endif; ?>

        <?php if (isset($address['phone']) && null !== $address['phone'] &&
            '' !== ($phone = trim($address['phone']))): ?>
        <a class="telephone" itemprop="telephone" href="tel:<?php esc_attr($phone) ?>">
            <?php echo esc_html($phone) ?>
        </a>
        <?php endif; ?>

        <?php if (isset($address['email']) && null !== $address['email'] && '' !== ($email = trim($address['email']))
            && is_email($email)): ?>
        <a class="email" href="mailto:<?php echo esc_attr($email) ?>" itemprop="email">
            <?php echo esc_html($email) ?>
        </a>
        <?php endif ?>
    </address>
    <?php endif; ?>

    <?php if (isset($attributes['show_opening_hour']) && $attributes['show_opening_hour']): ?>
    <div class="opening-hours">
        <h4><?php esc_html_e('Regular opening hours') ?></h4>

        <?php if (empty($opening_hours)): ?>
        <p><?php _e('Location is closed.', I18N::DOMAIN); ?></p>
        <?php else: ?>
            <ul>
                <?php foreach ($opening_hours as $opening_hour): ?>
                <?php
                    $weekday = $wp_locale->get_weekday($opening_hour['open_day']);
                    $openTime = substr($opening_hour['open_time'], 0, 5);
                    $closeTime = substr($opening_hour['close_time'], 0, 5);
                    $microdata = sprintf('%s %s-%s', substr($weekday, 0, 2), $openTime, $closeTime);
                ?>
                <li
                    class="opening-hour"
                    itemprop="openingHours"
                    content="<?php echo esc_attr($microdata) ?>">
                    <strong><?php echo esc_html($weekday) ?></strong>:
                    <span class="open-time time"><?php echo esc_html($openTime) ?></span>
                    <span class="delimiter">-</span>
                    <span class="close-time time"><?php echo esc_html($closeTime) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (isset($attributes['show_opening_special_hours']) && $attributes['show_opening_special_hours'] &&
        !empty($opening_special_hours)): ?>
    <div class="opening-special-hours">
        <h4><?php esc_html_e('Special opening hours') ?></h4>
        <ul>
            <?php foreach ($opening_special_hours as $opening_hour): ?>
            <?php OpeningSpecialHourHelper::renderItem($opening_hour) ?>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
</div>
