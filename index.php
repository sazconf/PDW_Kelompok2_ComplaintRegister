<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

start_secure_session();

$page_title = 'Home - Yogyakarta City Complaint Register';
$is_logged_in = is_logged_in();
$is_admin_user = is_admin();
$stats = [
    'total_reports' => 0,
    'pending_reports' => 0,
    'in_progress_reports' => 0,
    'resolved_reports' => 0
];
$recent_reports = [];
$profile = [
    'name' => $_SESSION['user_name'] ?? 'User',
    'email' => '',
    'created_at' => '',
    'profile_image' => ''
];
$dashboard_error = '';

if ($is_logged_in && $is_admin_user) {
    redirect('/complaint-system/admin/dashboard.php');
}

if ($is_logged_in && !$is_admin_user) {
    $user_id = (int) $_SESSION['user_id'];
    $profile_sql = 'SELECT name, email, created_at, profile_image FROM users WHERE id = ? LIMIT 1';
    $profile_stmt = mysqli_prepare($conn, $profile_sql);

    if ($profile_stmt) {
        mysqli_stmt_bind_param($profile_stmt, 'i', $user_id);
        mysqli_stmt_execute($profile_stmt);
        $profile_result = mysqli_stmt_get_result($profile_stmt);
        $profile_row = mysqli_fetch_assoc($profile_result);

        if ($profile_row) {
            $profile = $profile_row;
        }

        mysqli_stmt_close($profile_stmt);
    } else {
        error_log('Homepage profile prepare failed: ' . mysqli_error($conn));
    }

    $stats_sql = "SELECT
            COUNT(*) AS total_reports,
            COALESCE(SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END), 0) AS pending_reports,
            COALESCE(SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END), 0) AS in_progress_reports,
            COALESCE(SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END), 0) AS resolved_reports
        FROM reports
        WHERE user_id = ?";
    $stats_stmt = mysqli_prepare($conn, $stats_sql);

    if ($stats_stmt) {
        mysqli_stmt_bind_param($stats_stmt, 'i', $user_id);
        mysqli_stmt_execute($stats_stmt);
        $stats_result = mysqli_stmt_get_result($stats_stmt);
        $stats = mysqli_fetch_assoc($stats_result);
        mysqli_stmt_close($stats_stmt);
    } else {
        error_log('Homepage stats prepare failed: ' . mysqli_error($conn));
        $dashboard_error = 'Unable to load report summary right now.';
    }

    $recent_sql = 'SELECT id, title, status, created_at
        FROM reports
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 5';
    $recent_stmt = mysqli_prepare($conn, $recent_sql);

    if ($recent_stmt) {
        mysqli_stmt_bind_param($recent_stmt, 'i', $user_id);
        mysqli_stmt_execute($recent_stmt);
        $recent_result = mysqli_stmt_get_result($recent_stmt);

        while ($row = mysqli_fetch_assoc($recent_result)) {
            $recent_reports[] = $row;
        }

        mysqli_stmt_close($recent_stmt);
    } else {
        error_log('Homepage recent reports prepare failed: ' . mysqli_error($conn));
        $dashboard_error = 'Unable to load recent reports right now.';
    }

}

include 'includes/header.php';
?>

<?php if (!$is_logged_in): ?>
    <section class="page-section">
        <div class="container">
            <div class="row align-items-center justify-content-center">
                <div class="col-lg-9 text-center">
                    <div class="alert alert-info d-inline-flex mb-4" role="alert">
                        Public service complaint reporting platform
                    </div>

                    <h1 class="display-5 fw-bold text-primary mb-3">
                        Yogyakarta City Complaint Register
                    </h1>
                    <p class="lead text-muted mb-4">
                        Submit public complaints, attach evidence, and track responses transparently through one simple portal.
                    </p>

                    <div class="d-grid d-sm-flex justify-content-sm-center gap-2">
                        <a href="login.php" class="btn btn-primary btn-lg px-4">Login</a>
                        <a href="register.php" class="btn btn-outline-primary btn-lg px-4">Register</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
<?php elseif ($is_admin_user): ?>
    <section class="page-section">
        <div class="container">
            <div class="card app-card">
                <div class="card-body p-4 p-md-5 text-center">
                    <h1 class="h3 mb-3">Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></h1>
                    <p class="text-muted mb-4">Use the admin dashboard to manage submitted public complaints.</p>
                    <a href="admin/dashboard.php" class="btn btn-primary">Open Admin Dashboard</a>
                </div>
            </div>
        </div>
    </section>
