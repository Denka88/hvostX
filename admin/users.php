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

    // Обработка аватара из кроппера
    $avatar = $_POST['existing_avatar'] ?? '';
    if (!empty($_POST['avatar_data'])) {
        $target_dir = "../assets/images/avatars/";
        
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $image_data = $_POST['avatar_data'];
        $image_type = $_POST['avatar_type'] ?? 'png';
        
        // Удаляем префикс data:image
        if (preg_match('/^data:image\/(\w+);base64,/', $image_data, $type_match)) {
            $image_type = $type_match[1];
            $image_data = substr($image_data, strpos($image_data, ',') + 1);
        }
        
        $image_data = base64_decode($image_data);
        
        if ($image_data !== false && strlen($image_data) > 0) {
            $new_filename = 'avatar_' . $id . '_' . time() . '.' . $image_type;
            $target_file = $target_dir . $new_filename;
            
            if (file_put_contents($target_file, $image_data)) {
                // Удаляем старый аватар
                if (!empty($_POST['existing_avatar']) && file_exists($target_dir . $_POST['existing_avatar'])) {
                    unlink($target_dir . $_POST['existing_avatar']);
                }
                $avatar = $new_filename;
            }
        }
    }

    if (!empty($name) && !empty($email) && $id > 0) {
        if ($current_user_role === 'moderator') {
            $query = "UPDATE users SET name = ?, email = ?, phone = ?, avatar = ? WHERE id = ? AND role = 'user'";
            $stmt = $connection->prepare($query);
            $stmt->bind_param("ssssi", $name, $email, $phone, $avatar, $id);
        } else {
            $query = "UPDATE users SET name = ?, email = ?, phone = ?, role = ?, avatar = ? WHERE id = ?";
            $stmt = $connection->prepare($query);
            $stmt->bind_param("sssssi", $name, $email, $phone, $role, $avatar, $id);
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
                                        <th>Аватар</th>
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
                                        <td>
                                            <?php if (!empty($user['avatar'])): ?>
                                            <img src="../assets/images/avatars/<?php echo htmlspecialchars($user['avatar']); ?>"
                                                 alt="Аватар" class="rounded-circle"
                                                 style="width: 40px; height: 40px; object-fit: cover;">
                                            <?php else: ?>
                                            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white"
                                                 style="width: 40px; height: 40px; font-size: 0.9rem; font-weight: bold;">
                                                <?php echo mb_strtoupper(mb_substr($user['name'], 0, 1)); ?>
                                            </div>
                                            <?php endif; ?>
                                        </td>
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
                                                <form method="POST" action="users.php" enctype="multipart/form-data" id="user-edit-form">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="edit_user" value="1">
                                                        <input type="hidden" name="existing_avatar" value="<?php echo htmlspecialchars($user['avatar'] ?? ''); ?>">
                                                        <input type="hidden" name="avatar_data" id="avatar-data" value="">
                                                        <input type="hidden" name="avatar_type" id="avatar-type" value="png">

                                                        <div class="mb-3 text-center">
                                                            <?php if (!empty($user['avatar'])): ?>
                                                            <img src="../assets/images/avatars/<?php echo htmlspecialchars($user['avatar']); ?>"
                                                                 alt="Текущий аватар" class="rounded-circle mb-2 avatar-preview-img"
                                                                 style="width: 100px; height: 100px; object-fit: cover;">
                                                            <p class="text-muted small">Текущий аватар</p>
                                                            <?php else: ?>
                                                            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white mx-auto mb-2 avatar-preview-img"
                                                                 style="width: 100px; height: 100px; font-size: 2.5rem; font-weight: bold;">
                                                                <?php echo mb_strtoupper(mb_substr($user['name'], 0, 1)); ?>
                                                            </div>
                                                            <p class="text-muted small">Аватар не загружен</p>
                                                            <?php endif; ?>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label for="avatar<?php echo $user['id']; ?>" class="form-label">Загрузить новый аватар</label><br>
                                                            <input type="file" id="avatar-input-<?php echo $user['id']; ?>" accept="image/*" style="display: none;">
                                                            <button type="button" class="btn btn-outline-primary" id="avatar-btn-<?php echo $user['id']; ?>">
                                                                <i class="fas fa-image me-2"></i>Выбрать изображение
                                                            </button>
                                                            <small class="text-muted d-block mt-2">JPG, PNG, GIF, WebP (макс. 5MB)</small>
                                                        </div>

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

    <style>
    #cropModal .modal-content {
        border-radius: 15px;
        overflow: hidden;
    }

    #cropModal .modal-body {
        background: #f8f9fa;
    }

    .crop-wrapper {
        background: repeating-conic-gradient(#e0e0e0 0% 25%, #f0f0f0 0% 50%) 50% / 20px 20px;
    }

    .avatar-preview {
        box-shadow: 0 0 20px rgba(40, 167, 69, 0.3);
        transition: transform 0.3s ease;
    }

    .avatar-preview:hover {
        transform: scale(1.05);
    }

    @keyframes pulse-success {
        0%, 100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.4); }
        50% { box-shadow: 0 0 0 10px rgba(40, 167, 69, 0); }
    }

    .avatar-preview {
        animation: pulse-success 2s infinite;
    }

    .cropper-view-box,
    .cropper-face {
        border-radius: 50%;
    }

    .cropper-line {
        background-color: rgba(13, 110, 253, 0.6);
    }

    .cropper-point {
        background-color: rgba(13, 110, 253, 0.6);
    }

    .cropper-dashed {
        border-color: rgba(13, 110, 253, 0.5);
    }

    .cropper-view-box {
        outline: 2px solid rgba(13, 110, 253, 0.8);
    }

    @media (max-width: 991px) {
        #cropModal .modal-dialog {
            max-width: 95%;
        }

        .crop-wrapper {
            min-height: 350px !important;
        }

        .avatar-preview {
            width: 140px !important;
            height: 140px !important;
        }
    }
    </style>

    <!-- Модальное окно для кроппера аватара -->
    <div class="modal fade" id="cropModal" tabindex="-1" aria-labelledby="cropModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cropModalLabel">
                        <i class="fas fa-image me-2"></i>Редактирование аватара
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="container-fluid">
                        <div class="row g-0">
                            <div class="col-lg-8 bg-light d-flex align-items-center justify-content-center" style="min-height: 500px;">
                                <div class="crop-wrapper position-relative w-100 h-100 d-flex align-items-center justify-content-center p-3">
                                    <img id="image-to-crop" src="" alt="Изображение для обрезки" class="img-fluid" style="max-height: 450px; max-width: 100%;">
                                </div>
                            </div>
                            <div class="col-lg-4 bg-white border-start">
                                <div class="p-4 h-100 d-flex flex-column">
                                    <div class="mb-4 text-center">
                                        <h6 class="mb-3 text-dark"><i class="fas fa-eye me-2"></i>Предпросмотр</h6>
                                        <div class="avatar-preview-wrapper position-relative d-inline-block">
                                            <div class="avatar-preview rounded-circle overflow-hidden border border-3 border-success shadow-lg"
                                                 style="width: 180px; height: 180px; background: linear-gradient(45deg, #f8f9fa, #e9ecef);">
                                                <img id="avatar-preview" src="" alt="Предпросмотр" style="width: 100%; height: 100%; object-fit: cover;">
                                            </div>
                                            <div class="position-absolute bottom-0 start-0 w-100 text-center bg-dark bg-opacity-75 rounded-bottom py-1">
                                                <small class="text-white">180×180</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-4">
                                        <h6 class="mb-3 text-dark text-center"><i class="fas fa-grid me-2"></i>Размеры</h6>
                                        <div class="d-flex justify-content-center gap-3">
                                            <div class="text-center">
                                                <div class="rounded-circle border border-2 border-primary overflow-hidden mx-auto mb-1"
                                                     style="width: 50px; height: 50px; background: #f8f9fa;">
                                                    <img id="preview-small" src="" alt="Small" style="width: 100%; height: 100%; object-fit: cover;">
                                                </div>
                                                <small class="text-muted" style="font-size: 10px;">50×50</small>
                                            </div>
                                            <div class="text-center">
                                                <div class="rounded-circle border border-2 border-info overflow-hidden mx-auto mb-1"
                                                     style="width: 80px; height: 80px; background: #f8f9fa;">
                                                    <img id="preview-medium" src="" alt="Medium" style="width: 100%; height: 100%; object-fit: cover;">
                                                </div>
                                                <small class="text-muted" style="font-size: 10px;">80×80</small>
                                            </div>
                                            <div class="text-center">
                                                <div class="rounded-circle border border-2 border-warning overflow-hidden mx-auto mb-1"
                                                     style="width: 120px; height: 120px; background: #f8f9fa;">
                                                    <img id="preview-large" src="" alt="Large" style="width: 100%; height: 100%; object-fit: cover;">
                                                </div>
                                                <small class="text-muted" style="font-size: 10px;">120×120</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-4 flex-grow-1">
                                        <div class="card bg-light border-light">
                                            <div class="card-body p-3">
                                                <h6 class="card-title text-dark mb-2"><i class="fas fa-info-circle me-2"></i>Информация</h6>
                                                <ul class="card-text text-muted mb-0 small" style="font-size: 13px;">
                                                    <li>Перетащите рамку для выбора области</li>
                                                    <li>Используйте колёсико для масштабирования</li>
                                                    <li>Двойной клик для сброса</li>
                                                    <li>Формат: <span id="image-format" class="text-primary">PNG</span></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-auto">
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-secondary flex-fill" data-bs-dismiss="modal">
                                                <i class="fas fa-times me-1"></i>Отмена
                                            </button>
                                            <button type="button" class="btn btn-success flex-fill" id="crop-btn">
                                                <i class="fas fa-check me-1"></i>Применить
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        let currentUserId = null;
        let cropper = null;
        let cropModal = null;
        let currentFileName = '';

        const cropModalElement = document.getElementById('cropModal');
        const imageToCrop = document.getElementById('image-to-crop');
        const cropBtn = document.getElementById('crop-btn');
        const avatarDataInput = document.getElementById('avatar-data');
        const avatarTypeInput = document.getElementById('avatar-type');
        const imageFormatSpan = document.getElementById('image-format');
        const avatarPreview = document.getElementById('avatar-preview');
        const previewSmall = document.getElementById('preview-small');
        const previewMedium = document.getElementById('preview-medium');
        const previewLarge = document.getElementById('preview-large');

        if (typeof bootstrap !== 'undefined' && cropModalElement) {
            cropModal = new bootstrap.Modal(cropModalElement, {
                backdrop: 'static',
                keyboard: true
            });
        }

        function updatePreviews(imageData) {
            if (avatarPreview) avatarPreview.src = imageData;
            if (previewSmall) previewSmall.src = imageData;
            if (previewMedium) previewMedium.src = imageData;
            if (previewLarge) previewLarge.src = imageData;
        }

        function initAvatarUploader(userId) {
            const avatarInput = document.getElementById('avatar-input-' + userId);
            const avatarBtn = document.getElementById('avatar-btn-' + userId);

            if (!avatarInput || !avatarBtn) return;

            avatarBtn.addEventListener('click', function(e) {
                e.preventDefault();
                currentUserId = userId;
                avatarInput.click();
            });

            avatarInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (!file) return;

                currentFileName = file.name;

                if (file.size > 5 * 1024 * 1024) {
                    alert('Размер файла не должен превышать 5MB');
                    avatarInput.value = '';
                    return;
                }

                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Допустимы только файлы JPG, JPEG, PNG, GIF и WebP');
                    avatarInput.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(event) {
                    imageToCrop.src = event.target.result;
                    const imageType = file.type.split('/')[1] || 'png';
                    if (avatarTypeInput) avatarTypeInput.value = imageType;
                    if (imageFormatSpan) imageFormatSpan.textContent = imageType.toUpperCase();

                    if (cropper) {
                        cropper.destroy();
                        cropper = null;
                    }

                    setTimeout(function() {
                        cropper = new Cropper(imageToCrop, {
                            aspectRatio: 1,
                            viewMode: 1,
                            preview: '.avatar-preview',
                            autoCropArea: 0.9,
                            responsive: true,
                            guides: true,
                            center: true,
                            highlight: true,
                            cropBoxMovable: true,
                            cropBoxResizable: true,
                            toggleDragModeOnDblclick: true,
                            dragMode: 'move',
                            minContainerWidth: 300,
                            minContainerHeight: 300,
                        });

                        imageToCrop.addEventListener('crop', function(event) {
                            if (cropper) {
                                const canvas = cropper.getCroppedCanvas({
                                    width: 300,
                                    height: 300,
                                    imageSmoothingEnabled: true,
                                    imageSmoothingQuality: 'high',
                                });
                                if (canvas) {
                                    const previewData = canvas.toDataURL('image/' + imageType);
                                    updatePreviews(previewData);
                                }
                            }
                        });

                        cropModal.show();
                    }, 150);
                };
                reader.readAsDataURL(file);
            });
        }

        if (cropBtn) {
            cropBtn.addEventListener('click', function() {
                if (!cropper) {
                    alert('Кроппер не инициализирован');
                    return;
                }

                cropBtn.disabled = true;
                cropBtn.innerHTML = '<i class="fas fa-hourglass-split me-2"></i>Обработка...';

                try {
                    const canvas = cropper.getCroppedCanvas({
                        width: 300,
                        height: 300,
                        imageSmoothingEnabled: true,
                        imageSmoothingQuality: 'high',
                    });

                    if (!canvas) {
                        alert('Не удалось получить обрезанное изображение');
                        cropBtn.disabled = false;
                        cropBtn.innerHTML = '<i class="fas fa-check me-2"></i>Применить';
                        return;
                    }

                    const imageData = canvas.toDataURL('image/' + avatarTypeInput.value);

                    if (imageData.length > 4 * 1024 * 1024) {
                        alert('Изображение слишком большое');
                        cropBtn.disabled = false;
                        cropBtn.innerHTML = '<i class="fas fa-check me-2"></i>Применить';
                        return;
                    }

                    avatarDataInput.value = imageData;

                    // Обновляем превью в модальном окне редактирования
                    if (currentUserId) {
                        const previewImg = document.querySelector('#editModal' + currentUserId + ' .avatar-preview-img');
                        if (previewImg && previewImg.tagName === 'IMG') {
                            previewImg.src = imageData;
                        } else if (previewImg) {
                            // Заменяем заглушку на изображение
                            const newImg = document.createElement('img');
                            newImg.src = imageData;
                            newImg.className = 'rounded-circle mb-2 avatar-preview-img';
                            newImg.style = 'width: 100px; height: 100px; object-fit: cover;';
                            previewImg.parentNode.replaceChild(newImg, previewImg);
                        }
                    }

                    cropModal.hide();

                    const avatarInput = currentUserId ? document.getElementById('avatar-input-' + currentUserId) : null;
                    if (avatarInput) avatarInput.value = '';

                } catch (error) {
                    console.error('Ошибка при обработке изображения:', error);
                    alert('Произошла ошибка при обработке изображения');
                } finally {
                    cropBtn.disabled = false;
                    cropBtn.innerHTML = '<i class="fas fa-check me-2"></i>Применить';
                }
            });
        }

        if (cropModalElement) {
            cropModalElement.addEventListener('hidden.bs.modal', function() {
                if (cropper) {
                    cropper.destroy();
                    cropper = null;
                }
                const avatarInput = currentUserId ? document.getElementById('avatar-input-' + currentUserId) : null;
                if (avatarInput) avatarInput.value = '';
            });
        }

        // Инициализируем загрузчики для всех пользователей
        <?php foreach ($users as $user): ?>
        initAvatarUploader(<?php echo $user['id']; ?>);
        <?php endforeach; ?>
    })();
    </script>
</body>
</html>
