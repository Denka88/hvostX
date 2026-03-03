<?php
// /admin/404.php
session_start();
require_once 'auth.php';

checkModeratorAccess();

$page_title = "Страница не найдена - 404";
$http_response_code = 404;
http_response_code($http_response_code);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Страница не найдена</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
    .error-page {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .error-content {
        text-align: center;
        max-width: 600px;
        padding: 40px;
        background: white;
        border-radius: 20px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }

    .error-code {
        font-size: 120px;
        font-weight: 900;
        color: #667eea;
        line-height: 1;
        margin-bottom: 20px;
        text-shadow: 3px 3px 0 rgba(0,0,0,0.1);
    }

    .error-title {
        font-size: 28px;
        font-weight: 700;
        color: #333;
        margin-bottom: 20px;
    }

    .error-text {
        font-size: 16px;
        color: #666;
        margin-bottom: 30px;
        line-height: 1.6;
    }

    .error-icon {
        font-size: 60px;
        color: #ffc107;
        margin-bottom: 20px;
        animation: bounce 2s infinite;
    }

    @keyframes bounce {
        0%, 20%, 50%, 80%, 100% {
            transform: translateY(0);
        }
        40% {
            transform: translateY(-15px);
        }
        60% {
            transform: translateY(-8px);
        }
    }

    .error-buttons {
        display: flex;
        gap: 15px;
        justify-content: center;
        flex-wrap: wrap;
    }

    @media (max-width: 768px) {
        .error-code {
            font-size: 80px;
        }
        
        .error-title {
            font-size: 22px;
        }
        
        .error-content {
            margin: 20px;
            padding: 30px;
        }
    }
    </style>
</head>
<body>
    <div class="error-page">
        <div class="error-content">
            <div class="error-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            
            <div class="error-code">404</div>
            
            <h1 class="error-title">Страница не найдена</h1>
            
            <p class="error-text">
                Похоже, вы пытаетесь получить доступ к странице, которая не существует 
                или была удалена. Проверьте URL или вернитесь на главную панель управления.
            </p>
            
            <div class="error-buttons">
                <a href="index.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-tachometer-alt me-2"></i>Панель управления
                </a>
                <a href="products.php" class="btn btn-outline-success btn-lg">
                    <i class="fas fa-box me-2"></i>Товары
                </a>
                <a href="../index.php" class="btn btn-outline-info btn-lg">
                    <i class="fas fa-home me-2"></i>На сайт
                </a>
            </div>
            
            <div class="mt-4">
                <p class="text-muted small">
                    <i class="fas fa-key me-1"></i>
                    Если вы считаете, что это ошибка, обратитесь к администратору.
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
