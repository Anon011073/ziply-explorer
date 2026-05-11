<?php
/**
 * Admin Settings Page
 */

session_start();
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token';
    } else {
        $site_name = $_POST['site_name'] ?? SITE_NAME;
        $root_path = $_POST['root_path'] ?? ROOT_PATH;

        // Update config.php
        $config_content = "<?php\n";
        $config_content .= "define('SITE_NAME', " . var_export($site_name, true) . ");\n";
        $config_content .= "define('ROOT_PATH', " . var_export($root_path, true) . ");\n";
        $config_content .= "define('DB_PATH', " . var_export(DB_PATH, true) . ");\n";
        $config_content .= "define('APP_KEY', " . var_export(APP_KEY, true) . ");\n";

        if (file_put_contents('config.php', $config_content)) {
            $success = 'Settings updated successfully.';
            log_activity('Settings', 'Updated site settings.');
        } else {
            $error = 'Failed to update config.php. Check permissions.';
        }
    }
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <title>Settings - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen">
    <div class="max-w-4xl mx-auto py-12 px-6">
        <div class="flex items-center gap-4 mb-8">
            <a href="index.php" class="p-2 hover:bg-slate-800 rounded-lg transition-colors"><i data-lucide="arrow-left" class="w-6 h-6"></i></a>
            <h1 class="text-3xl font-bold">Admin Settings</h1>
        </div>

        <?php if ($success): ?>
            <div class="bg-green-900/50 border border-green-500 text-green-200 p-4 rounded-xl mb-6">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-900/50 border border-red-500 text-red-200 p-4 rounded-xl mb-6">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="md:col-span-2 space-y-6">
                <form method="POST" class="bg-slate-800 border border-slate-700 rounded-2xl p-6 space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div>
                        <label class="block text-sm font-medium text-slate-400 mb-2">Site Name</label>
                        <input type="text" name="site_name" value="<?php echo htmlspecialchars(SITE_NAME); ?>" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-3 focus:outline-none focus:border-blue-500 text-white">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-400 mb-2">Root Directory Path</label>
                        <input type="text" name="root_path" value="<?php echo htmlspecialchars(ROOT_PATH); ?>" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-3 focus:outline-none focus:border-blue-500 text-white">
                        <p class="text-xs text-slate-500 mt-2">Example: C:/laragon/www/ or /var/www/html/. Use forward slashes.</p>
                    </div>

                    <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 px-6 rounded-xl transition-colors">Save Settings</button>
                </form>

                <div class="bg-slate-800 border border-slate-700 rounded-2xl p-6">
                    <h2 class="text-xl font-bold mb-4">Recent Activity</h2>
                    <div class="space-y-4">
                        <?php
                        $db = get_db_connection();
                        $logs = $db->query("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 10")->fetchAll();
                        foreach ($logs as $log): ?>
                            <div class="flex items-center justify-between text-sm border-b border-slate-700 pb-3 last:border-0 last:pb-0">
                                <div>
                                    <p class="font-medium"><?php echo htmlspecialchars($log['action']); ?></p>
                                    <p class="text-xs text-slate-500"><?php echo htmlspecialchars($log['details']); ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-slate-400"><?php echo $log['created_at']; ?></p>
                                    <p class="text-[10px] text-slate-600"><?php echo $log['ip_address']; ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-slate-800 border border-slate-700 rounded-2xl p-6 text-center">
                    <h2 class="text-sm font-medium text-slate-400 mb-4 uppercase tracking-wider">System Info</h2>
                    <div class="space-y-4">
                        <div class="bg-slate-900/50 rounded-xl p-4">
                            <p class="text-xs text-slate-500 mb-1">PHP Version</p>
                            <p class="text-xl font-bold"><?php echo PHP_VERSION; ?></p>
                        </div>
                        <div class="bg-slate-900/50 rounded-xl p-4">
                            <p class="text-xs text-slate-500 mb-1">Max Upload</p>
                            <p class="text-xl font-bold"><?php echo ini_get('upload_max_filesize'); ?></p>
                        </div>
                        <div class="bg-slate-900/50 rounded-xl p-4">
                            <p class="text-xs text-slate-500 mb-1">SQLite</p>
                            <p class="text-xl font-bold">Enabled</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
