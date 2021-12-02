jQuery(function($) {
    const openInNewTab = function (href) {
        Object.assign(document.createElement('a'), {
            target: '_blank',
            href: href,
        }).click();
    }

    $('.customer-export').click(function (e) {
        e.preventDefault();
        const language = $('select[name="lang"]').val();
        Swal.fire({
            title: 'Preparing export',
            text: 'Please wait...',
            showConfirmButton: false
        });

        $.get(exportSettings.url, {
            type: exportSettings['customer-export'],
            lang: language
        }).done(function ({response}) {
            swal.close();
            if (response) {
                openInNewTab(response);
            }
        }).fail(function (xhrText, textStatus) {
            swal.close();
            alert(textStatus);
        });
    });
});