<?php
// /admin/categories.php
session_start();
require_once '../includes/db.php';
require_once 'auth.php';
require_once 'filter.php';

checkModeratorAccess();

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

if (isset($_GET['clear_filter'])) {
    $filter = new TableFilter($connection, 'categories');
    $filter->clear();
    header("Location: categories.php");
    exit;
}

$filter = new TableFilter($connection, 'categories');
$filter->addField('name', 'text', 'Название')
       ->addField('is_active', 'select', 'Статус', [
           '1' => 'Активна',
           '0' => 'Неактивна'
       ]);

if (!empty($_GET) && !isset($_GET['action']) && !isset($_GET['clear_filter'])) {
    $filter->saveValues($_GET);
}

if ($action === 'delete' && $id > 0) {
    $query = "DELETE FROM categories WHERE id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: categories.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (!empty($name)) {
        if ($id > 0) {
            $query = "UPDATE categories SET name = ?, description = ?, is_active = ? WHERE id = ?";
            $stmt = $connection->prepare($query);
            $stmt->bind_param("ssii", $name, $description, $is_active, $id);
        } else {
            $query = "INSERT INTO categories (name, description, is_active) VALUES (?, ?, ?)";
            $stmt = $connection->prepare($query);
            $stmt->bind_param("ssi", $name, $description, $is_active);
        }
        $stmt->execute();
        header("Location: categories.php");
        exit;
    }
}

$count_query = "SELECT COUNT(*) as total FROM categories";
$count_result = mysqli_query($connection, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];

$pagination = new Pagination($total_records, 20);
$offset = $pagination->getOffset();
$limit = $pagination->getLimit();

list($where, $params, $types) = $filter->getSQL();

$query = "SELECT * FROM categories $where ORDER BY id DESC LIMIT $limit OFFSET $offset";
if (!empty($params)) {
    $stmt = $connection->prepare($query);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $categories = mysqli_fetch_all($result, MYSQLI_ASSOC);
} else {
    $result = mysqli_query($connection, $query);
    $categories = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

$edit_category = null;
if ($id > 0) {
    $query = "SELECT * FROM categories WHERE id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_category = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление категориями - HvostX</title>
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
                <h1 class="mt-4 mb-4">Управление категориями</h1>

                <?php echo $filter->render(); ?>

                <div class="row">
                    <div class="col-lg-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-plus me-1"></i>
                                <?php echo $edit_category ? 'Редактировать категорию' : 'Новая категория'; ?>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="categories.php">
                                    <input type="hidden" name="id" value="<?php echo $edit_category['id'] ?? 0; ?>">

                                    <div class="mb-3">
                                        <label for="name" class="form-label">Название *</label>
                                        <input type="text" class="form-control" id="name" name="name"
                                               value="<?php echo htmlspecialchars($edit_category['name'] ?? ''); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="description" class="form-label">Описание</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($edit_category['description'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active"
                                               <?php echo (isset($edit_category['is_active']) && $edit_category['is_active']) || !$edit_category ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active">Активна</label>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i> Сохранить
                                        </button>
                                        <?php if ($edit_category): ?>
                                        <a href="categories.php" class="btn btn-secondary">
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
                                Список категорий
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Название</th>
                                                <th>Описание</th>
                                                <th>Активна</th>
                                                <th>Действия</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($categories as $category): ?>
                                            <tr>
                                                <td><?php echo $category['id']; ?></td>
                                                <td><?php echo htmlspecialchars($category['name']); ?></td>
                                                <td><?php echo htmlspecialchars($category['description'] ?? '-'); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $category['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                        <?php echo $category['is_active'] ? 'Да' : 'Нет'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="categories.php?id=<?php echo $category['id']; ?>" class="btn btn-sm btn-warning" data-bs-toggle="tooltip" title="Редактировать">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="categories.php?action=delete&id=<?php echo $category['id']; ?>" class="btn btn-sm btn-danger btn-delete" data-bs-toggle="tooltip" title="Удалить">
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
</body>
</html>
