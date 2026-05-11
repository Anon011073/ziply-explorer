/**
 * Ziply Explorer - Frontend Application
 */

const state = {
    currentPath: '',
    files: [],
    viewMode: 'grid', // 'grid' or 'list'
    selectedFiles: [],
    showHidden: localStorage.getItem('showHidden') === 'true',
    favorites: [],
    clipboard: {
        action: null, // 'copy' or 'cut'
        items: []
    }
};

const elements = {
    fileGrid: document.getElementById('file-grid'),
    fileList: document.getElementById('file-list'),
    listBody: document.getElementById('list-body'),
    breadcrumbs: document.getElementById('breadcrumbs'),
    storageBar: document.getElementById('storage-bar'),
    storagePercent: document.getElementById('storage-percent'),
    storageText: document.getElementById('storage-text'),
    navItems: document.querySelectorAll('.nav-item'),
    detailsEmpty: document.getElementById('details-empty'),
    detailsContent: document.getElementById('details-content'),
    modalContainer: document.getElementById('modal-container'),
    modalContent: document.getElementById('modal-content'),
    searchInput: document.getElementById('search-input'),
    themeToggle: document.getElementById('theme-toggle'),
    sortOptions: document.getElementById('sort-options'),
    viewGrid: document.getElementById('view-grid'),
    viewList: document.getElementById('view-list'),
    csrfToken: document.getElementById('csrf_token').value
};

/**
 * Initialize Application
 */
async function init() {
    await loadFiles();
    updateStats();
    setupEventListeners();

    // Check for saved theme
    if (localStorage.getItem('theme') === 'light') {
        document.documentElement.classList.remove('dark');
    }
}

/**
 * Load Files from API
 */
async function loadFiles(path = state.currentPath) {
    try {
        const response = await fetch(`api.php?action=list&path=${encodeURIComponent(path)}&show_hidden=${state.showHidden}`);
        const data = await response.json();

        if (data.error) {
            showToast(data.error, 'error');
            return;
        }

        state.files = data.files;
        state.currentPath = path;
        renderFiles();
        renderBreadcrumbs();
    } catch (error) {
        showToast('Failed to load files', 'error');
    }
}

/**
 * Render Files
 */
function renderFiles() {
    if (state.currentPath !== undefined) {
        elements.fileGrid.className = "grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-8 gap-4 min-h-[100px]";
    }
    const query = elements.searchInput.value.toLowerCase();
    let filteredFiles = state.files.filter(f => f.name.toLowerCase().includes(query));

    // Sort
    const sort = elements.sortOptions.value;
    filteredFiles.sort((a, b) => {
        if (a.is_dir && !b.is_dir) return -1;
        if (!a.is_dir && b.is_dir) return 1;

        let comparison = 0;
        switch (sort.split('-')[0]) {
            case 'name': comparison = a.name.localeCompare(b.name); break;
            case 'size': comparison = a.size - b.size; break;
            case 'date': comparison = a.modified - b.modified; break;
        }
        return sort.split('-')[1] === 'desc' ? comparison * -1 : comparison;
    });

    elements.fileGrid.innerHTML = '';
    elements.listBody.innerHTML = '';

    if (filteredFiles.length === 0) {
        const emptyMsg = '<div class="col-span-full py-20 text-center text-slate-400">No files found</div>';
        elements.fileGrid.innerHTML = emptyMsg;
        elements.listBody.innerHTML = '<tr><td colspan="4" class="py-20 text-center text-slate-400">No files found</td></tr>';
    }

    filteredFiles.forEach(file => {
        elements.fileGrid.appendChild(createGridItem(file));
        elements.listBody.appendChild(createListItem(file));
    });

    lucide.createIcons();
    setupContextMenus();
    updateSelectionUI();
}

function setupContextMenus() {
    const cards = document.querySelectorAll('.file-card, #list-body tr');
    cards.forEach((card) => {
        card.oncontextmenu = (e) => {
            e.preventDefault();
            const name = card.querySelector('.truncate, .font-medium').textContent.trim();
            const file = state.files.find(f => f.name === name);
            if (file) showContextMenu(e.pageX, e.pageY, file);
        };
    });
}

