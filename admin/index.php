<?php
// /admin/index.php
session_start();
require_once '../includes/db.php';
require_once 'auth.php';

checkModeratorAccess();

function time_ago($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'Только что';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' мин. назад';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' ч. назад';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' дн. назад';
    } else {
        return date('d.m.Y H:i', $timestamp);
    }
}

$users_query = "SELECT COUNT(*) as count FROM users";
$users_result = mysqli_query($connection, $users_query);
$users_count = mysqli_fetch_assoc($users_result)['count'];

$products_query = "SELECT COUNT(*) as count FROM products";
$products_result = mysqli_query($connection, $products_query);
$products_count = mysqli_fetch_assoc($products_result)['count'];

$orders_query = "SELECT COUNT(*) as count FROM orders WHERE status = 'completed'";
$orders_result = mysqli_query($connection, $orders_query);
$orders_count = mysqli_fetch_assoc($orders_result)['count'];

$messages_query = "SELECT COUNT(*) as count FROM contact_messages WHERE is_read = 0";
$messages_result = mysqli_query($connection, $messages_query);
$messages_count = mysqli_fetch_assoc($messages_result)['count'];

$reviews_pending_query = "SELECT COUNT(*) as count FROM product_reviews WHERE is_approved = FALSE";
$reviews_pending_result = mysqli_query($connection, $reviews_pending_query);
$reviews_pending_count = mysqli_fetch_assoc($reviews_pending_result)['count'];

$reviews_total_query = "SELECT COUNT(*) as count FROM product_reviews";
$reviews_total_result = mysqli_query($connection, $reviews_total_query);
$reviews_total_count = mysqli_fetch_assoc($reviews_total_result)['count'];

$reviews_avg_query = "SELECT AVG(rating) as avg_rating FROM product_reviews WHERE is_approved = TRUE";
$reviews_avg_result = mysqli_query($connection, $reviews_avg_query);
$reviews_avg = mysqli_fetch_assoc($reviews_avg_result)['avg_rating'] ?? 0;

$events = [];

$orders_events_query = "SELECT o.*, u.name as user_name
                        FROM orders o
                        LEFT JOIN users u ON o.user_id = u.id
                        ORDER BY o.created_at DESC LIMIT 3";
$orders_events_result = mysqli_query($connection, $orders_events_query);
while ($order = mysqli_fetch_assoc($orders_events_result)) {
    $events[] = [
        'type' => 'order',
        'title' => 'Новый заказ #' . $order['id'],
        'description' => ($order['user_name'] ?? 'Гость') . ' оформил заказ на сумму ' . number_format($order['total_amount'], 0, '.', ' ') . ' ₽',
        'time' => $order['created_at'],
        'icon' => 'fa-shopping-cart',
        'color' => 'primary',
        'link' => 'orders.php'
    ];
}

$reviews_events_query = "SELECT r.*, u.name as user_name, p.name as product_name
                         FROM product_reviews r
                         LEFT JOIN users u ON r.user_id = u.id
                         LEFT JOIN products p ON r.product_id = p.id
                         WHERE r.is_approved = FALSE
                         ORDER BY r.created_at DESC LIMIT 2";
$reviews_events_result = mysqli_query($connection, $reviews_events_query);
while ($review = mysqli_fetch_assoc($reviews_events_result)) {
    $events[] = [
        'type' => 'review',
        'title' => 'Новый отзыв на товар',
        'description' => ($review['user_name'] ?? 'Пользователь') . ' оставил отзыв на "' . ($review['product_name'] ?? 'товар') . '"',
        'time' => $review['created_at'],
        'icon' => 'fa-comments',
        'color' => 'warning',
        'link' => 'reviews.php'
    ];
}

$messages_events_query = "SELECT * FROM contact_messages
                          WHERE is_read = FALSE
                          ORDER BY created_at DESC LIMIT 2";
