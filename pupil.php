<?php
include 'includes/config.php';
include 'login_check.php';

requireLogin();

$user = getCurrentUser();

// Fetch forms and subjects based on user's education level
$forms = [];
$subjects = [];
$selected_form_id = $_GET['form'] ?? null;
$selected_subject_id = $_GET['subject'] ?? null;
$current_form = null;
$current_subject = null;

// Fetch facts for display
$facts = [];
if ($user) {
    $table = $user['education_level'] === 'secondary' ? 'secondary_did_you_know' : 'primary_did_you_know';
    $facts_stmt = $pdo->prepare("SELECT * FROM $table WHERE is_active = 1 ORDER BY display_order, created_at DESC LIMIT 5");
    $facts_stmt->execute();
    $facts = $facts_stmt->fetchAll(PDO::FETCH_ASSOC);
}

try {
    if ($user && $user['education_level'] === 'secondary') {
        // Fetch forms for secondary students
        $forms_stmt = $pdo->prepare("SELECT * FROM secondary_forms ORDER BY form_number ASC");
        $forms_stmt->execute();
        $forms = $forms_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If a form is selected, fetch subjects for that specific form
        if ($selected_form_id) {
            // Get current form info
            foreach ($forms as $form) {
                if ($form['id'] == $selected_form_id) {
                    $current_form = $form;
                    break;
                }
            }
            
            // Fetch subjects for the selected form
            $subjects_stmt = $pdo->prepare("
                SELECT s.*, f.form_name 
                FROM secondary_subjects s 
                JOIN secondary_forms f ON s.form_id = f.id 
                WHERE s.form_id = ?
                ORDER BY s.subject_name
            ");
            $subjects_stmt->execute([$selected_form_id]);
            $subjects = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Fetch all subjects for secondary students (no form filter)
            $subjects_stmt = $pdo->prepare("
                SELECT s.*, f.form_name 
                FROM secondary_subjects s 
                JOIN secondary_forms f ON s.form_id = f.id 
                ORDER BY f.form_number, s.subject_name
            ");
            $subjects_stmt->execute();
            $subjects = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        // Fetch subjects for primary students
        $subjects_stmt = $pdo->prepare("
            SELECT s.*, g.grade_name
            FROM primary_subjects s
            JOIN primary_grades g ON s.grade_id = g.id
            ORDER BY g.grade_number, s.subject_name
        ");
        $subjects_stmt->execute();
        $subjects = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // Handle database errors gracefully
    error_log("Database error in pupil.php: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        /* Header */
        .header {
            position: sticky;
            top: 0;
            z-index: 1000;
            background-color: #2c3e50;
            color: #ecf0f1;
            padding: 12px 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
        }

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
            font-size: 24px;
            font-weight: 800;
            color: white;
        }

        .header-title {
            font-size: 18px;
            font-weight: 600;
        }

        .header-nav {
            display: flex;
            gap: 12px;
        }

        .header-link {
            color: #ecf0f1;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 6px;
            transition: background-color 0.2s;
        }

        .header-link:hover {
            background-color: rgba(236, 240, 241, 0.12);
        }

        /* Main Layout */
        .main-container {
            display: flex;
            flex: 1;
        }

        /* Sidebar Menu */
        .sidebar-menu {
            width: 240px;
            background-color: #2c3e50;
            color: #ecf0f1;
            height: calc(100vh - 60px);
            position: sticky;
            top: 60px;
            padding: 20px 0;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }

        .sidebar-title {
            font-size: 16px;
            font-weight: 600;
            padding: 0 20px 15px 20px;
            border-bottom: 1px solid rgba(236, 240, 241, 0.1);
            margin-bottom: 15px;
            color: #ecf0f1;
        }

        .sidebar-subtitle {
            font-size: 12px;
            font-weight: 600;
            padding: 0 20px 8px 20px;
            color: #bdc3c7;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 5px;
            padding: 0 15px;
        }

        .side-link {
            color: #ecf0f1;
            text-decoration: none;
            padding: 12px 15px;
            border-radius: 6px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .side-link:hover {
            background-color: rgba(236, 240, 241, 0.1);
            transform: translateX(5px);
        }

        .side-link.active {
            background-color: #3498db;
            color: white;
        }

        .side-link i {
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 30px;
            background-color: #f5f7fa;
        }
        
        .dashboard-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-bottom: 20px;
        }
        
        h1 {
            color: #2c3e50;
            margin-bottom: 20px;
        }
        
        .user-info {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .info-item {
            margin-bottom: 10px;
            display: flex;
        }
        
        .info-label {
            font-weight: 600;
            width: 150px;
            color: #2c3e50;
        }
        
        .btn {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #2980b9;
        }
        
        .btn-logout {
            background-color: #e74c3c;
        }
        
        .btn-logout:hover {
            background-color: #c0392b;
        }
        
        .subject-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 50px 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }

        .hero-content {
            position: relative;
            z-index: 1;
        }

        .hero-icon {
            font-size: 64px;
            margin-bottom: 20px;
            animation: bounce 2s ease-in-out infinite;
        }

        .hero-section h1 {
            font-size: 36px;
            margin-bottom: 15px;
            font-weight: 700;
        }

        .hero-section p {
            font-size: 18px;
            margin-bottom: 30px;
            opacity: 0.9;
        }

        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 30px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            opacity: 0.8;
        }

        /* Profile Card */
        .profile-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(245, 87, 108, 0.3);
        }

        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin-right: 20px;
        }

        .profile-info h3 {
            font-size: 24px;
            margin-bottom: 5px;
        }

        .profile-info p {
            opacity: 0.8;
        }

        .profile-details {
            display: grid;
            gap: 15px;
        }

        .detail-row {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-row i {
            width: 20px;
            opacity: 0.8;
        }

        /* Actions Section */
        .actions-section h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 24px;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .action-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            text-decoration: none;
            color: inherit;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #3498db;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .action-card.primary {
            border-left-color: #3498db;
        }

        .action-card.secondary {
            border-left-color: #2ecc71;
        }

        .action-card.success {
            border-left-color: #27ae60;
        }

        .action-card.warning {
            border-left-color: #f39c12;
        }

        .action-card.disabled {
            opacity: 0.6;
            cursor: default;
        }

        .action-card.disabled:hover {
            transform: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .action-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .action-card.primary .action-icon {
            background: linear-gradient(135deg, #3498db, #2980b9);
        }

        .action-card.secondary .action-icon {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
        }

        .action-card.success .action-icon {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
        }

        .action-card.warning .action-icon {
            background: linear-gradient(135deg, #f39c12, #e67e22);
        }

        .action-card.disabled .action-icon {
            background: #95a5a6;
        }

        .action-content h3 {
            font-size: 18px;
            margin-bottom: 5px;
            color: #2c3e50;
        }

        .action-content p {
            color: #7f8c8d;
            font-size: 14px;
        }

        .action-arrow {
            margin-left: auto;
            color: #bdc3c7;
            font-size: 18px;
        }

        /* Quote Section */
        .quote-section {
            margin-top: 40px;
        }

        .quote-card {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.8) 0%, rgba(118, 75, 162, 0.8) 100%), url('../img/absolutvision-82TpEld0_e4-unsplash.jpg');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            position: relative;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .quote-icon {
            font-size: 32px;
            margin-bottom: 20px;
            opacity: 0.8;
        }

        .quote-card blockquote {
            font-size: 24px;
            font-style: italic;
            margin-bottom: 15px;
            line-height: 1.4;
        }

        .quote-card cite {
            font-size: 16px;
            opacity: 0.8;
            font-weight: 600;
        }

        /* Animations */
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .hero-stats {
                flex-direction: column;
                gap: 20px;
            }

            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .profile-avatar {
                margin-right: 0;
                margin-bottom: 15px;
            }

            .actions-grid {
                grid-template-columns: 1fr;
            }

            .action-card {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }

            .action-arrow {
                margin-left: 0;
                margin-top: 10px;
            }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .main-container {
                flex-direction: column;
            }

            .sidebar-menu {
                width: 100%;
                height: auto;
                position: relative;
                top: 0;
            }

            .sidebar-nav {
                flex-direction: row;
                overflow-x: auto;
                padding: 10px 15px;
            }

            .side-link {
                white-space: nowrap;
            }
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
        .dark-mode .sidebar-menu {
            background: var(--sidebar-bg);
            color: var(--text-color);
        }
        .dark-mode .main-content {
            background: var(--bg-gradient);
        }
        .dark-mode .dashboard-card {
            background: var(--center-bg);
            color: var(--text-color);
        }
        .dark-mode .hero-section {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
        }
        .dark-mode .profile-card {
            background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%);
        }
        .dark-mode .action-card {
            background: var(--center-bg);
            color: var(--text-color);
        }
        .dark-mode .action-card:hover {
            background: rgba(50, 50, 50, 0.95);
        }
        .dark-mode .quote-card {
            background: linear-gradient(135deg, rgba(52, 73, 94, 0.8) 0%, rgba(44, 62, 80, 0.8) 100%), url('../img/absolutvision-82TpEld0_e4-unsplash.jpg');
        }
        .dark-mode .subject-card {
            background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%);
        }
        .dark-mode .side-link {
            color: var(--text-color);
        }
        .dark-mode .side-link:hover {
            background-color: rgba(236, 240, 241, 0.1);
        }
        .dark-mode .side-link.active {
            background-color: var(--accent);
        }
        .dark-mode .header-link {
            color: var(--text-color);
        }
        .dark-mode .header-link:hover {
            background-color: rgba(236, 240, 241, 0.1);
        }
        .dark-mode h1, .dark-mode h2, .dark-mode h3 {
            color: var(--text-color);
        }
        .dark-mode .action-content h3 {
            color: var(--text-color);
        }
        .dark-mode .action-content p {
            color: var(--text-color);
        }
        .dark-mode .stat-label {
            color: var(--text-color);
        }
        .dark-mode .profile-info h3 {
            color: var(--text-color);
        }
        .dark-mode .profile-info p {
            color: var(--text-color);
        }
        .dark-mode .detail-row {
            color: var(--text-color);
        }
        .dark-mode .sidebar-title {
            color: var(--text-color);
        }
        .dark-mode .sidebar-subtitle {
            color: var(--text-color);
        }
    </style>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="header">
        <a href="index.php" class="logo">
            <div class="logo-icon">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <div class="logo-text">SmartLearn</div>
        </a>
        <nav class="header-nav">
            <a class="header-link" href="progress.php">
                <i class="fas fa-chart-line"></i> Progress
            </a>
            <a class="header-link" href="achievement.php">
                <i class="fas fa-trophy"></i> Achievement
            </a>
            <a class="header-link" href="profile.php">
                <i class="fas fa-user"></i> Profile
            </a>
            <button id="theme-toggle" class="btn">🌙</button>
            <a class="header-link" href="logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>
    </div>

    <div class="main-container">
        <!-- Sidebar Menu -->
        <aside class="sidebar-menu">
            <?php if ($user && $user['education_level'] === 'primary'): ?>
                <div class="sidebar-title">PRIMARY - SUBJECTS</div>
                <nav class="sidebar-nav">
                    <?php if (!empty($subjects)): ?>
                        <?php foreach ($subjects as $subject): ?>
                            <a class="side-link" href="grade_five.php?subject=<?php echo urlencode($subject['id']); ?>">
                                <i class="fas fa-book"></i> <?php echo htmlspecialchars($subject['subject_name']); ?>
                                <small style="display: block; font-size: 11px; opacity: 0.7;"><?php echo htmlspecialchars($subject['grade_name']); ?></small>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="side-link" style="opacity:.8; cursor: default;">
                            <i class="fas fa-info-circle"></i> No subjects available yet
                        </div>
                    <?php endif; ?>
                </nav>
            <?php else: ?>
                <div class="sidebar-title">SECONDARY - FORMS & SUBJECTS</div>
                <nav class="sidebar-nav">
                    <?php if (!empty($forms)): ?>
                        <div class="sidebar-subtitle">Available Forms:</div>
                        <?php foreach ($forms as $form): ?>
                            <a class="side-link <?php echo $selected_form_id == $form['id'] ? 'active' : ''; ?>" 
                               href="pupil.php?form=<?php echo urlencode($form['id']); ?>">
                                <i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($form['form_name']); ?>
                                <?php if ($form['description']): ?>
                                    <small style="display: block; font-size: 11px; opacity: 0.7;"><?php echo htmlspecialchars($form['description']); ?></small>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <?php if (!empty($subjects)): ?>
                        <div class="sidebar-subtitle" style="margin-top: 20px;">
                            <?php if ($selected_form_id): ?>
                                Subjects in <?php echo htmlspecialchars($current_form['form_name'] ?? 'Selected Form'); ?>:
                            <?php else: ?>
                                Available Subjects:
                            <?php endif; ?>
                        </div>
                        <?php foreach ($subjects as $subject): ?>
                            <a class="side-link <?php echo $selected_subject_id == $subject['id'] ? 'active' : ''; ?>" 
                               href="secondary_subject.php?form_level=<?php echo urlencode($current_form['form_number'] ?? 1); ?>&subject_id=<?php echo urlencode($subject['id']); ?>">
                                <i class="fas fa-book"></i> <?php echo htmlspecialchars($subject['subject_name']); ?>
                                <small style="display: block; font-size: 11px; opacity: 0.7;"><?php echo htmlspecialchars($subject['form_name']); ?></small>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <?php if (empty($forms) && empty($subjects)): ?>
                        <div class="side-link" style="opacity:.8; cursor: default;">
                            <i class="fas fa-info-circle"></i> No forms or subjects available yet
                        </div>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="dashboard-card">
                <?php if ($selected_form_id && $current_form): ?>
                    <!-- Form Selected - Show Subjects -->
                    <h1>
                        <i class="fas fa-graduation-cap"></i> 
                        <?php echo htmlspecialchars($current_form['form_name']); ?> Subjects
                    </h1>
                    
                    <?php if ($current_form['description']): ?>
                        <p style="color: #7f8c8d; margin-bottom: 20px; font-size: 16px;">
                            <?php echo htmlspecialchars($current_form['description']); ?>
                        </p>
                    <?php endif; ?>
                    
                    <?php if (!empty($subjects)): ?>
                        <div class="subjects-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
                            <?php foreach ($subjects as $subject): ?>
                                <div class="subject-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 10px; text-decoration: none; text-align: center; transition: transform 0.3s ease; cursor: pointer;" 
                                     onclick="window.location.href='secondary_subject.php?form_level=<?php echo urlencode($current_form['form_number'] ?? 1); ?>&subject_id=<?php echo urlencode($subject['id']); ?>'">
                                    <i class="fas fa-book" style="font-size: 48px; margin-bottom: 15px;"></i>
                                    <h3 style="font-size: 20px; margin-bottom: 10px;"><?php echo htmlspecialchars($subject['subject_name']); ?></h3>
                                    <?php if ($subject['description']): ?>
                                        <p style="opacity: 0.9; font-size: 14px; margin-bottom: 15px;"><?php echo htmlspecialchars($subject['description']); ?></p>
                                    <?php endif; ?>
                                    <div style="background: rgba(255,255,255,0.2); padding: 8px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                                        Click to start questions
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: #7f8c8d;">
                            <i class="fas fa-book" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                            <h3>No Subjects Available</h3>
                            <p>No subjects have been added to <?php echo htmlspecialchars($current_form['form_name']); ?> yet.</p>
                        </div>
                    <?php endif; ?>
                    
                    <div style="margin-top: 30px; text-align: center;">
                        <a href="pupil.php" class="btn" style="background: #6c757d; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; display: inline-block;">
                            <i class="fas fa-arrow-left"></i> Back to All Forms
                        </a>
                    </div>
                    
                <?php else: ?>
                    <!-- Default Dashboard View -->
                    <!-- Hero Section -->
                    <div class="hero-section">
                        <div class="hero-content">
                            <div class="hero-icon">
                                <i class="fas fa-rocket"></i>
                            </div>
                            <h1>Welcome back, <?php echo htmlspecialchars($user['first_name'] ?? 'Student'); ?>!</h1>
                            <p>Ready to continue your learning journey? Let's explore new subjects and achieve your goals today!</p>
                            <div class="hero-stats">
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo count($subjects); ?></div>
                                    <div class="stat-label">Available Subjects</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo $user['education_level'] === 'primary' ? count($subjects) : count($forms); ?></div>
                                    <div class="stat-label"><?php echo $user['education_level'] === 'primary' ? 'Grades' : 'Forms'; ?> Available</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number">∞</div>
                                    <div class="stat-label">Learning Possibilities</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($user): ?>
                        <!-- User Profile Card -->
                        <div class="profile-card">
                            <div class="profile-header">
                                <div class="profile-avatar">
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                                <div class="profile-info">
                                    <h3><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                                    <p>@<?php echo htmlspecialchars($user['username']); ?></p>
                                </div>
                            </div>
                            <div class="profile-details">
                                <div class="detail-row">
                                    <i class="fas fa-school"></i>
                                    <span><?php echo htmlspecialchars($user['school_name']); ?></span>
                                </div>
                                <div class="detail-row">
                                    <i class="fas fa-graduation-cap"></i>
                                    <span><?php echo ucfirst(htmlspecialchars($user['education_level'])); ?> Level</span>
                                </div>
                                <div class="detail-row">
                                    <i class="fas fa-layer-group"></i>
                                    <span>
                                        <?php
                                        if ($user['education_level'] == 'primary') {
                                            echo 'Grade ' . htmlspecialchars($user['grade']);
                                        } else {
                                            echo 'Form ' . htmlspecialchars($user['form']);
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Quick Actions Section -->
                    <div class="actions-section">
                        <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
                        <div class="actions-grid">
                            <?php if ($user && $user['education_level'] === 'primary'): ?>
                                <?php if (!empty($subjects)): ?>
                                    <a href="grade_five.php" class="action-card primary">
                                        <div class="action-icon">
                                            <i class="fas fa-play-circle"></i>
                                        </div>
                                        <div class="action-content">
                                            <h3>Browse Subjects</h3>
                                            <p>Explore available subjects and start learning</p>
                                        </div>
                                        <div class="action-arrow">
                                            <i class="fas fa-arrow-right"></i>
                                        </div>
                                    </a>
                                <?php else: ?>
                                    <div class="action-card disabled">
                                        <div class="action-icon">
                                            <i class="fas fa-info-circle"></i>
                                        </div>
                                        <div class="action-content">
                                            <h3>No Subjects Yet</h3>
                                            <p>Check back later for new subjects</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if (!empty($forms)): ?>
                                    <a href="pupil.php" class="action-card secondary">
                                        <div class="action-icon">
                                            <i class="fas fa-graduation-cap"></i>
                                        </div>
                                        <div class="action-content">
                                            <h3>Browse Forms</h3>
                                            <p>Select your form and explore subjects</p>
                                        </div>
                                        <div class="action-arrow">
                                            <i class="fas fa-arrow-right"></i>
                                        </div>
                                    </a>
                                <?php elseif (!empty($subjects)): ?>
                                    <a href="grade_five.php" class="action-card primary">
                                        <div class="action-icon">
                                            <i class="fas fa-play-circle"></i>
                                        </div>
                                        <div class="action-content">
                                            <h3>Browse Subjects</h3>
                                            <p>Explore available subjects and start learning</p>
                                        </div>
                                        <div class="action-arrow">
                                            <i class="fas fa-arrow-right"></i>
                                        </div>
                                    </a>
                                <?php else: ?>
                                    <div class="action-card disabled">
                                        <div class="action-icon">
                                            <i class="fas fa-info-circle"></i>
                                        </div>
                                        <div class="action-content">
                                            <h3>Coming Soon</h3>
                                            <p>New content will be available soon</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <a href="progress.php" class="action-card success">
                                <div class="action-icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="action-content">
                                    <h3>View Progress</h3>
                                    <p>Check your learning progress and achievements</p>
                                </div>
                                <div class="action-arrow">
                                    <i class="fas fa-arrow-right"></i>
                                </div>
                            </a>

                            <a href="achievement.php" class="action-card warning">
                                <div class="action-icon">
                                    <i class="fas fa-trophy"></i>
                                </div>
                                <div class="action-content">
                                    <h3>Achievements</h3>
                                    <p>View your badges and accomplishments</p>
                                </div>
                                <div class="action-arrow">
                                    <i class="fas fa-arrow-right"></i>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- Did You Know Facts -->
                    <?php if (!empty($facts)): ?>
                    <div class="quote-section">
                        <div class="quote-card">
                            <div class="quote-icon">
                                <i class="fas fa-lightbulb"></i>
                            </div>
                            <h3 style="color: white; margin-bottom: 20px;">Did You Know?</h3>
                            <div id="facts-carousel" style="position: relative; overflow: hidden; height: 120px; cursor: pointer;" onclick="nextFact()">
                                <div id="facts-container" style="display: flex; transition: transform 0.5s ease-in-out;">
                                    <?php foreach ($facts as $fact): ?>
                                        <div class="fact-item" style="min-width: 100%; padding: 0 20px; box-sizing: border-box;">
                                            <blockquote style="font-size: 18px; margin-bottom: 8px;">
                                                <?php echo htmlspecialchars($fact['fact_text']); ?>
                                            </blockquote>
                                            <?php if ($fact['source']): ?>
                                                <cite style="font-size: 12px;">- <?php echo htmlspecialchars($fact['source']); ?></cite>
                                            <?php endif; ?>
                                            <div style="margin-top: 10px; font-size: 11px; opacity: 0.8;">
                                                Category: <?php echo ucfirst(htmlspecialchars($fact['category'])); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        document.getElementById('theme-toggle').addEventListener('click', function() {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            this.textContent = isDark ? '☀️' : '🌙';
        });
        let currentFactIndex = 0;
        const factsContainer = document.getElementById('facts-container');
        const factItems = document.querySelectorAll('.fact-item');

        function showFact(index) {
            if (factsContainer) {
                factsContainer.style.transform = `translateX(-${index * 100}%)`;
            }
        }

        function nextFact() {
            currentFactIndex = (currentFactIndex + 1) % factItems.length;
            showFact(currentFactIndex);
        }

        // Auto-rotate facts every 30 seconds
        if (factItems.length > 1) {
            setInterval(nextFact, 30000);
        }
    </script>
</body>
</html>