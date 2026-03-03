<?php
// /products.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';

$search = $_GET['search'] ?? '';

// Получаем выбранные теги из GET параметра
$selected_tags = isset($_GET['tags']) && is_array($_GET['tags']) ? $_GET['tags'] : [];
$selected_tags = array_map('intval', $selected_tags);
$selected_tags = array_filter($selected_tags); // Убираем нули

// Строим базовый запрос с учетом поиска
$base_where = "WHERE p.is_active = 1";
$params = [];
$types = "";

if (!empty($search)) {
    $search_escaped = mysqli_real_escape_string($connection, $search);
    $base_where .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search_escaped%";
    $params[] = "%$search_escaped%";
    $types .= "ss";
}

// Получаем товары, соответствующие ВСЕМ выбранным тегам (AND логика)
if (!empty($selected_tags)) {
    // Подзапрос: находим товары, у которых есть ВСЕ выбранные теги
    $tag_placeholders = implode(',', array_fill(0, count($selected_tags), '?'));
    $params = array_merge($params, $selected_tags);
    $types .= str_repeat('i', count($selected_tags));
    
    $base_where .= " AND p.id IN (
        SELECT pt.product_id 
        FROM product_tags pt 
        WHERE pt.tag_id IN ($tag_placeholders)
        GROUP BY pt.product_id
        HAVING COUNT(DISTINCT pt.tag_id) = " . count($selected_tags) . "
    )";
}

