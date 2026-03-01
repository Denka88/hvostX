<?php
// /cart.php
require_once 'includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: account.php");
    exit;
}

if (isset($_GET['add']) || isset($_POST['add_to_cart'])) {
    if (!isset($_SESSION['user_id'])) {
        if (isset($_POST['add_to_cart'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => 'Необходимо авторизоваться для добавления товаров в корзину'
            ]);
            exit;
        }
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header("Location: account.php");
        exit;
    }
}

if (isset($_POST['add_to_cart']) && isset($_POST['product_id'])) {
    $product_id = (int)$_POST['product_id'];
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

    $query = "SELECT * FROM products WHERE id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();

    if ($product) {
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]['quantity'] += $quantity;
        } else {
            $_SESSION['cart'][$product_id] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'price' => $product['price'],
                'image' => $product['image'],
                'quantity' => $quantity
            ];
        }
    }

    $total_items = array_sum(array_column($_SESSION['cart'], 'quantity'));

    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'message' => 'Товар добавлен в корзину',
        'total_items' => $total_items
    ]);
    exit;
}

if (isset($_GET['add']) && is_numeric($_GET['add'])) {
    $product_id = (int)$_GET['add'];

    $query = "SELECT * FROM products WHERE id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();

    if ($product) {
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]['quantity']++;
        } else {
            $_SESSION['cart'][$product_id] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'price' => $product['price'],
                'image' => $product['image'],
                'quantity' => 1
            ];
        }
    }

    header("Location: cart.php");
    exit;
}

if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $product_id = (int)$_GET['remove'];
    if (isset($_SESSION['cart'][$product_id])) {
        unset($_SESSION['cart'][$product_id]);
    }
    header("Location: cart.php");
    exit;
}

if (isset($_POST['remove']) && is_numeric($_POST['remove'])) {
    $product_id = (int)$_POST['remove'];
    if (isset($_SESSION['cart'][$product_id])) {
        unset($_SESSION['cart'][$product_id]);
    }
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);
    exit;
}

if (isset($_POST['update_cart'])) {
    foreach ($_POST['quantities'] as $product_id => $quantity) {
        $quantity = (int)$quantity;
        if ($quantity > 0 && isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]['quantity'] = $quantity;
        } elseif ($quantity == 0 && isset($_SESSION['cart'][$product_id])) {
            unset($_SESSION['cart'][$product_id]);
        }
    }
    header("Location: cart.php");
    exit;
}

if (isset($_POST['update_quantity']) && isset($_POST['product_id']) && isset($_POST['quantity'])) {
    $product_id = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];

    if ($quantity > 0 && isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id]['quantity'] = $quantity;
    } elseif ($quantity <= 0 && isset($_SESSION['cart'][$product_id])) {
        unset($_SESSION['cart'][$product_id]);
    }

    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);
    exit;
}

if (isset($_GET['clear'])) {
    $_SESSION['cart'] = [];
    header("Location: cart.php");
    exit;
}

