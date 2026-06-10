<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$page_title = 'Manage Reports - Yogyakarta City Complaint Register';
$categories = ['Infrastructure', 'Cleanliness', 'Security', 'Public Service', 'Environment'];
$statuses = ['Pending', 'In Progress', 'Resolved', 'Rejected'];
$filter_keys = ['search', 'category', 'status', 'date_from', 'date_to'];
$filters = [];
$has_filter_input = false;

if (isset($_GET['reset'])) {
    unset($_SESSION['admin_report_filters']);
}

foreach ($filter_keys as $key) {
    if (isset($_GET[$key])) {
        $has_filter_input = true;
        $filters[$key] = trim($_GET[$key]);
    }
}

if ($has_filter_input) {
    $_SESSION['admin_report_filters'] = $filters;
} elseif (isset($_SESSION['admin_report_filters'])) {
    $filters = $_SESSION['admin_report_filters'];
}

$search = trim($filters['search'] ?? '');
$category_filter = trim($filters['category'] ?? '');
$status_filter = trim($filters['status'] ?? '');
$date_from = trim($filters['date_from'] ?? '');
$date_to = trim($filters['date_to'] ?? '');
$reports = [];
$admins = [];
$error_message = '';
$bulk_error = '';
$where_parts = [];
$params = [];
$types = '';
$per_page = 10;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;
$total_reports = 0;
$total_pages = 1;

$admins_result = mysqli_query($conn, "SELECT id, name, email FROM users WHERE role = 'admin' ORDER BY name");

if ($admins_result) {
    while ($row = mysqli_fetch_assoc($admins_result)) {
        $admins[] = $row;
    }
}

$sql = "SELECT r.id, r.title, r.category, r.status, r.image, r.created_at, r.due_date,
        u.name AS user_name, a.name AS assigned_name
    FROM reports r
    INNER JOIN users u ON r.user_id = u.id
    LEFT JOIN users a ON r.assigned_to = a.id";

if ($search !== '') {
    $where_parts[] = '(r.title LIKE ? OR r.category LIKE ? OR u.name LIKE ?)';
    $search_value = '%' . $search . '%';
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $types .= 'sss';
}

if ($category_filter !== '' && in_array($category_filter, $categories, true)) {
    $where_parts[] = 'r.category = ?';
    $params[] = $category_filter;
    $types .= 's';
}

if ($status_filter !== '' && in_array($status_filter, $statuses, true)) {
    $where_parts[] = 'r.status = ?';
    $params[] = $status_filter;
    $types .= 's';
}

if ($date_from !== '' && $date_to !== '') {
    $where_parts[] = 'DATE(r.created_at) BETWEEN ? AND ?';
    $params[] = $date_from;
    $params[] = $date_to;
    $types .= 'ss';
} elseif ($date_from !== '') {
    $where_parts[] = 'DATE(r.created_at) >= ?';
    $params[] = $date_from;
    $types .= 's';
} elseif ($date_to !== '') {
    $where_parts[] = 'DATE(r.created_at) <= ?';
    $params[] = $date_to;
    $types .= 's';
}

if (count($where_parts) > 0) {
    $sql .= ' WHERE ' . implode(' AND ', $where_parts);
}

$count_sql = 'SELECT COUNT(*) AS total_count FROM reports r INNER JOIN users u ON r.user_id = u.id';
if (count($where_parts) > 0) {
    $count_sql .= ' WHERE ' . implode(' AND ', $where_parts);
}
$count_stmt = mysqli_prepare($conn, $count_sql);

if ($count_stmt) {
    if (count($params) > 0) {
        $count_bind = [$types];
        foreach ($params as $index => $value) {
            $count_bind[] = &$params[$index];
        }
        call_user_func_array([$count_stmt, 'bind_param'], $count_bind);
    }

    mysqli_stmt_execute($count_stmt);
    $count_result = mysqli_stmt_get_result($count_stmt);
    $count_row = mysqli_fetch_assoc($count_result);
    $total_reports = (int) ($count_row['total_count'] ?? 0);
    $total_pages = max(1, (int) ceil($total_reports / $per_page));
    mysqli_stmt_close($count_stmt);
}

$sql .= ' ORDER BY r.created_at DESC LIMIT ? OFFSET ?';
$stmt = mysqli_prepare($conn, $sql);

