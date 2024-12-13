<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs;

use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\LocationModel;
use StoreKeeper\WooCommerce\B2C\Models\Location\AddressModel;
use StoreKeeper\WooCommerce\B2C\Models\Location\OpeningHourModel;
use StoreKeeper\WooCommerce\B2C\Models\Location\OpeningSpecialHoursModel;
use StoreKeeper\WooCommerce\B2C\Helpers\Location\AddressHelper;
use StoreKeeper\WooCommerce\B2C\Helpers\Location\OpeningSpecialHour as OpeningSpecialHourHelper;
use StoreKeeper\WooCommerce\B2C\Helpers\DateTimeHelper;
use StoreKeeper\WooCommerce\B2C\Backoffice\Helpers\TableRenderer;
use StoreKeeper\WooCommerce\B2C\Helpers\HtmlEscape;

class LocationsTab extends AbstractLogsTab
{

    /**
     * Limit in the grid of the opening special hours
     */
    protected const OPENING_SPECIAL_HOURS_LIMIT = 10;

    /**
     * View location action
     */
    protected const VIEW_LOCATION_ACTION = 'view';

    /**
     * Location addresses
     *
     * @var null|array
     */
    protected $locationAddresses;

    /**
     * Location opening hours
     *
     * @var null|array
     */
    protected $locationOpeningHours;

    /**
     * Location opening special hours
     *
     * @var null|array
     */
    protected $locationOpeningSpecialHours;

    public function __construct(string $title, string $slug = '')
    {
        parent::__construct($title, $slug);

        $this->addAction(
            self::VIEW_LOCATION_ACTION,
            [$this, 'viewLocationAction']
        );
    }

    /**
     * Retrive style paths
     *
     * @return string[]
     */
    protected function getStylePaths(): array
    {
        return array_merge(
            parent::getStylePaths(),
            [
                plugin_dir_url(__FILE__).'/../../../static/locations.tab.css',
            ]
        );
    }

    /**
     * Render tab
     */
    public function render(): void
    {
        $this->items = $this->fetchData(LocationModel::class);
        $this->count = LocationModel::count();

        $this->loadLocationRelations();

        $this->renderPagination();

        $this->renderTable(
            [
                [
                    'title' => '',
                    'key' => 'is_active',
                    'bodyFunction' => function ($status) {
                        $this->renderStatus($status);
                    }
                ],
                [
                    'title' => __('Backoffice ID', I18N::DOMAIN),
                    'key' => LocationModel::PRIMARY_KEY
                ],
                [
                    'title' => __('Name', I18N::DOMAIN),
                    'key' => 'name',
                    'bodyFunction' => \Closure::fromCallable([$this, 'renderName'])
                ],
                [
                    'title' => __('Address', I18N::DOMAIN),
                    'key' => 'address',
                    'bodyFunction' => \Closure::fromCallable([$this, 'renderAddress'])
                ],
                [
                    'title' => __('Regular opening hours', I18N::DOMAIN),
                    'key' => 'opening_hours',
                    'bodyFunction' => \Closure::fromCallable([$this, 'renderOpeningHours'])
                ],
                [
                    'title' => __('Special opening hours', I18N::DOMAIN),
                    'key' => 'opening_special_hours',
                    'bodyFunction' => \Closure::fromCallable([$this, 'renderOpeningSpecialHours'])
                ],
                [
                    'title' => __('Action', I18N::DOMAIN),
                    'key' => 'action',
                    'bodyFunction' => \Closure::fromCallable([$this, 'renderLocationActions'])
                ],
            ]
        );

        $this->renderPagination();
    }

    protected function loadLocationRelations()
    {
        $this
            ->loadLocationAddresses()
            ->loadLocationOpeningHours()
            ->loadLocationOpeningSpecialHours();

        return $this;
    }

    /**
     * Load addresses of current locations
     *
     * @return LocationsTab
     */
    protected function loadLocationAddresses()
    {
        if (null === $this->locationAddresses) {
            $this->locationAddresses = $this->getLocationRelations(AddressModel::class, true);
        }

        return $this;
    }

    /**
     * Load opening hours of current locations
     *
     * @return LocationsTab
     */
    protected function loadLocationOpeningHours()
    {
        if (null === $this->locationOpeningHours) {
            $this->locationOpeningHours = $this->getLocationRelations(
                OpeningHourModel::class,
                false,
                [],
                [],
                'open_day',
                'ASC'
            );
        }

        return $this;
    }

