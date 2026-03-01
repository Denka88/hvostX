<?php
// /admin/users.php
session_start();
require_once '../includes/db.php';
require_once 'auth.php';
require_once 'filter.php';

checkModeratorAccess();

$current_user_role = $_SESSION['user_role'];

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

if (isset($_GET['clear_filter'])) {
    $filter = new TableFilter($connection, 'users');
    $filter->clear();
    header("Location: users.php");
    exit;
}

$filter = new TableFilter($connection, 'users');
$filter->addField('login', 'text', 'Логин')
       ->addField('name', 'text', 'Имя')
       ->addField('email', 'text', 'Email')
       ->addField('role', 'select', 'Роль', [
           'admin' => 'Администратор',
           'moderator' => 'Модератор',
           'user' => 'Пользователь'
       ])
       ->addDateRange('created_at', 'Дата регистрации');

if (!empty($_GET) && !isset($_GET['action']) && !isset($_GET['clear_filter'])) {
    $filter->saveValues($_GET);
}

if ($action === 'delete' && $id > 0) {
    if ($current_user_role === 'admin') {
        if ($id != $_SESSION['user_id']) {
            $check_query = "SELECT role FROM users WHERE id = ?";
            $check_stmt = $connection->prepare($check_query);
            $check_stmt->bind_param("i", $id);
            $check_stmt->execute();
            $user_to_delete = $check_stmt->get_result()->fetch_assoc();

            if ($user_to_delete && $user_to_delete['role'] !== 'admin') {
                $query = "DELETE FROM users WHERE id = ?";
                $stmt = $connection->prepare($query);
                $stmt->bind_param("i", $id);
                $stmt->execute();
            }
        }
    }
    header("Location: users.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $id = $_POST['id'] ?? 0;
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? 'user';

    if (!empty($name) && !empty($email) && $id > 0) {
        if ($current_user_role === 'moderator') {
            $query = "UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ? AND role = 'user'";
            $stmt = $connection->prepare($query);
            $stmt->bind_param("sssi", $name, $email, $phone, $id);
        } else {
            $query = "UPDATE users SET name = ?, email = ?, phone = ?, role = ? WHERE id = ?";
            $stmt = $connection->prepare($query);
            $stmt->bind_param("ssssi", $name, $email, $phone, $role, $id);
        }
        $stmt->execute();
        header("Location: users.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $id = $_POST['id'] ?? 0;
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!empty($password) && $id > 0) {
        if ($password === $confirm_password) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $query = "UPDATE users SET password = ? WHERE id = ?";
            $stmt = $connection->prepare($query);
            $stmt->bind_param("si", $hashed_password, $id);
            $stmt->execute();
            $success = "Пароль успешно изменён";
        } else {
            $error = "Пароли не совпадают";
        }
    }
}

$count_query = "SELECT COUNT(*) as total FROM users WHERE role NOT IN ('admin', 'moderator')";
$count_result = mysqli_query($connection, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];

$pagination = new Pagination($total_records, 20);
$offset = $pagination->getOffset();
$limit = $pagination->getLimit();

list($where, $params, $types) = $filter->getSQL();

$admin_where = "WHERE role NOT IN ('admin', 'moderator')";
if (!empty($where)) {
    $admin_where .= " " . str_replace('WHERE', 'AND', $where);
}

$query = "SELECT * FROM users $admin_where ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
if (!empty($params)) {
    $stmt = $connection->prepare($query);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $users = mysqli_fetch_all($result, MYSQLI_ASSOC);
} else {
    $result = mysqli_query($connection, $query);
    $users = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

$edit_user = null;
if ($id > 0) {
    $query = "SELECT * FROM users WHERE id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_user = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление пользователями - HvostX</title>
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
                <h1 class="mt-4 mb-4">Управление пользователями</h1>

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
                    <div class="card-header">
                        <i class="fas fa-users me-1"></i>
                        Список пользователей
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
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td><?php echo htmlspecialchars($user['login']); ?></td>
                                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></td>
                                        <td>
                                            <?php
                                            $badge_class = match($user['role']) {
                                                'admin' => 'bg-danger',
                                                'moderator' => 'bg-warning text-dark',
                                                default => 'bg-primary'
                                            };
                                            $role_name = match($user['role']) {
                                                'admin' => 'Администратор',
                                                'moderator' => 'Модератор',
                                                default => 'Пользователь'
                                            };
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>">
                                                <?php echo $role_name; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                    data-bs-target="#viewModal<?php echo $user['id']; ?>"
                                                    data-bs-toggle="tooltip" title="Просмотр">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php 
                                            // Модераторы могут редактировать только обычных пользователей
                                            $can_edit = ($current_user_role === 'admin') || ($current_user_role === 'moderator' && $user['role'] === 'user');
                                            ?>
                                            <?php if ($can_edit): ?>
                                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal"
                                                    data-bs-target="#editModal<?php echo $user['id']; ?>"
                                                    data-bs-toggle="tooltip" title="Редактировать">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-secondary" data-bs-toggle="modal"
                                                    data-bs-target="#passwordModal<?php echo $user['id']; ?>"
                                                    data-bs-toggle="tooltip" title="Сменить пароль">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <?php if ($current_user_role === 'admin' && $user['id'] != $_SESSION['user_id'] && $user['role'] !== 'admin'): ?>
                                            <a href="users.php?action=delete&id=<?php echo $user['id']; ?>"
                                               class="btn btn-sm btn-danger btn-delete"
                                               data-bs-toggle="tooltip" title="Удалить">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>

                                    <!-- Модальное окно просмотра -->
                                    <div class="modal fade" id="viewModal<?php echo $user['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Пользователь #<?php echo $user['id']; ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p><strong>Логин:</strong> <?php echo htmlspecialchars($user['login']); ?></p>
                                                    <p><strong>Имя:</strong> <?php echo htmlspecialchars($user['name']); ?></p>
                                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                                                    <p><strong>Телефон:</strong> <?php echo htmlspecialchars($user['phone'] ?? '-'); ?></p>
                                                    <p><strong>Роль:</strong>
                                                        <?php
                                                        $badge_class = match($user['role']) {
                                                            'admin' => 'bg-danger',
                                                            'moderator' => 'bg-warning text-dark',
                                                            default => 'bg-primary'
                                                        };
                                                        $role_name = match($user['role']) {
                                                            'admin' => 'Администратор',
                                                            'moderator' => 'Модератор',
                                                            default => 'Пользователь'
                                                        };
                                                        ?>
                                                        <span class="badge <?php echo $badge_class; ?>">
                                                            <?php echo $role_name; ?>
                                                        </span>
                                                    </p>
                                                    <p><strong>Дата регистрации:</strong> <?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></p>
                                                    <?php if (!empty($user['avatar'])): ?>
                                                    <p><strong>Аватар:</strong><br>
                                                        <img src="../assets/images/<?php echo htmlspecialchars($user['avatar']); ?>"
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
                                    <div class="modal fade" id="editModal<?php echo $user['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Редактирование пользователя #<?php echo $user['id']; ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST" action="users.php">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="edit_user" value="1">

                                                        <div class="mb-3">
                                                            <label for="name<?php echo $user['id']; ?>" class="form-label">Имя *</label>
                                                            <input type="text" class="form-control" id="name<?php echo $user['id']; ?>"
                                                                   name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="email<?php echo $user['id']; ?>" class="form-label">Email *</label>
                                                            <input type="email" class="form-control" id="email<?php echo $user['id']; ?>"
                                                                   name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="phone<?php echo $user['id']; ?>" class="form-label">Телефон</label>
                                                            <input type="text" class="form-control" id="phone<?php echo $user['id']; ?>"
                                                                   name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                                        </div>
                                                        <?php if ($current_user_role === 'admin'): ?>
                                                        <div class="mb-3">
                                                            <label for="role<?php echo $user['id']; ?>" class="form-label">Роль</label>
                                                            <select class="form-select" id="role<?php echo $user['id']; ?>" name="role">
                                                                <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>Пользователь</option>
                                                                <option value="moderator" <?php echo $user['role'] === 'moderator' ? 'selected' : ''; ?>>Модератор</option>
                                                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Администратор</option>
                                                            </select>
                                                        </div>
                                                        <?php endif; ?>
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
                                    <div class="modal fade" id="passwordModal<?php echo $user['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Смена пароля для пользователя #<?php echo $user['id']; ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST" action="users.php">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="change_password" value="1">

                                                        <div class="mb-3">
                                                            <label for="password<?php echo $user['id']; ?>" class="form-label">Новый пароль *</label>
                                                            <input type="password" class="form-control" id="password<?php echo $user['id']; ?>"
                                                                   name="password" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="confirm_password<?php echo $user['id']; ?>" class="form-label">Подтверждение пароля *</label>
                                                            <input type="password" class="form-control" id="confirm_password<?php echo $user['id']; ?>"
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
                                </tbody>
                            </table>
                        </div>
                        
                        <?php echo $pagination->render(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
</body>
</html>