function showContextMenu(x, y, file) {
    const menu = document.getElementById('context-menu');
    menu.style.left = `${x}px`;
    menu.style.top = `${y}px`;
    menu.classList.add('active');

    const path = (state.currentPath ? state.currentPath + '/' : '') + file.name;

    menu.innerHTML = `
        <div class="px-3 py-1 text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">${file.name}</div>
        <button onclick="handleOpen('${file.name}')" class="w-full text-left px-4 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center gap-2">
            <i data-lucide="external-link" class="w-4 h-4"></i> Open
        </button>
        <button onclick="handleRename('${file.name}')" class="w-full text-left px-4 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center gap-2">
            <i data-lucide="edit-3" class="w-4 h-4"></i> Rename
        </button>
        <button onclick="handleDownload('${file.name}')" class="w-full text-left px-4 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center gap-2">
            <i data-lucide="download" class="w-4 h-4"></i> Download
        </button>
        <button onclick="handleShare('${file.name}')" class="w-full text-left px-4 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center gap-2">
            <i data-lucide="share-2" class="w-4 h-4"></i> Share
        </button>
        <button onclick="handleToggleFavorite('${path}')" class="w-full text-left px-4 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center gap-2">
            <i data-lucide="star" class="w-4 h-4"></i> Favorite
        </button>
        <div class="h-px bg-slate-100 dark:bg-slate-800 my-1"></div>
        ${file.extension === 'zip' ? `
        <button onclick="handleUnzip('${file.name}')" class="w-full text-left px-4 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center gap-2">
            <i data-lucide="file-archive" class="w-4 h-4"></i> Extract
        </button>
        ` : ''}
        <button onclick="handleDelete('${file.name}')" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 flex items-center gap-2">
            <i data-lucide="trash-2" class="w-4 h-4"></i> Delete
        </button>
    `;
    lucide.createIcons();

    const hideMenu = () => {
        menu.classList.remove('active');
        document.removeEventListener('click', hideMenu);
    };
    setTimeout(() => document.addEventListener('click', hideMenu), 10);
}

window.handleOpen = (name) => {
    const file = state.files.find(f => f.name === name);
    if (file.is_dir) {
        loadFiles((state.currentPath ? state.currentPath + '/' : '') + name);
    } else {
        const editable = ['js', 'php', 'css', 'html', 'md', 'json', 'sql', 'txt'].includes(file.extension);
        if (editable) openEditor(file);
        else selectFile(file);
    }
};

/**
 * Create Grid Item
 */
function createGridItem(file) {
    const div = document.createElement('div');
    div.className = `file-card group p-4 rounded-xl border border-transparent hover:border-slate-200 dark:hover:border-dark-border hover:bg-white dark:hover:bg-dark-surface transition-all cursor-pointer relative ${state.selectedFiles.includes(file.name) ? 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800' : ''}`;

    const icon = getFileIcon(file);
    const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(file.extension);

    div.innerHTML = `
        <div class="aspect-square rounded-lg mb-3 flex items-center justify-center bg-slate-100 dark:bg-slate-800 overflow-hidden relative">
            ${isImage ? `<img src="api.php?action=thumbnail&path=${encodeURIComponent((state.currentPath ? state.currentPath + '/' : '') + file.name)}" class="w-full h-full object-cover" loading="lazy">` : `<i data-lucide="${icon}" class="w-10 h-10 ${file.is_dir ? 'text-blue-500 fill-blue-500/20' : 'text-slate-400'}"></i>`}
        </div>
        <div class="text-sm font-medium truncate mb-1" title="${file.name}">${file.name}</div>
        <div class="text-[10px] text-slate-400">${file.is_dir ? 'Folder' : formatBytes(file.size)}</div>
    `;

    div.onclick = (e) => {
        if (e.ctrlKey || e.metaKey || e.shiftKey) {
            toggleSelect(file.name);
        } else {
            state.selectedFiles = [file.name];
            renderFiles();
            selectFile(file);
        }
    };

    div.ondblclick = (e) => {
        e.preventDefault();
        window.handleOpen(file.name);
    };

    return div;
}

/**
 * Create List Item
 */