    /**
     * Load opening special hours of current locations
     *
     * @return LocationsTab
     */
    protected function loadLocationOpeningSpecialHours()
    {
        if (null === $this->locationOpeningSpecialHours) {
            $whereClauses = $whereValues = [];

            if ('' === sanitize_key($_REQUEST['action'] ?? '')) {
                $whereClauses[] = 'date >= :date';
                $whereValues['date'] = wp_date(DateTimeHelper::MYSQL_DATE_FORMAT);
            }

            $this->locationOpeningSpecialHours = $this->getLocationRelations(
                OpeningSpecialHoursModel::class,
                false,
                $whereClauses,
                $whereValues,
                'date',
                'ASC',
                self::OPENING_SPECIAL_HOURS_LIMIT
            );
        }

        return $this;
    }

    /**
     * Load relations of current locations
     *
     * @param string $modelClass
     * @param bool $unique
     * @param array $whereClauses
     * @param array $whereValues
     * @param null|string $orderBy
     * @param null|string $orderDirection
     * @param null|int $limit
     * @param string $column
     * @return array
     */
    protected function getLocationRelations(
        $modelClass,
        $unique = false,
        array $whereClauses = [],
        array $whereValues = [],
        ?string $orderBy = null,
        ?string $orderDirection = null,
        ?int $limit = null,
        $column = 'location_id'
    ) {
        $items = [];

        if ($this->items) {
            $items = call_user_func(
                [$modelClass, 'findBy'],
                array_merge(
                    $whereClauses,
                    [
                        sprintf(
                            '%s IN (%s)',
                            $column,
                            implode(', ', array_column($this->items, LocationModel::PRIMARY_KEY))
                        )
                    ]
                ),
                $whereValues,
                $orderBy,
                $orderDirection,
                $limit
            );

            if ($items) {
                if ($unique) {
                    $items = array_combine(
                        array_column($items, $column),
                        $items
                    );
                } else {
                    $grouped = [];
                    foreach ($items as $item) {
                        if (!array_key_exists($item[$column], $grouped)) {
                            $grouped[$item[$column]] = [];
                        }

                        $grouped[$item[$column]][$item[constant("$modelClass::PRIMARY_KEY")]] = $item;

                        unset($item);
                    }

                    $items = $grouped;

                    unset($grouped);
                }
            }
        }

        return $items;
    }

    /**
     * Render location name
     *
     * @param string $name
     * @param array $location
     * @return void
     */
    protected function renderName($name, $location)
    {
        echo esc_html((string) $name);
        echo $this->getLocationDefaultLabelHtml($location);
    }

    /**
     * Render location status
     *
     * @param int|string $status
     * @param bool $echo
     * @return string|void
     */
    protected function renderStatus($status, $echo = true)
    {
        $title = esc_attr(($status) ? __('Activated', I18N::DOMAIN) : __('Deactivated', I18N::DOMAIN));
        $status = ($status) ? 'success' : 'danger';

        $html = wp_kses(<<<HTML
            <span class="storekeeper-status" title="{$title}"><span class="storekeeper-status-{$status}"></span></span>
            HTML,
            HtmlEscape::ALLOWED_COMMON
        );

        if (!$echo) {
            return $html;
        }

        echo $html;
    }

    /**
     * Render location address
     *
     * @param mixed $value
     * @param array $item
     * @return void
     */
    protected function renderAddress($value, array $item)
    {
        $formattedAddress = '-';

        if (array_key_exists($item[LocationModel::PRIMARY_KEY], $this->locationAddresses)) {
            $locationAddress = $this->locationAddresses[$item[LocationModel::PRIMARY_KEY]];
            $formattedAddress = AddressHelper::getFormattedAddress($locationAddress, ', ') ?: $formattedAddress;
            unset($locationAddress);
        }

        echo $formattedAddress;
    }

    /**
     * Render location opening hours
     *
     * @param mixed $value
     * @param array $item
     * @return void
     */
    protected function renderOpeningHours($value, array $item)
    {
        global $wp_locale;

        if (array_key_exists($item[LocationModel::PRIMARY_KEY], $this->locationOpeningHours)) {
            echo '<ul>';
            foreach ($this->locationOpeningHours[$item[LocationModel::PRIMARY_KEY]] as $openingHour) {
                printf(
                    '<li><strong>%s</strong>: %s - %s</li>',
                    $wp_locale->get_weekday($openingHour['open_day']),
                    substr($openingHour['open_time'], 0, 5),
                    substr($openingHour['close_time'], 0, 5)
                );
            }
            echo '</ul>';
        } else {
            esc_html_e('Location is closed.', I18N::DOMAIN);
        }
    }

    /**
     * Render location opening special hours
     *
     * @param mixed $value
     * @param array $item
     * @return void
     */
    protected function renderOpeningSpecialHours($value, array $item)
    {
        $openingSpecialHours = $this->locationOpeningSpecialHours[$item[LocationModel::PRIMARY_KEY]] ?? [];

        if ($openingSpecialHours) {
            echo '<ul>';
            foreach ($openingSpecialHours as $specialHour) {
                OpeningSpecialHourHelper::renderItem($specialHour);

                unset($specialHour);
            }
            echo '</ul>';
        } else {
            esc_html_e('No special opening hours set.', I18N::DOMAIN);
        }

        unset($openingSpecialHours);
    }

