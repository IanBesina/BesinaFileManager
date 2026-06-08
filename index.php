<?php
session_start();

// Configuration
$users_file = __DIR__ . '/.users';
$upload_dir = __DIR__ . '/';

// Create uploads directory if it doesn't exist
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// --- AUTHENTICATION LOGIC ---
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (file_exists($users_file)) {
        $lines = file($users_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $header = str_getcsv(array_shift($lines));

        foreach ($lines as $line) {
            $data = array_combine($header, str_getcsv($line));
            if ($data['username'] === $username && $data['password'] === $password) {
                $_SESSION['user'] = $username;
                $_SESSION['level'] = (int)$data['admin'];
                header("Location: index.php");
                exit;
            }
        }
    }
    $login_error = "Invalid username or password.";
}

$is_logged_in = isset($_SESSION['user']);
$user_level = $_SESSION['level'] ?? 0;

// --- FILE SYSTEM LOGIC ---
$current_dir = isset($_GET['dir']) ? $_GET['dir'] : '';
// Sanitize path to prevent directory traversal
$current_dir = trim(str_replace(['..', '\\'], '', $current_dir), '/');
$absolute_path = realpath($upload_dir . '/' . $current_dir);

// Fallback to base upload dir if path is invalid or outside uploads
if ($absolute_path === false || strpos($absolute_path, realpath($upload_dir)) !== 0) {
    $absolute_path = realpath($upload_dir);
    $current_dir = '';
}

$rel_path_query = $current_dir ? "?dir=" . urlencode($current_dir) : "";

// Handle Actions
if ($is_logged_in && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Level 1+ Actions: Upload, Create Folder, Rename
    if ($user_level >= 1) {
        if ($action === 'upload' && isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $target = $absolute_path . '/' . basename($_FILES['file']['name']);

            // Check if file already exists
            if (file_exists($target)) {
                $_SESSION['error_msg'] = "Cannot upload the file - same filename exists. Either delete or rename the file first before uploading.";
            } else {
                move_uploaded_file($_FILES['file']['tmp_name'], $target);
            }
        }
        elseif ($action === 'create_folder' && !empty($_POST['folder_name'])) {
            $new_folder = $absolute_path . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['folder_name']);
            if (!file_exists($new_folder)) mkdir($new_folder);
        }
        elseif ($action === 'rename' && !empty($_POST['selected_files']) && !empty($_POST['new_name'])) {
            $old_file = $absolute_path . '/' . basename($_POST['selected_files'][0]);
            $new_file = $absolute_path . '/' . preg_replace('/[^a-zA-Z0-9_.-]/', '', $_POST['new_name']);
            if (file_exists($old_file) && !file_exists($new_file)) rename($old_file, $new_file);
        }
    }

    // Level 2+ Actions: Delete
    if ($user_level >= 2) {
        if ($action === 'delete' && !empty($_POST['selected_files'])) {
            foreach ($_POST['selected_files'] as $file) {
                $target = $absolute_path . '/' . basename($file);
                if (is_file($target)) unlink($target);
                elseif (is_dir($target)) {
                    // Simple rmdir, requires empty folder. Use recursive delete for production.
                    @rmdir($target);
                }
            }
        }
    }
    header("Location: index.php" . $rel_path_query);
    exit;
}

// Handle Download (Level 0+)
if ($is_logged_in && isset($_GET['download'])) {
    $target = $absolute_path . '/' . basename($_GET['download']);
    if (file_exists($target) && is_file($target)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($target).'"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($target));
        readfile($target);
        exit;
    }
}

