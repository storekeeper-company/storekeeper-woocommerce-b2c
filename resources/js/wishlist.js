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
    $('.delete-button').on('click', function () {
        const wishlistId = $(this).data('wishlist-id');
        const listItem = $(this).closest('.wishlist-item');
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
                    alert('Error: ' + (response.data.message || 'Could not delete wishlist.'));
                }
            },
            error: function () {
                alert('AJAX request failed.');
            }
        });
    });
});