    /**
     * Render location actions
     *
     * @param mixed $value
     * @param array $item
     * @return void
     */
    protected function renderLocationActions($value, array $item)
    {
        $viewUrl = add_query_arg(
            'location',
            $item[LocationModel::PRIMARY_KEY],
            $this->getActionUrl(self::VIEW_LOCATION_ACTION)
        );
        ?>
        <div class="button-group">
            <a
                href="<?php echo esc_url($viewUrl) ?>"
                class="button button-secondary"
                title="<?php echo esc_attr($item['name'] ?? __('View', I18N::DOMAIN)) ?>"
            ><?php esc_html_e('View', I18N::DOMAIN) ?></a>
        </div>
        <?php
    }

    /**
     * Execute location view action
     *
     * @return void
     */
    protected function viewLocationAction()
    {
        if (array_key_exists('location', $_GET)) {
            $location = null;

            if (is_numeric($_GET['location'])) {
                $location = LocationModel::get((int) $_GET['location']);
            }

            if (null !== $location) {
                $this->renderRestOtherTab = false;
                $this->items[$location[LocationModel::PRIMARY_KEY]] = $location;

                $this->loadLocationRelations($location);
                $this->renderLocationViewHeader($location);
                $this->renderLocationOpeningHours($location);
                $this->renderLocationAddress($location);
            } else {
                $this->clearArgs();
            }
        } else {
            $this->clearArgs();
        }
    }

    /**
     * Render location view header
     *
     * @param array $location
     * @return void
     */
    protected function renderLocationViewHeader($location)
    {
        if ($this->slug) {
            $locationsUrl = add_query_arg('page', $this->slug, admin_url('admin.php'));
        } else {
            $locationsUrl = remove_query_arg(['location', 'action']);
        }

        $referer = wp_get_referer();
        if ($referer && parse_url($referer, PHP_URL_PATH) === parse_url($locationsUrl, PHP_URL_PATH)) {
            $args = [];

            parse_str(parse_url($referer, PHP_URL_QUERY), $args);

            if (!array_key_exists('action', $args) && isset($args['page']) && $args['page'] === $_GET['page']) {
                $locationsUrl = $referer;
            }

            unset($args);
        }

        $locationsLabel = esc_html__('Locations', I18N::DOMAIN);
        $locationTxtLabel = esc_html__('Location', I18N::DOMAIN);
        $locationStatusHtml = $this->renderStatus($location['is_active'], false);
        $locationName = esc_html($location['name']);
        $locationIdTxt = esc_html(sprintf(__('ID#%s', I18N::DOMAIN), $location['storekeeper_id']));
        $locationDefaultHtml = $this->getLocationDefaultLabelHtml($location);
        $locationsUrl = esc_url($locationsUrl);

        echo <<<HTML
            <h2 class="storekeeper-location-heading">
                {$locationStatusHtml}
                <span>{$locationTxtLabel}: {$locationName} ({$locationIdTxt})</span>
                {$locationDefaultHtml}
                <a href="{$locationsUrl}" class="button button-secondary button-locations">{$locationsLabel}</a>
            </h2>
        HTML;
    }

    /**
     * Render location opening hours view action
     *
     * @param array $location
     * @return void
     */
    protected function renderLocationOpeningHours($location)
    {
        $table = new TableRenderer();
        $table->addColumn(__('Opening hours', I18N::DOMAIN), 'title');
        $table->addColumn('', 'value');
        $table->render(
            [
                [
                    'title' => __('Regular opening hours', I18N::DOMAIN),
                    'value' => null,
                    'function::value' => function() use ($location) {
                        $this->renderOpeningHours(null, $location);
                    }
                ],
                [
                    'title' => __('Special opening hours', I18N::DOMAIN),
                    'value' => null,
                    'function::value' => function () use ($location) {
                        $this->renderOpeningSpecialHours(null, $location);
                    }
                ]
            ]
        );
        unset($table);
    }

