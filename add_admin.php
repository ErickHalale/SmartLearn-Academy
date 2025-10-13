<?php
include 'includes/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit();
}

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match';
    }

    if (!$errors) {
        try {
            // unique constraints
            $check = $pdo->prepare('SELECT id FROM admins WHERE email = :email OR username = :username LIMIT 1');
            $check->execute([':email' => $email, ':username' => $username]);
            if ($check->fetch()) {
                $errors[] = 'Email or Username already exists';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins = $pdo->prepare('INSERT INTO admins (first_name, last_name, username, email, password_hash) VALUES (:first_name, :last_name, :username, :email, :hash)');
                if ($ins->execute([':first_name' => $first_name, ':last_name' => $last_name, ':username' => $username, ':email' => $email, ':hash' => $hash])) {
                    $success = 'Admin created successfully';
                } else {
                    $errors[] = 'Failed to create admin';
                }
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Admin</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f5f7fa; margin:0; }
        .header { background:#2c3e50; color:#fff; padding:16px 24px; }
        .container { max-width:600px; margin:24px auto; background:#fff; padding:24px; border-radius:8px; box-shadow:0 4px 16px rgba(0,0,0,.08); }
        label { display:block; margin:12px 0 6px; }
        input { width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; }
        .btn { margin-top:14px; padding:10px 14px; background:#3498db; color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:600; }
        .alert { padding:10px 12px; border-radius:6px; margin-bottom:12px; }
        .alert.error { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
        .alert.success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
        a.link { display:inline-block; margin-top:10px; }
    </style>
    </head>
<body>
    <div class="header"><h1>Add Admin</h1></div>
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
        <form method="post" action="">
            <label for="first_name">First Name</label>
            <input type="text" id="first_name" name="first_name" required value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : '';?>">
            <label for="last_name">Last Name</label>
            <input type="text" id="last_name" name="last_name" required value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : '';?>">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '';?>">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '';?>">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
            <label for="confirm">Confirm Password</label>
            <input type="password" id="confirm" name="confirm" required>
            <button class="btn" type="submit">Create Admin</button>
        </form>
        <a class="link" href="admin.php">Back to Admin Dashboard</a>
    </div>
</body>
</html>


