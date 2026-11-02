# AppointPro – Installation Guide

## System Requirements
- WordPress 6.0 or higher
- PHP 8.1 or higher (PHP 8.2 and 8.3 fully supported)
- MySQL 5.7+ or MariaDB 10.3+

## Standard WordPress Installation
1. Download the `appointpro.zip` release archive.
2. Log in to your WordPress Administration Dashboard.
3. Navigate to **Plugins** -> **Add New** -> **Upload Plugin**.
4. Select `appointpro.zip` from your computer and click **Install Now**.
5. After the upload completes, click **Activate Plugin**.
6. Upon activation, AppointPro will automatically initialize all required database tables and default categories.
7. A new top-level **AppointPro** menu item will appear in your admin sidebar.

## Manual FTP / SFTP Installation
1. Extract the `appointpro.zip` file on your local machine.
2. Upload the extracted `appointpro` directory into your WordPress `wp-content/plugins/` directory.
3. Ensure directory permissions are standard (`755` for directories, `644` for files).
4. Go to **Plugins** -> **Installed Plugins** in WordPress and click **Activate** under AppointPro.