    /**
     * Render location address view action
     *
     * @param array $location
     * @return void
     */
    protected function renderLocationAddress($location)
    {
        if (array_key_exists($location[LocationModel::PRIMARY_KEY], $this->locationAddresses)) {
            $locationAddress = $this->locationAddresses[$location[LocationModel::PRIMARY_KEY]];
            $defaultValue = '-';

            $table = new TableRenderer();
            $table->addColumn(__('Address', I18N::DOMAIN), 'title');
            $table->addColumn('', 'value');
            $table->render([
                [
                    'title' => __('Street name', I18N::DOMAIN),
                    'value' => $locationAddress['street'] ?: $defaultValue
                ],
                [
                    'title' => __('House number', I18N::DOMAIN),
                    'value' => $locationAddress['streetnumber'] ?: $defaultValue
                ],
                [
                    'title' => __('Flat number', I18N::DOMAIN),
                    'value' => $locationAddress['flatnumber'] ?: $defaultValue
                ],
                [
                    'title' => __('Zipcode', I18N::DOMAIN),
                    'value' => $locationAddress['zipcode'] ?: $defaultValue
                ],
                [
                    'title' => __('City', I18N::DOMAIN),
                    'value' => $locationAddress['city'] ?: $defaultValue
                ],
                [
                    'title' => __('State', I18N::DOMAIN),
                    'value' => $locationAddress['state'] ?: $defaultValue
                ],
                [
                    'title' => __('Country', I18N::DOMAIN),
                    'value' => $locationAddress['country'] ?: null,
                    'function::value' => function ($country) use ($defaultValue) {
                        $this->renderAddressCountry($country, $defaultValue);
                    }
                ],
                [
                    'title' => __('Telephone', I18N::DOMAIN),
                    'value' => $locationAddress['phone'] ?: null,
                    'function::value' => function ($phone) use ($defaultValue) {
                        $this->renderAddressTelephone($phone, $defaultValue);
                    }
                ],
                [
                    'title' => __('E-mail address', I18N::DOMAIN),
                    'value' => $locationAddress['email'] ?: null,
                    'function::value' => function ($email) use ($defaultValue) {
                        $this->renderAddressEmail($email, $defaultValue);
                    }
                ],
                [
                    'title' => __('Website', I18N::DOMAIN),
                    'value' => $locationAddress['url'] ?: null,
                    'function::value' => function ($website) use ($defaultValue) {
                        $this->renderAddressWebsite($website, $defaultValue);
                    }
                ],
                [
                    'title' => __('GLN (Global Location Number)', I18N::DOMAIN),
                    'value' => $locationAddress['gln'] ?: $defaultValue
                ]
            ]);
            unset($table);
        }
    }

    /**
     * Render location address country
     *
     * @param null|string $phone
     * @param string $defaultValue
     * @return void
     */
    protected function renderAddressCountry($country, $defaultValue = '-')
    {
        if (null === $country || '' === ($country = trim($country)) || $country === $defaultValue) {
            echo $defaultValue;
        } else {
            echo esc_html(
                WC()->countries->country_exists($country)
                    ? WC()->countries->get_countries()[$country]
                    : $country
            );
        }
    }

    /**
     * Render location address telephone
     *
     * @param null|string $phone
     * @param string $defaultValue
     * @return void
     */
    protected function renderAddressTelephone($phone, $defaultValue = '-')
    {
        if (null === $phone || '' === ($phone = trim($phone)) || $phone === $defaultValue) {
            echo $defaultValue;
        } else {
            $url = esc_attr($phone);
            $phone = esc_html($phone);
            echo <<<HTML
            <a href="tel:$url">$phone</a>
            HTML;
        }
    }

    /**
     * Render location address email
     *
     * @param null|string $email
     * @param string $defaultValue
     * @return void
     */
    protected function renderAddressEmail($email, $defaultValue = '-')
    {
        if (null === $email || '' === ($email = trim($email)) || $email === $defaultValue) {
            echo $defaultValue;
        } else if (!is_email($email)) {
            echo esc_html($email);
        } else {
            $url = esc_attr($email);
            $email = esc_html($email);
            echo <<<HTML
            <a href="mailto:$url">$email</a>
            HTML;
        }
    }

    /**
     * Render location address website
     *
     * @param null|string $website
     * @param string $defaultValue
     * @return void
     */
    protected function renderAddressWebsite($website, $defaultValue = '-')
    {
        if (null === $website || '' === ($website = trim($website)) || $website === $defaultValue) {
            echo $defaultValue;
        } else if (!wp_http_validate_url($website)) {
            echo esc_html($website);
        } else {
            $url = esc_url($website);
            $website = esc_html($website);
            echo <<<HTML
            <a href="$url" target="_blank" rel="noopener noreferrer">$website</a>
            HTML;
        }
    }

    /**
     * Get and prepare location default label
     *
     * @param array $location
     * @return string
     */
    protected function getLocationDefaultLabelHtml($location)
    {
        $defaultHtml = '';

        if ($location['is_default']) {
            $label = esc_html__('default', I18N::DOMAIN);
            $defaultHtml = <<<HTML
            <span class="storekeeper-status"><span class="storekeeper-status-default">{$label}</span></span>
            HTML;
        }

        return $defaultHtml;
    }

    /**
     * Redirect to locations page/tab
     *
     * @return void
     */
    protected function clearArgs(): void
    {
        wp_redirect(remove_query_arg(['location', 'action']));
    }
}
