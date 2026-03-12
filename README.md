# HvostX — Интернет-магазин товаров для домашних животных

## 🚀 Требования

- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.4+
- Apache
- Расширения PHP: `mysqli`, `session`, `gd`

## ⚙️ Установка

1. **База данных**
   ```sql
   CREATE DATABASE hvostx CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
   Импортируйте `assets/hvostx.sql`

2. **Настройка БД** (`includes/db.php`)
   ```php
   $servername = "localhost";
   $username = "root";
   $password = "";
   $dbname = "hvostx";
   ```

3. **reCAPTCHA** (`includes/recaptcha.php`)
   ```php
   define('RECAPTCHA_SITE_KEY', 'ваш_ключ');
   define('RECAPTCHA_SECRET_KEY', 'ваш_секретный_ключ');
   ```

## 👤 Доступы

### Админ-панель (`/admin/`)
- Логин: `admin`
- Пароль: `admin`

> ⚠️ Смените пароль после первого входа!

## 📄 Основные страницы

| Страница | URL |
|----------|-----|
| Главная | `/index.php` |
| Каталог | `/products.php` |
| Категории | `/categories.php` |
| Товар | `/product_single.php?id=` |
| Корзина | `/cart.php` |
| Оформление | `/checkout.php` |
| Профиль | `/profile.php` |
| Вход/Регистрация | `/account.php` |
| Новости | `/news.php` |
| О компании | `/about.php` |
| О продукции | `/production.php` |
| Партнёры | `/partners.php` |
| Контакты | `/contacts.php` |

## 🛠️ Админ-панель

| Раздел | Файл |
|--------|------|
| Дашборд | `/admin/index.php` |
| Товары | `/admin/products.php` |
| Заказы | `/admin/orders.php` |
| Пользователи | `/admin/users.php` |
| Новости | `/admin/news.php` |
| Отзывы | `/admin/reviews.php` |
| Сообщения | `/admin/messages.php` |
| Фильтры | `/admin/filter.php` |

## 📁 Структура

```
hvostx/
├── admin/              # Админ-панель
├── assets/
│   ├── hvostx.sql     # Дамп БД
│   └── images/        # Изображения
├── includes/
│   ├── db.php         # Подключение к БД
│   ├── header.php     # Шапка
│   ├── footer.php     # Подвал
│   └── recaptcha.php  # reCAPTCHA
├── index.php
├── products.php
├── categories.php
├── cart.php
├── checkout.php
├── profile.php
├── account.php
└── ...
```

## 🔧 SEO

На всех страницах прописаны meta description. По умолчанию:
```
HvostX - интернет-магазин товаров для домашних животных. Корма, игрушки, аксессуары и всё необходимое для ваших питомцев.
```

Для `product_single.php` description генерируется из описания товара.

## 🤖 robots.txt

Запрещены к индексации:
- `/admin/`, `/includes/`, `/assets/`
- Личный кабинет, корзина, заказы
- API-обработчики
- Страницы с параметрами (избежание дублей)
