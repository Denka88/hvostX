<?php
// /admin/administrators.php
session_start();
require_once '../includes/db.php';
require_once 'auth.php';
require_once 'filter.php';

checkAdminAccess();

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

if (isset($_GET['clear_filter'])) {
    $filter = new TableFilter($connection, 'users', 'u');
    $filter->clear();
    header("Location: administrators.php");
    exit;
}

$filter = new TableFilter($connection, 'users', 'u');
$filter->addField('login', 'text', 'Логин')
       ->addField('name', 'text', 'Имя')
       ->addField('email', 'text', 'Email')
       ->addField('role', 'select', 'Роль', [
           'admin' => 'Администратор',
           'moderator' => 'Модератор'
       ])
       ->addDateRange('created_at', 'Дата регистрации');

if (!empty($_GET) && !isset($_GET['action']) && !isset($_GET['clear_filter'])) {
    $filter->saveValues($_GET);
}

if ($action === 'delete' && $id > 0) {
    if ($id != $_SESSION['user_id']) {
        $check_query = "SELECT role FROM users WHERE id = ?";
        $check_stmt = $connection->prepare($check_query);
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $user_to_delete = $check_stmt->get_result()->fetch_assoc();

        if ($user_to_delete && $user_to_delete['role'] === 'moderator') {
            $query = "DELETE FROM users WHERE id = ?";
            $stmt = $connection->prepare($query);
            $stmt->bind_param("i", $id);
            $stmt->execute();
        }
    }
    header("Location: administrators.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_moderator'])) {
    $id = $_POST['id'] ?? 0;
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (!empty($name) && !empty($email) && $id > 0) {
        $query = "UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ? AND role IN ('admin', 'moderator')";
        $stmt = $connection->prepare($query);
        $stmt->bind_param("sssi", $name, $email, $phone, $id);
        $stmt->execute();
        header("Location: administrators.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_moderator'])) {
    $login = trim($_POST['login'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!empty($login) && !empty($name) && !empty($email) && !empty($password)) {
        if ($password === $confirm_password) {
            $check_query = "SELECT id FROM users WHERE login = ? OR email = ?";
            $check_stmt = $connection->prepare($check_query);
            $check_stmt->bind_param("ss", $login, $email);
            $check_stmt->execute();

            if ($check_stmt->get_result()->num_rows > 0) {
                $error = "Логин или email уже заняты";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $query = "INSERT INTO users (login, name, email, phone, password, role) VALUES (?, ?, ?, ?, ?, 'moderator')";
                $stmt = $connection->prepare($query);
                $stmt->bind_param("sssss", $login, $name, $email, $phone, $hashed_password);

                if ($stmt->execute()) {
                    $success = "Модератор успешно создан";
                } else {
                    $error = "Ошибка при создании модератора";
                }
            }
        } else {
            $error = "Пароли не совпадают";
        }
    } else {
        $error = "Заполните все обязательные поля";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $id = $_POST['id'] ?? 0;
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!empty($password) && $id > 0) {
        if ($password === $confirm_password) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $query = "UPDATE users SET password = ? WHERE id = ? AND role IN ('admin', 'moderator')";
            $stmt = $connection->prepare($query);
            $stmt->bind_param("si", $hashed_password, $id);
            $stmt->execute();
            $success = "Пароль успешно изменён";
        } else {
            $error = "Пароли не совпадают";
        }
    }
}

$count_query = "SELECT COUNT(*) as total FROM users WHERE role IN ('admin', 'moderator')";
$count_result = mysqli_query($connection, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];

$pagination = new Pagination($total_records, 20);
$offset = $pagination->getOffset();
$limit = $pagination->getLimit();

list($where, $params, $types) = $filter->getSQL();

$admin_where = "WHERE role IN ('admin', 'moderator')";
if (!empty($where)) {
    $admin_where .= " " . str_replace('WHERE', 'AND', $where);
}

$query = "SELECT * FROM users u $admin_where ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
if (!empty($params)) {
    $stmt = $connection->prepare($query);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $moderators = mysqli_fetch_all($result, MYSQLI_ASSOC);
} else {
    $result = mysqli_query($connection, $query);
    $moderators = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

$edit_moderator = null;
if ($id > 0) {
    $query = "SELECT * FROM users WHERE id = ? AND role IN ('admin', 'moderator')";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_moderator = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление администрацией - HvostX</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include 'sidebar.php'; ?>

        <div id="page-content-wrapper">
            <?php include 'navbar.php'; ?>

            <div class="container-fluid px-4">
                <h1 class="mt-4 mb-4">Управление администрацией</h1>

                <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php echo $filter->render(); ?>

                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-user-shield me-1"></i> Список администраторов и модераторов</span>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createModal">
                            <i class="fas fa-plus me-1"></i> Создать модератора
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Логин</th>
                                        <th>Имя</th>
                                        <th>Email</th>
                                        <th>Телефон</th>
                                        <th>Роль</th>
                                        <th>Дата регистрации</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($moderators) > 0): ?>
                                        <?php foreach ($moderators as $mod): ?>
                                        <tr>
                                            <td><?php echo $mod['id']; ?></td>
                                            <td><?php echo htmlspecialchars($mod['login']); ?></td>
                                            <td><?php echo htmlspecialchars($mod['name']); ?></td>
                                            <td><?php echo htmlspecialchars($mod['email']); ?></td>
                                            <td><?php echo htmlspecialchars($mod['phone'] ?? '-'); ?></td>
                                            <td>
                                                <?php
                                                $badge_class = match($mod['role']) {
                                                    'admin' => 'bg-danger',
                                                    'moderator' => 'bg-warning text-dark',
                                                    default => 'bg-primary'
                                                };
                                                $role_name = match($mod['role']) {
                                                    'admin' => 'Администратор',
                                                    'moderator' => 'Модератор',
                                                    default => 'Пользователь'
                                                };
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>">
                                                    <?php echo $role_name; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d.m.Y H:i', strtotime($mod['created_at'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#viewModal<?php echo $mod['id']; ?>"
                                                        data-bs-toggle="tooltip" title="Просмотр">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal"
                                                        data-bs-target="#editModal<?php echo $mod['id']; ?>"
                                                        data-bs-toggle="tooltip" title="Редактировать">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-secondary" data-bs-toggle="modal"
                                                        data-bs-target="#passwordModal<?php echo $mod['id']; ?>"
                                                        data-bs-toggle="tooltip" title="Сменить пароль">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                                <?php if ($mod['id'] != $_SESSION['user_id'] && $mod['role'] !== 'admin'): ?>
                                                <a href="administrators.php?action=delete&id=<?php echo $mod['id']; ?>"
                                                   class="btn btn-sm btn-danger btn-delete"
                                                   data-bs-toggle="tooltip" title="Удалить">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>

                                        <!-- Модальное окно просмотра -->
                                        <div class="modal fade" id="viewModal<?php echo $mod['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">
                                                            <?php echo $mod['role'] === 'admin' ? 'Администратор' : 'Модератор'; ?> #<?php echo $mod['id']; ?>
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p><strong>Логин:</strong> <?php echo htmlspecialchars($mod['login']); ?></p>
                                                        <p><strong>Имя:</strong> <?php echo htmlspecialchars($mod['name']); ?></p>
                                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($mod['email']); ?></p>
                                                        <p><strong>Телефон:</strong> <?php echo htmlspecialchars($mod['phone'] ?? '-'); ?></p>
                                                        <p><strong>Роль:</strong>
                                                            <?php
                                                            $badge_class = $mod['role'] === 'admin' ? 'bg-danger' : 'bg-warning text-dark';
                                                            $role_name = $mod['role'] === 'admin' ? 'Администратор' : 'Модератор';
                                                            ?>
                                                            <span class="badge <?php echo $badge_class; ?>"><?php echo $role_name; ?></span>
                                                        </p>
                                                        <p><strong>Дата регистрации:</strong> <?php echo date('d.m.Y H:i', strtotime($mod['created_at'])); ?></p>
                                                        <?php if (!empty($mod['avatar'])): ?>
                                                        <p><strong>Аватар:</strong><br>
                                                            <img src="../assets/images/<?php echo htmlspecialchars($mod['avatar']); ?>"
                                                                 alt="Аватар" class="img-thumbnail" style="max-width: 150px;">
                                                        </p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Модальное окно редактирования -->
                                        <div class="modal fade" id="editModal<?php echo $mod['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">
                                                            Редактирование <?php echo $mod['role'] === 'admin' ? 'администратора' : 'модератора'; ?> #<?php echo $mod['id']; ?>
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST" action="administrators.php">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="id" value="<?php echo $mod['id']; ?>">
                                                            <input type="hidden" name="edit_moderator" value="1">

                                                            <div class="mb-3">
                                                                <label for="name<?php echo $mod['id']; ?>" class="form-label">Имя *</label>
                                                                <input type="text" class="form-control" id="name<?php echo $mod['id']; ?>"
                                                                       name="name" value="<?php echo htmlspecialchars($mod['name']); ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="email<?php echo $mod['id']; ?>" class="form-label">Email *</label>
                                                                <input type="email" class="form-control" id="email<?php echo $mod['id']; ?>"
                                                                       name="email" value="<?php echo htmlspecialchars($mod['email']); ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="phone<?php echo $mod['id']; ?>" class="form-label">Телефон</label>
                                                                <input type="text" class="form-control" id="phone<?php echo $mod['id']; ?>"
                                                                       name="phone" value="<?php echo htmlspecialchars($mod['phone'] ?? ''); ?>">
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                                            <button type="submit" class="btn btn-primary">Сохранить</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Модальное окно смены пароля -->
                                        <div class="modal fade" id="passwordModal<?php echo $mod['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">
                                                            Смена пароля для <?php echo $mod['role'] === 'admin' ? 'администратора' : 'модератора'; ?> #<?php echo $mod['id']; ?>
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST" action="administrators.php">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="id" value="<?php echo $mod['id']; ?>">
                                                            <input type="hidden" name="change_password" value="1">

                                                            <div class="mb-3">
                                                                <label for="password<?php echo $mod['id']; ?>" class="form-label">Новый пароль *</label>
                                                                <input type="password" class="form-control" id="password<?php echo $mod['id']; ?>"
                                                                       name="password" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="confirm_password<?php echo $mod['id']; ?>" class="form-label">Подтверждение пароля *</label>
                                                                <input type="password" class="form-control" id="confirm_password<?php echo $mod['id']; ?>"
                                                                       name="confirm_password" required>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                                            <button type="submit" class="btn btn-primary">Сохранить</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">Администраторы и модераторы не найдены</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php echo $pagination->render(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно создания модератора -->
    <div class="modal fade" id="createModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Создание модератора</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="administrators.php">
                    <div class="modal-body">
                        <input type="hidden" name="create_moderator" value="1">

                        <div class="mb-3">
                            <label for="new_login" class="form-label">Логин *</label>
                            <input type="text" class="form-control" id="new_login" name="login" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_name" class="form-label">Имя *</label>
                            <input type="text" class="form-control" id="new_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="new_email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_phone" class="form-label">Телефон</label>
                            <input type="text" class="form-control" id="new_phone" name="phone">
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">Пароль *</label>
                            <input type="password" class="form-control" id="new_password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_confirm_password" class="form-label">Подтверждение пароля *</label>
                            <input type="password" class="form-control" id="new_confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary">Создать</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
</body>
</html>