$messages_events_result = mysqli_query($connection, $messages_events_query);
while ($message = mysqli_fetch_assoc($messages_events_result)) {
    $events[] = [
        'type' => 'message',
        'title' => 'Новое сообщение',
        'description' => $message['name'] . ' отправил сообщение через форму обратной связи',
        'time' => $message['created_at'],
        'icon' => 'fa-envelope',
        'color' => 'info',
        'link' => 'messages.php'
    ];
}

$users_events_query = "SELECT * FROM users
                       WHERE role = 'user'
                       ORDER BY created_at DESC LIMIT 2";
$users_events_result = mysqli_query($connection, $users_events_query);
while ($user = mysqli_fetch_assoc($users_events_result)) {
    $events[] = [
        'type' => 'user',
        'title' => 'Новый пользователь',
        'description' => 'Зарегистрировался пользователь: ' . $user['name'],
        'time' => $user['created_at'],
        'icon' => 'fa-user',
        'color' => 'success',
        'link' => 'users.php'
    ];
}

usort($events, function($a, $b) {
    return strtotime($b['time']) - strtotime($a['time']);
});

$events = array_slice($events, 0, 5);

$sales_data_query = "SELECT
                     DATE_FORMAT(created_at, '%Y-%m') as month,
                     COUNT(*) as orders_count,
                     SUM(total_amount) as total_sales
                     FROM orders
                     WHERE status = 'completed'
                     AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                     GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                     ORDER BY month ASC";
$sales_data_result = mysqli_query($connection, $sales_data_query);
$sales_labels = [];
$sales_values = [];

if ($sales_data_result) {
    while ($row = mysqli_fetch_assoc($sales_data_result)) {
        $sales_labels[] = date('M.Y', strtotime($row['month'] . '-01'));
        $sales_values[] = (float)$row['total_sales'];
    }
}

if (empty($sales_labels)) {
    for ($i = 5; $i >= 0; $i--) {
        $sales_labels[] = date('M.Y', strtotime("-$i months"));
        $sales_values[] = 0;
    }
}

$popular_products_query = "SELECT
                           p.name,
                           COALESCE(SUM(oi.quantity), 0) as total_sold
                           FROM products p
                           LEFT JOIN order_items oi ON p.id = oi.product_id
                           LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed'
                           WHERE p.is_active = 1
                           GROUP BY p.id, p.name
                           HAVING total_sold > 0
                           ORDER BY total_sold DESC
                           LIMIT 10";
$popular_products_result = mysqli_query($connection, $popular_products_query);
$products_labels = [];
$products_values = [];

if ($popular_products_result) {
    while ($row = mysqli_fetch_assoc($popular_products_result)) {
        $products_labels[] = mb_substr($row['name'], 0, 20) . (mb_strlen($row['name']) > 20 ? '...' : '');
        $products_values[] = (int)$row['total_sold'];
    }
}

if (empty($products_labels)) {
    $products_labels = ['Нет данных'];
    $products_values = [0];
}

$tags_query = "SELECT
               t.name,
               COUNT(pt.product_id) as products_count
               FROM tags t
               LEFT JOIN product_tags pt ON t.id = pt.tag_id
               LEFT JOIN products p ON pt.product_id = p.id AND p.is_active = 1
               GROUP BY t.id, t.name
               ORDER BY products_count DESC
               LIMIT 10";
$tags_result = mysqli_query($connection, $tags_query);
$tags_labels = [];
$tags_values = [];

if ($tags_result) {
    while ($row = mysqli_fetch_assoc($tags_result)) {
        $tags_labels[] = $row['name'];
        $tags_values[] = (int)$row['products_count'];
    }
}

