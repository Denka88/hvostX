<?php
// /admin/orders.php
session_start();
require_once '../includes/db.php';
require_once 'auth.php';
require_once 'filter.php';

checkModeratorAccess();

$action = $_GET['action'] ?? '';
$id = (int) ($_GET['id'] ?? 0);

if (isset($_GET['clear_filter'])) {
    $filter = new TableFilter($connection, 'orders', 'o');
    $filter->clear();
    header("Location: orders.php");
    exit;
}

$filter = new TableFilter($connection, 'orders', 'o');
$filter->addField('status', 'select', 'Статус', [
    'pending' => 'В обработке',
    'processing' => 'В работе',
    'completed' => 'Выполнен',
    'cancelled' => 'Отменён'
])
    ->addField('shipping_method', 'select', 'Доставка', [
        'pickup' => 'Самовывоз',
        'courier' => 'Курьер'
    ])
    ->addField('payment_method', 'select', 'Оплата', [
        'cash' => 'Наличными',
        'card' => 'Картой'
    ])
    ->addDateRange('created_at', 'Дата заказа');

if (!empty($_GET) && !isset($_GET['action']) && !isset($_GET['clear_filter'])) {
    $filter->saveValues($_GET);
}

if ($action === 'update_status' && $id > 0 && isset($_GET['status'])) {

    $status = $_GET['status'];
    $valid = ['pending', 'processing', 'completed', 'cancelled'];

    if (in_array($status, $valid)) {
        $stmt = $connection->prepare(
            "UPDATE orders SET status=?, updated_at=NOW() WHERE id=?"
        );
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
    }

    header("Location: orders.php");
    exit;
}

list($where, $params, $types) = $filter->getSQL();

$count_query = "SELECT COUNT(*) as total FROM orders o $where";
if (!empty($params)) {
    $count_stmt = $connection->prepare($count_query);
    if (!empty($types)) {
        $count_stmt->bind_param($types, ...$params);
    }
    $count_stmt->execute();
    $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
} else {
    $count_result = mysqli_query($connection, "SELECT COUNT(*) as total FROM orders");
    $total_records = mysqli_fetch_assoc($count_result)['total'];
}

$pagination = new Pagination($total_records, 20);
$offset = $pagination->getOffset();
$limit = $pagination->getLimit();

$query = "
SELECT o.*,
       u.name user_name,
       u.email user_email,
       u.phone user_phone
FROM orders o
LEFT JOIN users u ON u.id=o.user_id
$where
ORDER BY o.created_at DESC
LIMIT $limit OFFSET $offset";

