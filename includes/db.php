<?php
// Database connection and helper functions

function get_db_connection() {
    static $db = null;
    if ($db === null) {
        try {
            $db = new PDO('sqlite:' . DB_PATH);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    return $db;
}

function log_activity($action, $details = '') {
    $db = get_db_connection();
    $stmt = $db->prepare("INSERT INTO activity_log (action, details, ip_address) VALUES (?, ?, ?)");
    $stmt->execute([$action, $details, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
}
