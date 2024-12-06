document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('wishlist-modal');

    if (!modal) {
        return;
    }

    var createWishlistLink = document.querySelector('a[href*="create_wishlist=true"]');
    var closeModalBtn = document.getElementsByClassName('wishlist-close-btn')[0];

    if (createWishlistLink) {
        createWishlistLink.addEventListener('click', function(event) {
            event.preventDefault();
            modal.style.display = 'block';
        });
    }

    if (closeModalBtn) {
        closeModalBtn.onclick = function() {
            modal.style.display = 'none';
        };
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    };

    const dropdown = document.querySelector('.custom-dropdown');
    const button = dropdown.querySelector('.dropdown-button');
    const menu = dropdown.querySelector('.dropdown-menu');
    const search = dropdown.querySelector('.dropdown-search');
    const items = dropdown.querySelectorAll('.dropdown-list li');

    button.addEventListener('click', function () {
        console.log(33);
        dropdown.classList.toggle('active');
    });
    document.addEventListener('click', function (e) {
        if (!dropdown.contains(e.target)) {
            dropdown.classList.remove('active');
        }
    });
    search.addEventListener('input', function () {
        const query = search.value.toLowerCase();
        items.forEach(function (item) {
            if (item.textContent.toLowerCase().includes(query)) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    });
    items.forEach(function (item) {
        item.addEventListener('click', function () {
            button.textContent = item.textContent;
            dropdown.classList.remove('active');
        });
    });
});

document.addEventListener("DOMContentLoaded", function() {
    var modal = document.getElementById("new-wishlist-modal");
    var addButton = document.querySelector(".add-new-wishlist");
    var closeButton = document.querySelector(".wishlist-close");
    addButton.addEventListener("click", function() {
        modal.style.display = "block";
    });
    closeButton.addEventListener("click", function() {
        modal.style.display = "none";
    });
    window.addEventListener("click", function(event) {
        if (event.target === modal) {
            modal.style.display = "none";
        }
    });
});

