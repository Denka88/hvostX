<?php
// /admin/redirects.php
session_start();
require_once '../includes/db.php';
require_once 'auth.php';

checkModeratorAccess();

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

// Удаление редиректа
if ($action === 'delete' && $id > 0) {
    $query = "DELETE FROM redirects WHERE id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: redirects.php");
    exit;
}

// Сброс счётчика
if ($action === 'reset_hits' && $id > 0) {
    $query = "UPDATE redirects SET hits = 0 WHERE id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: redirects.php");
    exit;
}

// Сохранение редиректа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    $from_url = trim($_POST['from_url'] ?? '');
    $to_url = trim($_POST['to_url'] ?? '');
    $redirect_type = $_POST['redirect_type'] ?? '301';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Добавляем слеш в начало если нет
    if ($from_url && $from_url[0] !== '/') {
        $from_url = '/' . $from_url;
    }
    if ($to_url && $to_url[0] !== '/' && !preg_match('#^https?://#', $to_url)) {
        $to_url = '/' . $to_url;
    }

    if (!empty($from_url) && !empty($to_url)) {
        if ($id > 0) {
            $query = "UPDATE redirects SET from_url = ?, to_url = ?, redirect_type = ?, is_active = ? WHERE id = ?";
            $stmt = $connection->prepare($query);
            $stmt->bind_param("sssii", $from_url, $to_url, $redirect_type, $is_active, $id);
        } else {
            $query = "INSERT INTO redirects (from_url, to_url, redirect_type, is_active) VALUES (?, ?, ?, ?)";
            $stmt = $connection->prepare($query);
            $stmt->bind_param("sssi", $from_url, $to_url, $redirect_type, $is_active);
        }
        $stmt->execute();
        header("Location: redirects.php");
        exit;
    }
}

// Получаем все редиректы
$query = "SELECT * FROM redirects ORDER BY created_at DESC";
$result = mysqli_query($connection, $query);
$redirects = mysqli_fetch_all($result, MYSQLI_ASSOC);

$edit_redirect = null;
if ($id > 0) {
    $query = "SELECT * FROM redirects WHERE id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_redirect = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление редиректами - HvostX</title>
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
                <h1 class="mt-4 mb-4">Управление редиректами</h1>

                <div class="row">
                    <div class="col-lg-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-exchange-alt me-1"></i>
                                <?php echo $edit_redirect ? 'Редактировать редирект' : 'Новый редирект'; ?>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="redirects.php">
                                    <input type="hidden" name="id" value="<?php echo $edit_redirect['id'] ?? 0; ?>">

                                    <div class="mb-3">
                                        <label for="from_url" class="form-label">Откуда (URL) *</label>
                                        <input type="text" class="form-control" id="from_url" name="from_url"
                                               value="<?php echo htmlspecialchars($edit_redirect['from_url'] ?? ''); ?>"
                                               placeholder="/old-page" required>
                                        <small class="text-muted">Например: /old-category или /old-product</small>
                                    </div>

                                    <div class="mb-3">
                                        <label for="to_url" class="form-label">Куда (URL) *</label>
                                        <input type="text" class="form-control" id="to_url" name="to_url"
                                               value="<?php echo htmlspecialchars($edit_redirect['to_url'] ?? ''); ?>"
                                               placeholder="/new-page" required>
                                        <small class="text-muted">Например: /products.php или https://example.com</small>
                                    </div>

                                    <div class="mb-3">
                                        <label for="redirect_type" class="form-label">Тип редиректа</label>
                                        <select class="form-select" id="redirect_type" name="redirect_type">
                                            <option value="301" <?php echo (isset($edit_redirect['redirect_type']) && $edit_redirect['redirect_type'] === '301') || !$edit_redirect ? 'selected' : ''; ?>>
                                                301 - Постоянный (для SEO)
                                            </option>
                                            <option value="302" <?php echo isset($edit_redirect['redirect_type']) && $edit_redirect['redirect_type'] === '302' ? 'selected' : ''; ?>>
                                                302 - Временный
                                            </option>
                                        </select>
                                        <small class="text-muted">
                                            <strong>301</strong> — страница перемещена навсегда (поисковики обновят индекс)<br>
                                            <strong>302</strong> — временное перемещение
                                        </small>
                                    </div>

                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active"
                                               <?php echo (isset($edit_redirect['is_active']) && $edit_redirect['is_active']) || !$edit_redirect ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active">Активен</label>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i> Сохранить
                                        </button>
                                        <?php if ($edit_redirect): ?>
                                        <a href="redirects.php" class="btn btn-secondary">
                                            <i class="fas fa-times me-1"></i> Отмена
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-list me-1"></i>
                                Список редиректов
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Откуда</th>
                                                <th>Куда</th>
                                                <th>Тип</th>
                                                <th>Переходов</th>
                                                <th>Статус</th>
                                                <th>Действия</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($redirects as $redirect): ?>
                                            <tr>
                                                <td><?php echo $redirect['id']; ?></td>
                                                <td>
                                                    <code><?php echo htmlspecialchars($redirect['from_url']); ?></code>
                                                </td>
                                                <td>
                                                    <code><?php echo htmlspecialchars($redirect['to_url']); ?></code>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $redirect['redirect_type'] === '301' ? 'bg-primary' : 'bg-info'; ?>">
                                                        <?php echo $redirect['redirect_type']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?php echo $redirect['hits']; ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $redirect['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                        <?php echo $redirect['is_active'] ? 'Активен' : 'Неактивен'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="redirects.php?id=<?php echo $redirect['id']; ?>" 
                                                       class="btn btn-sm btn-warning" data-bs-toggle="tooltip" title="Редактировать">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="redirects.php?action=reset_hits&id=<?php echo $redirect['id']; ?>" 
                                                       class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="Сбросить счётчик">
                                                        <i class="fas fa-sync-alt"></i>
                                                    </a>
                                                    <a href="redirects.php?action=delete&id=<?php echo $redirect['id']; ?>" 
                                                       class="btn btn-sm btn-danger btn-delete" data-bs-toggle="tooltip" title="Удалить">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-info-circle me-1"></i>
                                Справка по редиректам
                            </div>
                            <div class="card-body">
                                <h6>Как использовать:</h6>
                                <ul class="mb-0">
                                    <li><strong>Откуда</strong> — старый URL (например, <code>/old-product</code>)</li>
                                    <li><strong>Куда</strong> — новый URL (например, <code>/products.php</code>)</li>
                                    <li><strong>301 редирект</strong> — используйте когда страница перемещена навсегда (лучше для SEO)</li>
                                    <li><strong>302 редирект</strong> — используйте для временных акций, распродаж</li>
                                    <li><strong>Переходов</strong> — количество раз, когда сработал этот редирект</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
</body>
</html>
