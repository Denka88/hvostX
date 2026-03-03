<?php
// /admin/reviews.php
session_start();
require_once '../includes/db.php';
require_once 'auth.php';
require_once 'filter.php';

checkModeratorAccess();

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

if (isset($_GET['clear_filter'])) {
    $filter = new TableFilter($connection, 'product_reviews', 'r');
    $filter->clear();
    header("Location: reviews.php");
    exit;
}

$filter = new TableFilter($connection, 'product_reviews', 'r');
$filter->addField('is_approved', 'select', 'Статус', [
    '0' => 'На модерации',
    '1' => 'Одобрен'
])
->addField('rating', 'select', 'Рейтинг', [
    '5' => '5 звёзд',
    '4' => '4 звезды',
    '3' => '3 звезды',
    '2' => '2 звезды',
    '1' => '1 звезда'
])
->addDateRange('created_at', 'Дата отзыва');

if (!empty($_GET) && !isset($_GET['action']) && !isset($_GET['clear_filter'])) {
    $filter->saveValues($_GET);
}

if ($action === 'approve' && $id > 0) {
    $product_query = "SELECT product_id FROM product_reviews WHERE id = ?";
    $product_stmt = $connection->prepare($product_query);
    $product_stmt->bind_param("i", $id);
    $product_stmt->execute();
    $product_data = $product_stmt->get_result()->fetch_assoc();
    
    $query = "UPDATE product_reviews SET is_approved = TRUE WHERE id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    if ($product_data) {
        update_product_rating($connection, $product_data['product_id']);
    }
    
    header("Location: reviews.php?approved=1");
    exit;
}

if ($action === 'delete' && $id > 0) {
    $product_query = "SELECT product_id FROM product_reviews WHERE id = ?";
    $product_stmt = $connection->prepare($product_query);
    $product_stmt->bind_param("i", $id);
    $product_stmt->execute();
    $product_data = $product_stmt->get_result()->fetch_assoc();
    
    $query = "DELETE FROM product_reviews WHERE id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    if ($product_data) {
        update_product_rating($connection, $product_data['product_id']);
    }
    
    header("Location: reviews.php?deleted=1");
    exit;
}

list($where, $params, $types) = $filter->getSQL();

$count_query = "SELECT COUNT(*) as total FROM product_reviews r $where";
if (!empty($params)) {
    $count_stmt = $connection->prepare($count_query);
    if (!empty($types)) {
        $count_stmt->bind_param($types, ...$params);
    }
    $count_stmt->execute();
    $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
} else {
    $count_result = mysqli_query($connection, "SELECT COUNT(*) as total FROM product_reviews");
    $total_records = mysqli_fetch_assoc($count_result)['total'];
}

$pagination = new Pagination($total_records, 20);
$offset = $pagination->getOffset();
$limit = $pagination->getLimit();

$query = "SELECT r.*, u.name as user_name, u.email as user_email, u.avatar as user_avatar, p.name as product_name
          FROM product_reviews r
          LEFT JOIN users u ON r.user_id = u.id
          LEFT JOIN products p ON r.product_id = p.id
          $where
          ORDER BY r.created_at DESC
          LIMIT $limit OFFSET $offset";

