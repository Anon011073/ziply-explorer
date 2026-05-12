# Ziply Explorer

A lightweight, secure, and modern web-based file manager built with PHP 8+. Inspired by Tiny File Manager with a polished UI similar to PHP Transfer by Lunatio.

## Features

- **Modern UI:** Dark-first responsive design using Tailwind CSS and Lucide icons.
- **File Operations:** Create, rename, move, copy, delete, and ZIP/unZIP files and folders.
- **Advanced Uploads:** Support for drag-and-drop and recursive folder uploads.
- **Secure Sharing:** Generate password-protected public share links with expiration and download limits.
- **Integrated Editor:** Full-featured code editor (CodeMirror) with theme support (Dracula, Monokai, etc.) and syntax highlighting.
- **Previews:** High-quality previews for images, video, audio, PDF, and text/code files.
- **Search & Filtering:** Instant and recursive search with hidden file support.
- **Security:** CSRF protection, directory traversal guards, extension-based upload filtering, and secure session handling.

## Requirements

- PHP 8.0+
- SQLite (default) or MySQL (optional)
- PHP Extensions: `pdo_sqlite`, `gd`, `zip`, `fileinfo`
- Apache or Nginx

## Installation

1. Upload the files to your server (e.g., Laragon, XAMPP, or a shared host).
2. Ensure the `data/` and root directory are writable by the web server.
3. Access the project URL in your browser.
4. The **Installer** will guide you through setting up your admin credentials and root directory.
5. Once installed, log in with your credentials.

## Security Note

For production environments, ensure that the `.htaccess` files are active to protect sensitive data. On Nginx, manual configuration of directory restrictions for `data/` and `includes/` is required.

## License

MIT License. Feel free to customize and deploy.
