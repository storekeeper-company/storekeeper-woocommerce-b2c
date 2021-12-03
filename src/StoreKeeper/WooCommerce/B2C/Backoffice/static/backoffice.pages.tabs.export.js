jQuery(function($) {
    const translate = function (key) {
        return exportSettings.labels[key] ?? key;
    };
    const openInNewTab = function (href) {
        Object.assign(document.createElement('a'), {
            target: '_blank',
            href: href,
        }).click();
    }

    $('.export-button').click(function (e) {
        e.preventDefault();
        const type = $(this).val();
        const language = $('select[name="lang"]').val();
        let isCancelled = false;
        let exportRequest = null;
        Swal.fire({
            title: translate('Preparing export'),
            text:  translate('Please wait and keep the page and popup window open while we are preparing your export'),
            showConfirmButton: false,
            showDenyButton: true,
            denyButtonText: translate('Stop exporting'),
            allowOutsideClick: false,
            allowEscapeKey: false
        }).then((result) => {
            if (result.isDenied && exportRequest !== null) {
                isCancelled = true;
                exportRequest.abort();
            }
        });

        exportRequest = $.ajax({
            url: exportSettings.url,
            data: {
                type: type,
                lang: language
            },
            timeout: 0
        }).done(function ({ url, size, filename }) {
            Swal.fire({
                title: translate('Your file has been generated'),
                html: `
                    ${translate('Your download will start in a few seconds. If not, you can download the file manually using the link below')}
                    <br><br>
                    <a href="${url}" class="button button-secondary" target="_blank">${filename}<br>${translate('Size')}: ${size}</a>
                `,
                showConfirmButton: false,
                showCloseButton: true,
                allowOutsideClick: false,
                allowEscapeKey: false
            });

            setTimeout(function () {
                if (url) {
                    openInNewTab(url);
                }
            }, 1500);

        }).fail(function (xhrText, textStatus) {
            if (!isCancelled) {
                Swal.fire({
                    title: translate('Something went wrong while exporting'),
                    text:  translate('Do you want to try splitting export files by 100?'),
                    showDenyButton: true,
                    denyButtonText: 'No, thanks'
                }).then(function (result) {
                    if (result.isConfirmed) {

                    }
                });
            }
        });
    });
});