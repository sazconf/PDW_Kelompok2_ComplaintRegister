<?php
function start_secure_session()
{
    if (session_status() === PHP_SESSION_NONE) {
        $session_path = __DIR__ . '/../sessions';

        if (is_dir($session_path) && is_writable($session_path)) {
            session_save_path($session_path);
        }

        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        if (!@session_start()) {
            error_log('Session could not be started.');
            die('Session error. Please refresh the page and try again.');
        }
    }
}

function sanitize_input($value)
{
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

function get_csrf_token()
{
    start_secure_session();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    $token = get_csrf_token();
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

function validate_csrf_token()
{
    start_secure_session();
    $token = $_POST['csrf_token'] ?? '';

    return is_string($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function require_valid_csrf()
{
    if (!validate_csrf_token()) {
        http_response_code(403);
        die('Invalid request token. Please refresh and try again.');
    }
}

function set_flash_message($type, $message)
{
    start_secure_session();
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

function get_flash_message()
{
    start_secure_session();
    $message = $_SESSION['flash_message'] ?? null;
    unset($_SESSION['flash_message']);

    return $message;
}

function clear_authenticated_session()
{
    start_secure_session();
    unset(
        $_SESSION['user_id'],
        $_SESSION['user_name'],
        $_SESSION['user_role'],
        $_SESSION['user_avatar']
    );
}

function is_logged_in()
{
    start_secure_session();

    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    global $conn;

    if (!isset($conn) || !($conn instanceof mysqli)) {
        return true;
    }

    $user_id = (int) $_SESSION['user_id'];
    $sql = 'SELECT name, role, profile_image FROM users WHERE id = ? LIMIT 1';

    try {
        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {
            return true;
        }

        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
    } catch (mysqli_sql_exception $exception) {
        error_log('Session user validation failed: ' . $exception->getMessage());
        return true;
    }

    if (!$user) {
        clear_authenticated_session();
        return false;
    }

    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_avatar'] = $user['profile_image'] ?? '';

    return true;
}

function is_admin()
{
    return is_logged_in() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function require_login()
{
    if (!is_logged_in()) {
        redirect('/complaint-system/login.php');
    }
}

function require_admin()
{
    require_login();

    if (!is_admin()) {
        redirect('/complaint-system/index.php');
    }
}

function get_status_badge_class($status)
{
    if ($status === 'In Progress') {
        return 'bg-warning text-dark';
    }

    if ($status === 'Resolved') {
        return 'bg-success';
    }

    if ($status === 'Rejected') {
        return 'bg-danger';
    }

    return 'bg-secondary';
}

function format_datetime($value)
{
    if (empty($value)) {
        return '-';
    }

    return date('d M Y, H:i', strtotime($value));
}

function format_date($value)
{
    if (empty($value)) {
        return '-';
    }

    return date('d M Y', strtotime($value));
}

function log_admin_action($conn, $action, $details, $report_id = null)
{
    if (!is_admin()) {
        return;
    }

    $admin_id = (int) ($_SESSION['user_id'] ?? 0);
    if ($admin_id <= 0) {
        return;
    }

    $sql = 'INSERT INTO audit_logs (admin_id, report_id, action, details) VALUES (?, ?, ?, ?)';
    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {
        $report_value = $report_id ? (int) $report_id : null;
        mysqli_stmt_bind_param($stmt, 'iiss', $admin_id, $report_value, $action, $details);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

function add_status_history($conn, $report_id, $status, $note = null)
{
    $sql = 'INSERT INTO report_status_history (report_id, status, note) VALUES (?, ?, ?)';
    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'iss', $report_id, $status, $note);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

function add_notification($conn, $user_id, $title, $message)
{
    $sql = 'INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)';
    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'iss', $user_id, $title, $message);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}
?>
