<?php
// /order_success.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: account.php");
    exit;
}

$order_id = $_GET['order_id'] ?? 0;

if ($order_id <= 0) {
    header("Location: index.php");
    exit;
}

$query = "SELECT o.*, u.name, u.email, u.phone
          FROM orders o
          LEFT JOIN users u ON o.user_id = u.id
          WHERE o.id = ? AND o.user_id = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    header("Location: index.php");
    exit;
}

$query = "SELECT oi.*, p.name, p.image
          FROM order_items oi
          LEFT JOIN products p ON oi.product_id = p.id
          WHERE oi.order_id = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$status_labels = [
    'pending' => ['text' => 'В обработке', 'class' => 'warning'],
    'processing' => ['text' => 'Обрабатывается', 'class' => 'info'],
    'completed' => ['text' => 'Выполнен', 'class' => 'success'],
    'cancelled' => ['text' => 'Отменён', 'class' => 'danger']
];

$status_info = $status_labels[$order['status']] ?? ['text' => 'Неизвестно', 'class' => 'secondary'];

$page_title = "Заказ #" . $order_id . " - HvostX";
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="main-container animate-fade-in">
        <main class="container my-5">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <!-- Успешное оформление -->
                    <div class="text-center mb-5">
                        <div class="display-1 text-success mb-3">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                        <h1 class="mb-3">Заказ успешно оформлен!</h1>
                        <p class="lead text-muted">
                            Спасибо за ваш заказ, <?php echo htmlspecialchars($order['name']); ?>!
                        </p>
                        <p class="text-muted">
                            Номер вашего заказа: <strong>#<?php echo $order_id; ?></strong>
                        </p>
                        <div class="alert alert-success d-inline-block">
                            <i class="bi bi-envelope me-2"></i>
                            Подтверждение отправлено на email: <strong><?php echo htmlspecialchars($order['email']); ?></strong>
                        </div>
                    </div>

                    <!-- Информация о заказе -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="bi bi-receipt me-2"></i>Информация о заказе</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <h6 class="text-muted mb-2">Статус заказа</h6>
                                    <span class="badge bg-<?php echo $status_info['class']; ?> fs-6">
                                        <?php echo $status_info['text']; ?>
                                    </span>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <h6 class="text-muted mb-2">Дата оформления</h6>
                                    <p class="mb-0"><?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <h6 class="text-muted mb-2">Способ оплаты</h6>
                                    <p class="mb-0">
                                        <?php
                                        $payment_labels = [
                                            'cash' => 'Наличными при получении',
                                            'card' => 'Картой при получении',
                                            'transfer' => 'Банковский перевод'
                                        ];
                                        echo $payment_labels[$order['payment_method']] ?? $order['payment_method'];
                                        ?>
                                    </p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <h6 class="text-muted mb-2">Способ доставки</h6>
                                    <p class="mb-0">
                                        <?php
                                        $shipping_labels = [
                                            'pickup' => 'Самовывоз',
                                            'courier' => 'Курьером'
                                        ];
                                        echo $shipping_labels[$order['shipping_method']] ?? $order['shipping_method'];
                                        ?>
                                    </p>
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
                            <p class="mb-1"><strong>Город:</strong> <?php echo htmlspecialchars($order['city']); ?></p>
                            <?php if (!empty($order['postal_code'])): ?>
                            <p class="mb-1"><strong>Индекс:</strong> <?php echo htmlspecialchars($order['postal_code']); ?></p>
                            <?php endif; ?>
                            <p class="mb-1"><strong>Адрес:</strong> <?php echo htmlspecialchars($order['shipping_address']); ?></p>
                            <?php if (!empty($order['comment'])): ?>
                            <hr>
                            <p class="mb-0"><strong>Комментарий:</strong> <?php echo nl2br(htmlspecialchars($order['comment'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Товары заказа -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="bi bi-box me-2"></i>Товары в заказе</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Товар</th>
                                            <th>Цена</th>
                                            <th>Кол-во</th>
                                            <th>Сумма</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($order_items as $item): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="assets/images/products/<?php echo htmlspecialchars($item['image']); ?>"
                                                         alt="<?php echo htmlspecialchars($item['name']); ?>"
                                                         style="width: 50px; height: 50px; object-fit: cover; margin-right: 10px;"
                                                         class="rounded">
                                                    <?php echo htmlspecialchars($item['name']); ?>
                                                </div>
                                            </td>
                                            <td><?php echo number_format($item['price'], 0, '.', ' '); ?> ₽</td>
                                            <td><?php echo $item['quantity']; ?> шт.</td>
                                            <td><?php echo number_format($item['price'] * $item['quantity'], 0, '.', ' '); ?> ₽</td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="3">Итого:</th>
                                            <th class="text-success fs-5"><?php echo number_format($order['total_amount'], 0, '.', ' '); ?> ₽</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Кнопки действий -->
                    <div class="d-flex justify-content-between">
                        <a href="products.php" class="btn btn-outline-success">
                            <i class="bi bi-shop me-2"></i>Продолжить покупки
                        </a>
                        <a href="profile.php" class="btn btn-success">
                            <i class="bi bi-person me-2"></i>Мои заказы
                        </a>
                    </div>

                    <!-- Информация для связи -->
                    <div class="alert alert-info mt-4">
                        <h6 class="alert-heading"><i class="bi bi-info-circle me-2"></i>Что дальше?</h6>
                        <hr>
                        <p class="mb-0">
                            <strong>1.</strong> Менеджер свяжется с вами в течение рабочего дня для подтверждения заказа.<br>
                            <strong>2.</strong> Вы получите SMS или email уведомление о статусе заказа.<br>
                            <strong>3.</strong> После отправки вы сможете отслеживать статус в личном кабинете.
                        </p>
                        <p class="mb-0 mt-3">
                            Если у вас возникли вопросы, позвоните нам: 
                            <a href="tel:+79027580003" class="alert-link">+7 (902) 758-00-03</a>
                        </p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
