<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$page_title = 'Admin Report Details - Yogyakarta City Complaint Register';
$categories = ['Infrastructure', 'Cleanliness', 'Security', 'Public Service', 'Environment'];
$statuses = ['Pending', 'In Progress', 'Resolved', 'Rejected'];
$report_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$report = null;
$admins = [];
$status_history = [];
$errors = [];
$success_message = '';
$update_completed = false;
$has_coordinates = false;
$extra_head = '';
$form_title = '';
$form_description = '';
$form_category = '';
$form_incident_date = '';
$form_location = '';
$form_latitude = '';
$form_longitude = '';
$form_status = '';
$form_assigned_to = '';
$form_due_date = '';
$form_admin_response = '';

function is_valid_report_date($date)
{
    $parts = explode('-', $date);

    if (count($parts) !== 3) {
        return false;
    }

    return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
}

if ($report_id <= 0) {
    $errors['general'] = 'Invalid report ID.';
}

if ($report_id > 0) {
    $sql = "SELECT r.id, r.title, r.description, r.category, r.incident_date, r.location,
                    r.image, r.status, r.admin_response, r.latitude, r.longitude, r.created_at, r.updated_at,
                    r.user_id, r.assigned_to, r.due_date,
                u.name AS user_name, u.email AS user_email
            FROM reports r
            INNER JOIN users u ON r.user_id = u.id
            WHERE r.id = ?
            LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $report_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $report = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
    } else {
        error_log('Admin report detail query prepare failed: ' . mysqli_error($conn));
        $errors['general'] = 'Database error. Please try again.';
    }
}

if (!$report && $report_id > 0 && !isset($errors['general'])) {
    $errors['general'] = 'Report not found.';
}

if ($report) {
    $form_title = $report['title'];
    $form_description = $report['description'];
    $form_category = $report['category'];
    $form_incident_date = $report['incident_date'];
    $form_location = $report['location'] ?? '';
    $form_latitude = $report['latitude'] ?? '';
    $form_longitude = $report['longitude'] ?? '';
    $form_status = $report['status'];
    $form_assigned_to = $report['assigned_to'] ?? '';
    $form_due_date = $report['due_date'] ?? '';
    $form_admin_response = $report['admin_response'] ?? '';
}

$admins_result = mysqli_query($conn, "SELECT id, name, email FROM users WHERE role = 'admin' ORDER BY name");

