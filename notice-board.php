<?php
// Start session
session_start();

// Define constants
define("NOTICES_FILE", "notices.json");
define("ADMINS_FILE", "admins.json");

// Function to initialize JSON files if they don't exist
function initializeFiles() {
    if (!file_exists(NOTICES_FILE)) {
        file_put_contents(NOTICES_FILE, json_encode([], JSON_PRETTY_PRINT));
    }
    
    if (!file_exists(ADMINS_FILE)) {
        // Create default admin account if admins file doesn't exist
        $defaultAdmin = [
            [
                'id' => 1,
                'username' => 'admin',
                'password' => password_hash('admin123', PASSWORD_DEFAULT),
                'created_at' => date('Y-m-d H:i:s')
            ]
        ];
        file_put_contents(ADMINS_FILE, json_encode($defaultAdmin, JSON_PRETTY_PRINT));
    }
}

// Initialize files
initializeFiles();

// Function to get all notices
function getNotices() {
    if (file_exists(NOTICES_FILE)) {
        $notices = json_decode(file_get_contents(NOTICES_FILE), true);
        
        // Sort notices by date (newest first)
        usort($notices, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        return $notices;
    }
    return [];
}

// Function to get a single notice by ID
function getNoticeById($id) {
    $notices = getNotices();
    
    foreach ($notices as $notice) {
        if ($notice['id'] == $id) {
            return $notice;
        }
    }
    
    return null;
}

// Function to add a new notice
function addNotice($title, $content) {
    $notices = getNotices();
    
    $newNotice = [
        'id' => time(), // Using timestamp as a simple ID
        'title' => $title,
        'content' => $content,
        'date' => date('Y-m-d H:i:s')
    ];
    
    $notices[] = $newNotice;
    file_put_contents(NOTICES_FILE, json_encode($notices, JSON_PRETTY_PRINT));
    
    return true;
}

// Function to edit a notice
function editNotice($id, $title, $content) {
    $notices = getNotices();
    
    foreach ($notices as $key => $notice) {
        if ($notice['id'] == $id) {
            $notices[$key]['title'] = $title;
            $notices[$key]['content'] = $content;
            $notices[$key]['updated_at'] = date('Y-m-d H:i:s');
            
            file_put_contents(NOTICES_FILE, json_encode($notices, JSON_PRETTY_PRINT));
            return true;
        }
    }
    
    return false;
}

// Function to delete a notice
function deleteNotice($id) {
    $notices = getNotices();
    
    foreach ($notices as $key => $notice) {
        if ($notice['id'] == $id) {
            unset($notices[$key]);
            file_put_contents(NOTICES_FILE, json_encode(array_values($notices), JSON_PRETTY_PRINT));
            return true;
        }
    }
    
    return false;
}

// Function to get all admins
function getAdmins() {
    if (file_exists(ADMINS_FILE)) {
        return json_decode(file_get_contents(ADMINS_FILE), true);
    }
    return [];
}

// Function to add a new admin
function addAdmin($username, $password) {
    $admins = getAdmins();
    
    // Check if username already exists
    foreach ($admins as $admin) {
        if ($admin['username'] === $username) {
            return false;
        }
    }
    
    $newAdmin = [
        'id' => count($admins) + 1,
        'username' => $username,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $admins[] = $newAdmin;
    file_put_contents(ADMINS_FILE, json_encode($admins, JSON_PRETTY_PRINT));
    
    return true;
}

// Function to delete an admin
function deleteAdmin($id) {
    $admins = getAdmins();
    
    // Prevent deleting the last admin
    if (count($admins) <= 1) {
        return false;
    }
    
    foreach ($admins as $key => $admin) {
        if ($admin['id'] == $id) {
            unset($admins[$key]);
            file_put_contents(ADMINS_FILE, json_encode(array_values($admins), JSON_PRETTY_PRINT));
            return true;
        }
    }
    
    return false;
}

// Check if user is logged in as admin
function isAdmin() {
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
}

// Authenticate admin
function authenticateAdmin($username, $password) {
    $admins = getAdmins();
    
    foreach ($admins as $admin) {
        if ($admin['username'] === $username && password_verify($password, $admin['password'])) {
            return true;
        }
    }
    
    return false;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Login form
    if (isset($_POST['action']) && $_POST['action'] === 'login') {
        if (authenticateAdmin($_POST['username'], $_POST['password'])) {
            $_SESSION['admin'] = true;
            $_SESSION['username'] = $_POST['username'];
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $loginError = "Invalid credentials";
        }
    }
    
    // Add notice form
    if (isset($_POST['action']) && $_POST['action'] === 'add_notice' && isAdmin()) {
        if (!empty($_POST['title']) && !empty($_POST['content'])) {
            addNotice($_POST['title'], $_POST['content']);
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $addError = "Title and content are required";
        }
    }
    
    // Edit notice form
    if (isset($_POST['action']) && $_POST['action'] === 'edit_notice' && isAdmin()) {
        if (!empty($_POST['notice_id']) && !empty($_POST['title']) && !empty($_POST['content'])) {
            editNotice($_POST['notice_id'], $_POST['title'], $_POST['content']);
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $editError = "Title and content are required";
        }
    }
    
    // Delete notice
    if (isset($_POST['action']) && $_POST['action'] === 'delete_notice' && isAdmin()) {
        if (isset($_POST['notice_id'])) {
            deleteNotice($_POST['notice_id']);
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
    
    // Add admin form
    if (isset($_POST['action']) && $_POST['action'] === 'add_admin' && isAdmin()) {
        if (!empty($_POST['username']) && !empty($_POST['password']) && !empty($_POST['confirm_password'])) {
            if ($_POST['password'] === $_POST['confirm_password']) {
                if (addAdmin($_POST['username'], $_POST['password'])) {
                    $adminAddSuccess = "Admin added successfully";
                } else {
                    $adminAddError = "Username already exists";
                }
            } else {
                $adminAddError = "Passwords do not match";
            }
        } else {
            $adminAddError = "All fields are required";
        }
    }
    
    // Delete admin
    if (isset($_POST['action']) && $_POST['action'] === 'delete_admin' && isAdmin()) {
        if (isset($_POST['admin_id'])) {
            if (deleteAdmin($_POST['admin_id'])) {
                $adminDeleteSuccess = "Admin deleted successfully";
            } else {
                $adminDeleteError = "Cannot delete the last admin";
            }
        }
    }
    
    // Logout
    if (isset($_POST['action']) && $_POST['action'] === 'logout') {
        session_unset();
        session_destroy();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Get all notices
$notices = getNotices();

// Get notice to edit if editing
$editingNotice = null;
if (isset($_GET['edit']) && isAdmin()) {
    $editingNotice = getNoticeById($_GET['edit']);
}

// Get all admins if admin is logged in
$admins = isAdmin() ? getAdmins() : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>R.V.R. & J.C. College of Engineering - Notice Board</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .header {
            background-color: #fff;
            padding: 10px;
            text-align: center;
            border-bottom: 1px solid #ddd;
        }
        .header img {
            max-width: 100%;
            height: auto;
        }
        .title-bar {
    background-color: #fff;
    padding: 15px;
    border-bottom: 1px solid #ddd;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
}

.title-bar h1 {
    margin: 0;
    font-size: 24px;
    text-align: center;
    width: 100%;
    position: absolute;
    left: 0;
    right: 0;
}

.auth-buttons {
    display: flex;
    gap: 10px;
    position: relative;
    z-index: 1;
    margin-left: auto;
}
        .auth-buttons a, .auth-buttons button {
            display: inline-block;
            padding: 8px 16px;
            background-color: #3333cc;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        .notice {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 20px;
            transition: transform 0.2s;
            text-align: center;
        }
        .notice:hover {
            transform: translateY(-5px);
        }
        .notice h2 {
            margin-top: 0;
            color: #333;
            text-align: center;
        }
        .notice-date {
            color: #777;
            font-size: 14px;
            margin-bottom: 10px;
            text-align: center;
        }
        .notice-content {
            color: #444;
            line-height: 1.5;
            text-align: center;
        }
        .admin-actions {
            margin-top: 15px;
            text-align: center;
            display: flex;
            justify-content: center;
            gap: 10px;
        }
        .admin-actions form {
            display: inline-block;
        }
        .delete-btn {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        .edit-btn {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .login-form, .add-notice-form, .edit-notice-form, .admin-management {
            background-color: #fff;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .form-group textarea {
            height: 150px;
        }
        .submit-btn {
            background-color: #3333cc;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .error {
            color: #e74c3c;
            margin-bottom: 15px;
            text-align: center;
        }
        .success {
            color: #2ecc71;
            margin-bottom: 15px;
            text-align: center;
        }
        .admin-panel {
            margin-bottom: 30px;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin: 20px auto;
        }
        .admin-panel h2 {
            margin-top: 0;
            text-align: center;
        }
        .tabs {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }
        .tab {
            padding: 10px 20px;
            background-color: #f5f5f5;
            border: 1px solid #ddd;
            cursor: pointer;
            margin: 0 5px;
            border-radius: 4px 4px 0 0;
        }
        .tab.active {
            background-color: #fff;
            border-bottom: 1px solid #fff;
        }
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .admin-table th, .admin-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        .admin-table th {
            background-color: #f5f5f5;
        }
        .admin-management h3 {
            text-align: center;
            margin-top: 0;
        }
        .back-link {
            display: block;
            margin-top: 20px;
            text-align: center;
            color: #3333cc;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        @media (max-width: 900px) {
            .container {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 600px) {
            .container {
                grid-template-columns: 1fr;
            }
            .title-bar {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="https://rvrjcce.ac.in/ximages/2.2%20Header%20(1).png" alt="R.V.R. & J.C. College of Engineering" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'100%25\' height=\'100\' viewBox=\'0 0 800 100\'%3E%3Crect fill=\'%23FF0000\' width=\'800\' height=\'100\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' dominant-baseline=\'middle\' text-anchor=\'middle\' font-family=\'Arial\' font-size=\'24\' fill=\'%23FFFFFF\'%3ER.V.R. %26 J.C. COLLEGE OF ENGINEERING%3C/text%3E%3C/svg%3E'">
    </div>
    
    <div class="title-bar">
    <div style="width: 100px;"><!-- Spacer to balance the layout --></div>
    <h1>NOTICE BOARD OF R.V.R & J.C. COLLEGE OF ENGINEERING</h1>
    <div class="auth-buttons">
        <?php if (isAdmin()): ?>
            <a href="?page=admin">ADMIN PANEL</a>
            <form method="post" style="margin: 0;">
                <input type="hidden" name="action" value="logout">
                <button type="submit">LOGOUT</button>
            </form>
        <?php else: ?>
            <a href="?page=login">LOGIN</a>
        <?php endif; ?>
    </div>
</div>
    
    <?php 
    // Handle different pages
    $page = isset($_GET['page']) ? $_GET['page'] : 'notices';
    
    if ($page === 'login' && !isAdmin()): 
    ?>
        <div class="login-form">
            <h2>Admin Login</h2>
            <?php if (isset($loginError)): ?>
                <div class="error"><?php echo $loginError; ?></div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div style="text-align: center;">
                    <button type="submit" class="submit-btn">Login</button>
                </div>
            </form>
        </div>
    <?php elseif ($page === 'admin' && isAdmin()): ?>
        <div class="tabs">
            <div class="tab <?php echo !isset($_GET['tab']) || $_GET['tab'] === 'notices' ? 'active' : ''; ?>" onclick="location.href='?page=admin&tab=notices'">Manage Notices</div>
            <div class="tab <?php echo isset($_GET['tab']) && $_GET['tab'] === 'admins' ? 'active' : ''; ?>" onclick="location.href='?page=admin&tab=admins'">Manage Admins</div>
        </div>
        
        <?php if (!isset($_GET['tab']) || $_GET['tab'] === 'notices'): ?>
            <?php if ($editingNotice): ?>
                <div class="edit-notice-form">
                    <h2>Edit Notice</h2>
                    <?php if (isset($editError)): ?>
                        <div class="error"><?php echo $editError; ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <input type="hidden" name="action" value="edit_notice">
                        <input type="hidden" name="notice_id" value="<?php echo $editingNotice['id']; ?>">
                        <div class="form-group">
                            <label for="title">Title:</label>
                            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($editingNotice['title']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="content">Content:</label>
                            <textarea id="content" name="content" required><?php echo htmlspecialchars($editingNotice['content']); ?></textarea>
                        </div>
                        <div style="text-align: center;">
                            <button type="submit" class="submit-btn">Update Notice</button>
                        </div>
                    </form>
                    <a href="?page=admin" class="back-link">Cancel</a>
                </div>
            <?php else: ?>
                <div class="admin-panel">
                    <h2>Add New Notice</h2>
                    <?php if (isset($addError)): ?>
                        <div class="error"><?php echo $addError; ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <input type="hidden" name="action" value="add_notice">
                        <div class="form-group">
                            <label for="title">Title:</label>
                            <input type="text" id="title" name="title" required>
                        </div>
                        <div class="form-group">
                            <label for="content">Content:</label>
                            <textarea id="content" name="content" required></textarea>
                        </div>
                        <div style="text-align: center;">
                            <button type="submit" class="submit-btn">Publish Notice</button>
                        </div>
                    </form>
                </div>
                
                <div class="container">
                    <?php foreach($notices as $notice): ?>
                        <div class="notice">
                            <h2><?php echo htmlspecialchars($notice['title']); ?></h2>
                            <div class="notice-date">Posted on: <?php echo htmlspecialchars($notice['date']); ?></div>
                            <div class="notice-content"><?php echo nl2br(htmlspecialchars($notice['content'])); ?></div>
                            <div class="admin-actions">
                                <a href="?page=admin&edit=<?php echo $notice['id']; ?>" class="edit-btn">Edit</a>
                                <form method="post" onsubmit="return confirm('Are you sure you want to delete this notice?');">
                                    <input type="hidden" name="action" value="delete_notice">
                                    <input type="hidden" name="notice_id" value="<?php echo $notice['id']; ?>">
                                    <button type="submit" class="delete-btn">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($notices)): ?>
                        <div class="notice">
                            <h2>No notices yet</h2>
                            <div class="notice-content">There are no notices to display at this time.</div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php elseif (isset($_GET['tab']) && $_GET['tab'] === 'admins'): ?>
            <div class="admin-management">
                <h3>Add New Admin</h3>
                <?php if (isset($adminAddError)): ?>
                    <div class="error"><?php echo $adminAddError; ?></div>
                <?php endif; ?>
                <?php if (isset($adminAddSuccess)): ?>
                    <div class="success"><?php echo $adminAddSuccess; ?></div>
                <?php endif; ?>
                <form method="post">
                    <input type="hidden" name="action" value="add_admin">
                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password:</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password:</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    <div style="text-align: center;">
                        <button type="submit" class="submit-btn">Add Admin</button>
                    </div>
                </form>
                
                <h3>Manage Admins</h3>
                <?php if (isset($adminDeleteError)): ?>
                    <div class="error"><?php echo $adminDeleteError; ?></div>
                <?php endif; ?>
                <?php if (isset($adminDeleteSuccess)): ?>
                    <div class="success"><?php echo $adminDeleteSuccess; ?></div>
                <?php endif; ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Created On</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($admins as $admin): ?>
                            <tr>
                                <td><?php echo $admin['id']; ?></td>
                                <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                <td><?php echo htmlspecialchars($admin['created_at']); ?></td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Are you sure you want to delete this admin?');">
                                        <input type="hidden" name="action" value="delete_admin">
                                        <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                        <button type="submit" class="delete-btn" <?php echo count($admins) <= 1 ? 'disabled' : ''; ?>>Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <!-- Default page: display notices -->
        <div class="container">
            <?php foreach($notices as $notice): ?>
                <div class="notice">
                    <h2><?php echo htmlspecialchars($notice['title']); ?></h2>
                    <div class="notice-date">Posted on: <?php echo htmlspecialchars($notice['date']); ?></div>
                    <div class="notice-content"><?php echo nl2br(htmlspecialchars($notice['content'])); ?></div>
                    <?php if (isAdmin()): ?>
                        <div class="admin-actions">
                            <a href="?page=admin&edit=<?php echo $notice['id']; ?>" class="edit-btn">Edit</a>
                            <form method="post" onsubmit="return confirm('Are you sure you want to delete this notice?');">
                                <input type="hidden" name="action" value="delete_notice">
                                <input type="hidden" name="notice_id" value="<?php echo $notice['id']; ?>">
                                <button type="submit" class="delete-btn">Delete</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
            <?php if (empty($notices)): ?>
                <div class="notice">
                    <h2>No notices yet</h2>
                    <div class="notice-content">There are no notices to display at this time.</div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</body>
</html>