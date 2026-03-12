<?php
// /about.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';

$page_title = "О компании - HvostX";
$meta_description = "О компании HvostX — ведущий поставщик товаров для домашних животных в России. Наша миссия, ценности и команда профессионалов.";
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>О компании - HvostX</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="main-container animate-fade-in">
        <main class="container my-5">
        <h1 class="mb-4">О компании HvostX</h1>

        <div class="row">
            <div class="col-lg-8">
                <div class="mb-4">
                    <p>HvostX(Хвостикс) - ведущий поставщик товаров для домашних животных на российском рынке. Мы работаем с 2010 года и за это время завоевали доверие тысяч клиентов.</p>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <h3>Наша миссия</h3>
                        <p>Наша миссия - сделать жизнь домашних животных счастливее и здоровье, предоставляя качественные товары по доступным ценам.</p>
                    </div>
                    <div class="col-md-6">
                        <h3>Наши ценности</h3>
                        <p>Мы ценим качество, честность, заботу о животных и клиентоориентированность.</p>
                    </div>
                </div>

                <div class="mb-4">
                    <h3>Наша команда</h3>
                    <p>Наша команда состоит из опытных зоологов, ветеринаров и специалистов по уходу за животными.</p>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Контактная информация</h5>
                        <p class="card-text">
                            <strong>Адрес:</strong> г. Белореченск, ул. Ленина, д. 54<br>
                            <strong>Телефон:</strong> +7 (902) 758-00-03<br>
                            <strong>Email:</strong> info@hvostx.ru
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>