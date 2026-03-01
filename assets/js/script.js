document.addEventListener('DOMContentLoaded', function() {
    console.log('Скрипт загружен');

    const dropdownElementList = document.querySelectorAll('[data-bs-toggle="dropdown"]');
    console.log('Найдено dropdown элементов:', dropdownElementList.length);

    const dropdownList = [...dropdownElementList].map(dropdownToggleEl => {
        console.log('Инициализация dropdown:', dropdownToggleEl);
        return new bootstrap.Dropdown(dropdownToggleEl);
    });

    const addToCartButtons = document.querySelectorAll('.add-to-cart-btn');
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            addToCart(productId);
        });
    });
});

function addToCart(productId) {
    let quantity = 1;
    const quantityInput = document.getElementById('quantity');
    if (quantityInput) {
        quantity = parseInt(quantityInput.value) || 1;
    }

    fetch('cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `add_to_cart=1&product_id=${productId}&quantity=${quantity}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            updateCartIconBadge(data.total_items);
            showNotification('Товар добавлен в корзину!');
        } else if (data.status === 'error' && data.message) {
            showNotificationError(data.message);
            setTimeout(() => {
                window.location.href = 'account.php';
            }, 1500);
        } else {
            console.error('Ошибка при добавлении товара в корзину:', data.message);
        }
    })
    .catch(error => {
        console.error('Ошибка при добавлении товара в корзину:', error);
    });
}

function updateCartIconBadge(totalItems) {
    const badge = document.getElementById('cart-badge');
    if (badge) {
        badge.textContent = totalItems;
        badge.className = totalItems > 0 ? 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger' : 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none';
    }
}

function showNotification(message) {
    const notification = document.createElement('div');
    notification.className = 'alert alert-success position-fixed';
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 250px;';
    notification.textContent = message;
    document.body.appendChild(notification);

    setTimeout(() => {
        document.body.removeChild(notification);
    }, 3000);
}

function showNotificationError(message) {
    const notification = document.createElement('div');
    notification.className = 'alert alert-danger position-fixed';
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 250px;';
    notification.textContent = message;
    document.body.appendChild(notification);

    setTimeout(() => {
        document.body.removeChild(notification);
    }, 3000);
}

function updateQuantity(productId, change) {
    const input = document.querySelector(`input[name='quantities[${productId}]']`);
    const currentQty = parseInt(input.value);
    let newQty = currentQty + change;

    if (newQty < 1) {
        newQty = 1;
    }

    input.value = newQty;

    updateItemTotal(productId, newQty);

    updateCartTotal();
}

function updateItemTotal(productId, quantity) {
    const row = document.querySelector(`input[name='quantities[${productId}]']`).closest('tr');
    const priceText = row.cells[1].textContent;
    const price = parseFloat(priceText.replace(/[^\d.-]/g, ''));
    const total = price * quantity;

    const totalCell = row.querySelector('.item-total');
    totalCell.textContent = total.toFixed(2) + ' ₽';
}

function updateCartTotal() {
    let grandTotal = 0;

    document.querySelectorAll('.item-total').forEach(function(itemTotal) {
        const itemPriceText = itemTotal.textContent;
        const itemPrice = parseFloat(itemPriceText.replace(/[^\d.-]/g, ''));
        grandTotal += itemPrice;
    });

    document.querySelector('.cart-total').textContent = grandTotal.toFixed(2) + ' ₽';
}
