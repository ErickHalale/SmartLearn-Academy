<?php
session_start();
require_once 'includes/config.php';

// Check if admin is logged in
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_fact'])) {
        // Add new fact
        $fact_text = trim($_POST['fact_text']);
        $category = $_POST['category'];
        $display_order = intval($_POST['display_order']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (!empty($fact_text)) {
            $stmt = $pdo->prepare("
                INSERT INTO primary_did_you_know
                (fact_text, category, display_order, is_active)
                VALUES (?, ?, ?, ?)
            ");

            if ($stmt->execute([$fact_text, $category, $display_order, $is_active])) {
                $message = "Fact added successfully!";
            } else {
                $error = "Error adding fact. Please try again.";
            }
        } else {
            $error = "Fact text is required!";
        }
    }
    elseif (isset($_POST['update_fact'])) {
        // Update existing fact
        $fact_id = $_POST['fact_id'];
        $fact_text = trim($_POST['fact_text']);
        $category = $_POST['category'];
        $display_order = intval($_POST['display_order']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (!empty($fact_text)) {
            $stmt = $pdo->prepare("
                UPDATE primary_did_you_know
                SET fact_text = ?, category = ?, display_order = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");

            if ($stmt->execute([$fact_text, $category, $display_order, $is_active, $fact_id])) {
                $message = "Fact updated successfully!";
            } else {
                $error = "Error updating fact. Please try again.";
            }
        } else {
            $error = "Fact text is required!";
        }
    }
    elseif (isset($_POST['delete_fact'])) {
        // Delete fact
        $fact_id = $_POST['fact_id'];
        
        $stmt = $pdo->prepare("DELETE FROM primary_did_you_know WHERE id = ?");
        if ($stmt->execute([$fact_id])) {
            $message = "Fact deleted successfully!";
        } else {
            $error = "Error deleting fact. Please try again.";
        }
    }
}

// Fetch facts for dropdown
$facts = $pdo->query("SELECT id, fact_text FROM primary_did_you_know ORDER BY display_order, created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch specific fact for editing
$edit_fact = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("
        SELECT * FROM primary_did_you_know 
        WHERE id = ?
    ");
    $stmt->execute([$_GET['edit']]);
    $edit_fact = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Primary Facts - SmartLearn</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            padding: 25px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.2rem;
            margin-bottom: 10px;
        }

        .header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .nav-links {
            background: #f8fafc;
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: center;
            gap: 20px;
        }

        .nav-links a {
            text-decoration: none;
            color: #4f46e5;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-links a:hover {
            background: #4f46e5;
            color: white;
        }

        .content {
            padding: 30px;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            margin-bottom: 25px;
            overflow: hidden;
        }

        .card-header {
            background: #f8fafc;
            padding: 18px 25px;
            border-bottom: 1px solid #e2e8f0;
        }

        .card-header h2 {
            color: #1e293b;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-body {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #4f46e5;
            color: white;
        }

        .btn-primary:hover {
            background: #4338ca;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: #dc2626;
            color: white;
        }

        .btn-danger:hover {
            background: #b91c1c;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
        }

        tr:hover {
            background: #f8fafc;
        }

        .fact-text {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-active {
            background: #d1fae5;
            color: #065f46;
        }

        .status-inactive {
            background: #fef3c7;
            color: #92400e;
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        .btn-sm {
            padding: 8px 12px;
            font-size: 0.85rem;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .nav-links {
                flex-direction: column;
                align-items: center;
            }
            
            .actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-lightbulb"></i> Manage Primary Facts</h1>
            <p>Add, edit, and delete educational facts for primary level students</p>
        </div>

        <div class="nav-links">
            <a href="admin.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="manage_facts_secondary.php"><i class="fas fa-school"></i> Secondary Facts</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>

        <div class="content">
            <?php if ($message): ?>
                <div class="alert success">
                    <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert error">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Add/Edit Fact Form -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas <?php echo $edit_fact ? 'fa-edit' : 'fa-plus'; ?>"></i> 
                        <?php echo $edit_fact ? 'Edit Fact' : 'Add New Fact'; ?>
                    </h2>
                </div>
                <div class="card-body">
                    <form method="POST" id="factForm">
                        <?php if ($edit_fact): ?>
                            <input type="hidden" name="fact_id" value="<?php echo $edit_fact['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="fact_text">Fact Text *</label>
                            <textarea name="fact_text" class="form-control" required placeholder="Enter the educational fact..."><?php echo $edit_fact ? htmlspecialchars($edit_fact['fact_text']) : ''; ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="category">Category</label>
                            <select name="category" class="form-control" required>
                                <option value="">Select Category</option>
                                <option value="science" <?php echo $edit_fact && $edit_fact['category'] == 'science' ? 'selected' : ''; ?>>Science</option>
                                <option value="math" <?php echo $edit_fact && $edit_fact['category'] == 'math' ? 'selected' : ''; ?>>Mathematics</option>
                                <option value="history" <?php echo $edit_fact && $edit_fact['category'] == 'history' ? 'selected' : ''; ?>>History</option>
                                <option value="geography" <?php echo $edit_fact && $edit_fact['category'] == 'geography' ? 'selected' : ''; ?>>Geography</option>
                                <option value="biology" <?php echo $edit_fact && $edit_fact['category'] == 'biology' ? 'selected' : ''; ?>>Biology</option>
                                <option value="chemistry" <?php echo $edit_fact && $edit_fact['category'] == 'chemistry' ? 'selected' : ''; ?>>Chemistry</option>
                                <option value="physics" <?php echo $edit_fact && $edit_fact['category'] == 'physics' ? 'selected' : ''; ?>>Physics</option>
                                <option value="literature" <?php echo $edit_fact && $edit_fact['category'] == 'literature' ? 'selected' : ''; ?>>Literature</option>
                                <option value="arts" <?php echo $edit_fact && $edit_fact['category'] == 'arts' ? 'selected' : ''; ?>>Arts</option>
                                <option value="technology" <?php echo $edit_fact && $edit_fact['category'] == 'technology' ? 'selected' : ''; ?>>Technology</option>
                                <option value="general" <?php echo $edit_fact && $edit_fact['category'] == 'general' ? 'selected' : ''; ?>>General</option>
                            </select>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="display_order">Display Order</label>
                                <input type="number" name="display_order" class="form-control" 
                                       value="<?php echo $edit_fact ? $edit_fact['display_order'] : '0'; ?>" 
                                       min="0" required>
                            </div>

                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" name="is_active" id="is_active" 
                                           <?php echo $edit_fact ? ($edit_fact['is_active'] ? 'checked' : '') : 'checked'; ?>>
                                    <label for="is_active">Active (Visible to students)</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <?php if ($edit_fact): ?>
                                <button type="submit" name="update_fact" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Fact
                                </button>
                                <a href="manage_facts_primary.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            <?php else: ?>
                                <button type="submit" name="add_fact" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add Fact
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Delete Fact -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-trash"></i> Delete Fact</h2>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label for="delete_fact_id">Select Fact to Delete</label>
                            <select name="fact_id" id="delete_fact_id" class="form-control" required>
                                <option value="">Select Fact</option>
                                <?php foreach ($facts as $fact): ?>
                                    <option value="<?php echo $fact['id']; ?>"><?php echo htmlspecialchars(substr($fact['fact_text'], 0, 50)); ?>...</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" class="btn btn-primary" onclick="editFact()">
                            <i class="fas fa-edit"></i> Edit Fact
                        </button>
                        <button type="submit" name="delete_fact" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this fact?');">
                            <i class="fas fa-trash"></i> Delete Fact
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <script>
        function editFact() {
            const select = document.getElementById('delete_fact_id');
            const factId = select.value;
            if (factId) {
                window.location.href = 'manage_facts_primary.php?edit=' + factId;
            } else {
                alert('Please select a fact to edit.');
            }
        }
    </script>
</body>
</html>