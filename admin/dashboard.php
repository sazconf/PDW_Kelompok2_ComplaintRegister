<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$stats = [
    'total_reports' => 0,
    'pending_reports' => 0,
    'resolved_reports' => 0,
    'rejected_reports' => 0
];
$recent_reports = [];
$dashboard_error = '';
$recent_error = '';
$category_labels = [];
$category_values = [];
$month_labels = [];
$month_values = [];
$audit_logs = [];

$count_sql = "SELECT
        COUNT(*) AS total_reports,
        COALESCE(SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END), 0) AS pending_reports,
        COALESCE(SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END), 0) AS resolved_reports,
        COALESCE(SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END), 0) AS rejected_reports
    FROM reports";
$count_result = mysqli_query($conn, $count_sql);

if ($count_result) {
    $stats = mysqli_fetch_assoc($count_result);
} else {
    error_log('Admin dashboard count query failed: ' . mysqli_error($conn));
    $dashboard_error = 'Dashboard statistics are temporarily unavailable.';
}

$recent_sql = "SELECT r.id, r.title, r.category, r.status, r.created_at, u.name AS user_name
    FROM reports r
    INNER JOIN users u ON r.user_id = u.id
    ORDER BY r.created_at DESC
    LIMIT 6";
$recent_result = mysqli_query($conn, $recent_sql);

if ($recent_result) {
    while ($row = mysqli_fetch_assoc($recent_result)) {
        $recent_reports[] = $row;
    }
} else {
    error_log('Admin dashboard recent query failed: ' . mysqli_error($conn));
    $recent_error = 'Recent complaints are temporarily unavailable.';
}

$category_result = mysqli_query($conn, 'SELECT category, COUNT(*) AS total FROM reports GROUP BY category ORDER BY total DESC');

if ($category_result) {
    while ($row = mysqli_fetch_assoc($category_result)) {
        $category_labels[] = $row['category'];
        $category_values[] = (int) $row['total'];
    }
}

