<?php
// /partners.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';

$query = "SELECT * FROM partners WHERE is_active = 1 ORDER BY partner_order";
$result = mysqli_query($connection, $query);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Наши партнеры - HvostX</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="main-container animate-fade-in">
        <main class="container my-5">
        <h1 class="mb-4">Наши партнеры</h1>

        <div class="mb-4">
            <p>Мы гордимся нашими партнерскими отношениями, которые помогают нам предоставлять лучшие продукты и услуги нашим клиентам.</p>
        </div>

        <div class="row">
            <?php while ($partner = mysqli_fetch_assoc($result)): ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100 partner-page-card">
                    <div class="card-body text-center d-flex flex-column align-items-center justify-content-between">
                        <img src="assets/images/partners/<?php echo htmlspecialchars($partner['logo']); ?>"
                             alt="<?php echo htmlspecialchars($partner['name']); ?>"
                             class="img-fluid mb-3 partner-logo" style="max-height: 100px;">
                        <h5 class="card-title"><?php echo htmlspecialchars($partner['name']); ?></h5>
                        <p class="card-text flex-grow-1"><?php echo htmlspecialchars($partner['description']); ?></p>
                        <?php if (!empty($partner['website'])): ?>
                        <a href="<?php echo htmlspecialchars($partner['website']); ?>" class="btn btn-outline-primary mt-auto" target="_blank">Перейти на сайт</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <section class="mt-5">
            <div class="card">
                <div class="card-body">
                    <h3 class="card-title">Хотите стать нашим партнером?</h3>
                    <p class="card-text">Если вы заинтересованы в сотрудничестве с нашей компанией, пожалуйста, свяжитесь с нами через форму обратной связи или по телефону.</p>
                    <a href="contacts.php" class="btn btn-success">Связаться с нами</a>
                </div>
            </div>
        </section>
    </main>
</div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>