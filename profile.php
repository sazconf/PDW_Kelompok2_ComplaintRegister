<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

require_login();

$page_title = 'Profile - Yogyakarta City Complaint Register';
$user_id = (int) $_SESSION['user_id'];
$profile_errors = [];
$password_errors = [];
$photo_errors = [];
$profile_success = '';
$password_success = '';
$photo_success = '';
$user = [
    'name' => '',
    'email' => '',
    'profile_image' => '',
    'password' => '',
    'role' => '',
    'created_at' => ''
];

$allowed_photo_extensions = ['jpg', 'jpeg', 'png', 'webp'];
$allowed_photo_mime_types = ['image/jpeg', 'image/png', 'image/webp'];
$max_photo_size = 2 * 1024 * 1024;

function load_profile_user($conn, $user_id)
{
    $sql = 'SELECT name, email, profile_image, password, role, created_at FROM users WHERE id = ? LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        error_log('Profile load prepare failed: ' . mysqli_error($conn));
        return null;
    }

    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $user;
}

$loaded_user = load_profile_user($conn, $user_id);

if (!$loaded_user) {
    redirect('/complaint-system/logout.php');
}

$user = $loaded_user;
$name = $user['name'];
$email = $user['email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $form_type = $_POST['form_type'] ?? '';

    if ($form_type === 'profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($name === '') {
            $profile_errors['name'] = 'Full name is required.';
        }

        if ($email === '') {
            $profile_errors['email'] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $profile_errors['email'] = 'Please enter a valid email address.';
        }

        if (empty($profile_errors)) {
            $check_sql = 'SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1';
            $check_stmt = mysqli_prepare($conn, $check_sql);

            if ($check_stmt) {
                mysqli_stmt_bind_param($check_stmt, 'si', $email, $user_id);
                mysqli_stmt_execute($check_stmt);
                mysqli_stmt_store_result($check_stmt);

                if (mysqli_stmt_num_rows($check_stmt) > 0) {
                    $profile_errors['email'] = 'This email is already used by another account.';
                }

                mysqli_stmt_close($check_stmt);
            } else {
                error_log('Profile duplicate email prepare failed: ' . mysqli_error($conn));
                $profile_errors['general'] = 'Unable to validate email right now.';
            }
        }

        if (empty($profile_errors)) {
            $update_sql = 'UPDATE users SET name = ?, email = ? WHERE id = ? LIMIT 1';
            $update_stmt = mysqli_prepare($conn, $update_sql);

            if ($update_stmt) {
                mysqli_stmt_bind_param($update_stmt, 'ssi', $name, $email, $user_id);

                if (mysqli_stmt_execute($update_stmt)) {
                    $_SESSION['user_name'] = $name;
                    set_flash_message('success', 'Profile information updated successfully.');
                    $user['name'] = $name;
                    $user['email'] = $email;
                } else {
                    error_log('Profile update failed: ' . mysqli_stmt_error($update_stmt));
                    $profile_errors['general'] = 'Profile update failed. Please try again.';
                }

                mysqli_stmt_close($update_stmt);
            } else {
                error_log('Profile update prepare failed: ' . mysqli_error($conn));
                $profile_errors['general'] = 'Profile update is temporarily unavailable.';
            }
        }
    }

    if ($form_type === 'photo') {
        $photo_action = $_POST['photo_action'] ?? 'upload';

        if ($photo_action === 'remove') {
            $remove_sql = 'UPDATE users SET profile_image = NULL WHERE id = ? LIMIT 1';
            $remove_stmt = mysqli_prepare($conn, $remove_sql);

            if ($remove_stmt) {
                mysqli_stmt_bind_param($remove_stmt, 'i', $user_id);

                if (mysqli_stmt_execute($remove_stmt)) {
                    if (!empty($user['profile_image'])) {
                        $existing_path = __DIR__ . '/' . $user['profile_image'];

                        if (file_exists($existing_path)) {
                            unlink($existing_path);
                        }
                    }

                    $user['profile_image'] = '';
                    $_SESSION['user_avatar'] = '';
                    set_flash_message('success', 'Profile photo removed successfully.');
                } else {
                    error_log('Profile image remove failed: ' . mysqli_stmt_error($remove_stmt));
                    $photo_errors['general'] = 'Unable to remove profile photo right now.';
                }

                mysqli_stmt_close($remove_stmt);
            } else {
                error_log('Profile image remove prepare failed: ' . mysqli_error($conn));
                $photo_errors['general'] = 'Unable to update profile photo right now.';
            }
        } else {
            if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] === UPLOAD_ERR_NO_FILE) {
                $photo_errors['profile_image'] = 'Please select a photo to upload.';
            } elseif ($_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
                $photo_errors['profile_image'] = 'Photo upload failed. Please try again.';
            } elseif ($_FILES['profile_image']['size'] > $max_photo_size) {
                $photo_errors['profile_image'] = 'Photo size must not exceed 2MB.';
            }

            $photo_path = '';

            if (empty($photo_errors)) {
                $original_name = $_FILES['profile_image']['name'];
                $tmp_name = $_FILES['profile_image']['tmp_name'];
                $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                $image_info = @getimagesize($tmp_name);
                $mime_type = $image_info['mime'] ?? '';

                if (!is_uploaded_file($tmp_name)) {
                    $photo_errors['profile_image'] = 'Invalid upload request.';
                } elseif (!in_array($extension, $allowed_photo_extensions, true)) {
                    $photo_errors['profile_image'] = 'Only JPG, JPEG, PNG, or WebP files are allowed.';
                } elseif (!in_array($mime_type, $allowed_photo_mime_types, true)) {
                    $photo_errors['profile_image'] = 'Invalid image type.';
                } else {
                    $base_name = pathinfo($original_name, PATHINFO_FILENAME);
                    $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $base_name);
                    $safe_name = trim($safe_name, '_');

                    if ($safe_name === '') {
                        $safe_name = 'avatar';
                    }

                    $new_file_name = uniqid('avatar_', true) . '_' . $safe_name . '.' . $extension;
                    $upload_dir = __DIR__ . '/uploads/profile/';
                    $target_path = $upload_dir . $new_file_name;

                    if (!is_dir($upload_dir)) {
                        $photo_errors['profile_image'] = 'Profile upload folder does not exist.';
                    } elseif (!is_writable($upload_dir)) {
                        $photo_errors['profile_image'] = 'Profile upload folder is not writable.';
                    } elseif (!move_uploaded_file($tmp_name, $target_path)) {
                        $photo_errors['profile_image'] = 'Unable to save uploaded photo.';
                    } else {
                        $photo_path = 'uploads/profile/' . $new_file_name;
                    }
                }
            }

            if (empty($photo_errors) && $photo_path !== '') {
                $photo_sql = 'UPDATE users SET profile_image = ? WHERE id = ? LIMIT 1';
                $photo_stmt = mysqli_prepare($conn, $photo_sql);

                if ($photo_stmt) {
                    mysqli_stmt_bind_param($photo_stmt, 'si', $photo_path, $user_id);

                    if (mysqli_stmt_execute($photo_stmt)) {
                        if (!empty($user['profile_image'])) {
                            $existing_path = __DIR__ . '/' . $user['profile_image'];

                            if (file_exists($existing_path)) {
                                unlink($existing_path);
                            }
                        }

                        $user['profile_image'] = $photo_path;
                        $_SESSION['user_avatar'] = $photo_path;
                        set_flash_message('success', 'Profile photo updated successfully.');
                    } else {
                        error_log('Profile image update failed: ' . mysqli_stmt_error($photo_stmt));
                        $photo_errors['general'] = 'Unable to update profile photo right now.';

                        if (file_exists(__DIR__ . '/' . $photo_path)) {
                            unlink(__DIR__ . '/' . $photo_path);
                        }
                    }

                    mysqli_stmt_close($photo_stmt);
                } else {
                    error_log('Profile image update prepare failed: ' . mysqli_error($conn));
                    $photo_errors['general'] = 'Unable to update profile photo right now.';

                    if (file_exists(__DIR__ . '/' . $photo_path)) {
                        unlink(__DIR__ . '/' . $photo_path);
                    }
                }
            }
        }
    }

    if ($form_type === 'password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_new_password = $_POST['confirm_new_password'] ?? '';

        if ($current_password === '') {
            $password_errors['current_password'] = 'Current password is required.';
        } elseif (!password_verify($current_password, $user['password'])) {
            $password_errors['current_password'] = 'Current password is incorrect.';
        }

        if ($new_password === '') {
            $password_errors['new_password'] = 'New password is required.';
        } elseif (strlen($new_password) < 6) {
            $password_errors['new_password'] = 'New password must be at least 6 characters.';
        }

        if ($confirm_new_password === '') {
            $password_errors['confirm_new_password'] = 'Please confirm the new password.';
        } elseif ($new_password !== $confirm_new_password) {
            $password_errors['confirm_new_password'] = 'Password confirmation does not match.';
        }

        if (empty($password_errors)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $password_sql = 'UPDATE users SET password = ? WHERE id = ? LIMIT 1';
            $password_stmt = mysqli_prepare($conn, $password_sql);

            if ($password_stmt) {
                mysqli_stmt_bind_param($password_stmt, 'si', $hashed_password, $user_id);

                if (mysqli_stmt_execute($password_stmt)) {
                    set_flash_message('success', 'Password changed successfully.');
                    $user['password'] = $hashed_password;
                } else {
                    error_log('Password update failed: ' . mysqli_stmt_error($password_stmt));
                    $password_errors['general'] = 'Password update failed. Please try again.';
                }

                mysqli_stmt_close($password_stmt);
            } else {
                error_log('Password update prepare failed: ' . mysqli_error($conn));
                $password_errors['general'] = 'Password update is temporarily unavailable.';
            }
        }
    }
}

