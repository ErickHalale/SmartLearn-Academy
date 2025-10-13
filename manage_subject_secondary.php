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
$topic_id = isset($_GET['topic_id']) ? (int)$_GET['topic_id'] : 0;
$question_id = isset($_GET['question_id']) ? (int)$_GET['question_id'] : 0;

// Create tables if they don't exist
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

    $pdo->exec("CREATE TABLE IF NOT EXISTS secondary_subjects (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        subject_name VARCHAR(100) NOT NULL,
        form_id INT UNSIGNED NOT NULL,
        description TEXT,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_subject_form (subject_name, form_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS secondary_topics (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        topic_name VARCHAR(150) NOT NULL,
        subject_id INT UNSIGNED NOT NULL,
        description TEXT,
        order_index INT UNSIGNED DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_subject_id (subject_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS secondary_questions (
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
        INDEX idx_topic_id (topic_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS secondary_question_options (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        question_id INT UNSIGNED NOT NULL,
        option_text TEXT NOT NULL,
        is_correct BOOLEAN DEFAULT FALSE,
        order_index INT UNSIGNED DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_question_id (question_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS secondary_question_answers (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        question_id INT UNSIGNED NOT NULL,
        answer_text TEXT NOT NULL,
        is_case_sensitive BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_question_id (question_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Insert default forms if they don't exist
    $forms = [
        ['Form 1', 1, 'First year of secondary education'],
        ['Form 2', 2, 'Second year of secondary education'],
        ['Form 3', 3, 'Third year of secondary education'],
        ['Form 4', 4, 'Fourth year of secondary education']
    ];
    
    foreach ($forms as $form) {
        $check_stmt = $pdo->prepare("SELECT id FROM forms WHERE form_name = ?");
        $check_stmt->execute([$form[0]]);
        if ($check_stmt->rowCount() == 0) {
            $insert_stmt = $pdo->prepare("INSERT INTO forms (form_name, form_number, description) VALUES (?, ?, ?)");
            $insert_stmt->execute($form);
        }
    }
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_subject'])) {
        // Update subject
        $subject_id = (int)$_POST['subject_id'];
        $subject_name = trim($_POST['subject_name']);
        $form_id = (int)$_POST['form_id'];
        $description = trim($_POST['description']);
        
        if (empty($subject_name) || empty($form_id)) {
            $error = 'Subject name and form are required.';
        } else {
            try {
                // Check if subject already exists for this form (excluding current subject)
                $check_stmt = $pdo->prepare("SELECT id FROM secondary_subjects WHERE subject_name = ? AND form_id = ? AND id != ?");
                $check_stmt->execute([$subject_name, $form_id, $subject_id]);
                
                if ($check_stmt->rowCount() > 0) {
                    $error = 'Subject already exists for this form.';
                } else {
                    // Update subject
                    $stmt = $pdo->prepare("UPDATE secondary_subjects SET subject_name = ?, form_id = ?, description = ? WHERE id = ?");
                    $stmt->execute([$subject_name, $form_id, $description, $subject_id]);
                    
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
            $topics_stmt = $pdo->prepare("SELECT COUNT(*) FROM secondary_topics WHERE subject_id = ?");
            $topics_stmt->execute([$subject_id]);
            $topic_count = $topics_stmt->fetchColumn();
            
            if ($topic_count > 0) {
                $error = 'Cannot delete subject. It has ' . $topic_count . ' topic(s) associated with it. Please delete the topics first.';
            } else {
                // Delete subject
                $stmt = $pdo->prepare("DELETE FROM secondary_subjects WHERE id = ?");
                $stmt->execute([$subject_id]);
                
                $message = 'Subject deleted successfully!';
            }
            
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Database error: ' . $e->getMessage();
        }
    } elseif (isset($_POST['add_topic'])) {
        // Add new topic
        $subject_id = (int)$_POST['subject_id'];
        $topic_name = trim($_POST['topic_name']);
        $description = trim($_POST['description']);
        
        if (empty($topic_name)) {
            $error = 'Topic name is required.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO secondary_topics (topic_name, subject_id, description) VALUES (?, ?, ?)");
                $stmt->execute([$topic_name, $subject_id, $description]);
                $message = 'Topic added successfully!';
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['update_topic'])) {
        // Update topic
        $topic_id = (int)$_POST['topic_id'];
        $topic_name = trim($_POST['topic_name']);
        $description = trim($_POST['description']);
        
        if (empty($topic_name)) {
            $error = 'Topic name is required.';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE secondary_topics SET topic_name = ?, description = ? WHERE id = ?");
                $stmt->execute([$topic_name, $description, $topic_id]);
                $message = 'Topic updated successfully!';
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_topic'])) {
        // Delete topic
        $topic_id = (int)$_POST['topic_id'];
        
        try {
            $pdo->beginTransaction();
            
            // Check if topic has questions
            $questions_stmt = $pdo->prepare("SELECT COUNT(*) FROM secondary_questions WHERE topic_id = ?");
            $questions_stmt->execute([$topic_id]);
            $question_count = $questions_stmt->fetchColumn();
            
            if ($question_count > 0) {
                $error = 'Cannot delete topic. It has ' . $question_count . ' question(s) associated with it. Please delete the questions first.';
            } else {
                // Delete topic
                $stmt = $pdo->prepare("DELETE FROM secondary_topics WHERE id = ?");
                $stmt->execute([$topic_id]);
                $message = 'Topic deleted successfully!';
            }
            
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Database error: ' . $e->getMessage();
        }
    } elseif (isset($_POST['add_question'])) {
        // Add new question
        $topic_id = (int)$_POST['topic_id'];
        $question_text = trim($_POST['question_text']);
        $question_type = $_POST['question_type'];
        $points = (int)$_POST['points'];
        $difficulty_level = $_POST['difficulty_level'];
        
        if (empty($question_text)) {
            $error = 'Question text is required.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO secondary_questions (question_text, question_type, topic_id, points, difficulty_level) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$question_text, $question_type, $topic_id, $points, $difficulty_level]);
                $message = 'Question added successfully!';
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['update_question'])) {
        // Update question
        $question_id = (int)$_POST['question_id'];
        $question_text = trim($_POST['question_text']);
        $question_type = $_POST['question_type'];
        $points = (int)$_POST['points'];
        $difficulty_level = $_POST['difficulty_level'];
        
        if (empty($question_text)) {
            $error = 'Question text is required.';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE secondary_questions SET question_text = ?, question_type = ?, points = ?, difficulty_level = ? WHERE id = ?");
                $stmt->execute([$question_text, $question_type, $points, $difficulty_level, $question_id]);
                $message = 'Question updated successfully!';
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_question'])) {
        // Delete question
        $question_id = (int)$_POST['question_id'];
        
        try {
            $pdo->beginTransaction();
            
            // Delete related options and answers
            $pdo->prepare("DELETE FROM secondary_question_options WHERE question_id = ?")->execute([$question_id]);
            $pdo->prepare("DELETE FROM secondary_question_answers WHERE question_id = ?")->execute([$question_id]);
            
            // Delete question
            $stmt = $pdo->prepare("DELETE FROM secondary_questions WHERE id = ?");
            $stmt->execute([$question_id]);
            
            $message = 'Question deleted successfully!';
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Fetch data
$forms = [];
$subjects = [];
$current_subject = null;
$current_topic = null;
$current_question = null;

try {
    // Fetch forms for dropdown
    $forms_stmt = $pdo->prepare("SELECT * FROM forms ORDER BY form_number ASC");
    $forms_stmt->execute();
    $forms = $forms_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch subjects with form information and topic/question counts
    $subjects_stmt = $pdo->prepare("
        SELECT s.*, f.form_name, 
               (SELECT COUNT(*) FROM secondary_topics t WHERE t.subject_id = s.id) as topic_count,
               (SELECT COUNT(*) FROM secondary_questions q JOIN secondary_topics t ON q.topic_id = t.id WHERE t.subject_id = s.id) as question_count
        FROM secondary_subjects s 
        JOIN forms f ON s.form_id = f.id 
        ORDER BY f.form_number, s.subject_name
    ");
    $subjects_stmt->execute();
    $subjects = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch subject for editing
    if ($action === 'edit' && $subject_id > 0) {
        $subject_stmt = $pdo->prepare("SELECT s.*, f.form_name FROM secondary_subjects s JOIN forms f ON s.form_id = f.id WHERE s.id = ?");
        $subject_stmt->execute([$subject_id]);
        $current_subject = $subject_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$current_subject) {
            $error = 'Subject not found.';
            $action = '';
        }
    }
    
    // Fetch topic for editing
    if ($action === 'edit_topic' && $topic_id > 0) {
        $topic_stmt = $pdo->prepare("SELECT t.*, s.subject_name, f.form_name FROM secondary_topics t JOIN secondary_subjects s ON t.subject_id = s.id JOIN forms f ON s.form_id = f.id WHERE t.id = ?");
        $topic_stmt->execute([$topic_id]);
        $current_topic = $topic_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$current_topic) {
            $error = 'Topic not found.';
            $action = '';
        }
    }
    
    // Fetch question for editing
    if ($action === 'edit_question' && $question_id > 0) {
        $question_stmt = $pdo->prepare("
            SELECT q.*, t.topic_name, s.subject_name, f.form_name 
            FROM secondary_questions q 
            JOIN secondary_topics t ON q.topic_id = t.id 
            JOIN secondary_subjects s ON t.subject_id = s.id 
            JOIN forms f ON s.form_id = f.id 
            WHERE q.id = ?
        ");
        $question_stmt->execute([$question_id]);
        $current_question = $question_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$current_question) {
            $error = 'Question not found.';
            $action = '';
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
    <title>Manage Secondary Subjects - SmartLearn Admin</title>
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
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-section h1::before {
            content: "🎓";
            font-size: 28px;
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
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
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

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 14px;
        }

        .subjects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
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

        .subject-form {
            color: #666;
            font-size: 14px;
            background: #e9ecef;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: 600;
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

        .empty-state::before {
            content: "📚";
            font-size: 48px;
            display: block;
            margin-bottom: 15px;
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

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        .table tr:hover {
            background-color: #f5f5f5;
        }

        @media (max-width: 768px) {
            .subjects-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                flex-direction: column;
            }
            
            .subject-header {
                flex-direction: column;
            }
            
            .subject-actions {
                margin-top: 10px;
                width: 100%;
                justify-content: space-between;
            }
            
            .nav-links {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-section">
            <h1>Manage Secondary Subjects</h1>
        </div>
        <div class="nav-links">
            <a href="admin.php" class="back-link">Back to Dashboard</a>
            <a href="add_subject_secondary.php" class="primary-link">Add New Subject</a>
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
                <h2>✏️ Edit Subject</h2>
                
                <div class="subject-info">
                    <strong>Current Subject:</strong> <?php echo htmlspecialchars($current_subject['subject_name']); ?><br>
                    <strong>Form:</strong> <?php echo htmlspecialchars($current_subject['form_name']); ?>
                </div>

                <form method="POST">
                    <input type="hidden" name="subject_id" value="<?php echo $current_subject['id']; ?>">
                    
                    <div class="form-group">
                        <label for="form_id">Form:</label>
                        <select id="form_id" name="form_id" required>
                            <option value="">Choose Form</option>
                            <?php foreach ($forms as $form): ?>
                                <option value="<?php echo $form['id']; ?>" 
                                    <?php echo $current_subject['form_id'] == $form['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($form['form_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="subject_name">Subject Name:</label>
                        <input type="text" id="subject_name" name="subject_name" 
                               value="<?php echo htmlspecialchars($current_subject['subject_name']); ?>" 
                               placeholder="e.g., Mathematics, English, Biology" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description:</label>
                        <textarea id="description" name="description" 
                                  placeholder="Brief description of the subject"><?php echo htmlspecialchars($current_subject['description']); ?></textarea>
                    </div>

                    <div class="action-buttons">
                        <a href="manage_subject_secondary.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" name="update_subject" class="btn btn-success">Update Subject</button>
                    </div>
                </form>
            </div>

        <?php elseif ($action === 'delete' && $subject_id > 0): ?>
            <!-- Delete Subject Confirmation -->
            <?php
            $subject_to_delete = null;
            try {
                $stmt = $pdo->prepare("SELECT s.*, f.form_name FROM secondary_subjects s JOIN forms f ON s.form_id = f.id WHERE s.id = ?");
                $stmt->execute([$subject_id]);
                $subject_to_delete = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
            ?>
            <?php if ($subject_to_delete): ?>
                <div class="card">
                    <div class="delete-confirmation">
                        <div class="delete-icon">⚠️</div>
                        <h2>Delete Subject</h2>
                        <p>Are you sure you want to delete this subject?</p>
                        
                        <div class="subject-info">
                            <strong>Subject:</strong> <?php echo htmlspecialchars($subject_to_delete['subject_name']); ?><br>
                            <strong>Form:</strong> <?php echo htmlspecialchars($subject_to_delete['form_name']); ?>
                        </div>

                        <?php
                        // Check if subject has topics
                        $topics_stmt = $pdo->prepare("SELECT COUNT(*) FROM secondary_topics WHERE subject_id = ?");
                        $topics_stmt->execute([$subject_id]);
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
                                <a href="manage_subject_secondary.php" class="btn btn-secondary">Cancel</a>
                                <?php if ($topic_count == 0): ?>
                                    <button type="submit" name="delete_subject" class="btn btn-danger">Delete Subject</button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

        <?php elseif ($action === 'manage_topics' && $subject_id > 0): ?>
            <!-- Manage Topics View -->
            <?php
            $sub_stmt = $pdo->prepare("SELECT s.*, f.form_name FROM secondary_subjects s JOIN forms f ON s.form_id = f.id WHERE s.id = ?");
            $sub_stmt->execute([$subject_id]);
            $manage_subject = $sub_stmt->fetch(PDO::FETCH_ASSOC);
            ?>
            <?php if ($manage_subject): ?>
                <div class="card">
                    <h2>📚 Manage Topics - <?php echo htmlspecialchars($manage_subject['subject_name']); ?></h2>
                    
                    <div class="subject-info">
                        <strong>Subject:</strong> <?php echo htmlspecialchars($manage_subject['subject_name']); ?>
                        <br>
                        <strong>Form:</strong> <?php echo htmlspecialchars($manage_subject['form_name']); ?>
                    </div>

                    <!-- Add Topic Form -->
                    <div class="card" style="background: #f8f9fa; margin-bottom: 20px;">
                        <h3>➕ Add New Topic</h3>
                        <form method="POST">
                            <input type="hidden" name="subject_id" value="<?php echo $manage_subject['id']; ?>">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Topic Name</label>
                                    <input type="text" name="topic_name" placeholder="Enter topic name" required>
                                </div>
                                <div class="form-group">
                                    <label>Description</label>
                                    <input type="text" name="description" placeholder="Enter topic description">
                                </div>
                            </div>
                            <button type="submit" name="add_topic" class="btn btn-success">Add Topic</button>
                        </form>
                    </div>

                    <?php
                    $tstmt = $pdo->prepare("SELECT t.*, 
                                           (SELECT COUNT(*) FROM secondary_questions q WHERE q.topic_id = t.id) as question_count
                                           FROM secondary_topics t WHERE t.subject_id = ? ORDER BY order_index, topic_name");
                    $tstmt->execute([$subject_id]);
                    $subject_topics = $tstmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    
                    <?php if (empty($subject_topics)): ?>
                        <div class="empty-state">
                            <h3>No Topics Found</h3>
                            <p>Add topics for this subject using the form above.</p>
                        </div>
                    <?php else: ?>
                        <div class="subjects-grid">
                            <?php foreach ($subject_topics as $topic): ?>
                                <div class="subject-card">
                                    <div class="subject-header">
                                        <div>
                                            <div class="subject-title"><?php echo htmlspecialchars($topic['topic_name']); ?></div>
                                            <div class="subject-form"><?php echo $topic['question_count']; ?> question(s)</div>
                                        </div>
                                    </div>
                                    <?php if (!empty($topic['description'])): ?>
                                        <div class="subject-description"><?php echo htmlspecialchars($topic['description']); ?></div>
                                    <?php endif; ?>
                                    
                                    <div class="subject-meta">
                                        <div class="subject-actions">
                                            <a href="?action=edit_topic&topic_id=<?php echo $topic['id']; ?>" class="btn btn-small btn-secondary">Edit</a>
                                            <a href="?action=manage_questions&topic_id=<?php echo $topic['id']; ?>" class="btn btn-small btn-warning">Manage Questions</a>
                                            <a href="?action=delete_topic&topic_id=<?php echo $topic['id']; ?>" class="btn btn-small btn-danger">Delete</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="action-buttons">
                        <a href="manage_subject_secondary.php" class="btn btn-secondary">Back to Subjects</a>
                    </div>
                </div>
            <?php endif; ?>

        <?php elseif ($action === 'edit_topic' && $current_topic): ?>
            <!-- Edit Topic Form -->
            <div class="card">
                <h2>✏️ Edit Topic</h2>
                
                <div class="subject-info">
                    <strong>Topic:</strong> <?php echo htmlspecialchars($current_topic['topic_name']); ?><br>
                    <strong>Subject:</strong> <?php echo htmlspecialchars($current_topic['subject_name']); ?><br>
                    <strong>Form:</strong> <?php echo htmlspecialchars($current_topic['form_name']); ?>
                </div>

                <form method="POST">
                    <input type="hidden" name="topic_id" value="<?php echo $current_topic['id']; ?>">
                    
                    <div class="form-group">
                        <label for="topic_name">Topic Name:</label>
                        <input type="text" id="topic_name" name="topic_name" 
                               value="<?php echo htmlspecialchars($current_topic['topic_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description:</label>
                        <textarea id="description" name="description"><?php echo htmlspecialchars($current_topic['description']); ?></textarea>
                    </div>

                    <div class="action-buttons">
                        <a href="?action=manage_topics&id=<?php echo $current_topic['subject_id']; ?>" class="btn btn-secondary">Cancel</a>
                        <button type="submit" name="update_topic" class="btn btn-success">Update Topic</button>
                    </div>
                </form>
            </div>

        <?php elseif ($action === 'delete_topic' && $topic_id > 0): ?>
            <!-- Delete Topic Confirmation -->
            <?php
            $topic_to_delete = null;
            try {
                $stmt = $pdo->prepare("SELECT t.*, s.subject_name, f.form_name FROM secondary_topics t JOIN secondary_subjects s ON t.subject_id = s.id JOIN forms f ON s.form_id = f.id WHERE t.id = ?");
                $stmt->execute([$topic_id]);
                $topic_to_delete = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
            ?>
            <?php if ($topic_to_delete): ?>
                <div class="card">
                    <div class="delete-confirmation">
                        <div class="delete-icon">⚠️</div>
                        <h2>Delete Topic</h2>
                        <p>Are you sure you want to delete this topic?</p>
                        
                        <div class="subject-info">
                            <strong>Topic:</strong> <?php echo htmlspecialchars($topic_to_delete['topic_name']); ?><br>
                            <strong>Subject:</strong> <?php echo htmlspecialchars($topic_to_delete['subject_name']); ?><br>
                            <strong>Form:</strong> <?php echo htmlspecialchars($topic_to_delete['form_name']); ?>
                        </div>

                        <?php
                        // Check if topic has questions
                        $questions_stmt = $pdo->prepare("SELECT COUNT(*) FROM secondary_questions WHERE topic_id = ?");
                        $questions_stmt->execute([$topic_id]);
                        $question_count = $questions_stmt->fetchColumn();
                        
                        if ($question_count > 0): ?>
                            <div class="warning">
                                <strong>Warning:</strong> This topic has <?php echo $question_count; ?> question(s) associated with it. 
                                You must delete all questions before you can delete this topic.
                            </div>
                        <?php endif; ?>

                        <form method="POST" style="margin-top: 20px;">
                            <input type="hidden" name="topic_id" value="<?php echo $topic_to_delete['id']; ?>">
                            
                            <div class="action-buttons">
                                <a href="?action=manage_topics&id=<?php echo $topic_to_delete['subject_id']; ?>" class="btn btn-secondary">Cancel</a>
                                <?php if ($question_count == 0): ?>
                                    <button type="submit" name="delete_topic" class="btn btn-danger">Delete Topic</button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

        <?php elseif ($action === 'manage_questions' && $topic_id > 0): ?>
            <!-- Manage Questions View -->
            <?php
            $topic_stmt = $pdo->prepare("SELECT t.*, s.subject_name, f.form_name FROM secondary_topics t JOIN secondary_subjects s ON t.subject_id = s.id JOIN forms f ON s.form_id = f.id WHERE t.id = ?");
            $topic_stmt->execute([$topic_id]);
            $manage_topic = $topic_stmt->fetch(PDO::FETCH_ASSOC);
            ?>
            <?php if ($manage_topic): ?>
                <div class="card">
                    <h2>❓ Manage Questions - <?php echo htmlspecialchars($manage_topic['topic_name']); ?></h2>
                    
                    <div class="subject-info">
                        <strong>Topic:</strong> <?php echo htmlspecialchars($manage_topic['topic_name']); ?>
                        <br>
                        <strong>Subject:</strong> <?php echo htmlspecialchars($manage_topic['subject_name']); ?>
                        <br>
                        <strong>Form:</strong> <?php echo htmlspecialchars($manage_topic['form_name']); ?>
                    </div>

                    <!-- Add Question Form -->
                    <div class="card" style="background: #f8f9fa; margin-bottom: 20px;">
                        <h3>➕ Add New Question</h3>
                        <form method="POST">
                            <input type="hidden" name="topic_id" value="<?php echo $manage_topic['id']; ?>">
                            <div class="form-group">
                                <label>Question Text</label>
                                <textarea name="question_text" placeholder="Enter the question" required></textarea>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Question Type</label>
                                    <select name="question_type" required>
                                        <option value="multiple_choice">Multiple Choice</option>
                                        <option value="one_word">One Word Answer</option>
                                        <option value="true_false">True/False</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Points</label>
                                    <input type="number" name="points" value="1" min="1" required>
                                </div>
                                <div class="form-group">
                                    <label>Difficulty Level</label>
                                    <select name="difficulty_level" required>
                                        <option value="easy">Easy</option>
                                        <option value="medium" selected>Medium</option>
                                        <option value="hard">Hard</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" name="add_question" class="btn btn-success">Add Question</button>
                        </form>
                    </div>

                    <?php
                    $qstmt = $pdo->prepare("SELECT * FROM secondary_questions WHERE topic_id = ? ORDER BY order_index, id");
                    $qstmt->execute([$topic_id]);
                    $topic_questions = $qstmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    
                    <?php if (empty($topic_questions)): ?>
                        <div class="empty-state">
                            <h3>No Questions Found</h3>
                            <p>Add questions for this topic using the form above.</p>
                        </div>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Question</th>
                                    <th>Type</th>
                                    <th>Points</th>
                                    <th>Difficulty</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topic_questions as $question): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(substr($question['question_text'], 0, 100)); ?><?php echo strlen($question['question_text']) > 100 ? '...' : ''; ?></td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $question['question_type'])); ?></td>
                                        <td><?php echo $question['points']; ?></td>
                                        <td><?php echo ucfirst($question['difficulty_level']); ?></td>
                                        <td>
                                            <a href="?action=edit_question&question_id=<?php echo $question['id']; ?>" class="btn btn-small btn-secondary">Edit</a>
                                            <a href="?action=delete_question&question_id=<?php echo $question['id']; ?>" class="btn btn-small btn-danger">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    
                    <div class="action-buttons">
                        <a href="?action=manage_topics&id=<?php echo $manage_topic['subject_id']; ?>" class="btn btn-secondary">Back to Topics</a>
                    </div>
                </div>
            <?php endif; ?>

        <?php elseif ($action === 'edit_question' && $current_question): ?>
            <!-- Edit Question Form -->
            <div class="card">
                <h2>✏️ Edit Question</h2>
                
                <div class="subject-info">
                    <strong>Topic:</strong> <?php echo htmlspecialchars($current_question['topic_name']); ?><br>
                    <strong>Subject:</strong> <?php echo htmlspecialchars($current_question['subject_name']); ?><br>
                    <strong>Form:</strong> <?php echo htmlspecialchars($current_question['form_name']); ?>
                </div>

                <form method="POST">
                    <input type="hidden" name="question_id" value="<?php echo $current_question['id']; ?>">
                    
                    <div class="form-group">
                        <label for="question_text">Question Text:</label>
                        <textarea id="question_text" name="question_text" required><?php echo htmlspecialchars($current_question['question_text']); ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="question_type">Question Type:</label>
                            <select id="question_type" name="question_type" required>
                                <option value="multiple_choice" <?php echo $current_question['question_type'] == 'multiple_choice' ? 'selected' : ''; ?>>Multiple Choice</option>
                                <option value="one_word" <?php echo $current_question['question_type'] == 'one_word' ? 'selected' : ''; ?>>One Word Answer</option>
                                <option value="true_false" <?php echo $current_question['question_type'] == 'true_false' ? 'selected' : ''; ?>>True/False</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="points">Points:</label>
                            <input type="number" id="points" name="points" value="<?php echo $current_question['points']; ?>" min="1" required>
                        </div>
                        <div class="form-group">
                            <label for="difficulty_level">Difficulty Level:</label>
                            <select id="difficulty_level" name="difficulty_level" required>
                                <option value="easy" <?php echo $current_question['difficulty_level'] == 'easy' ? 'selected' : ''; ?>>Easy</option>
                                <option value="medium" <?php echo $current_question['difficulty_level'] == 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="hard" <?php echo $current_question['difficulty_level'] == 'hard' ? 'selected' : ''; ?>>Hard</option>
                            </select>
                        </div>
                    </div>

                    <div class="action-buttons">
                        <a href="?action=manage_questions&topic_id=<?php echo $current_question['topic_id']; ?>" class="btn btn-secondary">Cancel</a>
                        <button type="submit" name="update_question" class="btn btn-success">Update Question</button>
                    </div>
                </form>
            </div>

        <?php elseif ($action === 'delete_question' && $question_id > 0): ?>
            <!-- Delete Question Confirmation -->
            <?php
            $question_to_delete = null;
            try {
                $stmt = $pdo->prepare("
                    SELECT q.*, t.topic_name, s.subject_name, f.form_name 
                    FROM secondary_questions q 
                    JOIN secondary_topics t ON q.topic_id = t.id 
                    JOIN secondary_subjects s ON t.subject_id = s.id 
                    JOIN forms f ON s.form_id = f.id 
                    WHERE q.id = ?
                ");
                $stmt->execute([$question_id]);
                $question_to_delete = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
            ?>
            <?php if ($question_to_delete): ?>
                <div class="card">
                    <div class="delete-confirmation">
                        <div class="delete-icon">⚠️</div>
                        <h2>Delete Question</h2>
                        <p>Are you sure you want to delete this question?</p>
                        
                        <div class="subject-info">
                            <strong>Question:</strong> <?php echo htmlspecialchars($question_to_delete['question_text']); ?><br>
                            <strong>Topic:</strong> <?php echo htmlspecialchars($question_to_delete['topic_name']); ?><br>
                            <strong>Subject:</strong> <?php echo htmlspecialchars($question_to_delete['subject_name']); ?><br>
                            <strong>Form:</strong> <?php echo htmlspecialchars($question_to_delete['form_name']); ?>
                        </div>

                        <form method="POST" style="margin-top: 20px;">
                            <input type="hidden" name="question_id" value="<?php echo $question_to_delete['id']; ?>">
                            
                            <div class="action-buttons">
                                <a href="?action=manage_questions&topic_id=<?php echo $question_to_delete['topic_id']; ?>" class="btn btn-secondary">Cancel</a>
                                <button type="submit" name="delete_question" class="btn btn-danger">Delete Question</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Subjects List -->
            <div class="card">
                <h2>
                    📚 Manage Secondary Subjects
                    <span style="font-size: 16px; color: #666;"><?php echo count($subjects); ?> subject(s)</span>
                </h2>

                <?php if (empty($subjects)): ?>
                    <div class="empty-state">
                        <h3>No Subjects Found</h3>
                        <p>You haven't added any subjects yet.</p>
                        <a href="add_subject_secondary.php" class="btn primary-link" style="margin-top: 15px;">Add Your First Subject</a>
                    </div>
                <?php else: ?>
                    <div class="subjects-grid">
                        <?php foreach ($subjects as $subject): ?>
                            <div class="subject-card">
                                <div class="subject-header">
                                    <div>
                                        <div class="subject-title"><?php echo htmlspecialchars($subject['subject_name']); ?></div>
                                        <div class="subject-form"><?php echo htmlspecialchars($subject['form_name']); ?></div>
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
                                        <a href="?action=manage_topics&id=<?php echo $subject['id']; ?>" class="btn btn-small btn-warning">Manage Topics</a>
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
        // Confirm delete actions
        document.addEventListener('DOMContentLoaded', function() {
            const deleteLinks = document.querySelectorAll('a.btn-danger');
            deleteLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to delete this item?')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>