function createListItem(file) {
    const tr = document.createElement('tr');
    tr.className = `group hover:bg-slate-50 dark:hover:bg-slate-800/50 cursor-pointer transition-colors ${state.selectedFiles.includes(file.name) ? 'bg-blue-50 dark:bg-blue-900/20' : ''}`;

    const icon = getFileIcon(file);

    tr.innerHTML = `
        <td class="py-3 px-2">
            <div class="flex items-center gap-3">
                <i data-lucide="${icon}" class="w-4 h-4 ${file.is_dir ? 'text-blue-500' : 'text-slate-400'}"></i>
                <span class="font-medium">${file.name}</span>
            </div>
        </td>
        <td class="py-3 text-slate-400 text-xs">${file.is_dir ? '--' : formatBytes(file.size)}</td>
        <td class="py-3 text-slate-400 text-xs">${new Date(file.modified * 1000).toLocaleDateString()}</td>
        <td class="py-3 text-right pr-2 opacity-0 group-hover:opacity-100 transition-opacity">
            <button class="p-1 hover:bg-slate-200 dark:hover:bg-slate-700 rounded"><i data-lucide="more-vertical" class="w-4 h-4"></i></button>
        </td>
    `;

    tr.onclick = (e) => {
        if (e.ctrlKey || e.metaKey || e.shiftKey) {
            toggleSelect(file.name);
        } else {
            state.selectedFiles = [file.name];
            renderFiles();
            selectFile(file);
        }
    };

    tr.ondblclick = (e) => {
        e.preventDefault();
        window.handleOpen(file.name);
    };

    return tr;
}

/**
 * Render Breadcrumbs
 */
function renderBreadcrumbs() {
    elements.breadcrumbs.innerHTML = '';

    const homeBtn = document.createElement('button');
    homeBtn.className = 'hover:text-blue-600 transition-colors';
    homeBtn.innerHTML = '<i data-lucide="home" class="w-4 h-4"></i>';
    homeBtn.onclick = () => loadFiles('');
    elements.breadcrumbs.appendChild(homeBtn);

    if (state.currentPath) {
        const parts = state.currentPath.split('/');
        let path = '';

        parts.forEach((part, i) => {
            elements.breadcrumbs.appendChild(createBreadcrumbSeparator());
            path += (path ? '/' : '') + part;
            const currentPath = path;

            const btn = document.createElement('button');
            btn.className = 'hover:text-blue-600 transition-colors max-w-[150px] truncate';
            btn.textContent = part;
            btn.onclick = () => loadFiles(currentPath);
            elements.breadcrumbs.appendChild(btn);
        });
    }
    lucide.createIcons();
}

function createBreadcrumbSeparator() {
    const span = document.createElement('span');
    span.className = 'mx-2 text-slate-300 dark:text-slate-700';
    span.innerHTML = '<i data-lucide="chevron-right" class="w-3 h-3"></i>';
    return span;
}

/**
 * Update Stats
 */
async function updateStats() {
    try {
        const response = await fetch('api.php?action=stats');
        const data = await response.json();

        elements.storagePercent.textContent = data.disk_percent + '%';
        elements.storageBar.style.width = data.disk_percent + '%';
        elements.storageText.textContent = `${data.disk_used} of ${data.disk_total} used (${data.file_count} files)`;
    } catch (e) {}
}

/**
 * Helpers
 */
function getFileIcon(file) {
    if (file.is_dir) return 'folder';
    const ext = file.extension;
    if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(ext)) return 'image';
    if (['mp4', 'webm', 'ogg', 'mov'].includes(ext)) return 'film';
    if (['mp3', 'wav', 'flac'].includes(ext)) return 'music';
    if (['pdf'].includes(ext)) return 'file-text';
    if (['zip', 'rar', '7z', 'tar', 'gz'].includes(ext)) return 'archive';
    if (['html', 'css', 'js', 'php', 'py', 'json', 'sql'].includes(ext)) return 'file-code';
    return 'file';
}

