<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

require_login();

$page_title = 'My Reports - Yogyakarta City Complaint Register';
$user_id = (int) $_SESSION['user_id'];
$statuses = ['Pending', 'In Progress', 'Resolved', 'Rejected'];
$status_filter = trim($_GET['status'] ?? '');

if (!in_array($status_filter, $statuses, true)) {
    $status_filter = '';
}

$reports = [];
$error_message = '';
$per_page = 10;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;
$total_reports = 0;
$total_pages = 1;

$count_sql = 'SELECT COUNT(*) AS total_count FROM reports WHERE user_id = ?';

if ($status_filter !== '') {
    $count_sql .= ' AND status = ?';
}

$count_stmt = mysqli_prepare($conn, $count_sql);

if ($count_stmt) {
    if ($status_filter !== '') {
        mysqli_stmt_bind_param($count_stmt, 'is', $user_id, $status_filter);
    } else {
        mysqli_stmt_bind_param($count_stmt, 'i', $user_id);
    }

    mysqli_stmt_execute($count_stmt);
    $count_result = mysqli_stmt_get_result($count_stmt);
    $count_row = mysqli_fetch_assoc($count_result);
    $total_reports = (int) ($count_row['total_count'] ?? 0);
    $total_pages = max(1, (int) ceil($total_reports / $per_page));
    mysqli_stmt_close($count_stmt);
}

$sql = 'SELECT id, title, category, status, image, created_at
        FROM reports
        WHERE user_id = ?';

if ($status_filter !== '') {
    $sql .= ' AND status = ?';
}

$sql .= ' ORDER BY created_at DESC
          LIMIT ? OFFSET ?';
$stmt = mysqli_prepare($conn, $sql);

if ($stmt) {
    if ($status_filter !== '') {
        mysqli_stmt_bind_param($stmt, 'isii', $user_id, $status_filter, $per_page, $offset);
    } else {
        mysqli_stmt_bind_param($stmt, 'iii', $user_id, $per_page, $offset);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $reports[] = $row;
    }

    mysqli_stmt_close($stmt);
} else {
    error_log('My reports query prepare failed: ' . mysqli_error($conn));
    $error_message = 'Unable to load your reports right now. Please try again later.';
}

include 'includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h1 class="h3 mb-1">My Reports</h1>
                <p class="text-muted mb-0">View reports that you have submitted.</p>
            </div>
            <a href="submit_report.php" class="btn btn-primary">Submit Report</a>
        </div>

        <div class="card app-card mb-4">
            <div class="card-body p-3">
                <form method="GET" action="my_reports.php" class="row g-2 align-items-end">
                    <div class="col-sm-8 col-md-5">
                        <label for="status" class="form-label">Filter by Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Reports</option>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $status_filter === $status ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($status); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-4 col-md-auto">
                        <button type="submit" class="btn btn-primary w-100">Apply Filter</button>
                    </div>
                    <?php if ($status_filter !== ''): ?>
                        <div class="col-sm-4 col-md-auto">
                            <a href="my_reports.php" class="btn btn-outline-secondary w-100">Clear</a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="card app-card">
            <div class="card-body p-0">
                <?php if ($error_message !== ''): ?>
                    <div class="p-4">
                        <div class="alert alert-danger mb-0" role="alert">
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    </div>
                <?php elseif (count($reports) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Image</th>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Created At</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reports as $report): ?>
                                    <tr>
                                        <td style="width: 110px;">
                                            <?php if (!empty($report['image'])): ?>
                                                <img src="<?php echo htmlspecialchars($report['image']); ?>" alt="Report image" class="img-thumbnail report-thumb" loading="lazy">
                                            <?php else: ?>
                                                <span class="text-muted small">No image</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($report['title']); ?></td>
                                        <td><?php echo htmlspecialchars($report['category']); ?></td>
                                        <td>
                                            <span class="badge status-badge <?php echo get_status_badge_class($report['status']); ?>">
                                                <?php echo htmlspecialchars($report['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars(format_datetime($report['created_at'])); ?></td>
                                        <td>
                                            <a href="report_details.php?id=<?php echo (int) $report['id']; ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($total_pages > 1): ?>
                        <div class="p-3 border-top d-flex justify-content-between align-items-center">
                            <span class="text-muted small">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                            <nav>
                                <ul class="pagination mb-0">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(['status' => $status_filter, 'page' => max(1, $page - 1)]); ?>">Prev</a>
                                    </li>
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(['status' => $status_filter, 'page' => min($total_pages, $page + 1)]); ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state text-center">
                        <h2 class="h5 mb-2"><?php echo $status_filter !== '' ? 'No matching reports' : 'No reports yet'; ?></h2>
                        <p class="text-muted mb-3">
                            <?php echo $status_filter !== '' ? 'There are no reports with the selected status.' : 'When you submit a complaint, it will appear here for tracking.'; ?>
                        </p>
                        <?php if ($status_filter !== ''): ?>
                            <a href="my_reports.php" class="btn btn-outline-secondary">View All Reports</a>
                        <?php else: ?>
                            <a href="submit_report.php" class="btn btn-primary">Submit Your First Report</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
