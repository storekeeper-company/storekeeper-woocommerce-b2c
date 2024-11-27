jQuery(document).ready(function ($) {
    // Open the upload image popup
    $('.upload-image-btn').click(function (e) {
        e.preventDefault();

        // Get the current option ID
        var currentOptionId = $(this).data('option-id');
        var currentInputId = '#uploaded_image_url_' + currentOptionId; // Hidden input
        var validationMessageId = '#validation-message-' + currentOptionId; // Validation message container
        var productId = $('#product-id').val(); // Product ID

        // Show the popup
        $('#image-upload-popup').fadeIn();

        // Clear previous handlers for the upload button
        $('#upload-image-btn-popup').off('click').on('click', function () {
            var fileInput = $('#image-upload-input')[0];
            var file = fileInput.files[0];

            if (!file) {
                alert('Please select an image first.');
                return;
            }

            // Validate image dimensions
            var minHeight = parseInt($('#image_min_h').val(), 10);
            var minWidth = parseInt($('#image_min_w').val(), 10);
            var maxHeight = parseInt($('#image_max_h').val(), 10);
            var maxWidth = parseInt($('#image_max_w').val(), 10);

            var img = new Image();
            img.src = URL.createObjectURL(file);

            img.onload = function () {
                var validationMessage = '';

                // Check image dimensions
                if (img.height < minHeight || img.width < minWidth) {
                    validationMessage = `Image is too small. Minimum dimensions are ${minWidth}x${minHeight}.`;
                }
                if (img.height > maxHeight || img.width > maxWidth) {
                    validationMessage = `Image is too large. Maximum dimensions are ${maxWidth}x${maxHeight}.`;
                }

                console.log(validationMessage);
                // If validation fails, show the message and return
                if (validationMessage) {
                    $(validationMessageId).html(`<span style="color: red;">${validationMessage}</span>`);
                    return;
                }

                // Proceed with the upload
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
                            // Update the hidden input field and preview
                            $(currentInputId).val(response.data.url);

                            // Reset the file input and validation message
                            $('#image-upload-input').val('');

                            // Show a success message in the popup
                            $('#success-message').text('Image uploaded successfully!').fadeIn();

                            // Close the popup after a short delay
                            setTimeout(function () {
                                $('#image-upload-popup').fadeOut();
                                $('#success-message').fadeOut();
                            }, 2000);
                        } else {
                            $(validationMessageId).html(`<span style="color: red;">${response.data.message}</span>`);
                        }
                    },
                    error: function () {
                        $(validationMessageId).html('<span style="color: red;">An error occurred while uploading the image.</span>');
                    }
                });
            };
        });

        // Clear the file input, preview, and validation message
        $('#image-upload-input').val('');
        $(validationMessageId).html('');
    });

    // Close the popup when clicking on the close button or outside the popup
    $('.popup-close').click(function () {
        $('#image-upload-popup').fadeOut();
    });

    $(window).click(function (event) {
        if ($(event.target).is('#image-upload-popup')) {
            $('#image-upload-popup').fadeOut();
        }
    });

    const $addToCartButton = $('.single_add_to_cart_button'); // Adjust the selector as needed

    // Function to check if all required inputs are filled
    function updateAddToCartState() {
        let isDisabled = false;

        // Check all inputs with class "required"
        $('.required').each(function() {
            if (!$(this).val().trim()) {
                isDisabled = true; // Disable button if any required input is empty
            }
        });

        // Enable or disable the Add to Cart button
        $addToCartButton.prop('disabled', isDisabled);
    }

    // Simulate image upload and update input value
    $('.upload-image-btn').on('click', function() {
        const optionId = $(this).data('option-id');
        const $inputField = $('#uploaded_image_url_' + optionId);
        const $validationMessage = $('#validation-message-' + optionId);

        // Simulate image upload (this could be replaced with actual image upload logic)
        setTimeout(function() {
            $inputField.val('uploaded_image_' + optionId + '.jpg'); // Simulated uploaded image URL

            // Clear any validation message
            $validationMessage.text('');

            // Update Add to Cart button state
            updateAddToCartState();
        }, 1000); // Simulate a delay (1 second) for the image upload
    });

    // Initial state check on page load
    updateAddToCartState();
});
