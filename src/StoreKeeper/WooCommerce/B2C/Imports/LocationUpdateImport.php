<?php

namespace StoreKeeper\WooCommerce\B2C\Imports;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Models\LocationModel;

class LocationUpdateImport extends LocationImport
{

    /**#@+
     * Location scopes
     */
    public const ADDRESS_SCOPE = 'address';
    public const OPENING_HOUR_SCOPE = 'opening_hour';
    public const OPENING_SPECIAL_HOUR_SCOPE = 'opening_special_hour';
    /**#@-*/

    /**
     * @var null|array
     */
    protected $scope;

    /**
     * Location constructor.
     *
     * @throws \Exception
     */
    public function __construct(array $settings = [])
    {
        if (array_key_exists('scope', $settings)) {
            $scope = array_unique(array_filter(array_map('trim', explode(',', $settings['scope']) ?: [])));

            if ($scope) {
                $this->scope = $scope;
            }

            unset($settings['scope'], $scope);
        }

        parent::__construct($settings);
    }

    /**
     * @param Dot $dotObject
     * @param array $options
     * @throws \Exception
     */
    protected function processItem($dotObject, array $options = []): ?int
    {
        $locationStoreKeeperId = $this->getStoreKeeperId($dotObject);
        $locationId = LocationModel::getLocationIdByStoreKeeperId($locationStoreKeeperId);

        if (null === $locationId) {
            return parent::processItem($dotObject, $options);
        }

        if (!$this->scope) {
            $this->processLocation($dotObject, $options);
        } else {
            if (in_array(self::ADDRESS_SCOPE, $this->scope)) {
                $this->processLocationAddress($dotObject, $locationId);
            }

            if (in_array(self::OPENING_HOUR_SCOPE, $this->scope)) {
                $this->processLocationOpeningHours($dotObject, $locationId);
            }

            if (in_array(self::OPENING_SPECIAL_HOUR_SCOPE, $this->scope)) {
                $this->processLocationSpecialOpeningHours($dotObject, $locationId);
            }
        }

        return LocationModel::getStoreKeeperId($locationId);
    }
}
