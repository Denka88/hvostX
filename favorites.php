<?php
// /favorites.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: account.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$query = "SELECT f.*, p.name, p.description, p.price, p.image, p.is_active,
          c.name as category_name
          FROM favorites f
          INNER JOIN products p ON f.product_id = p.id
          LEFT JOIN categories c ON p.category_id = c.id
          WHERE f.user_id = ?
          ORDER BY f.created_at DESC";
$stmt = $connection->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$favorites = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = "Избранное - HvostX";
?>

<?php include 'includes/header.php'; ?>

<div class="main-container animate-fade-in">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Главная</a></li>
            <li class="breadcrumb-item active" aria-current="page">Избранное</li>
        </ol>
    </nav>

    <h1 class="mb-4">
        <i class="fas fa-heart text-danger me-2"></i>
        Избранное
        <?php if (!empty($favorites)): ?>
        <span class="badge bg-danger ms-2"><?php echo count($favorites); ?></span>
        <?php endif; ?>
    </h1>

    <?php if (empty($favorites)): ?>
    <div class="alert alert-info">
        <i class="fas fa-heart-broken me-2"></i>
        У вас пока нет избранных товаров. 
        <a href="products.php">Перейти к каталогу</a>, чтобы добавить товары в избранное.
    </div>
    <?php else: ?>
    <div class="row">
        <?php foreach ($favorites as $favorite): ?>
        <div class="col-md-4 col-sm-6 mb-4">
            <div class="card h-100 product-card">
                <?php if ($favorite['is_active']): ?>
                <span class="badge bg-success position-absolute" style="top: 10px; right: 10px;">В наличии</span>
                <?php else: ?>
                <span class="badge bg-secondary position-absolute" style="top: 10px; right: 10px;">Нет в наличии</span>
                <?php endif; ?>

                <img src="assets/images/products/<?php echo htmlspecialchars($favorite['image']); ?>"
                     class="card-img-top" alt="<?php echo htmlspecialchars($favorite['name']); ?>">

                <div class="card-body">
                    <?php if ($favorite['category_name']): ?>
                    <span class="badge bg-info text-dark mb-2"><?php echo htmlspecialchars($favorite['category_name']); ?></span>
                    <?php endif; ?>

                    <h5 class="card-title mb-2"><?php echo htmlspecialchars($favorite['name']); ?></h5>
                    <p class="card-text text-muted mb-2">
                        <?php echo mb_substr(htmlspecialchars($favorite['description']), 0, 80); ?>...
                    </p>
                    <p class="text-success fw-bold fs-4 mb-3"><?php echo number_format($favorite['price'], 2); ?> ₽</p>

                    <div class="d-grid gap-2">
                        <a href="product_single.php?id=<?php echo $favorite['product_id']; ?>"
                           class="btn btn-primary btn-sm">
                            <i class="fas fa-eye me-2"></i> Подробнее
                        </a>
                        <button type="button" class="btn btn-outline-success btn-sm add-to-cart-btn" data-product-id="<?php echo $favorite['product_id']; ?>">
                            <i class="fas fa-shopping-cart me-2"></i> В корзину
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm remove-from-favorites-btn" data-product-id="<?php echo $favorite['product_id']; ?>">
                            <i class="fas fa-heart-broken me-2"></i> Удалить из избранного
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="text-center mt-4">
        <a href="products.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i> Вернуться к покупкам
        </a>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

<!-- Модальное окно подтверждения удаления из избранного -->
<div class="modal fade" id="removeFromFavoritesModal" tabindex="-1" aria-labelledby="removeFromFavoritesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="removeFromFavoritesModalLabel">
                    <i class="fas fa-heart-broken me-2"></i>Удалить из избранного
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <p>Вы уверены, что хотите удалить этот товар из избранного?</p>
                <p class="text-muted mb-0" id="removeProductName"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Отмена
                </button>
                <button type="button" class="btn btn-danger" id="confirmRemoveFromFavorites">
                    <i class="fas fa-trash me-2"></i>Удалить
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let productToRemove = null;
    const removeModal = new bootstrap.Modal(document.getElementById('removeFromFavoritesModal'));
    
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

    document.querySelectorAll('.remove-from-favorites-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const productId = this.dataset.productId;
            const card = this.closest('.product-card');
            const productName = card.querySelector('.card-title')?.textContent || 'этот товар';

            productToRemove = { productId, card };
            document.getElementById('removeProductName').textContent = productName;
            removeModal.show();
        });
    });

    document.getElementById('confirmRemoveFromFavorites').addEventListener('click', function() {
        if (!productToRemove) return;

        const { productId, card } = productToRemove;

        const formData = new FormData();
        formData.append('action', 'remove');
        formData.append('product_id', productId);

        fetch('api_favorites.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                removeModal.hide();

                card.style.opacity = '0';
                card.style.transform = 'scale(0.9)';
                card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                setTimeout(() => {
                    card.remove();
                    const remainingCards = document.querySelectorAll('.product-card');
                    if (remainingCards.length === 0) {
                        location.reload();
                    } else {
                        const badge = document.querySelector('h1 .badge');
                        if (badge) {
                            badge.textContent = remainingCards.length;
                        }
                    }
                }, 300);
                updateFavoritesCount();

                showSuccessToast('Товар удален из избранного');
            } else {
                removeModal.hide();
                alert(data.message || 'Ошибка при удалении из избранного');
            }
        })
        .catch(error => {
            console.error('Ошибка:', error);
            removeModal.hide();
            alert('Произошла ошибка при удалении из избранного');
        });

        productToRemove = null;
    });

    function showSuccessToast(message) {
        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) {
            const container = document.createElement('div');
            container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = 'toast align-items-center text-white bg-success border-0';
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-check-circle me-2"></i>${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;

        document.querySelector('.toast-container').appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        
        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });
    }
});
</script>
