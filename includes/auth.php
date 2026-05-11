<?php
// Authentication logic

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function authenticate($username, $password) {
    $db = get_db_connection();
    $stmt = $db->prepare("SELECT id, username, password FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        log_activity('Login', 'User ' . $username . ' logged in successfully.');
        return true;
    }

    log_activity('Login Failed', 'Attempted login for username: ' . $username);
    return false;
}

function logout() {
    log_activity('Logout', 'User ' . ($_SESSION['username'] ?? 'unknown') . ' logged out.');
    session_unset();
    session_destroy();
}

function check_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