function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `px-4 py-3 rounded-lg shadow-lg text-white flex items-center gap-3 animate-fade-in ${type === 'success' ? 'bg-green-600' : 'bg-red-600'}`;

    const icon = type === 'success' ? 'check-circle' : 'alert-circle';
    toast.innerHTML = `<i data-lucide="${icon}" class="w-5 h-5"></i><span>${message}</span>`;

    document.getElementById('toast-container').appendChild(toast);
    lucide.createIcons();

    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function selectFile(file) {
    elements.detailsEmpty.classList.add('hidden');
    elements.detailsContent.classList.remove('hidden');

    const icon = getFileIcon(file);
    const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(file.extension);

    elements.detailsContent.innerHTML = `
        <div class="flex flex-col items-center text-center space-y-4">
            <div class="w-32 h-32 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center overflow-hidden border border-slate-200 dark:border-dark-border">
                ${isImage ? `<img src="api.php?action=thumbnail&path=${encodeURIComponent((state.currentPath ? state.currentPath + '/' : '') + file.name)}" class="w-full h-full object-cover">` : `<i data-lucide="${icon}" class="w-12 h-12 text-slate-400"></i>`}
            </div>
            <div>
                <h3 class="font-bold text-lg truncate w-full max-w-[200px]" title="${file.name}">${file.name}</h3>
                <p class="text-sm text-slate-400">${file.is_dir ? 'Folder' : file.extension.toUpperCase() + ' File'}</p>
            </div>
        </div>

        <div class="space-y-3 pt-6 border-t border-slate-200 dark:border-dark-border">
            <div class="flex justify-between text-sm">
                <span class="text-slate-400 font-medium">Size</span>
                <span>${file.is_dir ? '--' : formatBytes(file.size)}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-400 font-medium">Modified</span>
                <span>${new Date(file.modified * 1000).toLocaleDateString()}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-400 font-medium">Permissions</span>
                <span class="font-mono">${file.permissions}</span>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 pt-6">
            <button onclick="handleDelete('${file.name}')" class="flex items-center justify-center gap-2 px-4 py-2 bg-red-50 dark:bg-red-900/20 text-red-600 rounded-xl text-sm font-semibold hover:bg-red-100 transition-colors">
                <i data-lucide="trash-2" class="w-4 h-4"></i> Delete
            </button>
            <button onclick="handleRename('${file.name}')" class="flex items-center justify-center gap-2 px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-dark-text rounded-xl text-sm font-semibold hover:bg-slate-200 transition-colors">
                <i data-lucide="edit-3" class="w-4 h-4"></i> Rename
            </button>
            <button onclick="handleDownload('${file.name}')" class="col-span-2 flex items-center justify-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-500 transition-colors">
                <i data-lucide="download" class="w-4 h-4"></i> Download
            </button>
            <button onclick="handleShare('${file.name}')" class="col-span-2 flex items-center justify-center gap-2 px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-dark-text rounded-xl text-sm font-semibold hover:bg-slate-200 transition-colors mt-2">
                <i data-lucide="share-2" class="w-4 h-4"></i> Share Link
            </button>
        </div>
    `;
    lucide.createIcons();
}

function openEditor(file) {
    const path = (state.currentPath ? state.currentPath + '/' : '') + file.name;
    elements.modalContainer.classList.remove('hidden');

    const defaultTheme = localStorage.getItem('editorTheme') || (document.documentElement.classList.contains('dark') ? 'dracula' : 'default');

    elements.modalContent.innerHTML = `
        <div class="flex items-center justify-between p-4 border-b border-slate-200 dark:border-dark-border bg-slate-50 dark:bg-slate-800">
            <div class="flex items-center gap-3">
                <i data-lucide="edit-3" class="w-5 h-5 text-blue-500"></i>
                <span class="font-bold truncate max-w-[200px]">${file.name}</span>
            </div>
            <div class="flex items-center gap-4">
                <select id="editor-theme-select" class="bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg px-2 py-1 text-xs outline-none">
                    <option value="default" ${defaultTheme === 'default' ? 'selected' : ''}>Light Theme</option>
                    <option value="dracula" ${defaultTheme === 'dracula' ? 'selected' : ''}>Dracula</option>
                    <option value="ayu-mirage" ${defaultTheme === 'ayu-mirage' ? 'selected' : ''}>Ayu Mirage</option>
                </select>
                <button id="editor-save" class="px-4 py-1.5 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-500 transition-colors">Save Changes</button>
                <button id="editor-close" class="p-2 hover:bg-slate-200 dark:hover:bg-slate-700 rounded-lg"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
        </div>
        <div class="flex-1 relative overflow-hidden bg-[#282a36]">
            <textarea id="editor-textarea"></textarea>
        </div>
    `;
    lucide.createIcons();

    const editor = CodeMirror.fromTextArea(document.getElementById('editor-textarea'), {
        lineNumbers: true,
        theme: defaultTheme,
        mode: getCodeMirrorMode(file.extension),
        viewportMargin: Infinity,
        lineWrapping: true
    });

    // Theme selector
    document.getElementById('editor-theme-select').onchange = (e) => {
        const theme = e.target.value;
        editor.setOption('theme', theme);
        localStorage.setItem('editorTheme', theme);
    };

    // Load content
    fetch(`api.php?action=get_content&path=${encodeURIComponent(path)}`)
        .then(res => res.json())
        .then(data => {
            if (data.content !== undefined) {
                editor.setValue(data.content);
                setTimeout(() => editor.refresh(), 100);
            }
        });

    document.getElementById('editor-close').onclick = () => {
        elements.modalContainer.classList.add('hidden');
    };

    document.getElementById('editor-save').onclick = async () => {
        const formData = new FormData();
        formData.append('csrf_token', elements.csrfToken);
        formData.append('path', path);
        formData.append('content', editor.getValue());

        const response = await fetch('api.php?action=save_content', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            showToast('File saved');
            elements.modalContainer.classList.add('hidden');
            const currentSelection = [...state.selectedFiles];
            await loadFiles();
            state.selectedFiles = currentSelection;
            renderFiles();
        } else {
            showToast(data.error, 'error');
        }
    };
}

