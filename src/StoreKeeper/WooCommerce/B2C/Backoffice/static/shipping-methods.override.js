jQuery(window).load(() => {
    var zoneIds = shippingZones.ids;
    zoneIds.forEach((zoneId) => {
        jQuery(`tbody.wc-shipping-zone-rows > tr[data-id="${zoneId}"] .row-actions`).remove();

        var zoneNameAnchor = jQuery(`tbody.wc-shipping-zone-rows > tr[data-id="${zoneId}"] .wc-shipping-zone-name a`);
        var zoneName = zoneNameAnchor.text();
        jQuery(`tbody.wc-shipping-zone-rows > tr[data-id="${zoneId}"] .wc-shipping-zone-name`).text(zoneName);
    });

    // TODO: Elements come back on draggable
});
