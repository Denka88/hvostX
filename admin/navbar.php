<?php
// /admin/navbar.php
?>
<nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
    <div class="container-fluid">
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav ms-auto mt-2 mt-lg-0">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button" data-bs-toggle="dropdown">
                        <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                        <?php
                        $role_badge = match($_SESSION['user_role'] ?? '') {
                            'admin' => '<span class="badge bg-danger ms-1">Админ</span>',
                            'moderator' => '<span class="badge bg-warning text-dark ms-1">Модератор</span>',
                            default => ''
                        };
                        echo $role_badge;
                        ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="../account.php">Личный кабинет</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../account.php?logout">Выйти</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
