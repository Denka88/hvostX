<?php
// /contacts.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$contact = [
    'address' => 'г. Белореченск, ул. Ленина, д. 54',
    'phone' => '+7 (902) 758-00-03',
    'email' => 'info@hvostx.ru',
    'working_hours' => "\nПн-Пт: 9:00 - 19:00\n Сб-Вс: 10:00 - 18:00",
    'map_url' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2244.892191234567!2d39.865049!3d44.769006!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x40f82d0a8c8a8a8b%3A0x8a8a8a8a8a8a8a8a!2z0JHQvtC70YzQvtGB0LrQstCwINC60LjQu9C-0LLQs9C-0YHQutCw0Y8g0JHQvtC70YzQvtGB0LrQstCw0Y8gNTQ!5e0!3m2!1sru!2sru!4v1709000000000!5m2!1sru!2sru'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'includes/db.php';

    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $message = $_POST['message'] ?? '';

    require_once 'includes/recaptcha.php';
    $recaptcha_result = verifyRecaptcha($_POST['g-recaptcha-response'] ?? '');

    $errors = [];
    if (!$recaptcha_result['success']) {
        $errors[] = $recaptcha_result['message'];
    }
    if (empty($name)) $errors[] = 'Пожалуйста, укажите ваше имя';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Пожалуйста, укажите корректный email';
    if (empty($message)) $errors[] = 'Пожалуйста, введите ваше сообщение';

    if (empty($errors)) {
        $stmt = $connection->prepare("INSERT INTO contact_messages (name, email, phone, message, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssss", $name, $email, $phone, $message);
        $stmt->execute();

        $success = "Ваше сообщение успешно отправлено! Мы свяжемся с вами в ближайшее время.";
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Контакты - HvostX</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="main-container animate-fade-in">
        <main class="container my-5">
        <h1 class="mb-4">Контакты</h1>

        <div class="row">
            <div class="col-lg-6 mb-5">
                <h2>Свяжитесь с нами</h2>
                <p>У вас есть вопросы или предложения? Напишите нам, и мы ответим в ближайшее время.</p>

                <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                    <p><?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="contacts.php">
                    <div class="mb-3">
                        <label for="name" class="form-label">Ваше имя *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Телефон</label>
                        <input type="tel" class="form-control" id="phone" name="phone">
                    </div>
                    <div class="mb-3">
                        <label for="message" class="form-label">Ваше сообщение *</label>
                        <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="g-recaptcha" data-sitekey="6Ld4XXssAAAAABPizR6w4L3c5sx_okDAVXdP4YaD"></div>
                    </div>
                    <button type="submit" class="btn btn-success">Отправить сообщение</button>
                </form>
            </div>

            <div class="col-lg-6">
                <h2>Наши контакты</h2>

                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Контактная информация</h5>
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="fas fa-map-marker-alt me-2 text-success"></i>
                                <strong>Адрес:</strong> <?php echo htmlspecialchars($contact['address']); ?>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-phone me-2 text-success"></i>
                                <strong>Телефон:</strong> <a href="tel:<?php echo preg_replace('/[^0-9+]/', '', $contact['phone']); ?>"><?php echo htmlspecialchars($contact['phone']); ?></a>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-envelope me-2 text-success"></i>
                                <strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($contact['email']); ?>"><?php echo htmlspecialchars($contact['email']); ?></a>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-clock me-2 text-success"></i>
                                <strong>Режим работы:</strong>
                                <span class="ms-4"><?php echo nl2br(htmlspecialchars($contact['working_hours'])); ?></span>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Мы на карте</h5>
                        <div class="contact-map-container">
                            <iframe src="<?php echo htmlspecialchars($contact['map_url']); ?>"
                                    width="100%" height="350" style="border:0;"
                                    allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

    <?php include 'includes/footer.php'; ?>

    <script>
    const phoneInput = document.getElementById('phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 0) {
                if (value[0] === '7' || value[0] === '8') {
                    value = value.substring(1);
                }
                let formatted = '+7';
                if (value.length > 0) formatted += ' (' + value.substring(0, 3);
                if (value.length > 3) formatted += ') ' + value.substring(3, 6);
                if (value.length > 6) formatted += '-' + value.substring(6, 8);
                if (value.length > 8) formatted += '-' + value.substring(8, 10);
                e.target.value = formatted;
            }
        });
    }
    </script>
</body>
</html>