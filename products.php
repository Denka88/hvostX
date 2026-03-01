<?php
// /products.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';

$category_id = $_GET['category'] ?? 0;

$search = $_GET['search'] ?? '';

$categories_query = "SELECT * FROM categories WHERE is_active = 1";
$categories_result = mysqli_query($connection, $categories_query);
$categories = mysqli_fetch_all($categories_result, MYSQLI_ASSOC);

$base_query = "SELECT p.*, c.name as category_name,
          COALESCE((SELECT SUM(oi.quantity) FROM order_items oi WHERE oi.product_id = p.id), 0) as total_sold,
          COALESCE(p.avg_rating, 0) as avg_rating,
          COALESCE(p.review_count, 0) as review_count
          FROM products p
          LEFT JOIN categories c ON p.category_id = c.id
          WHERE p.is_active = 1";

$where = "";
if ($category_id > 0) {
    $where .= " AND p.category_id = " . intval($category_id);
}

if (!empty($search)) {
    $search_escaped = mysqli_real_escape_string($connection, $search);
    $where .= " AND (p.name LIKE '%$search_escaped%' OR p.description LIKE '%$search_escaped%')";
}

$count_query = "SELECT COUNT(*) as total FROM products p WHERE p.is_active = 1" . $where;
$count_result = mysqli_query($connection, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];

$pagination = new Pagination($total_records, 12);
$offset = $pagination->getOffset();
$limit = $pagination->getLimit();

$query = $base_query . $where . " ORDER BY p.name LIMIT $limit OFFSET $offset";
$result = mysqli_query($connection, $query);
$products = mysqli_fetch_all($result, MYSQLI_ASSOC);

$base_url = 'products.php';
$params = [];
if ($category_id > 0) $params[] = 'category=' . $category_id;
if (!empty($search)) $params[] = 'search=' . urlencode($search);
if (!empty($params)) $base_url .= '?' . implode('&', $params);

$current_category = null;
if ($category_id > 0) {
    foreach ($categories as $cat) {
        if ($cat['id'] == $category_id) {
            $current_category = $cat;
            break;
        }
    }
}

$page_title = "Наши товары - HvostX";
?>

<?php include 'includes/header.php'; ?>

