function escapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

jQuery(document).ready(function ($) {
    $('.upload-image-btn').click(function (e) {
        e.preventDefault();
        var currentOptionId = $(this).data('option-id');
        var currentInputId = '#uploaded_image_url_' + currentOptionId;
        var validationMessageId = '#validation-message-' + currentOptionId;
        var productId = $('#product-id').val();
        $('#image-upload-popup').fadeIn();
        var currentImageUrl = $(currentInputId).val();

        if (currentImageUrl) {
            $('#preview-img').attr('src', currentImageUrl);
            $('#image-preview').show();
        } else {
            $('#image-preview').hide();
        }

        $('#upload-image-btn-popup').off('click').on('click', function () {
            var fileInput = $('#image-upload-input')[0];
            var file = fileInput.files[0];

            if (!file) {
                alert(ajax_object.translations.please_select_image);
                return;
            }

            var minHeight = parseInt($('#image_min_h').val(), 10);
            var minWidth = parseInt($('#image_min_w').val(), 10);
            var maxHeight = parseInt($('#image_max_h').val(), 10);
            var maxWidth = parseInt($('#image_max_w').val(), 10);

            var img = new Image();
            img.src = URL.createObjectURL(file);

            img.onload = function () {
                var validationMessage = '';

                if (img.height < minHeight || img.width < minWidth) {
                    validationMessage = ajax_object.translations.image_too_small
                        .replace('{width}', minWidth)
                        .replace('{height}', minHeight);
                }
                if (img.height > maxHeight || img.width > maxWidth) {
                    validationMessage = ajax_object.translations.image_too_large
                        .replace('{width}', maxWidth)
                        .replace('{height}', maxHeight);
                }

                if (validationMessage) {
                    $(validationMessageId).html(`<span style="color: red;">${escapeHtml(validationMessage)}</span>`);
                    return;
                }

                var formData = new FormData();
                formData.append('file', file);
                formData.append('product_id', productId);
                formData.append('option_id', currentOptionId);
                formData.append('action', 'upload_product_image');
                formData.append('nonce', ajax_object.nonce);

                $.ajax({
                    url: ajax_object.ajax_url,
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function (response) {
                        if (response.success) {
                            $(currentInputId).val(response.data.url);
                            $('#preview-img').attr('src', response.data.url);
                            $('#image-preview').show();

                            var fileUrl = response.data.url;
                            var filename = fileUrl.split('/').pop();
                            $('#image-preview-' + currentOptionId)
                                .attr('href', response.data.url)
                                .text(escapeHtml(filename));

                            $('#image-upload-input').val('');

                            $('#success-message')
                                .text(escapeHtml(ajax_object.translations.upload_success))
                                .fadeIn();
                            setTimeout(function () {
                                $('#image-upload-popup').fadeOut();
                                $('#success-message').fadeOut();
                            }, 2000);
                        } else {
                            const escapedMessage = escapeHtml(response.data.message);
                            $(validationMessageId).html(`<span style="color: red;">${escapedMessage}</span>`);
                        }
                    },
                    error: function () {
                        $(validationMessageId).html(`<span style="color: red;">${escapeHtml(ajax_object.translations.upload_error)}</span>`);
                    }
                });
            };
        });

        $('#image-upload-input').val('');
        $(validationMessageId).html('');
    });

    $('#image-upload-input').change(function () {
        var currentOptionId = $('.upload-image-btn').data('option-id');
        var file = this.files[0];
        if (file) {
            var fileUrl = URL.createObjectURL(file);
            $('#preview-img').attr('src', fileUrl);
            $('#image-preview').show();
        } else {
            $('#image-preview').hide();
        }
    });

    $('.popup-close').click(function () {
        $('#image-upload-popup').fadeOut();
    });

    const imageCheckbox = jQuery('#agree-images');
    const textCheckbox = jQuery('#agree-text');
    const imageOptionsList = jQuery('.addon-image-optional ul');
    const textOptionsList = jQuery('.addon-text-optional ul');
    const allOptionsList = imageOptionsList.add(textOptionsList);

    allOptionsList.hide();
    imageCheckbox.on('change', function () {
        imageOptionsList.toggle(this.checked);
    });

    textCheckbox.on('change', function () {
        textOptionsList.toggle(this.checked);
    });
});