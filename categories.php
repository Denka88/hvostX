<?php
// /categories.php - Страница категорий животных
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';

// Получаем все активные категории
$categories_query = "SELECT c.*, 
                     (SELECT COUNT(*) FROM products p WHERE p.pet_category_id = c.id AND p.is_active = 1) as products_count
                     FROM pet_categories c 
                     WHERE c.is_active = 1 
                     ORDER BY c.display_order ASC, c.name ASC";
$categories = mysqli_query($connection, $categories_query);
$categories_list = mysqli_fetch_all($categories, MYSQLI_ASSOC);

$page_title = "Категории товаров - HvostX";
?>

<?php include 'includes/header.php'; ?>

<div class="main-container animate-fade-in">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Главная</a></li>
            <li class="breadcrumb-item active" aria-current="page">Категории</li>
        </ol>
    </nav>

    <h1 class="mb-4">Категории товаров</h1>
    <p class="lead text-muted mb-5">Выберите категорию вашего питомца, чтобы увидеть все товары для него</p>

    <?php if (empty($categories_list)): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-2"></i>
        Категории временно недоступны. Попробуйте позже.
    </div>
    <?php else: ?>
    <div class="row g-4">
        <?php foreach ($categories_list as $category): ?>
        <div class="col-lg-4 col-md-6">
            <a href="products.php?category=<?php echo htmlspecialchars($category['slug']); ?>"
               class="text-decoration-none">
                <div class="card category-card h-100 animate-scale-hover">
                    <div class="category-card-image position-relative">
                        <?php if (!empty($category['image'])): ?>
                        <img src="assets/images/categories/<?php echo htmlspecialchars($category['image']); ?>"
                             class="card-img-top" alt="<?php echo htmlspecialchars($category['name']); ?>"
                             style="height: 200px; object-fit: cover;">
                        <?php else: ?>
                        <div class="category-placeholder" style="height: 200px; background: linear-gradient(135deg, #19875422, #19875444); display: flex; align-items: center; justify-content: center;">
                            <span style="font-size: 3rem; color: #198754; font-weight: bold;">
                                <?php echo htmlspecialchars(mb_substr($category['name'], 0, 1)); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        <div class="category-overlay position-absolute top-0 start-0 w-100 h-100"
                             style="background: rgba(0,0,0,0.3); opacity: 0; transition: opacity 0.3s;"></div>
                    </div>
                    <div class="card-body text-center">
                        <h5 class="card-title mb-2"><?php echo htmlspecialchars($category['name']); ?></h5>
                        <?php if (!empty($category['description'])): ?>
                        <p class="card-text text-muted small"><?php echo htmlspecialchars(mb_substr($category['description'], 0, 80)); ?>...</p>
                        <?php endif; ?>
                        <div class="category-products-count mt-3">
                            <span class="badge" style="background-color: #198754; font-size: 0.9rem;">
                                <i class="fas fa-box me-1"></i> <?php echo $category['products_count']; ?> товаров
                            </span>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent text-center">
                        <span class="btn btn-sm" style="background-color: #198754; color: #fff;">
                            <i class="fas fa-arrow-right me-2"></i>Смотреть товары
                        </span>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Секция с популярными товарами -->
    <section class="popular-products mt-5 pt-4">
        <h2 class="section-title mb-4">Популярные товары</h2>
        <div class="row">
            <?php
            $popular_query = "SELECT p.*, pc.name as category_name, pc.color as category_color
                              FROM products p
                              LEFT JOIN pet_categories pc ON p.pet_category_id = pc.id
                              WHERE p.is_active = 1
                              ORDER BY p.avg_rating DESC, p.review_count DESC
                              LIMIT 4";
            $popular_result = mysqli_query($connection, $popular_query);
            $popular_products = mysqli_fetch_all($popular_result, MYSQLI_ASSOC);
            
            if (!empty($popular_products)):
            ?>
            <?php foreach ($popular_products as $product): ?>
            <div class="col-md-3 mb-4">
                <div class="card h-100 product-card">
                    <?php if ($product['is_active']): ?>
                    <span class="badge bg-success position-absolute" style="top: 10px; right: 10px; z-index: 10;">В наличии</span>
                    <?php endif; ?>

                    <?php if (!empty($product['category_name'])): ?>
                    <span class="badge position-absolute" style="top: 10px; left: 10px; z-index: 10; background-color: #198754;">
                        <?php echo htmlspecialchars($product['category_name']); ?>
                    </span>
                    <?php endif; ?>

                    <img src="assets/images/products/<?php echo htmlspecialchars($product['image']); ?>"
                         class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>"
                         style="height: 200px; object-fit: cover;">

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

                        <h5 class="card-title mb-2" style="font-size: 0.95rem;"><?php echo htmlspecialchars($product['name']); ?></h5>
                        <p class="text-success fw-bold fs-5 mb-3"><?php echo number_format($product['price'], 0, '.', ' '); ?> ₽</p>

                        <div class="d-grid gap-2">
                            <a href="product_single.php?id=<?php echo $product['id']; ?>"
                               class="btn btn-primary btn-sm">
                                <i class="fas fa-eye me-2"></i> Подробнее
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Популярные товары временно отсутствуют.
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<style>
.category-card {
    transition: all 0.3s ease;
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}

.category-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}

.category-card:hover .category-overlay {
    opacity: 1;
}

.category-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.animate-scale-hover {
    transition: transform 0.3s ease;
}

.animate-scale-hover:hover {
    transform: scale(1.03);
}

.category-card-image {
    overflow: hidden;
}

.category-card-image img {
    transition: transform 0.3s ease;
}

.category-card:hover .category-card-image img {
    transform: scale(1.1);
}
</style>

<?php include 'includes/footer.php'; ?>
