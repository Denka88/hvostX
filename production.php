<?php
// /production.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>О продукции - HvostX</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="main-container animate-fade-in">
    <main class="container my-5">
        
        <div class="row mb-5">
            <div class="col-lg-6">
                <h1 class="mb-4">О нашей продукции</h1>
                <h2>Наше производство</h2>
                <p>Наше производство оснащено современным оборудованием и соответствует всем европейским стандартам качества.</p>

                <div class="mt-4">
                    <h3>Качество и безопасность</h3>
                    <p>Все наши продукты проходят многоуровневый контроль качества и имеют необходимые сертификаты.</p>
                </div>
            </div>
            <div class="col-lg-6">
                <img src="assets/images/production.jpg" class="img-fluid rounded" alt="Наше производство">
            </div>
        </div>

        <section class="mb-5">
            <h2 class="mb-4">Процесс производства</h2>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Отбор сырья</h5>
                            <p class="card-text">Тщательный отбор качественного сырья от проверенных поставщиков</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Производство</h5>
                            <p class="card-text">Современное производство с соблюдением всех стандартов</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Контроль качества</h5>
                            <p class="card-text">Многоуровневая система контроля качества продукции</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Упаковка</h5>
                            <p class="card-text">Экологичная упаковка, сохраняющая свежесть продукта</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section>
            <h2 class="mb-4">Наши сертификаты</h2>
            <div class="row">
                <div class="col-md-3 mb-4">
                    <div class="card">
                        <img src="assets/images/cert1.jpg" class="card-img-top" alt="Сертификат качества">
                        <div class="card-body">
                            <h5 class="card-title">Сертификат качества</h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card">
                        <img src="assets/images/cert2.jpg" class="card-img-top" alt="Экологический сертификат">
                        <div class="card-body">
                            <h5 class="card-title">Экологический сертификат</h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card">
                        <img src="assets/images/cert3.jpg" class="card-img-top" alt="Международный стандарт">
                        <div class="card-body">
                            <h5 class="card-title">Международный стандарт</h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card">
                        <img src="assets/images/cert4.jpg" class="card-img-top" alt="Безопасность продукции">
                        <div class="card-body">
                            <h5 class="card-title">Безопасность продукции</h5>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>