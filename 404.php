<?php
// /404.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';

$page_title = "Страница не найдена - 404";
$http_response_code = 404;
http_response_code($http_response_code);
?>

<?php include 'includes/header.php'; ?>

<style>
.error-page {
    min-height: 70vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 50px 0;
}

.error-content {
    text-align: center;
    max-width: 600px;
}

.error-code {
    font-size: 120px;
    font-weight: 900;
    color: #28a745;
    line-height: 1;
    margin-bottom: 20px;
    text-shadow: 3px 3px 0 rgba(0,0,0,0.1);
}

.error-title {
    font-size: 32px;
    font-weight: 700;
    color: #333;
    margin-bottom: 20px;
}

.error-text {
    font-size: 18px;
    color: #666;
    margin-bottom: 30px;
    line-height: 1.6;
}

.error-icon {
    font-size: 80px;
    color: #ffc107;
    margin-bottom: 30px;
    animation: bounce 2s infinite;
}

@keyframes bounce {
    0%, 20%, 50%, 80%, 100% {
        transform: translateY(0);
    }
    40% {
        transform: translateY(-20px);
    }
    60% {
        transform: translateY(-10px);
    }
}

.error-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
}

.error-illustration {
    margin-bottom: 30px;
}

.error-illustration img {
    max-width: 300px;
    height: auto;
}

@media (max-width: 768px) {
    .error-code {
        font-size: 80px;
    }
    
    .error-title {
        font-size: 24px;
    }
    
    .error-text {
        font-size: 16px;
    }
}
</style>

<div class="main-container">
    <div class="error-page animate-fade-in">
        <div class="error-content">
            <div class="error-illustration">
                <i class="fas fa-dog error-icon"></i>
            </div>
            
            <div class="error-code">404</div>
            
            <h1 class="error-title">Упс! Страница не найдена</h1>
            
            <p class="error-text">
                Похоже, вы забрели не туда, куда нужно. 
                Возможно, страница была удалена, перемещена или её никогда не существовало.
                Не волнуйтесь, наши хвостики помогут вам вернуться на правильный путь!
            </p>
            
            <div class="error-buttons">
                <a href="index.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-home me-2"></i>На главную
                </a>
                <a href="products.php" class="btn btn-outline-success btn-lg">
                    <i class="fas fa-shopping-bag me-2"></i>В каталог
                </a>
                <?php if (isset($_SESSION['user_id'])): ?>
                <a href="profile.php" class="btn btn-outline-info btn-lg">
                    <i class="fas fa-user me-2"></i>Профиль
                </a>
                <?php else: ?>
                <a href="account.php" class="btn btn-outline-info btn-lg">
                    <i class="fas fa-sign-in-alt me-2"></i>Войти
                </a>
                <?php endif; ?>
            </div>
            
            <div class="mt-5">
                <p class="text-muted small">
                    <i class="fas fa-paw me-1"></i>
                    Если вы считаете, что это ошибка, пожалуйста, свяжитесь с нами через страницу 
                    <a href="contacts.php">контактов</a>.
                </p>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
