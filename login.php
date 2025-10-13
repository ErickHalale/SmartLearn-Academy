<?php
include 'includes/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    $errors = [];
    
    if (empty($username)) $errors[] = "Username is required";
    if (empty($password)) $errors[] = "Password is required";
    
    if (empty($errors)) {
        try {
            // Try admin first
            $sqlA = "SELECT * FROM admins WHERE username = :username LIMIT 1";
            $stmtA = $pdo->prepare($sqlA);
            $stmtA->bindParam(':username', $username);
            $stmtA->execute();
            $admin = $stmtA->fetch();

            if ($admin && password_verify($password, $admin['password_hash'])) {
                $_SESSION['is_admin'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['first_name'] = $admin['first_name'] ?? null;
                $_SESSION['last_name'] = $admin['last_name'] ?? null;
                header("Location: admin.php");
                exit();
            }

            // Fallback: pupil (user) - check primary first, then secondary
            $sqlP = "SELECT * FROM primary_users WHERE username = :username LIMIT 1";
            $stmtP = $pdo->prepare($sqlP);
            $stmtP->bindParam(':username', $username);
            $stmtP->execute();
            $user = $stmtP->fetch();

            if (!$user) {
                $sqlS = "SELECT * FROM secondary_users WHERE username = :username LIMIT 1";
                $stmtS = $pdo->prepare($sqlS);
                $stmtS->bindParam(':username', $username);
                $stmtS->execute();
                $user = $stmtS->fetch();
            }

            if ($user && password_verify($password, $user['password'])) {
                unset($_SESSION['is_admin']);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['school_name'] = $user['school_name'];
                $_SESSION['education_level'] = $user['education_level'];
                header("Location: pupil.php");
                exit();
            }

            $errors[] = "Invalid username or password";
        } catch(PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: url('../img/element5-digital-OyCl7Y4y0Bk-unsplash.jpg') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .top-header { position: fixed; top:0; left:0; right:0; background:rgba(0,0,0,0.3); backdrop-filter: blur(10px); color:#fff; padding:10px 16px; display:flex; justify-content:space-between; align-items:center; }
        .top-header .brand { display:flex; align-items:center; gap:15px; }
        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #3498db, #2ecc71);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            font-weight: bold;
        }
        .logo-text {
            font-size: 28px;
            font-weight: 800;
            color: white;
        }
        .top-header a { color:#fff; text-decoration:none; font-weight:600; background:#3498db; padding:6px 10px; border-radius:6px; }
        
        .container {
            width: 100%;
            max-width: 400px;
        }
        
        .login-card {
            background-color: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        
        h1 {
            color: white;
            text-align: center;
            margin-bottom: 10px;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.7);
        }

        .subtitle {
            text-align: center;
            color: white;
            margin-bottom: 25px;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.7);
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: white;
            font-weight: bold;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.7);
        }
        
        input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border 0.3s;
        }
        
        input:focus {
            border-color: #3498db;
            outline: none;
        }
        
        .btn {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #2980b9;
        }
        
        .register-link {
            text-align: center;
            margin-top: 20px;
            color: white;
            font-weight: bold;
        }
        
        .register-link a {
            color: #3498db;
            text-decoration: none;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="top-header">
        <div class="brand">
            <div class="logo-icon">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <div class="logo-text">SmartLearn</div>
        </div>
        <div><a href="index.php">Back to Home</a></div>
    </div>
    <div class="container" style="margin-top:60px;">
        <div class="login-card">
            <h1>Student Login</h1>
            <p class="subtitle">Access your student account</p>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div style="position: relative;">
                        <input type="password" id="password" name="password" required style="padding-right: 40px;">
                        <button type="button" onclick="togglePassword('password')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #666; cursor: pointer;">
                            <i class="fas fa-eye" id="password-icon"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn">Login</button>
            </form>
            
            <div class="register-link">
                Don't have an account? <a href="registration.php">Register here</a>
                <br>
               
            </div>
        </div>
    </div>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(inputId + '-icon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }
    </script>
</body>
</html>