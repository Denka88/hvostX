<?php
// /includes/footer.php
?>
</div>
        </main>

        <footer class="mt-5 py-4">
            <div class="footer-content">
                <div class="row">
                    <div class="col-lg-4 col-md-6 mb-4 mb-md-0">
                        <h5 class="mb-3">HvostX</h5>
                        <p>Ваш надежный поставщик товаров для домашних животных. Мы заботимся о ваших питомцах так же, как и вы!</p>
                    </div>

                    <div class="col-lg-2 col-md-3 col-6 mb-4 mb-md-0">
                        <h5 class="mb-3">Компания</h5>
                        <ul class="list-unstyled">
                            <li class="mb-2"><a href="about.php" class="text-decoration-none">О компании</a></li>
                            <li class="mb-2"><a href="news.php" class="text-decoration-none">Новости</a></li>
                            <li class="mb-2"><a href="production.php" class="text-decoration-none">О продукции</a></li>
                            <li class="mb-2"><a href="partners.php" class="text-decoration-none">Партнеры</a></li>
                            <li class="mb-2"><a href="contacts.php" class="text-decoration-none">Контакты</a></li>
                        </ul>
                    </div>

                    <div class="col-lg-2 col-md-3 col-6 mb-4 mb-md-0">
                        <h5 class="mb-3">Помощь</h5>
                        <ul class="list-unstyled">
                            <li class="mb-2"><a href="contacts.php" class="text-decoration-none">Связь с нами</a></li>
                        </ul>
                    </div>

                    <div class="col-lg-4 col-md-12">
                        <h5 class="mb-3">Контакты</h5>
                        <address>
                            <strong>HvostX</strong><br>
                            г. Белореченск, ул. Ленина, д. 54<br>
                            <i class="fas fa-phone me-2"></i> +7 (902) 758-00-03<br>
                            <i class="fas fa-envelope me-2"></i> info@hvostx.ru
                        </address>

                        <h5 class="mt-4 mb-3">Подписка на новости</h5>
                        <form>
                            <div class="input-group mb-3">
                                <input type="email" class="form-control" placeholder="Ваш email" aria-label="Email для подписки">
                                <button class="btn btn-primary" type="button">Подписаться</button>
                            </div>
                        </form>
                    </div>
                </div>

                <hr class="my-4">

                <div class="row">
                    <div class="col-md-6 text-center text-md-start">
                        <p class="mb-0">&copy; <?php echo date('Y'); ?> HvostX. Все права защищены.</p>
                    </div>
                    <div class="col-md-6 text-center text-md-end">
                        <p class="mb-0">
                            <a href="#" class="text-decoration-none me-3">Политика конфиденциальности</a>
                            <a href="#" class="text-decoration-none">Условия использования</a>
                        </p>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-12 text-center">
                        <p class="text-white-50 mb-0 small">
                            Разработчик: <a href="https://github.com/Denka88" target="_blank" rel="noopener noreferrer" class="text-decoration-none text-white-50">@Denka88</a>
                            <i class="fab fa-github ms-1"></i>
                        </p>
                    </div>
                </div>
            </div>
        </footer>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="assets/js/script.js"></script>
        <script src="assets/js/paw-animation.js?v=<?php echo time(); ?>"></script>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const favoritesBadge = document.querySelector('.favorites-count-badge');
            if (!favoritesBadge) return;
            
            fetch('api_favorites.php?action=count')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success' && data.total_favorites > 0) {
                        favoritesBadge.textContent = data.total_favorites;
                        favoritesBadge.classList.remove('d-none');
                    } else {
                        favoritesBadge.classList.add('d-none');
                    }
                })
                .catch(error => console.error('Ошибка обновления счетчика избранного:', error));
        });
        </script>
    </body>
</html>