jQuery(function() {
    const errorDialog = jQuery('#dialog-error-message');
    errorDialog.dialog({
        modal: true,
        autoOpen: false,
        closeText: false,
        width: 1000
    });

    jQuery('.dialog-logs').on('click', function () {
        const id = jQuery(this).data('id');
        const message = jQuery('#error-message-' + id).html();
        errorDialog.html(message);
        errorDialog.dialog('open');
    });
});