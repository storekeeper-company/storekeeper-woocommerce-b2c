jQuery(document).ready(function($) {
    function adjustPrice(quantity, productId) {
        $.ajax({
            url: ajax_obj.ajax_url,
            method: 'POST',
            data: {
                action: 'adjust_price_based_on_quantity',
                quantity: quantity,
                product_id: productId
            },
            success: function(response) {
                if (response.success && response.data.new_price) {
                    $('.price').html(response.data.new_price);
                } else {
                    console.log('Error: ', response.data.message);
                }
            },
            error: function(response) {
                console.log('AJAX request failed', response);
            }
        });
    }

    const initialQuantity = $('input[name="quantity"]').val();
    let productId;

    // Detect product ID for both simple and variable products
    if ($('button[name="add-to-cart"]').length) {
        productId = $('button[name="add-to-cart"]').attr('value');
    }
    if ($('input[name="add-to-cart"]').length) {
        productId = $('input[name="add-to-cart"]').val();
    }

    // Load initial price for simple products
    if (productId && initialQuantity) {
        adjustPrice(initialQuantity, productId);
    }

    // Adjust price when the quantity is changed by the user
    $('input[name="quantity"]').on('input', function() {
        const quantity = $(this).val();
        if ($('form.variations_form').length > 0) {
            const variationId = $('input[name="variation_id"]').val();

            if (variationId) {
                productId = variationId;
            }
        }
        if (productId) {
            adjustPrice(quantity, productId);
        }
    });

    // Display segment prices for simple products on initial load
    if (typeof segmentPricesData !== 'undefined' && productId) {
        if (segmentPricesData.segmentPricesData[productId]) {
            $('.segment-prices-table tbody').empty();
            segmentPricesData.segmentPricesData[productId].forEach(function(price) {
                $('.segment-prices-table tbody').append(
                    '<tr>' +
                    '<td align="center">' + price.from_qty + '</td>' +
                    '<td align="center">' + price.ppu_wt + '</td>' +
                    '</tr>'
                );
            });
        } else {
            $('.segment-prices-table tbody').append(
                '<tr><td colspan="2" align="center">' + segmentPricesData.noSegmentPricesMessage + '</td></tr>'
            );
        }
    }

    // Adjust price and segment prices for variable products on variation selection
    $('form.variations_form').on('found_variation', function(event, variation) {
        var variationId = variation.variation_id;
        $('.segment-prices-table tbody').empty();

        if (segmentPricesData.segmentPricesData[variationId]) {
            segmentPricesData.segmentPricesData[variationId].forEach(function(price) {
                $('.segment-prices-table tbody').append(
                    '<tr>' +
                    '<td align="center">' + price.from_qty + '</td>' +
                    '<td align="center">' + price.ppu_wt + '</td>' +
                    '</tr>'
                );
            });
        } else {
            $('.segment-prices-table tbody').append(
                '<tr><td colspan="2" align="center">' + segmentPricesData.noSegmentPricesVariationMessage + '</td></tr>'
            );
        }
    });

    // Clear the segment prices table when no variation is selected
    $('form.variations_form').on('reset_data', function() {
        $('.segment-prices-table tbody').empty();
        $('.segment-prices-table tbody').append(
            '<tr><td colspan="2" align="center">' + segmentPricesData.selectVariationMessage + '</td></tr>'
        );
    });

    if (typeof productData !== 'undefined') {
        var productPrice = parseFloat(productData.price);
        var productQuantity = productData.quantity;

        $('input[name="quantity"]').val(productQuantity);
        function updatePrice() {
            var quantity = parseInt($('input[name="quantity"]').val(), 10);
            if (!isNaN(quantity)) {
                var totalPrice = productPrice * quantity;
                $('.product-price .amount').text('$' + totalPrice.toFixed(2));
            }
        }

        updatePrice();

        $('input[name="quantity"]').on('change', function() {
            updatePrice();
        });
    }
});