function getCodeMirrorMode(ext) {
    const modes = {
        'js': 'javascript',
        'php': 'php',
        'css': 'css',
        'html': 'xml',
        'md': 'markdown',
        'json': 'javascript',
        'sql': 'sql'
    };
    return modes[ext] || 'text/plain';
}

function toggleSelect(name) {
    const index = state.selectedFiles.indexOf(name);
    if (index > -1) {
        state.selectedFiles.splice(index, 1);
    } else {
        state.selectedFiles.push(name);
    }
    renderFiles();
}

function updateSelectionUI() {
    if (state.selectedFiles.length > 0) {
        elements.detailsEmpty.classList.add('hidden');
        elements.detailsContent.classList.remove('hidden');
        elements.detailsContent.innerHTML = `
            <div class="text-center py-10">
                <div class="w-20 h-20 bg-blue-100 dark:bg-blue-900/30 text-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="layers" class="w-10 h-10"></i>
                </div>
                <h3 class="font-bold text-lg">${state.selectedFiles.length} items selected</h3>
            </div>
            <div class="grid grid-cols-1 gap-3 pt-6 border-t border-slate-200 dark:border-dark-border">
                <button onclick="handleBulkDelete()" class="flex items-center justify-center gap-2 px-4 py-2 bg-red-50 dark:bg-red-900/20 text-red-600 rounded-xl text-sm font-semibold hover:bg-red-100 transition-colors">
                    <i data-lucide="trash-2" class="w-4 h-4"></i> Delete Selected
                </button>
                <button onclick="handleBulkCopy()" class="flex items-center justify-center gap-2 px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-dark-text rounded-xl text-sm font-semibold hover:bg-slate-200 transition-colors">
                    <i data-lucide="copy" class="w-4 h-4"></i> Copy Selected
                </button>
                <button onclick="handleBulkMove()" class="flex items-center justify-center gap-2 px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-dark-text rounded-xl text-sm font-semibold hover:bg-slate-200 transition-colors">
                    <i data-lucide="move" class="w-4 h-4"></i> Move Selected
                </button>
                <button onclick="handleBulkZip()" class="flex items-center justify-center gap-2 px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-dark-text rounded-xl text-sm font-semibold hover:bg-slate-200 transition-colors">
                    <i data-lucide="file-archive" class="w-4 h-4"></i> ZIP Selected
                </button>
            </div>
        `;
        lucide.createIcons();
    }
}

/**
 * Event Listeners
 */
