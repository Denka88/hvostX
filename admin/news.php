<?php
// /admin/news.php
session_start();
require_once '../includes/db.php';
require_once 'auth.php';
require_once 'filter.php';

checkModeratorAccess();

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

if (isset($_GET['clear_filter'])) {
    $filter = new TableFilter($connection, 'news');
    $filter->clear();
    header("Location: news.php");
    exit;
}

$filter = new TableFilter($connection, 'news');
$filter->addField('title', 'text', 'Заголовок')
       ->addField('is_active', 'select', 'Статус', [
           '1' => 'Активна',
           '0' => 'Неактивна'
       ])
       ->addDateRange('created_at', 'Дата публикации');

if (!empty($_GET) && !isset($_GET['action']) && !isset($_GET['clear_filter'])) {
    $filter->saveValues($_GET);
}

if ($action === 'delete' && $id > 0) {
    $query = "DELETE FROM news WHERE id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: news.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $image = $_POST['existing_image'] ?? '';
    if (!empty($_FILES['image']['name'])) {
        $target_dir = "../assets/images/news/";

        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $imageFileType = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
        $image = 'news_' . time() . '_' . uniqid() . '.' . $imageFileType;
        $target_file = $target_dir . $image;

        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($imageFileType, $allowed_types)) {
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                if (!empty($_POST['existing_image']) && file_exists($target_dir . $_POST['existing_image'])) {
                    unlink($target_dir . $_POST['existing_image']);
                }
            } else {
                $image = $_POST['existing_image'] ?? '';
            }
        } else {
            $image = $_POST['existing_image'] ?? '';
        }
    }

    if (!empty($title)) {
        if ($id > 0) {
            $query = "UPDATE news SET title = ?, content = ?, image = ?, is_active = ? WHERE id = ?";
            $stmt = $connection->prepare($query);
            $stmt->bind_param("ssssi", $title, $content, $image, $is_active, $id);
        } else {
            $query = "INSERT INTO news (title, content, image, is_active) VALUES (?, ?, ?, ?)";
            $stmt = $connection->prepare($query);
            $stmt->bind_param("sssi", $title, $content, $image, $is_active);
        }
        $stmt->execute();
        header("Location: news.php");
        exit;
    }
}

$count_query = "SELECT COUNT(*) as total FROM news";
$count_result = mysqli_query($connection, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];

$pagination = new Pagination($total_records, 20);
$offset = $pagination->getOffset();
$limit = $pagination->getLimit();

list($where, $params, $types) = $filter->getSQL();

$query = "SELECT * FROM news $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
if (!empty($params)) {
    $stmt = $connection->prepare($query);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $news_items = mysqli_fetch_all($result, MYSQLI_ASSOC);
} else {
    $result = mysqli_query($connection, $query);
    $news_items = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

$edit_news = null;
if ($id > 0) {
    $query = "SELECT * FROM news WHERE id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_news = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление новостями - HvostX</title>
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
                <h1 class="mt-4 mb-4">Управление новостями</h1>

                <?php echo $filter->render(); ?>

                <div class="row">
                    <div class="col-lg-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-plus me-1"></i>
                                <?php echo $edit_news ? 'Редактировать новость' : 'Новая новость'; ?>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="news.php" enctype="multipart/form-data">
                                    <input type="hidden" name="id" value="<?php echo $edit_news['id'] ?? 0; ?>">
                                    <input type="hidden" name="existing_image" value="<?php echo $edit_news['image'] ?? ''; ?>">

                                    <div class="mb-3">
                                        <label for="title" class="form-label">Заголовок *</label>
                                        <input type="text" class="form-control" id="title" name="title"
                                               value="<?php echo htmlspecialchars($edit_news['title'] ?? ''); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="content" class="form-label">Содержимое</label>
                                        <textarea class="form-control" id="content" name="content" rows="8"><?php echo htmlspecialchars($edit_news['content'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label for="image" class="form-label">Изображение</label>
                                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                        <?php if (!empty($edit_news['image'])): ?>
                                        <div class="mt-2">
                                            <img src="../assets/images/news/<?php echo htmlspecialchars($edit_news['image']); ?>"
                                                 alt="Текущее изображение" class="img-thumbnail" style="max-width: 200px;">
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active"
                                               <?php echo (isset($edit_news['is_active']) && $edit_news['is_active']) || !$edit_news ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active">Активна</label>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i> Сохранить
                                        </button>
                                        <?php if ($edit_news): ?>
                                        <a href="news.php" class="btn btn-secondary">
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
                                <i class="fas fa-newspaper me-1"></i>
                                Список новостей
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Заголовок</th>
                                                <th>Изображение</th>
                                                <th>Активна</th>
                                                <th>Дата создания</th>
                                                <th>Действия</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($news_items as $news): ?>
                                            <tr>
                                                <td><?php echo $news['id']; ?></td>
                                                <td><?php echo htmlspecialchars($news['title']); ?></td>
                                                <td>
                                                    <?php if (!empty($news['image'])): ?>
                                                    <img src="../assets/images/news/<?php echo htmlspecialchars($news['image']); ?>"
                                                         alt="Изображение" class="img-thumbnail" style="max-width: 80px; height: 60px; object-fit: cover;">
                                                    <?php else: ?>
                                                    <span class="text-muted">Нет</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $news['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                        <?php echo $news['is_active'] ? 'Да' : 'Нет'; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('d.m.Y', strtotime($news['created_at'])); ?></td>
                                                <td>
                                                    <a href="news.php?id=<?php echo $news['id']; ?>" class="btn btn-sm btn-warning" data-bs-toggle="tooltip" title="Редактировать">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="news.php?action=delete&id=<?php echo $news['id']; ?>" class="btn btn-sm btn-danger btn-delete" data-bs-toggle="tooltip" title="Удалить">
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
