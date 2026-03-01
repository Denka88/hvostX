<?php
// /admin/products.php
session_start();
require_once '../includes/db.php';
require_once 'auth.php';
require_once 'filter.php';

checkModeratorAccess();

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

if (isset($_GET['clear_filter'])) {
    $filter = new TableFilter($connection, 'products', 'p');
    $filter->clear();
    header("Location: products.php");
    exit;
}

$categories_query = "SELECT * FROM categories WHERE is_active = 1";
$categories_result = mysqli_query($connection, $categories_query);
$categories_list = mysqli_fetch_all($categories_result, MYSQLI_ASSOC);
$category_options = [];
foreach ($categories_list as $cat) {
    $category_options[$cat['id']] = $cat['name'];
}

$filter = new TableFilter($connection, 'products', 'p');
$filter->addField('name', 'text', 'Название')
       ->addField('category_id', 'select', 'Категория', $category_options)
       ->addField('is_active', 'select', 'Статус', [
           '1' => 'Активен',
           '0' => 'Неактивен'
       ])
       ->addDateRange('created_at', 'Дата добавления');

if (!empty($_GET) && !isset($_GET['action']) && !isset($_GET['clear_filter'])) {
    $filter->saveValues($_GET);
}

if ($action === 'delete' && $id > 0) {
    $query = "DELETE FROM products WHERE id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: products.php");
    exit;
}

$count_query = "SELECT COUNT(*) as total FROM products";
$count_result = mysqli_query($connection, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];

$pagination = new Pagination($total_records, 20);
$offset = $pagination->getOffset();
$limit = $pagination->getLimit();

list($where, $params, $types) = $filter->getSQL();

$query = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id $where ORDER BY p.created_at DESC LIMIT $limit OFFSET $offset";
if (!empty($params)) {
    $stmt = $connection->prepare($query);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $products = mysqli_fetch_all($result, MYSQLI_ASSOC);
} else {
    $result = mysqli_query($connection, $query);
    $products = mysqli_fetch_all($result, MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление товарами - HvostX</title>
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
                <h1 class="mt-4 mb-4">Управление товарами</h1>

                <?php echo $filter->render(); ?>

                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-box me-1"></i>
                            Список товаров
                        </div>
                        <a href="product_edit.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus me-1"></i> Добавить товар
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Название</th>
                                        <th>Категория</th>
                                        <th>Цена</th>
                                        <th>Активен</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td><?php echo $product['id']; ?></td>
                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td><?php echo htmlspecialchars($product['category_name'] ?? 'Без категории'); ?></td>
                                        <td><?php echo number_format($product['price'], 2); ?> ₽</td>
                                        <td>
                                            <span class="badge <?php echo $product['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo $product['is_active'] ? 'Да' : 'Нет'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="product_edit.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-warning" data-bs-toggle="tooltip" title="Редактировать">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="products.php?action=delete&id=<?php echo $product['id']; ?>" class="btn btn-sm btn-danger btn-delete" data-bs-toggle="tooltip" title="Удалить">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
</body>
</html>