include 'includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="page-heading">
            <h1 class="h3 mb-1">Profile</h1>
            <p class="text-muted mb-0">Manage your account details and password.</p>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card app-card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <?php if (!empty($user['profile_image'])): ?>
                                <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile" class="profile-avatar">
                            <?php else: ?>
                                <div class="profile-initial profile-avatar-fallback">
                                    <?php echo htmlspecialchars(strtoupper(substr($user['name'], 0, 1))); ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <h2 class="h5 mb-1"><?php echo htmlspecialchars($user['name']); ?></h2>
                                <p class="text-muted small mb-0"><?php echo htmlspecialchars($user['email']); ?></p>
                            </div>
                        </div>

                        <dl class="mb-0">
                            <dt class="text-muted small">Role</dt>
                            <dd class="mb-3"><?php echo htmlspecialchars(ucfirst($user['role'])); ?></dd>

                            <dt class="text-muted small">Member Since</dt>
                            <dd class="mb-0"><?php echo htmlspecialchars(format_date($user['created_at'])); ?></dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card app-card mb-4">
                    <div class="card-body p-4">
                        <h2 class="h5 mb-3">Profile Photo</h2>

                        <?php if (isset($photo_errors['general'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($photo_errors['general']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="profile.php" enctype="multipart/form-data" novalidate>
                            <?php csrf_field(); ?>
                            <input type="hidden" name="form_type" value="photo">

                            <div class="mb-3">
                                <label for="profile_image" class="form-label">Upload Photo</label>
                                <input type="file" class="form-control <?php echo isset($photo_errors['profile_image']) ? 'is-invalid' : ''; ?>" id="profile_image" name="profile_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                                <div class="form-text">Allowed: JPG, JPEG, PNG, WebP. Maximum size: 2MB.</div>
                                <?php if (isset($photo_errors['profile_image'])): ?>
                                    <div class="invalid-feedback"><?php echo htmlspecialchars($photo_errors['profile_image']); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="form-actions">
                                <?php if (!empty($user['profile_image'])): ?>
                                    <button type="submit" name="photo_action" value="remove" class="btn btn-outline-danger">Remove Photo</button>
                                <?php endif; ?>
                                <button type="submit" name="photo_action" value="upload" class="btn btn-primary">Update Photo</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card app-card mb-4">
                    <div class="card-body p-4">
                        <h2 class="h5 mb-3">Account Information</h2>

                        <?php if (isset($profile_errors['general'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($profile_errors['general']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="profile.php" novalidate>
                            <?php csrf_field(); ?>
                            <input type="hidden" name="form_type" value="profile">

                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" class="form-control <?php echo isset($profile_errors['name']) ? 'is-invalid' : ''; ?>" id="name" name="name" value="<?php echo sanitize_input($name); ?>" required>
                                <?php if (isset($profile_errors['name'])): ?>
                                    <div class="invalid-feedback"><?php echo htmlspecialchars($profile_errors['name']); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-4">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control <?php echo isset($profile_errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo sanitize_input($email); ?>" required>
                                <?php if (isset($profile_errors['email'])): ?>
                                    <div class="invalid-feedback"><?php echo htmlspecialchars($profile_errors['email']); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Save Profile</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card app-card">
                    <div class="card-body p-4">
                        <h2 class="h5 mb-3">Change Password</h2>

                        <?php if (isset($password_errors['general'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($password_errors['general']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="profile.php" novalidate>
                            <?php csrf_field(); ?>
                            <input type="hidden" name="form_type" value="password">

                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control <?php echo isset($password_errors['current_password']) ? 'is-invalid' : ''; ?>" id="current_password" name="current_password" required>
                                <?php if (isset($password_errors['current_password'])): ?>
                                    <div class="invalid-feedback"><?php echo htmlspecialchars($password_errors['current_password']); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control <?php echo isset($password_errors['new_password']) ? 'is-invalid' : ''; ?>" id="new_password" name="new_password" required>
                                    <?php if (isset($password_errors['new_password'])): ?>
                                        <div class="invalid-feedback"><?php echo htmlspecialchars($password_errors['new_password']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-md-6 mb-4">
                                    <label for="confirm_new_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control <?php echo isset($password_errors['confirm_new_password']) ? 'is-invalid' : ''; ?>" id="confirm_new_password" name="confirm_new_password" required>
                                    <?php if (isset($password_errors['confirm_new_password'])): ?>
                                        <div class="invalid-feedback"><?php echo htmlspecialchars($password_errors['confirm_new_password']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Change Password</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