$month_result = mysqli_query($conn, "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS total
    FROM reports
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month");

if ($month_result) {
    while ($row = mysqli_fetch_assoc($month_result)) {
        $month_labels[] = $row['month'];
        $month_values[] = (int) $row['total'];
    }
}

$audit_result = mysqli_query($conn, 'SELECT a.action, a.details, a.created_at, u.name AS admin_name
    FROM audit_logs a
    INNER JOIN users u ON a.admin_id = u.id
    ORDER BY a.created_at DESC
    LIMIT 5');

if ($audit_result) {
    while ($row = mysqli_fetch_assoc($audit_result)) {
        $audit_logs[] = $row;
    }
}

$page_title = 'Admin Dashboard - Yogyakarta City Complaint Register';
$extra_head = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>';
include __DIR__ . '/../includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h1 class="h3 mb-1">Admin Dashboard</h1>
                <p class="text-muted mb-0">Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?>. Overview of public complaint reports.</p>
            </div>
            <a href="manage_reports.php" class="btn btn-primary">Manage Reports</a>
        </div>

        <?php if ($dashboard_error !== ''): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($dashboard_error); ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-sm-6 col-xl-3">
                <a class="card-link" href="manage_reports.php">
                    <div class="card app-card stat-card h-100">
                        <div class="card-body">
                            <p class="text-muted mb-1">Total Reports</p>
                            <h2 class="display-6 fw-bold mb-0"><?php echo (int) $stats['total_reports']; ?></h2>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-xl-3">
                <a class="card-link" href="manage_reports.php?status=Pending">
                    <div class="card app-card stat-card h-100">
                        <div class="card-body">
                            <p class="text-muted mb-1">Pending Reports</p>
                            <h2 class="display-6 fw-bold mb-0 text-secondary"><?php echo (int) $stats['pending_reports']; ?></h2>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-xl-3">
                <a class="card-link" href="manage_reports.php?status=Resolved">
                    <div class="card app-card stat-card h-100">
                        <div class="card-body">
                            <p class="text-muted mb-1">Resolved Reports</p>
                            <h2 class="display-6 fw-bold mb-0 text-success"><?php echo (int) $stats['resolved_reports']; ?></h2>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-xl-3">
                <a class="card-link" href="manage_reports.php?status=Rejected">
                    <div class="card app-card stat-card h-100">
                        <div class="card-body">
                            <p class="text-muted mb-1">Rejected Reports</p>
                            <h2 class="display-6 fw-bold mb-0 text-danger"><?php echo (int) $stats['rejected_reports']; ?></h2>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-12">
                <div class="card app-card">
                    <div class="card-body p-0">
                        <div class="p-4 border-bottom">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                                <div>
                                    <h2 class="h5 mb-1">Recent Complaints</h2>
                                    <p class="text-muted mb-0">Latest reports submitted by citizens.</p>
                                </div>
                                <a href="manage_reports.php" class="btn btn-outline-primary btn-sm">View All</a>
                            </div>
                        </div>

                        <?php if ($recent_error !== ''): ?>
                            <div class="p-4">
                                <div class="alert alert-danger mb-0" role="alert">
                                    <?php echo htmlspecialchars($recent_error); ?>
                                </div>
                            </div>
                        <?php elseif (count($recent_reports) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Title</th>
                                            <th>Category</th>
                                            <th>User</th>
                                            <th>Status</th>
                                            <th>Submitted</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_reports as $report): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($report['title']); ?></td>
                                                <td><?php echo htmlspecialchars($report['category']); ?></td>
                                                <td><?php echo htmlspecialchars($report['user_name']); ?></td>
                                                <td>
                                                    <span class="badge status-badge <?php echo get_status_badge_class($report['status']); ?>">
                                                        <?php echo htmlspecialchars($report['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars(format_datetime($report['created_at'])); ?></td>
                                                <td>
                                                    <a href="report_details.php?id=<?php echo (int) $report['id']; ?>" class="btn btn-sm btn-outline-primary">Review</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state text-center">
                                <h3 class="h5 mb-2">No complaints yet</h3>
                                <p class="text-muted mb-0">New reports will appear here as soon as they are submitted.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-lg-6">
                <div class="card app-card h-100">
                    <div class="card-body p-4">
                        <h2 class="h5 mb-3">Reports by Category</h2>
                        <canvas id="categoryChart" height="220"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card app-card h-100">
                    <div class="card-body p-4">
                        <h2 class="h5 mb-3">Reports over Time</h2>
                        <canvas id="monthChart" height="220"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-12">
                <div class="card app-card">
                    <div class="card-body p-4">
                        <h2 class="h5 mb-3">Recent Admin Activity</h2>
                        <?php if (count($audit_logs) > 0): ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($audit_logs as $log): ?>
                                    <li class="list-group-item px-0">
                                        <div class="d-flex justify-content-between">
                                            <span class="fw-semibold"><?php echo htmlspecialchars($log['admin_name']); ?> • <?php echo htmlspecialchars($log['action']); ?></span>
                                            <span class="text-muted small"><?php echo htmlspecialchars(format_datetime($log['created_at'])); ?></span>
                                        </div>
                                        <?php if (!empty($log['details'])): ?>
                                            <div class="text-muted small"><?php echo htmlspecialchars($log['details']); ?></div>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted mb-0">No admin activity yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
    const categoryLabels = <?php echo json_encode($category_labels); ?>;
    const categoryValues = <?php echo json_encode($category_values); ?>;
    const monthLabels = <?php echo json_encode($month_labels); ?>;
    const monthValues = <?php echo json_encode($month_values); ?>;

    const categoryContext = document.getElementById('categoryChart');
    const monthContext = document.getElementById('monthChart');

    if (categoryContext) {
        new Chart(categoryContext, {
            type: 'bar',
            data: {
                labels: categoryLabels,
                datasets: [{
                    label: 'Reports',
                    data: categoryValues,
                    backgroundColor: 'rgba(22, 125, 134, 0.6)'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    if (monthContext) {
        new Chart(monthContext, {
            type: 'line',
            data: {
                labels: monthLabels,
                datasets: [{
                    label: 'Reports',
                    data: monthValues,
                    borderColor: 'rgba(22, 125, 134, 0.8)',
                    backgroundColor: 'rgba(22, 125, 134, 0.2)',
                    tension: 0.35,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
