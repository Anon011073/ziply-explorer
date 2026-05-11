<?php
session_start();

if (file_exists('config.php')) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $site_name = $_POST['site_name'] ?? 'Ziply Explorer';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $root_path = $_POST['root_path'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username and password are required.';
    } else {
        // Sanitize root path
        $root_path = rtrim(str_replace('\\', '/', $root_path), '/');
        if (empty($root_path)) {
            $root_path = str_replace('\\', '/', __DIR__);
        }

        // Create config.php
        $config_content = "<?php\n";
        $config_content .= "define('SITE_NAME', " . var_export($site_name, true) . ");\n";
        $config_content .= "define('ROOT_PATH', " . var_export($root_path, true) . ");\n";
        $config_content .= "define('DB_PATH', __DIR__ . '/data/database.sqlite');\n";
        $config_content .= "define('APP_KEY', " . var_export(bin2hex(random_bytes(32)), true) . ");\n";

        if (file_put_contents('config.php', $config_content)) {
            // Initialize SQLite database
            try {
                if (!is_dir('data')) {
                    mkdir('data', 0755, true);
                }
                $db = new PDO('sqlite:data/database.sqlite');
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $db->exec("CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username TEXT UNIQUE,
                    password TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");

                $db->exec("CREATE TABLE IF NOT EXISTS shares (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    file_path TEXT,
                    share_key TEXT UNIQUE,
                    password TEXT,
                    expires_at DATETIME,
                    download_limit INTEGER DEFAULT 0,
                    download_count INTEGER DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");

                $db->exec("CREATE TABLE IF NOT EXISTS activity_log (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    action TEXT,
                    details TEXT,
                    ip_address TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");

                $db->exec("CREATE TABLE IF NOT EXISTS favorites (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    file_path TEXT UNIQUE,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");

                // Insert admin user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                $stmt->execute([$username, $hashed_password]);

                $success = true;
            } catch (Exception $e) {
                $error = 'Database error: ' . $e->getMessage();
                unlink('config.php');
            }
        } else {
            $error = 'Failed to create config.php. Check permissions.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install - Ziply Explorer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background-color: #0f172a; color: #f1f5f9; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md p-8 bg-slate-800 rounded-xl shadow-2xl border border-slate-700">
        <h1 class="text-3xl font-bold mb-6 text-center text-blue-400">Ziply Explorer</h1>

        <?php if ($success): ?>
            <div class="bg-green-900/50 border border-green-500 text-green-200 p-4 rounded mb-6 text-sm">
                Installation successful! You can now log in.
            </div>
            <a href="login.php" class="block w-full text-center bg-blue-600 hover:bg-blue-500 text-white font-bold py-2 px-4 rounded transition">Go to Login</a>
        <?php else: ?>
            <p class="mb-6 text-slate-400 text-center">Set up your admin account and configuration.</p>

            <?php if ($error): ?>
                <div class="bg-red-900/50 border border-red-500 text-red-200 p-4 rounded mb-6 text-sm">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Site Name</label>
                    <input type="text" name="site_name" value="Ziply Explorer" class="w-full bg-slate-900 border border-slate-700 rounded p-2 focus:outline-none focus:border-blue-500 text-white">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Root Directory Path</label>
                    <input type="text" name="root_path" value="<?php echo str_replace('\\', '/', __DIR__); ?>" placeholder="e.g. C:/laragon/www/ or /var/www/html/" class="w-full bg-slate-900 border border-slate-700 rounded p-2 focus:outline-none focus:border-blue-500 text-white">
                    <p class="text-xs text-slate-500 mt-1">Leave as is for current directory. Use forward slashes.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Admin Username</label>
                    <input type="text" name="username" required class="w-full bg-slate-900 border border-slate-700 rounded p-2 focus:outline-none focus:border-blue-500 text-white">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Admin Password</label>
                    <input type="password" name="password" required class="w-full bg-slate-900 border border-slate-700 rounded p-2 focus:outline-none focus:border-blue-500 text-white">
                </div>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-2 px-4 rounded transition mt-6">Install Now</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
