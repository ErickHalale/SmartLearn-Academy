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
$action = isset($_GET['action']) ? $_GET['action'] : '';
$subject_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Create tables if they don't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS primary_subjects (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        subject_name VARCHAR(100) NOT NULL,
        grade_id INT UNSIGNED NOT NULL,
        description TEXT,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_subjects_grade (subject_name, grade_id),
        INDEX idx_subjects_grade (grade_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS primary_topics (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        topic_name VARCHAR(150) NOT NULL,
        subject_id INT UNSIGNED NOT NULL,
        description TEXT,
        order_index INT UNSIGNED DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_topics_subject (subject_id),
        INDEX idx_topics_order (subject_id, order_index)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS primary_grades (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        grade_name VARCHAR(50) NOT NULL,
        grade_number TINYINT UNSIGNED NOT NULL,
        description TEXT,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_grades_number (grade_number),
        UNIQUE KEY uk_grades_name (grade_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS primary_questions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        question_text TEXT NOT NULL,
        question_type ENUM('multiple_choice','one_word','true_false') NOT NULL DEFAULT 'multiple_choice',
        topic_id INT UNSIGNED NOT NULL,
        points INT UNSIGNED DEFAULT 1,
        difficulty_level ENUM('easy','medium','hard') DEFAULT 'medium',
        order_index INT UNSIGNED DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_questions_topic (topic_id),
        INDEX idx_questions_order (topic_id, order_index)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS primary_question_options (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        question_id INT UNSIGNED NOT NULL,
        option_text TEXT NOT NULL,
        is_correct BOOLEAN DEFAULT FALSE,
        order_index INT UNSIGNED DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_options_question (question_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS primary_question_answers (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        question_id INT UNSIGNED NOT NULL,
        answer_text TEXT NOT NULL,
        is_case_sensitive BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_answers_question (question_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) {
    // Tables might already exist, continue
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_subject'])) {
        // Update subject
        $subject_id = (int)$_POST['subject_id'];
        $subject_name = trim($_POST['subject_name']);
        $grade_id = (int)$_POST['grade_id'];
        $description = trim($_POST['description']);
        
        if (empty($subject_name) || empty($grade_id)) {
            $error = 'Subject name and grade are required.';
        } else {
            try {
                // Check if subject already exists for this grade (excluding current subject)
                $check_stmt = $pdo->prepare("SELECT id FROM primary_subjects WHERE subject_name = ? AND grade_id = ? AND id != ?");
                $check_stmt->execute([$subject_name, $grade_id, $subject_id]);

                if ($check_stmt->rowCount() > 0) {
                    $error = 'Subject already exists for this grade.';
                } else {
                    // Update subject
                    $stmt = $pdo->prepare("UPDATE primary_subjects SET subject_name = ?, grade_id = ?, description = ? WHERE id = ?");
                    $stmt->execute([$subject_name, $grade_id, $description, $subject_id]);
                    
                    $message = 'Subject updated successfully!';
                    $action = ''; // Return to list view
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_subject'])) {
        // Delete subject
        $subject_id = (int)$_POST['subject_id'];
        
        try {
            $pdo->beginTransaction();
            
            // Check if subject has topics
            $topics_stmt = $pdo->prepare("SELECT COUNT(*) FROM primary_topics WHERE subject_id = ?");
            $topics_stmt->execute([$subject_id]);
            $topic_count = $topics_stmt->fetchColumn();

            if ($topic_count > 0) {
                $error = 'Cannot delete subject. It has ' . $topic_count . ' topic(s) associated with it. Please delete the topics first.';
            } else {
                // Delete subject
                $stmt = $pdo->prepare("DELETE FROM primary_subjects WHERE id = ?");
                $stmt->execute([$subject_id]);
                
                $message = 'Subject deleted successfully!';
            }
            
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Database error: ' . $e->getMessage();
        }
    } elseif (isset($_POST['update_topic'])) {
        // Update topic
        $topic_id = (int)$_POST['topic_id'];
        $topic_name = trim($_POST['topic_name']);
        $topic_description = trim($_POST['topic_description']);
        try {
            $stmt = $pdo->prepare("UPDATE primary_topics SET topic_name = ?, description = ? WHERE id = ?");
            $stmt->execute([$topic_name, $topic_description, $topic_id]);
            $message = 'Topic updated successfully!';
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    } elseif (isset($_POST['delete_topic'])) {
        // Delete topic
        $topic_id = (int)$_POST['topic_id'];
        try {
            $pdo->beginTransaction();
            // Delete question data under topic
            $pdo->prepare("DELETE qo FROM primary_question_options qo JOIN primary_questions q ON qo.question_id = q.id WHERE q.topic_id = ?")->execute([$topic_id]);
            $pdo->prepare("DELETE qa FROM primary_question_answers qa JOIN primary_questions q ON qa.question_id = q.id WHERE q.topic_id = ?")->execute([$topic_id]);
            $pdo->prepare("DELETE FROM primary_questions WHERE topic_id = ?")->execute([$topic_id]);
            $pdo->prepare("DELETE FROM primary_topics WHERE id = ?")->execute([$topic_id]);
            $pdo->commit();
            $message = 'Topic and its questions deleted successfully!';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Database error: ' . $e->getMessage();
        }
    } elseif (isset($_POST['update_question'])) {
        // Update question text and meta
        $question_id = (int)$_POST['question_id'];
        $question_text = trim($_POST['question_text']);
        $difficulty = $_POST['difficulty_level'] ?? 'medium';
        $points = (int)($_POST['points'] ?? 1);
        try {
            $stmt = $pdo->prepare("UPDATE primary_questions SET question_text = ?, difficulty_level = ?, points = ? WHERE id = ?");
            $stmt->execute([$question_text, $difficulty, $points, $question_id]);
            $message = 'Question updated successfully!';
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    } elseif (isset($_POST['delete_question'])) {
        // Delete a single question and its options/answers
        $question_id = (int)$_POST['question_id'];
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM primary_question_options WHERE question_id = ?")->execute([$question_id]);
            $pdo->prepare("DELETE FROM primary_question_answers WHERE question_id = ?")->execute([$question_id]);
            $pdo->prepare("DELETE FROM primary_questions WHERE id = ?")->execute([$question_id]);
            $pdo->commit();
            $message = 'Question deleted successfully!';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Database error: ' . $e->getMessage();
        }
    }
} elseif ($action === 'delete' && $subject_id > 0) {
    // Show delete confirmation
    $subject_to_delete = null;
    try {
        $stmt = $pdo->prepare("SELECT s.*, g.grade_name FROM primary_subjects s JOIN primary_grades g ON s.grade_id = g.id WHERE s.id = ?");
        $stmt->execute([$subject_id]);
        $subject_to_delete = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$subject_to_delete) {
            $error = 'Subject not found.';
            $action = '';
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
        $action = '';
    }
}

// Fetch data
$grades = [];
$subjects = [];
$current_subject = null;
$manage_subject = null;
$subject_topics = [];
$subject_questions = [];

try {
    // Fetch grades for dropdown
    $grades_stmt = $pdo->prepare("SELECT * FROM primary_grades ORDER BY grade_number ASC");
    $grades_stmt->execute();
    $grades = $grades_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch subjects with grade information and topic counts
    $subjects_stmt = $pdo->prepare("
        SELECT s.*, g.grade_name,
               (SELECT COUNT(*) FROM primary_topics t WHERE t.subject_id = s.id) as topic_count,
               (SELECT COUNT(*) FROM primary_questions q JOIN primary_topics t ON q.topic_id = t.id WHERE t.subject_id = s.id) as question_count
        FROM primary_subjects s
        JOIN primary_grades g ON s.grade_id = g.id
        ORDER BY g.grade_number, s.subject_name
    ");
    $subjects_stmt->execute();
    $subjects = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch subject for editing
    if ($action === 'edit' && $subject_id > 0) {
        $subject_stmt = $pdo->prepare("SELECT s.*, g.grade_name FROM primary_subjects s JOIN primary_grades g ON s.grade_id = g.id WHERE s.id = ?");
        $subject_stmt->execute([$subject_id]);
        $current_subject = $subject_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$current_subject) {
            $error = 'Subject not found.';
            $action = '';
        }
    }
    // Fetch topics management view
    if ($action === 'manage_topics' && $subject_id > 0) {
        $sub_stmt = $pdo->prepare("SELECT s.*, g.grade_name FROM primary_subjects s JOIN primary_grades g ON s.grade_id = g.id WHERE s.id = ?");
        $sub_stmt->execute([$subject_id]);
        $manage_subject = $sub_stmt->fetch(PDO::FETCH_ASSOC);
        if ($manage_subject) {
            $tstmt = $pdo->prepare("SELECT * FROM primary_topics WHERE subject_id = ? ORDER BY order_index, topic_name");
            $tstmt->execute([$subject_id]);
            $subject_topics = $tstmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    // Fetch questions management view
    if ($action === 'manage_questions' && $subject_id > 0) {
        $sub_stmt = $pdo->prepare("SELECT s.*, g.grade_name FROM primary_subjects s JOIN primary_grades g ON s.grade_id = g.id WHERE s.id = ?");
        $sub_stmt->execute([$subject_id]);
        $manage_subject = $sub_stmt->fetch(PDO::FETCH_ASSOC);
        if ($manage_subject) {
            $qstmt = $pdo->prepare("SELECT q.*, t.topic_name FROM primary_questions q JOIN primary_topics t ON q.topic_id = t.id WHERE t.subject_id = ? ORDER BY t.topic_name, q.order_index");
            $qstmt->execute([$subject_id]);
            $subject_questions = $qstmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subjects - SmartLearn Admin</title>
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
            padding: 20px 0;
            margin: 0;
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

        .back-link {
            background-color: #6c757d;
            color: white !important;
        }

        .back-link:hover {
            background-color: #5a6268 !important;
        }

        .primary-link {
            background-color: #007bff;
            color: white !important;
        }

        .primary-link:hover {
            background-color: #0056b3 !important;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
            box-sizing: border-box;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
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

        .warning {
            background-color: #fff3cd;
            color: #856404;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #ffeaa7;
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
        .form-group select,
        .form-group textarea {
            width: 100%;
            max-width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
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
        .dark-mode .subject-card {
            background: rgba(40, 40, 40, 0.95);
            color: var(--text-color);
        }
        .dark-mode .subject-title {
            color: var(--text-color);
        }
        .dark-mode .subject-description {
            color: var(--text-color);
        }
        .dark-mode .stat-badge {
            background: rgba(60, 60, 60, 0.95);
            color: var(--text-color);
        }
        .dark-mode .form-group label {
            color: var(--text-color);
        }
        .dark-mode .form-group input,
        .dark-mode .form-group select,
        .dark-mode .form-group textarea {
            background: rgba(50, 50, 50, 0.95);
            color: var(--text-color);
            border-color: rgba(102, 126, 234, 0.3);
        }
        .dark-mode .form-group input:focus,
        .dark-mode .form-group select:focus,
        .dark-mode .form-group textarea:focus {
            border-color: var(--accent);
        }
        .dark-mode .table th,
        .dark-mode .table td {
            background: rgba(40, 40, 40, 0.95);
            color: var(--text-color);
            border-bottom-color: rgba(255, 255, 255, 0.1);
        }
        .dark-mode .table th {
            background: rgba(50, 50, 50, 0.95);
        }
        .dark-mode .table tr:hover {
            background: rgba(60, 60, 60, 0.95);
        }
        .dark-mode .subject-info {
            background: rgba(40, 40, 40, 0.95);
            color: var(--text-color);
        }
        .dark-mode .empty-state {
            color: var(--text-color);
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
        .dark-mode .warning {
            background-color: rgba(255, 193, 7, 0.2);
            color: #fff3cd;
            border-color: rgba(255, 193, 7, 0.3);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: transform 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
            margin-right: 10px;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-danger {
            background: #dc3545;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-success {
            background: #28a745;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 14px;
        }

        .subjects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .subject-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #667eea;
            transition: transform 0.3s, box-shadow 0.3s;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .subject-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .subject-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .subject-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .subject-grade {
            color: #666;
            font-size: 14px;
        }

        .subject-description {
            color: #777;
            font-size: 14px;
            margin-bottom: 15px;
            line-height: 1.4;
        }

        .subject-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .subject-stats {
            display: flex;
            gap: 10px;
        }

        .stat-badge {
            background: #e9ecef;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            color: #495057;
        }

        .subject-actions {
            display: flex;
            gap: 10px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
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

        .delete-confirmation {
            text-align: center;
            padding: 20px;
        }

        .delete-icon {
            font-size: 48px;
            color: #dc3545;
            margin-bottom: 15px;
        }

        .subject-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .form-row {
            display: flex;
            gap: 20px;
        }

        .form-row .form-group {
            flex: 1;
        }

        @media (max-width: 768px) {
            .subjects-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .subject-card {
                padding: 15px;
            }

            .form-row {
                flex-direction: column;
            }

            .subject-header {
                flex-direction: column;
                gap: 10px;
            }

            .subject-actions {
                margin-top: 10px;
                width: 100%;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 5px;
            }

            .subject-actions a,
            .subject-actions button {
                flex: 1;
                min-width: 80px;
                text-align: center;
            }

            .nav-links {
                flex-direction: column;
                gap: 10px;
            }

            .card {
                padding: 20px;
            }
        }

        @media (max-width: 480px) {
            .subjects-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .subject-card {
                padding: 12px;
            }

            .subject-title {
                font-size: 16px;
            }

            .subject-actions {
                flex-direction: column;
                gap: 8px;
            }

            .subject-actions a,
            .subject-actions button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-section">
            <h1>SmartLearn Admin - Manage Primary Subjects</h1>
        </div>
        <div class="nav-links">
            <a href="admin.php" class="back-link">Back to Dashboard</a>
            <a href="create_subject.php" class="primary-link">Add New Subject</a>
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

        <?php if ($action === 'edit' && $current_subject): ?>
            <!-- Edit Subject Form -->
            <div class="card">
                <h2>Edit Subject</h2>
                
                <div class="subject-info">
                    <strong>Current Subject:</strong> <?php echo htmlspecialchars($current_subject['subject_name']); ?><br>
                    <strong>Grade:</strong> <?php echo htmlspecialchars($current_subject['grade_name']); ?>
                </div>

                <form method="POST">
                    <input type="hidden" name="subject_id" value="<?php echo $current_subject['id']; ?>">
                    
                    <div class="form-group">
                        <label for="grade_id">Grade:</label>
                        <select id="grade_id" name="grade_id" required>
                            <option value="">Choose Grade</option>
                            <?php foreach ($grades as $grade): ?>
                                <option value="<?php echo $grade['id']; ?>" 
                                    <?php echo $current_subject['grade_id'] == $grade['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($grade['grade_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="subject_name">Subject Name:</label>
                        <input type="text" id="subject_name" name="subject_name" 
                               value="<?php echo htmlspecialchars($current_subject['subject_name']); ?>" 
                               placeholder="e.g., Mathematics, English, Science" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description:</label>
                        <textarea id="description" name="description" 
                                  placeholder="Brief description of the subject"><?php echo htmlspecialchars($current_subject['description']); ?></textarea>
                    </div>

                    <div class="action-buttons">
                        <a href="manage_subject_primary.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" name="update_subject" class="btn btn-success">Update Subject</button>
                    </div>
                </form>
            </div>

        <?php elseif ($action === 'delete' && isset($subject_to_delete)): ?>
            <!-- Delete Confirmation -->
            <div class="card">
                <div class="delete-confirmation">
                    <div class="delete-icon">⚠️</div>
                    <h2>Delete Subject</h2>
                    <p>Are you sure you want to delete this subject?</p>
                    
                    <div class="subject-info">
                        <strong>Subject:</strong> <?php echo htmlspecialchars($subject_to_delete['subject_name']); ?><br>
                        <strong>Grade:</strong> <?php echo htmlspecialchars($subject_to_delete['grade_name']); ?>
                    </div>

                    <?php
                    // Check if subject has topics
                    $topics_stmt = $pdo->prepare("SELECT COUNT(*) FROM primary_topics WHERE subject_id = ?");
                    $topics_stmt->execute([$subject_to_delete['id']]);
                    $topic_count = $topics_stmt->fetchColumn();
                    
                    if ($topic_count > 0): ?>
                        <div class="warning">
                            <strong>Warning:</strong> This subject has <?php echo $topic_count; ?> topic(s) associated with it. 
                            You must delete all topics before you can delete this subject.
                        </div>
                    <?php endif; ?>

                    <form method="POST" style="margin-top: 20px;">
                        <input type="hidden" name="subject_id" value="<?php echo $subject_to_delete['id']; ?>">
                        
                        <div class="action-buttons">
                            <a href="manage_subject_primary.php" class="btn btn-secondary">Cancel</a>
                            <?php if ($topic_count == 0): ?>
                                <button type="submit" name="delete_subject" class="btn btn-danger">Delete Subject</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

        <?php elseif ($action === 'manage_topics' && $manage_subject): ?>
            <!-- Manage Topics View -->
            <div class="card">
                <h2>Manage Topics - <?php echo htmlspecialchars($manage_subject['subject_name']); ?> (<?php echo htmlspecialchars($manage_subject['grade_name']); ?>)</h2>
                <?php if (empty($subject_topics)): ?>
                    <div class="empty-state">
                        <div>📁</div>
                        <h3>No Topics Found</h3>
                        <p>Add topics for this subject from the add page.</p>
                    </div>
                <?php else: ?>
                    <div class="subjects-grid">
                        <?php foreach ($subject_topics as $topic): ?>
                            <div class="subject-card">
                                <div class="subject-header">
                                    <div>
                                        <div class="subject-title"><?php echo htmlspecialchars($topic['topic_name']); ?></div>
                                    </div>
                                </div>
                                <?php if (!empty($topic['description'])): ?>
                                    <div class="subject-description"><?php echo htmlspecialchars($topic['description']); ?></div>
                                <?php endif; ?>
                                <form method="POST" class="action-buttons">
                                    <input type="hidden" name="topic_id" value="<?php echo $topic['id']; ?>">
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Topic Name</label>
                                            <input type="text" name="topic_name" value="<?php echo htmlspecialchars($topic['topic_name']); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Description</label>
                                            <input type="text" name="topic_description" value="<?php echo htmlspecialchars($topic['description'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="subject-actions">
                                        <button type="submit" name="update_topic" class="btn btn-small btn-success">Save</button>
                                        <button type="submit" name="delete_topic" class="btn btn-small btn-danger" onclick="return confirm('Delete this topic and its questions?')">Delete</button>
                                    </div>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="action-buttons">
                    <a href="manage_subject_primary.php" class="btn btn-secondary">Back</a>
                </div>
            </div>

        <?php elseif ($action === 'manage_questions' && $manage_subject): ?>
            <!-- Manage Questions View -->
            <div class="card">
                <h2>Manage Questions - <?php echo htmlspecialchars($manage_subject['subject_name']); ?> (<?php echo htmlspecialchars($manage_subject['grade_name']); ?>)</h2>
                <?php if (empty($subject_questions)): ?>
                    <div class="empty-state">
                        <div>❓</div>
                        <h3>No Questions Found</h3>
                        <p>Add questions for this subject's topics from the add page.</p>
                    </div>
                <?php else: ?>
                    <div class="subjects-grid">
                        <?php foreach ($subject_questions as $q): ?>
                            <div class="subject-card">
                                <div class="subject-header">
                                    <div>
                                        <div class="subject-title"><?php echo htmlspecialchars($q['topic_name']); ?></div>
                                        <div class="subject-grade">Question #<?php echo (int)$q['id']; ?></div>
                                    </div>
                                </div>
                                <form method="POST">
                                    <input type="hidden" name="question_id" value="<?php echo $q['id']; ?>">
                                    <div class="form-group">
                                        <label>Question Text</label>
                                        <textarea name="question_text" required><?php echo htmlspecialchars($q['question_text']); ?></textarea>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Difficulty</label>
                                            <select name="difficulty_level">
                                                <option value="easy" <?php echo $q['difficulty_level']==='easy'?'selected':''; ?>>Easy</option>
                                                <option value="medium" <?php echo $q['difficulty_level']==='medium'?'selected':''; ?>>Medium</option>
                                                <option value="hard" <?php echo $q['difficulty_level']==='hard'?'selected':''; ?>>Hard</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Points</label>
                                            <input type="number" name="points" min="1" value="<?php echo (int)$q['points']; ?>">
                                        </div>
                                    </div>
                                    <div class="subject-actions">
                                        <button type="submit" name="update_question" class="btn btn-small btn-success">Save</button>
                                        <button type="submit" name="delete_question" class="btn btn-small btn-danger" onclick="return confirm('Delete this question?')">Delete</button>
                                    </div>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="action-buttons">
                    <a href="manage_subject_primary.php" class="btn btn-secondary">Back</a>
                </div>
            </div>

        <?php else: ?>
            <!-- Subjects List -->
            <div class="card">
                <h2>
                    Manage Primary Subjects
                    <span style="font-size: 16px; color: #666;"><?php echo count($subjects); ?> subject(s)</span>
                </h2>

                <?php if (empty($subjects)): ?>
                    <div class="empty-state">
                        <div>📚</div>
                        <h3>No Subjects Found</h3>
                        <p>You haven't added any subjects yet.</p>
                        <a href="create_subject.php" class="btn primary-link" style="margin-top: 15px;">Add Your First Subject</a>
                    </div>
                <?php else: ?>
                    <div class="subjects-grid">
                        <?php foreach ($subjects as $subject): ?>
                            <div class="subject-card">
                                <div class="subject-header">
                                    <div>
                                        <div class="subject-title"><?php echo htmlspecialchars($subject['subject_name']); ?></div>
                                        <div class="subject-grade"><?php echo htmlspecialchars($subject['grade_name']); ?></div>
                                    </div>
                                </div>
                                
                                <?php if ($subject['description']): ?>
                                    <div class="subject-description">
                                        <?php echo htmlspecialchars($subject['description']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="subject-meta">
                                    <div class="subject-stats">
                                        <div class="stat-badge">
                                            <?php echo $subject['topic_count']; ?> topic(s)
                                        </div>
                                        <div class="stat-badge">
                                            <?php echo $subject['question_count'] ?? 0; ?> question(s)
                                        </div>
                                    </div>
                                    <div class="subject-actions">
                                        <a href="?action=edit&id=<?php echo $subject['id']; ?>" class="btn btn-small btn-secondary">Edit</a>
                                        <a href="?action=manage_topics&id=<?php echo $subject['id']; ?>" class="btn btn-small">Manage Topics</a>
                                        <a href="?action=manage_questions&id=<?php echo $subject['id']; ?>" class="btn btn-small">Manage Questions</a>
                                        <a href="?action=delete&id=<?php echo $subject['id']; ?>" class="btn btn-small btn-danger">Delete</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.getElementById('theme-toggle').addEventListener('click', function() {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            this.textContent = isDark ? '☀️' : '🌙';
        });
        // Confirm delete action
        document.addEventListener('DOMContentLoaded', function() {
            const deleteLinks = document.querySelectorAll('a.btn-danger');
            deleteLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    if (!this.href.includes('action=delete')) {
                        if (!confirm('Are you sure you want to delete this subject?')) {
                            e.preventDefault();
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>