$cart_items = $_SESSION['cart'];
$total_price = 0;
foreach ($cart_items as $item) {
    $total_price += $item['price'] * $item['quantity'];
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Корзина - HvostX</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="main-container animate-fade-in">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Главная</a></li>
                <li class="breadcrumb-item active" aria-current="page">Корзина</li>
            </ol>
        </nav>

        <main class="my-4">
        <h1 class="mb-4">
            <i class="fas fa-shopping-cart text-success me-2"></i>
            Корзина
            <?php if (!empty($cart_items)): ?>
            <span class="badge bg-success ms-2"><?php echo array_sum(array_column($cart_items, 'quantity')); ?></span>
            <?php endif; ?>
        </h1>

        <?php if (empty($cart_items)): ?>
        <div class="alert alert-info">
            <i class="fas fa-shopping-cart me-2"></i>
            Ваша корзина пуста. <a href="products.php">Перейти к товарам</a>, чтобы добавить товары.
        </div>
        <?php else: ?>
        <form method="POST" action="cart.php">
            <div class="table-responsive mb-4">
                <table class="table cart-table">
                    <thead>
                        <tr>
                            <th>Товар</th>
                            <th>Цена</th>
                            <th>Количество</th>
                            <th>Сумма</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart_items as $item): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="assets/images/products/<?php echo htmlspecialchars($item['image']); ?>"
                                         alt="<?php echo htmlspecialchars($item['name']); ?>"
                                         style="width: 50px; height: 50px; object-fit: cover; margin-right: 10px;">
                                    <?php echo htmlspecialchars($item['name']); ?>
                                </div>
                            </td>
                            <td><?php echo number_format($item['price'], 2); ?> ₽</td>
                            <td>
                                <div class="quantity-control d-flex align-items-center">
                                    <button type="button" class="btn btn-outline-secondary btn-sm quantity-btn minus-btn" onclick="updateQuantity(<?php echo $item['id']; ?>, -1)">-</button>
                                    <input type="number" name="quantities[<?php echo $item['id']; ?>]"
                                           value="<?php echo $item['quantity']; ?>" min="1" class="form-control quantity-input mx-2" style="width: 60px; text-align: center;" onchange="updateQuantityInput(<?php echo $item['id']; ?>)">
                                    <button type="button" class="btn btn-outline-secondary btn-sm quantity-btn plus-btn" onclick="updateQuantity(<?php echo $item['id']; ?>, 1)">+</button>
                                </div>
                            </td>
                            <td class="item-total"><?php echo number_format($item['price'] * $item['quantity'], 2); ?> ₽</td>
                            <td>
                                <button type="button" class="btn btn-sm btn-danger remove-btn" 
                                        data-product-id="<?php echo $item['id']; ?>" 
                                        data-product-name="<?php echo htmlspecialchars($item['name']); ?>">
                                    <i class="fas fa-trash me-1"></i>Удалить
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3">Итого:</th>
                            <th colspan="2" class="cart-total"><?php echo number_format($total_price, 2); ?> ₽</th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="d-flex justify-content-between mb-4">
                <a href="products.php" class="btn btn-outline-primary">Продолжить покупки</a>
                <a href="cart.php?clear" class="btn btn-outline-danger">Очистить корзину</a>
            </div>

            <div class="text-end">
                <?php if (isset($_SESSION['user_id'])): ?>
                <a href="checkout.php" class="btn btn-success btn-lg">Оформить заказ</a>
                <?php else: ?>
                <a href="account.php" class="btn btn-success btn-lg">
                    <i class="fas fa-sign-in-alt me-2"></i>Войти для оформления
                </a>
                <?php endif; ?>
            </div>
        </form>
        <?php endif; ?>
        </main>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script>
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

            fetch('cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `update_quantity=1&product_id=${productId}&quantity=${newQty}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    updateCartIcon();
                } else {
                    console.error('Ошибка при обновлении количества:', data.message);
                }
            })
            .catch(error => {
                console.error('Ошибка при обновлении количества:', error);
            });
        }

        function updateQuantityInput(productId) {
            const input = document.querySelector(`input[name='quantities[${productId}]']`);
            let newQty = parseInt(input.value);

            if (isNaN(newQty) || newQty < 1) {
                newQty = 1;
                input.value = 1;
            }

            updateItemTotal(productId, newQty);

            updateCartTotal();

            fetch('cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `update_quantity=1&product_id=${productId}&quantity=${newQty}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    updateCartIcon();
                } else {
                    console.error('Ошибка при обновлении количества:', data.message);
                }
            })
            .catch(error => {
                console.error('Ошибка при обновлении количества:', error);
            });
        }

        function updateCartIcon() {
            location.reload();
        }
    </script>

    <!-- Модальное окно подтверждения удаления из корзины -->
    <div class="modal fade" id="removeFromCartModal" tabindex="-1" aria-labelledby="removeFromCartModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="removeFromCartModalLabel">
                        <i class="fas fa-trash-alt me-2"></i>Удалить из корзины
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <p>Вы уверены, что хотите удалить этот товар из корзины?</p>
                    <p class="text-muted mb-0" id="removeProductCartName"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Отмена
                    </button>
                    <button type="button" class="btn btn-danger" id="confirmRemoveFromCart">
                        <i class="fas fa-trash me-2"></i>Удалить
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        let productToRemove = null;
        const removeModal = new bootstrap.Modal(document.getElementById('removeFromCartModal'));

        document.querySelectorAll('.remove-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const productId = this.dataset.productId;
                const productName = this.dataset.productName || 'этот товар';
                const row = this.closest('tr');

                productToRemove = { productId, row };
                document.getElementById('removeProductCartName').textContent = productName;
                removeModal.show();
            });
        });

        document.getElementById('confirmRemoveFromCart').addEventListener('click', function() {
            if (!productToRemove) return;

            const { productId, row } = productToRemove;

            fetch('cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `remove=${productId}`
            })
            .then(response => {
                if (response.ok) {
                    removeModal.hide();

                    row.style.opacity = '0';
                    row.style.transform = 'translateX(100%)';
                    row.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    setTimeout(() => {
                        row.remove();

                        const remainingRows = document.querySelectorAll('.cart-table tbody tr');
                        if (remainingRows.length === 0) {
                            location.reload();
                        } else {
                            updateCartTotal();
                        }
                    }, 300);

                    showSuccessToast('Товар удален из корзины');
                }
            })
            .catch(error => {
                console.error('Ошибка:', error);
                removeModal.hide();
                alert('Произошла ошибка при удалении из корзины');
            });

            productToRemove = null;
        });

        function showSuccessToast(message) {
            const toastContainer = document.querySelector('.toast-container');
            if (!toastContainer) {
                const container = document.createElement('div');
                container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
                container.style.zIndex = '9999';
                document.body.appendChild(container);
            }

            const toast = document.createElement('div');
            toast.className = 'toast align-items-center text-white bg-success border-0';
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-check-circle me-2"></i>${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;

            document.querySelector('.toast-container').appendChild(toast);
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();

            toast.addEventListener('hidden.bs.toast', () => {
                toast.remove();
            });
        }
    });
    </script>
</body>
</html>