if (!empty($params)) {
    $stmt = $connection->prepare($query);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = mysqli_fetch_all($result, MYSQLI_ASSOC);
} else {
    $result = mysqli_query($connection, $query);
    $orders = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

$statuses = [
    'pending' => [
        'label' => 'В обработке',
        'badge' => 'secondary',
    ],
    'processing' => [
        'label' => 'В работе',
        'badge' => 'warning',
    ],
    'completed' => [
        'label' => 'Выполнен',
        'badge' => 'success',
    ],
    'cancelled' => [
        'label' => 'Отменён',
        'badge' => 'danger',
    ],
];

$shipping_methods = [
    'pickup' => 'Самовывоз',
    'courier' => 'Курьер'
];

$payment_methods = [
    'cash' => 'Наличными',
    'card' => 'Картой'
];
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Заказы</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/admin.css">
</head>

<body>

    <div class="d-flex" id="wrapper">

        <?php include 'sidebar.php'; ?>

        <div id="page-content-wrapper">

            <?php include 'navbar.php'; ?>

            <div class="container-fluid px-4">

                <h1 class="mt-4 mb-4">Управление заказами</h1>

                <?php echo $filter->render(); ?>

                <div class="card">

                    <div class="card-header d-flex justify-content-between">
                        <strong><i class="fas fa-shopping-cart me-2"></i>Список заказов</strong>
                        <span class="badge bg-primary"><?= count($orders) ?> шт.</span>
                    </div>

                    <div class="card-body">

                        <?php if (empty($orders)): ?>

                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>Заказов нет</p>
                            </div>

                        <?php else: ?>

                            <div class="table-responsive">

                                <table class="table table-hover align-middle">

                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Дата</th>
                                            <th>Клиент</th>
                                            <th>Сумма</th>
                                            <th>Статус</th>
                                            <th>Доставка</th>
                                            <th width="140">Действия</th>
                                        </tr>
                                    </thead>

                                    <tbody>

                                        <?php foreach ($orders as $order): ?>

                                            <tr>

                                                <td><strong>#<?= $order['id'] ?></strong></td>

                                                <td>
                                                    <?= date('d.m.Y', strtotime($order['created_at'])) ?><br>
                                                    <small class="text-muted">
                                                        <?= date('H:i', strtotime($order['created_at'])) ?>
                                                    </small>
                                                </td>

                                                <td>
                                                    <strong><?= htmlspecialchars($order['user_name'] ?? 'Гость') ?></strong><br>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars($order['user_email'] ?? '') ?>
                                                    </small>
                                                </td>

                                                <td>
                                                    <strong><?= number_format($order['total_amount'], 2, '.', ' ') ?> ₽</strong>
                                                </td>

                                                <td>
                                                    <span class="badge bg-<?= $statuses[$order['status']]['badge'] ?>">
                                                        <?= $statuses[$order['status']]['label'] ?>
                                                    </span>
                                                </td>

                                                <td>
                                                    <?= $shipping_methods[$order['shipping_method']] ?? '—' ?>
                                                </td>

                                                <td>

                                                    <div class="btn-group">

                                                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                                            data-bs-target="#orderModal<?= $order['id'] ?>">

                                                            <i class="fas fa-eye"></i>
                                                        </button>

                                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                                            data-bs-toggle="dropdown">
                                                        </button>

                                                        <ul class="dropdown-menu">

                                                            <?php foreach ($statuses as $key => $st): ?>

                                                                <li>
                                                                    <a class="dropdown-item"
                                                                        href="?action=update_status&id=<?= $order['id'] ?>&status=<?= $key ?>">
                                                                        <?= $st['label'] ?>
                                                                    </a>
                                                                </li>

                                                            <?php endforeach; ?>

                                                        </ul>

                                                    </div>

                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    </tbody>
                                </table>

                            </div>

                            <?php echo $pagination->render(); ?>

                        <?php endif; ?>

                    </div>
                </div>

            </div>
        </div>
    </div>

    <?php foreach ($orders as $order):

        $stmt = $connection->prepare("
            SELECT oi.*,p.name product_name
            FROM order_items oi
            LEFT JOIN products p ON p.id=oi.product_id
            WHERE order_id=?");

        $stmt->bind_param("i", $order['id']);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        ?>

        <div class="modal fade" id="orderModal<?= $order['id'] ?>" tabindex="-1">

            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title">
                            Заказ #<?= $order['id'] ?>
                        </h5>
                        <button class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Клиент:</strong></p>
                                <p><?= htmlspecialchars($order['user_name'] ?? 'Гость') ?></p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Email:</strong></p>
                                <p><?= htmlspecialchars($order['user_email'] ?? '—') ?></p>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Телефон:</strong></p>
                                <p><?= htmlspecialchars($order['phone'] ?? $order['user_phone'] ?? '—') ?></p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Город:</strong></p>
                                <p><?= htmlspecialchars($order['city'] ?? '—') ?></p>
                            </div>
                        </div>

                        <div class="mb-3">
                            <p class="mb-1"><strong>Адрес доставки:</strong></p>
                            <p><?= htmlspecialchars($order['shipping_address'] ?? '—') ?></p>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Способ доставки:</strong></p>
                                <p><?= $shipping_methods[$order['shipping_method']] ?? '—' ?></p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Оплата:</strong></p>
                                <p><?= $payment_methods[$order['payment_method']] ?? '—' ?></p>
                            </div>
                        </div>

                        <?php if (!empty($order['postal_code'])): ?>
                            <div class="mb-3">
                                <p class="mb-1"><strong>Почтовый индекс:</strong></p>
                                <p><?= htmlspecialchars($order['postal_code']) ?></p>
                            </div>
                        <?php endif; ?>

                        <hr>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Статус:</strong></p>
                                <span class="badge bg-<?= $statuses[$order['status']]['badge'] ?>">
                                    <?= $statuses[$order['status']]['label'] ?>
                                </span>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Сумма заказа:</strong></p>
                                <p class="text-success fw-bold"><?= number_format($order['total_amount'], 2, '.', ' ') ?> ₽
                                </p>
                            </div>
                        </div>

                        <?php if (!empty($order['comment'])): ?>
                            <hr>
                            <p class="mb-1">
                                <strong><i class="fas fa-comment-dots me-1"></i>Комментарий к заказу:</strong>
                            </p>
                            <p class="bg-light p-2 rounded"><?= htmlspecialchars($order['comment']) ?></p>
                        <?php endif; ?>

                        <hr>

                        <h6 class="mb-3"><i class="fas fa-box me-2"></i>Товары в заказе:</h6>

                        <table class="table table-sm">

                            <thead>
                                <tr>
                                    <th>Товар</th>
                                    <th>Кол-во</th>
                                    <th class="text-end">Цена</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['product_name'] ?? 'Удалён') ?></td>
                                        <td><?= $item['quantity'] ?></td>
                                        <td class="text-end">
                                            <?= number_format($item['price'], 2) ?> ₽
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-secondary" data-bs-dismiss="modal">
                            Закрыть
                        </button>
                    </div>

                </div>
            </div>
        </div>

    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>