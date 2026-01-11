document.addEventListener('DOMContentLoaded', function() {
    initializeCart();

    setupEventListeners();

    updateCartCounter();
});

function initializeCart() {
    updateCheckoutButton();
}

function setupEventListeners() {
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('quantity-btn')) {
            handleQuantityButtonClick(e);
        }

        if (e.target.classList.contains('plus')) {
            handlePlusClick(e);
        }

        if (e.target.classList.contains('minus')) {
            handleMinusClick(e);
        }

        if (e.target.classList.contains('update_cart_button')) {
            setTimeout(updateCartCounter, 1000);
        }

        if (e.target.classList.contains('add-to-cart-btn')) {
            handleAddToCart(e);
        }

        if (e.target.classList.contains('cart_icon') || e.target.closest('.cart_icon')) {
            handleCartIconClick(e);
        }
    });

    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('node_quantity')) {
            handleQuantityInput(e);
        }
    });
}

function handleQuantityButtonClick(e) {
    e.preventDefault();
    const button = e.target;
    const nodeItem = button.closest('.node_item');
    const quantity = parseInt(button.dataset.quantity);
    const quantityInput = nodeItem.querySelector('.node_quantity');

    nodeItem.querySelectorAll('.quantity-btn').forEach(btn => {
        btn.classList.remove('active');
    });

    button.classList.add('active');

    quantityInput.value = quantity;
}

function handlePlusClick(e) {
    const quantityInput = e.target.parentElement.querySelector('.node_quantity');
    let currentValue = parseInt(quantityInput.value) || 1;
    quantityInput.value = currentValue + 1;

    const nodeItem = e.target.closest('.node_item');
    nodeItem.querySelectorAll('.quantity-btn').forEach(btn => {
        btn.classList.remove('active');
    });
}

function handleMinusClick(e) {
    const quantityInput = e.target.parentElement.querySelector('.node_quantity');
    let currentValue = parseInt(quantityInput.value) || 1;
    if (currentValue > 1) {
        quantityInput.value = currentValue - 1;

        const nodeItem = e.target.closest('.node_item');
        nodeItem.querySelectorAll('.quantity-btn').forEach(btn => {
            btn.classList.remove('active');
        });
    }
}

function handleQuantityInput(e) {
    const input = e.target;
    let value = parseInt(input.value);

    if (isNaN(value) || value < 1) {
        input.value = 1;
    }

    const nodeItem = input.closest('.node_item');
    nodeItem.querySelectorAll('.quantity-btn').forEach(btn => {
        btn.classList.remove('active');

        if (parseInt(btn.dataset.quantity) === parseInt(input.value)) {
            btn.classList.add('active');
        }
    });
}

function handleAddToCart(e) {
    e.preventDefault();
    const button = e.target;
    const nodeItem = button.closest('.node_item');
    const productId = nodeItem.dataset.productId;
    const quantity = parseInt(nodeItem.querySelector('.node_quantity').value) || 1;

    button.disabled = true;

    jQuery.post(custom_cart_ajax.ajax_url, {
        action: 'add_to_cart_custom',
        product_id: productId,
        quantity: quantity,
        nonce: custom_cart_ajax.nonce
    })
        .done(function(response) {
            if (response.success) {
                showNotification('Success', 'success');

                updateCartCounter();

                updateCheckoutButton();
            } else {
                showNotification(response.data.message || 'Error', 'error');
            }
        })
        .fail(function() {
            showNotification('Network error', 'error');
        })
        .always(function() {
            button.disabled = false;
        });
}

function handleCartIconClick(e) {
    e.preventDefault();
    window.location.href = custom_cart_ajax.cart_url;
}

function updateCartCounter() {
    jQuery.post(custom_cart_ajax.ajax_url, {
        action: 'get_cart_count'
    })
        .done(function(response) {
            if (response.success) {
                const cartCounter = document.querySelector('.items_in_cart');
                if (cartCounter) {
                    cartCounter.textContent = response.data.cart_count;

                    if (response.data.cart_count > 0) {
                        cartCounter.style.display = 'flex';
                    } else {
                        cartCounter.style.display = 'none';
                    }
                }
            }
        });
}

function updateCheckoutButton() {
    if (typeof jQuery === 'undefined' || typeof custom_cart_ajax === 'undefined') {
        console.error('jQuery or custom_cart_ajax is not available');
        return;
    }

    const checkoutBtn = document.getElementById('checkout-btn');
    if (checkoutBtn) {
        jQuery.post(custom_cart_ajax.ajax_url, {
            action: 'get_cart_count'
        })
            .done(function(response) {
                if (response.success && response.data.cart_count > 0) {
                    checkoutBtn.classList.remove('disabled');
                    checkoutBtn.href = custom_cart_ajax.checkout_url;
                } else {
                    checkoutBtn.classList.add('disabled');
                    checkoutBtn.href = '#';
                }
            })
            .fail(function(xhr, status, error) {
                console.error('Error updating checkout button:', error);
            });
    }
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `custom-notification custom-notification-${type}`;
    notification.textContent = message;

    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#17a2b8'};
        color: white;
        padding: 12px 20px;
        border-radius: 4px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        z-index: 9999;
        font-size: 14px;
        max-width: 300px;
        animation: slideIn 0.3s ease;
    `;

    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    `;
    document.head.appendChild(style);

    document.body.appendChild(notification);

    // Автоматическое удаление через 3 секунды
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

////////////////////////////////
jQuery(document).ready(function($) {

    // Select all renewals
    $('#select-all-renewals').on('click', function(e) {
        e.preventDefault();
        $('input[name="renewal_products[]"]').prop('checked', true);
    });

    // Deselect all renewals
    $('#deselect-all-renewals').on('click', function(e) {
        e.preventDefault();
        $('input[name="renewal_products[]"]').prop('checked', false);
    });

    // Handle form submission
    $('#renewal-form-submit').on('submit', function(e) {
        e.preventDefault();

        let selectedProducts = [];

        // Collect selected products and their data
        $('input[name="renewal_products[]"]:checked').each(function() {
            let uniqueKey = $(this).val();
            selectedProducts.push(uniqueKey);
        });

        if (selectedProducts.length === 0) {
            alert('Select at least one node');
            return;
        }

        // Disable submit button
        let submitButton = $('#add-renewals-to-cart');
        let originalText = submitButton.text();
        submitButton.prop('disabled', true).text('Adding...');

        // Send AJAX request
        $.ajax({
            url: custom_cart_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'add_renewal_products_to_cart',
                product_ids: selectedProducts,
                nonce: custom_cart_ajax.renewal_nonce
            },
            success: function(response) {
                if (response.success) {
                    //$('input[name="renewal_products[]"]:checked').prop('checked', false);
                    window.location.href = custom_cart_ajax.cart_url;
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Error');
            },
            complete: function() {
                // Re-enable submit button
                submitButton.prop('disabled', false).text(originalText);
            }
        });
    });
});