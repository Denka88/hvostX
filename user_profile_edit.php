<?php
// /user_profile_edit.php
require_once 'includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: account.php");
    exit;
}

$query = "SELECT * FROM users WHERE id = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    session_destroy();
    header("Location: account.php");
    exit;
}

$success = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $login = $_POST['login'] ?? '';
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $delete_avatar = $_POST['delete_avatar'] ?? false;

    if (empty($login) || strlen($login) < 3) $errors[] = 'Логин должен содержать не менее 3 символов';
    if (empty($name)) $errors[] = 'Пожалуйста, укажите ваше имя';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Пожалуйста, укажите корректный email';

    if ($login !== $user['login']) {
        $query = "SELECT id FROM users WHERE login = ? AND id != ?";
        $stmt = $connection->prepare($query);
        $stmt->bind_param("si", $login, $user_id);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            $errors[] = 'Пользователь с таким логином уже существует';
        }
    }

    if ($email !== $user['email']) {
        $query = "SELECT id FROM users WHERE email = ? AND id != ?";
        $stmt = $connection->prepare($query);
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            $errors[] = 'Пользователь с таким email уже существует';
        }
    }

    if (!empty($new_password)) {
        if (empty($current_password)) {
            $errors[] = 'Пожалуйста, укажите текущий пароль';
        } else {
            if (!password_verify($current_password, $user['password'])) {
                $errors[] = 'Неверный текущий пароль';
            }

            if (strlen($new_password) < 6) {
                $errors[] = 'Новый пароль должен содержать не менее 6 символов';
            }

            if ($new_password !== $confirm_password) {
                $errors[] = 'Новые пароли не совпадают';
            }
        }
    }

    $avatar = $user['avatar'];

    if (!empty($_POST['avatar_data'])) {
        $target_dir = "assets/images/avatars/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $image_data = $_POST['avatar_data'];
        $image_type = $_POST['avatar_type'] ?? 'png';

        if (preg_match('/^data:image\/(\w+);base64,/', $image_data, $type_match)) {
            $image_type = $type_match[1];
            $image_data = substr($image_data, strpos($image_data, ',') + 1);
        }

        $image_data = base64_decode($image_data);

        if ($image_data !== false && strlen($image_data) > 0) {
            $new_filename = 'avatar_' . $user_id . '_' . time() . '.' . $image_type;
            $target_file = $target_dir . $new_filename;

            if (file_put_contents($target_file, $image_data)) {
                if (!empty($user['avatar']) && file_exists($target_dir . $user['avatar'])) {
                    unlink($target_dir . $user['avatar']);
                }
                $avatar = $new_filename;
            } else {
                $errors[] = 'Ошибка при сохранении файла';
            }
        } else {
            $errors[] = 'Ошибка при декодировании изображения или пустые данные';
        }
    }

    if ($delete_avatar && !empty($user['avatar'])) {
        $target_dir = "assets/images/avatars/";
        if (file_exists($target_dir . $user['avatar'])) {
            unlink($target_dir . $user['avatar']);
        }
        $avatar = null;
    }

    if (empty($errors)) {
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $query = "UPDATE users SET login = ?, name = ?, email = ?, phone = ?, avatar = ?, password = ? WHERE id = ?";
            $stmt = $connection->prepare($query);
            $stmt->bind_param("ssssssi", $login, $name, $email, $phone, $avatar, $hashed_password, $user_id);
        } else {
            $query = "UPDATE users SET login = ?, name = ?, email = ?, phone = ?, avatar = ? WHERE id = ?";
            $stmt = $connection->prepare($query);
            $stmt->bind_param("sssssi", $login, $name, $email, $phone, $avatar, $user_id);
        }

        $stmt->execute();

        $_SESSION['user_name'] = $name;
        $_SESSION['user_login'] = $login;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_avatar'] = $avatar;

        $success = "Ваш профиль успешно обновлен!";

        $user['login'] = $login;
        $user['name'] = $name;
        $user['email'] = $email;
        $user['phone'] = $phone;
        $user['avatar'] = $avatar;
    }
}

$page_title = "Редактирование профиля - HvostX";
?>

<?php include 'includes/header.php'; ?>

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
    background-color: rgba(0, 0, 0, 0.6);
}

.cropper-point {
    background-color: rgba(0, 0, 0, 0.6);
}

