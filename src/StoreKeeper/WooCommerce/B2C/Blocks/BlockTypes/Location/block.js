/*! StoreKeeper | StoreKeeper for WooCommerce | https://www.storekeeper.com/ */
(function (blocks, element, components, blockEditor, i18n, data, serverSideRender) {
    var createElement = element.createElement;
    var InspectorControls = blockEditor.InspectorControls;

    data.dispatch('core').addEntities([{
        name: 'locations',
        kind: '/storekeeper-woocommerce-b2c/v1',
        baseURL: '/storekeeper-woocommerce-b2c/v1/locations/'
    }]);

    blocks.registerBlockType('storekeeper/location', {
        edit: function (props) {
            var blockProps = blockEditor.useBlockProps();

            var locations = data.useSelect(function (select) {
                return select('core').getEntityRecords('/storekeeper-woocommerce-b2c/v1', 'locations', {active: true});
            });

            return createElement(
                'div',
                blockProps,
                [
                    createElement(
                        InspectorControls,
                        {},
                        createElement(
                            components.Panel,
                            {
                                children: [
                                    createElement(
                                        components.PanelBody,
                                        {
                                            title: i18n.__('Settings'),
                                            initialOpen: true,
                                            children: [
                                                createElement(
                                                    components.PanelRow,
                                                    {
                                                        children: (function () {
                                                            if (!locations) {
                                                                return createElement(components.Spinner);
                                                            }
    
                                                            return createElement(
                                                                components.SelectControl,
                                                                {
                                                                    label: i18n.__('Location', 'storekeeper'),
                                                                    options: locations.reduce(
                                                                        function (accumulator, location) {
                                                                            accumulator.push({
                                                                                value: location.id,
                                                                                label: location.name
                                                                            });
                                                                            return accumulator;
                                                                        },
                                                                        [
                                                                            {
                                                                                value: 0,
                                                                                label: i18n.__(
                                                                                    'Please select a location',
                                                                                    'storekeeper'
                                                                                )
                                                                            }
                                                                        ]
                                                                    ),
                                                                    onChange: function (locationId) {
                                                                        props.setAttributes({
                                                                            location: parseInt(locationId)
                                                                        });
                                                                    },
                                                                    value: props.attributes.location
                                                                }
                                                            );
                                                        }())
                                                    }
                                                ),

                                                createElement(
                                                    components.PanelRow,
                                                    {
                                                        children: createElement(
                                                            components.ToggleControl,
                                                            {
                                                                label: i18n.__('Show address', 'storekeeper'),
                                                                onChange: function (showAddress) {
                                                                    props.setAttributes({
                                                                        show_address: showAddress
                                                                    });
                                                                },
                                                                checked: props.attributes.show_address
                                                            }
                                                        )
                                                    }
                                                ),

                                                createElement(
                                                    components.PanelRow,
                                                    {
                                                        children: createElement(
                                                            components.ToggleControl,
                                                            {
                                                                label: i18n.__('Show opening hours', 'storekeeper'),
                                                                onChange: function (showOpeningHours) {
                                                                    props.setAttributes({
                                                                        show_opening_hour: showOpeningHours
                                                                    });
                                                                },
                                                                checked: props.attributes.show_opening_hour
                                                            }
                                                        )
                                                    }
                                                ),

                                                createElement(
                                                    components.PanelRow,
                                                    {
                                                        children: createElement(
                                                            components.ToggleControl,
                                                            {
                                                                label: i18n.__(
                                                                    'Show opening special hours',
                                                                    'storekeeper'
                                                                ),
                                                                onChange: function (openingSpecialHours) {
                                                                    props.setAttributes({
                                                                        show_opening_special_hours: openingSpecialHours
                                                                    });
                                                                },
                                                                checked: props.attributes.show_opening_special_hours
                                                            }
                                                        )
                                                    }
                                                )
                                            ]
                                        }
                                    )
                                ]
                            }
                        )
                    ),

                    (function () {
                        if (props.attributes.location) {
                            return createElement(
                                serverSideRender,
                                {
                                    block: props.name,
                                    attributes: props.attributes
                                }
                            );
                        }

                        return createElement('p', {}, i18n.__('Please select a location', 'storekeeper'));
                    }())
                ]
            );
        }
    });
}(
    window.wp.blocks,
    window.wp.element,
    window.wp.components,
    window.wp.blockEditor,
    window.wp.i18n,
    window.wp.data,
    window.wp.serverSideRender
));
