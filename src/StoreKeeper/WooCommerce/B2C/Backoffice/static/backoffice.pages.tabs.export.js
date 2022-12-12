jQuery(function($) {
    let isDownloading = false;

    /* Functions */
    const translate = function (key) {
        if (typeof exportSettings.translations[key] === 'undefined') {
            console.log(`Storekeeper: Missing translation for (${key})`);
        }
        return exportSettings.translations[key] ?? key;
    };
    const openInNewTab = function (href) {
        Object.assign(document.createElement('a'), {
            target: '_blank',
            href: href,
        }).click();
    }

    /* Triggers when download button is clicked */
    window.onfocus = function () {
        if (isDownloading) {
            isDownloading = false;
            setTimeout(function () {
                Swal.close();
            }, 1000);
        }
    };

    /* Events */
    $('.toggle-help').click(function (e) {
        e.preventDefault();
        const isVisible = $('.help-section').is(':visible');
        if (isVisible) {
            $('.help-section').hide();
        } else  {
            $('.help-section').show();
        }
    });

    $('.export-button').click(function (e) {
        e.preventDefault();
        const type = $(this).val();
        const language = $('select[name="lang"]').val();
        let isCancelled = false;
        let exportRequest = null;
        Swal.fire({
            title: translate('Preparing export'),
            html:  `
            <div class="loader"></div>
            <br>
            <span>${translate('Please wait and keep the page and popup window open while we are preparing your export')}</span>
            `,
            showConfirmButton: false,
            showDenyButton: true,
            denyButtonText: translate('Stop exporting'),
            allowOutsideClick: false,
            allowEscapeKey: false,
        }).then((result) => {
            if (result.isDenied && exportRequest !== null) {
                isCancelled = true;
                exportRequest.abort();
            }
        });

        let data = {
            type: type,
            lang: language
        };

        if ($(`input[type="checkbox"][name="${type}-all-products"]`).length > 0) {
            data.activeProductsOnly = !$(`input[type="checkbox"][name="${type}-all-products"]`).is(':checked');
        }

        if ($(`input[type="checkbox"][name="${type}-skip-empty-tags"]`).length > 0) {
            data.skipEmptyTags = $(`input[type="checkbox"][name="${type}-skip-empty-tags"]`).is(':checked');
        }

        exportRequest = $.ajax({
            url: exportSettings.url,
            data: data,
            timeout: 0
        }).done(function ({ url, size, filename }) {
            Swal.fire({
                title: translate('Your file has been generated'),
                html: `
                    ${translate('Your download will start in a few seconds. If not, you can download the file manually using the link below')}
                    <br><br>
                    <a href="${url}" class="button button-secondary button-download" target="_blank">${filename}<br>${translate('Size')}: ${size}</a>
                `,
                showConfirmButton: false,
                showCloseButton: true,
                allowOutsideClick: false,
                allowEscapeKey: false
            });

            $('.button-download').off('click');
            $('.button-download').click(function () {
                isDownloading = true;
            });

            setTimeout(function () {
                if (url) {
                    openInNewTab(url);
                }
            }, 1500);

        }).fail(function (xhrText, textStatus) {
            if (!isCancelled) {
                Swal.fire({
                    title: translate('Export failed'),
                    text:  translate('Something went wrong during export or server timed out. You can try manual export via command line, do you want to read the guide?'),
                    showDenyButton: true,
                    denyButtonText: translate('No, thanks'),
                    confirmButtonText: translate('Yes, please')
                }).then(function (result) {
                    if (result.isConfirmed) {
                        $('.help-section').show();
                    }
                });
            }
        });
    });
});
