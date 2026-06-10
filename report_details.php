<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

require_login();

$page_title = 'Report Details - Yogyakarta City Complaint Register';
$user_id = (int) $_SESSION['user_id'];
$report_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$report = null;
$error_message = '';
$has_coordinates = false;
$status_history = [];
$extra_head = '';

if ($report_id <= 0) {
    $error_message = 'Invalid report ID.';
}

if ($report_id > 0) {
    $sql = 'SELECT id, title, description, category, incident_date, location, image, status, admin_response, latitude, longitude, created_at
            FROM reports
            WHERE id = ? AND user_id = ?
            LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $report_id, $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $report = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
    } else {
        error_log('User report detail prepare failed: ' . mysqli_error($conn));
        $error_message = 'Unable to load report details right now. Please try again later.';
    }
}

if ($report_id > 0) {
    $history_stmt = mysqli_prepare($conn, 'SELECT status, note, created_at FROM report_status_history WHERE report_id = ? ORDER BY created_at DESC');

    if ($history_stmt) {
        mysqli_stmt_bind_param($history_stmt, 'i', $report_id);
        mysqli_stmt_execute($history_stmt);
        $history_result = mysqli_stmt_get_result($history_stmt);

        while ($row = mysqli_fetch_assoc($history_result)) {
            $status_history[] = $row;
        }

        mysqli_stmt_close($history_stmt);
    }
}

if ($report && is_numeric($report['latitude']) && is_numeric($report['longitude'])) {
    $has_coordinates = true;
    $extra_head = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">';
}

include 'includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="mb-4">
            <a href="my_reports.php" class="btn btn-outline-secondary">&larr; Back to My Reports</a>
        </div>

        <?php if ($error_message !== ''): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php elseif ($report): ?>
            <div class="alert alert-info" role="alert">
                Current status:
                <span class="badge <?php echo get_status_badge_class($report['status']); ?>">
                    <?php echo htmlspecialchars($report['status']); ?>
                </span>
            </div>

            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card app-card h-100">
                        <div class="card-body p-4">
                            <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-3">
                                <div>
                                    <h1 class="h3 mb-1"><?php echo htmlspecialchars($report['title']); ?></h1>
                                    <p class="text-muted mb-0">
                                        <?php echo htmlspecialchars($report['category']); ?>
                                    </p>
                                </div>
                                <div>
                                    <span class="badge status-badge <?php echo get_status_badge_class($report['status']); ?>">
                                        <?php echo htmlspecialchars($report['status']); ?>
                                    </span>
                                </div>
                            </div>

                            <dl class="row mb-4 border-top pt-3">
                                <dt class="col-sm-4">Incident Date</dt>
                                <dd class="col-sm-8"><?php echo htmlspecialchars(format_date($report['incident_date'])); ?></dd>

                                <dt class="col-sm-4">Location</dt>
                                <dd class="col-sm-8"><?php echo htmlspecialchars($report['location']); ?></dd>

                                <dt class="col-sm-4">Submitted At</dt>
                                <dd class="col-sm-8"><?php echo htmlspecialchars(format_datetime($report['created_at'])); ?></dd>
                            </dl>

                            <h2 class="h5">Description</h2>
                            <p class="mb-4"><?php echo nl2br(htmlspecialchars($report['description'])); ?></p>

                            <h2 class="h5">Admin Response</h2>
                            <?php if (!empty($report['admin_response'])): ?>
                                <div class="alert alert-light border mb-0" role="alert">
                                    <?php echo nl2br(htmlspecialchars($report['admin_response'])); ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-secondary mb-0" role="alert">
                                    No admin response yet.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card app-card">
                        <div class="card-body p-4">
                            <h2 class="h5 mb-3">Uploaded Image</h2>
                            <?php if (!empty($report['image'])): ?>
                                <img src="<?php echo htmlspecialchars($report['image']); ?>" alt="Uploaded report evidence" class="img-fluid rounded border report-image w-100" loading="lazy">
                                <div class="mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#imageModal" data-image="<?php echo htmlspecialchars($report['image']); ?>">View Full Image</button>
                                </div>
                            <?php else: ?>
                                <p class="text-muted mb-0">No image available.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card app-card mt-4">
                        <div class="card-body p-4">
                            <h2 class="h5 mb-3">Status Timeline</h2>
                            <?php if (count($status_history) > 0): ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($status_history as $history): ?>
                                        <li class="list-group-item px-0">
                                            <div class="d-flex justify-content-between">
                                                <span class="fw-semibold"><?php echo htmlspecialchars($history['status']); ?></span>
                                                <span class="text-muted small"><?php echo htmlspecialchars(format_datetime($history['created_at'])); ?></span>
                                            </div>
                                            <?php if (!empty($history['note'])): ?>
                                                <div class="text-muted small"><?php echo htmlspecialchars($history['note']); ?></div>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted mb-0">No history available.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($has_coordinates): ?>
                        <div class="card app-card mt-4">
                            <div class="card-body p-4">
                                <h2 class="h5 mb-3">Selected Location</h2>
                                <div id="report_map" class="map-panel map-panel-sm"></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-danger" role="alert">
                Report not found or you do not have permission to view it.
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($has_coordinates): ?>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const reportLatitude = <?php echo json_encode((float) $report['latitude']); ?>;
        const reportLongitude = <?php echo json_encode((float) $report['longitude']); ?>;
        const reportMap = L.map('report_map', {
            dragging: true,
            scrollWheelZoom: false
        }).setView([reportLatitude, reportLongitude], 15);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(reportMap);

        L.marker([reportLatitude, reportLongitude]).addTo(reportMap);
    </script>
<?php endif; ?>

<?php if (!empty($report['image'])): ?>
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body p-0">
                    <img src="<?php echo htmlspecialchars($report['image']); ?>" alt="Report evidence" class="w-100">
                </div>
            </div>
        </div>
    </div>

    <script>
        const imageModal = document.getElementById('imageModal');
        if (imageModal) {
            imageModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const image = button ? button.getAttribute('data-image') : '';
                const modalImage = imageModal.querySelector('img');
                if (modalImage && image) {
                    modalImage.src = image;
                }
            });
        }
    </script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
