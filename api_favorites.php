<?php
// /api_favorites.php
require_once 'includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Необходимо авторизоваться'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

    if ($product_id <= 0) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Неверный ID товара'
        ]);
        exit;
    }

    $check_query = "SELECT id FROM products WHERE id = ? AND is_active = 1";
    $check_stmt = $connection->prepare($check_query);
    $check_stmt->bind_param("i", $product_id);
    $check_stmt->execute();
    $product = $check_stmt->get_result()->fetch_assoc();

    if (!$product) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Товар не найден'
        ]);
        exit;
    }

    switch ($action) {
        case 'add':
            $insert_query = "INSERT IGNORE INTO favorites (user_id, product_id) VALUES (?, ?)";
            $insert_stmt = $connection->prepare($insert_query);
            $insert_stmt->bind_param("ii", $user_id, $product_id);

            if ($insert_stmt->execute()) {
                $count_query = "SELECT COUNT(*) as count FROM favorites WHERE user_id = ?";
                $count_stmt = $connection->prepare($count_query);
                $count_stmt->bind_param("i", $user_id);
                $count_stmt->execute();
                $count = $count_stmt->get_result()->fetch_assoc()['count'];

                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Товар добавлен в избранное',
                    'total_favorites' => $count
                ]);
            } else {
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Ошибка при добавлении в избранное'
                ]);
            }
            break;

        case 'remove':
            $delete_query = "DELETE FROM favorites WHERE user_id = ? AND product_id = ?";
            $delete_stmt = $connection->prepare($delete_query);
            $delete_stmt->bind_param("ii", $user_id, $product_id);

            if ($delete_stmt->execute()) {
                $count_query = "SELECT COUNT(*) as count FROM favorites WHERE user_id = ?";
                $count_stmt = $connection->prepare($count_query);
                $count_stmt->bind_param("i", $user_id);
                $count_stmt->execute();
                $count = $count_stmt->get_result()->fetch_assoc()['count'];

                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Товар удален из избранного',
                    'total_favorites' => $count
                ]);
            } else {
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Ошибка при удалении из избранного'
                ]);
            }
            break;

        case 'toggle':
            $check_query = "SELECT id FROM favorites WHERE user_id = ? AND product_id = ?";
            $check_stmt = $connection->prepare($check_query);
            $check_stmt->bind_param("ii", $user_id, $product_id);
            $check_stmt->execute();
            $exists = $check_stmt->get_result()->fetch_assoc();

            if ($exists) {
                $delete_query = "DELETE FROM favorites WHERE user_id = ? AND product_id = ?";
                $delete_stmt = $connection->prepare($delete_query);
                $delete_stmt->bind_param("ii", $user_id, $product_id);
                $delete_stmt->execute();
                $is_favorite = false;
            } else {
                $insert_query = "INSERT INTO favorites (user_id, product_id) VALUES (?, ?)";
                $insert_stmt = $connection->prepare($insert_query);
                $insert_stmt->bind_param("ii", $user_id, $product_id);
                $insert_stmt->execute();
                $is_favorite = true;
            }

            $count_query = "SELECT COUNT(*) as count FROM favorites WHERE user_id = ?";
            $count_stmt = $connection->prepare($count_query);
            $count_stmt->bind_param("i", $user_id);
            $count_stmt->execute();
            $count = $count_stmt->get_result()->fetch_assoc()['count'];

            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'is_favorite' => $is_favorite,
                'total_favorites' => $count
            ]);
            break;

        default:
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => 'Неверное действие'
            ]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'check':
            $product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

            if ($product_id <= 0) {
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Неверный ID товара'
                ]);
                exit;
            }

            $check_query = "SELECT id FROM favorites WHERE user_id = ? AND product_id = ?";
            $check_stmt = $connection->prepare($check_query);
            $check_stmt->bind_param("ii", $user_id, $product_id);
            $check_stmt->execute();
            $exists = $check_stmt->get_result()->fetch_assoc();

            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'is_favorite' => (bool)$exists
            ]);
            break;

        case 'count':
            $count_query = "SELECT COUNT(*) as count FROM favorites WHERE user_id = ?";
            $count_stmt = $connection->prepare($count_query);
            $count_stmt->bind_param("i", $user_id);
            $count_stmt->execute();
            $count = $count_stmt->get_result()->fetch_assoc()['count'];

            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'total_favorites' => $count
            ]);
            break;

        default:
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => 'Неверное действие'
            ]);
    }
    exit;
}

header('Content-Type: application/json');
echo json_encode([
    'status' => 'error',
    'message' => 'Неверный метод запроса'
]);
