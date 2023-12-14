jQuery(window).load(() => {
    var $ = jQuery;
    var zoneIds = shippingZones.ids;
    var disableActions = () => {
        // Removes the action links for shipping methods from StoreKeeper
        zoneIds.forEach((zoneId) => {
            var tableRow = $(`tbody.wc-shipping-zone-rows tr[data-id="${zoneId}"]`);
            tableRow.find(`div.row-actions`).remove();

            var zoneNameAnchor = tableRow.find(`td.wc-shipping-zone-name a`);
            var zoneName = zoneNameAnchor.text();
            if (!zoneName) {
                zoneName = tableRow.find('td.wc-shipping-zone-name').text();
            }
            $(`tbody.wc-shipping-zone-rows tr[data-id="${zoneId}"] .wc-shipping-zone-name`).text(zoneName);
        });
    };

    disableActions();

    $(document).on('sortstop', '.ui-sortable', () => {
        disableActions();
        // Perform disabling of actions in a few milliseconds
        // to make sure all shipping methods are already rerendered.
        setTimeout(disableActions, 250);
    });

    // Fallback just in case the sortstop event listener fires
    // before the table is rerendered.
    zoneIds.forEach((zoneId) => {
        $(document).on('mouseover', `tbody.wc-shipping-zone-rows tr[data-id="${zoneId}"]`, () => {
            disableActions();
        });
    });
});
