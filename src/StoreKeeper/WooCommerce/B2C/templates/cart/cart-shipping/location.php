<?php
/**
 * Template meant for displaying shipping/pickup location.
 *
 * @var array $locations
 */
use StoreKeeper\WooCommerce\B2C\Helpers\Location\AddressHelper;
use StoreKeeper\WooCommerce\B2C\Tools\LocationShippingDateResolver;
use StoreKeeper\WooCommerce\B2C\Helpers\DateTimeHelper;
use StoreKeeper\WooCommerce\B2C\Models\LocationModel;
use StoreKeeper\WooCommerce\B2C\Models\Location\AddressModel;

$chosenLocations = WC()->session->get('chosen_storekeeper_location') ?: [];
$checkedValue = 1 === count($locations) || !array_key_exists($rate->get_instance_id(), $chosenLocations)
    ? reset($locations)[LocationModel::PRIMARY_KEY]
    : $chosenLocations[$rate->get_instance_id()];
?>
<div class="storekeeper-locations">
    <?php foreach ($locations as $location): ?>
    <?php
        $locationAddress = AddressModel::getByLocationId($location[LocationModel::PRIMARY_KEY]);
        $formattedAddress = trim(AddressHelper::getFormattedAddress($locationAddress, ', '));
        $deliveryDate = LocationShippingDateResolver::getPickupDate($location[LocationModel::PRIMARY_KEY]);
    ?>
    <ul>
        <li class="location">
            <label>
                <input
                    type="radio"
                    name="storekeeper[location][shipping_method][<?php echo esc_attr($rate->get_instance_id()) ?>]"
                    value="<?php echo esc_attr($location[LocationModel::PRIMARY_KEY]) ?>"
                    <?php if (null !== $checkedValue && $checkedValue == $location[LocationModel::PRIMARY_KEY]): ?>
                    checked="checked"
                    <?php endif; ?>
                />
                <span class="details">
                    <strong class="name"><?php echo esc_html($location['name']) ?></strong>
                    <?php if ('' !== $formattedAddress): ?>
                    <address><?php echo esc_html($formattedAddress) ?></address>
                    <?php endif; ?>
                    <?php if ($deliveryDate): ?>
                    <input
                        type="hidden"
                        name="storekeeper[location][date][<?php echo esc_attr($location[LocationModel::PRIMARY_KEY]) ?>]"
                        value="<?php echo esc_attr($deliveryDate->getTimestamp()) ?>"
                    />
                    <span class="delivery-date">
                        <?php echo esc_html(DateTimeHelper::formatForDisplay(\DateTime::createFromImmutable($deliveryDate))) ?>
                    </span>
                    <?php endif; ?>
                </span>
            </label>
        </li>
    </ul>
    <?php endforeach; ?>
</div>
