<?php
class TableFilter {
    private $connection;
    private $table;
    private $alias = '';
    private $fields = [];
    private $dateRanges = [];
    private $values = [];

    public function __construct($connection, $table, $alias = '') {
        $this->connection = $connection;
        $this->table = $table;
        $this->alias = $alias;

        if (isset($_SESSION['filter_' . $table])) {
            $this->values = $_SESSION['filter_' . $table];
        }
    }

    public function addField($name, $type = 'text', $label = '', $options = []) {
        $this->fields[$name] = [
            'name' => $name,
            'type' => $type,
            'label' => $label ?: ucfirst($name),
            'options' => $options
        ];
        return $this;
    }

    public function addDateRange($field, $label = '') {
        $this->dateRanges[$field] = [
            'field' => $field,
            'label' => $label ?: ucfirst($field)
        ];
        return $this;
    }

    public function saveValues($data) {
        $this->values = [];

        foreach ($this->fields as $name => $field) {
            if (isset($data[$name]) && $data[$name] !== '') {
                $this->values[$name] = trim($data[$name]);
            }
        }

        foreach ($this->dateRanges as $name => $range) {
            $fromKey = $name . '_from';
            $toKey = $name . '_to';

            if (isset($data[$fromKey]) && $data[$fromKey] !== '') {
                $this->values[$fromKey] = $data[$fromKey];
            }
            if (isset($data[$toKey]) && $data[$toKey] !== '') {
                $this->values[$toKey] = $data[$toKey];
            }
        }

        $_SESSION['filter_' . $this->table] = $this->values;
    }

    public function clear() {
        unset($_SESSION['filter_' . $this->table]);
        $this->values = [];
    }

    public function getSQL() {
        $conditions = [];
        $params = [];
        $types = '';

        $prefix = !empty($this->alias) ? $this->alias . '.' : '';

        foreach ($this->fields as $name => $field) {
            if (isset($this->values[$name]) && $this->values[$name] !== '') {
                $value = $this->values[$name];

                if ($field['type'] === 'text') {
                    $conditions[] = "{$prefix}{$name} LIKE ?";
                    $params[] = "%$value%";
                    $types .= 's';
                } elseif ($field['type'] === 'select' || $field['type'] === 'number') {
                    $conditions[] = "{$prefix}{$name} = ?";
                    $params[] = $value;
                    $types .= is_numeric($value) ? 'i' : 's';
                }
            }
        }

        foreach ($this->dateRanges as $name => $range) {
            $fromKey = $name . '_from';
            $toKey = $name . '_to';

            if (isset($this->values[$fromKey]) && $this->values[$fromKey] !== '') {
                $conditions[] = "{$prefix}{$name} >= ?";
                $params[] = $this->values[$fromKey] . ' 00:00:00';
                $types .= 's';
            }

            if (isset($this->values[$toKey]) && $this->values[$toKey] !== '') {
                $conditions[] = "{$prefix}{$name} <= ?";
                $params[] = $this->values[$toKey] . ' 23:59:59';
                $types .= 's';
            }
        }

        $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return [$where, $params, $types];
    }

