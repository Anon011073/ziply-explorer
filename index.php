<?php
/**
 * Ziply Explorer - Modern Web-based File Manager
 */

session_start();

if (!file_exists('config.php')) {
    header('Location: install.php');
    exit;
}

require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if logged in
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        dark: {
                            bg: '#0f172a',
                            surface: '#1e293b',
                            border: '#334155',
                            text: '#f1f5f9',
                            muted: '#94a3b8'
                        }
                    }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/theme/ayu-mirage.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/theme/dracula.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/theme/monokai.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/theme/material.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/theme/nord.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/php/php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/markdown/markdown.min.js"></script>
</head>
<body class="bg-slate-50 dark:bg-dark-bg text-slate-900 dark:text-dark-text min-h-screen flex flex-col transition-colors duration-300">
    <input type="hidden" id="csrf_token" value="<?php echo $csrf_token; ?>">

    <!-- Header -->
    <header class="h-16 border-b border-slate-200 dark:border-dark-border flex items-center justify-between px-6 bg-white dark:bg-dark-surface sticky top-0 z-30">
        <div class="flex items-center gap-4">
            <button id="sidebar-toggle" class="lg:hidden p-2 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg">
                <i data-lucide="menu" class="w-5 h-5"></i>
            </button>
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                    <i data-lucide="folder" class="w-5 h-5 text-white"></i>
                </div>
                <h1 class="font-bold text-xl hidden sm:block"><?php echo SITE_NAME; ?></h1>
            </div>
        </div>

        <div class="flex-1 max-w-xl mx-8 hidden md:block">
            <div class="relative">
                <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" id="search-input" placeholder="Search files..." class="w-full bg-slate-100 dark:bg-slate-800 border-none rounded-xl py-2 pl-10 pr-4 focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button id="theme-toggle" class="p-2 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg">
                <i data-lucide="moon" class="w-5 h-5 dark:hidden"></i>
                <i data-lucide="sun" class="w-5 h-5 hidden dark:block"></i>
            </button>
            <div class="h-8 w-px bg-slate-200 dark:border-dark-border mx-2"></div>
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium hidden sm:block"><?php echo $_SESSION['username']; ?></span>
                <a href="logout.php" class="p-2 hover:bg-red-50 dark:hover:bg-red-900/20 text-red-600 rounded-lg transition-colors">
                    <i data-lucide="log-out" class="w-5 h-5"></i>
                </a>
            </div>
        </div>
    </header>

    <div class="flex flex-1 overflow-hidden">
        <!-- Sidebar -->
        <aside id="sidebar" class="w-64 border-r border-slate-200 dark:border-dark-border bg-white dark:bg-dark-surface hidden lg:flex flex-col fixed lg:static inset-y-0 left-0 z-40">
            <div class="p-4 space-y-1">
                <button data-nav="root" class="nav-item active w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-blue-600 bg-blue-50 dark:bg-blue-900/20" onclick="location.reload()">
                    <i data-lucide="home" class="w-4 h-4"></i>
                    All Files
                </button>
                <button data-nav="favorites" class="nav-item w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-slate-600 dark:text-dark-muted hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                    <i data-lucide="star" class="w-4 h-4"></i>
                    Favorites
                </button>
                <button data-nav="recent" id="nav-recent" class="nav-item w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-slate-600 dark:text-dark-muted hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                    <i data-lucide="clock" class="w-4 h-4"></i>
                    Recent
                </button>
                <button data-nav="shares" id="nav-shares" class="nav-item w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-slate-600 dark:text-dark-muted hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                    <i data-lucide="share-2" class="w-4 h-4"></i>
                    Shared Links
                </button>
                <a href="settings.php" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-slate-600 dark:text-dark-muted hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                    <i data-lucide="settings" class="w-4 h-4"></i>
                    Settings
                </a>
            </div>

            <div class="mt-auto p-4 border-t border-slate-200 dark:border-dark-border">
                <div class="mb-2 flex items-center justify-between text-xs font-medium text-slate-500">
                    <span>Storage</span>
                    <span id="storage-percent">0%</span>
                </div>
                <div class="w-full h-1.5 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                    <div id="storage-bar" class="h-full bg-blue-600" style="width: 0%"></div>
                </div>
                <p id="storage-text" class="text-[10px] text-slate-400 mt-2">0 GB of 0 GB used</p>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col min-w-0 bg-slate-50 dark:bg-dark-bg overflow-hidden">
            <!-- Toolbar -->
            <div class="h-14 border-b border-slate-200 dark:border-dark-border bg-white dark:bg-dark-surface flex items-center justify-between px-6 z-20">
                <nav id="breadcrumbs" class="flex items-center text-sm font-medium text-slate-500 overflow-x-auto whitespace-nowrap scrollbar-hide">
                    <!-- Breadcrumbs will be injected here -->
                </nav>

                <div class="flex items-center gap-2">
                    <button id="btn-upload" class="flex items-center gap-2 bg-blue-600 hover:bg-blue-500 text-white px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors">
                        <i data-lucide="upload" class="w-4 h-4"></i>
                        <span class="hidden sm:inline">Upload</span>
                    </button>
                    <button id="btn-new-folder" class="p-2 hover:bg-slate-200 dark:hover:bg-slate-800 rounded-lg text-slate-600 dark:text-dark-muted" title="New Folder">
                        <i data-lucide="folder-plus" class="w-4 h-4"></i>
                    </button>
                    <div class="w-px h-6 bg-slate-200 dark:bg-dark-border mx-1"></div>
                    <button id="view-grid" class="p-2 bg-slate-200 dark:bg-slate-800 rounded-lg text-blue-600" title="Grid View">
                        <i data-lucide="layout-grid" class="w-4 h-4"></i>
                    </button>
                    <button id="view-list" class="p-2 hover:bg-slate-200 dark:hover:bg-slate-800 rounded-lg text-slate-600 dark:text-dark-muted" title="List View">
                        <i data-lucide="list" class="w-4 h-4"></i>
                    </button>
                    <button id="btn-toggle-hidden" class="p-2 hover:bg-slate-200 dark:hover:bg-slate-800 rounded-lg text-slate-600 dark:text-dark-muted" title="Toggle Hidden Files">
                        <i data-lucide="eye-off" id="icon-hidden" class="w-4 h-4"></i>
                    </button>
                    <div class="w-px h-6 bg-slate-200 dark:bg-dark-border mx-1"></div>
                    <select id="sort-options" class="bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-sm font-medium focus:outline-none cursor-pointer border border-slate-200 dark:border-slate-700 rounded-lg px-2 py-1 transition-all">
                        <option value="name-asc" class="bg-white dark:bg-slate-800">Name A-Z</option>
                        <option value="name-desc">Name Z-A</option>
                        <option value="size-desc">Size Large</option>
                        <option value="size-asc">Size Small</option>
                        <option value="date-desc">Newest</option>
                        <option value="date-asc">Oldest</option>
                    </select>
                </div>
            </div>

            <!-- File View -->
            <div id="file-container" class="flex-1 p-6 overflow-y-auto custom-scrollbar">
                <div id="file-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-8 gap-4 min-h-[100px]">
                    <!-- Files will be injected here -->
                </div>
                <div id="file-list" class="hidden overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-slate-500 border-b border-slate-200 dark:border-dark-border">
                            <tr>
                                <th class="pb-3 font-medium">Name</th>
                                <th class="pb-3 font-medium">Size</th>
                                <th class="pb-3 font-medium">Modified</th>
                                <th class="pb-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody id="list-body">
                            <!-- List items will be injected here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </main>

        <!-- Details Panel -->
        <aside id="details-panel" class="w-80 border-l border-slate-200 dark:border-dark-border bg-white dark:bg-dark-surface hidden xl:flex flex-col p-6 overflow-y-auto custom-scrollbar">
            <div id="details-empty" class="h-full flex flex-col items-center justify-center text-slate-400">
                <i data-lucide="info" class="w-12 h-12 mb-4 opacity-20"></i>
                <p>Select a file to see details</p>
            </div>
            <div id="details-content" class="hidden space-y-6">
                <!-- Details will be injected here -->
            </div>
        </aside>
    </div>

    <!-- Modals and Toasts -->
    <div id="modal-container" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
        <div id="modal-content" class="bg-white dark:bg-dark-surface w-full max-w-4xl h-[80vh] rounded-2xl shadow-2xl flex flex-col overflow-hidden animate-fade-in">
            <!-- Content injected via JS -->
        </div>
    </div>

    <div id="toast-container" class="fixed bottom-6 right-6 z-50 flex flex-col gap-2"></div>

    <!-- Context Menu -->
    <div id="context-menu" class="context-menu w-48 bg-white dark:bg-dark-surface border border-slate-200 dark:border-dark-border rounded-xl shadow-xl py-2">
        <!-- Context items will be injected here -->
    </div>

    <script src="assets/js/app.js"></script>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