jQuery(document).ready(function ($) {
    $('.delete-button').on('click', function (e) {
        e.preventDefault(); // Prevent default button behavior
        const tooltip = $(this).siblings('.tooltip-container');
        tooltip.addClass('active');
    });

    $('.tooltip-option.yes').on('click', function () {
        const wishlistId = $(this).closest('.wishlist-item').find('.delete-button').data('wishlist-id');
        const listItem = $(this).closest('.wishlist-item');
        const tooltip = $(this).closest('.tooltip-container');

        $.ajax({
            url: ajax_obj.admin_url,
            type: 'POST',
            data: {
                action: 'delete_wishlist_item',
                wishlist_id: wishlistId,
            },
            success: function (response) {
                if (response.success) {
                    listItem.fadeOut(function () {
                        $(this).remove();
                    });
                } else {
                    const message = response.success
                        ? ajax_object.translations.wishlist_not_deleted
                        : (response.data?.message || ajax_object.translations.wishlist_not_deleted);

                    const errorMessageDiv = $('.error-message p');
                    errorMessageDiv.text(message);
                    $('.error-message').fadeIn();
                }
            },
            error: function () {
                const message = response.success
                    ? ajax_object.translations.ajax_failed
                    : (response.data?.message || ajax_object.translations.ajax_failed);

                const errorMessageDiv = $('.error-message p');
                errorMessageDiv.text(message);
                $('.error-message').fadeIn();
            },
            complete: function () {
                tooltip.removeClass('active');
            }
        });
    });

    $('.tooltip-option.no').on('click', function () {
        const tooltip = $(this).closest('.tooltip-container');
        tooltip.removeClass('active');
    });

    var searchInput = $('.product-search-input');
    var dropdownList = $('.dropdown-list-search');

    if (searchInput.length === 0 || dropdownList.length === 0) {
        return;
    }

    searchInput.on('input', function() {
        const searchTerm = searchInput.val().trim();
        if (searchTerm.length >= 2) {
            $.ajax({
                url: ajax_object.admin_url,
                method: 'POST',
                data: {
                    action: 'search_products_by_sku_name',
                    search_term: searchTerm,
                },
                success: function(response) {
                    dropdownList.empty();
                    if (response.success && response.data.products.length > 0) {
                        $.each(response.data.products, function(index, product) {
                            const $li = $('<li>').addClass('order-item')
                                .attr('data-product-id', product.id)
                                .attr('data-variation-id', product.variation_id || 0)
                                .html(`
                                <img src="${product.image}" alt="${product.name}" style="max-width: 50px; height: auto; margin-right: 10px;">
                                #${product.sku} - ${product.name} - <strong>${product.price}</strong>
                            `)
                                .css({
                                    'margin-bottom': '10px',
                                    'padding': '10px',
                                    'border-bottom': '1px solid #E0E8EC',
                                    'display': 'flex',
                                    'align-items': 'center',
                                })
                                .on('click', function() {
                                    var productId = $(this).attr('data-product-id');
                                    var wishlistId = $('#wishlist_id').val();

                                    $.ajax({
                                        url: ajax_object.admin_url,
                                        method: 'POST',
                                        data: {
                                            action: 'add_product_to_wishlist',
                                            wishlist_id: wishlistId,
                                            product_id: productId,
                                            qty: 1,
                                            wishlist_nonce: ajax_object.wishlistNonce
                                        },
                                        success: function(response) {
                                            if (response.success) {
                                                const message = response.success
                                                    ? ajax_object.translations.product_added_to_wishlist
                                                    : (response.data?.message || ajax_object.translations.product_added_to_wishlist);

                                                const successMessageDiv = $('.success-message p');
                                                successMessageDiv.text(message);
                                                $('.success-message').fadeIn();

                                                setTimeout(function () {
                                                    $('.success-message').fadeOut();
                                                }, 5000);
                                                location.reload();
                                            } else {
                                                const message = response.success
                                                    ? ajax_object.translations.product_added_to_wishlist_failed
                                                    : (response.data?.message || ajax_object.translations.product_added_to_wishlist_failed);

                                                const errorMessageDiv = $('.error-message p');
                                                errorMessageDiv.text(message);
                                                $('.error-message').fadeIn();

                                                setTimeout(function () {
                                                    $('.error-message').fadeOut();
                                                }, 5000);
                                            }
                                        },
                                        error: function(error) {
                                            const message = ajax_object.translations.product_added_to_wishlist_failed;

                                            const errorMessageDiv = $('.error-message p');
                                            errorMessageDiv.text(message);
                                            $('.error-message').fadeIn();

                                            setTimeout(function () {
                                                $('.error-message').fadeOut();
                                            }, 5000);
                                        }
                                    });
                                });

                            dropdownList.append($li);
                        });
                    } else {
                        dropdownList.html('<li>No results found</li>');
                    }
                },
            });
        } else {
            dropdownList.empty();
        }
    });

    $('.minus-icon, .plus-icon').on('click', function() {
        var productId = $(this).data('product-id');
        var variationId = $(this).data('variation-id') || 0;
        var action = $(this).data('action');
        var currentQty = parseInt($(this).siblings('.qty-value').text());
        var newQty = action === 'increase' ? currentQty + 1 : currentQty - 1;

        if (newQty < 1) return;

        var qtyElement = $(this).siblings('.qty-value');

        $.ajax({
            url: ajax_object.admin_url,
            method: 'POST',
            data: {
                action: 'update_wishlist_quantity',
                product_id: productId,
                variation_id: variationId,
                qty: newQty
            },
            success: function(response) {
                if (response.success) {
                    qtyElement.text(newQty);
                } else {
                    const message = response.success
                        ? ajax_object.translations.failed_quantity_update
                        : (response.data?.message || ajax_object.translations.failed_quantity_update);

                    const errorMessageDiv = $('.error-message p');
                    errorMessageDiv.text(message);
                    $('.error-message').fadeIn();

                    setTimeout(function () {
                        $('.error-message').fadeOut();
                    }, 5000);
                }
            },
            error: function() {
                const message = ajax_object.translations.error_occurred;

                const errorMessageDiv = $('.error-message p');
                errorMessageDiv.text(message);
                $('.error-message').fadeIn();

                setTimeout(function () {
                    $('.error-message').fadeOut();
                }, 5000);
            }
        });
    });

    $('.store-act').on('click', function() {
        var wishlistId = $('#wishlist_id').val();
        var products = [];

        $('.wishlist-item-orderlist').each(function() {
            var productId = $(this).data('product-id');
            var variationId = $(this).find('.variation_id').val() || 0;
            var qty = parseInt($(this).find('.qty-value').text(), 10);

            if (productId > 0 && qty > 0) {
                products.push({
                    product_id: productId,
                    variation_id: variationId,
                    qty: qty
                });
            }
        });

        $.ajax({
            url: ajax_object.admin_url,
            method: 'POST',
            data: {
                action: 'store_wishlist',
                wishlist_id: wishlistId,
                products: products
            },
            success: function(response) {
                if (response.success) {
                    const message = response.success
                        ? ajax_object.translations.wishlist_saved
                        : (response.data?.message || ajax_object.translations.wishlist_saved);

                    const successMessageDiv = $('.success-message p');
                    successMessageDiv.text(message);
                    $('.success-message').fadeIn();

                    setTimeout(function () {
                        $('.success-message').fadeOut();
                    }, 5000);
                } else {
                    const message = response.success
                        ? ajax_object.translations.error_occurred
                        : (response.data?.message || ajax_object.translations.error_occurred);

                    const errorMessageDiv = $('.error-message p');
                    errorMessageDiv.text(message);
                    $('.error-message').fadeIn();

                    setTimeout(function () {
                        $('.error-message').fadeOut();
                    }, 5000);
                }
            },
            error: function() {
                const message = ajax_object.translations.error_occurred;
                const errorMessageDiv = $('.error-message p');
                errorMessageDiv.text(message);
                $('.error-message').fadeIn();

                setTimeout(function () {
                    $('.error-message').fadeOut();
                }, 5000);
            }
        });
    });


    $('.cart-act').on('click', function() {
        var wishlistId = $('#wishlist_id').val();

        var products = [];
        $('.wishlist-item-orderlist').each(function() {
            var productId = $(this).data('product-id');
            var variationId = $(this).data('variation-id') || 0;
            var qty = $(this).find('.qty-value').text().trim();
            qty = parseInt(qty, 10);

            if (qty > 0) {
                products.push({
                    product_id: productId,
                    variation_id: variationId,
                    qty: qty
                });
            }
        });

        $.ajax({
            url: ajax_object.admin_url,
            method: 'POST',
            data: {
                action: 'add_products_to_cart',
                wishlist_id: wishlistId,
                products: products
            },
            success: function(response) {
                if (response.success) {
                    window.location.href = response.data.cart_url || ajax_object.cart_url;
                } else {
                    const message = response.success
                        ? ajax_object.translations.error_occurred
                        : (response.data?.message || ajax_object.translations.error_occurred);

                    const errorMessageDiv = $('.error-message p');
                    errorMessageDiv.text(message);
                    $('.error-message').fadeIn();

                    setTimeout(function () {
                        $('.error-message').fadeOut();
                    }, 5000);
                }
            },
            error: function() {
                const message = ajax_object.translations.error_occurred;

                const errorMessageDiv = $('.error-message p');
                errorMessageDiv.text(message);
                $('.error-message').fadeIn();

                setTimeout(function () {
                    $('.error-message').fadeOut();
                }, 5000);
            }
        });
    });

    $('.wishlist-item-delete-product').on('click', function (e) {
        e.preventDefault();

        const tooltip = $(this).siblings('.tooltip-container-product');
        tooltip.addClass('active');
    });

    // Handle "Yes" button click
    $('.tooltip-option-product.yes').on('click', function () {
        const wishlistItem = $(this).closest('.wishlist-item-orderlist');
        const wishlistId = $('#wishlist_id').val();
        const productId = wishlistItem.find('.delete-button-product').data('product-id');
        const variationId = wishlistItem.find('.delete-button-product').data('variation-id') || 0;

        $.ajax({
            url: ajax_obj.admin_url,
            type: 'POST',
            data: {
                action: 'delete_product_from_wishlist',
                wishlist_id: wishlistId,
                product_id: productId,
                variation_id: variationId,
                wishlist_nonce: ajax_obj.wishlist_nonce,
            },
            success: function (response) {
                if (response.success) {
                    wishlistItem.fadeOut(function () {
                        $(this).remove();
                    });
                } else {
                    const message = response.success
                        ? ajax_object.translations.error_occurred
                        : (response.data?.message || ajax_object.translations.error_occurred);

                    const errorMessageDiv = $('.error-message p');
                    errorMessageDiv.text(message);
                    $('.error-message').fadeIn();

                    setTimeout(function () {
                        $('.error-message').fadeOut();
                    }, 5000);
                }
            },
            error: function () {
                const message = ajax_object.translations.ajax_failed;

                const errorMessageDiv = $('.error-message p');
                errorMessageDiv.text(message);
                $('.error-message').fadeIn();

                setTimeout(function () {
                    $('.error-message').fadeOut();
                }, 5000);
            }
        });
    });



    // Handle "No" button click
    $('.tooltip-option-product.no').on('click', function () {
        const tooltip = $(this).closest('.tooltip-container-product');
        tooltip.removeClass('active'); // Hide the tooltip
    });
});