if ($stmt) {
    $bind_params = [$types . 'ii'];

    foreach ($params as $index => $value) {
        $bind_params[] = &$params[$index];
    }

    $bind_params[] = &$per_page;
    $bind_params[] = &$offset;

    call_user_func_array([$stmt, 'bind_param'], $bind_params);

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $reports[] = $row;
    }

    mysqli_stmt_close($stmt);
} else {
    error_log('Admin manage reports query prepare failed: ' . mysqli_error($conn));
    $error_message = 'Unable to load reports right now. Please try again later.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $bulk_action = $_POST['bulk_action'] ?? '';
    $selected_reports = $_POST['selected_reports'] ?? [];

    if (!is_array($selected_reports) || count($selected_reports) === 0) {
        $bulk_error = 'Please select at least one report.';
    } elseif ($bulk_action === 'status') {
        $bulk_status = $_POST['bulk_status'] ?? '';
        if (!in_array($bulk_status, $statuses, true)) {
            $bulk_error = 'Please select a valid status.';
        } else {
            foreach ($selected_reports as $report_id) {
                $report_id = (int) $report_id;
                $detail_stmt = mysqli_prepare($conn, 'SELECT user_id, title, status FROM reports WHERE id = ?');
                if ($detail_stmt) {
                    mysqli_stmt_bind_param($detail_stmt, 'i', $report_id);
                    mysqli_stmt_execute($detail_stmt);
                    $detail_result = mysqli_stmt_get_result($detail_stmt);
                    $detail = mysqli_fetch_assoc($detail_result);
                    mysqli_stmt_close($detail_stmt);
                } else {
                    $detail = null;
                }

                $update_stmt = mysqli_prepare($conn, 'UPDATE reports SET status = ?, updated_at = NOW() WHERE id = ?');
                if ($update_stmt) {
                    mysqli_stmt_bind_param($update_stmt, 'si', $bulk_status, $report_id);
                    mysqli_stmt_execute($update_stmt);
                    mysqli_stmt_close($update_stmt);
                }

                if ($detail && $detail['status'] !== $bulk_status) {
                    add_status_history($conn, $report_id, $bulk_status, 'Status updated in bulk.');
                    add_notification($conn, (int) $detail['user_id'], 'Report status updated', 'Your report "' . $detail['title'] . '" is now ' . $bulk_status . '.');
                }
            }

            log_admin_action($conn, 'bulk_status', 'Bulk status update to ' . $bulk_status . '.', null);
            set_flash_message('success', 'Bulk status update completed.');
            redirect('/complaint-system/admin/manage_reports.php');
        }
    } elseif ($bulk_action === 'assign') {
        $bulk_assigned = $_POST['bulk_assigned_to'] ?? '';
        $assigned_value = $bulk_assigned === '' ? null : (int) $bulk_assigned;

        foreach ($selected_reports as $report_id) {
            $report_id = (int) $report_id;
            $assign_stmt = mysqli_prepare($conn, 'UPDATE reports SET assigned_to = ?, updated_at = NOW() WHERE id = ?');
            if ($assign_stmt) {
                mysqli_stmt_bind_param($assign_stmt, 'ii', $assigned_value, $report_id);
                mysqli_stmt_execute($assign_stmt);
                mysqli_stmt_close($assign_stmt);
            }
        }

        log_admin_action($conn, 'bulk_assign', 'Bulk assignment updated.', null);
        set_flash_message('success', 'Bulk assignment updated.');
        redirect('/complaint-system/admin/manage_reports.php');
    } else {
        $bulk_error = 'Please select a bulk action.';
    }
}

if (isset($_GET['export']) && $_GET['export'] === '1') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="reports_export.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Title', 'Category', 'Status', 'User', 'Assigned To', 'Due Date', 'Created At']);
    $export_sql = $sql;
    $export_sql = str_replace(' LIMIT ? OFFSET ?', '', $export_sql);
    $export_stmt = mysqli_prepare($conn, $export_sql);
    if ($export_stmt) {
        if (count($params) > 0) {
            $export_bind = [$types];
            foreach ($params as $index => $value) {
                $export_bind[] = &$params[$index];
            }
            call_user_func_array([$export_stmt, 'bind_param'], $export_bind);
        }
        mysqli_stmt_execute($export_stmt);
        $export_result = mysqli_stmt_get_result($export_stmt);
        while ($row = mysqli_fetch_assoc($export_result)) {
            fputcsv($output, [
                $row['id'],
                $row['title'],
                $row['category'],
                $row['status'],
                $row['user_name'],
                $row['assigned_name'],
                $row['due_date'],
                $row['created_at']
            ]);
        }
        mysqli_stmt_close($export_stmt);
    }
    fclose($output);
    exit;
}

