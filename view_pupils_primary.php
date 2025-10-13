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
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$grade_filter = isset($_GET['grade']) ? (int)$_GET['grade'] : 0;

// Add status column to primary_users table if it doesn't exist
try {
    $pdo->exec("ALTER TABLE primary_users ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active'");
} catch (PDOException $e) {
    // Column might already exist, continue
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $user_id = (int)$_POST['user_id'];
        $new_status = $_POST['status'];
        
        if (in_array($new_status, ['active', 'inactive'])) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND education_level = 'primary'");
                $stmt->execute([$new_status, $user_id]);
                
                $message = 'Pupil status updated successfully!';
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['bulk_action'])) {
        $action = $_POST['bulk_action'];
        $selected_pupils = $_POST['selected_pupils'] ?? [];
        
        if (!empty($selected_pupils) && in_array($action, ['activate', 'deactivate'])) {
            try {
                $status = ($action === 'activate') ? 'active' : 'inactive';
                $placeholders = str_repeat('?,', count($selected_pupils) - 1) . '?';
                $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id IN ($placeholders) AND education_level = 'primary'");
                $stmt->execute(array_merge([$status], $selected_pupils));
                
                $count = count($selected_pupils);
                $message = "$count pupil(s) " . ($action === 'activate' ? 'activated' : 'deactivated') . " successfully!";
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Build query based on filters
$where_conditions = ["education_level = 'primary'"];
$params = [];

if ($filter === 'active') {
    $where_conditions[] = "status = 'active'";
} elseif ($filter === 'inactive') {
    $where_conditions[] = "status = 'inactive'";
}

if ($grade_filter > 0) {
    $where_conditions[] = "grade = ?";
    $params[] = $grade_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR username LIKE ? OR school_name LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

$where_clause = implode(' AND ', $where_conditions);

// Fetch pupils
$pupils = [];
$total_pupils = 0;
$active_pupils = 0;
$inactive_pupils = 0;

try {
    // Get filtered pupils
    $pupils_stmt = $pdo->prepare("
        SELECT id, first_name, last_name, username, school_name, grade, status, created_at
        FROM primary_users
        WHERE $where_clause
        ORDER BY created_at DESC
    ");
    $pupils_stmt->execute($params);
    $pupils = $pupils_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stats_stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive
        FROM primary_users
    ");
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    $total_pupils = $stats['total'];
    $active_pupils = $stats['active'];
    $inactive_pupils = $stats['inactive'];
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Get grades for filter dropdown
$grades = [];
try {
    $grades_stmt = $pdo->prepare("SELECT * FROM primary_grades ORDER BY grade_number ASC");
    $grades_stmt->execute();
    $grades = $grades_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Continue without grades
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Primary Pupils - SmartLearn Admin</title>
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

        .logo-section h1 {
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
        .dark-mode .card {
            background: rgba(30, 30, 30, 0.95);
            color: var(--text-color);
        }
        .dark-mode .stat-card {
            background: rgba(40, 40, 40, 0.95);
            color: var(--text-color);
        }
        .dark-mode .stat-number {
            color: var(--text-color);
        }
        .dark-mode .stat-label {
            color: var(--text-color);
        }
        .dark-mode .filters-section {
            background: rgba(40, 40, 40, 0.95);
            color: var(--text-color);
        }
        .dark-mode .filter-group label {
            color: var(--text-color);
        }
        .dark-mode .filter-group select,
        .dark-mode .filter-group input {
            background: rgba(50, 50, 50, 0.95);
            color: var(--text-color);
            border-color: rgba(102, 126, 234, 0.3);
        }
        .dark-mode .filter-group select:focus,
        .dark-mode .filter-group input:focus {
            border-color: var(--accent);
        }
        .dark-mode .filter-tab {
            background: rgba(60, 60, 60, 0.95);
            color: var(--text-color);
        }
        .dark-mode .filter-tab.active {
            background: var(--accent);
        }
        .dark-mode .pupils-table {
            background: rgba(40, 40, 40, 0.95);
            color: var(--text-color);
        }
        .dark-mode .pupils-table th {
            background: var(--accent);
            color: white;
        }
        .dark-mode .pupils-table td {
            background: rgba(40, 40, 40, 0.95);
            color: var(--text-color);
            border-bottom-color: rgba(255, 255, 255, 0.1);
        }
        .dark-mode .pupils-table tr:hover {
            background: rgba(60, 60, 60, 0.95);
        }
        .dark-mode .status-badge.active {
            background-color: rgba(40, 167, 69, 0.2);
            color: #d4edda;
        }
        .dark-mode .status-badge.inactive {
            background-color: rgba(220, 53, 69, 0.2);
            color: #f8d7da;
        }
        .dark-mode .bulk-actions {
            background: rgba(40, 40, 40, 0.95);
            color: var(--text-color);
        }
        .dark-mode .bulk-actions select,
        .dark-mode .bulk-actions button {
            background: rgba(50, 50, 50, 0.95);
            color: var(--text-color);
            border-color: rgba(102, 126, 234, 0.3);
        }
        .dark-mode .empty-state {
            color: var(--text-color);
        }
        .dark-mode .search-box input {
            background: rgba(50, 50, 50, 0.95);
            color: var(--text-color);
            border-color: rgba(102, 126, 234, 0.3);
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

        .back-link {
            background-color: #6c757d;
            color: white !important;
        }

        .back-link:hover {
            background-color: #5a6268 !important;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            margin-bottom: 20px;
        }

        .card h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 24px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #667eea;
        }

        .stat-card.active {
            border-left-color: #28a745;
        }

        .stat-card.inactive {
            border-left-color: #dc3545;
        }

        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .filters-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-group label {
            font-weight: 600;
            color: #555;
        }

        .filter-group select,
        .filter-group input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .filter-tab {
            padding: 10px 20px;
            background: #e9ecef;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            color: #495057;
            font-weight: 600;
            transition: all 0.3s;
        }

        .filter-tab.active {
            background: #667eea;
            color: white;
        }

        .filter-tab:hover {
            background: #495057;
            color: white;
        }

        .pupils-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .pupils-table th,
        .pupils-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .pupils-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .pupils-table tr:hover {
            background-color: #f8f9fa;
        }

        .pupils-table tr:last-child td {
            border-bottom: none;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.active {
            background-color: #d4edda;
            color: #155724;
        }

        .status-badge.inactive {
            background-color: #f8d7da;
            color: #721c24;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            transition: transform 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-small {
            padding: 4px 8px;
            font-size: 12px;
        }

        .btn-success {
            background: #28a745;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-danger {
            background: #dc3545;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .bulk-actions {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .bulk-actions select,
        .bulk-actions button {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .search-box {
            position: relative;
            max-width: 300px;
        }

        .search-box input {
            width: 100%;
            padding: 8px 35px 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .search-box::after {
            content: "🔍";
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .filters-section {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                justify-content: space-between;
            }

            .pupils-table {
                font-size: 14px;
            }

            .pupils-table th,
            .pupils-table td {
                padding: 10px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .bulk-actions {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-section">
            <h1>SmartLearn Admin - View Primary Pupils (<?php echo $total_pupils; ?>)</h1>
        </div>
        <div class="nav-links">
            <a href="admin.php" class="back-link">Back to Dashboard</a>
            <button id="theme-toggle" class="btn">🌙</button>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_pupils; ?></div>
                <div class="stat-label">Total Pupils</div>
            </div>
            <div class="stat-card active">
                <div class="stat-number"><?php echo $active_pupils; ?></div>
                <div class="stat-label">Active Pupils</div>
            </div>
            <div class="stat-card inactive">
                <div class="stat-number"><?php echo $inactive_pupils; ?></div>
                <div class="stat-label">Inactive Pupils</div>
            </div>
        </div>

        <div class="card">
            <h2>Primary School Pupils</h2>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <a href="?filter=all&search=<?php echo urlencode($search); ?>&grade=<?php echo $grade_filter; ?>" 
                   class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    All Pupils (<?php echo $total_pupils; ?>)
                </a>
                <a href="?filter=active&search=<?php echo urlencode($search); ?>&grade=<?php echo $grade_filter; ?>" 
                   class="filter-tab <?php echo $filter === 'active' ? 'active' : ''; ?>">
                    Active (<?php echo $active_pupils; ?>)
                </a>
                <a href="?filter=inactive&search=<?php echo urlencode($search); ?>&grade=<?php echo $grade_filter; ?>" 
                   class="filter-tab <?php echo $filter === 'inactive' ? 'active' : ''; ?>">
                    Inactive (<?php echo $inactive_pupils; ?>)
                </a>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: center; width: 100%;">
                    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                    
                    <div class="filter-group">
                        <label>Grade:</label>
                        <select name="grade" onchange="this.form.submit()">
                            <option value="0">All Grades</option>
                            <?php foreach ($grades as $grade): ?>
                                <option value="<?php echo $grade['grade_number']; ?>" 
                                    <?php echo $grade_filter == $grade['grade_number'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($grade['grade_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group search-box">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search pupils..." onchange="this.form.submit()">
                    </div>

                    <button type="submit" class="btn btn-small">Apply Filters</button>
                    <a href="view_pupils_primary.php" class="btn btn-small btn-secondary">Clear Filters</a>
                </form>
            </div>

            <?php if (!empty($pupils)): ?>
                <!-- Bulk Actions -->
                <form method="POST" id="bulkForm">
                    <div class="bulk-actions">
                        <label>
                            <input type="checkbox" id="selectAll"> Select All
                        </label>
                        <select name="bulk_action">
                            <option value="">Bulk Actions</option>
                            <option value="activate">Activate Selected</option>
                            <option value="deactivate">Deactivate Selected</option>
                        </select>
                        <button type="submit" class="btn btn-small" onclick="return confirmBulkAction()">Apply</button>
                    </div>

                    <!-- Pupils Table -->
                    <table class="pupils-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAllHeader"></th>
                                <th>Name</th>
                                <th>Username</th>
                                <th>School</th>
                                <th>Grade</th>
                                <th>Status</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pupils as $pupil): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_pupils[]" value="<?php echo $pupil['id']; ?>" class="pupil-checkbox">
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($pupil['first_name'] . ' ' . $pupil['last_name']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($pupil['username']); ?></td>
                                    <td><?php echo htmlspecialchars($pupil['school_name']); ?></td>
                                    <td>Grade <?php echo $pupil['grade']; ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $pupil['status']; ?>">
                                            <?php echo ucfirst($pupil['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($pupil['created_at'])); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $pupil['id']; ?>">
                                            <input type="hidden" name="status" value="<?php echo $pupil['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                            <button type="submit" name="update_status" 
                                                    class="btn btn-small <?php echo $pupil['status'] === 'active' ? 'btn-danger' : 'btn-success'; ?>"
                                                    onclick="return confirm('Are you sure you want to <?php echo $pupil['status'] === 'active' ? 'deactivate' : 'activate'; ?> this pupil?')">
                                                <?php echo $pupil['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>

            <?php else: ?>
                <div class="empty-state">
                    <div>👥</div>
                    <h3>No Pupils Found</h3>
                    <p>
                        <?php if (!empty($search) || $grade_filter > 0 || $filter !== 'all'): ?>
                            No pupils match your current filters. Try adjusting your search criteria.
                        <?php else: ?>
                            No primary school pupils have registered yet.
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($search) || $grade_filter > 0 || $filter !== 'all'): ?>
                        <a href="view_pupils_primary.php" class="btn" style="margin-top: 15px;">Clear Filters</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.getElementById('theme-toggle').addEventListener('click', function() {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            this.textContent = isDark ? '☀️' : '🌙';
        });
        // Select all functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.pupil-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        document.getElementById('selectAllHeader').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.pupil-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            document.getElementById('selectAll').checked = this.checked;
        });

        // Update select all when individual checkboxes change
        document.querySelectorAll('.pupil-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allCheckboxes = document.querySelectorAll('.pupil-checkbox');
                const checkedCheckboxes = document.querySelectorAll('.pupil-checkbox:checked');

                document.getElementById('selectAll').checked = allCheckboxes.length === checkedCheckboxes.length;
                document.getElementById('selectAllHeader').checked = allCheckboxes.length === checkedCheckboxes.length;
            });
        });

        function confirmBulkAction() {
            const selectedPupils = document.querySelectorAll('.pupil-checkbox:checked');
            const action = document.querySelector('select[name="bulk_action"]').value;

            if (selectedPupils.length === 0) {
                alert('Please select at least one pupil.');
                return false;
            }

            if (!action) {
                alert('Please select an action.');
                return false;
            }

            const actionText = action === 'activate' ? 'activate' : 'deactivate';
            return confirm(`Are you sure you want to ${actionText} ${selectedPupils.length} selected pupil(s)?`);
        }
    </script>
</body>
</html>







