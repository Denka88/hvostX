<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hvostx";

$connection = mysqli_connect($servername, $username, $password, $dbname);

if (!$connection) {
    die("Connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($connection, "utf8mb4");

if (!class_exists('Pagination')) {
    class Pagination {
        private $total;
        private $page;
        private $perPage;
        private $totalPages;

        public function __construct($total, $perPage = 12) {
            $this->total = (int)$total;
            $this->perPage = (int)$perPage;
            $this->page = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
            $this->totalPages = ceil($this->total / $this->perPage);
        }

        public function getOffset() {
            return ($this->page - 1) * $this->perPage;
        }

        public function getLimit() {
            return $this->perPage;
        }

        public function getPage() {
            return $this->page;
        }

        public function getTotalPages() {
            return $this->totalPages;
        }

        public function getTotal() {
            return $this->total;
        }

        public function hasPages() {
            return $this->totalPages > 1;
        }

        public function render($baseUrl = '') {
            if (!$this->hasPages()) {
                return '';
            }

            $html = '<nav aria-label="Пагинация" class="mt-5">';
            $html .= '<ul class="pagination pagination-custom justify-content-center">';

            $prevPage = $this->page - 1;
            if ($prevPage >= 1) {
                $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . $prevPage . '"><i class="fas fa-chevron-left"></i></a></li>';
            }

            $startPage = max(1, $this->page - 2);
            $endPage = min($this->totalPages, $this->page + 2);

            for ($i = $startPage; $i <= $endPage; $i++) {
                $active = $i == $this->page ? 'active' : '';
                $html .= '<li class="page-item ' . $active . '"><a class="page-link" href="' . $baseUrl . '?page=' . $i . '">' . $i . '</a></li>';
            }

            $nextPage = $this->page + 1;
            if ($nextPage <= $this->totalPages) {
                $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . $nextPage . '"><i class="fas fa-chevron-right"></i></a></li>';
            }

            $html .= '</ul>';
            $html .= '<p class="text-center text-muted small">Страница ' . $this->page . ' из ' . $this->totalPages . '</p>';
            $html .= '</nav>';

            return $html;
        }
    }
}
?>