if ($admins_result) {
    while ($row = mysqli_fetch_assoc($admins_result)) {
        $admins[] = $row;
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $report_id > 0) {
    require_valid_csrf();
    if (!$report) {
        $errors['general'] = 'Report not found.';
    } else {
        $form_title = trim($_POST['title'] ?? '');
        $form_description = trim($_POST['description'] ?? '');
        $form_category = trim($_POST['category'] ?? '');
        $form_incident_date = trim($_POST['incident_date'] ?? '');
        $form_location = trim($_POST['location'] ?? '');
        $form_latitude = trim($_POST['latitude'] ?? '');
        $form_longitude = trim($_POST['longitude'] ?? '');
        $form_status = trim($_POST['status'] ?? '');
        $form_assigned_to = trim($_POST['assigned_to'] ?? '');
        $form_due_date = trim($_POST['due_date'] ?? '');
        $form_admin_response = trim($_POST['admin_response'] ?? '');

        if ($form_title === '') {
            $errors['title'] = 'Title is required.';
        } elseif (strlen($form_title) < 5 || strlen($form_title) > 150) {
            $errors['title'] = 'Title must be between 5 and 150 characters.';
        }

        if ($form_description === '') {
            $errors['description'] = 'Description is required.';
        } elseif (strlen($form_description) < 10) {
            $errors['description'] = 'Description must be at least 10 characters.';
        }

        if ($form_category === '') {
            $errors['category'] = 'Category is required.';
        } elseif (!in_array($form_category, $categories, true)) {
            $errors['category'] = 'Please select a valid category.';
        }

        if ($form_incident_date === '') {
            $errors['incident_date'] = 'Incident date is required.';
        } elseif (!is_valid_report_date($form_incident_date)) {
            $errors['incident_date'] = 'Please enter a valid date.';
        }

        if ($form_location === '') {
            $errors['location'] = 'Location is required.';
        }

        if ($form_latitude !== '' || $form_longitude !== '') {
            if ($form_latitude === '' || $form_longitude === '') {
                $errors['coordinates'] = 'Please provide both latitude and longitude.';
            } elseif (!is_numeric($form_latitude) || !is_numeric($form_longitude)) {
                $errors['coordinates'] = 'Selected map coordinates are invalid.';
            } elseif ((float) $form_latitude < -90 || (float) $form_latitude > 90 || (float) $form_longitude < -180 || (float) $form_longitude > 180) {
                $errors['coordinates'] = 'Selected map coordinates are outside the valid range.';
            }
        }

        if ($form_status === '') {
            $errors['status'] = 'Status is required.';
        } elseif (!in_array($form_status, $statuses, true)) {
            $errors['status'] = 'Please select a valid status.';
        }

        if ($form_assigned_to !== '' && !ctype_digit($form_assigned_to)) {
            $errors['assigned_to'] = 'Please select a valid admin.';
        }

        if ($form_due_date !== '' && !is_valid_report_date($form_due_date)) {
            $errors['due_date'] = 'Please enter a valid due date.';
        }

        if (empty($errors)) {
            $latitude_value = $form_latitude === '' ? null : $form_latitude;
            $longitude_value = $form_longitude === '' ? null : $form_longitude;
            $previous_status = $report['status'];
            $previous_assigned = $report['assigned_to'] ?? '';
            $previous_due = $report['due_date'] ?? '';
            $assigned_value = $form_assigned_to === '' ? null : (int) $form_assigned_to;
            $due_value = $form_due_date === '' ? null : $form_due_date;
            $update_sql = 'UPDATE reports
                           SET title = ?, description = ?, category = ?, incident_date = ?, location = ?,
                               latitude = ?, longitude = ?, status = ?, assigned_to = ?, due_date = ?,
                               admin_response = ?, updated_at = NOW()
                           WHERE id = ?
                           LIMIT 1';
            $update_stmt = mysqli_prepare($conn, $update_sql);

            if ($update_stmt) {
                mysqli_stmt_bind_param(
                    $update_stmt,
                    'ssssssssissi',
                    $form_title,
                    $form_description,
                    $form_category,
                    $form_incident_date,
                    $form_location,
                    $latitude_value,
                    $longitude_value,
                    $form_status,
                    $assigned_value,
                    $due_value,
                    $form_admin_response,
                    $report_id
                );

                if (mysqli_stmt_execute($update_stmt)) {
                    $update_completed = true;
                    log_admin_action($conn, 'update_report', 'Updated report details.', $report_id);

                    if ($previous_status !== $form_status) {
                        add_status_history($conn, $report_id, $form_status, 'Status updated by admin.');
                        add_notification($conn, (int) $report['user_id'], 'Report status updated', 'Your report "' . $form_title . '" is now ' . $form_status . '.');
                        log_admin_action($conn, 'update_status', 'Status changed from ' . $previous_status . ' to ' . $form_status . '.', $report_id);
                    }

                    if ((string) $previous_assigned !== (string) $form_assigned_to) {
                        log_admin_action($conn, 'assign_report', 'Assigned report to admin ID ' . ($form_assigned_to === '' ? 'none' : $form_assigned_to) . '.', $report_id);
                    }

                    if ((string) $previous_due !== (string) $form_due_date) {
                        log_admin_action($conn, 'update_due_date', 'Updated due date to ' . ($form_due_date === '' ? 'none' : $form_due_date) . '.', $report_id);
                    }
                } else {
                    error_log('Admin report update failed: ' . mysqli_stmt_error($update_stmt));
                    $errors['general'] = 'Unable to update report. Please try again.';
                }

                mysqli_stmt_close($update_stmt);
            } else {
                error_log('Admin report update prepare failed: ' . mysqli_error($conn));
                $errors['general'] = 'Database error. Please try again.';
            }
        }
    }
}

if ($report && $update_completed) {
    $refresh_stmt = mysqli_prepare($conn, $sql);

    if ($refresh_stmt) {
        mysqli_stmt_bind_param($refresh_stmt, 'i', $report_id);
        mysqli_stmt_execute($refresh_stmt);
        $refresh_result = mysqli_stmt_get_result($refresh_stmt);
        $report = mysqli_fetch_assoc($refresh_result);
        mysqli_stmt_close($refresh_stmt);

        if ($report) {
            $form_title = $report['title'];
            $form_description = $report['description'];
            $form_category = $report['category'];
            $form_incident_date = $report['incident_date'];
            $form_location = $report['location'] ?? '';
            $form_latitude = $report['latitude'] ?? '';
            $form_longitude = $report['longitude'] ?? '';
            $form_status = $report['status'];
            $form_assigned_to = $report['assigned_to'] ?? '';
            $form_due_date = $report['due_date'] ?? '';
            $form_admin_response = $report['admin_response'] ?? '';
        }
    }

    $success_message = 'Report has been updated successfully.';
}

if ($report && is_numeric($report['latitude']) && is_numeric($report['longitude'])) {
    $has_coordinates = true;
    $extra_head = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">';
}

include __DIR__ . '/../includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="mb-3">
            <a href="manage_reports.php" class="btn btn-outline-secondary">&larr; Back to Manage Reports</a>
        </div>

        <?php if ($success_message !== ''): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($errors['general'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($errors['general']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($report): ?>
            <?php if (!empty($errors) && !isset($errors['general'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    Please review the highlighted fields and try again.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="report_details.php?id=<?php echo (int) $report['id']; ?>" novalidate>
                <?php csrf_field(); ?>
                <div class="row g-3">
                    <div class="col-lg-7">
                        <div class="card app-card mb-3">
                            <div class="card-body p-3">
                                <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-2">
                                    <div>
                                        <h1 class="h3 mb-1">Edit Report</h1>
                                        <p class="text-muted mb-0">
                                            Submitted by <?php echo htmlspecialchars($report['user_name']); ?>
                                            (<?php echo htmlspecialchars($report['user_email']); ?>)
                                        </p>
                                    </div>
                                    <div>
                                        <span class="badge status-badge <?php echo get_status_badge_class($report['status']); ?>">
                                            <?php echo htmlspecialchars($report['status']); ?>
                                        </span>
                                    </div>
                                </div>

                                <dl class="row mb-3 border-top pt-3">
                                    <dt class="col-sm-4">Created Date</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars(format_datetime($report['created_at'])); ?></dd>

                                    <dt class="col-sm-4">Last Updated</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars(format_datetime($report['updated_at'] ?? '')); ?></dd>
                                </dl>

                                <div class="mb-2">
                                    <label for="title" class="form-label">Title</label>
                                    <input type="text" class="form-control <?php echo isset($errors['title']) ? 'is-invalid' : ''; ?>" id="title" name="title" value="<?php echo sanitize_input($form_title); ?>" required>
                                    <?php if (isset($errors['title'])): ?>
                                        <div class="invalid-feedback"><?php echo htmlspecialchars($errors['title']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-2">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control <?php echo isset($errors['description']) ? 'is-invalid' : ''; ?>" id="description" name="description" rows="6" required><?php echo sanitize_input($form_description); ?></textarea>
                                    <?php if (isset($errors['description'])): ?>
                                        <div class="invalid-feedback"><?php echo htmlspecialchars($errors['description']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <label for="category" class="form-label">Category</label>
                                        <select class="form-select <?php echo isset($errors['category']) ? 'is-invalid' : ''; ?>" id="category" name="category" required>
                                            <option value="">Select category</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $form_category === $category ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($category); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (isset($errors['category'])): ?>
                                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['category']); ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-md-6 mb-2">
                                        <label for="incident_date" class="form-label">Incident Date</label>
                                        <input type="date" class="form-control <?php echo isset($errors['incident_date']) ? 'is-invalid' : ''; ?>" id="incident_date" name="incident_date" value="<?php echo sanitize_input($form_incident_date); ?>" required>
                                        <?php if (isset($errors['incident_date'])): ?>
                                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['incident_date']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="mb-2">
                                    <label for="location" class="form-label">Location</label>
                                    <input type="text" class="form-control <?php echo isset($errors['location']) ? 'is-invalid' : ''; ?>" id="location" name="location" value="<?php echo sanitize_input($form_location); ?>" required>
                                    <?php if (isset($errors['location'])): ?>
                                        <div class="invalid-feedback"><?php echo htmlspecialchars($errors['location']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <label for="latitude" class="form-label">Latitude</label>
                                        <input type="text" class="form-control <?php echo isset($errors['coordinates']) ? 'is-invalid' : ''; ?>" id="latitude" name="latitude" value="<?php echo sanitize_input($form_latitude); ?>" placeholder="e.g. -6.175392">
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label for="longitude" class="form-label">Longitude</label>
                                        <input type="text" class="form-control <?php echo isset($errors['coordinates']) ? 'is-invalid' : ''; ?>" id="longitude" name="longitude" value="<?php echo sanitize_input($form_longitude); ?>" placeholder="e.g. 106.827153">
                                        <?php if (isset($errors['coordinates'])): ?>
                                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['coordinates']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($has_coordinates): ?>
                                    <div class="mt-3">
                                        <h2 class="h6 mb-2">Selected Location</h2>
                                        <div id="admin_report_map" class="map-panel map-panel-sm"></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="card app-card mb-3">
                            <div class="card-body p-3">
                                <h2 class="h5 mb-2">Admin Controls</h2>
                                <div class="mb-2">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select <?php echo isset($errors['status']) ? 'is-invalid' : ''; ?>" id="status" name="status" required>
                                        <?php foreach ($statuses as $status): ?>
                                            <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $form_status === $status ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($status); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['status'])): ?>
                                        <div class="invalid-feedback"><?php echo htmlspecialchars($errors['status']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-2">
                                    <label for="assigned_to" class="form-label">Assign To</label>
                                    <select class="form-select <?php echo isset($errors['assigned_to']) ? 'is-invalid' : ''; ?>" id="assigned_to" name="assigned_to">
                                        <option value="">Unassigned</option>
                                        <?php foreach ($admins as $admin): ?>
                                            <option value="<?php echo (int) $admin['id']; ?>" <?php echo (string) $form_assigned_to === (string) $admin['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($admin['name']); ?> (<?php echo htmlspecialchars($admin['email']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['assigned_to'])): ?>
                                        <div class="invalid-feedback"><?php echo htmlspecialchars($errors['assigned_to']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-2">
                                    <label for="due_date" class="form-label">Due Date</label>
                                    <input type="date" class="form-control <?php echo isset($errors['due_date']) ? 'is-invalid' : ''; ?>" id="due_date" name="due_date" value="<?php echo sanitize_input($form_due_date); ?>">
                                    <?php if (isset($errors['due_date'])): ?>
                                        <div class="invalid-feedback"><?php echo htmlspecialchars($errors['due_date']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label for="admin_response" class="form-label">Official Admin Response</label>
                                    <textarea class="form-control" id="admin_response" name="admin_response" rows="5" placeholder="Write the official response shown to the user"><?php echo htmlspecialchars($form_admin_response); ?></textarea>
                                </div>

                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">Save Updates</button>
                                </div>
                            </div>
                        </div>

                        <div class="card app-card mb-3">
                            <div class="card-body p-3">
                                <h2 class="h5 mb-2">Uploaded Image</h2>
                                <?php if (!empty($report['image'])): ?>
                                    <img src="/complaint-system/<?php echo htmlspecialchars($report['image']); ?>" alt="Uploaded report evidence" class="img-fluid rounded border report-image w-100" loading="lazy">
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#imageModal" data-image="/complaint-system/<?php echo htmlspecialchars($report['image']); ?>">View Full Image</button>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted mb-0">No image available.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card app-card">
                            <div class="card-body p-3">
                                <h2 class="h5 mb-2">Status Timeline</h2>
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

                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>

<?php if ($has_coordinates): ?>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const adminReportLatitude = <?php echo json_encode((float) $report['latitude']); ?>;
        const adminReportLongitude = <?php echo json_encode((float) $report['longitude']); ?>;
        const adminReportMap = L.map('admin_report_map', {
            dragging: true,
            scrollWheelZoom: false
        }).setView([adminReportLatitude, adminReportLongitude], 15);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(adminReportMap);

        L.marker([adminReportLatitude, adminReportLongitude]).addTo(adminReportMap);
    </script>
<?php endif; ?>

<?php if (!empty($report['image'])): ?>
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body p-0">
                    <img src="/complaint-system/<?php echo htmlspecialchars($report['image']); ?>" alt="Report evidence" class="w-100">
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
