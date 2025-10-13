<?php
include 'includes/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit();
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare('DELETE FROM admins WHERE id = :id');
        $stmt->execute([':id' => $id]);
        header('Location: manage_admin.php');
        exit();
    } catch (PDOException $e) {
        // ignore for now, show inline later
    }
}

// Handle edit
$editId = null;
$editEmail = '';
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
$r = $pdo->prepare('SELECT id, first_name, last_name, username, email FROM admins WHERE id = :id');
    $r->execute([':id' => $editId]);
    if ($row = $r->fetch()) {
        $editEmail = $row['email'];
        $editFirst = $row['first_name'];
        $editLast = $row['last_name'];
        $editUser = $row['username'];
    } else {
        $editId = null;
    }
}

$errors = [];
$success = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if ($first_name === '') { $errors[] = 'First name is required'; }
    if ($last_name === '') { $errors[] = 'Last name is required'; }
    if ($username === '') { $errors[] = 'Username is required'; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required';
    }
    if ($password !== '') {
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        }
        if ($password !== $confirm) {
            $errors[] = 'Passwords do not match';
        }
    }

    if (!$errors) {
        try {
            // ensure unique email except self
            $chk = $pdo->prepare('SELECT id FROM admins WHERE (email = :email OR username = :username) AND id <> :id');
            $chk->execute([':email' => $email, ':username' => $username, ':id' => $id]);
            if ($chk->fetch()) {
                $errors[] = 'Email or Username already in use';
            } else {
                if ($password === '') {
                    $upd = $pdo->prepare('UPDATE admins SET first_name = :first_name, last_name = :last_name, username = :username, email = :email WHERE id = :id');
                    $ok = $upd->execute([':first_name' => $first_name, ':last_name' => $last_name, ':username' => $username, ':email' => $email, ':id' => $id]);
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $upd = $pdo->prepare('UPDATE admins SET first_name = :first_name, last_name = :last_name, username = :username, email = :email, password_hash = :hash WHERE id = :id');
                    $ok = $upd->execute([':first_name' => $first_name, ':last_name' => $last_name, ':username' => $username, ':email' => $email, ':hash' => $hash, ':id' => $id]);
                }
                if ($ok) {
                    $success = 'Admin updated successfully';
                } else {
                    $errors[] = 'Failed to update admin';
                }
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Fetch all admins
$admins = [];
try {
$q = $pdo->query('SELECT id, first_name, last_name, username, email, created_at FROM admins ORDER BY id DESC');
    $admins = $q->fetchAll();
} catch (PDOException $e) {
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Admins</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f5f7fa; margin:0; }
        .header { background:#2c3e50; color:#fff; padding:16px 24px; }
        .container { max-width:1000px; margin:24px auto; background:#fff; padding:24px; border-radius:8px; box-shadow:0 4px 16px rgba(0,0,0,.08); }
        table { width:100%; border-collapse: collapse; }
        th, td { padding:10px 8px; border-bottom:1px solid #eee; text-align:left; }
        .btn { display:inline-block; padding:6px 10px; background:#3498db; color:#fff; text-decoration:none; border-radius:6px; font-weight:600; }
        .btn.edit { background:#16a085; }
        .btn.delete { background:#e74c3c; }
        .alert { padding:10px 12px; border-radius:6px; margin-bottom:12px; }
        .alert.error { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
        .alert.success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
        form.inline { display:inline; }
        label { display:block; margin:12px 0 6px; }
        input { width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; }
    </style>
    </head>
<body>
    <div class="header"><h1>Manage Admins</h1></div>
    <div class="container">
        <?php if ($errors): ?>
            <div class="alert error">
                <?php foreach ($errors as $e): ?>
                    <div><?php echo htmlspecialchars($e); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($editId): ?>
            <h2>Edit Admin</h2>
            <form method="post" action="">
                <input type="hidden" name="id" value="<?php echo (int)$editId; ?>">
                <label for="first_name">First Name</label>
                <input type="text" id="first_name" name="first_name" required value="<?php echo htmlspecialchars($editFirst ?? ''); ?>">
                <label for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" required value="<?php echo htmlspecialchars($editLast ?? ''); ?>">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($editUser ?? ''); ?>">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($editEmail); ?>">
                <label for="password">New Password (leave blank to keep)</label>
                <input type="password" id="password" name="password">
                <label for="confirm">Confirm New Password</label>
                <input type="password" id="confirm" name="confirm">
                <button class="btn" type="submit">Save Changes</button>
            </form>
            <hr>
        <?php endif; ?>

        <h2>All Admins</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($admins as $a): ?>
                    <tr>
                        <td><?php echo (int)$a['id']; ?></td>
                        <td><?php echo htmlspecialchars(($a['first_name'] ?? '').' '.($a['last_name'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars($a['username'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($a['email']); ?></td>
                        <td><?php echo htmlspecialchars($a['created_at']); ?></td>
                        <td>
                            <a class="btn edit" href="manage_admin.php?edit=<?php echo (int)$a['id']; ?>">Edit</a>
                            <a class="btn delete" href="manage_admin.php?delete=<?php echo (int)$a['id']; ?>" onclick="return confirm('Delete this admin?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p><a class="btn" href="add_admin.php">Add Admin</a> <a class="btn edit" href="admin.php">Back to Dashboard</a></p>
    </div>
</body>
</html>


