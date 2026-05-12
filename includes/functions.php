<?php
/**
 * File System functions
 */

/**
 * Get real path and ensure it's within root directory
 */
function get_safe_path($path) {
    $root = realpath(ROOT_PATH);
    if (!$root) return false;

    // Normalize path
    $path = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);
    $path = trim($path, DIRECTORY_SEPARATOR);

    $full_path = realpath($root . DIRECTORY_SEPARATOR . $path);

    if ($path === '') {
        $full_path = $root;
    }

    if ($full_path === false || strpos($full_path, $root) !== 0) {
        return false;
    }
    return $full_path;
}

/**
 * List files and directories
 */
function list_dir($dir_path) {
    $real_path = get_safe_path($dir_path);
    if ($real_path === false || !is_dir($real_path)) {
        return false;
    }

    $items = [];
    $files = scandir($real_path);

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;

        // Hide internal files
        if ($dir_path === '' || $dir_path === '.') {
            if (in_array($file, ['config.php', 'data', 'includes', 'assets', 'install.php', 'index.php', 'login.php', 'logout.php', 'api.php', 'download.php', 'settings.php', 'share.php', '.git', 'README.md', 'php_server.log', 'hello.txt'])) {
                continue;
            }
        }

        $path = $real_path . DIRECTORY_SEPARATOR . $file;
        $stat = @stat($path);
        if (!$stat) continue;

        $items[] = [
            'name' => $file,
            'path' => ($dir_path ? trim($dir_path, '/') . '/' : '') . $file,
            'is_dir' => is_dir($path),
            'size' => is_dir($path) ? 0 : $stat['size'],
            'modified' => $stat['mtime'],
            'extension' => is_dir($path) ? '' : strtolower(pathinfo($file, PATHINFO_EXTENSION)),
            'permissions' => substr(sprintf('%o', fileperms($path)), -4)
        ];
    }

    return $items;
}

/**
 * Format bytes to human readable size
 */
function format_size($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * Recursive delete
 */
function delete_recursive($path) {
    if (is_dir($path)) {
        $files = scandir($path);
        foreach ($files as $file) {
            if ($file != "." && $file != "..") {
                delete_recursive($path . DIRECTORY_SEPARATOR . $file);
            }
        }
        return rmdir($path);
    } elseif (file_exists($path)) {
        return unlink($path);
    }
    return false;
}

/**
 * Recursive copy
 */
function copy_recursive($source, $dest) {
    if (is_dir($source)) {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }
        $files = scandir($source);
        foreach ($files as $file) {
            if ($file != "." && $file != "..") {
                copy_recursive($source . DIRECTORY_SEPARATOR . $file, $dest . DIRECTORY_SEPARATOR . $file);
            }
        }
        return true;
    } elseif (file_exists($source)) {
        return copy($source, $dest);
    }
    return false;
}

/**
 * Thumbnail generation
 */
function generate_thumbnail($file_path, $size = 200) {
    $real_path = get_safe_path($file_path);
    if (!$real_path || !file_exists($real_path)) return false;

    $ext = strtolower(pathinfo($real_path, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!in_array($ext, $allowed)) return false;

    $cache_dir = __DIR__ . '/../data/thumbnails';
    if (!is_dir($cache_dir)) mkdir($cache_dir, 0755, true);

    $cache_file = $cache_dir . '/' . md5($real_path . filemtime($real_path)) . '.webp';

    if (file_exists($cache_file)) return $cache_file;

    $img_info = @getimagesize($real_path);
    if (!$img_info) {
        error_log("Thumbnail failed: getimagesize returned false for $real_path");
        return false;
    }
    list($width, $height) = $img_info;

    // Safety check for large images
    if ($width * $height > 10000000) { // 10MP limit
        error_log("Thumbnail skipped: Image too large ($width x $height)");
        return false;
    }

    $ratio = $width / $height;

    if ($width > $height) {
        $new_width = $size;
        $new_height = $size / $ratio;
    } else {
        $new_height = $size;
        $new_width = $size * $ratio;
    }

    $src = @imagecreatefromstring(file_get_contents($real_path));

    if (!$src) {
        error_log("Thumbnail failed: Could not create image from source ($ext) $real_path");
        return false;
    }

    $dst = imagecreatetruecolor($new_width, $new_height);

    // Preserve transparency
    if ($ext == 'png' || $ext == 'gif' || $ext == 'webp') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

    $tmp_file = $cache_file . '.' . uniqid() . '.tmp';
    imagewebp($dst, $tmp_file, 80);
    if (file_exists($tmp_file)) {
        rename($tmp_file, $cache_file);
    }

    imagedestroy($src);
    imagedestroy($dst);

    return $cache_file;
}

/**
 * ZIP Directory
 */
function zip_directory($source_path, $zip_file) {
    $root = get_safe_path($source_path);
    if (!$root) return false;

    $zip = new ZipArchive();
    if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        return false;
    }

    if (is_dir($root)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($root) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
    } else {
        $zip->addFile($root, basename($root));
    }

    return $zip->close();
}
