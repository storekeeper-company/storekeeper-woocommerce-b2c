document.addEventListener('DOMContentLoaded', function () {
    const dropdown = document.querySelector('.custom-dropdown');
    const button = dropdown.querySelector('.dropdown-button');
    const menu = dropdown.querySelector('.dropdown-menu');
    const search = dropdown.querySelector('.dropdown-search');
    const items = dropdown.querySelectorAll('.dropdown-list li');

    button.addEventListener('click', () => dropdown.classList.toggle('active'));

    document.addEventListener('click', (e) => {
        if (!dropdown.contains(e.target)) dropdown.classList.remove('active');
    });

    search.addEventListener('input', function () {
        const query = search.value.toLowerCase();
        items.forEach(item => item.style.display = item.textContent.toLowerCase().includes(query) ? '' : 'none');
    });

    items.forEach(item => {
        item.addEventListener('click', function () {
            button.textContent = item.textContent;
            dropdown.classList.remove('active');
        });
    });

    jQuery(document).ready(function ($) {
        $('.order-item').on('click', function () {
            console.log(3)
            const orderId = $(this).data('order-id');
            const wishlistId = $('#wishlist_id').val();
            $.ajax({
                url: ajax_obj.admin_url,
                type: 'POST',
                data: {
                    action: 'add_order_products_to_wishlist',
                    order_id: orderId,
                    wishlist_id: wishlistId,
                },
                success: function (response) {
                    alert(response.success ? 'Products added to wishlist.' : (response.data.message || 'Something went wrong.'));
                },
                error: function () {
                    alert('AJAX request failed.');
                }
            });
        });
    });
});