function setupEventListeners() {
    const btnToggleHidden = document.getElementById('btn-toggle-hidden');
    const iconHidden = document.getElementById('icon-hidden');

    if (state.showHidden) {
        iconHidden.setAttribute('data-lucide', 'eye');
        lucide.createIcons();
    }

    btnToggleHidden.onclick = () => {
        state.showHidden = !state.showHidden;
        localStorage.setItem('showHidden', state.showHidden);
        iconHidden.setAttribute('data-lucide', state.showHidden ? 'eye' : 'eye-off');
        lucide.createIcons();
        loadFiles();
    };

    document.querySelector('[data-nav="favorites"]').onclick = async () => {
        const response = await fetch('api.php?action=get_favorites');
        const data = await response.json();
        state.files = data.files;
        elements.fileGrid.innerHTML = '';
        elements.fileList.classList.add('hidden');
        elements.fileGrid.classList.remove('hidden');
        elements.fileGrid.className = "grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-8 gap-4 min-h-[100px]";
        elements.breadcrumbs.innerHTML = '<span class="font-bold">Favorite Items</span>';
        renderFiles();
    };

    document.getElementById('nav-recent').onclick = async () => {
        const response = await fetch('api.php?action=get_recent');
        const data = await response.json();
        state.files = data.files;
        elements.fileGrid.innerHTML = '';
        elements.fileList.classList.add('hidden');
        elements.fileGrid.classList.remove('hidden');
        elements.fileGrid.className = "grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-8 gap-4 min-h-[100px]";
        elements.breadcrumbs.innerHTML = '<span class="font-bold">Recent Files</span>';
        renderFiles();
    };

    document.getElementById('nav-shares').onclick = async () => {
        const response = await fetch('api.php?action=get_shares');
        const data = await response.json();

        elements.fileGrid.innerHTML = '';
        elements.fileList.classList.add('hidden');
        elements.fileGrid.classList.remove('hidden');
        elements.breadcrumbs.innerHTML = '<span class="font-bold">Active Share Links</span>';

        // Add a top padding/margin to the grid for this specific view to avoid overlap
        elements.fileGrid.className = "grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mt-2";

        data.shares.forEach(share => {
            const div = document.createElement('div');
            div.className = 'bg-white dark:bg-dark-surface p-4 rounded-xl border border-slate-200 dark:border-dark-border shadow-sm';
            div.innerHTML = `
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 bg-blue-50 dark:bg-blue-900/20 text-blue-500 rounded-lg flex items-center justify-center">
                        <i data-lucide="share-2" class="w-5 h-5"></i>
                    </div>
                    <div class="truncate flex-1">
                        <p class="font-bold text-sm truncate">${share.file_path.split('/').pop()}</p>
                        <p class="text-[10px] text-slate-400">Key: ${share.share_key}</p>
                    </div>
                </div>
                <div class="text-xs text-slate-500 mb-4">
                    <p>Downloads: ${share.download_count} / ${share.download_limit || '∞'}</p>
                    <p>Expires: ${share.expires_at || 'Never'}</p>
                </div>
                <div class="flex gap-2">
                    <button onclick="navigator.clipboard.writeText(window.location.origin + window.location.pathname.replace('index.php', '') + 'share.php?k=${share.share_key}'); showToast('Copied!')" class="flex-1 py-1.5 bg-slate-100 dark:bg-slate-800 text-[10px] font-bold rounded-lg hover:bg-slate-200 transition-colors">Copy Link</button>
                    <button onclick="handleDeleteShare(${share.id})" class="p-1.5 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                </div>
            `;
            elements.fileGrid.appendChild(div);
        });
        lucide.createIcons();
    };

    window.handleDeleteShare = async (id) => {
        if (confirm('Delete this share link?')) {
            const formData = new FormData();
            formData.append('csrf_token', elements.csrfToken);
            formData.append('id', id);
            const response = await fetch('api.php?action=delete_share', { method: 'POST', body: formData });
            const data = await response.json();
            if (data.success) {
                showToast('Share deleted');
                document.getElementById('nav-shares').click();
            }
        }
    };

    elements.themeToggle.onclick = () => {
        document.documentElement.classList.toggle('dark');
        localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
    };

    elements.viewGrid.onclick = () => {
        state.viewMode = 'grid';
        elements.fileGrid.classList.remove('hidden');
        elements.fileList.classList.add('hidden');
        elements.viewGrid.classList.add('bg-slate-200', 'dark:bg-slate-800', 'text-blue-600');
        elements.viewList.classList.remove('bg-slate-200', 'dark:bg-slate-800', 'text-blue-600');
    };

    elements.viewList.onclick = () => {
        state.viewMode = 'list';
        elements.fileGrid.classList.add('hidden');
        elements.fileList.classList.remove('hidden');
        elements.viewList.classList.add('bg-slate-200', 'dark:bg-slate-800', 'text-blue-600');
        elements.viewGrid.classList.remove('bg-slate-200', 'dark:bg-slate-800', 'text-blue-600');
    };

    elements.sortOptions.onchange = () => renderFiles();

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Delete' && state.selectedFiles.length > 0) {
            handleBulkDelete();
        }
    });

    let searchTimeout;
    elements.searchInput.oninput = () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(async () => {
            const query = elements.searchInput.value;
            if (query.length > 2) {
                const response = await fetch(`api.php?action=list&path=${encodeURIComponent(state.currentPath)}&search=${encodeURIComponent(query)}&show_hidden=${state.showHidden}`);
                const data = await response.json();
                state.files = data.files;
                renderFiles();
            } else if (query.length === 0) {
                loadFiles();
            } else {
                renderFiles();
            }
        }, 300);
    };

    document.getElementById('btn-new-folder').onclick = async () => {
        const name = prompt('Enter folder name:');
        if (name) {
            const formData = new FormData();
            formData.append('csrf_token', elements.csrfToken);
            formData.append('path', state.currentPath);
            formData.append('name', name);

            const response = await fetch('api.php?action=mkdir', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.success) {
                showToast('Folder created');
                loadFiles();
            } else {
                showToast(data.error, 'error');
            }
        }
    };

    // Drag and Drop Upload
    const fileContainer = document.getElementById('file-container');
    fileContainer.ondragover = (e) => {
        e.preventDefault();
        fileContainer.classList.add('bg-blue-50/50', 'dark:bg-blue-900/10');
    };
    fileContainer.ondragleave = () => {
        fileContainer.classList.remove('bg-blue-50/50', 'dark:bg-blue-900/10');
    };
    fileContainer.ondrop = async (e) => {
        e.preventDefault();
        fileContainer.classList.remove('bg-blue-50/50', 'dark:bg-blue-900/10');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            const formData = new FormData();
            formData.append('csrf_token', elements.csrfToken);
            formData.append('path', state.currentPath);
            for (let file of files) {
                formData.append('files[]', file);
            }
            showToast('Uploading...');
            const response = await fetch('api.php?action=upload', { method: 'POST', body: formData });
            const data = await response.json();
            if (data.success) {
                showToast('Upload complete');
                loadFiles();
            }
        }
    };

    // Upload Handler
    const btnUpload = document.getElementById('btn-upload');
    const fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.multiple = true;
    fileInput.onchange = async () => {
        const formData = new FormData();
        formData.append('csrf_token', elements.csrfToken);
        formData.append('path', state.currentPath);
        for (let file of fileInput.files) {
            formData.append('files[]', file);
        }

        showToast('Uploading...');
        const response = await fetch('api.php?action=upload', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            showToast('Upload complete');
            loadFiles();
        } else {
            showToast(data.error, 'error');
        }
    };
    btnUpload.onclick = () => fileInput.click();
}