.cropper-dashed {
    border-color: rgba(0, 0, 0, 0.5);
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

<div class="main-container animate-fade-in">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Главная</a></li>
            <li class="breadcrumb-item"><a href="profile.php">Профиль</a></li>
            <li class="breadcrumb-item active" aria-current="page">Редактирование</li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <?php if (!empty($user['avatar']) && file_exists("assets/images/avatars/" . $user['avatar'])): ?>
                        <img src="assets/images/avatars/<?php echo htmlspecialchars($user['avatar']); ?>"
                             alt="Ваш аватар" class="img-fluid rounded-circle" style="width: 150px; height: 150px; object-fit: cover;">
                        <?php else: ?>
                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto"
                             style="width: 150px; height: 150px;">
                            <i class="bi bi-person-fill text-muted" style="font-size: 4rem;"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <h4><?php echo htmlspecialchars($user['name']); ?></h4>
                    <p class="text-muted">@<?php echo htmlspecialchars($user['login']); ?></p>
                    <p class="text-muted small"><?php echo htmlspecialchars($user['email']); ?></p>
                    <a href="profile.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i> Назад в профиль
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Редактирование профиля</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="user_profile_edit.php">
                        <h5 class="mb-3"><i class="bi bi-person me-2"></i>Основные данные</h5>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="login" class="form-label">Логин *</label>
                                    <input type="text" class="form-control" id="login" name="login"
                                           value="<?php echo htmlspecialchars($user['login']); ?>" required>
                                    <div class="form-text">Минимум 3 символа</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Ваше имя *</label>
                                    <input type="text" class="form-control" id="name" name="name"
                                           value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email"
                                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Телефон</label>
                                    <input type="tel" class="form-control" id="phone" name="phone"
                                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                           placeholder="+7 (___) ___-__-__">
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="avatar" class="form-label">Аватар</label><br>
                            <input type="file" id="avatar-input" accept="image/*" style="display: none;">
                            <button type="button" class="btn btn-outline-primary" id="avatar-btn">
                                <i class="bi bi-image me-2"></i>Выбрать изображение
                            </button>
                            <div class="form-text mt-2">Загрузите изображение для вашего профиля (JPG, PNG, GIF, WebP, макс. 5MB)</div>

                            <?php if (!empty($user['avatar'])): ?>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="delete_avatar" id="delete_avatar" value="1">
                                <label class="form-check-label text-danger" for="delete_avatar">
                                    <i class="bi bi-trash me-1"></i>Удалить текущий аватар
                                </label>
                            </div>
                            <?php endif; ?>
                        </div>

                        <hr class="my-4">

                        <h5 class="mb-3"><i class="bi bi-lock me-2"></i>Изменение пароля</h5>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Оставьте поля пустыми, если не хотите менять пароль
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Текущий пароль</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password">
                                </div>
                            </div>
                            <div class="col-md-6"></div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">Новый пароль</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password">
                                    <div class="form-text">Минимум 6 символов</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Подтвердите новый пароль</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <a href="profile.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-lg me-2"></i>Отмена
                            </a>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-save me-2"></i>Сохранить изменения
                            </button>
                        </div>
                        
                        <!-- Скрытые поля для данных кроппера -->
                        <input type="hidden" name="avatar_data" id="avatar-data" value="">
                        <input type="hidden" name="avatar_type" id="avatar-type" value="png">
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для кроппера аватара -->
<div class="modal fade" id="cropModal" tabindex="-1" aria-labelledby="cropModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cropModalLabel">
                    <i class="bi bi-image me-2"></i>Редактирование аватара
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="container-fluid">
                    <div class="row g-0">
                        <!-- Область с изображением -->
                        <div class="col-lg-8 bg-light d-flex align-items-center justify-content-center" style="min-height: 500px;">
                            <div class="crop-wrapper position-relative w-100 h-100 d-flex align-items-center justify-content-center p-3">
                                <img id="image-to-crop" src="" alt="Изображение для обрезки" class="img-fluid" style="max-height: 450px; max-width: 100%;">
                            </div>
                        </div>

                        <!-- Панель управления -->
                        <div class="col-lg-4 bg-white border-start">
                            <div class="p-4 h-100 d-flex flex-column">
                                <!-- Предпросмотр -->
                                <div class="mb-4 text-center">
                                    <h6 class="mb-3 text-dark">
                                        <i class="bi bi-eye me-2"></i>Предпросмотр
                                    </h6>
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

                                <!-- Мини-превью -->
                                <div class="mb-4">
                                    <h6 class="mb-3 text-dark text-center">
                                        <i class="bi bi-grid-3x3-gap me-2"></i>Размеры
                                    </h6>
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

                                <!-- Информация -->
                                <div class="mb-4 flex-grow-1">
                                    <div class="card bg-light border-light">
                                        <div class="card-body p-3">
                                            <h6 class="card-title text-dark mb-2">
                                                <i class="bi bi-info-circle me-2"></i>Информация
                                            </h6>
                                            <ul class="card-text text-muted mb-0 small" style="font-size: 13px;">
                                                <li>Перетащите рамку для выбора области</li>
                                                <li>Используйте колёсико для масштабирования</li>
                                                <li>Двойной клик для сброса</li>
                                                <li>Формат: <span id="image-format" class="text-primary">PNG</span></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <!-- Кнопки управления -->
                                <div class="mt-auto">
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-secondary flex-fill" data-bs-dismiss="modal">
                                            <i class="bi bi-x-lg me-1"></i>Отмена
                                        </button>
                                        <button type="button" class="btn btn-success flex-fill" id="crop-btn">
                                            <i class="bi bi-check-lg me-1"></i>Применить
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

<?php include 'includes/footer.php'; ?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

<script>
(function() {
    const avatarInput = document.getElementById('avatar-input');
    const avatarBtn = document.getElementById('avatar-btn');
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

    let cropper = null;
    let cropModal = null;
    let currentFileName = '';

    if (typeof bootstrap !== 'undefined' && cropModalElement) {
        cropModal = new bootstrap.Modal(cropModalElement, {
            backdrop: 'static',
            keyboard: true
        });
    } else {
        console.error('Bootstrap Modal не доступен!');
        return;
    }

    if (!avatarDataInput) {
        console.error('Скрытое поле avatar-data не найдено!');
        return;
    }

    if (avatarBtn) {
        avatarBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (avatarInput) avatarInput.click();
        });
    }

    function updatePreviews(imageData) {
        if (avatarPreview) avatarPreview.src = imageData;
        if (previewSmall) previewSmall.src = imageData;
        if (previewMedium) previewMedium.src = imageData;
        if (previewLarge) previewLarge.src = imageData;
    }

    if (avatarInput) {
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
                if (avatarTypeInput) {
                    avatarTypeInput.value = imageType;
                }
                
                if (imageFormatSpan) {
                    imageFormatSpan.textContent = imageType.toUpperCase();
                }

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
            cropBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Обработка...';

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
                    cropBtn.innerHTML = '<i class="bi bi-check-lg me-2"></i>Применить';
                    return;
                }

                const imageData = canvas.toDataURL('image/' + avatarTypeInput.value);

                if (imageData.length > 4 * 1024 * 1024) {
                    alert('Изображение слишком большое. Пожалуйста, выберите изображение меньшего размера.');
                    cropBtn.disabled = false;
                    cropBtn.innerHTML = '<i class="bi bi-check-lg me-2"></i>Применить';
                    return;
                }

                console.log('Обработка изображения:', {
                    файл: currentFileName,
                    тип: avatarTypeInput.value,
                    размер_данных: Math.round(imageData.length / 1024) + ' KB'
                });

                avatarDataInput.value = imageData;

                const currentAvatar = document.querySelector('.card-body.text-center img');
                if (currentAvatar) {
                    currentAvatar.src = imageData;
                }

                cropModal.hide();

                avatarInput.value = '';

                const successAlert = document.createElement('div');
                successAlert.className = 'alert alert-success alert-dismissible fade show';
                successAlert.innerHTML = `
                    <i class="bi bi-check-circle me-2"></i>
                    Аватар успешно загружен! Не забудьте сохранить изменения.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                const formElement = document.querySelector('form[method="POST"]');
                if (formElement) {
                    const existingAlert = formElement.querySelector('.alert-success');
                    if (existingAlert) {
                        existingAlert.remove();
                    }
                    formElement.insertBefore(successAlert, formElement.firstChild);
                    
                    setTimeout(() => {
                        successAlert.classList.remove('show');
                        setTimeout(() => successAlert.remove(), 150);
                    }, 5000);
                }

            } catch (error) {
                console.error('Ошибка при обработке изображения:', error);
                alert('Произошла ошибка при обработке изображения. Попробуйте ещё раз.');
            } finally {
                cropBtn.disabled = false;
                cropBtn.innerHTML = '<i class="bi bi-check-lg me-2"></i>Применить';
            }
        });
    }

    const cropModalEl = document.getElementById('cropModal');
    if (cropModalEl) {
        cropModalEl.addEventListener('hidden.bs.modal', function() {
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
            avatarInput.value = '';
        });

        cropModalEl.addEventListener('shown.bs.modal', function() {
            if (cropBtn) {
                cropBtn.focus();
            }
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && cropModalElement && cropModalElement.classList.contains('show')) {
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
            avatarInput.value = '';
        }
    });
})();
</script>
