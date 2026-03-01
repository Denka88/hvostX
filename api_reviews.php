<?php
// /api_reviews.php
require_once 'includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add':
            if (!isset($_SESSION['user_id'])) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Необходимо авторизоваться для оставления отзыва'
                ]);
                exit;
            }

            $user_id = $_SESSION['user_id'];
            $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
            $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
            $comment = trim($_POST['comment'] ?? '');
            $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

            require_once 'includes/recaptcha.php';
            $recaptcha_result = verifyRecaptcha($recaptcha_response);

            if (!$recaptcha_result['success']) {
                echo json_encode([
                    'status' => 'error',
                    'message' => $recaptcha_result['message']
                ]);
                exit;
            }

            if ($product_id <= 0) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Неверный ID товара'
                ]);
                exit;
            }

            if ($rating < 1 || $rating > 5) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Рейтинг должен быть от 1 до 5'
                ]);
                exit;
            }

            $check_query = "SELECT id FROM products WHERE id = ? AND is_active = 1";
            $check_stmt = $connection->prepare($check_query);
            $check_stmt->bind_param("i", $product_id);
            $check_stmt->execute();
            $product = $check_stmt->get_result()->fetch_assoc();

            if (!$product) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Товар не найден'
                ]);
                exit;
            }

            $check_review_query = "SELECT id FROM product_reviews WHERE user_id = ? AND product_id = ?";
            $check_review_stmt = $connection->prepare($check_review_query);
            $check_review_stmt->bind_param("ii", $user_id, $product_id);
            $check_review_stmt->execute();
            $existing_review = $check_review_stmt->get_result()->fetch_assoc();

            if ($existing_review) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Вы уже оставляли отзыв на этот товар'
                ]);
                exit;
            }

            $insert_query = "INSERT INTO product_reviews (user_id, product_id, rating, comment, is_approved) VALUES (?, ?, ?, ?, FALSE)";
            $insert_stmt = $connection->prepare($insert_query);
            $insert_stmt->bind_param("iiis", $user_id, $product_id, $rating, $comment);

            if ($insert_stmt->execute()) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Отзыв добавлен и отправлен на модерацию'
                ]);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Ошибка при добавлении отзыва'
                ]);
            }
            break;

        case 'approve':
        case 'reject':
        case 'delete':
            if (!isset($_SESSION['user_id'])) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Необходимо авторизоваться'
                ]);
                exit;
            }

            $user_id = $_SESSION['user_id'];
            $check_role_query = "SELECT role FROM users WHERE id = ?";
            $check_role_stmt = $connection->prepare($check_role_query);
            $check_role_stmt->bind_param("i", $user_id);
            $check_role_stmt->execute();
            $user = $check_role_stmt->get_result()->fetch_assoc();

            if (!$user || !in_array($user['role'], ['admin', 'moderator'])) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Недостаточно прав'
                ]);
                exit;
            }

            $review_id = isset($_POST['review_id']) ? (int)$_POST['review_id'] : 0;

            if ($review_id <= 0) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Неверный ID отзыва'
                ]);
                exit;
            }

            switch ($action) {
                case 'approve':
                    $update_query = "UPDATE product_reviews SET is_approved = TRUE WHERE id = ?";
                    break;
                case 'reject':
                case 'delete':
                    $update_query = "DELETE FROM product_reviews WHERE id = ?";
                    break;
            }

            $update_stmt = $connection->prepare($update_query);
            $update_stmt->bind_param("i", $review_id);

            if ($update_stmt->execute()) {
                echo json_encode([
                    'status' => 'success',
                    'message' => $action === 'approve' ? 'Отзыв одобрен' : 'Отзыв удален'
                ]);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Ошибка при обработке отзыва'
                ]);
            }
            break;

        default:
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
        case 'get':
            $product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

            if ($product_id <= 0) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Неверный ID товара'
                ]);
                exit;
            }

            $reviews_query = "SELECT r.*, u.name as user_name, u.avatar
                              FROM product_reviews r
                              LEFT JOIN users u ON r.user_id = u.id
                              WHERE r.product_id = ? AND r.is_approved = TRUE
                              ORDER BY r.created_at DESC";
            $reviews_stmt = $connection->prepare($reviews_query);
            $reviews_stmt->bind_param("i", $product_id);
            $reviews_stmt->execute();
            $reviews = $reviews_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            $stats_query = "SELECT
                            COUNT(*) as total,
                            AVG(rating) as average,
                            SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
                            SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
                            SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
                            SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
                            SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
                            FROM product_reviews
                            WHERE product_id = ? AND is_approved = TRUE";
            $stats_stmt = $connection->prepare($stats_query);
            $stats_stmt->bind_param("i", $product_id);
            $stats_stmt->execute();
            $stats = $stats_stmt->get_result()->fetch_assoc();

            echo json_encode([
                'status' => 'success',
                'reviews' => $reviews,
                'stats' => $stats
            ]);
            break;

        case 'check':
            if (!isset($_SESSION['user_id'])) {
                echo json_encode([
                    'status' => 'success',
                    'has_review' => false
                ]);
                exit;
            }

            $user_id = $_SESSION['user_id'];
            $product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

            if ($product_id <= 0) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Неверный ID товара'
                ]);
                exit;
            }

            $check_query = "SELECT id FROM product_reviews WHERE user_id = ? AND product_id = ?";
            $check_stmt = $connection->prepare($check_query);
            $check_stmt->bind_param("ii", $user_id, $product_id);
            $check_stmt->execute();
            $exists = $check_stmt->get_result()->fetch_assoc();

            echo json_encode([
                'status' => 'success',
                'has_review' => (bool)$exists
            ]);
            break;

        case 'pending':
            if (!isset($_SESSION['user_id'])) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Необходимо авторизоваться'
                ]);
                exit;
            }

            $user_id = $_SESSION['user_id'];
            $check_role_query = "SELECT role FROM users WHERE id = ?";
            $check_role_stmt = $connection->prepare($check_role_query);
            $check_role_stmt->bind_param("i", $user_id);
            $check_role_stmt->execute();
            $user = $check_role_stmt->get_result()->fetch_assoc();

            if (!$user || !in_array($user['role'], ['admin', 'moderator'])) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Недостаточно прав'
                ]);
                exit;
            }

            $pending_query = "SELECT r.*, u.name as user_name, p.name as product_name
                              FROM product_reviews r
                              LEFT JOIN users u ON r.user_id = u.id
                              LEFT JOIN products p ON r.product_id = p.id
                              WHERE r.is_approved = FALSE
                              ORDER BY r.created_at DESC";
            $pending_result = $connection->query($pending_query);
            $pending_reviews = $pending_result->fetch_all(MYSQLI_ASSOC);

            echo json_encode([
                'status' => 'success',
                'reviews' => $pending_reviews
            ]);
            break;

        default:
            echo json_encode([
                'status' => 'error',
                'message' => 'Неверное действие'
            ]);
    }
    exit;
}

echo json_encode([
    'status' => 'error',
    'message' => 'Неверный метод запроса'
]);
