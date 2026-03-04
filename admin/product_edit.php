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
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $tag_ids = $_POST['tag_ids'] ?? [];
    $pet_category_id = $_POST['pet_category_id'] ?? null;
    $pet_category_id = !empty($pet_category_id) ? (int)$pet_category_id : null;

    $current_image = '';
    if ($id > 0) {
        $img_query = "SELECT image FROM products WHERE id = ?";
        $img_stmt = $connection->prepare($img_query);
        $img_stmt->bind_param("i", $id);
        $img_stmt->execute();
        $result = $img_stmt->get_result()->fetch_assoc();
        $current_image = $result['image'] ?? '';
        if ($current_image === '0' || $current_image === null) {
            $current_image = '';
        }
    }
    
    $image = $current_image;

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK && !empty($_FILES['image']['name'])) {
        $target_dir = "../assets/images/products/";

        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $imageFileType = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
        $new_image = 'product_' . time() . '_' . uniqid() . '.' . $imageFileType;
        $target_file = $target_dir . $new_image;

        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($imageFileType, $allowed_types)) {
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                if (!empty($current_image) && file_exists($target_dir . $current_image)) {
                    unlink($target_dir . $current_image);
                }
                $image = $new_image;
            }
        }
    }

    if ($id > 0) {
        $query = "UPDATE products SET name = ?, description = ?, price = ?, image = ?, is_active = ?, pet_category_id = ? WHERE id = ?";
        $stmt = $connection->prepare($query);
        $stmt->bind_param("ssdssii", $name, $description, $price, $image, $is_active, $pet_category_id, $id);
        $stmt->execute();

        $delete_tags_query = "DELETE FROM product_tags WHERE product_id = ?";
        $delete_stmt = $connection->prepare($delete_tags_query);
        $delete_stmt->bind_param("i", $id);
        $delete_stmt->execute();
        
        if (!empty($tag_ids)) {
            $insert_tag_query = "INSERT INTO product_tags (product_id, tag_id) VALUES (?, ?)";
            $insert_stmt = $connection->prepare($insert_tag_query);
            foreach ($tag_ids as $tag_id) {
                $tag_id = (int)$tag_id;
                if ($tag_id > 0) {
                    $insert_stmt->bind_param("ii", $id, $tag_id);
                    $insert_stmt->execute();
                }
            }
        }
    } else {
        $query = "INSERT INTO products (name, description, price, image, is_active, pet_category_id) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $connection->prepare($query);
        $stmt->bind_param("ssdssi", $name, $description, $price, $image, $is_active, $pet_category_id);
        $stmt->execute();
        $id = $connection->insert_id;

        if (!empty($tag_ids)) {
            $insert_tag_query = "INSERT INTO product_tags (product_id, tag_id) VALUES (?, ?)";
            $insert_stmt = $connection->prepare($insert_tag_query);
            foreach ($tag_ids as $tag_id) {
                $tag_id = (int)$tag_id;
                if ($tag_id > 0) {
                    $insert_stmt->bind_param("ii", $id, $tag_id);
                    $insert_stmt->execute();
                }
            }
        }
    }
    
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

    if (!$product) {
        header("Location: products.php");
        exit;
    }

    $product_tags_query = "SELECT tag_id FROM product_tags WHERE product_id = ?";
    $product_tags_stmt = $connection->prepare($product_tags_query);
    $product_tags_stmt->bind_param("i", $id);
    $product_tags_stmt->execute();
    $result = $product_tags_stmt->get_result();
    $product_tags = [];
    while ($row = $result->fetch_assoc()) {
        $product_tags[] = $row['tag_id'];
    }
}

$tags_query = "SELECT * FROM tags WHERE is_active = 1 ORDER BY name";
$tags_result = mysqli_query($connection, $tags_query);
$tags = mysqli_fetch_all($tags_result, MYSQLI_ASSOC);

$pet_categories_query = "SELECT * FROM pet_categories WHERE is_active = 1 ORDER BY display_order ASC, name ASC";
$pet_categories_result = mysqli_query($connection, $pet_categories_query);
$pet_categories = mysqli_fetch_all($pet_categories_result, MYSQLI_ASSOC);
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
                            <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($product['image'] ?? ''); ?>">

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
                                <label for="pet_category_id" class="form-label">Категория животного</label>
                                <select class="form-select" id="pet_category_id" name="pet_category_id">
                                    <option value="">-- Не выбрана --</option>
                                    <?php foreach ($pet_categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"
                                            <?php echo (isset($product['pet_category_id']) && $product['pet_category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Выберите категорию животных, для которых предназначен товар</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Теги</label>
                                <div class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                                    <?php if (empty($tags)): ?>
                                    <p class="text-muted mb-0">Теги не созданы. <a href="tags.php" target="_blank">Создать теги</a></p>
                                    <?php else: ?>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach ($tags as $tag): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="tag_ids[]" 
                                                   value="<?php echo $tag['id']; ?>" id="tag_<?php echo $tag['id']; ?>"
                                                   <?php echo (isset($product_tags) && in_array($tag['id'], $product_tags)) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="tag_<?php echo $tag['id']; ?>">
                                                <span class="badge" style="background-color: <?php echo htmlspecialchars($tag['color']); ?>;">
                                                    <?php echo htmlspecialchars($tag['name']); ?>
                                                </span>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted">Выберите один или несколько тегов для товара</small>
                            </div>

                            <div class="mb-3">
                                <label for="image" class="form-label">Изображение</label>
                                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                <?php if (!empty($product['image']) && $product['image'] !== '0'): ?>
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