include __DIR__ . '/../includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h1 class="h3 mb-1">Manage Reports</h1>
                <p class="text-muted mb-0">View and manage all public complaint reports.</p>
            </div>
            <a href="dashboard.php" class="btn btn-outline-secondary">Dashboard</a>
        </div>

        <div class="card app-card mb-4">
            <div class="card-body p-4">
                <form method="GET" action="manage_reports.php" class="row g-3">
                    <div class="col-lg-5">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search" value="<?php echo sanitize_input($search); ?>" placeholder="Title, category, or user name">
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <label for="category" class="form-label">Category</label>
                        <select class="form-select" id="category" name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $category_filter === $category ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 col-lg-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Statuses</option>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $status_filter === $status ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($status); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 col-lg-2">
                        <label for="date_from" class="form-label">From</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo sanitize_input($date_from); ?>">
                    </div>
                    <div class="col-md-6 col-lg-2">
                        <label for="date_to" class="form-label">To</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo sanitize_input($date_to); ?>">
                    </div>
                    <div class="col-lg-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                    <div class="col-lg-2 d-flex align-items-end">
                        <a href="manage_reports.php?reset=1" class="btn btn-outline-secondary w-100">Clear</a>
                    </div>
                </form>

                <div class="d-flex flex-wrap gap-2 mt-3">
                    <a href="manage_reports.php?status=" class="btn btn-outline-secondary btn-sm">All</a>
                    <?php foreach ($statuses as $status): ?>
                        <a href="manage_reports.php?status=<?php echo urlencode($status); ?>" class="btn btn-outline-primary btn-sm">
                            <?php echo htmlspecialchars($status); ?>
                        </a>
                    <?php endforeach; ?>
                    <a href="manage_reports.php?export=1" class="btn btn-outline-primary btn-sm ms-auto">Export CSV</a>
                </div>
            </div>
        </div>

        <div class="card app-card">
            <div class="card-body p-0">
                <?php if ($bulk_error !== ''): ?>
                    <div class="p-4">
                        <div class="alert alert-danger mb-0" role="alert">
                            <?php echo htmlspecialchars($bulk_error); ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($error_message !== ''): ?>
                    <div class="p-4">
                        <div class="alert alert-danger mb-0" role="alert">
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    </div>
                <?php elseif (count($reports) > 0): ?>
                    <form method="POST" action="manage_reports.php">
                        <?php csrf_field(); ?>
                        <div class="p-3 border-bottom d-flex flex-column flex-lg-row gap-2 align-items-lg-center">
                            <div class="d-flex gap-2">
                                <select class="form-select form-select-sm" name="bulk_action">
                                    <option value="">Bulk Action</option>
                                    <option value="status">Change Status</option>
                                    <option value="assign">Assign To</option>
                                </select>
                                <select class="form-select form-select-sm" name="bulk_status">
                                    <option value="">Select Status</option>
                                    <?php foreach ($statuses as $status): ?>
                                        <option value="<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($status); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select class="form-select form-select-sm" name="bulk_assigned_to">
                                    <option value="">Assign Admin</option>
                                    <?php foreach ($admins as $admin): ?>
                                        <option value="<?php echo (int) $admin['id']; ?>"><?php echo htmlspecialchars($admin['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                            <span class="text-muted small">Selected: <span id="selected_count">0</span></span>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 48px;">
                                        <input type="checkbox" id="select_all">
                                    </th>
                                    <th>ID</th>
                                    <th>Image</th>
                                    <th>User Name</th>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Assigned</th>
                                    <th>Due Date</th>
                                    <th>Created Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reports as $report): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="report-checkbox" name="selected_reports[]" value="<?php echo (int) $report['id']; ?>">
                                        </td>
                                        <td><?php echo (int) $report['id']; ?></td>
                                        <td style="width: 100px;">
                                            <?php if (!empty($report['image'])): ?>
                                                <a href="/complaint-system/<?php echo htmlspecialchars($report['image']); ?>" target="_blank">
                                                    <img src="/complaint-system/<?php echo htmlspecialchars($report['image']); ?>" alt="Report image" class="img-thumbnail report-thumb" loading="lazy">
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small">No image</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($report['user_name']); ?></td>
                                        <td><?php echo htmlspecialchars($report['title']); ?></td>
                                        <td><?php echo htmlspecialchars($report['category']); ?></td>
                                        <td>
                                            <span class="badge status-badge <?php echo get_status_badge_class($report['status']); ?>">
                                                <?php echo htmlspecialchars($report['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($report['assigned_name'] ?? 'Unassigned'); ?></td>
                                        <td><?php echo htmlspecialchars(format_date($report['due_date'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars(format_datetime($report['created_at'])); ?></td>
                                        <td>
                                            <a href="report_details.php?id=<?php echo (int) $report['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            </table>
                        </div>
                    </form>
                    <?php if ($total_pages > 1): ?>
                        <div class="p-3 border-top d-flex justify-content-between align-items-center">
                            <span class="text-muted small">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                            <nav>
                                <ul class="pagination mb-0">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo max(1, $page - 1); ?>">Prev</a>
                                    </li>
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo min($total_pages, $page + 1); ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state text-center">
                        <h2 class="h5 mb-2">No reports found</h2>
                        <p class="text-muted mb-0">Try adjusting the search text, category, or status filter.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
    const selectAll = document.getElementById('select_all');
    const reportCheckboxes = document.querySelectorAll('.report-checkbox');
    const selectedCount = document.getElementById('selected_count');

    function updateSelectedCount() {
        const count = Array.from(reportCheckboxes).filter((checkbox) => checkbox.checked).length;
        if (selectedCount) {
            selectedCount.textContent = count.toString();
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            reportCheckboxes.forEach((checkbox) => {
                checkbox.checked = selectAll.checked;
            });
            updateSelectedCount();
        });
    }

    reportCheckboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', function () {
            if (selectAll) {
                selectAll.checked = Array.from(reportCheckboxes).every((item) => item.checked);
            }
            updateSelectedCount();
        });
    });

    updateSelectedCount();
</script>
