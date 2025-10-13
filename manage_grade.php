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

// Handle DELETE request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $delete_id = (int)$_GET['delete'];
        
        // Check if grade is being used by any students
        $check_usage = $pdo->prepare("SELECT COUNT(*) FROM primary_users WHERE grade = ?");
        $check_usage->execute([$delete_id]);
        $usage_count = $check_usage->fetchColumn();
        
        if ($usage_count > 0) {
            $error = "Cannot delete this grade. It is currently assigned to {$usage_count} student(s).";
        } else {
            $delete_stmt = $pdo->prepare("DELETE FROM primary_grades WHERE id = ?");
            $delete_stmt->execute([$delete_id]);
            $message = 'Grade deleted successfully!';
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Handle EDIT request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_grade'])) {
    $edit_id = (int)$_POST['edit_id'];
    $grade_name = trim($_POST['grade_name']);
    $grade_number = trim($_POST['grade_number']);
    $description = trim($_POST['description']);
    
    if (empty($grade_name) || empty($grade_number)) {
        $error = 'Grade name and number are required.';
    } else {
        try {
            // Check if grade name or number already exists (excluding current record)
            $check_stmt = $pdo->prepare("SELECT id FROM primary_grades WHERE (grade_name = ? OR grade_number = ?) AND id != ?");
            $check_stmt->execute([$grade_name, $grade_number, $edit_id]);
            
            if ($check_stmt->rowCount() > 0) {
                $error = 'Grade name or number already exists.';
            } else {
                // Update grade
                $update_stmt = $pdo->prepare("UPDATE primary_grades SET grade_name = ?, grade_number = ?, description = ?, updated_at = NOW() WHERE id = ?");
                $update_stmt->execute([$grade_name, $grade_number, $description, $edit_id]);
                
                $message = 'Grade updated successfully!';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Fetch all grades
try {
    $grades_stmt = $pdo->prepare("
        SELECT g.*,
               (SELECT COUNT(*) FROM primary_users u WHERE u.grade = g.grade_number) as student_count
        FROM primary_grades g
        ORDER BY g.grade_number ASC
    ");
    $grades_stmt->execute();
    $grades = $grades_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
    $grades = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Grades - SmartLearn Admin</title>
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
            gap: 10px;
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
        #theme-toggle {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            border: 1px solid rgba(102, 126, 234, 0.2);
            padding: 12px 18px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 16px;
        }
        #theme-toggle:hover {
            background: #667eea;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        .dark-mode {
            --bg-gradient: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            --header-bg: rgba(30, 30, 30, 0.98);
            --sidebar-bg: rgba(30, 30, 30, 0.98);
            --center-bg: rgba(30, 30, 30, 0.95);
            --footer-bg: rgba(20, 20, 20, 0.98);
            --text-color: #e0e0e0;
            --accent: #667eea;
        }
        .dark-mode body {
            background: var(--bg-gradient);
            color: var(--text-color);
        }
        .dark-mode .header {
            background: var(--header-bg);
            color: var(--text-color);
        }
        .dark-mode .sidebar {
            background: var(--sidebar-bg);
        }
        .dark-mode .center-space {
            background: var(--center-bg);
        }
        .dark-mode .footer {
            background: var(--footer-bg);
        }
        .dark-mode .menu a {
            background: rgba(40, 40, 40, 0.95);
            color: var(--text-color);
        }
        .dark-mode .menu a:hover {
            background: var(--accent);
        }
        .dark-mode .logo-section h2 {
            color: var(--text-color);
        }
        .dark-mode .footer-nav a {
            color: var(--text-color);
        }
        .dark-mode .footer-nav a:hover {
            color: var(--accent);
        }
        .dark-mode .contact-info p {
            color: var(--text-color);
        }
        .dark-mode .header-content h1 {
            color: var(--text-color);
        }
        .dark-mode .header-content div {
            color: var(--accent);
        }
        .dark-mode .sidebar-content {
            background: rgba(30, 30, 30, 0.8);
        }
        .dark-mode .logo-section p {
            color: var(--text-color);
        }
        .dark-mode .footer-logo p {
            color: var(--text-color);
        }
        .dark-mode .footer-copyright {
            color: var(--text-color);
        }
        .dark-mode .container {
            background: rgba(30, 30, 30, 0.95);
            color: var(--text-color);
        }
        .dark-mode .grades-table {
            background: rgba(40, 40, 40, 0.95);
            color: var(--text-color);
        }
        .dark-mode .grades-table th {
            background: var(--accent);
            color: white;
        }
        .dark-mode .grades-table td {
            background: rgba(40, 40, 40, 0.95);
            color: var(--text-color);
            border-bottom-color: rgba(255, 255, 255, 0.1);
        }
        .dark-mode .grades-table tr:hover {
            background: rgba(60, 60, 60, 0.95);
        }
        .dark-mode .student-count {
            background: rgba(60, 60, 60, 0.95);
            color: var(--text-color);
        }
        .dark-mode .modal-content {
            background: rgba(40, 40, 40, 0.95);
            color: var(--text-color);
        }
        .dark-mode .form-group label {
            color: var(--text-color);
        }
        .dark-mode .form-group input,
        .dark-mode .form-group textarea {
            background: rgba(50, 50, 50, 0.95);
            color: var(--text-color);
            border-color: rgba(102, 126, 234, 0.3);
        }
        .dark-mode .form-group input:focus,
        .dark-mode .form-group textarea:focus {
            border-color: var(--accent);
        }
        .dark-mode .message {
            background-color: rgba(40, 167, 69, 0.2);
            color: #d4edda;
            border-color: rgba(40, 167, 69, 0.3);
        }
        .dark-mode .error {
            background-color: rgba(220, 53, 69, 0.2);
            color: #f8d7da;
            border-color: rgba(220, 53, 69, 0.3);
        }
        .dark-mode .no-grades {
            color: var(--text-color);
        }

        .back-link {
            background-color: #6c757d;
            color: white !important;
        }

        .back-link:hover {
            background-color: #5a6268 !important;
        }

        .add-link {
            background-color: #28a745;
            color: white !important;
        }

        .add-link:hover {
            background-color: #218838 !important;
        }

        .container {
            max-width: 1200px;
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

        .grades-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .grades-table th,
        .grades-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .grades-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .grades-table tr:hover {
            background-color: #f8f9fa;
        }

        .grades-table tr:last-child td {
            border-bottom: none;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-block;
        }

        .btn-edit {
            background-color: #007bff;
            color: white;
        }

        .btn-edit:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
        }

        .btn-delete {
            background-color: #dc3545;
            color: white;
        }

        .btn-delete:hover {
            background-color: #c82333;
            transform: translateY(-2px);
        }

        .no-grades {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 18px;
        }

        .student-count {
            background-color: #e9ecef;
            color: #495057;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #ddd;
        }

        .modal-header h3 {
            color: #333;
            font-size: 24px;
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }

        .close:hover {
            color: #000;
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
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .btn-primary {
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

        .btn-primary:hover {
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }

            .grades-table {
                font-size: 14px;
            }

            .grades-table th,
            .grades-table td {
                padding: 10px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .modal-content {
                margin: 10% auto;
                width: 95%;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-section">
            <h1>SmartLearn Admin - Manage Grades</h1>
        </div>
        <div class="nav-links">
            <a href="add_grade.php" class="add-link">Add New Grade</a>
            <a href="admin.php" class="back-link">Back to Dashboard</a>
            <button id="theme-toggle" class="btn">🌙</button>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <h2>Manage Primary Grades</h2>
        
        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (empty($grades)): ?>
            <div class="no-grades">
                <p>No grades found. <a href="add_grade.php">Add your first grade</a></p>
            </div>
        <?php else: ?>
            <table class="grades-table">
                <thead>
                    <tr>
                        <th>Grade Number</th>
                        <th>Grade Name</th>
                        <th>Description</th>
                        <th>Students Enrolled</th>
                        <th>Created Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($grades as $grade): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($grade['grade_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($grade['grade_name']); ?></td>
                            <td><?php echo htmlspecialchars($grade['description'] ?: 'No description'); ?></td>
                            <td>
                                <span class="student-count">
                                    <?php echo $grade['student_count']; ?> student<?php echo $grade['student_count'] != 1 ? 's' : ''; ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($grade['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-edit" onclick="editGrade(<?php echo $grade['id']; ?>, '<?php echo htmlspecialchars($grade['grade_name'], ENT_QUOTES); ?>', <?php echo $grade['grade_number']; ?>, '<?php echo htmlspecialchars($grade['description'], ENT_QUOTES); ?>')">
                                        Edit
                                    </button>
                                    <a href="?delete=<?php echo $grade['id']; ?>" 
                                       class="btn btn-delete" 
                                       onclick="return confirm('Are you sure you want to delete this grade? This action cannot be undone.')">
                                        Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Grade</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" id="edit_id" name="edit_id">
                <input type="hidden" name="edit_grade" value="1">
                
                <div class="form-group">
                    <label for="edit_grade_name">Grade Name:</label>
                    <input type="text" id="edit_grade_name" name="grade_name" required>
                </div>

                <div class="form-group">
                    <label for="edit_grade_number">Grade Number:</label>
                    <input type="number" id="edit_grade_number" name="grade_number" min="1" max="8" required>
                </div>

                <div class="form-group">
                    <label for="edit_description">Description:</label>
                    <textarea id="edit_description" name="description" placeholder="Brief description of the grade level"></textarea>
                </div>

                <button type="submit" class="btn-primary">Update Grade</button>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('theme-toggle').addEventListener('click', function() {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            this.textContent = isDark ? '☀️' : '🌙';
        });
        function editGrade(id, name, number, description) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_grade_name').value = name;
            document.getElementById('edit_grade_number').value = number;
            document.getElementById('edit_description').value = description;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>

