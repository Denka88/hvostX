<?php
// /profile.php
require_once 'includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: account.php");
    exit;
}

$query = "SELECT * FROM users WHERE id = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    session_destroy();
    header("Location: account.php");
    exit;
}

$query = "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $connection->prepare($query);
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$orders_count = count($orders);

$status_labels = [
    'pending' => ['label' => 'В обработке', 'class' => 'bg-secondary'],
    'processing' => ['label' => 'В работе', 'class' => 'bg-warning'],
    'completed' => ['label' => 'Выполнен', 'class' => 'bg-success'],
    'cancelled' => ['label' => 'Отменён', 'class' => 'bg-danger']
];

$page_title = "Профиль - HvostX";
?>

<?php include 'includes/header.php'; ?>

<div class="main-container animate-fade-in">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Главная</a></li>
            <li class="breadcrumb-item active" aria-current="page">Профиль</li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-lg-4 mb-4">
            <!-- Карточка профиля -->
            <div class="card shadow-sm mb-4">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <?php if (!empty($user['avatar']) && file_exists("assets/images/avatars/" . $user['avatar'])): ?>
                        <img src="assets/images/avatars/<?php echo htmlspecialchars($user['avatar']); ?>"
                             alt="Ваш аватар" class="img-fluid rounded-circle" style="width: 150px; height: 150px; object-fit: cover;">
                        <?php else: ?>
                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto"
                             style="width: 150px; height: 150px;">
                            <i class="bi bi-person-fill text-muted" style="font-size: 4rem;"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <h4 class="mb-1"><?php echo htmlspecialchars($user['name']); ?></h4>
                    <p class="text-muted mb-1">@<?php echo htmlspecialchars($user['login']); ?></p>
                    <p class="text-muted small mb-3">
                        <i class="bi bi-calendar-check me-1"></i>
                        Регистрация: <?php echo date('d.m.Y', strtotime($user['created_at'])); ?>
                    </p>
                    <a href="user_profile_edit.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-pencil-square me-1"></i> Редактировать
                    </a>
                </div>
            </div>

            <!-- Контактная информация -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-person-lines-fill me-2"></i>Контактная информация</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="bi bi-envelope me-2 text-primary"></i>
                            <span class="text-muted">Email:</span>
                            <?php echo htmlspecialchars($user['email']); ?>
                        </li>
                        <?php if (!empty($user['phone'])): ?>
                        <li class="mb-2">
                            <i class="bi bi-telephone me-2 text-primary"></i>
                            <span class="text-muted">Телефон:</span>
                            <?php echo htmlspecialchars($user['phone']); ?>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <!-- Статистика -->
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Статистика</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Всего заказов:</span>
                        <strong><?php echo $orders_count; ?></strong>
                    </div>
                    <?php
                    $completed_orders = array_filter($orders, fn($o) => $o['status'] === 'completed');
                    $pending_orders = array_filter($orders, fn($o) => $o['status'] === 'pending' || $o['status'] === 'processing');
                    ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Выполнено:</span>
                        <strong class="text-success"><?php echo count($completed_orders); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">В работе:</span>
                        <strong class="text-warning"><?php echo count($pending_orders); ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="bi bi-bag me-2"></i>История заказов</h4>
                    <span class="badge bg-light text-dark"><?php echo $orders_count; ?> шт.</span>
                </div>
                <div class="card-body">
                    <?php if (empty($orders)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-bag-x text-muted" style="font-size: 4rem;"></i>
                        <h5 class="mt-3 text-muted">У вас пока нет заказов</h5>
                        <p class="text-muted">Оформите первый заказ в нашем магазине!</p>
                        <a href="products.php" class="btn btn-primary">
                            <i class="bi bi-shop me-2"></i>Перейти в каталог
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>№ заказа</th>
                                    <th>Дата</th>
                                    <th>Сумма</th>
                                    <th>Статус</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                <?php
                                $status = $status_labels[$order['status']] ?? ['label' => $order['status'], 'class' => 'bg-secondary'];
                                ?>
                                <tr>
                                    <td><strong>#<?php echo $order['id']; ?></strong></td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?></td>
                                    <td><strong><?php echo number_format($order['total_amount'], 0, '.', ' '); ?> ₽</strong></td>
                                    <td>
                                        <span class="badge <?php echo $status['class']; ?>">
                                            <?php echo $status['label']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="order_view.php?id=<?php echo $order['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye me-1"></i>Подробнее
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