    public function render() {
        if (empty($this->fields) && empty($this->dateRanges)) {
            return '';
        }

        $hasValues = !empty($this->values);

        ob_start();
        ?>
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-filter me-1"></i>
                Фильтр
                <?php if ($hasValues): ?>
                <a href="?clear_filter=1" class="btn btn-sm btn-outline-danger ms-2">
                    <i class="fas fa-times"></i> Сбросить
                </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <?php foreach ($this->fields as $name => $field): ?>
                    <div class="col-md-3">
                        <label for="filter_<?php echo $name; ?>" class="form-label">
                            <?php echo htmlspecialchars($field['label']); ?>
                        </label>
                        <?php if ($field['type'] === 'select'): ?>
                        <select class="form-select" id="filter_<?php echo $name; ?>" name="<?php echo $name; ?>">
                            <option value="">Все</option>
                            <?php foreach ($field['options'] as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>"
                                    <?php echo isset($this->values[$name]) && $this->values[$name] == $value ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <input type="<?php echo $field['type']; ?>"
                               class="form-control"
                               id="filter_<?php echo $name; ?>"
                               name="<?php echo $name; ?>"
                               value="<?php echo isset($this->values[$name]) ? htmlspecialchars($this->values[$name]) : ''; ?>">
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>

                    <?php foreach ($this->dateRanges as $name => $range): ?>
                    <div class="col-md-4">
                        <label class="form-label"><?php echo htmlspecialchars($range['label']); ?></label>
                        <div class="input-group">
                            <input type="date"
                                   class="form-control"
                                   name="<?php echo $name; ?>_from"
                                   value="<?php echo isset($this->values[$name . '_from']) ? htmlspecialchars($this->values[$name . '_from']) : ''; ?>">
                            <span class="input-group-text">—</span>
                            <input type="date"
                                   class="form-control"
                                   name="<?php echo $name; ?>_to"
                                   value="<?php echo isset($this->values[$name . '_to']) ? htmlspecialchars($this->values[$name . '_to']) : ''; ?>">
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i> Найти
                        </button>
                        <?php if ($hasValues): ?>
                        <a href="?clear_filter=1" class="btn btn-outline-secondary">
                            <i class="fas fa-undo me-1"></i> Сбросить
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function getActiveFilters() {
        $active = [];

        foreach ($this->fields as $name => $field) {
            if (isset($this->values[$name]) && $this->values[$name] !== '') {
                $active[$name] = [
                    'label' => $field['label'],
                    'value' => $this->values[$name]
                ];
            }
        }

        foreach ($this->dateRanges as $name => $range) {
            $fromKey = $name . '_from';
            $toKey = $name . '_to';

            $from = isset($this->values[$fromKey]) ? $this->values[$fromKey] : null;
            $to = isset($this->values[$toKey]) ? $this->values[$toKey] : null;

            if ($from || $to) {
                $active[$name] = [
                    'label' => $range['label'],
                    'value' => ($from ?? 'начало') . ' — ' . ($to ?? 'конец'),
                    'from' => $from,
                    'to' => $to
                ];
            }
        }

        return $active;
    }
}

if (!class_exists('Pagination')) {
    class Pagination {
        private $total;
        private $page;
        private $perPage;
        private $totalPages;

        public function __construct($total, $perPage = 20) {
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

    public function render() {
        if (!$this->hasPages()) {
            return '';
        }

        $html = '<nav aria-label="Пагинация" class="mt-3">';
        $html .= '<style>
            .pagination .page-link i {
                font-size: 0.875rem;
                line-height: 1;
                vertical-align: middle;
            }
            .pagination .page-link {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 2.5rem;
            }
        </style>';
        $html .= '<ul class="pagination justify-content-center">';

        if ($this->page > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="?page=1"><i class="fas fa-angle-double-left"></i></a></li>';
        }

        $prevPage = $this->page - 1;
        if ($prevPage >= 1) {
            $html .= '<li class="page-item"><a class="page-link" href="?page=' . $prevPage . '"><i class="fas fa-angle-left"></i></a></li>';
        }

        $startPage = max(1, $this->page - 2);
        $endPage = min($this->totalPages, $this->page + 2);

        for ($i = $startPage; $i <= $endPage; $i++) {
            $active = $i == $this->page ? 'active' : '';
            $html .= '<li class="page-item ' . $active . '"><a class="page-link" href="?page=' . $i . '">' . $i . '</a></li>';
        }

        $nextPage = $this->page + 1;
        if ($nextPage <= $this->totalPages) {
            $html .= '<li class="page-item"><a class="page-link" href="?page=' . $nextPage . '"><i class="fas fa-angle-right"></i></a></li>';
        }

        if ($this->page < $this->totalPages) {
            $html .= '<li class="page-item"><a class="page-link" href="?page=' . $this->totalPages . '"><i class="fas fa-angle-double-right"></i></a></li>';
        }

        $html .= '</ul>';
        $html .= '<p class="text-center text-muted small">Показано ' . (($this->page - 1) * $this->perPage + 1) . ' - ' . min($this->page * $this->perPage, $this->total) . ' из ' . $this->total . ' записей</p>';
        $html .= '</nav>';

        return $html;
    }
    }
}
?>
