<?php
// /includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'HvostX - Товары для домашних животных'; ?></title>
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/paw-animation.css?v=<?php echo time(); ?>">
    <script src="https://www.google.com/recaptcha/api.js?hl=ru" async defer></script>
</head>
<body>
    <header class="bg-white shadow-sm">
        <div class="container">
            <nav class="navbar navbar-expand-lg navbar-light bg-white">
                <div class="container-fluid">
                    <a class="navbar-brand" href="index.php">
                        <img src="assets/images/logo.png" alt="HvostX" height="65" class="d-inline-block align-text-top">
                    </a>

                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                        <span class="navbar-toggler-icon"></span>
                    </button>

                    <div class="collapse navbar-collapse" id="mainNav">
                        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                            <li class="nav-item">
                                <a class="nav-link" href="index.php">Главная</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="products.php">Товары</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="news.php">Новости</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="about.php">О компании</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="production.php">О продукции</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="partners.php">Партнеры</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="contacts.php">Контакты</a>
                            </li>
                        </ul>

                        <div class="d-flex align-items-center">
                            <a href="favorites.php" class="me-3 position-relative" id="favorites-link">
                                <i class="fas fa-heart fa-lg text-danger"></i>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger favorites-count-badge d-none" id="favorites-count-badge">0</span>
                            </a>

                            <a href="cart.php" class="me-3 position-relative" id="cart-link">
                                <i class="fas fa-shopping-cart fa-lg"></i>
                                <?php if (!empty($_SESSION['cart'])): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="cart-badge">
                                    <?php echo array_sum(array_column($_SESSION['cart'], 'quantity')); ?>
                                </span>
                                <?php else: ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="cart-badge">0</span>
                                <?php endif; ?>
                            </a>

                            <?php if (isset($_SESSION['user_id'])): ?>
                            <div class="dropdown">
                                <a class="dropdown-toggle text-decoration-none text-dark d-flex align-items-center" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                    <?php if (!empty($_SESSION['user_avatar']) && file_exists("assets/images/avatars/" . $_SESSION['user_avatar'])): ?>
                                    <img src="assets/images/avatars/<?php echo htmlspecialchars($_SESSION['user_avatar']); ?>"
                                         alt="" class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                                    <?php else: ?>
                                    <div class="rounded-circle me-2 d-flex align-items-center justify-content-center bg-success text-white" style="width: 32px; height: 32px; font-weight: bold;">
                                        <?php echo mb_strtoupper(mb_substr($_SESSION['user_name'], 0, 1)); ?>
                                    </div>
                                    <?php endif; ?>
                                    <span class="fw-medium"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                                    <i class="fas fa-chevron-down ms-2 text-muted" style="font-size: 0.75rem;"></i>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" aria-labelledby="userDropdown">
                                    <li><a class="dropdown-item py-2" href="profile.php"><i class="fas fa-user me-2 text-success"></i>Профиль</a></li>
                                    <?php if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'moderator'])): ?>
                                    <li><a class="dropdown-item py-2" href="admin/index.php"><i class="fas fa-cog me-2 text-success"></i>Админ-панель</a></li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider my-2"></li>
                                    <li><a class="dropdown-item py-2" href="account.php?logout"><i class="fas fa-sign-out-alt me-2 text-danger"></i>Выйти</a></li>
                                </ul>
                            </div>
                            <?php else: ?>
                            <a href="account.php" class="btn btn-success">Войти</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </nav>
        </div>
    </header>

    <!-- Поисковая строка -->
    <div class="search-bar">
        <div class="container">
            <form method="GET" action="products.php" class="row g-2 align-items-center">
                <div class="col-md-8 mx-auto">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" 
                               placeholder="Поиск товаров по названию или описанию..." 
                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        <button type="submit" class="btn">
                            <i class="fas fa-search me-2"></i>Найти
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <main class="flex-shrink-0">
        <div class="container mt-4">