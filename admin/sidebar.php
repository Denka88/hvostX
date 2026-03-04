<?php
// /admin/sidebar.php
?>

<div class="sidebar-menu">
    <a href="index.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>">
        <i class="fas fa-tachometer-alt"></i>
        <span class="sidebar-text">Панель управления</span>
    </a>
    <a href="products.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) === 'products.php' ? 'active' : ''; ?>">
        <i class="fas fa-box"></i>
        <span class="sidebar-text">Товары</span>
    </a>
    <a href="pet_categories.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) === 'pet_categories.php' ? 'active' : ''; ?>">
        <i class="fas fa-paw"></i>
        <span class="sidebar-text">Категории животных</span>
    </a>
    <a href="tags.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) === 'tags.php' ? 'active' : ''; ?>">
        <i class="fas fa-tags"></i>
        <span class="sidebar-text">Теги</span>
    </a>
    <a href="redirects.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) === 'redirects.php' ? 'active' : ''; ?>">
        <i class="fas fa-exchange-alt"></i>
        <span class="sidebar-text">Редиректы</span>
    </a>
    <a href="orders.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) === 'orders.php' ? 'active' : ''; ?>">
        <i class="fas fa-shopping-cart"></i>
        <span class="sidebar-text">Заказы</span>
    </a>
    <a href="users.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : ''; ?>">
        <i class="fas fa-users"></i>
        <span class="sidebar-text">Пользователи</span>
    </a>
    <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
    <a href="administrators.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) === 'administrators.php' ? 'active' : ''; ?>">
        <i class="fas fa-user-shield"></i>
        <span class="sidebar-text">Администрация</span>
    </a>
    <?php endif; ?>
    <a href="news.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) === 'news.php' ? 'active' : ''; ?>">
        <i class="fas fa-newspaper"></i>
        <span class="sidebar-text">Новости</span>
    </a>
    <a href="partners.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) === 'partners.php' ? 'active' : ''; ?>">
        <i class="fas fa-handshake"></i>
        <span class="sidebar-text">Партнеры</span>
    </a>
    <?php
    $messages_query = "SELECT COUNT(*) as count FROM contact_messages WHERE is_read = 0";
    $messages_result = mysqli_query($connection, $messages_query);
    $messages_count = mysqli_fetch_assoc($messages_result)['count'];
    ?>
    <a href="messages.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) === 'messages.php' ? 'active' : ''; ?>">
        <i class="fas fa-envelope"></i>
        <span class="sidebar-text">Сообщения</span>
        <?php if ($messages_count > 0): ?>
        <span class="sidebar-badge bg-danger"><?php echo $messages_count > 99 ? '99+' : $messages_count; ?></span>
        <?php endif; ?>
    </a>
    <?php
    $reviews_query = "SELECT COUNT(*) as count FROM product_reviews WHERE is_approved = 0";
    $reviews_result = mysqli_query($connection, $reviews_query);
    $reviews_count = mysqli_fetch_assoc($reviews_result)['count'];
    ?>
    <a href="reviews.php" class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) === 'reviews.php' ? 'active' : ''; ?>">
        <i class="fas fa-comments"></i>
        <span class="sidebar-text">Отзывы о товарах</span>
        <?php if ($reviews_count > 0): ?>
        <span class="sidebar-badge bg-danger"><?php echo $reviews_count > 99 ? '99+' : $reviews_count; ?></span>
        <?php endif; ?>
    </a>
    <a href="../account.php?logout" class="sidebar-item sidebar-item-danger">
        <i class="fas fa-sign-out-alt"></i>
        <span class="sidebar-text">Выйти</span>
    </a>
</div>