/**
 * Global Handlers for Detail Panel
 */
window.handleDelete = async (name) => {
    if (confirm(`Are you sure you want to delete ${name}?`)) {
        const formData = new FormData();
        formData.append('csrf_token', elements.csrfToken);
        formData.append('items[]', (state.currentPath ? state.currentPath + '/' : '') + name);

        const response = await fetch('api.php?action=delete', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            showToast('Deleted');
            loadFiles();
            elements.detailsContent.classList.add('hidden');
            elements.detailsEmpty.classList.remove('hidden');
        }
    }
};

window.handleRename = async (name) => {
    const newName = prompt('Enter new name:', name);
    if (newName && newName !== name) {
        const formData = new FormData();
        formData.append('csrf_token', elements.csrfToken);
        formData.append('old_path', (state.currentPath ? state.currentPath + '/' : '') + name);
        formData.append('new_name', newName);

        const response = await fetch('api.php?action=rename', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            showToast('Renamed');
            loadFiles();
        }
    }
};

window.handleDownload = (name) => {
    const path = (state.currentPath ? state.currentPath + '/' : '') + name;
    window.open(`download.php?path=${encodeURIComponent(path)}`, '_blank');
};

window.handleToggleFavorite = async (path) => {
    const formData = new FormData();
    formData.append('csrf_token', elements.csrfToken);
    formData.append('path', path);
    const response = await fetch('api.php?action=toggle_favorite', { method: 'POST', body: formData });
    const data = await response.json();
    if (data.success) {
        showToast(data.status === 'added' ? 'Added to favorites' : 'Removed from favorites');
    }
};