if (empty($tags_labels)) {
    $tags_labels = ['Нет данных'];
    $tags_values = [0];
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель - HvostX</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include 'sidebar.php'; ?>

        <div id="page-content-wrapper">
            <?php include 'navbar.php'; ?>

            <div class="container-fluid px-4">
                <h1 class="mt-4 mb-4">Панель управления</h1>

                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="card bg-primary text-white mb-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Заказы</h5>
                                        <p class="card-text fs-4"><?php echo $orders_count; ?></p>
                                    </div>
                                    <i class="fas fa-shopping-cart fa-2x"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <a href="orders.php" class="small stretched-link">Посмотреть все</a>
                                <div class="small"><i class="fas fa-angle-right text-primary"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card bg-success text-white mb-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Товары</h5>
                                        <p class="card-text fs-4"><?php echo $products_count; ?></p>
                                    </div>
                                    <i class="fas fa-box fa-2x"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <a href="products.php" class="small stretched-link">Посмотреть все</a>
                                <div class="small"><i class="fas fa-angle-right text-success"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card bg-info text-white mb-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Пользователи</h5>
                                        <p class="card-text fs-4"><?php echo $users_count; ?></p>
                                    </div>
                                    <i class="fas fa-users fa-2x"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <a href="users.php" class="small stretched-link">Посмотреть все</a>
                                <div class="small"><i class="fas fa-angle-right text-info"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card bg-warning text-white mb-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Сообщения</h5>
                                        <p class="card-text fs-4"><?php echo $messages_count; ?></p>
                                    </div>
                                    <i class="fas fa-envelope fa-2x"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <a href="messages.php" class="small stretched-link">Посмотреть все</a>
                                <div class="small"><i class="fas fa-angle-right text-warning"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card bg-secondary text-white mb-4" style="background: linear-gradient(135deg, #6f42c1 0%, #a65eea 100%) !important;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Отзывы</h5>
                                        <p class="card-text fs-4"><?php echo $reviews_pending_count; ?> / <?php echo $reviews_total_count; ?></p>
                                        <small class="text-white-50">На модерации / Всего</small>
                                    </div>
                                    <i class="fas fa-comments fa-2x"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <a href="reviews.php" class="small stretched-link">Посмотреть все</a>
                                <div class="small">
                                    <i class="fas fa-star text-warning me-1"></i>
                                    <span class="text-dark"><?php echo number_format($reviews_avg, 1); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-8 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <i class="fas fa-chart-line me-1"></i>
                                Статистика продаж (по месяцам)
                            </div>
                            <div class="card-body">
                                <canvas id="salesChart" width="100%" height="60"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <i class="fas fa-chart-bar me-1"></i>
                                Популярные товары
                            </div>
                            <div class="card-body">
                                <canvas id="productsChart" width="100%" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <i class="fas fa-chart-pie me-1"></i>
                                Товары по тегам
                            </div>
                            <div class="card-body">
                                <canvas id="tagsChart" width="100%" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <i class="fas fa-star me-1"></i>
                                Товары с лучшим рейтингом
                            </div>
                            <div class="card-body">
                                <?php
                                $top_rated_query = "SELECT name, avg_rating, review_count 
                                                   FROM products 
                                                   WHERE avg_rating > 0 AND is_active = 1
                                                   ORDER BY avg_rating DESC, review_count DESC 
                                                   LIMIT 5";
                                $top_rated_result = mysqli_query($connection, $top_rated_query);
                                if (mysqli_num_rows($top_rated_result) > 0):
                                ?>
                                <div class="list-group list-group-flush">
                                    <?php while ($product = mysqli_fetch_assoc($top_rated_result)): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($product['name']); ?></h6>
                                            <small class="text-muted">
                                                <i class="fas fa-comment-alt me-1"></i><?php echo $product['review_count']; ?> отзывов
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <div class="text-warning">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?php echo $i <= round($product['avg_rating']) ? '' : 'text-muted'; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <small class="text-muted"><?php echo number_format($product['avg_rating'], 1); ?></small>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                    <p class="text-muted mb-0">Пока нет товаров с отзывами</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <i class="fas fa-bell me-1"></i>
                                Последние события
                            </div>
                            <div class="card-body">
                        <?php if (empty($events)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">Событий пока нет</p>
                        </div>
                        <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($events as $event): ?>
                            <a href="<?php echo $event['link']; ?>" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <span class="badge bg-<?php echo $event['color']; ?> rounded-circle p-2" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas <?php echo $event['icon']; ?>"></i>
                                            </span>
                                        </div>
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($event['title']); ?></h6>
                                            <p class="mb-0"><?php echo htmlspecialchars($event['description']); ?></p>
                                        </div>
                                    </div>
                                    <small class="text-muted"><?php echo time_ago($event['time']); ?></small>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        const salesLabels = <?php echo json_encode($sales_labels); ?>;
        const salesValues = <?php echo json_encode($sales_values); ?>;
        const productsLabels = <?php echo json_encode($products_labels); ?>;
        const productsValues = <?php echo json_encode($products_values); ?>;
        const tagsLabels = <?php echo json_encode($tags_labels); ?>;
        const tagsValues = <?php echo json_encode($tags_values); ?>;

        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: salesLabels,
                datasets: [{
                    label: 'Продажи (₽)',
                    data: salesValues,
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Продажи: ' + new Intl.NumberFormat('ru-RU').format(context.parsed.y) + ' ₽';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('ru-RU', { notation: 'compact' }).format(value) + ' ₽';
                            }
                        }
                    }
                }
            }
        });

        const productsCtx = document.getElementById('productsChart').getContext('2d');
        const productsChart = new Chart(productsCtx, {
            type: 'bar',
            data: {
                labels: productsLabels,
                datasets: [{
                    label: 'Продано (шт)',
                    data: productsValues,
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.8)',
                        'rgba(28, 200, 138, 0.8)',
                        'rgba(246, 194, 62, 0.8)',
                        'rgba(231, 74, 59, 0.8)',
                        'rgba(108, 117, 125, 0.8)',
                        'rgba(13, 110, 253, 0.8)',
                        'rgba(111, 66, 193, 0.8)',
                        'rgba(220, 53, 69, 0.8)',
                        'rgba(255, 193, 7, 0.8)',
                        'rgba(25, 135, 84, 0.8)'
                    ],
                    borderColor: [
                        'rgba(40, 167, 69, 1)',
                        'rgba(28, 200, 138, 1)',
                        'rgba(246, 194, 62, 1)',
                        'rgba(231, 74, 59, 1)',
                        'rgba(108, 117, 125, 1)',
                        'rgba(13, 110, 253, 1)',
                        'rgba(111, 66, 193, 1)',
                        'rgba(220, 53, 69, 1)',
                        'rgba(255, 193, 7, 1)',
                        'rgba(25, 135, 84, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Продано: ' + context.parsed.x + ' шт';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // График тегов
        const tagsCtx = document.getElementById('tagsChart').getContext('2d');
        const tagsChart = new Chart(tagsCtx, {
            type: 'doughnut',
            data: {
                labels: tagsLabels,
                datasets: [{
                    data: tagsValues,
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.8)',
                        'rgba(13, 110, 253, 0.8)',
                        'rgba(255, 193, 7, 0.8)',
                        'rgba(220, 53, 69, 0.8)',
                        'rgba(111, 66, 193, 0.8)',
                        'rgba(25, 135, 84, 0.8)'
                    ],
                    borderColor: [
                        'rgba(40, 167, 69, 1)',
                        'rgba(13, 110, 253, 1)',
                        'rgba(255, 193, 7, 1)',
                        'rgba(220, 53, 69, 1)',
                        'rgba(111, 66, 193, 1)',
                        'rgba(25, 135, 84, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.parsed + ' товаров';
                            }
                        }
                    }
                }
            }
        });

        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.getElementById('wrapper').classList.toggle('toggled');
        });
    </script>
</body>
</html>
