<?php
// /order_view.php
require_once 'includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: account.php");
    exit;
}

$order_id = $_GET['id'] ?? 0;

if (!$order_id) {
    header("Location: profile.php");
    exit;
}

$query = "SELECT * FROM orders WHERE id = ? AND user_id = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    header("Location: 404.php");
    exit;
}

$query = "SELECT oi.*, p.name as product_name, p.image as product_image
          FROM order_items oi
          LEFT JOIN products p ON oi.product_id = p.id
          WHERE oi.order_id = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$status_labels = [
    'pending' => ['label' => 'В обработке', 'class' => 'bg-secondary'],
    'processing' => ['label' => 'В работе', 'class' => 'bg-warning text-dark'],
    'completed' => ['label' => 'Выполнен', 'class' => 'bg-success'],
    'cancelled' => ['label' => 'Отменён', 'class' => 'bg-danger']
];

$status = $status_labels[$order['status']] ?? ['label' => $order['status'], 'class' => 'bg-secondary'];

$page_title = "Заказ #{$order_id} - HvostX";
?>

<?php include 'includes/header.php'; ?>

<div class="main-container animate-fade-in">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Главная</a></li>
            <li class="breadcrumb-item"><a href="profile.php">Профиль</a></li>
            <li class="breadcrumb-item active" aria-current="page">Заказ #<?php echo $order_id; ?></li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="bi bi-bag me-2"></i>Заказ #<?php echo $order_id; ?></h4>
                    <span class="badge <?php echo $status['class']; ?> fs-6">
                        <?php echo $status['label']; ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h5 class="border-bottom pb-2 mb-3">
                                <i class="bi bi-info-circle me-2"></i>Информация о заказе
                            </h5>
                            <table class="table table-sm">
                                <tr>
                                    <td class="text-muted">Дата оформления:</td>
                                    <td><strong><?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?></strong></td>
                                </tr>
                                <?php if ($order['updated_at']): ?>
                                <tr>
                                    <td class="text-muted">Последнее обновление:</td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($order['updated_at'])); ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td class="text-muted">Статус:</td>
                                    <td>
                                        <span class="badge <?php echo $status['class']; ?>">
                                            <?php echo $status['label']; ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="col-md-6">
                            <h5 class="border-bottom pb-2 mb-3">
                                <i class="bi bi-truck me-2"></i>Доставка
                            </h5>
                            <table class="table table-sm">
                                <?php if (!empty($order['shipping_method'])): ?>
                                <tr>
                                    <td class="text-muted">Способ доставки:</td>
                                    <td>
                                        <?php
                                        $shipping_labels = [
                                            'pickup' => 'Самовывоз',
                                            'courier' => 'Курьером'
                                        ];
                                        echo $shipping_labels[$order['shipping_method']] ?? htmlspecialchars($order['shipping_method']);
                                        ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($order['city'])): ?>
                                <tr>
                                    <td class="text-muted">Город:</td>
                                    <td><?php echo htmlspecialchars($order['city']); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($order['postal_code'])): ?>
                                <tr>
                                    <td class="text-muted">Индекс:</td>
                                    <td><?php echo htmlspecialchars($order['postal_code']); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($order['shipping_address'])): ?>
                                <tr>
                                    <td class="text-muted">Адрес:</td>
                                    <td><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($order['comment'])): ?>
                                <tr>
                                    <td class="text-muted">Комментарий:</td>
                                    <td><?php echo nl2br(htmlspecialchars($order['comment'])); ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>

                        <div class="col-md-6">
                            <h5 class="border-bottom pb-2 mb-3">
                                <i class="bi bi-credit-card me-2"></i>Оплата
                            </h5>
                            <table class="table table-sm">
                                <?php if (!empty($order['payment_method'])): ?>
                                <tr>
                                    <td class="text-muted">Способ оплаты:</td>
                                    <td>
                                        <?php
                                        $payment_labels = [
                                            'cash' => 'Наличными при получении',
                                            'card' => 'Картой при получении',
                                            'transfer' => 'Банковский перевод'
                                        ];
                                        echo $payment_labels[$order['payment_method']] ?? htmlspecialchars($order['payment_method']);
                                        ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>

                    <h5 class="border-bottom pb-2 mb-3">
                        <i class="bi bi-cart me-2"></i>Товары в заказе
                    </h5>

                    <?php if (empty($order_items)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>Информация о товарах недоступна
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Товар</th>
                                    <th class="text-center">Количество</th>
                                    <th class="text-end">Цена</th>
                                    <th class="text-end">Сумма</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total = 0;
                                foreach ($order_items as $item): 
                                    $item_sum = $item['price'] * $item['quantity'];
                                    $total += $item_sum;
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($item['product_image'])): ?>
                                            <img src="assets/images/products/<?php echo htmlspecialchars($item['product_image']); ?>" 
                                                 alt="" class="me-3" style="width: 50px; height: 50px; object-fit: cover;">
                                            <?php else: ?>
                                            <div class="bg-light d-flex align-items-center justify-content-center me-3" 
                                                 style="width: 50px; height: 50px;">
                                                <i class="bi bi-box text-muted"></i>
                                            </div>
                                            <?php endif; ?>
                                            <div>
                                                <?php if (!empty($item['product_name'])): ?>
                                                <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                                <?php else: ?>
                                                <span class="text-muted">Товар удалён</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center"><?php echo $item['quantity']; ?></td>
                                    <td class="text-end"><?php echo number_format($item['price'], 0, '.', ' '); ?> ₽</td>
                                    <td class="text-end"><strong><?php echo number_format($item_sum, 0, '.', ' '); ?> ₽</strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="3" class="text-end">Итого:</th>
                                    <th class="text-end">
                                        <strong class="fs-5"><?php echo number_format($total, 0, '.', ' '); ?> ₽</strong>
                                    </th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <?php endif; ?>

                    <?php if ($order['total_amount'] != $total && $order['total_amount'] > 0): ?>
                    <div class="alert alert-warning mt-3">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Обратите внимание: сумма заказа в базе данных (<?php echo number_format($order['total_amount'], 0, '.', ' '); ?> ₽)
                        отличается от рассчитанной суммы товаров (<?php echo number_format($total, 0, '.', ' '); ?> ₽)
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-end">
                    <a href="profile.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Назад к заказам
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
