<?php
// /news.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';

$count_query = "SELECT COUNT(*) as total FROM news WHERE is_active = 1";
$count_result = mysqli_query($connection, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];

$pagination = new Pagination($total_records, 9);
$offset = $pagination->getOffset();
$limit = $pagination->getLimit();

$query = "SELECT * FROM news WHERE is_active = 1 ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$result = mysqli_query($connection, $query);

$page_title = "Новости - HvostX";
?>

<?php include 'includes/header.php'; ?>

<style>
    .news-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .news-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }
    .news-content {
        line-height: 1.8;
        font-size: 1.05rem;
    }
</style>

<div class="main-container animate-fade-in">
    <main class="container my-5">
        <h1 class="mb-4 text-center">Новости</h1>

        <?php if (mysqli_num_rows($result) > 0): ?>
        <div class="row">
            <?php while ($news = mysqli_fetch_assoc($result)): ?>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card h-100 news-card">
                    <?php if (!empty($news['image'])): ?>
                    <img src="assets/images/news/<?php echo htmlspecialchars($news['image']); ?>"
                         class="card-img-top" alt="<?php echo htmlspecialchars($news['title']); ?>"
                         style="height: 200px; object-fit: cover;">
                    <?php else: ?>
                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center" 
                         style="height: 200px;">
                        <i class="fas fa-newspaper fa-3x text-muted"></i>
                    </div>
                    <?php endif; ?>
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title"><?php echo htmlspecialchars($news['title']); ?></h5>
                        <p class="card-text text-muted small">
                            <i class="far fa-calendar-alt me-1"></i>
                            <?php echo date('d.m.Y', strtotime($news['created_at'])); ?>
                        </p>
                        <p class="card-text flex-grow-1">
                            <?php echo htmlspecialchars(mb_substr($news['content'], 0, 150)); ?><?php echo mb_strlen($news['content']) > 150 ? '...' : ''; ?>
                        </p>
                        <button class="btn btn-primary mt-auto" 
                                data-bs-toggle="modal" 
                                data-bs-target="#newsModal<?php echo $news['id']; ?>">
                            <i class="fas fa-eye me-1"></i> Читать далее
                        </button>
                    </div>
                </div>
            </div>

            <!-- Модальное окно с полной новостью -->
            <div class="modal fade" id="newsModal<?php echo $news['id']; ?>" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><?php echo htmlspecialchars($news['title']); ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <?php if (!empty($news['image'])): ?>
                            <img src="assets/images/news/<?php echo htmlspecialchars($news['image']); ?>" 
                                 class="img-fluid rounded mb-3" alt="<?php echo htmlspecialchars($news['title']); ?>">
                            <?php endif; ?>
                            <p class="text-muted mb-3">
                                <i class="far fa-calendar-alt me-1"></i>
                                Опубликовано: <?php echo date('d.m.Y H:i', strtotime($news['created_at'])); ?>
                            </p>
                            <div class="news-content">
                                <?php echo nl2br(htmlspecialchars($news['content'])); ?>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-5">
            <i class="fas fa-newspaper fa-4x text-muted mb-3"></i>
            <h3>Новости пока отсутствуют</h3>
            <p class="text-muted">Заходите позже, чтобы быть в курсе последних событий!</p>
        </div>
        <?php endif; ?>
        
        <?php echo $pagination->render('news.php'); ?>
    </main>
</div>



<?php include 'includes/footer.php'; ?>
