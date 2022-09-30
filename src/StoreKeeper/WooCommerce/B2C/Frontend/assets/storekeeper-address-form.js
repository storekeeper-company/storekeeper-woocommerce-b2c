jQuery(function ($) {
    /* Global functions */
    window.storekeeperTranslations = settings.translations;
    if (typeof storekeeperTranslate != 'function') {
        window.storekeeperTranslate = function (key) {
            if (typeof window.storekeeperTranslations[key] === 'undefined') {
                console.log(`Storekeeper: Missing translation for (${key})`);
            }
            return window.storekeeperTranslations[key] ?? key;
        }
    };

    if (typeof storekeeperBlockForm != 'function') {
        window.storekeeperBlockForm = function (addressForm) {
            $(':focus').blur();
            addressForm.addClass('storekeeper-loading');
        };
    }

    if (typeof storekeeperUnblockForm != 'function') {
        window.storekeeperUnblockForm = function (addressForm) {
            addressForm.removeClass('storekeeper-loading');
        };
    }

    if (typeof storekeeperFetchAddressFromBackend != 'function') {
        window.storekeeperFetchAddressFromBackend = function (formPrefix, parentForm) {
            return new Promise(function (resolve, reject) {
                let isValid = true;
                window.storekeeperBlockForm(parentForm);
                const postCodeContainer = $(`#${formPrefix}_postcode_field`);
                const postCodeInput = $(postCodeContainer).find('input');
                const houseNumberContainer = $(`#${formPrefix}_address_house_number_field`);
                const houseNumberInput = $(houseNumberContainer).find('input');

                const postCode = $(postCodeInput).val();
                const houseNumber = $(houseNumberInput).val();
                $(postCodeContainer).removeClass('woocommerce-validated').removeClass('woocommerce-invalid');
                $(houseNumberContainer).removeClass('woocommerce-validated').removeClass('woocommerce-invalid');
                $(postCodeContainer).find('.postcode-housenr-validation-message').remove();
                $(postCodeContainer).append(`<small class="postcode-housenr-validation-message">${window.storekeeperTranslate('Validating postcode and house number. Please wait...')}</small>`);
                $.ajax({
                    url: settings.url,
                    data: {
                        postCode,
                        houseNumber
                    },
                }).done(function ({state, city, street}) {
                    window.storekeeperUnblockForm(parentForm);
                    $(`#${formPrefix}_address_1`).val(street);
                    $(`#${formPrefix}_address_1`).change();
                    $(`#${formPrefix}_city`).val(city);
                    $(`#${formPrefix}_city`).change();
                    $(postCodeContainer).addClass('woocommerce-validated');
                    $(houseNumberContainer).addClass('woocommerce-validated');
                    $(postCodeContainer).find('.postcode-housenr-validation-message').remove();
                    $(postCodeContainer).append(`<small class="postcode-housenr-validation-message" style="color: green">${window.storekeeperTranslate('Valid postcode and house number')}</small>`);
                    resolve();
                }).fail(function (xhrText, textStatus) {
                    window.storekeeperUnblockForm(parentForm);
                    $(postCodeContainer).addClass('woocommerce-invalid');
                    $(houseNumberContainer).addClass('woocommerce-invalid');
                    $(postCodeContainer).find('.postcode-housenr-validation-message').remove();
                    $(postCodeContainer).append(`<small class="postcode-housenr-validation-message" style="color: red">${window.storekeeperTranslate('Invalid postcode or house number')}</small>`);
                    reject();
                });
            });

        };
    }
    /* End of global functions */

    const prepareAddressForm = function (formPrefix) {
        const dutchPostcodeRegex = /^[1-9][0-9]{3} ?(?!sa|sd|ss)[a-z]{2}$/i;
        var form = $(`#${formPrefix}_postcode`).closest('form');
        let isValid = true;

        if ($(`#${formPrefix}_country`).val() !== 'NL') {
            $(`#${formPrefix}_address_1`).removeAttr('readonly');
            $(`#${formPrefix}_city`).removeAttr('readonly');
        }

        $(`#${formPrefix}_country`).on('change', () => {
            if ($(`#${formPrefix}_country`).val() === 'NL') {
                $(`#${formPrefix}_address_1`).attr('readonly', 'readonly');
                $(`#${formPrefix}_city`).attr('readonly', 'readonly');
            } else {
                $(`#${formPrefix}_address_1`).removeAttr('readonly');
                $(`#${formPrefix}_city`).removeAttr('readonly');
            }
        });

        $(form).submit(function (e) {
            const country = $(`#${formPrefix}_country`).val();
            if (country === 'NL' && !isValid) {
                e.preventDefault();
                $(`#${formPrefix}_postcode`).focus();
            }
        });

        const delay = function (callback, milliseconds) {
            let timer = 0;

            return function (...args) {
                clearTimeout(timer);
                timer = setTimeout(callback.bind(this, ...args), milliseconds || 0);
            }
        };

        $(`#${formPrefix}_postcode`).keyup(delay(
            function () {
                isValid = false;
                const postCodeContainer = $(`#${formPrefix}_postcode_field`);
                const postCodeInput = $(postCodeContainer).find('input');
                const houseNumberContainer = $(`#${formPrefix}_address_house_number_field`);
                const houseNumberInput = $(houseNumberContainer).find('input');
                const country = $(`#${formPrefix}_country`).val();
                const postCode = $(postCodeInput).val();
                const houseNumber = $(houseNumberInput).val();

                if (!postCode || !houseNumber) {
                    return;
                }
                if (country === 'NL') {
                    if (!dutchPostcodeRegex.test(postCode)) {
                        isValid = false;
                        $(postCodeContainer).addClass('woocommerce-invalid');
                        $(postCodeContainer).find('.postcode-housenr-validation-message').remove();
                        $(postCodeContainer).append(`<small class="postcode-housenr-validation-message" style="color: red">${window.storekeeperTranslate('Postcode format for NL address is invalid')}</small>`);
                    } else {
                        isValid = true;
                        window.storekeeperFetchAddressFromBackend(formPrefix, form).then(function () {
                            isValid = true;
                        }).catch(function () {
                            isValid = false;
                        });
                    }
                }
            },
            500
        ));

        $(`#${formPrefix}_address_house_number`).keyup(delay(
            function () {
                const postCodeContainer = $(`#${formPrefix}_postcode_field`);
                const postCodeInput = $(postCodeContainer).find('input');
                const houseNumberContainer = $(`#${formPrefix}_address_house_number_field`);
                const houseNumberInput = $(houseNumberContainer).find('input');
                const postCode = $(postCodeInput).val();
                const houseNumber = $(houseNumberInput).val();

                if (!postCode || !houseNumber) {
                    return;
                }
                isValid = false;
                window.storekeeperFetchAddressFromBackend(formPrefix, form).then(function () {
                    isValid = true;
                }).catch(function () {
                    isValid = false;
                });
            },
            500
        ));
    }

    let formPrefix = settings.addressType;
    if (!formPrefix) {
        for (let index in settings.defaultAddressTypes) {
            const formPrefix = settings.defaultAddressTypes[index];
            prepareAddressForm(formPrefix);
        }
    } else {
        // If address type is defined
        prepareAddressForm(formPrefix);
    }
});
