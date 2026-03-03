<?php
// /admin/tags.php
session_start();
require_once '../includes/db.php';
require_once 'auth.php';
require_once 'filter.php';

checkModeratorAccess();

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

if (isset($_GET['clear_filter'])) {
    $filter = new TableFilter($connection, 'tags');
    $filter->clear();
    header("Location: tags.php");
    exit;
}

$filter = new TableFilter($connection, 'tags');
$filter->addField('name', 'text', 'Название')
       ->addField('is_active', 'select', 'Статус', [
           '1' => 'Активен',
           '0' => 'Неактивен'
       ]);

if (!empty($_GET) && !isset($_GET['action']) && !isset($_GET['clear_filter'])) {
    $filter->saveValues($_GET);
}

if ($action === 'delete' && $id > 0) {
    $query = "DELETE FROM tags WHERE id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: tags.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    $name = trim($_POST['name'] ?? '');
    $color = trim($_POST['color'] ?? '#0dcaf0');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (!empty($name)) {
        if ($id > 0) {
            $query = "UPDATE tags SET name = ?, color = ?, is_active = ? WHERE id = ?";
            $stmt = $connection->prepare($query);
            $stmt->bind_param("ssii", $name, $color, $is_active, $id);
        } else {
            $query = "INSERT INTO tags (name, color, is_active) VALUES (?, ?, ?)";
            $stmt = $connection->prepare($query);
            $stmt->bind_param("ssi", $name, $color, $is_active);
        }
        $stmt->execute();
        header("Location: tags.php");
        exit;
    }
}

$count_query = "SELECT COUNT(*) as total FROM tags";
$count_result = mysqli_query($connection, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];

$pagination = new Pagination($total_records, 20);
$offset = $pagination->getOffset();
$limit = $pagination->getLimit();

list($where, $params, $types) = $filter->getSQL();

$query = "SELECT * FROM tags $where ORDER BY id DESC LIMIT $limit OFFSET $offset";
if (!empty($params)) {
    $stmt = $connection->prepare($query);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $tags = mysqli_fetch_all($result, MYSQLI_ASSOC);
} else {
    $result = mysqli_query($connection, $query);
    $tags = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

$edit_tag = null;
if ($id > 0) {
    $query = "SELECT * FROM tags WHERE id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_tag = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление тегами - HvostX</title>
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
                <h1 class="mt-4 mb-4">Управление тегами</h1>

                <?php echo $filter->render(); ?>

                <div class="row">
                    <div class="col-lg-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-plus me-1"></i>
                                <?php echo $edit_tag ? 'Редактировать тег' : 'Новый тег'; ?>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="tags.php">
                                    <input type="hidden" name="id" value="<?php echo $edit_tag['id'] ?? 0; ?>">

                                    <div class="mb-3">
                                        <label for="name" class="form-label">Название *</label>
                                        <input type="text" class="form-control" id="name" name="name"
                                               value="<?php echo htmlspecialchars($edit_tag['name'] ?? ''); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="color" class="form-label">Цвет тега</label>
                                        <div class="input-group">
                                            <input type="color" class="form-control form-control-color" id="color" name="color"
                                                   value="<?php echo htmlspecialchars($edit_tag['color'] ?? '#0dcaf0'); ?>"
                                                   title="Выберите цвет">
                                            <input type="text" class="form-control" id="color_text" 
                                                   value="<?php echo htmlspecialchars($edit_tag['color'] ?? '#0dcaf0'); ?>"
                                                   placeholder="#0dcaf0">
                                        </div>
                                        <small class="text-muted">Цвет, который будет отображаться на карточке товара</small>
                                    </div>

                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active"
                                               <?php echo (isset($edit_tag['is_active']) && $edit_tag['is_active']) || !$edit_tag ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active">Активен</label>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i> Сохранить
                                        </button>
                                        <?php if ($edit_tag): ?>
                                        <a href="tags.php" class="btn btn-secondary">
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
                                <i class="fas fa-tags me-1"></i>
                                Список тегов
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Название</th>
                                                <th>Цвет</th>
                                                <th>Активен</th>
                                                <th>Товаров</th>
                                                <th>Действия</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($tags as $tag): ?>
                                            <?php
                                            $product_count_query = "SELECT COUNT(*) as count FROM product_tags WHERE tag_id = ?";
                                            $product_count_stmt = $connection->prepare($product_count_query);
                                            $product_count_stmt->bind_param("i", $tag['id']);
                                            $product_count_stmt->execute();
                                            $product_count = $product_count_stmt->get_result()->fetch_assoc()['count'];
                                            ?>
                                            <tr>
                                                <td><?php echo $tag['id']; ?></td>
                                                <td>
                                                    <span class="badge" style="background-color: <?php echo htmlspecialchars($tag['color']); ?>;">
                                                        <?php echo htmlspecialchars($tag['name']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <input type="color" value="<?php echo htmlspecialchars($tag['color']); ?>" disabled 
                                                           style="width: 40px; height: 25px; border: none;">
                                                    <small class="ms-2"><?php echo htmlspecialchars($tag['color']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $tag['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                        <?php echo $tag['is_active'] ? 'Да' : 'Нет'; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $product_count; ?></td>
                                                <td>
                                                    <a href="tags.php?id=<?php echo $tag['id']; ?>" class="btn btn-sm btn-warning" data-bs-toggle="tooltip" title="Редактировать">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="tags.php?action=delete&id=<?php echo $tag['id']; ?>" class="btn btn-sm btn-danger btn-delete" data-bs-toggle="tooltip" title="Удалить">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
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
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
    // Синхронизация color picker и текстового поля
    document.getElementById('color').addEventListener('input', function() {
        document.getElementById('color_text').value = this.value;
    });
    document.getElementById('color_text').addEventListener('input', function() {
        if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
            document.getElementById('color').value = this.value;
        }
    });
    </script>
</body>
</html>
