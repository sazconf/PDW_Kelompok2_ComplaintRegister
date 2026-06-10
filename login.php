<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

start_secure_session();

if (is_logged_in()) {
    if (is_admin()) {
        redirect('/complaint-system/admin/dashboard.php');
    }

    redirect('/complaint-system/index.php');
}

$page_title = 'Login - Yogyakarta City Complaint Register';
$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '') {
        $errors['email'] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if ($password === '') {
        $errors['password'] = 'Password is required.';
    }

    if (empty($errors)) {
        $sql = 'SELECT id, name, email, password, role, profile_image FROM users WHERE email = ? LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_avatar'] = $user['profile_image'] ?? '';

                mysqli_stmt_close($stmt);

                if ($user['role'] === 'admin') {
                    redirect('/complaint-system/admin/dashboard.php');
                }

                redirect('/complaint-system/index.php');
            }

            $errors['general'] = 'Invalid email or password.';
            mysqli_stmt_close($stmt);
        } else {
            error_log('Login prepare failed: ' . mysqli_error($conn));
            $errors['general'] = 'Login is temporarily unavailable. Please try again later.';
        }
    }
}

include 'includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5">
                <div class="card app-card">
                    <div class="card-body p-4">
                        <div class="text-center mb-3">
                            <span class="brand-mark">
                                <img src="/complaint-system/logo/logo.png" alt="Yogyakarta City Complaint Register logo" class="brand-logo">
                            </span>
                        </div>
                        <h1 class="h3 text-center mb-4">Login</h1>

                        <?php if (isset($errors['general'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($errors['general']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="login.php" novalidate>
                            <?php csrf_field(); ?>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo sanitize_input($email); ?>" placeholder="name@example.com" required>
                                <?php if (isset($errors['email'])): ?>
                                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['email']); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" id="password" name="password" placeholder="Enter your password" required>
                                <?php if (isset($errors['password'])): ?>
                                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['password']); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Login</button>
                            </div>
                        </form>

                        <p class="text-center text-muted mt-3 mb-0">
                            Do not have an account?
                            <a href="register.php">Register</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