window.handleShare = async (name) => {
    const path = (state.currentPath ? state.currentPath + '/' : '') + name;
    const password = prompt('Set a password for this share (optional):');
    const expires = prompt('Set expiration (e.g. 1 hour, 1 day, 7 days) (optional):');

    const formData = new FormData();
    formData.append('csrf_token', elements.csrfToken);
    formData.append('path', path);
    if (password) formData.append('password', password);
    if (expires) formData.append('expires', expires);

    const response = await fetch('api.php?action=share', {
        method: 'POST',
        body: formData
    });
    const data = await response.json();
    if (data.success) {
        const fullUrl = window.location.origin + window.location.pathname.replace('index.php', '') + data.share_url;
        prompt('Share link created! Copy it below:', fullUrl);
    } else {
        showToast(data.error, 'error');
    }
};

window.handleUnzip = async (name) => {
    const formData = new FormData();
    formData.append('csrf_token', elements.csrfToken);
    formData.append('path', (state.currentPath ? state.currentPath + '/' : '') + name);

    showToast('Extracting...');
    const response = await fetch('api.php?action=unzip', {
        method: 'POST',
        body: formData
    });
    const data = await response.json();
    if (data.success) {
        showToast('Extracted successfully');
        loadFiles();
    } else {
        showToast(data.error, 'error');
    }
};

window.handleBulkDelete = async () => {
    if (confirm(`Are you sure you want to delete ${state.selectedFiles.length} items?`)) {
        const formData = new FormData();
        formData.append('csrf_token', elements.csrfToken);
        state.selectedFiles.forEach(name => {
            formData.append('items[]', (state.currentPath ? state.currentPath + '/' : '') + name);
        });

        const response = await fetch('api.php?action=delete', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            showToast('Items deleted');
            state.selectedFiles = [];
            loadFiles();
            updateSelectionUI();
        }
    }
};

window.handleBulkCopy = () => {
    state.clipboard = {
        action: 'copy',
        items: state.selectedFiles.map(name => (state.currentPath ? state.currentPath + '/' : '') + name)
    };
    showToast(`${state.selectedFiles.length} items copied to clipboard`);
    renderPasteButton();
};

window.handleBulkMove = () => {
    state.clipboard = {
        action: 'move',
        items: state.selectedFiles.map(name => (state.currentPath ? state.currentPath + '/' : '') + name)
    };
    showToast(`${state.selectedFiles.length} items cut to clipboard`);
    renderPasteButton();
};

window.handleBulkZip = async () => {
    const name = prompt('Enter archive name:', 'archive.zip');
    if (name) {
        const formData = new FormData();
        formData.append('csrf_token', elements.csrfToken);
        formData.append('path', state.currentPath);
        formData.append('name', name);
        state.selectedFiles.forEach(f => {
            formData.append('items[]', (state.currentPath ? state.currentPath + '/' : '') + f);
        });

        showToast('Creating archive...');
        const response = await fetch('api.php?action=zip', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            showToast('Archive created');
            state.selectedFiles = [];
            loadFiles();
            updateSelectionUI();
        } else {
            showToast(data.error, 'error');
        }
    }
};

function renderPasteButton() {
    if (!document.getElementById('btn-paste')) {
        const btn = document.createElement('button');
        btn.id = 'btn-paste';
        btn.className = 'flex items-center gap-2 bg-green-600 hover:bg-green-500 text-white px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors ml-2';
        btn.innerHTML = '<i data-lucide="clipboard-paste" class="w-4 h-4"></i> Paste';
        btn.onclick = handlePaste;
        document.querySelector('#btn-upload').parentElement.appendChild(btn);
        lucide.createIcons();
    }
}

async function handlePaste() {
    if (state.clipboard.items.length === 0) return;

    const formData = new FormData();
    formData.append('csrf_token', elements.csrfToken);
    formData.append('dest', state.currentPath);
    state.clipboard.items.forEach(item => {
        formData.append('items[]', item);
    });

    const response = await fetch(`api.php?action=${state.clipboard.action}`, {
        method: 'POST',
        body: formData
    });
    const data = await response.json();
    if (data.success) {
        showToast(`Items ${state.clipboard.action}ed successfully`);
        if (state.clipboard.action === 'move') {
            state.clipboard = { action: null, items: [] };
            document.getElementById('btn-paste').remove();
        }
        loadFiles();
    } else {
        showToast(data.error, 'error');
    }
}

// Start
init();
