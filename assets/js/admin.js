document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    document.querySelectorAll('.btn-delete').forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Вы уверены, что хотите удалить этот элемент?')) {
                e.preventDefault();
            }
        });
    });

    document.querySelectorAll('.needs-validation').forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
});

Chart.defaults.font.family = "'Nunito', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'Helvetica Neue', 'Arial', 'sans-serif'";
Chart.defaults.font.size = 12;
Chart.defaults.font.weight = '400';

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        const toast = new bootstrap.Toast(document.getElementById('copyToast'));
        toast.show();
    }, function(err) {
        console.error('Could not copy text: ', err);
    });
}

function updateStatus(element, id, status, table) {
    fetch('ajax/update_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=${id}&status=${status}&table=${table}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            element.closest('tr').querySelector('.status-badge').textContent = status;
            element.closest('tr').querySelector('.status-badge').className = `badge bg-${getStatusColor(status)}`;
        } else {
            alert('Ошибка при обновлении статуса: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

function getStatusColor(status) {
    const colors = {
        'active': 'success',
        'pending': 'warning',
        'completed': 'success',
        'cancelled': 'danger',
        'inactive': 'secondary'
    };
    return colors[status] || 'primary';
}

function loadData(url, targetElement, callback) {
    fetch(url)
    .then(response => response.text())
    .then(html => {
        document.getElementById(targetElement).innerHTML = html;
        if (typeof callback === 'function') {
            callback();
        }
    })
    .catch(error => {
        console.error('Error loading data:', error);
    });
}

function showLoading(element) {
    const spinner = document.createElement('div');
    spinner.className = 'spinner-border spinner-border-sm text-primary';
    spinner.role = 'status';
    spinner.innerHTML = '<span class="visually-hidden">Loading...</span>';

    element.disabled = true;
    element.innerHTML = '';
    element.appendChild(spinner);
    element.appendChild(document.createTextNode(' Загрузка...'));
}

function hideLoading(element, originalText) {
    element.disabled = false;
    element.innerHTML = originalText;
}

function initDataTables() {
    if (typeof $.fn.DataTable === 'function') {
        $('.data-table').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/ru.json'
            },
            responsive: true,
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>t<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            lengthMenu: [10, 25, 50, 100],
            pageLength: 25
        });
    }
}

function exportToExcel(tableId, filename = 'export') {
    const table = document.getElementById(tableId);
    const wb = XLSX.utils.table_to_book(table);
    XLSX.writeFile(wb, filename + '.xlsx');
}

function exportToCSV(tableId, filename = 'export') {
    const table = document.getElementById(tableId);
    const csv = [];
    const rows = table.querySelectorAll('tr');

    for (let i = 0; i < rows.length; i++) {
        const row = [];
        const cols = rows[i].querySelectorAll('td, th');

        for (let j = 0; j < cols.length; j++) {
            row.push(cols[j].innerText);
        }

        csv.push(row.join(','));
    }

    downloadCSV(csv.join('\n'), filename);
}

function downloadCSV(csv, filename) {
    const csvFile = new Blob([csv], {type: 'text/csv'});
    const downloadLink = document.createElement('a');

    downloadLink.download = filename + '.csv';
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';

    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

function previewImage(input, previewElementId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();

        reader.onload = function(e) {
            document.getElementById(previewElementId).src = e.target.result;
            document.getElementById(previewElementId).style.display = 'block';
        }

        reader.readAsDataURL(input.files[0]);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    if (typeof MutationObserver !== 'undefined') {
        const observer = new MutationObserver(function(mutations) {
            initDataTables();
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
});