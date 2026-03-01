<?php
// /admin/partners.php
session_start();
require_once '../includes/db.php';
require_once 'auth.php';
require_once 'filter.php';

checkModeratorAccess();

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

if (isset($_GET['clear_filter'])) {
    $filter = new TableFilter($connection, 'partners');
    $filter->clear();
    header("Location: partners.php");
    exit;
}

$filter = new TableFilter($connection, 'partners');
$filter->addField('name', 'text', 'Название')
       ->addField('is_active', 'select', 'Статус', [
           '1' => 'Активен',
           '0' => 'Неактивен'
       ])
       ->addDateRange('created_at', 'Дата добавления');

if (!empty($_GET) && !isset($_GET['action']) && !isset($_GET['clear_filter'])) {
    $filter->saveValues($_GET);
}

if ($action === 'delete' && $id > 0) {
    $query = "DELETE FROM partners WHERE id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: partners.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $partner_order = intval($_POST['partner_order'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $logo = $_POST['existing_logo'] ?? '';
    if (!empty($_FILES['logo']['name'])) {
        $target_dir = "../assets/images/partners/";

        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $imageFileType = strtolower(pathinfo($_FILES["logo"]["name"], PATHINFO_EXTENSION));
        $logo = 'partner_' . time() . '_' . uniqid() . '.' . $imageFileType;
        $target_file = $target_dir . $logo;

        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($imageFileType, $allowed_types)) {
            if (move_uploaded_file($_FILES["logo"]["tmp_name"], $target_file)) {
                if (!empty($_POST['existing_logo']) && file_exists($target_dir . $_POST['existing_logo'])) {
                    unlink($target_dir . $_POST['existing_logo']);
                }
            } else {
                $logo = $_POST['existing_logo'] ?? '';
            }
        } else {
            $logo = $_POST['existing_logo'] ?? '';
        }
    }

    if (!empty($name)) {
        if ($id > 0) {
            $query = "UPDATE partners SET name = ?, description = ?, logo = ?, website = ?, is_active = ?, partner_order = ? WHERE id = ?";
            $stmt = $connection->prepare($query);
            $stmt->bind_param("ssssiii", $name, $description, $logo, $website, $is_active, $partner_order, $id);
        } else {
            $query = "INSERT INTO partners (name, description, logo, website, is_active, partner_order) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $connection->prepare($query);
            $stmt->bind_param("ssssii", $name, $description, $logo, $website, $is_active, $partner_order);
        }
        $stmt->execute();
        header("Location: partners.php");
        exit;
    }
}

$count_query = "SELECT COUNT(*) as total FROM partners";
$count_result = mysqli_query($connection, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];

$pagination = new Pagination($total_records, 20);
$offset = $pagination->getOffset();
$limit = $pagination->getLimit();

list($where, $params, $types) = $filter->getSQL();

$query = "SELECT * FROM partners $where ORDER BY partner_order ASC, id DESC LIMIT $limit OFFSET $offset";
if (!empty($params)) {
    $stmt = $connection->prepare($query);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $partners = mysqli_fetch_all($result, MYSQLI_ASSOC);
} else {
    $result = mysqli_query($connection, $query);
    $partners = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

$edit_partner = null;
if ($id > 0) {
    $query = "SELECT * FROM partners WHERE id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_partner = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление партнерами - HvostX</title>
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
                <h1 class="mt-4 mb-4">Управление партнерами</h1>

                <?php echo $filter->render(); ?>

                <div class="row">
                    <div class="col-lg-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-plus me-1"></i>
                                <?php echo $edit_partner ? 'Редактировать партнера' : 'Новый партнер'; ?>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="partners.php" enctype="multipart/form-data">
                                    <input type="hidden" name="id" value="<?php echo $edit_partner['id'] ?? 0; ?>">
                                    <input type="hidden" name="existing_logo" value="<?php echo $edit_partner['logo'] ?? ''; ?>">

                                    <div class="mb-3">
                                        <label for="name" class="form-label">Название *</label>
                                        <input type="text" class="form-control" id="name" name="name"
                                               value="<?php echo htmlspecialchars($edit_partner['name'] ?? ''); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="description" class="form-label">Описание</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($edit_partner['description'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label for="logo" class="form-label">Логотип</label>
                                        <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
                                        <?php if (!empty($edit_partner['logo'])): ?>
                                        <div class="mt-2">
                                            <img src="../assets/images/partners/<?php echo htmlspecialchars($edit_partner['logo']); ?>"
                                                 alt="Текущий логотип" class="img-thumbnail" style="max-width: 200px;">
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="mb-3">
                                        <label for="website" class="form-label">Веб-сайт</label>
                                        <input type="url" class="form-control" id="website" name="website"
                                               value="<?php echo htmlspecialchars($edit_partner['website'] ?? ''); ?>"
                                               placeholder="https://example.com">
                                    </div>

                                    <div class="mb-3">
                                        <label for="partner_order" class="form-label">Порядок отображения</label>
                                        <input type="number" class="form-control" id="partner_order" name="partner_order"
                                               value="<?php echo $edit_partner['partner_order'] ?? 0; ?>" min="0">
                                    </div>

                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active"
                                               <?php echo (isset($edit_partner['is_active']) && $edit_partner['is_active']) || !$edit_partner ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active">Активен</label>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i> Сохранить
                                        </button>
                                        <?php if ($edit_partner): ?>
                                        <a href="partners.php" class="btn btn-secondary">
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
                                <i class="fas fa-handshake me-1"></i>
                                Список партнеров
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Логотип</th>
                                                <th>Название</th>
                                                <th>Веб-сайт</th>
                                                <th>Порядок</th>
                                                <th>Активен</th>
                                                <th>Действия</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($partners as $partner): ?>
                                            <tr>
                                                <td><?php echo $partner['id']; ?></td>
                                                <td>
                                                    <?php if (!empty($partner['logo'])): ?>
                                                    <img src="../assets/images/partners/<?php echo htmlspecialchars($partner['logo']); ?>"
                                                         alt="Логотип" class="img-thumbnail" style="max-width: 80px; height: 60px; object-fit: contain;">
                                                    <?php else: ?>
                                                    <span class="text-muted">Нет</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($partner['name']); ?></td>
                                                <td>
                                                    <?php if (!empty($partner['website'])): ?>
                                                    <a href="<?php echo htmlspecialchars($partner['website']); ?>" target="_blank" class="text-truncate d-inline-block" style="max-width: 200px;">
                                                        <?php echo htmlspecialchars($partner['website']); ?>
                                                    </a>
                                                    <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $partner['partner_order']; ?></td>
                                                <td>
                                                    <span class="badge <?php echo $partner['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                        <?php echo $partner['is_active'] ? 'Да' : 'Нет'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="partners.php?id=<?php echo $partner['id']; ?>" class="btn btn-sm btn-warning" data-bs-toggle="tooltip" title="Редактировать">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="partners.php?action=delete&id=<?php echo $partner['id']; ?>" class="btn btn-sm btn-danger btn-delete" data-bs-toggle="tooltip" title="Удалить">
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
