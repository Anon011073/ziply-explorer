<?php
/**
 * Download Handler
 */

session_start();
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

if (!is_logged_in()) {
    die("Unauthorized");
}

$path = get_safe_path($_GET['path'] ?? '');

if ($path && file_exists($path)) {
    if (is_dir($path)) {
        // Handle ZIP download for directory
        $zip_name = basename($path) . '.zip';
        $zip_path = sys_get_temp_dir() . '/' . $zip_name;

        if (zip_directory($path, $zip_path)) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zip_name . '"');
            header('Content-Length: ' . filesize($zip_path));
            readfile($zip_path);
            unlink($zip_path);
            log_activity('Download ZIP', 'Downloaded ' . $_GET['path'] . ' as ZIP');
            exit;
        } else {
            die("Failed to create ZIP");
        }
    } else {
        // Stream file
        $mime = mime_content_type($path);
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($path));
        log_activity('Download', 'Downloaded ' . $_GET['path']);
        readfile($path);
        exit;
    }
} else {
    die("File not found");
}
