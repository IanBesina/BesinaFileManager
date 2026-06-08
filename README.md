# 🌿 Besina File Manager

A lightweight, browser-based file management system written in PHP.

Besina File Manager provides a simple and intuitive interface for managing files and folders on a web server, featuring role-based permissions, secure login authentication, file uploads, downloads, folder creation, renaming, and deletion capabilities.

---

## Features

### Authentication System

* Session-based login system
* User accounts stored in a local `.users` file
* Multiple permission levels
* Secure logout functionality

### File Management

* Browse directories
* Download files
* Upload files
* Create folders
* Rename files and folders
* Delete files and folders
* Folder navigation with parent directory support

### Role-Based Access Control

| Level   | Permissions                                          |
| ------- | ---------------------------------------------------- |
| Level 0 | Browse folders, view files, download files           |
| Level 1 | Level 0 + Upload files, create folders, rename items |
| Level 2 | Level 1 + Delete files and folders                   |

### User Interface

* Responsive design
* Modern dashboard layout
* Icon-based actions
* Modal dialogs for operations
* Visual permission indicators
* Error notifications

---

# System Requirements

* PHP 7.4 or newer
* Apache, Nginx, or any PHP-compatible web server
* Write permissions on the application directory
* Modern web browser

---

# Installation

## 1. Upload Files

Upload all application files to your web server.

Example:

```text
/public_html/filemanager/
│
├── index.php
├── .users
└── files and folders...
```

---

## 2. Create User Database

Create a file named:

```text
.users
```

The first line must contain the CSV header:

```csv
username,password,admin
```

Example:

```csv
username,password,admin
admin,admin123,2
editor,editor123,1
viewer,viewer123,0
```

Where:

| Field    | Description              |
| -------- | ------------------------ |
| username | Login username           |
| password | Login password           |
| admin    | Permission level (0,1,2) |

---

## 3. Set Directory Permissions

Ensure PHP can read and write to the application directory.

Linux example:

```bash
chmod -R 755 filemanager
```

If uploads fail due to permissions:

```bash
chmod -R 775 filemanager
```

---

## 4. Access the Application

Open:

```text
https://yourdomain.com/filemanager/
```

Log in using one of the configured user accounts.

---

# User Guide

## Browsing Files

After login, files and folders appear in a grid layout.

* Click a folder to enter it.
* Click `..` to move up one level.
* Click a file to download it.

---

## Uploading Files

Permission Required: **Level 1**

1. Click the Upload button.
2. Select a file.
3. Upload begins automatically.

### Duplicate Filename Protection

If a file with the same name already exists, the upload will be rejected and a warning message displayed.

---

## Creating Folders

Permission Required: **Level 1**

1. Click the New Folder button.
2. Enter a folder name.
3. Click Create.

Folder names are automatically sanitized.

Allowed characters:

```text
A-Z
a-z
0-9
_
-
```

---

## Renaming Items

Permission Required: **Level 1**

1. Select exactly one item.
2. Click Rename.
3. Enter a new name.
4. Confirm.

---

## Deleting Items

Permission Required: **Level 2**

1. Select one or more items.
2. Click Delete.
3. Confirm deletion.

### Important

Folders can only be deleted when empty.

The application currently uses:

```php
rmdir()
```

which cannot remove folders containing files.

---

# Security Features

## Directory Traversal Protection

User-supplied paths are sanitized to prevent navigation outside the application root.

Example blocked attempts:

```text
../
../../
..\..
```

---

## Filename Sanitization

Folder and file names are cleaned before creation or renaming.

---

## Session Authentication

Authenticated users are tracked through PHP sessions.

---

## Download Validation

Files must exist within the current directory before download is permitted.

---

# Known Limitations

### Plain Text Password Storage

User passwords are currently stored in plain text inside `.users`.

Example:

```csv
admin,password123,2
```

For production environments, it is strongly recommended to use:

```php
password_hash()
password_verify()
```

instead.

---

### No Recursive Folder Deletion

Only empty folders may be deleted.

A recursive deletion function would be required to remove non-empty directories.

---

### Single File Upload

Only one file can be uploaded at a time.

---

### No File Size Validation

The application currently relies on PHP upload limits.

Recommended PHP settings:

```ini
upload_max_filesize = 100M
post_max_size = 100M
```

---

# Recommended Improvements

Future enhancements may include:

* Password hashing
* User management interface
* Drag-and-drop uploads
* Multi-file upload
* File previews
* Search functionality
* Activity logging
* Recursive folder deletion
* Dark mode
* File type icons
* Storage quotas
* Audit trails

---

# File Structure

```text
Besina File Manager
│
├── index.php
├── .users
│
├── Documents/
├── Images/
├── Uploads/
└── Other folders...
```

---

# License

This project is provided as-is for educational, internal, and small-scale deployment purposes.

You are free to modify and adapt the source code to suit your requirements.

---

# Author

**Besina File Manager**

A lightweight PHP file management solution with role-based access control and a modern web interface.
