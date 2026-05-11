<?php
/**
 * API Handler for Ziply Explorer
 */

session_start();
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        $path = $_GET['path'] ?? '';
        $recursive = isset($_GET['search']) && $_GET['search'] !== '';
        $show_hidden = isset($_GET['show_hidden']) && $_GET['show_hidden'] === 'true';

        if ($recursive) {
            $root = get_safe_path($path);
            if (!$root) { $root = realpath(ROOT_PATH); }

            $items = [];
            $search = strtolower($_GET['search']);

            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($it as $name => $file) {
                if (!$show_hidden && $file->getFilename()[0] === '.') continue;
                if (strpos(strtolower($file->getFilename()), $search) !== false) {
                    $items[] = [
                        'name' => $file->getFilename(),
                        'path' => substr($file->getRealPath(), strlen(realpath(ROOT_PATH)) + 1),
                        'is_dir' => $file->isDir(),
                        'size' => $file->isDir() ? 0 : $file->getSize(),
                        'modified' => $file->getMTime(),
                        'extension' => $file->isDir() ? '' : strtolower($file->getExtension()),
                        'permissions' => substr(sprintf('%o', $file->getPerms()), -4)
                    ];
                }
                if (count($items) > 100) break; // Limit results
            }
            echo json_encode(['files' => $items]);
        } else {
            $files = list_dir($path);
            if ($files === false) {
                echo json_encode(['error' => 'Invalid directory']);
            } else {
                if (!$show_hidden) {
                    $files = array_filter($files, fn($f) => $f['name'][0] !== '.');
                    $files = array_values($files);
                }
                echo json_encode(['files' => $files]);
            }
        }
        break;

    case 'delete':
        if (!check_csrf($_POST['csrf_token'] ?? '')) {
            echo json_encode(['error' => 'Invalid CSRF token']);
            break;
        }
        $items = $_POST['items'] ?? [];
        foreach ($items as $item) {
            $path = get_safe_path($item);
            if ($path) {
                delete_recursive($path);
                log_activity('Delete', 'Deleted ' . $item);
            }
        }
        echo json_encode(['success' => true]);
        break;

    case 'rename':
        if (!check_csrf($_POST['csrf_token'] ?? '')) {
            echo json_encode(['error' => 'Invalid CSRF token']);
            break;
        }
        $old_path = get_safe_path($_POST['old_path'] ?? '');
        $new_name = $_POST['new_name'] ?? '';
        if ($old_path && $new_name) {
            $new_path = dirname($old_path) . DIRECTORY_SEPARATOR . $new_name;
            if (rename($old_path, $new_path)) {
                log_activity('Rename', 'Renamed ' . $_POST['old_path'] . ' to ' . $new_name);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'Rename failed']);
            }
        }
        break;

    case 'move':
    case 'copy':
        if (!check_csrf($_POST['csrf_token'] ?? '')) {
            echo json_encode(['error' => 'Invalid CSRF token']);
            break;
        }
        $items = $_POST['items'] ?? [];
        $dest_dir = get_safe_path($_POST['dest'] ?? '');
        if ($dest_dir && is_dir($dest_dir)) {
            foreach ($items as $item) {
                $src = get_safe_path($item);
                if ($src) {
                    $dest = $dest_dir . DIRECTORY_SEPARATOR . basename($src);
                    if ($action === 'move') {
                        rename($src, $dest);
                    } else {
                        copy_recursive($src, $dest);
                    }
                }
            }
            log_activity(ucfirst($action), ucfirst($action) . 'ed ' . count($items) . ' items');
            echo json_encode(['success' => true]);
        }
        break;

    case 'zip':
        if (!check_csrf($_POST['csrf_token'] ?? '')) {
            echo json_encode(['error' => 'Invalid CSRF token']);
            break;
        }
        $items = $_POST['items'] ?? [];
        $name = $_POST['name'] ?? 'archive.zip';
        $parent = get_safe_path($_POST['path'] ?? '');
        if ($parent && !empty($items)) {
            $zip_path = $parent . DIRECTORY_SEPARATOR . $name;
            $zip = new ZipArchive();
            if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                foreach ($items as $item) {
                    $src = get_safe_path($item);
                    if ($src) {
                        if (is_dir($src)) {
                            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src), RecursiveIteratorIterator::LEAVES_ONLY);
                            foreach ($files as $f) {
                                if (!$f->isDir()) {
                                    $zip->addFile($f->getRealPath(), basename($src) . '/' . substr($f->getRealPath(), strlen($src) + 1));
                                }
                            }
                        } else {
                            $zip->addFile($src, basename($src));
                        }
                    }
                }
                $zip->close();
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'Failed to create ZIP']);
            }
        }
        break;

    case 'unzip':
        if (!check_csrf($_POST['csrf_token'] ?? '')) {
            echo json_encode(['error' => 'Invalid CSRF token']);
            break;
        }
        $path = get_safe_path($_POST['path'] ?? '');
        if ($path && is_file($path)) {
            $dest = dirname($path);
            $zip = new ZipArchive();
            if ($zip->open($path) === TRUE) {
                $zip->extractTo($dest);
                $zip->close();
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'Failed to open ZIP']);
            }
        }
        break;

    case 'mkdir':
        if (!check_csrf($_POST['csrf_token'] ?? '')) {
            echo json_encode(['error' => 'Invalid CSRF token']);
            break;
        }
        $parent = get_safe_path($_POST['path'] ?? '');
        $name = $_POST['name'] ?? '';
        if ($parent && $name) {
            $new_dir = $parent . DIRECTORY_SEPARATOR . $name;
            if (!file_exists($new_dir)) {
                mkdir($new_dir, 0755);
                log_activity('Mkdir', 'Created directory ' . $_POST['path'] . '/' . $name);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'Directory already exists']);
            }
        }
        break;

    case 'upload':
        if (!check_csrf($_POST['csrf_token'] ?? '')) {
            echo json_encode(['error' => 'Invalid CSRF token']);
            break;
        }
        $target_dir = get_safe_path($_POST['path'] ?? '');
        $blocked_extensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'phar', 'exe', 'bat', 'sh'];

        if ($target_dir && isset($_FILES['files'])) {
            foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
                $name = $_FILES['files']['name'][$key];
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                if (in_array($ext, $blocked_extensions)) {
                    continue; // Skip blocked files
                }

                $dest = $target_dir . DIRECTORY_SEPARATOR . $name;
                move_uploaded_file($tmp_name, $dest);
                log_activity('Upload', 'Uploaded file ' . $name . ' to ' . $_POST['path']);
            }
            echo json_encode(['success' => true]);
        }
        break;

    case 'get_content':
        $path = get_safe_path($_GET['path'] ?? '');
        if ($path && is_file($path)) {
            echo json_encode(['content' => file_get_contents($path)]);
        } else {
            echo json_encode(['error' => 'File not found']);
        }
        break;

    case 'save_content':
        if (!check_csrf($_POST['csrf_token'] ?? '')) {
            echo json_encode(['error' => 'Invalid CSRF token']);
            break;
        }
        $path = get_safe_path($_POST['path'] ?? '');
        $content = $_POST['content'] ?? '';
        if ($path && is_file($path)) {
            file_put_contents($path, $content);
            log_activity('Edit', 'Edited file ' . $_POST['path']);
            echo json_encode(['success' => true]);
        }
        break;

    case 'thumbnail':
        $path = $_GET['path'] ?? '';
        $thumb = generate_thumbnail($path);
        if ($thumb) {
            header('Content-Type: image/webp');
            readfile($thumb);
            exit;
        } else {
            http_response_code(404);
        }
        break;

    case 'share':
        if (!check_csrf($_POST['csrf_token'] ?? '')) {
            echo json_encode(['error' => 'Invalid CSRF token']);
            break;
        }
        $path = $_POST['path'] ?? '';
        $password = $_POST['password'] ?? '';
        $expires = $_POST['expires'] ?? '';
        $limit = (int)($_POST['limit'] ?? 0);

        $share_key = bin2hex(random_bytes(8));
        $hashed_password = $password ? password_hash($password, PASSWORD_DEFAULT) : null;

        $expires_at = null;
        if ($expires) {
            $expires_at = date('Y-m-d H:i:s', strtotime('+' . $expires));
        }

        $db = get_db_connection();
        $stmt = $db->prepare("INSERT INTO shares (file_path, share_key, password, expires_at, download_limit) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$path, $share_key, $hashed_password, $expires_at, $limit]);

        log_activity('Share', 'Created share link for ' . $path);
        echo json_encode(['success' => true, 'share_url' => 'share.php?k=' . $share_key]);
        break;

    case 'get_shares':
        $db = get_db_connection();
        $shares = $db->query("SELECT * FROM shares ORDER BY created_at DESC")->fetchAll();
        echo json_encode(['shares' => $shares]);
        break;

    case 'delete_share':
        if (!check_csrf($_POST['csrf_token'] ?? '')) {
            echo json_encode(['error' => 'Invalid CSRF token']);
            break;
        }
        $id = $_POST['id'] ?? 0;
        $db = get_db_connection();
        $stmt = $db->prepare("DELETE FROM shares WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    case 'get_recent':
        $root = realpath(ROOT_PATH);
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        $files = [];
        foreach ($it as $file) {
            if ($file->isFile()) {
                $files[] = [
                    'name' => $file->getFilename(),
                    'path' => substr($file->getRealPath(), strlen(realpath(ROOT_PATH)) + 1),
                    'is_dir' => false,
                    'size' => $file->getSize(),
                    'modified' => $file->getMTime(),
                    'extension' => strtolower($file->getExtension()),
                    'permissions' => substr(sprintf('%o', $file->getPerms()), -4)
                ];
            }
        }
        usort($files, fn($a, $b) => $b['modified'] - $a['modified']);
        echo json_encode(['files' => array_slice($files, 0, 50)]);
        break;

    case 'toggle_favorite':
        if (!check_csrf($_POST['csrf_token'] ?? '')) {
            echo json_encode(['error' => 'Invalid CSRF token']);
            break;
        }
        $path = $_POST['path'] ?? '';
        if ($path) {
            $db = get_db_connection();
            $stmt = $db->prepare("SELECT id FROM favorites WHERE file_path = ?");
            $stmt->execute([$path]);
            if ($stmt->fetch()) {
                $stmt = $db->prepare("DELETE FROM favorites WHERE file_path = ?");
                $stmt->execute([$path]);
                echo json_encode(['success' => true, 'status' => 'removed']);
            } else {
                $stmt = $db->prepare("INSERT INTO favorites (file_path) VALUES (?)");
                $stmt->execute([$path]);
                echo json_encode(['success' => true, 'status' => 'added']);
            }
        }
        break;

    case 'get_favorites':
        $db = get_db_connection();
        $favs = $db->query("SELECT file_path FROM favorites")->fetchAll(PDO::FETCH_COLUMN);
        $items = [];
        foreach ($favs as $path) {
            $real = get_safe_path($path);
            if ($real && file_exists($real)) {
                $items[] = [
                    'name' => basename($real),
                    'path' => $path,
                    'is_dir' => is_dir($real),
                    'size' => is_dir($real) ? 0 : filesize($real),
                    'modified' => filemtime($real),
                    'extension' => is_dir($real) ? '' : strtolower(pathinfo($real, PATHINFO_EXTENSION)),
                    'permissions' => substr(sprintf('%o', fileperms($real)), -4)
                ];
            } else {
                $stmt = $db->prepare("DELETE FROM favorites WHERE file_path = ?");
                $stmt->execute([$path]);
            }
        }
        echo json_encode(['files' => $items]);
        break;

    case 'stats':
        $root = realpath(ROOT_PATH);
        $total_size = 0;
        $file_count = 0;
        $dir_count = 0;

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $name => $file) {
            if ($file->isDir()) {
                $dir_count++;
            } else {
                $file_count++;
                $total_size += $file->getSize();
            }
        }

        $free_space = disk_free_space($root);
        $total_space = disk_total_space($root);
        $used_space = $total_space - $free_space;
        $percent = round(($used_space / $total_space) * 100, 2);

        echo json_encode([
            'total_size' => format_size($total_size),
            'file_count' => $file_count,
            'dir_count' => $dir_count,
            'php_version' => PHP_VERSION,
            'max_upload' => ini_get('upload_max_filesize'),
            'disk_total' => format_size($total_space),
            'disk_used' => format_size($used_space),
            'disk_percent' => $percent
        ]);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
        break;
}
