<?php
// /product_single.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';

$product_id = $_GET['id'] ?? 0;

$query = "SELECT p.*, c.name as category_name FROM products p
          LEFT JOIN categories c ON p.category_id = c.id
          WHERE p.id = ? AND p.is_active = 1";
$stmt = $connection->prepare($query);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    header("Location: products.php");
    exit;
}

$reviews_query = "SELECT r.*, u.name as user_name, u.avatar as user_avatar
                  FROM product_reviews r
                  LEFT JOIN users u ON r.user_id = u.id
                  WHERE r.product_id = ? AND r.is_approved = TRUE
                  ORDER BY r.created_at DESC LIMIT 10";
$reviews_stmt = $connection->prepare($reviews_query);
$reviews_stmt->bind_param("i", $product_id);
$reviews_stmt->execute();
$reviews = $reviews_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stats_query = "SELECT
                COUNT(*) as total,
                AVG(rating) as average,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
                FROM product_reviews
                WHERE product_id = ? AND is_approved = TRUE";
$stats_stmt = $connection->prepare($stats_query);
$stats_stmt->bind_param("i", $product_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

$user_has_review = false;
if (isset($_SESSION['user_id'])) {
    $check_review_query = "SELECT id FROM product_reviews WHERE user_id = ? AND product_id = ?";
    $check_review_stmt = $connection->prepare($check_review_query);
    $check_review_stmt->bind_param("ii", $_SESSION['user_id'], $product_id);
    $check_review_stmt->execute();
    $user_has_review = (bool)$check_review_stmt->get_result()->fetch_assoc();
}

$related_query = "SELECT * FROM products WHERE category_id = ? AND id != ? AND is_active = 1 LIMIT 4";
$related_stmt = $connection->prepare($related_query);
$related_stmt->bind_param("ii", $product['category_id'], $product_id);
$related_stmt->execute();
$related_products = $related_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = $product['name'] . " - HvostX";
?>

<?php include 'includes/header.php'; ?>

<div class="main-container animate-fade-in">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Главная</a></li>
            <li class="breadcrumb-item"><a href="products.php">Товары</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($product['name']); ?></li>
        </ol>
    </nav>

    <div class="row mb-5">
        <div class="col-lg-6">
            <div class="product-gallery mb-3">
                <img src="assets/images/products/<?php echo htmlspecialchars($product['image']); ?>"
                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                     class="img-fluid rounded product-main-image">
            </div>
        </div>

        <div class="col-lg-6">
            <h1 class="mb-3"><?php echo htmlspecialchars($product['name']); ?></h1>

            <div class="d-flex align-items-center mb-3">
                <span class="badge bg-success me-2">В наличии</span>
                <?php if ($product['category_name']): ?>
                <span class="text-muted">Категория: <?php echo htmlspecialchars($product['category_name']); ?></span>
                <?php endif; ?>
            </div>

            <!-- Рейтинг товара -->
            <div class="product-rating mb-3">
                <div class="d-flex align-items-center">
                    <div class="stars-rating-large me-2">
                        <?php
                        $avg_rating = $product['avg_rating'] ?? ($stats['average'] ?? 0);
                        $review_count = $product['review_count'] ?? ($stats['total'] ?? 0);
                        for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star <?php echo $i <= round($avg_rating) ? 'text-warning' : 'text-muted'; ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <span class="fw-bold me-2"><?php echo number_format($avg_rating, 1); ?></span>
                    <span class="text-muted">(<?php echo $review_count; ?> <?php echo $review_count == 1 ? 'отзыв' : ($review_count < 5 ? 'отзыва' : 'отзывов'); ?>)</span>
                </div>
            </div>

            <div class="product-price mb-4">
                <h3 class="text-success"><?php echo number_format($product['price'], 0, '.', ' '); ?> ₽</h3>
            </div>

            <div class="product-description mb-4">
                <h4>Описание</h4>
                <div id="product-description-content" style="max-height: 150px; overflow: hidden; transition: max-height 0.3s ease;">
                    <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                </div>
                <button type="button" class="btn btn-link btn-sm mt-2 p-0" id="toggle-description" onclick="toggleDescription()">
                    <i class="bi bi-chevron-down me-1"></i><span>Развернуть</span>
                </button>
            </div>

            <div class="product-actions mb-4">
                <div class="d-flex align-items-center mb-3">
                    <label for="quantity" class="me-3">Количество:</label>
                    <input type="number" id="quantity" name="quantity" value="1" min="1" max="10"
                           class="form-control" style="width: 80px;">
                </div>

                <div class="d-grid gap-2 d-md-flex">
                    <button type="button" class="btn btn-primary btn-lg flex-grow-1 add-to-cart-btn" data-product-id="<?php echo $product['id']; ?>">
                        <i class="fas fa-shopping-cart me-2"></i> Добавить в корзину
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-lg favorite-btn" data-product-id="<?php echo $product['id']; ?>">
                        <i class="fas fa-heart me-2"></i> В избранное
                    </button>
                </div>
            </div>

            <div class="product-meta">
                <h5>Характеристики</h5>
                <ul class="list-unstyled">
                    <li><strong>Категория:</strong> <?php echo htmlspecialchars($product['category_name'] ?? 'Не указано'); ?></li>
                    <li><strong>Артикул:</strong> HV-<?php echo str_pad($product['id'], 5, '0', STR_PAD_LEFT); ?></li>
                    <li><strong>Страна:</strong> Россия</li>
                </ul>
            </div>
        </div>
    </div>

    <?php if (!empty($related_products)): ?>
    <section class="related-products mb-5">
        <h2 class="section-title mb-4">Похожие товары</h2>
        <div class="row">
            <?php foreach ($related_products as $related_product): ?>
            <div class="col-md-3 mb-4">
                <div class="card h-100">
                    <img src="assets/images/products/<?php echo htmlspecialchars($related_product['image']); ?>"
                         class="card-img-top" alt="<?php echo htmlspecialchars($related_product['name']); ?>">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($related_product['name']); ?></h5>
                        <p class="text-success fw-bold"><?php echo number_format($related_product['price'], 0, '.', ' '); ?> ₽</p>
                        <a href="product_single.php?id=<?php echo $related_product['id']; ?>"
                           class="btn btn-sm btn-outline-primary">Подробнее</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Секция отзывов -->
    <section class="reviews-section mb-5">
        <h2 class="section-title mb-4">Отзывы о товаре</h2>
        
        <div class="reviews-summary card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-4 text-center">
                        <div class="display-4 fw-bold text-warning"><?php echo number_format($stats['average'] ?? 0, 1); ?></div>
                        <div class="stars-rating-large mb-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?php echo $i <= round($stats['average'] ?? 0) ? 'text-warning' : 'text-muted'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <div class="text-muted"><?php echo $stats['total'] ?? 0; ?> <?php echo ($stats['total'] ?? 0) == 1 ? 'отзыв' : (($stats['total'] ?? 0) < 5 ? 'отзыва' : 'отзывов'); ?></div>
                    </div>
                    <div class="col-md-8">
                        <div class="rating-bars">
                            <?php
                            $rating_counts = [
                                5 => $stats['five_star'] ?? 0,
                                4 => $stats['four_star'] ?? 0,
                                3 => $stats['three_star'] ?? 0,
                                2 => $stats['two_star'] ?? 0,
                                1 => $stats['one_star'] ?? 0
                            ];
                            $total = $stats['total'] ?? 1;
                            foreach ($rating_counts as $stars => $count):
                                $percentage = $total > 0 ? ($count / $total) * 100 : 0;
                            ?>
                            <div class="rating-bar-item mb-2">
                                <div class="d-flex align-items-center">
                                    <span class="me-2" style="width: 60px;"><?php echo $stars; ?> <i class="fas fa-star text-warning"></i></span>
                                    <div class="progress flex-grow-1" style="height: 8px;">
                                        <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                    <span class="ms-2 text-muted" style="width: 30px;"><?php echo $count; ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Форма добавления отзыва -->
        <?php if (isset($_SESSION['user_id'])): ?>
            <?php if (!$user_has_review): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Оставить отзыв</h5>
                </div>
                <div class="card-body">
                    <form id="review-form" data-product-id="<?php echo $product_id; ?>">
                        <div class="mb-3">
                            <label class="form-label">Ваша оценка</label>
                            <div class="stars-input">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <input type="radio" name="rating" value="<?php echo $i; ?>" id="star<?php echo $i; ?>" class="d-none">
                                <label for="star<?php echo $i; ?>" class="star-label" data-value="<?php echo $i; ?>"><i class="far fa-star"></i></label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="comment" class="form-label">Ваш отзыв</label>
                            <textarea class="form-control" id="comment" name="comment" rows="4" placeholder="Расскажите о вашем опыте использования товара..." required></textarea>
                        </div>
                        <div class="mb-3">
                            <div class="g-recaptcha" data-sitekey="6Ld4XXssAAAAABPizR6w4L3c5sx_okDAVXdP4YaD"></div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i> Отправить отзыв
                        </button>
                    </form>
                </div>
            </div>
            <?php else: ?>
            <div class="alert alert-info mb-4">
                <i class="fas fa-info-circle me-2"></i> Вы уже оставляли отзыв на этот товар.
            </div>
            <?php endif; ?>
        <?php else: ?>
        <div class="alert alert-warning mb-4">
            <i class="fas fa-exclamation-triangle me-2"></i> <a href="account.php" class="alert-link">Войдите</a>, чтобы оставить отзыв.
        </div>
        <?php endif; ?>

        <!-- Список отзывов -->
        <div class="reviews-list" id="reviews-list">
            <?php if (empty($reviews)): ?>
            <div class="alert alert-info">
                <i class="fas fa-comment-dots me-2"></i> Пока нет отзывов. Будьте первым!
            </div>
            <?php else: ?>
                <?php foreach ($reviews as $review): ?>
                <div class="card mb-3 review-item">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <?php if (!empty($review['user_avatar'])): ?>
                                        <img src="assets/images/avatars/<?php echo htmlspecialchars($review['user_avatar']); ?>" 
                                             alt="<?php echo htmlspecialchars($review['user_name']); ?>" 
                                             class="rounded-circle" 
                                             style="width: 50px; height: 50px; object-fit: cover; border: 2px solid #e9ecef;">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white" 
                                             style="width: 50px; height: 50px; font-size: 1.2rem; font-weight: bold;">
                                            <?php echo mb_strtoupper(mb_substr($review['user_name'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($review['user_name']); ?></h6>
                                    <div class="stars-rating-small">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>
                            <small class="text-muted"><?php echo date('d.m.Y', strtotime($review['created_at'])); ?></small>
                        </div>
                        <?php if (!empty($review['comment'])): ?>
                        <p class="card-text"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>

<script>
document.querySelectorAll('.stars-input').forEach(container => {
    const labels = Array.from(container.querySelectorAll('.star-label'));
    const radios = Array.from(container.querySelectorAll('input[type="radio"]'));

    labels.forEach(label => {
        label.addEventListener('click', function() {
            const value = parseInt(this.dataset.value);
            updateStars(labels, value);
        });

        label.addEventListener('mouseenter', function() {
            const value = parseInt(this.dataset.value);
            highlightStars(labels, value);
        });
    });

    container.addEventListener('mouseleave', function() {
        const checked = container.querySelector('input[type="radio"]:checked');
        const value = checked ? parseInt(checked.value) : 0;
        updateStars(labels, value);
    });
});

function highlightStars(labels, hoverValue) {
    labels.forEach(label => {
        const value = parseInt(label.dataset.value);
        const icon = label.querySelector('i');
        if (value <= hoverValue) {
            icon.classList.remove('far');
            icon.classList.add('fas', 'text-warning');
        } else {
            icon.classList.remove('fas', 'text-warning');
            icon.classList.add('far');
        }
    });
}

function updateStars(labels, value) {
    labels.forEach(label => {
        const labelValue = parseInt(label.dataset.value);
        const icon = label.querySelector('i');
        if (labelValue <= value) {
            icon.classList.remove('far');
            icon.classList.add('fas', 'text-warning');
        } else {
            icon.classList.remove('fas', 'text-warning');
            icon.classList.add('far');
        }
    });
}

document.getElementById('review-form')?.addEventListener('submit', function(e) {
    e.preventDefault();

    const form = this;
    const productId = form.dataset.productId;
    const rating = form.querySelector('input[name="rating"]:checked')?.value;
    const comment = form.querySelector('#comment').value.trim();
    
    // Получаем widget ID и токен reCAPTCHA
    const recaptchaWidget = form.querySelector('.g-recaptcha');
    const widgetId = recaptchaWidget?.getAttribute('data-widget-id');
    
    let recaptchaResponse = null;
    if (typeof grecaptcha !== 'undefined' && widgetId) {
        recaptchaResponse = grecaptcha.getResponse(widgetId);
    }

    if (!rating) {
        alert('Пожалуйста, выберите оценку');
        return;
    }

    if (!comment) {
        alert('Пожалуйста, напишите отзыв');
        return;
    }

    if (!recaptchaResponse) {
        alert('Подтвердите, что вы не робот (reCAPTCHA)');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('product_id', productId);
    formData.append('rating', rating);
    formData.append('comment', comment);
    formData.append('g-recaptcha-response', recaptchaResponse);

    fetch('api_reviews.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert(data.message);
            form.reset();
            const labels = form.querySelectorAll('.star-label');
            labels.forEach(label => {
                const icon = label.querySelector('i');
                icon.classList.remove('fas', 'text-warning');
                icon.classList.add('far');
            });
            if (typeof grecaptcha !== 'undefined' && widgetId) {
                grecaptcha.reset(widgetId);
            }
            setTimeout(() => location.reload(), 1000);
        } else {
            alert(data.message || 'Ошибка при отправке отзыва');
            if (typeof grecaptcha !== 'undefined' && widgetId) {
                grecaptcha.reset(widgetId);
            }
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        alert('Произошла ошибка при отправке отзыва');
        if (typeof grecaptcha !== 'undefined' && widgetId) {
            grecaptcha.reset(widgetId);
        }
    });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const isLoggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;

    function updateFavoriteButton(productId, isFavorite) {
        const btn = document.querySelector(`.favorite-btn[data-product-id="${productId}"]`);
        if (btn) {
            const icon = btn.querySelector('i');
            if (isFavorite) {
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
        }
        updateFavoritesCount();
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
                    updateFavoriteButton(productId, data.is_favorite);
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

    function checkFavoriteStatus(productId) {
        fetch(`api_favorites.php?action=check&product_id=${productId}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    updateFavoriteButton(productId, data.is_favorite);
                }
            })
            .catch(error => console.error('Ошибка проверки избранного:', error));
    }

    function updateFavoritesCount() {
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

    const productId = <?php echo $product['id']; ?>;
    if (isLoggedIn) {
        checkFavoriteStatus(productId);
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

function toggleDescription() {
    const content = document.getElementById('product-description-content');
    const btn = document.getElementById('toggle-description');
    const icon = btn.querySelector('i');
    const text = btn.querySelector('span');

    if (content.style.maxHeight === 'none') {
        content.style.maxHeight = '150px';
        icon.classList.remove('bi-chevron-up');
        icon.classList.add('bi-chevron-down');
        text.textContent = 'Развернуть';
    } else {
        content.style.maxHeight = 'none';
        icon.classList.remove('bi-chevron-down');
        icon.classList.add('bi-chevron-up');
        text.textContent = 'Свернуть';
    }
}

// Инициализация reCAPTCHA виджета для формы отзывов
document.addEventListener('DOMContentLoaded', function() {
    const reviewForm = document.getElementById('review-form');
    if (reviewForm && typeof grecaptcha !== 'undefined') {
        const recaptchaContainer = reviewForm.querySelector('.g-recaptcha');
        if (recaptchaContainer && !recaptchaContainer.getAttribute('data-widget-id')) {
            const widgetId = grecaptcha.render(recaptchaContainer, {
                sitekey: recaptchaContainer.getAttribute('data-sitekey'),
                callback: function(response) {
                    // Токен получен
                },
                'expired-callback': function() {
                    // Токен истек
                }
            });
            recaptchaContainer.setAttribute('data-widget-id', widgetId);
        }
    }
});
</script>