// Получаем все товары, соответствующие условиям (для определения доступных тегов)
$all_products_query = "SELECT DISTINCT p.id FROM products p $base_where";
$stmt = $connection->prepare($all_products_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$matching_product_ids = [];
while ($row = $result->fetch_assoc()) {
    $matching_product_ids[] = $row['id'];
}

// Получаем теги, которые есть у найденных товаров
$tags = [];
if (!empty($matching_product_ids)) {
    $ids_placeholders = implode(',', array_fill(0, count($matching_product_ids), '?'));
    $available_tags_query = "SELECT DISTINCT t.* FROM tags t 
                             INNER JOIN product_tags pt ON t.id = pt.tag_id 
                             WHERE pt.product_id IN ($ids_placeholders) 
                             AND t.is_active = 1
                             ORDER BY t.name";
    $available_stmt = $connection->prepare($available_tags_query);
    $available_stmt->bind_param(str_repeat('i', count($matching_product_ids)), ...$matching_product_ids);
    $available_stmt->execute();
    $tags = $available_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Считаем количество товаров для пагинации
$count_query = "SELECT COUNT(DISTINCT p.id) as total FROM products p $base_where";
$count_stmt = $connection->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];

$pagination = new Pagination($total_records, 12);
$offset = $pagination->getOffset();
$limit = $pagination->getLimit();

// Получаем товары с пагинацией
$query = "SELECT DISTINCT p.*,
          COALESCE((SELECT SUM(oi.quantity) FROM order_items oi WHERE oi.product_id = p.id), 0) as total_sold,
          COALESCE(p.avg_rating, 0) as avg_rating,
          COALESCE(p.review_count, 0) as review_count
          FROM products p
          $base_where 
          ORDER BY p.name 
          LIMIT $limit OFFSET $offset";

$stmt = $connection->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$products = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Формируем URL для пагинации
$base_url = 'products.php';
$url_params = [];
if (!empty($search)) $url_params[] = 'search=' . urlencode($search);
if (!empty($selected_tags)) {
    foreach ($selected_tags as $tag_id) {
        $url_params[] = 'tags[]=' . $tag_id;
    }
}
if (!empty($url_params)) $base_url .= '?' . implode('&', $url_params);

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

    <?php if (!empty($selected_tags)): ?>
    <div class="alert alert-info mb-4">
        <i class="fas fa-tags me-2"></i>
        Выбранные теги (товары должны иметь все выбранные теги):
        <?php
        $tag_names = [];
        foreach ($selected_tags as $tag_id) {
            foreach ($tags as $tag) {
                if ($tag['id'] == $tag_id) {
                    $tag_names[] = '<span class="badge ms-1" style="background-color: ' . htmlspecialchars($tag['color']) . ';">' . htmlspecialchars($tag['name']) . '</span>';
                }
            }
        }
        echo implode(' ', $tag_names);
        ?>
        <a href="products.php?<?php echo http_build_query(array_filter($_GET, function($k) { return $k !== 'tags'; }, ARRAY_FILTER_USE_KEY)); ?>" class="btn btn-sm btn-outline-secondary ms-3">Сбросить теги</a>
    </div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-lg-3 col-md-4 mb-4">
            <div class="card filter-card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Фильтрация</h5>
                </div>
                <div class="card-body">
                    <h6 class="mb-3">
                        Теги
                        <small class="text-muted d-block" style="font-size: 0.75rem;">(нажмите, чтобы добавить/удалить)</small>
                    </h6>
                    <div class="d-flex flex-wrap gap-2">
                        <?php if (empty($tags)): ?>
                        <p class="text-muted small">Нет доступных тегов для текущих условий</p>
                        <?php else: ?>
                        <?php foreach ($tags as $tag): ?>
                        <?php
                        $is_selected = in_array($tag['id'], $selected_tags);
                        // Формируем новый список тегов: если выбран - убираем, если нет - добавляем
                        if ($is_selected) {
                            $new_tags = array_diff($selected_tags, [$tag['id']]);
                        } else {
                            $new_tags = array_merge($selected_tags, [$tag['id']]);
                        }
                        $new_tags = array_values($new_tags); // переиндексируем
                        
                        $url_params_for_tag = $_GET;
                        if (!empty($new_tags)) {
                            $url_params_for_tag['tags'] = $new_tags;
                        } else {
                            unset($url_params_for_tag['tags']);
                        }
                        $new_url = 'products.php?' . http_build_query($url_params_for_tag);
                        ?>
                        <a href="<?php echo $new_url; ?>" 
                           class="badge text-decoration-none tag-filter <?php echo $is_selected ? 'selected' : ''; ?>"
                           style="background-color: <?php echo htmlspecialchars($tag['color']); ?>; color: #fff; padding: 0.5em 0.8em; border-radius: 20px; transition: all 0.2s; cursor: pointer;">
                            <?php echo htmlspecialchars($tag['name']); ?>
                            <?php if ($is_selected): ?>
                            <i class="fas fa-times ms-1"></i>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-9 col-md-8">
            <?php if (empty($products)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Товары не найдены.
            </div>
            <?php else: ?>
            <div class="row">
                <?php foreach ($products as $product): ?>
                <?php
                // Запрос тегов для каждого товара
                $product_tags_query = "SELECT t.id, t.name, t.color FROM tags t 
                                       INNER JOIN product_tags pt ON t.id = pt.tag_id 
                                       WHERE pt.product_id = ? 
                                       ORDER BY pt.created_at";
                $product_tags_stmt = $connection->prepare($product_tags_query);
                $product_tags_stmt->bind_param("i", $product['id']);
                $product_tags_stmt->execute();
                $product_tags = $product_tags_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $first_tag = $product_tags[0] ?? null;
                ?>
                <div class="col-md-4 col-sm-6 mb-4">
                    <div class="card h-100 product-card">
                        <?php if ($product['is_active']): ?>
                        <span class="badge bg-success position-absolute" style="top: 10px; right: 10px; z-index: 10;">В наличии</span>
                        <?php else: ?>
                        <span class="badge bg-secondary position-absolute" style="top: 10px; right: 10px; z-index: 10;">Нет в наличии</span>
                        <?php endif; ?>

                        <?php if ($first_tag): ?>
                        <span class="badge position-absolute" style="top: 10px; left: 10px; z-index: 10; background-color: <?php echo htmlspecialchars($first_tag['color']); ?>;">
                            <?php echo htmlspecialchars($first_tag['name']); ?>
                        </span>
                        <?php endif; ?>

                        <img src="assets/images/products/<?php echo htmlspecialchars($product['image']); ?>"
                             class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>">

                        <div class="card-body">
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

                            <!-- Теги товара -->
                            <?php if (!empty($product_tags)): ?>
                            <div class="mb-2">
                                <?php foreach ($product_tags as $ptag): ?>
                                <span class="badge me-1 mb-1" style="background-color: <?php echo htmlspecialchars($ptag['color']); ?>; font-size: 0.75rem;">
                                    <?php echo htmlspecialchars($ptag['name']); ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
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

<style>
.tag-filter:hover {
    opacity: 0.8;
    transform: scale(1.05);
}
.tag-filter.selected {
    box-shadow: 0 0 0 3px rgba(0,0,0,0.3);
    position: relative;
}
.tag-filter.selected::after {
    content: '✓';
    position: absolute;
    top: -5px;
    right: -5px;
    background: #fff;
    color: #000;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>

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
