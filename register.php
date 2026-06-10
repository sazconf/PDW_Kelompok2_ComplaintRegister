<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

start_secure_session();

if (is_logged_in()) {
    redirect('/complaint-system/index.php');
}

$page_title = 'Register - Yogyakarta City Complaint Register';
$errors = [];
$success_message = '';
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($name === '') {
        $errors['name'] = 'Name is required.';
    }

    if ($email === '') {
        $errors['email'] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if ($password === '') {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters.';
    }

    if ($confirm_password === '') {
        $errors['confirm_password'] = 'Please confirm your password.';
    } elseif ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Password confirmation does not match.';
    }

    if (empty($errors)) {
        $check_sql = 'SELECT id FROM users WHERE email = ? LIMIT 1';
        $check_stmt = mysqli_prepare($conn, $check_sql);

        if ($check_stmt) {
            mysqli_stmt_bind_param($check_stmt, 's', $email);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);

            if (mysqli_stmt_num_rows($check_stmt) > 0) {
                $errors['email'] = 'This email is already registered.';
            }

            mysqli_stmt_close($check_stmt);
        } else {
            error_log('Register email check prepare failed: ' . mysqli_error($conn));
            $errors['general'] = 'Registration is temporarily unavailable. Please try again later.';
        }
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $role = 'user';
        $insert_sql = 'INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)';
        $insert_stmt = mysqli_prepare($conn, $insert_sql);

        if ($insert_stmt) {
            mysqli_stmt_bind_param($insert_stmt, 'ssss', $name, $email, $hashed_password, $role);

            if (mysqli_stmt_execute($insert_stmt)) {
                set_flash_message('success', 'Registration successful. You can now log in.');
                mysqli_stmt_close($insert_stmt);
                redirect('/complaint-system/login.php');
            } else {
                error_log('Register insert failed: ' . mysqli_stmt_error($insert_stmt));

                if (mysqli_errno($conn) === 1062) {
                    $errors['email'] = 'This email is already registered.';
                } else {
                    $errors['general'] = 'Registration failed. Please try again.';
                }
            }

            mysqli_stmt_close($insert_stmt);
        } else {
            error_log('Register insert prepare failed: ' . mysqli_error($conn));
            $errors['general'] = 'Registration is temporarily unavailable. Please try again later.';
        }
    }
}

include 'includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card app-card">
                    <div class="card-body p-4">
                        <div class="text-center mb-3">
                            <span class="brand-mark">
                                <img src="/complaint-system/logo/logo.png" alt="Yogyakarta City Complaint Register logo" class="brand-logo">
                            </span>
                        </div>
                        <h1 class="h3 text-center mb-4">Create Account</h1>

                        <?php if (isset($errors['general'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($errors['general']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="register.php" novalidate>
                            <?php csrf_field(); ?>
                            <div class="mb-3">
                                <label for="name" class="form-label">Name</label>
                                <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" id="name" name="name" value="<?php echo sanitize_input($name); ?>" placeholder="Enter your full name" required>
                                <?php if (isset($errors['name'])): ?>
                                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['name']); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo sanitize_input($email); ?>" placeholder="name@example.com" required>
                                <?php if (isset($errors['email'])): ?>
                                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['email']); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" id="password" name="password" placeholder="Minimum 6 characters" required>
                                <?php if (isset($errors['password'])): ?>
                                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['password']); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" id="confirm_password" name="confirm_password" placeholder="Repeat your password" required>
                                <?php if (isset($errors['confirm_password'])): ?>
                                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['confirm_password']); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Register</button>
                            </div>
                        </form>

                        <p class="text-center text-muted mt-3 mb-0">
                            Already have an account?
                            <a href="login.php">Login</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