<?php else: ?>
    <section class="page-section">
        <div class="container">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 page-heading">
                <div>
                    <h1 class="h3 mb-1">Welcome, <?php echo htmlspecialchars($profile['name']); ?></h1>
                    <p class="text-muted mb-0">Here is your complaint activity.</p>
                </div>
            </div>

            <?php if ($dashboard_error !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($dashboard_error); ?>
                </div>
            <?php endif; ?>

            <div class="row g-4 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <a href="my_reports.php" class="stat-card-link" aria-label="View all reports">
                        <div class="card app-card stat-card h-100">
                            <div class="card-body">
                                <p class="text-muted mb-1">Total Reports</p>
                                <h2 class="display-6 fw-bold mb-0"><?php echo (int) $stats['total_reports']; ?></h2>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <a href="my_reports.php?status=Pending" class="stat-card-link" aria-label="View pending reports">
                        <div class="card app-card stat-card stat-card-secondary h-100">
                            <div class="card-body">
                                <p class="text-muted mb-1">Pending Reports</p>
                                <h2 class="display-6 fw-bold text-secondary mb-0"><?php echo (int) $stats['pending_reports']; ?></h2>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <a href="my_reports.php?status=In%20Progress" class="stat-card-link" aria-label="View reports in progress">
                        <div class="card app-card stat-card stat-card-warning h-100">
                            <div class="card-body">
                                <p class="text-muted mb-1">In Progress</p>
                                <h2 class="display-6 fw-bold text-warning mb-0"><?php echo (int) $stats['in_progress_reports']; ?></h2>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <a href="my_reports.php?status=Resolved" class="stat-card-link" aria-label="View resolved reports">
                        <div class="card app-card stat-card stat-card-success h-100">
                            <div class="card-body">
                                <p class="text-muted mb-1">Resolved Reports</p>
                                <h2 class="display-6 fw-bold text-success mb-0"><?php echo (int) $stats['resolved_reports']; ?></h2>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card app-card h-100">
                        <div class="card-body p-0">
                            <div class="p-4 border-bottom">
                                <h2 class="h5 mb-1">Recent Reports</h2>
                                <p class="text-muted mb-0">Your latest submitted complaints.</p>
                            </div>

                            <?php if (count($recent_reports) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Title</th>
                                                <th>Status</th>
                                                <th>Created Date</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_reports as $report): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($report['title']); ?></td>
                                                    <td>
                                                        <span class="badge status-badge <?php echo get_status_badge_class($report['status']); ?>">
                                                            <?php echo htmlspecialchars($report['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars(format_datetime($report['created_at'])); ?></td>
                                                    <td>
                                                        <a href="report_details.php?id=<?php echo (int) $report['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state text-center">
                                    <h3 class="h5 mb-2">No reports yet</h3>
                                    <p class="text-muted mb-3">Start by submitting your first public complaint.</p>
                                    <a href="submit_report.php" class="btn btn-primary">Submit First Report</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card app-card h-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center gap-3 mb-4">
                                <?php if (!empty($profile['profile_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($profile['profile_image']); ?>" alt="Profile" class="profile-avatar">
                                <?php else: ?>
                                    <div class="profile-initial profile-avatar-fallback">
                                        <?php echo htmlspecialchars(strtoupper(substr($profile['name'], 0, 1))); ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <h2 class="h5 mb-1"><?php echo htmlspecialchars($profile['name']); ?></h2>
                                    <p class="text-muted small mb-0"><?php echo htmlspecialchars($profile['email']); ?></p>
                                </div>
                            </div>

                            <dl class="mb-4">
                                <dt class="text-muted small">Account Type</dt>
                                <dd class="mb-3">Citizen User</dd>

                                <dt class="text-muted small">Member Since</dt>
                                <dd class="mb-0"><?php echo htmlspecialchars(format_date($profile['created_at'])); ?></dd>
                            </dl>

                            <div class="d-grid gap-2">
                                <a href="submit_report.php" class="btn btn-primary">Submit Report</a>
                                <a href="profile.php" class="btn btn-outline-secondary">Manage Profile</a>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
