<?php
// /admin/pet_categories.php
session_start();
require_once '../includes/db.php';
require_once 'auth.php';
require_once 'filter.php';

checkModeratorAccess();

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

if (isset($_GET['clear_filter'])) {
    $filter = new TableFilter($connection, 'pet_categories');
    $filter->clear();
    header("Location: pet_categories.php");
    exit;
}

$filter = new TableFilter($connection, 'pet_categories');
$filter->addField('name', 'text', 'Название')
       ->addField('is_active', 'select', 'Статус', [
           '1' => 'Активна',
           '0' => 'Неактивна'
       ]);

if (!empty($_GET) && !isset($_GET['action']) && !isset($_GET['clear_filter'])) {
    $filter->saveValues($_GET);
}

if ($action === 'delete' && $id > 0) {
    // Проверяем, есть ли товары в этой категории
    $check_query = "SELECT COUNT(*) as count FROM products WHERE pet_category_id = ?";
    $check_stmt = $connection->prepare($check_query);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $products_count = $check_stmt->get_result()->fetch_assoc()['count'];
    
    if ($products_count > 0) {
        $_SESSION['error_message'] = "Нельзя удалить категорию, в которой есть товары. Сначала переместите или удалите товары.";
        header("Location: pet_categories.php");
        exit;
    }
    
    $query = "DELETE FROM pet_categories WHERE id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: pet_categories.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $display_order = intval($_POST['display_order'] ?? 0);
    
    // Цвет всегда зеленый - акцентный
    $color = '#198754';
    
    // Генерируем slug из названия, если не указан
    if (empty($slug)) {
        $slug = transliterate($name);
    }
    
    // Обработка загрузки изображения
    $image_name = '';
    $image_error = '';
    $remove_image = isset($_POST['remove_image']) && $_POST['remove_image'] === 'on';
    
    // Если запрошено удаление изображения
    if ($remove_image && $id > 0) {
        $old_image_query = "SELECT image FROM pet_categories WHERE id = ?";
        $old_image_stmt = $connection->prepare($old_image_query);
        $old_image_stmt->bind_param("i", $id);
        $old_image_stmt->execute();
        $old_image = $old_image_stmt->get_result()->fetch_assoc()['image'] ?? '';
        if ($old_image && file_exists('../assets/images/categories/' . $old_image)) {
            unlink('../assets/images/categories/' . $old_image);
        }
        $image_name = null; // null означает, что нужно удалить изображение из БД
    }
    
    if (!empty($_FILES['image']['name'])) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $file_name = $_FILES['image']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $file_size = $_FILES['image']['size'] ?? 0;
        
        // Проверка размера файла (макс 5MB)
        if ($file_size > 5 * 1024 * 1024) {
            $image_error = 'Размер файла не должен превышать 5MB';
        } elseif (!in_array($file_ext, $allowed)) {
            $image_error = 'Допустимые форматы: jpg, jpeg, png, gif, webp';
        } else {
            $new_filename = 'category_' . time() . '_' . uniqid() . '.' . $file_ext;
            $upload_path = '../assets/images/categories/' . $new_filename;

            if (!file_exists('../assets/images/categories/')) {
                mkdir('../assets/images/categories/', 0777, true);
            }

            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                $image_name = $new_filename;
            } else {
                $image_error = 'Ошибка при загрузке файла. Проверьте права доступа к папке.';
            }
        }
    }
    
    // Если есть ошибка загрузки изображения, сохраняем её в сессию
    if (!empty($image_error)) {
        $_SESSION['upload_error'] = $image_error;
    }

    if (!empty($name)) {
        if ($id > 0) {
            // Обновление
            if ($image_name !== '' && !$remove_image) {
                // Загружено новое изображение (не удаление)
                // Удаляем старое изображение
                $old_image_query = "SELECT image FROM pet_categories WHERE id = ?";
                $old_image_stmt = $connection->prepare($old_image_query);
                $old_image_stmt->bind_param("i", $id);
                $old_image_stmt->execute();
                $old_image = $old_image_stmt->get_result()->fetch_assoc()['image'] ?? '';
                if ($old_image && file_exists('../assets/images/categories/' . $old_image)) {
                    unlink('../assets/images/categories/' . $old_image);
                }

                $query = "UPDATE pet_categories SET name = ?, slug = ?, description = ?, is_active = ?, display_order = ?, image = ? WHERE id = ?";
                $stmt = $connection->prepare($query);
                $stmt->bind_param("sssiisi", $name, $slug, $description, $is_active, $display_order, $image_name, $id);
            } elseif ($remove_image) {
                // Удаление изображения
                $query = "UPDATE pet_categories SET name = ?, slug = ?, description = ?, is_active = ?, display_order = ?, image = NULL WHERE id = ?";
                $stmt = $connection->prepare($query);
                $stmt->bind_param("sssisi", $name, $slug, $description, $is_active, $display_order, $id);
            } else {
                // Обновление без изменения изображения
                $query = "UPDATE pet_categories SET name = ?, slug = ?, description = ?, is_active = ?, display_order = ? WHERE id = ?";
                $stmt = $connection->prepare($query);
                $stmt->bind_param("sssisi", $name, $slug, $description, $is_active, $display_order, $id);
            }
        } else {
            // Создание
            $query = "INSERT INTO pet_categories (name, slug, description, is_active, display_order, image) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $connection->prepare($query);
            $stmt->bind_param("sssiis", $name, $slug, $description, $is_active, $display_order, $image_name);
        }
        $stmt->execute();
        $_SESSION['success_message'] = 'Категория успешно ' . ($id > 0 ? 'обновлена' : 'создана');
        header("Location: pet_categories.php");
        exit;
    }
}

