<?php
/**
 * Public Share Page
 */

if (!file_exists('config.php')) {
    die("Not installed");
}

require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$key = $_GET['k'] ?? '';

if (empty($key)) {
    die("Invalid share link");
}

$db = get_db_connection();
$stmt = $db->prepare("SELECT * FROM shares WHERE share_key = ?");
$stmt->execute([$key]);
$share = $stmt->fetch();

if (!$share) {
    die("Share not found");
}

// Check expiration
if ($share['expires_at'] && strtotime($share['expires_at']) < time()) {
    die("Share has expired");
}

// Check download limit
if ($share['download_limit'] > 0 && $share['download_count'] >= $share['download_limit']) {
    die("Download limit reached");
}

$file_path = get_safe_path($share['file_path']);
if (!$file_path || !file_exists($file_path)) {
    die("File no longer exists");
}

// Handle password protection
if ($share['password']) {
    session_start();
    if (!isset($_SESSION['share_auth'][$key])) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
            if (password_verify($_POST['password'], $share['password'])) {
                $_SESSION['share_auth'][$key] = true;
            } else {
                $error = "Incorrect password";
            }
        }

        if (!isset($_SESSION['share_auth'][$key])) {
            // Show password form
            ?>
            <!DOCTYPE html>
            <html lang="en" class="dark">
            <head>
                <meta charset="UTF-8"><title>Password Protected - <?php echo SITE_NAME; ?></title>
                <script src="https://cdn.tailwindcss.com"></script>
            </head>
            <body class="bg-slate-900 text-white flex items-center justify-center min-h-screen">
                <div class="w-full max-w-md p-8 bg-slate-800 rounded-xl shadow-2xl border border-slate-700">
                    <h1 class="text-2xl font-bold mb-6 text-center">Protected Share</h1>
                    <?php if (isset($error)) echo "<p class='text-red-500 mb-4'>$error</p>"; ?>
                    <form method="POST" class="space-y-4">
                        <input type="password" name="password" placeholder="Enter password" class="w-full bg-slate-900 border border-slate-700 rounded p-2 text-white">
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 py-2 rounded font-bold">Access File</button>
                    </form>
                </div>
            </body>
            </html>
            <?php
            exit;
        }
    }
}

// Handle download
if (isset($_GET['download'])) {
    // Update count
    $stmt = $db->prepare("UPDATE shares SET download_count = download_count + 1 WHERE id = ?");
    $stmt->execute([$share['id']]);

    if (is_dir($file_path)) {
        $zip_name = basename($file_path) . '.zip';
        $zip_path = sys_get_temp_dir() . '/' . $zip_name;
        if (zip_directory($file_path, $zip_path)) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zip_name . '"');
            readfile($zip_path);
            unlink($zip_path);
            exit;
        }
    } else {
        header('Content-Type: ' . mime_content_type($file_path));
        header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
        readfile($file_path);
        exit;
    }
}

// Show share page
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Share - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-950 text-slate-100 flex items-center justify-center min-h-screen p-4">
    <div class="w-full max-w-lg bg-slate-900 border border-slate-800 rounded-3xl shadow-2xl overflow-hidden">
        <div class="p-8 text-center space-y-6">
            <div class="w-20 h-20 bg-blue-600/20 text-blue-500 rounded-2xl flex items-center justify-center mx-auto">
                <i data-lucide="<?php echo is_dir($file_path) ? 'folder' : 'file'; ?>" class="w-10 h-10"></i>
            </div>

            <div>
                <h1 class="text-2xl font-bold mb-2"><?php echo htmlspecialchars(basename($file_path)); ?></h1>
                <p class="text-slate-400 text-sm">
                    <?php echo is_dir($file_path) ? 'Folder' : format_size(filesize($file_path)); ?> •
                    Shared by <?php echo SITE_NAME; ?>
                </p>
            </div>

            <div class="pt-4">
                <a href="?k=<?php echo $key; ?>&download=1" class="block w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-4 rounded-2xl transition-all shadow-lg shadow-blue-600/20 flex items-center justify-center gap-3">
                    <i data-lucide="download" class="w-5 h-5"></i>
                    Download Now
                </a>
            </div>

            <?php if ($share['expires_at']): ?>
                <p class="text-xs text-slate-500">Expires on: <?php echo $share['expires_at']; ?></p>
            <?php endif; ?>
        </div>

        <div class="bg-slate-800/50 p-4 border-t border-slate-800 text-center">
            <p class="text-xs text-slate-500">Securely shared via Ziply Explorer</p>
        </div>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
