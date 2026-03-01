<?php
// /admin/product_edit.php
session_start();
require_once '../includes/db.php';
require_once 'auth.php';

checkModeratorAccess();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? 0;
    $category_id = $_POST['category_id'] ?? null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $image = $_POST['existing_image'] ?? '';
    if (!empty($_FILES['image']['name'])) {
        $target_dir = "../assets/images/products/";

        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $imageFileType = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
        $image = 'product_' . time() . '_' . uniqid() . '.' . $imageFileType;
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

    if ($id > 0) {
        $query = "UPDATE products SET name = ?, description = ?, price = ?, category_id = ?, image = ?, is_active = ? WHERE id = ?";
        $stmt = $connection->prepare($query);
        $stmt->bind_param("ssdissi", $name, $description, $price, $category_id, $image, $is_active, $id);
    } else {
        $query = "INSERT INTO products (name, description, price, category_id, image, is_active) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $connection->prepare($query);
        $stmt->bind_param("ssdiss", $name, $description, $price, $category_id, $image, $is_active);
    }

    $stmt->execute();
    header("Location: products.php");
    exit;
}

$id = $_GET['id'] ?? 0;
$product = null;
if ($id > 0) {
    $query = "SELECT * FROM products WHERE id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
}

$categories_query = "SELECT * FROM categories";
$categories_result = mysqli_query($connection, $categories_query);
$categories = mysqli_fetch_all($categories_result, MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $product ? 'Редактирование товара' : 'Добавление товара'; ?> - HvostX</title>
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
                <h1 class="mt-4 mb-4"><?php echo $product ? 'Редактирование товара' : 'Добавление товара'; ?></h1>

                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-edit me-1"></i>
                        <?php echo $product ? 'Редактировать товар' : 'Новый товар'; ?>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <input type="hidden" name="id" value="<?php echo $product['id'] ?? 0; ?>">
                            <input type="hidden" name="existing_image" value="<?php echo $product['image'] ?? ''; ?>">

                            <div class="mb-3">
                                <label for="name" class="form-label">Название *</label>
                                <input type="text" class="form-control" id="name" name="name"
                                       value="<?php echo htmlspecialchars($product['name'] ?? ''); ?>" required>
                                <div class="invalid-feedback">Пожалуйста, укажите название товара</div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Описание</label>
                                <textarea class="form-control" id="description" name="description" rows="5"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="price" class="form-label">Цена *</label>
                                <input type="number" class="form-control" id="price" name="price"
                                       value="<?php echo $product['price'] ?? ''; ?>" step="0.01" min="0" required>
                                <div class="invalid-feedback">Пожалуйста, укажите цену товара</div>
                            </div>

                            <div class="mb-3">
                                <label for="category_id" class="form-label">Категория</label>
                                <select class="form-select" id="category_id" name="category_id">
                                    <option value="">Без категории</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>"
                                            <?php echo (isset($product['category_id']) && $product['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="image" class="form-label">Изображение</label>
                                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                <?php if (!empty($product['image'])): ?>
                                <div class="mt-2">
                                    <img src="../assets/images/products/<?php echo htmlspecialchars($product['image']); ?>"
                                         alt="Текущее изображение" class="img-thumbnail" style="max-width: 200px;">
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active"
                                       <?php echo (isset($product['is_active']) && $product['is_active']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">Активен</label>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Сохранить
                                </button>
                                <a href="products.php" class="btn btn-secondary ms-2">
                                    <i class="fas fa-times me-1"></i> Отмена
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
</body>
</html>