if (!empty($params)) {
    $stmt = $connection->prepare($query);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $reviews = mysqli_fetch_all($result, MYSQLI_ASSOC);
} else {
    $result = mysqli_query($connection, $query);
    $reviews = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

$pending_query = "SELECT COUNT(*) as count FROM product_reviews WHERE is_approved = FALSE";
$pending_result = mysqli_query($connection, $pending_query);
$pending_count = mysqli_fetch_assoc($pending_result)['count'];
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Модерация отзывов - HvostX</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include 'sidebar.php'; ?>

        <div id="page-content-wrapper" class="w-100">
            <?php include 'navbar.php'; ?>

            <div class="container-fluid px-4 py-4">
                <h1 class="mb-4">
                    <i class="fas fa-comments me-2"></i>
                    Модерация отзывов
                    <?php if ($filter === 'pending' && $pending_count > 0): ?>
                        <span class="badge bg-danger"><?php echo $pending_count; ?></span>
                    <?php endif; ?>
                </h1>

                <?php if (isset($_GET['approved'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i> Отзыв одобрен!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (isset($_GET['deleted'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-trash-alt me-2"></i> Отзыв удален!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php echo $filter->render(); ?>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Отзывы (<?php echo count($reviews); ?>)</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($reviews)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Нет отзывов для отображения</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 60px;">ID</th>
                                        <th style="width: 80px;">Оценка</th>
                                        <th>Пользователь</th>
                                        <th>Товар</th>
                                        <th>Отзыв</th>
                                        <th style="width: 120px;">Дата</th>
                                        <th style="width: 100px;">Статус</th>
                                        <th style="width: 150px;">Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reviews as $review): ?>
                                    <tr>
                                        <td><?php echo $review['id']; ?></td>
                                        <td>
                                            <div class="text-warning">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?php echo $i <= $review['rating'] ? '' : 'text-muted'; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="me-2">
                                                    <?php if (!empty($review['user_avatar']) && file_exists('../assets/images/avatars/' . $review['user_avatar'])): ?>
                                                        <img src="../assets/images/avatars/<?php echo htmlspecialchars($review['user_avatar']); ?>"
                                                             alt="<?php echo htmlspecialchars($review['user_name']); ?>"
                                                             class="rounded-circle"
                                                             style="width: 40px; height: 40px; object-fit: cover; border: 2px solid #e9ecef;">
                                                    <?php else: ?>
                                                        <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white"
                                                             style="width: 40px; height: 40px; font-size: 0.9rem; font-weight: bold;">
                                                            <?php echo mb_strtoupper(mb_substr($review['user_name'] ?? 'А', 0, 1)); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <div><?php echo htmlspecialchars($review['user_name'] ?? 'Аноним'); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($review['user_email'] ?? ''); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="../product_single.php?id=<?php echo $review['product_id']; ?>" target="_blank" class="text-decoration-none">
                                                <?php echo htmlspecialchars($review['product_name'] ?? 'Товар удален'); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php if (!empty($review['comment'])): ?>
                                                <button class="btn btn-sm btn-link text-start p-0" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#reviewModal<?php echo $review['id']; ?>"
                                                        style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                    <?php echo htmlspecialchars($review['comment']); ?>
                                                </button>
                                                
                                                <div class="modal fade" id="reviewModal<?php echo $review['id']; ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Отзыв на товар</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <div class="d-flex align-items-center mb-3">
                                                                    <div class="me-3">
                                                                        <?php if (!empty($review['user_avatar'])): ?>
                                                                            <img src="../assets/images/avatars/<?php echo htmlspecialchars($review['user_avatar']); ?>"
                                                                                 alt="<?php echo htmlspecialchars($review['user_name']); ?>"
                                                                                 class="rounded-circle"
                                                                                 style="width: 60px; height: 60px; object-fit: cover; border: 2px solid #e9ecef;">
                                                                        <?php else: ?>
                                                                            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white"
                                                                                 style="width: 60px; height: 60px; font-size: 1.5rem; font-weight: bold;">
                                                                                <?php echo mb_strtoupper(mb_substr($review['user_name'] ?? 'А', 0, 1)); ?>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <div>
                                                                        <h5 class="mb-1"><?php echo htmlspecialchars($review['user_name'] ?? 'Аноним'); ?></h5>
                                                                        <p class="mb-0 text-muted"><?php echo htmlspecialchars($review['user_email'] ?? ''); ?></p>
                                                                    </div>
                                                                </div>
                                                                <p><strong>Товар:</strong> <?php echo htmlspecialchars($review['product_name'] ?? 'Товар удален'); ?></p>
                                                                <p><strong>Оценка:</strong> 
                                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                        <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                                                    <?php endfor; ?>
                                                                </p>
                                                                <hr>
                                                                <p><strong>Текст отзыва:</strong></p>
                                                                <p><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                                                <hr>
                                                                <p><small class="text-muted">Дата: <?php echo date('d.m.Y H:i', strtotime($review['created_at'])); ?></small></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <?php if (!$review['is_approved']): ?>
                                                                <a href="reviews.php?action=approve&id=<?php echo $review['id']; ?>" class="btn btn-success">
                                                                    <i class="fas fa-check me-1"></i> Одобрить
                                                                </a>
                                                                <?php endif; ?>
                                                                <a href="reviews.php?action=delete&id=<?php echo $review['id']; ?>" class="btn btn-danger" onclick="return confirm('Вы уверены, что хотите удалить этот отзыв?')">
                                                                    <i class="fas fa-trash me-1"></i> Удалить
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">Без комментария</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('d.m.Y H:i', strtotime($review['created_at'])); ?></td>
                                        <td>
                                            <?php if ($review['is_approved']): ?>
                                                <span class="badge bg-success">Одобрен</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">На проверке</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <?php if (!$review['is_approved']): ?>
                                                <a href="reviews.php?action=approve&id=<?php echo $review['id']; ?>" 
                                                   class="btn btn-success" 
                                                   title="Одобрить">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <?php endif; ?>
                                                <a href="reviews.php?action=delete&id=<?php echo $review['id']; ?>" 
                                                   class="btn btn-danger" 
                                                   title="Удалить"
                                                   onclick="return confirm('Вы уверены, что хотите удалить этот отзыв?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