<div class="main-container animate-fade-in">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Главная</a></li>
            <li class="breadcrumb-item active" aria-current="page">Товары</li>
        </ol>
    </nav>

    <h1 class="mb-4">Наши товары</h1>

    <?php if (!empty($search)): ?>
    <div class="alert alert-info mb-4">
        <i class="fas fa-search me-2"></i>
        Результаты поиска по запросу: <strong><?php echo htmlspecialchars($search); ?></strong>
        <a href="products.php" class="btn btn-sm btn-outline-secondary ms-3">Сбросить поиск</a>
    </div>
    <?php endif; ?>

    <?php if ($current_category): ?>
    <div class="alert alert-info mb-4">
        <i class="fas fa-filter me-2"></i>
        Показаны товары из категории: <strong><?php echo htmlspecialchars($current_category['name']); ?></strong>
        <a href="products.php" class="btn btn-sm btn-outline-secondary ms-3">Сбросить фильтр</a>
    </div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-lg-3 col-md-4 mb-4">
            <div class="card filter-card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Фильтрация</h5>
                </div>
                <div class="card-body">
                    <h6 class="mb-3">Категории</h6>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <a href="products.php" class="<?php echo !$category_id ? 'active' : ''; ?>">
                                Все товары
                            </a>
                        </li>
                        <?php foreach ($categories as $category): ?>
                        <li class="mb-2">
                            <a href="products.php?category=<?php echo $category['id']; ?>"
                               class="<?php echo $category_id == $category['id'] ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-9 col-md-8">
            <?php if (empty($products)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                В этой категории нет товаров.
            </div>
            <?php else: ?>
            <div class="row">
                <?php foreach ($products as $product): ?>
                <div class="col-md-4 col-sm-6 mb-4">
                    <div class="card h-100 product-card">
                        <?php if ($product['is_active']): ?>
                        <span class="badge bg-success position-absolute" style="top: 10px; right: 10px;">В наличии</span>
                        <?php else: ?>
                        <span class="badge bg-secondary position-absolute" style="top: 10px; right: 10px;">Нет в наличии</span>
                        <?php endif; ?>

                        <img src="assets/images/products/<?php echo htmlspecialchars($product['image']); ?>"
                             class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>">

                        <div class="card-body">
                            <?php if ($product['category_name']): ?>
                            <span class="badge bg-info text-dark mb-2"><?php echo htmlspecialchars($product['category_name']); ?></span>
                            <?php endif; ?>

                            <!-- Рейтинг товара -->
                            <div class="product-rating mb-2">
                                <div class="stars-rating-small">
                                    <?php
                                    $avg_rating = $product['avg_rating'] ?? 0;
                                    for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= round($avg_rating) ? 'text-warning' : 'text-muted'; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <small class="text-muted"><?php echo number_format($avg_rating, 1); ?> (<?php echo $product['review_count'] ?? 0; ?>)</small>
                            </div>

                            <h5 class="card-title mb-2"><?php echo htmlspecialchars($product['name']); ?></h5>
                            <p class="card-text text-muted mb-2">
                                <?php echo mb_substr(htmlspecialchars($product['description']), 0, 80); ?>...
                            </p>
                            <p class="text-success fw-bold fs-4 mb-3"><?php echo number_format($product['price'], 0, '.', ' '); ?> ₽</p>

                            <?php if (!empty($product['total_sold']) && $product['total_sold'] > 0): ?>
                            <p class="text-muted mb-2 small">
                                <i class="fas fa-shopping-bag me-1"></i> Продано: <?php echo $product['total_sold']; ?>
                            </p>
                            <?php endif; ?>

                            <div class="d-grid gap-2">
                                <a href="product_single.php?id=<?php echo $product['id']; ?>"
                                   class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye me-2"></i> Подробнее
                                </a>
                                <button type="button" class="btn btn-outline-success btn-sm add-to-cart-btn" data-product-id="<?php echo $product['id']; ?>">
                                    <i class="fas fa-shopping-cart me-2"></i> В корзину
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm favorite-btn" data-product-id="<?php echo $product['id']; ?>">
                                    <i class="far fa-heart me-2"></i> В избранное
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php echo $pagination->render($base_url); ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const isLoggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;

    function updateFavoritesCount() {
        if (!isLoggedIn) return;

        fetch('api_favorites.php?action=count')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const badge = document.querySelector('.favorites-count-badge');
                    if (badge) {
                        if (data.total_favorites > 0) {
                            badge.textContent = data.total_favorites;
                            badge.style.display = 'inline';
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                }
            })
            .catch(error => console.error('Ошибка обновления счетчика:', error));
    }

    document.querySelectorAll('.favorite-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!isLoggedIn) {
                showNotificationError('Необходимо авторизоваться для добавления в избранное');
                setTimeout(() => {
                    window.location.href = 'account.php';
                }, 1500);
                return;
            }

            const productId = this.dataset.productId;
            const formData = new FormData();
            formData.append('action', 'toggle');
            formData.append('product_id', productId);

            fetch('api_favorites.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const icon = btn.querySelector('i');
                    if (data.is_favorite) {
                        btn.classList.remove('btn-outline-danger');
                        btn.classList.add('btn-danger');
                        icon.classList.remove('far');
                        icon.classList.add('fas');
                    } else {
                        btn.classList.remove('btn-danger');
                        btn.classList.add('btn-outline-danger');
                        icon.classList.remove('fas');
                        icon.classList.add('far');
                    }
                    updateFavoritesCount();
                } else {
                    alert(data.message || 'Ошибка при добавлении в избранное');
                }
            })
            .catch(error => {
                console.error('Ошибка:', error);
                alert('Произошла ошибка при добавлении в избранное');
            });
        });
    });

    if (isLoggedIn) {
        updateFavoritesCount();
    }
});

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
</script>

<?php include 'includes/footer.php'; ?>