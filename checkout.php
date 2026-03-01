<?php
// /checkout.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: account.php");
    exit;
}

if (empty($_SESSION['cart'])) {
    header("Location: cart.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$query = "SELECT * FROM users WHERE id = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$cart_items = $_SESSION['cart'];
$total_price = 0;
foreach ($cart_items as $item) {
    $total_price += $item['price'] * $item['quantity'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $shipping_method = $_POST['shipping_method'] ?? 'pickup';
    $comment = trim($_POST['comment'] ?? '');

    $errors = [];
    if (empty($name)) $errors[] = 'Пожалуйста, укажите ваше имя';
    if (empty($phone)) $errors[] = 'Пожалуйста, укажите телефон';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Пожалуйста, укажите корректный email';
    if (empty($address)) $errors[] = 'Пожалуйста, укажите адрес доставки';
    if (empty($city)) $errors[] = 'Пожалуйста, укажите город';

    if (empty($errors)) {
        $status = 'pending';
        $query = "INSERT INTO orders (user_id, total_amount, status, shipping_address, payment_method, shipping_method, city, postal_code, comment, phone, created_at)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $connection->prepare($query);
        $stmt->bind_param("idssssssss", $user_id, $total_price, $status, $address, $payment_method, $shipping_method, $city, $postal_code, $comment, $phone);

        if ($stmt->execute()) {
            $order_id = $connection->insert_id;

            foreach ($cart_items as $item) {
                $query = "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)";
                $stmt = $connection->prepare($query);
                $stmt->bind_param("iiid", $order_id, $item['id'], $item['quantity'], $item['price']);
                $stmt->execute();
            }

            $_SESSION['cart'] = [];

            header("Location: order_success.php?order_id=" . $order_id);
            exit;
        } else {
            $error = "Ошибка при оформлении заказа. Попробуйте позже.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Оформление заказа - HvostX</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="main-container animate-fade-in">
        <main class="container my-5">
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Главная</a></li>
                    <li class="breadcrumb-item"><a href="cart.php">Корзина</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Оформление заказа</li>
                </ol>
            </nav>

            <h1 class="mb-4">Оформление заказа</h1>

            <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="checkout.php" id="checkoutForm">
                <div class="row">
                    <!-- Левая колонка - данные -->
                    <div class="col-lg-8">
                        <!-- Контактные данные -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="bi bi-person me-2"></i>Контактные данные</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="name" class="form-label">Имя получателя *</label>
                                        <input type="text" class="form-control" id="name" name="name" required
                                               value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label">Телефон *</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" required
                                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                               placeholder="+7 (___) ___-__-__">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="email" name="email" required
                                               value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Адрес доставки -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="bi bi-geo-alt me-2"></i>Адрес доставки</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="city" class="form-label">Город *</label>
                                        <input type="text" class="form-control" id="city" name="city" required
                                               value="г. Белореченск">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="postal_code" class="form-label">Индекс</label>
                                        <input type="text" class="form-control" id="postal_code" name="postal_code"
                                               placeholder="352630">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="shipping_method" class="form-label">Способ доставки *</label>
                                        <select class="form-select" id="shipping_method" name="shipping_method" required>
                                            <option value="pickup" data-price="0">Самовывоз (бесплатно)</option>
                                            <option value="courier" data-price="300">Курьером (+300 ₽)</option>
                                        </select>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label for="address" class="form-label">Адрес доставки (улица, дом, квартира) *</label>
                                        <input type="text" class="form-control" id="address" name="address" required
                                               placeholder="ул. Ленина, д. 10, кв. 25">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Способ оплаты -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="bi bi-credit-card me-2"></i>Способ оплаты</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check payment-option p-3 border rounded">
                                            <input class="form-check-input" type="radio" name="payment_method" 
                                                   id="payment_cash" value="cash" checked>
                                            <label class="form-check-label w-100" for="payment_cash">
                                                <i class="bi bi-cash me-2"></i>
                                                <strong>Наличными при получении</strong>
                                                <small class="d-block text-muted">Оплата наличными курьеру или в пункте выдачи</small>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check payment-option p-3 border rounded">
                                            <input class="form-check-input" type="radio" name="payment_method" 
                                                   id="payment_card" value="card">
                                            <label class="form-check-label w-100" for="payment_card">
                                                <i class="bi bi-credit-card-2-front me-2"></i>
                                                <strong>Картой при получении</strong>
                                                <small class="d-block text-muted">Оплата картой курьеру или в пункте выдачи</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Комментарий к заказу -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="bi bi-chat-left-text me-2"></i>Комментарий к заказу</h5>
                            </div>
                            <div class="card-body">
                                <textarea class="form-control" id="comment" name="comment" rows="3"
                                          placeholder="Дополнительная информация для курьера (код домофона, желаемое время доставки и т.д.)"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Правая колонка - заказ -->
                    <div class="col-lg-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="bi bi-cart me-2"></i>Ваш заказ</h5>
                            </div>
                            <div class="card-body">
                                <div class="order-summary">
                                    <?php foreach ($cart_items as $item): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                        <div class="d-flex align-items-center">
                                            <img src="assets/images/products/<?php echo htmlspecialchars($item['image']); ?>"
                                                 alt="<?php echo htmlspecialchars($item['name']); ?>"
                                                 style="width: 50px; height: 50px; object-fit: cover; margin-right: 10px;"
                                                 class="rounded">
                                            <div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($item['name']); ?></h6>
                                                <small class="text-muted"><?php echo $item['quantity']; ?> шт. × <?php echo number_format($item['price'], 0, '.', ' '); ?> ₽</small>
                                            </div>
                                        </div>
                                        <span class="fw-bold"><?php echo number_format($item['price'] * $item['quantity'], 0, '.', ' '); ?> ₽</span>
                                    </div>
                                    <?php endforeach; ?>

                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between">
                                            <span>Товары:</span>
                                            <span class="fw-bold"><?php echo number_format($total_price, 0, '.', ' '); ?> ₽</span>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between">
                                            <span>Доставка:</span>
                                            <span class="fw-bold" id="shipping-cost">0 ₽</span>
                                        </div>
                                    </div>

                                    <hr>

                                    <div class="d-flex justify-content-between mb-3">
                                        <strong class="fs-5">Итого:</strong>
                                        <strong class="fs-4 text-success" id="order-total"><?php echo number_format($total_price, 0, '.', ' '); ?> ₽</strong>
                                    </div>

                                    <button type="submit" class="btn btn-success btn-lg w-100">
                                        <i class="bi bi-check-circle me-2"></i>Подтвердить заказ
                                    </button>

                                    <div class="text-center mt-3">
                                        <small class="text-muted">
                                            <i class="bi bi-shield-check me-1"></i>
                                            Нажимая кнопку, вы соглашаетесь с условиями обработки персональных данных
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const baseTotal = <?php echo $total_price; ?>;
        const shippingMethodSelect = document.getElementById('shipping_method');
        const shippingCostSpan = document.getElementById('shipping-cost');
        const orderTotalSpan = document.getElementById('order-total');

        shippingMethodSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const shippingCost = parseInt(selectedOption.dataset.price) || 0;
            const total = baseTotal + shippingCost;

            shippingCostSpan.textContent = shippingCost > 0 ? '+' + shippingCost + ' ₽' : '0 ₽';
            orderTotalSpan.textContent = total.toFixed(2) + ' ₽';
        });

        const phoneInput = document.getElementById('phone');
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 0) {
                if (value[0] === '7' || value[0] === '8') {
                    value = value.substring(1);
                }
                let formatted = '+7';
                if (value.length > 0) formatted += ' (' + value.substring(0, 3);
                if (value.length > 3) formatted += ') ' + value.substring(3, 6);
                if (value.length > 6) formatted += '-' + value.substring(6, 8);
                if (value.length > 8) formatted += '-' + value.substring(8, 10);
                e.target.value = formatted;
            }
        });

        document.querySelectorAll('.payment-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.payment-option').forEach(o => o.classList.remove('border-success', 'bg-light'));
                this.classList.add('border-success', 'bg-light');
                this.querySelector('input[type="radio"]').checked = true;
            });
        });

        const checkedRadio = document.querySelector('input[name="payment_method"]:checked');
        if (checkedRadio) {
            checkedRadio.closest('.payment-option').classList.add('border-success', 'bg-light');
        }
    });
    </script>
</body>
</html>