// Функция транслитерации
function transliterate($text) {
    $converter = [
        'а' => 'a',    'б' => 'b',    'в' => 'v',    'г' => 'g',    'д' => 'd',
        'е' => 'e',    'ё' => 'e',    'ж' => 'zh',   'з' => 'z',    'и' => 'i',
        'й' => 'y',    'к' => 'k',    'л' => 'l',    'м' => 'm',    'н' => 'n',
        'о' => 'o',    'п' => 'p',    'р' => 'r',    'с' => 's',    'т' => 't',
        'у' => 'u',    'ф' => 'f',    'х' => 'h',    'ц' => 'c',    'ч' => 'ch',
        'ш' => 'sh',   'щ' => 'sch',  'ь' => '',     'ы' => 'y',    'ъ' => '',
        'э' => 'e',    'ю' => 'yu',   'я' => 'ya',
    ];
    
    $text = mb_strtolower($text);
    $text = strtr($text, $converter);
    $text = preg_replace('/[^a-z0-9\-]/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    $text = trim($text, '-');
    
    return $text;
}

$count_query = "SELECT COUNT(*) as total FROM pet_categories";
$count_result = mysqli_query($connection, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];

$pagination = new Pagination($total_records, 20);
$offset = $pagination->getOffset();
$limit = $pagination->getLimit();

list($where, $params, $types) = $filter->getSQL();

$query = "SELECT * FROM pet_categories $where ORDER BY display_order ASC, id DESC LIMIT $limit OFFSET $offset";
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
    $query = "SELECT * FROM pet_categories WHERE id = ?";
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
    <title>Категории животных - HvostX</title>
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
                <h1 class="mt-4 mb-4">Категории животных</h1>

                <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $_SESSION['error_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    <?php unset($_SESSION['error_message']); ?>
                </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['upload_error'])): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $_SESSION['upload_error']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    <?php unset($_SESSION['upload_error']); ?>
                </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $_SESSION['success_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    <?php unset($_SESSION['success_message']); ?>
                </div>
                <?php endif; ?>

                <?php echo $filter->render(); ?>

                <div class="row">
                    <div class="col-lg-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-paw me-1"></i>
                                <?php echo $edit_category ? 'Редактировать категорию' : 'Новая категория'; ?>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="pet_categories.php" enctype="multipart/form-data">
                                    <input type="hidden" name="id" value="<?php echo $edit_category['id'] ?? 0; ?>">

                                    <div class="mb-3">
                                        <label for="name" class="form-label">Название категории *</label>
                                        <input type="text" class="form-control" id="name" name="name"
                                               value="<?php echo htmlspecialchars($edit_category['name'] ?? ''); ?>" 
                                               placeholder="Например: Кошкам" required>
                                        <small class="text-muted">Название будет отображаться на карточке категории</small>
                                    </div>

                                    <div class="mb-3">
                                        <label for="slug" class="form-label">URL (slug)</label>
                                        <input type="text" class="form-control" id="slug" name="slug"
                                               value="<?php echo htmlspecialchars($edit_category['slug'] ?? ''); ?>" 
                                               placeholder="cats">
                                        <small class="text-muted">Оставьте пустым для автогенерации</small>
                                    </div>

                                    <div class="mb-3">
                                        <label for="description" class="form-label">Описание</label>
                                        <textarea class="form-control" id="description" name="description" 
                                                  rows="3" placeholder="Краткое описание категории"><?php echo htmlspecialchars($edit_category['description'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label for="image" class="form-label">Изображение категории</label>
                                        <?php if (!empty($edit_category['image'])): ?>
                                        <div class="mb-2">
                                            <img src="../assets/images/categories/<?php echo htmlspecialchars($edit_category['image']); ?>"
                                                 alt="Текущее изображение" class="img-thumbnail" style="max-height: 100px;">
                                            <div class="form-check mt-2">
                                                <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image">
                                                <label class="form-check-label text-danger" for="remove_image">
                                                    <i class="fas fa-trash me-1"></i>Удалить изображение
                                                </label>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                        <small class="text-muted">Рекомендуемый размер: 400x300px. Оставьте пустым, чтобы не менять.</small>
                                    </div>

                                    <div class="mb-3">
                                        <label for="display_order" class="form-label">Порядок отображения</label>
                                        <input type="number" class="form-control" id="display_order" name="display_order"
                                               value="<?php echo htmlspecialchars($edit_category['display_order'] ?? '0'); ?>"
                                               min="0">
                                        <small class="text-muted">Меньшее число = выше в списке</small>
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
                                        <a href="pet_categories.php" class="btn btn-secondary">
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
                                <i class="fas fa-paw me-1"></i>
                                Список категорий
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Порядок</th>
                                                <th>Изображение</th>
                                                <th>Название</th>
                                                <th>Slug</th>
                                                <th>Товаров</th>
                                                <th>Активна</th>
                                                <th>Действия</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($categories as $category): ?>
                                            <?php
                                            $product_count_query = "SELECT COUNT(*) as count FROM products WHERE pet_category_id = ?";
                                            $product_count_stmt = $connection->prepare($product_count_query);
                                            $product_count_stmt->bind_param("i", $category['id']);
                                            $product_count_stmt->execute();
                                            $product_count = $product_count_stmt->get_result()->fetch_assoc()['count'];
                                            ?>
                                            <tr>
                                                <td><?php echo $category['display_order']; ?></td>
                                                <td>
                                                    <?php if (!empty($category['image'])): ?>
                                                    <img src="../assets/images/categories/<?php echo htmlspecialchars($category['image']); ?>"
                                                         alt="<?php echo htmlspecialchars($category['name']); ?>"
                                                         class="img-thumbnail" style="width: 50px; height: 50px; object-fit: cover;">
                                                    <?php else: ?>
                                                    <div class="bg-light d-flex align-items-center justify-content-center"
                                                         style="width: 50px; height: 50px; border-radius: 4px; color: #198754; font-size: 1.5rem;">
                                                        <i class="fas fa-image"></i>
                                                    </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                                </td>
                                                <td><code><?php echo htmlspecialchars($category['slug']); ?></code></td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $product_count; ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $category['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                        <?php echo $category['is_active'] ? 'Да' : 'Нет'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="pet_categories.php?id=<?php echo $category['id']; ?>"
                                                       class="btn btn-sm btn-warning" data-bs-toggle="tooltip" title="Редактировать">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="pet_categories.php?action=delete&id=<?php echo $category['id']; ?>"
                                                       class="btn btn-sm btn-danger btn-delete" data-bs-toggle="tooltip" title="Удалить">
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
    document.addEventListener('DOMContentLoaded', function() {
        // Автогенерация slug при вводе названия
        const nameInput = document.getElementById('name');
        const slugInput = document.getElementById('slug');

        if (nameInput && slugInput) {
            nameInput.addEventListener('input', function() {
                if (slugInput && !slugInput.value) {
                    // Простая транслитерация на лету
                    const name = this.value.toLowerCase();
                    const converter = {
                        'а': 'a', 'б': 'b', 'в': 'v', 'г': 'g', 'д': 'd',
                        'е': 'e', 'ё': 'e', 'ж': 'zh', 'з': 'z', 'и': 'i',
                        'й': 'y', 'к': 'k', 'л': 'l', 'м': 'm', 'н': 'n',
                        'о': 'o', 'п': 'p', 'р': 'r', 'с': 's', 'т': 't',
                        'у': 'u', 'ф': 'f', 'х': 'h', 'ц': 'c', 'ч': 'ch',
                        'ш': 'sh', 'щ': 'sch', 'ь': '', 'ы': 'y', 'ъ': '',
                        'э': 'e', 'ю': 'yu', 'я': 'ya', ' ': '-'
                    };
                    let slug = name.split('').map(char => converter[char] || char).join('');
                    slug = slug.replace(/[^a-z0-9\-]/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
                    slugInput.value = slug;
                }
            });
        }
    });
    </script>
</body>
</html>
