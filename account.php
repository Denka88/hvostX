<?php
// /account.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: account.php");
    exit;
}

if (isset($_SESSION['user_id'])) {
    header("Location: profile.php");
    exit;
}

if (isset($_POST['submit_login'])) {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    $_SESSION['login_input'] = $login;

    $query = "SELECT * FROM users WHERE login = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("s", $login);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_login'] = $user['login'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_avatar'] = $user['avatar'];
        unset($_SESSION['login_input']);

        if (isset($_SESSION['redirect_after_login'])) {
            $redirect = $_SESSION['redirect_after_login'];
            unset($_SESSION['redirect_after_login']);
            header("Location: " . $redirect);
        } else {
            header("Location: profile.php");
        }
        exit;
    } else {
        $error = "Неверный логин или пароль";
    }
}

if (isset($_POST['submit_register'])) {
    $login = trim($_POST['login'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    require_once 'includes/recaptcha.php';
    $recaptcha_result = verifyRecaptcha($_POST['g-recaptcha-response'] ?? '');

    $errors = [];
    if (!$recaptcha_result['success']) {
        $errors[] = $recaptcha_result['message'];
    }
    if (empty($login) || strlen($login) < 3) $errors[] = 'Логин должен содержать не менее 3 символов';
    if (empty($name)) $errors[] = 'Пожалуйста, укажите ваше имя';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Пожалуйста, укажите корректный email';
    if (empty($password)) $errors[] = 'Пожалуйста, укажите пароль';
    if (strlen($password) < 6) $errors[] = 'Пароль должен содержать не менее 6 символов';
    if ($password !== $confirm_password) $errors[] = 'Пароли не совпадают';

    $query = "SELECT id FROM users WHERE login = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("s", $login);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        $errors[] = 'Пользователь с таким логином уже существует';
    }

    $query = "SELECT id FROM users WHERE email = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        $errors[] = 'Пользователь с таким email уже существует';
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $query = "INSERT INTO users (login, name, email, password, role, created_at) VALUES (?, ?, ?, ?, 'user', NOW())";
        $stmt = $connection->prepare($query);
        $stmt->bind_param("ssss", $login, $name, $email, $hashed_password);
        $stmt->execute();

        $query = "SELECT * FROM users WHERE email = ?";
        $stmt = $connection->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $new_user = $stmt->get_result()->fetch_assoc();

        if ($new_user) {
            $_SESSION['user_id'] = $new_user['id'];
            $_SESSION['user_name'] = $new_user['name'];
            $_SESSION['user_login'] = $new_user['login'];
            $_SESSION['user_email'] = $new_user['email'];
            $_SESSION['user_role'] = $new_user['role'];
            $_SESSION['user_avatar'] = $new_user['avatar'];

            if (isset($_SESSION['redirect_after_login'])) {
                $redirect = $_SESSION['redirect_after_login'];
                unset($_SESSION['redirect_after_login']);
                header("Location: " . $redirect);
            } else {
                header("Location: profile.php");
            }
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет - HvostX</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="main-container animate-fade-in">
        <main class="container my-5">
            <h1 class="mb-4 text-center">Личный кабинет</h1>

            <?php if (!empty($error)): ?>
            <div class="alert alert-danger text-center"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
            <div class="alert alert-success text-center"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="row justify-content-center">
                <div class="col-lg-5 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white">
                            <h4 class="mb-0"><i class="bi bi-box-arrow-in-right me-2"></i>Вход</h4>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="account.php">
                                <div class="mb-3">
                                    <label for="login-login" class="form-label">Логин</label>
                                    <input type="text" class="form-control" id="login-login" name="login" required
                                           value="<?php echo isset($_SESSION['login_input']) ? htmlspecialchars($_SESSION['login_input']) : ''; ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="login-password" class="form-label">Пароль</label>
                                    <input type="password" class="form-control" id="login-password" name="password" required>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" name="submit_login" class="btn btn-success btn-lg">
                                        <i class="bi bi-box-arrow-in-right me-2"></i>Войти
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white">
                            <h4 class="mb-0"><i class="bi bi-person-plus me-2"></i>Регистрация</h4>
                        </div>
                        <div class="card-body">
                            <?php if (isset($errors) && !empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>

                            <form method="POST" action="account.php">
                                <div class="mb-3">
                                    <label for="register-login" class="form-label">Логин *</label>
                                    <input type="text" class="form-control" id="register-login" name="login" required
                                           value="<?php echo isset($_POST['login']) ? htmlspecialchars($_POST['login']) : ''; ?>">
                                    <div class="form-text">Минимум 3 символа</div>
                                </div>
                                <div class="mb-3">
                                    <label for="register-name" class="form-label">Ваше имя *</label>
                                    <input type="text" class="form-control" id="register-name" name="name" required
                                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="register-email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="register-email" name="email" required
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="register-password" class="form-label">Пароль *</label>
                                    <input type="password" class="form-control" id="register-password" name="password" required>
                                    <div class="form-text">Минимум 6 символов</div>
                                </div>
                                <div class="mb-3">
                                    <label for="register-confirm-password" class="form-label">Подтвердите пароль *</label>
                                    <input type="password" class="form-control" id="register-confirm-password" name="confirm_password" required>
                                </div>
                                <div class="mb-3">
                                    <div class="g-recaptcha" data-sitekey="6Ld4XXssAAAAABPizR6w4L3c5sx_okDAVXdP4YaD"></div>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" name="submit_register" class="btn btn-success btn-lg">
                                        <i class="bi bi-person-plus me-2"></i>Зарегистрироваться
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
