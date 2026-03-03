<?php
// /index.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';

$query = "SELECT p.*,
          COALESCE((SELECT SUM(oi.quantity) FROM order_items oi WHERE oi.product_id = p.id), 0) as total_sold
          FROM products p
          WHERE p.is_active = 1
          ORDER BY p.avg_rating DESC, p.review_count DESC, total_sold DESC
          LIMIT 6";
$result = mysqli_query($connection, $query);

$news_query = "SELECT * FROM news WHERE is_active = 1 ORDER BY created_at DESC LIMIT 3";
$news_result = mysqli_query($connection, $news_query);

$page_title = "Главная - HvostX";
?>

<?php include 'includes/header.php'; ?>

<div class="main-container animate-fade-in">
    <section class="hero-section mb-5">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-4">Добро пожаловать в HvostX!</h1>
                <p class="lead mb-4">Ваш надежный поставщик качественных товаров для домашних животных. Мы заботимся о ваших питомцах так же, как и вы!</p>
                <a href="products.php" class="btn btn-primary btn-lg">Посмотреть все товары</a>
            </div>
            <div class="col-lg-6">
                <img src="assets/images/hero-pets.png" alt="Счастливые домашние животные" class="img-fluid rounded">
            </div>
        </div>
    </section>

    <section class="popular-products mb-5">
        <h2 class="mb-4">Популярные товары</h2>
        <div class="row">
            <?php 
            $tags_cache = [];
            $tags_all_query = "SELECT * FROM tags WHERE is_active = 1";
            $tags_all_result = mysqli_query($connection, $tags_all_query);
            while ($t = mysqli_fetch_assoc($tags_all_result)) {
                $tags_cache[$t['id']] = $t;
            }
            
            while ($product = mysqli_fetch_assoc($result)): 
            $first_tag = null;
            $product_tags_query = "SELECT t.id, t.name, t.color FROM tags t 
                                   INNER JOIN product_tags pt ON t.id = pt.tag_id 
                                   WHERE pt.product_id = " . (int)$product['id'] . " 
                                   ORDER BY pt.created_at LIMIT 1";
            $product_tags_result = mysqli_query($connection, $product_tags_query);
            if ($product_tags_result && mysqli_num_rows($product_tags_result) > 0) {
                $first_tag = mysqli_fetch_assoc($product_tags_result);
            }
            ?>
            <div class="col-md-4 mb-4">
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

                    <img src="assets/images/products/<?php echo htmlspecialchars($product['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>">

                    <div class="card-body">

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
            <?php endwhile; ?>
        </div>
    </section>

    <section class="about-section mb-5">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <img src="assets/images/about-us.jpg" alt="О нашей компании" class="img-fluid rounded">
            </div>
            <div class="col-lg-6">
                <h2 class="mb-4">О нашей компании</h2>
                <p>HvostX - это команда профессионалов, которые любят животных и хотят сделать их жизнь лучше. Мы работаем с 2010 года и за это время помогли тысячам домашних питомцев по всей России.</p>
                <p>Наша миссия - предоставлять качественные товары по доступным ценам, чтобы каждый питомец мог получать лучший уход.</p>
                <a href="about.php" class="btn btn-outline-primary">Узнать больше</a>
            </div>
        </div>
    </section>

    <section class="news-section mb-5">
        <h2 class="mb-4">Последние новости</h2>
        <div class="row">
            <?php while ($news_item = mysqli_fetch_assoc($news_result)): ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <img src="assets/images/news/<?php echo htmlspecialchars($news_item['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($news_item['title']); ?>">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($news_item['title']); ?></h5>
                        <p class="card-text"><?php echo mb_substr(strip_tags(htmlspecialchars($news_item['content'])), 0, 150); ?>...</p>
                        <a href="#" class="btn btn-outline-primary">Читать дальше</a>
                    </div>
                    <div class="card-footer">
                        <small class="text-muted"><?php echo date('d.m.Y', strtotime($news_item['created_at'])); ?></small>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </section>

    <section class="partners-section mb-5">
    <h2 class="mb-4">Наши партнеры</h2>
    <div class="row">
        <?php
        $partners_query = "SELECT * FROM partners WHERE is_active = 1 ORDER BY partner_order LIMIT 4";
        $partners_result = mysqli_query($connection, $partners_query);
        while ($partner = mysqli_fetch_assoc($partners_result)):
        ?>
        <div class="col-md-3 col-6 mb-4">
            <div class="card h-100 text-center index-partner-card">
                <div class="card-body d-flex flex-column justify-content-center align-items-center" style="min-height: 200px;">
                    <img src="assets/images/partners/<?php echo htmlspecialchars($partner['logo']); ?>"
                         alt="<?php echo htmlspecialchars($partner['name']); ?>"
                         class="partner-logo mb-3" style="max-height: 80px; object-fit: contain;">
                    <h5 class="card-title"><?php echo htmlspecialchars($partner['name']); ?></h5>
                    <a href="partners.php" class="btn btn-outline-primary">Подробнее</a>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
    <div class="text-center">
        <a href="partners.php" class="btn btn-outline-secondary">Все партнеры</a>
    </div>
</section>
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