// Get files and folders
$items = [];
if ($is_logged_in) {
    $dir_handle = opendir($absolute_path);
    while (false !== ($entry = readdir($dir_handle))) {
        if ($entry != '.' && $entry != '..') {
            $items[] = $entry;
        }
    }
    closedir($dir_handle);
    sort($items);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Besina File Manager</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-green: #2E8B57; /* SeaGreen */
            --light-green: #E8F5E9;
            --dark-green: #1b5e20;
            --bg-color: #f4f9f4;
            --error-red: #d32f2f;
        }
        * { box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { margin: 0; background-color: var(--bg-color); color: #333; height: 100vh; display: flex; }

        /* Login Form */
        .login-wrapper { display: flex; align-items: center; justify-content: center; width: 100%; height: 100vh; }
        .login-box { background: white; padding: 40px; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 350px; text-align: center; border-top: 5px solid var(--primary-green); }
        .login-box h2 { color: var(--primary-green); margin-top: 0; }
        .login-box input { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ccc; border-radius: 8px; outline: none; }
        .login-box button { width: 100%; padding: 12px; background: var(--primary-green); color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: bold; transition: 0.3s; }
        .login-box button:hover { background: var(--dark-green); }
        .error { color: var(--error-red); margin-bottom: 10px; font-size: 14px; }

        /* Main App Layout */
        .sidebar { width: 80px; background: var(--primary-green); display: flex; flex-direction: column; align-items: center; padding: 20px 0; border-top-right-radius: 20px; border-bottom-right-radius: 20px; box-shadow: 2px 0 10px rgba(0,0,0,0.1); z-index: 10; }
        .action-btn { background: none; border: none; color: white; font-size: 24px; margin: 15px 0; cursor: pointer; position: relative; transition: 0.2s; opacity: 0.8; display: flex; justify-content: center; align-items: center; width: 50px; height: 50px; border-radius: 12px; }
        .action-btn:hover { opacity: 1; background: rgba(255,255,255,0.2); transform: scale(1.1); }
        .action-btn:disabled { opacity: 0.4; cursor: not-allowed; transform: none; background: none; }

        /* Custom R icon as requested */
        .icon-r { font-family: 'Georgia', serif; font-weight: 900; font-size: 26px; font-style: italic; }

        .main-content { flex: 1; padding: 30px; overflow-y: auto; display: flex; flex-direction: column; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; background: white; padding: 15px 25px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .header h1 { margin: 0; color: var(--primary-green); font-size: 22px; }
        .path { color: #666; font-size: 14px; }

        /* File Grid */
        .file-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 20px; }
        .item { background: white; border-radius: 12px; padding: 20px 10px; text-align: center; position: relative; box-shadow: 0 4px 10px rgba(0,0,0,0.04); transition: 0.2s; border: 2px solid transparent; }
        .item:hover { transform: translateY(-5px); box-shadow: 0 8px 15px rgba(0,0,0,0.08); }
        .item i { font-size: 40px; color: var(--primary-green); margin-bottom: 10px; }
        .item .name { font-size: 13px; color: #444; word-break: break-all; text-decoration: none; display: block; }
        .item.up-folder { background: var(--light-green); cursor: pointer; }
        .item.up-folder i { color: var(--dark-green); }

        /* Checkbox upper right */
        .item-checkbox { position: absolute; top: 10px; right: 10px; width: 18px; height: 18px; accent-color: var(--primary-green); cursor: pointer; }

        /* Modals */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 100; justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 25px; border-radius: 15px; width: 350px; text-align: center; border-top: 5px solid var(--primary-green); }
        .modal-content.error-border { border-top-color: var(--error-red); }
        .modal-content h3 { margin-top: 0; color: var(--primary-green); }
        .modal-content h3.error-text { color: var(--error-red); }
        .modal-content input { width: 100%; padding: 10px; margin: 15px 0; border: 1px solid #ccc; border-radius: 8px; }
        .modal-btns { display: flex; justify-content: space-between; gap: 10px; }
        .modal-btns button { flex: 1; padding: 10px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .btn-confirm { background: var(--primary-green); color: white; }
        .btn-cancel { background: #eee; color: #333; }

        .hidden { display: none; }
    </style>
</head>
<body>

<?php if (!$is_logged_in): ?>
    <div class="login-wrapper">
        <div class="login-box">
            <h2><i class="fas fa-leaf"></i> Besina File Manager Portal</h2>
            <?php if(isset($login_error)) echo "<div class='error'>$login_error</div>"; ?>
            <form method="POST">
                <input type="text" name="username" placeholder="Username" required autofocus>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" name="login">Log In</button>
            </form>
        </div>
    </div>
<?php else: ?>
    <form id="action-form" method="POST" enctype="multipart/form-data" style="display:none;">
        <input type="hidden" name="action" id="form-action" value="">
        <input type="file" name="file" id="file-input" onchange="submitForm('upload')">
        <input type="hidden" name="folder_name" id="folder-name-input">
        <input type="hidden" name="new_name" id="new-name-input">
        <div id="selected-files-container"></div>
    </form>

    <div class="sidebar">
        <button class="action-btn" title="Upload File" onclick="document.getElementById('file-input').click()" <?= $user_level < 1 ? 'disabled' : '' ?>>
            <i class="fas fa-file-upload"></i>
        </button>

        <button class="action-btn" title="New Folder" onclick="showModal('folderModal')" <?= $user_level < 1 ? 'disabled' : '' ?>>
            <i class="fas fa-folder-plus"></i>
        </button>

        <button class="action-btn" title="Rename Selected" onclick="promptRename()" <?= $user_level < 1 ? 'disabled' : '' ?>>
            <span class="icon-r">R</span>
        </button>

        <button class="action-btn" title="Delete Selected" onclick="promptDelete()" <?= $user_level < 2 ? 'disabled' : '' ?>>
            <i class="fas fa-trash-alt"></i>
        </button>

        <div style="flex-grow: 1;"></div>

        <a href="?logout=1" class="action-btn" title="Logout">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>

    <div class="main-content">
        <div class="header">
            <div>
                <h1><i class="fas fa-leaf"></i> Besina File Manager</h1>
                <div class="path">Location: /<?= htmlspecialchars($current_dir) ?></div>
            </div>
            <div>
                <span style="background: var(--light-green); padding: 5px 15px; border-radius: 20px; color: var(--dark-green); font-size: 14px; font-weight: bold;">
                    Level: <?= $user_level ?> (<?= $_SESSION['user'] ?>)
                </span>
            </div>
        </div>

        <div class="file-grid" id="file-grid">
            <?php if ($current_dir): ?>
                <?php
                    $up_dir = dirname($current_dir);
                    if ($up_dir === '.') $up_dir = '';
                    $up_link = "?dir=" . urlencode($up_dir);
                ?>
                <div class="item up-folder" onclick="window.location.href='<?= $up_link ?>'">
                    <i class="fas fa-folder"></i>
                    <span class="name">..</span>
                </div>
            <?php endif; ?>

            <?php foreach ($items as $item): ?>
                <?php
                    $item_path = $absolute_path . '/' . $item;
                    $is_dir = is_dir($item_path);
                    $icon = $is_dir ? 'fa-folder' : 'fa-file-alt';
                    $link = $is_dir ? "?dir=" . urlencode($current_dir . ($current_dir ? '/' : '') . $item) : "?dir=" . urlencode($current_dir) . "&download=" . urlencode($item);
                ?>
                <div class="item">
                    <input type="checkbox" class="item-checkbox" value="<?= htmlspecialchars($item) ?>">

                    <a href="<?= $link ?>" style="text-decoration: none; display: block;">
                        <i class="fas <?= $icon ?>"></i>
                        <span class="name"><?= htmlspecialchars($item) ?></span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (isset($_SESSION['error_msg'])): ?>
    <div id="errorModal" class="modal" style="display: flex;">
        <div class="modal-content error-border">
            <h3 class="error-text"><i class="fas fa-exclamation-triangle"></i> Upload Failed</h3>
            <p style="font-size: 14px; line-height: 1.5; margin: 20px 0;"><?= htmlspecialchars($_SESSION['error_msg']) ?></p>
            <div class="modal-btns">
                <button class="btn-cancel" onclick="hideModal('errorModal')" style="width: 100%;">Close</button>
            </div>
        </div>
    </div>
    <?php unset($_SESSION['error_msg']); // Clear so it only shows once ?>
    <?php endif; ?>

    <div id="folderModal" class="modal">
        <div class="modal-content">
            <h3>Create New Folder</h3>
            <input type="text" id="newFolderInput" placeholder="Folder Name">
            <div class="modal-btns">
                <button class="btn-cancel" onclick="hideModal('folderModal')">Cancel</button>
                <button class="btn-confirm" onclick="createFolder()">Create</button>
            </div>
        </div>
    </div>

    <div id="renameModal" class="modal">
        <div class="modal-content">
            <h3>Rename Item</h3>
            <p id="renameTargetName" style="font-size: 12px; color: #666;"></p>
            <input type="text" id="renameInput" placeholder="New Name">
            <div class="modal-btns">
                <button class="btn-cancel" onclick="hideModal('renameModal')">Cancel</button>
                <button class="btn-confirm" onclick="executeRename()">Rename</button>
            </div>
        </div>
    </div>

    <script>
        function showModal(id) { document.getElementById(id).style.display = 'flex'; }
        function hideModal(id) { document.getElementById(id).style.display = 'none'; }

        function updateSelectedFiles() {
            const checkboxes = document.querySelectorAll('.item-checkbox:checked');
            const container = document.getElementById('selected-files-container');
            container.innerHTML = '';
            checkboxes.forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_files[]';
                input.value = cb.value;
                container.appendChild(input);
            });
            return checkboxes.length;
        }

        function submitForm(action) {
            document.getElementById('form-action').value = action;
            document.getElementById('action-form').submit();
        }

        function createFolder() {
            const name = document.getElementById('newFolderInput').value;
            if (name.trim() !== '') {
                document.getElementById('folder-name-input').value = name;
                submitForm('create_folder');
            }
        }

        function promptRename() {
            const count = updateSelectedFiles();
            if (count !== 1) {
                alert('Please select exactly one item to rename.');
                return;
            }
            const selectedName = document.querySelector('.item-checkbox:checked').value;
            document.getElementById('renameTargetName').innerText = "Current: " + selectedName;
            document.getElementById('renameInput').value = selectedName;
            showModal('renameModal');
        }

        function executeRename() {
            const newName = document.getElementById('renameInput').value;
            if (newName.trim() !== '') {
                document.getElementById('new-name-input').value = newName;
                submitForm('rename');
            }
        }

        function promptDelete() {
            const count = updateSelectedFiles();
            if (count === 0) {
                alert('Please select items to delete.');
                return;
            }
            if (confirm(`Are you sure you want to delete ${count} selected item(s)?`)) {
                submitForm('delete');
            }
        }
    </script>
<?php endif; ?>
</body>
</html>
