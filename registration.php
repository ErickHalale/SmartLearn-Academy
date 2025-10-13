<?php
include 'includes/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $first_name = trim($_POST['firstName']);
    $last_name = trim($_POST['lastName']);
    $username = trim($_POST['username'] ?? '');
    $school_name = trim($_POST['schoolName']);
    $education_level = $_POST['educationLevel'];
    // Normalize optional numeric fields: coerce empty string to NULL, otherwise cast to int
    $grade = isset($_POST['grade']) && $_POST['grade'] !== '' ? (int)$_POST['grade'] : null;
    $form = isset($_POST['form']) && $_POST['form'] !== '' ? (int)$_POST['form'] : null;
    $password = $_POST['password'];
    
    // Validate form data
    $errors = [];
    
    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($last_name)) $errors[] = "Last name is required";
    if (empty($school_name)) $errors[] = "School name is required";
    if (empty($username)) $errors[] = "Username is required";
    if (empty($education_level)) $errors[] = "Education level is required";
    
    if ($education_level == 'primary' && empty($grade)) {
        $errors[] = "Grade is required for primary level";
    }
    
    if ($education_level == 'secondary' && empty($form)) {
        $errors[] = "Form is required for secondary level";
    }
    
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    // If no errors, insert into database
    if (empty($errors)) {
        try {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Ensure username is unique across primary and secondary
            $checkPrimary = $pdo->prepare('SELECT id FROM primary_users WHERE username = :username LIMIT 1');
            $checkPrimary->execute([':username' => $username]);
            $checkSecondary = $pdo->prepare('SELECT id FROM secondary_users WHERE username = :username LIMIT 1');
            $checkSecondary->execute([':username' => $username]);
            if ($checkPrimary->fetch() || $checkSecondary->fetch()) {
                $errors[] = "Username is already taken";
            }

            // Prepare SQL statement
            if (empty($errors)) {
                $targetTable = ($education_level === 'secondary') ? 'secondary_users' : 'primary_users';
                $sql = "INSERT INTO $targetTable (first_name, last_name, username, school_name, education_level, grade, form, password) 
                        VALUES (:first_name, :last_name, :username, :school_name, :education_level, :grade, :form, :password)";
            
                $stmt = $pdo->prepare($sql);
            
            // Bind parameters
            $stmt->bindParam(':first_name', $first_name);
            $stmt->bindParam(':last_name', $last_name);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':school_name', $school_name);
            $stmt->bindParam(':education_level', $education_level);
            // Bind nullable integers with correct PDO types
            $stmt->bindValue(':grade', $grade, $grade === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':form', $form, $form === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $stmt->bindParam(':password', $hashed_password);
            
                // Execute the statement
                if ($stmt->execute()) {
                    $success = "Registration successful! You can now login.";
                } else {
                    $errors[] = "Something went wrong. Please try again.";
                }
            }
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
    <title>Student Registration</title>
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
            max-width: 500px;
        }
        
        .registration-card {
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
        
        input, select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border 0.3s;
        }
        
        input:focus, select:focus {
            border-color: #3498db;
            outline: none;
        }
        
        .row {
            display: flex;
            gap: 15px;
        }
        
        .row .form-group {
            flex: 1;
        }
        
        .password-requirements {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 5px;
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
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: white;
            font-weight: bold;
        }
        
        .login-link a {
            color: #3498db;
            text-decoration: none;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .error {
            color: #e74c3c;
            font-size: 14px;
            margin-top: 5px;
            display: none;
        }
        
        .alert {
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @media (max-width: 600px) {
            .row {
                flex-direction: column;
                gap: 0;
            }
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
        <div class="registration-card">
            <h1>Student Registration</h1>
            <p class="subtitle">Create your student account</p>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form id="registrationForm" method="POST" action="">
                <div class="row">
                    <div class="form-group">
                        <label for="firstName">First Name</label>
                        <input type="text" id="firstName" name="firstName" value="<?php echo isset($_POST['firstName']) ? htmlspecialchars($_POST['firstName']) : ''; ?>" required>
                        <div class="error" id="firstNameError">Please enter your first name</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="lastName">Last Name</label>
                        <input type="text" id="lastName" name="lastName" value="<?php echo isset($_POST['lastName']) ? htmlspecialchars($_POST['lastName']) : ''; ?>" required>
                        <div class="error" id="lastNameError">Please enter your last name</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                    <div class="error" id="usernameError">Please choose a username</div>
                </div>

                <div class="form-group">
                    <label for="schoolName">School Name</label>
                    <input type="text" id="schoolName" name="schoolName" value="<?php echo isset($_POST['schoolName']) ? htmlspecialchars($_POST['schoolName']) : ''; ?>" required>
                    <div class="error" id="schoolNameError">Please enter your school name</div>
                </div>
                
                <div class="form-group">
                    <label for="educationLevel">Education Level</label>
                    <select id="educationLevel" name="educationLevel" required>
                        <option value="">Select Level</option>
                        <option value="primary" <?php echo (isset($_POST['educationLevel']) && $_POST['educationLevel'] == 'primary') ? 'selected' : ''; ?>>Primary</option>
                        <option value="secondary" <?php echo (isset($_POST['educationLevel']) && $_POST['educationLevel'] == 'secondary') ? 'selected' : ''; ?>>Secondary</option>
                    </select>
                    <div class="error" id="educationLevelError">Please select your education level</div>
                </div>
                
                <div class="form-group" id="gradeGroup" style="<?php echo (isset($_POST['educationLevel']) && $_POST['educationLevel'] == 'primary') ? 'display: block;' : 'display: none;'; ?>">
                    <label for="grade">Grade</label>
                    <select id="grade" name="grade">
                        <option value="">Select Grade</option>
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo (isset($_POST['grade']) && $_POST['grade'] == $i) ? 'selected' : ''; ?>>Grade <?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                    <div class="error" id="gradeError">Please select your grade</div>
                </div>
                
                <div class="form-group" id="formGroup" style="<?php echo (isset($_POST['educationLevel']) && $_POST['educationLevel'] == 'secondary') ? 'display: block;' : 'display: none;'; ?>">
                    <label for="form">Form</label>
                    <select id="form" name="form">
                        <option value="">Select Form</option>
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo (isset($_POST['form']) && $_POST['form'] == $i) ? 'selected' : ''; ?>>Form <?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                    <div class="error" id="formError">Please select your form</div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div style="position: relative;">
                        <input type="password" id="password" name="password" required style="padding-right: 40px;">
                        <button type="button" onclick="togglePassword('password')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #666; cursor: pointer;">
                            <i class="fas fa-eye" id="password-icon"></i>
                        </button>
                    </div>
                    <div class="password-requirements">Password must be at least 8 characters long</div>
                    <div class="error" id="passwordError">Please enter a valid password (min. 8 characters)</div>
                </div>

                <div class="form-group">
                    <label for="confirmPassword">Confirm Password</label>
                    <div style="position: relative;">
                        <input type="password" id="confirmPassword" name="confirmPassword" required style="padding-right: 40px;">
                        <button type="button" onclick="togglePassword('confirmPassword')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #666; cursor: pointer;">
                            <i class="fas fa-eye" id="confirmPassword-icon"></i>
                        </button>
                    </div>
                    <div class="error" id="confirmPasswordError">Passwords do not match</div>
                </div>
                
                <button type="submit" class="btn">Register</button>
            </form>
            
            <div class="login-link">
                Already have an account? <a href="login.php">Login here</a>
                <br>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registrationForm');
            const educationLevel = document.getElementById('educationLevel');
            const gradeGroup = document.getElementById('gradeGroup');
            const formGroup = document.getElementById('formGroup');
            
            // Show/hide grade or form based on education level
            educationLevel.addEventListener('change', function() {
                if (this.value === 'primary') {
                    gradeGroup.style.display = 'block';
                    formGroup.style.display = 'none';
                    document.getElementById('form').required = false;
                    document.getElementById('grade').required = true;
                } else if (this.value === 'secondary') {
                    gradeGroup.style.display = 'none';
                    formGroup.style.display = 'block';
                    document.getElementById('grade').required = false;
                    document.getElementById('form').required = true;
                } else {
                    gradeGroup.style.display = 'none';
                    formGroup.style.display = 'none';
                    document.getElementById('grade').required = false;
                    document.getElementById('form').required = false;
                }
            });
            
            // Form validation
            form.addEventListener('submit', function(e) {
                let isValid = true;
                
                // Reset errors
                document.querySelectorAll('.error').forEach(error => {
                    error.style.display = 'none';
                });
                
                // Validate first name
                const firstName = document.getElementById('firstName');
                if (!firstName.value.trim()) {
                    document.getElementById('firstNameError').style.display = 'block';
                    isValid = false;
                }
                
                // Validate last name
                const lastName = document.getElementById('lastName');
                if (!lastName.value.trim()) {
                    document.getElementById('lastNameError').style.display = 'block';
                    isValid = false;
                }
                
                // Validate school name
                const schoolName = document.getElementById('schoolName');
                if (!schoolName.value.trim()) {
                    document.getElementById('schoolNameError').style.display = 'block';
                    isValid = false;
                }
                
                // Validate education level
                if (!educationLevel.value) {
                    document.getElementById('educationLevelError').style.display = 'block';
                    isValid = false;
                }
                
                // Validate grade/form based on education level
                if (educationLevel.value === 'primary') {
                    const grade = document.getElementById('grade');
                    if (!grade.value) {
                        document.getElementById('gradeError').style.display = 'block';
                        isValid = false;
                    }
                } else if (educationLevel.value === 'secondary') {
                    const form = document.getElementById('form');
                    if (!form.value) {
                        document.getElementById('formError').style.display = 'block';
                        isValid = false;
                    }
                }
                
                // Validate password
                const password = document.getElementById('password');
                if (password.value.length < 8) {
                    document.getElementById('passwordError').style.display = 'block';
                    isValid = false;
                }
                
                // Validate password confirmation
                const confirmPassword = document.getElementById('confirmPassword');
                if (password.value !== confirmPassword.value) {
                    document.getElementById('confirmPasswordError').style.display = 'block';
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
                }
            });
        });

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