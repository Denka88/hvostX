<?php
// /admin/messages.php
session_start();
require_once '../includes/db.php';
require_once 'auth.php';
require_once 'filter.php';

checkModeratorAccess();

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

if (isset($_GET['clear_filter'])) {
    $filter = new TableFilter($connection, 'contact_messages');
    $filter->clear();
    header("Location: messages.php");
    exit;
}

$filter = new TableFilter($connection, 'contact_messages');
$filter->addField('name', 'text', 'Имя')
       ->addField('email', 'text', 'Email')
       ->addField('is_read', 'select', 'Статус', [
           '0' => 'Непрочитанные',
           '1' => 'Прочитанные'
       ])
       ->addDateRange('created_at', 'Дата');

if (!empty($_GET) && !isset($_GET['action']) && !isset($_GET['clear_filter'])) {
    $filter->saveValues($_GET);
}

if ($action === 'delete' && $id > 0) {
    $query = "DELETE FROM contact_messages WHERE id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: messages.php");
    exit;
}

if ($action === 'mark_read' && $id > 0) {
    $query = "UPDATE contact_messages SET is_read = 1 WHERE id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: messages.php");
    exit;
}

if ($action === 'mark_all_read') {
    $query = "UPDATE contact_messages SET is_read = 1 WHERE is_read = 0";
    mysqli_query($connection, $query);
    header("Location: messages.php");
    exit;
}

$count_query = "SELECT COUNT(*) as total FROM contact_messages";
$count_result = mysqli_query($connection, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];

$pagination = new Pagination($total_records, 20);
$offset = $pagination->getOffset();
$limit = $pagination->getLimit();

list($where, $params, $types) = $filter->getSQL();

$query = "SELECT * FROM contact_messages $where ORDER BY is_read ASC, created_at DESC LIMIT $limit OFFSET $offset";
if (!empty($params)) {
    $stmt = $connection->prepare($query);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = mysqli_fetch_all($result, MYSQLI_ASSOC);
} else {
    $result = mysqli_query($connection, $query);
    $messages = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

$view_message = null;
if ($id > 0) {
    $query = "SELECT * FROM contact_messages WHERE id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $view_message = $stmt->get_result()->fetch_assoc();

    if ($view_message && !$view_message['is_read']) {
        $query = "UPDATE contact_messages SET is_read = 1 WHERE id = ?";
        $stmt = $connection->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
}

$unread_query = "SELECT COUNT(*) as count FROM contact_messages WHERE is_read = 0";
$unread_result = mysqli_query($connection, $unread_query);
$unread_count = mysqli_fetch_assoc($unread_result)['count'];
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Сообщения - HvostX</title>
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
                <h1 class="mt-4 mb-4">Сообщения от клиентов</h1>

                <?php if ($unread_count > 0): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="fas fa-info-circle me-2"></i>
                    У вас <strong><?php echo $unread_count; ?></strong> непрочитанное(ых) сообщение(ий)
                    <a href="messages.php?action=mark_all_read" class="alert-link ms-2">Отметить все как прочитанные</a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php echo $filter->render(); ?>

                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-envelope me-1"></i>
                            Список сообщений
                        </div>
                        <?php if ($unread_count > 0): ?>
                        <a href="messages.php?action=mark_all_read" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-check-double me-1"></i> Отметить все как прочитанные
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Имя</th>
                                        <th>Email</th>
                                        <th>Телефон</th>
                                        <th>Дата</th>
                                        <th>Статус</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($messages as $message): ?>
                                    <tr class="<?php echo !$message['is_read'] ? 'table-primary' : ''; ?>">
                                        <td><?php echo $message['id']; ?></td>
                                        <td><?php echo htmlspecialchars($message['name']); ?></td>
                                        <td>
                                            <a href="mailto:<?php echo htmlspecialchars($message['email']); ?>">
                                                <?php echo htmlspecialchars($message['email']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($message['phone'] ?? '-'); ?></td>
                                        <td><?php echo date('d.m.Y H:i', strtotime($message['created_at'])); ?></td>
                                        <td>
                                            <span class="badge <?php echo $message['is_read'] ? 'bg-secondary' : 'bg-warning text-dark'; ?>">
                                                <?php echo $message['is_read'] ? 'Прочитано' : 'Новое'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                    data-bs-target="#viewModal<?php echo $message['id']; ?>"
                                                    data-bs-toggle="tooltip" title="Просмотр">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if (!$message['is_read']): ?>
                                            <a href="messages.php?action=mark_read&id=<?php echo $message['id']; ?>"
                                               class="btn btn-sm btn-success"
                                               data-bs-toggle="tooltip" title="Отметить как прочитанное">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <?php endif; ?>
                                            <a href="messages.php?action=delete&id=<?php echo $message['id']; ?>"
                                               class="btn btn-sm btn-danger btn-delete"
                                               data-bs-toggle="tooltip" title="Удалить">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>

                                    <!-- Модальное окно просмотра -->
                                    <div class="modal fade" id="viewModal<?php echo $message['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Сообщение #<?php echo $message['id']; ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <p><strong>Имя:</strong> <?php echo htmlspecialchars($message['name']); ?></p>
                                                            <p><strong>Email:</strong>
                                                                <a href="mailto:<?php echo htmlspecialchars($message['email']); ?>">
                                                                    <?php echo htmlspecialchars($message['email']); ?>
                                                                </a>
                                                            </p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <p><strong>Телефон:</strong> <?php echo htmlspecialchars($message['phone'] ?? '-'); ?></p>
                                                            <p><strong>Дата:</strong> <?php echo date('d.m.Y H:i', strtotime($message['created_at'])); ?></p>
                                                        </div>
                                                    </div>
                                                    <hr>
                                                    <h6>Сообщение:</h6>
                                                    <div class="bg-light p-3 rounded">
                                                        <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <a href="mailto:<?php echo htmlspecialchars($message['email']); ?>"
                                                       class="btn btn-primary">
                                                        <i class="fas fa-reply me-1"></i> Ответить
                                                    </a>
                                                    <?php if (!$message['is_read']): ?>
                                                    <a href="messages.php?action=mark_read&id=<?php echo $message['id']; ?>"
                                                       class="btn btn-success">
                                                        <i class="fas fa-check me-1"></i> Отметить как прочитанное
                                                    </a>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
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
