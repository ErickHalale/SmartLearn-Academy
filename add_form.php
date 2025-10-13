<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Check if user is admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit();
}

require_once 'includes/config.php';

$message = '';
$error = '';

// Create forms table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS forms (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        form_name VARCHAR(100) NOT NULL,
        form_number TINYINT UNSIGNED NOT NULL,
        description TEXT,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_forms_name (form_name),
        UNIQUE KEY uk_forms_number (form_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) {
    // Table might already exist, continue
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_name = trim($_POST['form_name']);
    $form_number = trim($_POST['form_number']);
    $description = trim($_POST['description']);
    
    if (empty($form_name) || empty($form_number)) {
        $error = 'Form name and number are required.';
    } else {
        try {
            // Check if form already exists
            $check_stmt = $pdo->prepare("SELECT id FROM secondary_forms WHERE form_name = ? OR form_number = ?");
            $check_stmt->execute([$form_name, $form_number]);
            
            if ($check_stmt->rowCount() > 0) {
                $error = 'Form name or number already exists.';
            } else {
                // Insert new form
                $stmt = $pdo->prepare("INSERT INTO secondary_forms (form_name, form_number, description) VALUES (?, ?, ?)");
                $stmt->execute([$form_name, $form_number, $description]);
                
                $message = 'Form added successfully!';
                
                // Clear form
                $_POST = array();
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Form - SmartLearn Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 15px 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
        }

        .logo-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #3498db, #2ecc71);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
        }

        .logo-text {
            font-size: 28px;
            font-weight: 800;
            color: #2c3e50;
        }

        .header h1 {
            color: #333;
            font-size: 24px;
        }

        .nav-links {
            display: flex;
            gap: 20px;
        }

        .nav-links a {
            text-decoration: none;
            color: #333;
            padding: 8px 16px;
            border-radius: 5px;
            transition: background-color 0.3s;
        }

        .nav-links a:hover {
            background-color: #f0f0f0;
        }

        .back-link {
            background-color: #6c757d;
            color: white !important;
        }

        .back-link:hover {
            background-color: #5a6268 !important;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .container h2 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
            font-size: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .container h2::before {
            content: "🎓";
            font-size: 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 600;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-row {
            display: flex;
            gap: 20px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: transform 0.3s;
            width: 100%;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .message {
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }

        .form-info {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid #2196f3;
        }

        .form-info h3 {
            color: #1976d2;
            margin-bottom: 10px;
            font-size: 18px;
        }

        .form-info p {
            color: #424242;
            line-height: 1.5;
            margin-bottom: 8px;
        }

        .form-examples {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
        }

        .form-examples h4 {
            color: #495057;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-examples ul {
            margin-left: 20px;
            color: #6c757d;
        }

        .form-examples li {
            margin-bottom: 3px;
        }

        .input-help {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
                margin: 10px;
            }

            .form-row {
                flex-direction: column;
                gap: 0;
            }

            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-section">
            <a href="index.php" class="logo">
                <div class="logo-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="logo-text">SmartLearn</div>
            </a>
        </div>
        <div class="nav-links">
            <a href="admin.php" class="back-link">Back to Dashboard</a>
            <a href="manage_form.php">Manage Forms</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <h2>Add New Form</h2>

        <div class="form-info">
            <h3>Secondary School Forms</h3>
            <p>Forms are used to organize secondary school students by their academic level. Each form represents a year of study in secondary education.</p>
            
            <div class="form-examples">
                <h4>Common Form Structure:</h4>
                <ul>
                    <li><strong>Form 1:</strong> First year of secondary school</li>
                    <li><strong>Form 2:</strong> Second year of secondary school</li>
                    <li><strong>Form 3:</strong> Third year of secondary school</li>
                    <li><strong>Form 4:</strong> Fourth year of secondary school</li>
                    <li><strong>Form 5:</strong> Fifth year of secondary school (if applicable)</li>
                    <li><strong>Form 6:</strong> Sixth year of secondary school (if applicable)</li>
                </ul>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label for="form_name">Form Name:</label>
                    <input type="text" id="form_name" name="form_name" 
                           value="<?php echo isset($_POST['form_name']) ? htmlspecialchars($_POST['form_name']) : ''; ?>" 
                           placeholder="e.g., Form One, Form Two" required>
                    <div class="input-help">Enter the full name of the form (e.g., "Form One", "Form Two")</div>
                </div>
                
                <div class="form-group">
                    <label for="form_number">Form Number:</label>
                    <select id="form_number" name="form_number" required>
                        <option value="">Select Form Number</option>
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                            <option value="<?php echo $i; ?>" 
                                <?php echo (isset($_POST['form_number']) && $_POST['form_number'] == $i) ? 'selected' : ''; ?>>
                                Form <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <div class="input-help">Select the numerical form level (1-6)</div>
                </div>
            </div>

            <div class="form-group">
                <label for="description">Description (Optional):</label>
                <textarea id="description" name="description" 
                          placeholder="Brief description of the form level and what students typically study"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                <div class="input-help">Provide additional information about this form level (curriculum focus, age group, etc.)</div>
            </div>

            <button type="submit" class="btn">Add Form</button>
        </form>
    </div>

    <script>
        // Auto-generate form name based on form number selection
        document.getElementById('form_number').addEventListener('change', function() {
            const formNumber = this.value;
            const formNameInput = document.getElementById('form_name');
            
            if (formNumber && !formNameInput.value) {
                const formNames = {
                    '1': 'Form One',
                    '2': 'Form Two', 
                    '3': 'Form Three',
                    '4': 'Form Four',
                    '5': 'Form Five',
                    '6': 'Form Six'
                };
                
                formNameInput.value = formNames[formNumber] || '';
            }
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const formName = document.getElementById('form_name').value.trim();
            const formNumber = document.getElementById('form_number').value;
            
            if (!formName) {
                alert('Please enter a form name.');
                e.preventDefault();
                return;
            }
            
            if (!formNumber) {
                alert('Please select a form number.');
                e.preventDefault();
                return;
            }
            
            // Confirm submission
            if (!confirm('Are you sure you want to add this form?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>







