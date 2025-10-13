<?php
require_once 'includes/config.php';
include 'login_check.php';

// Use the shared login guard
requireLogin();

$user_id = $_SESSION['user_id'];
$form_level = $_GET['form_level'] ?? 1; // Default to Form 1

// Initialize variables
$show_results = false;
$score = 0;
$max_possible_score = 0;
$percentage = 0;
$results = [];
$user_answers = [];
$total_questions = 0;
$correct_count = 0;

// Fetch available forms from database
$available_forms = [];
try {
    $forms_stmt = $pdo->prepare("SELECT * FROM secondary_forms ORDER BY form_number ASC");
    $forms_stmt->execute();
    $available_forms = $forms_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no forms are available, set default form level to 1
    if (empty($available_forms)) {
        $form_level = 1;
    } else {
        // Validate that the selected form level exists in the database
        $form_exists = false;
        foreach ($available_forms as $form) {
            if ($form['form_number'] == $form_level) {
                $form_exists = true;
                break;
            }
        }
        // If the selected form doesn't exist, default to the first available form
        if (!$form_exists) {
            $form_level = $available_forms[0]['form_number'];
        }
    }
} catch (PDOException $e) {
    // If there's an error fetching forms, default to form 1
    $form_level = 1;
    $available_forms = [];
}

// Fetch subjects and topics for Secondary level
$subjects = [];
$topics = [];
$questions = [];
$selected_subject_id = $_GET['subject_id'] ?? $_GET['subject'] ?? null;
$selected_topic_id = $_GET['topic_id'] ?? null;
$current_subject = null;
$current_topic = null;

// Persist IDs on POST submissions so grading and results work reliably
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['form_level']) && $_POST['form_level'] !== '') {
        $form_level = (int)$_POST['form_level'];
    }
    if (isset($_POST['subject_id']) && $_POST['subject_id'] !== '') {
        $selected_subject_id = $_POST['subject_id'];
    }
    if (isset($_POST['topic_id']) && $_POST['topic_id'] !== '') {
        $selected_topic_id = $_POST['topic_id'];
    }
}

try {
    // Fetch subjects for the selected form level
    $subjects_stmt = $pdo->prepare("
        SELECT s.*, f.form_name, f.form_number
        FROM secondary_subjects s 
        JOIN secondary_forms f ON s.form_id = f.id 
        WHERE f.form_number = ? 
        ORDER BY s.subject_name
    ");
    $subjects_stmt->execute([$form_level]);
    $subjects = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);

    // If topic is selected but subject is not (or incorrect), derive subject from topic
    if ($selected_topic_id && !$selected_subject_id) {
        $derive_stmt = $pdo->prepare("
            SELECT s.id as subject_id, s.subject_name, t.*
            FROM secondary_topics t
            JOIN secondary_subjects s ON t.subject_id = s.id
            JOIN secondary_forms f ON s.form_id = f.id
            WHERE t.id = ? AND f.form_number = ?
        ");
        $derive_stmt->execute([$selected_topic_id, $form_level]);
        $found = $derive_stmt->fetch(PDO::FETCH_ASSOC);
        if ($found) {
            $selected_subject_id = (string)$found['subject_id'];
            $current_subject = ['id' => $found['subject_id'], 'subject_name' => $found['subject_name']];
            $current_topic = $found;
        }
    }

    // Fetch topics when a subject is determined
    if ($selected_subject_id) {
        // Get current subject info
        foreach ($subjects as $subject) {
            if ($subject['id'] == $selected_subject_id) {
                $current_subject = $subject;
                break;
            }
        }
        
        $topics_stmt = $pdo->prepare("
            SELECT t.*, s.subject_name,
                   (SELECT COUNT(*) FROM secondary_questions q WHERE q.topic_id = t.id) AS question_count
            FROM secondary_topics t 
            JOIN secondary_subjects s ON t.subject_id = s.id 
            WHERE s.id = ? 
            ORDER BY t.order_index
        ");
        $topics_stmt->execute([$selected_subject_id]);
        $topics = $topics_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fetch questions when a specific topic is selected
    if ($selected_topic_id) {
        // Verify topic belongs to a subject in the current form level; also derive subject if needed
        $verify_stmt = $pdo->prepare("
            SELECT t.*, s.subject_name, s.id AS subject_id, f.form_number
            FROM secondary_topics t
            JOIN secondary_subjects s ON t.subject_id = s.id
            JOIN secondary_forms f ON s.form_id = f.id
            WHERE t.id = ? AND f.form_number = ?
        ");
        $verify_stmt->execute([$selected_topic_id, $form_level]);
        $current_topic = $verify_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($current_topic) {
            // Ensure selected subject aligns with topic's subject for consistency
            $selected_subject_id = (string)($current_topic['subject_id'] ?? $selected_subject_id);
            $questions_stmt = $pdo->prepare("
                SELECT q.*, t.topic_name, s.subject_name,
                       (SELECT COUNT(*) FROM secondary_question_options WHERE question_id = q.id) as option_count,
                       (SELECT COUNT(*) FROM secondary_question_answers WHERE question_id = q.id) as answer_count
                FROM secondary_questions q 
                JOIN secondary_topics t ON q.topic_id = t.id 
                JOIN secondary_subjects s ON t.subject_id = s.id 
                WHERE t.id = ?
                ORDER BY q.order_index ASC, q.id ASC
            ");
            $questions_stmt->execute([$selected_topic_id]);
            $questions = $questions_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch options for multiple choice questions
            foreach ($questions as &$question) {
                if ($question['question_type'] === 'multiple_choice') {
                    // Load options and derive correct answers from options table
                    $options_stmt = $pdo->prepare("
                        SELECT id, option_text, is_correct, order_index FROM secondary_question_options 
                        WHERE question_id = ? 
                        ORDER BY order_index, id
                    ");
                    $options_stmt->execute([$question['id']]);
                    $question['options'] = $options_stmt->fetchAll(PDO::FETCH_ASSOC);

                    $question['correct_answers'] = array_values(array_map(function($opt) {
                        return ['answer_text' => $opt['option_text'], 'is_case_sensitive' => 0];
                    }, array_filter($question['options'], function($opt) { return (int)$opt['is_correct'] === 1; })));
                } else {
                    // Fetch correct answers for one_word and true_false from question_answers
                    $answers_stmt = $pdo->prepare("
                        SELECT answer_text, is_case_sensitive 
                        FROM secondary_question_answers 
                        WHERE question_id = ?
                    ");
                    $answers_stmt->execute([$question['id']]);
                    $question['correct_answers'] = $answers_stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            }
            unset($question);
        } else {
            // Invalid topic selection for this form
            $selected_topic_id = null;
            $error = "Invalid topic selection for the selected form.";
        }
    }

} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Handle question submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_answers']) && $selected_topic_id) {
    $score = 0;
    $total_questions = count($questions);
    $results = [];
    $attempt_data = [];
    $user_answers = [];

    foreach ($questions as $question) {
        $question_id = $question['id'];
        $user_answer = $_POST['answer_' . $question_id] ?? '';
        $user_answers[$question_id] = $user_answer;
        
        if ($question['question_type'] === 'multiple_choice') {
            // For multiple choice, check if the selected option is correct
            $option_stmt = $pdo->prepare("
                SELECT is_correct, option_text FROM secondary_question_options 
                WHERE question_id = ? AND id = ?
            ");
            $option_stmt->execute([$question_id, $user_answer]);
            $option_result = $option_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($option_result && $option_result['is_correct']) {
                $score += $question['points'];
                $results[$question_id] = [
                    'correct' => true, 
                    'points' => $question['points'],
                    'user_answer_text' => $option_result['option_text']
                ];
            } else {
                $results[$question_id] = [
                    'correct' => false, 
                    'points' => 0,
                    'user_answer_text' => $option_result ? $option_result['option_text'] : 'Not answered'
                ];
            }
            
        } elseif ($question['question_type'] === 'one_word') {
            // For one word answers, check against stored answers
            $is_correct = false;
            $correct_answer_text = '';
            
            foreach ($question['correct_answers'] as $correct_answer) {
                if ($correct_answer['is_case_sensitive']) {
                    if (trim($user_answer) === trim($correct_answer['answer_text'])) {
                        $is_correct = true;
                        $correct_answer_text = $correct_answer['answer_text'];
                        break;
                    }
                } else {
                    if (strtolower(trim($user_answer)) === strtolower(trim($correct_answer['answer_text']))) {
                        $is_correct = true;
                        $correct_answer_text = $correct_answer['answer_text'];
                        break;
                    }
                }
            }
            
            if ($is_correct) {
                $score += $question['points'];
                $results[$question_id] = [
                    'correct' => true, 
                    'points' => $question['points'],
                    'user_answer_text' => $user_answer
                ];
            } else {
                $results[$question_id] = [
                    'correct' => false, 
                    'points' => 0,
                    'user_answer_text' => $user_answer ?: 'Not answered'
                ];
            }
            
        } elseif ($question['question_type'] === 'true_false') {
            // For true/false questions
            $correct_answer = $question['correct_answers'][0]['answer_text'] ?? '';
            
            if (strtolower(trim($user_answer)) === strtolower(trim($correct_answer))) {
                $score += $question['points'];
                $results[$question_id] = [
                    'correct' => true, 
                    'points' => $question['points'],
                    'user_answer_text' => $user_answer
                ];
            } else {
                $results[$question_id] = [
                    'correct' => false, 
                    'points' => 0,
                    'user_answer_text' => $user_answer ?: 'Not answered'
                ];
            }
        }
        
        // Store attempt data for batch insertion
        $attempt_data[] = [
            'user_id' => $user_id,
            'question_id' => $question_id,
            'user_answer' => $user_answer,
            'is_correct' => $results[$question_id]['correct'] ? 1 : 0,
            'points_earned' => $results[$question_id]['points'],
            'topic_id' => $selected_topic_id
        ];
    }
    
    // Save all attempts to database in a single transaction
    try {
        // Create attempts table if it doesn't exist (enhanced version)
        $pdo->exec("CREATE TABLE IF NOT EXISTS secondary_question_attempts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            question_id INT UNSIGNED NOT NULL,
            topic_id INT UNSIGNED NOT NULL,
            user_answer TEXT,
            is_correct BOOLEAN DEFAULT FALSE,
            points_earned INT UNSIGNED DEFAULT 0,
            attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_attempts_user (user_id),
            INDEX idx_attempts_question (question_id),
            INDEX idx_attempts_topic (topic_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        $pdo->beginTransaction();
        
        $attempt_stmt = $pdo->prepare("
            INSERT INTO secondary_question_attempts (user_id, question_id, topic_id, user_answer, is_correct, points_earned) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($attempt_data as $attempt) {
            $attempt_stmt->execute([
                $attempt['user_id'],
                $attempt['question_id'],
                $attempt['topic_id'],
                $attempt['user_answer'],
                $attempt['is_correct'],
                $attempt['points_earned']
            ]);
        }
        
        $pdo->commit();
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        // Continue execution even if saving attempts fails
        error_log("Failed to save question attempts: " . $e->getMessage());
    }
    
    $max_possible_score = array_sum(array_column($questions, 'points'));
    $percentage = $max_possible_score > 0 ? round(($score / $max_possible_score) * 100, 2) : 0;
    $show_results = true;

    // Store results in session for persistence
    $_SESSION['secondary_quiz_results'] = [
        'score' => $score,
        'max_score' => $max_possible_score,
        'percentage' => $percentage,
        'results' => $results,
        'user_answers' => $user_answers,
        'topic_id' => (int)$selected_topic_id,
        'subject_id' => $selected_subject_id,
        'form_level' => $form_level,
        'total_questions' => $total_questions
    ];
}

// Check if we should show results from previous submission
if (isset($_GET['show_results']) && isset($_SESSION['secondary_quiz_results']) && (int)$_SESSION['secondary_quiz_results']['topic_id'] == (int)$selected_topic_id) {
    $show_results = true;
    $score = $_SESSION['secondary_quiz_results']['score'];
    $max_possible_score = $_SESSION['secondary_quiz_results']['max_score'];
    $percentage = $_SESSION['secondary_quiz_results']['percentage'];
    $results = $_SESSION['secondary_quiz_results']['results'];
    $user_answers = $_SESSION['secondary_quiz_results']['user_answers'];
    $total_questions = $_SESSION['secondary_quiz_results']['total_questions'];
    
    // Calculate correct count
    $correct_count = count(array_filter($results, function($r) { return $r['correct']; }));
    
    // Ensure questions are loaded for results display
    if (empty($questions) && $selected_topic_id) {
        $questions_stmt = $pdo->prepare("
            SELECT q.*, t.topic_name, s.subject_name,
                   (SELECT COUNT(*) FROM secondary_question_options WHERE question_id = q.id) as option_count,
                   (SELECT COUNT(*) FROM secondary_question_answers WHERE question_id = q.id) as answer_count
            FROM secondary_questions q 
            JOIN secondary_topics t ON q.topic_id = t.id 
            JOIN secondary_subjects s ON t.subject_id = s.id 
            WHERE t.id = ?
            ORDER BY q.order_index ASC, q.id ASC
        ");
        $questions_stmt->execute([$selected_topic_id]);
        $questions = $questions_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch options for multiple choice questions
        foreach ($questions as &$question) {
            if ($question['question_type'] === 'multiple_choice') {
                $options_stmt = $pdo->prepare("
                    SELECT id, option_text, is_correct, order_index FROM secondary_question_options 
                    WHERE question_id = ? 
                    ORDER BY order_index, id
                ");
                $options_stmt->execute([$question['id']]);
                $question['options'] = $options_stmt->fetchAll(PDO::FETCH_ASSOC);
                $question['correct_answers'] = array_values(array_map(function($opt) {
                    return ['answer_text' => $opt['option_text'], 'is_case_sensitive' => 0];
                }, array_filter($question['options'], function($opt) { return (int)$opt['is_correct'] === 1; })));
            } else {
                $answers_stmt = $pdo->prepare("
                    SELECT answer_text, is_case_sensitive 
                    FROM secondary_question_answers 
                    WHERE question_id = ?
                ");
                $answers_stmt->execute([$question['id']]);
                $question['correct_answers'] = $answers_stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        unset($question);
    }
    
    // Clear session data after displaying results to prevent stale data
    unset($_SESSION['secondary_quiz_results']);
} elseif (isset($_GET['show_results']) && $selected_topic_id) {
    // Fallback: load latest attempts from DB to show last scores for this topic
    try {
        $attempts_stmt = $pdo->prepare("SELECT question_id, user_answer, is_correct, points_earned, attempted_at FROM secondary_question_attempts WHERE user_id = ? AND topic_id = ? ORDER BY attempted_at DESC");
        $attempts_stmt->execute([$user_id, $selected_topic_id]);
        $rows = $attempts_stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            $latest_per_question = [];
            foreach ($rows as $row) {
                $qid = (int)$row['question_id'];
                if (!isset($latest_per_question[$qid])) {
                    $latest_per_question[$qid] = $row;
                }
            }
            // Build results from latest attempts
            $results = [];
            $user_answers = [];
            $score = 0;
            $max_possible_score = array_sum(array_column($questions, 'points'));
            $total_questions = count($questions);
            foreach ($questions as $question) {
                $qid = (int)$question['id'];
                $attempt = $latest_per_question[$qid] ?? null;
                $user_answer_text = 'Not answered';
                if ($attempt) {
                    $ua = $attempt['user_answer'];
                    if ($question['question_type'] === 'multiple_choice') {
                        // Translate option id to text if numeric
                        $optText = null;
                        if (ctype_digit((string)$ua) && !empty($question['options'])) {
                            foreach ($question['options'] as $opt) {
                                if ((string)$opt['id'] === (string)$ua) { $optText = $opt['option_text']; break; }
                            }
                        }
                        $user_answer_text = $optText !== null ? $optText : 'Not answered';
                    } else {
                        $user_answer_text = (string)$ua;
                    }
                    $user_answers[$qid] = $ua;
                    $is_correct = (bool)$attempt['is_correct'];
                    $earned = (int)$attempt['points_earned'];
                } else {
                    $is_correct = false;
                    $earned = 0;
                    $user_answers[$qid] = '';
                }
                $results[$qid] = [
                    'correct' => $is_correct,
                    'points' => $earned,
                    'user_answer_text' => $user_answer_text
                ];
                $score += $earned;
            }
            $percentage = $max_possible_score > 0 ? round(($score / $max_possible_score) * 100, 2) : 0;
            $show_results = true;
        } else {
            $error = 'No previous attempts found for this topic.';
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php 
        $title_form_name = 'Form ' . $form_level;
        foreach ($available_forms as $form) {
            if ($form['form_number'] == $form_level) {
                $title_form_name = $form['form_name'];
                break;
            }
        }
        echo htmlspecialchars($title_form_name);
        ?> Subjects - SmartLearn</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            flex-direction: column;
            min-block-size: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="80" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="60" r="0.5" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            z-index: -1;
        }
        
        /* Header */
        .header {
            position: sticky;
            inset-block-start: 0;
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

        /* Form Selection */
        .form-selection {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 30px;
            margin-block-end: 20px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .form-title {
            color: #2c3e50;
            margin-block-end: 15px;
            font-size: 24px;
        }

        .form-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .form-btn {
            padding: 15px 25px;
            border: 2px solid #667eea;
            background: rgba(255, 255, 255, 0.9);
            color: #667eea;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
        }

        .form-btn:hover {
            background: #667eea;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .form-btn.active {
            background: #667eea;
            color: white;
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
        }

        /* Main Layout */
        .main-container {
            display: flex;
            flex: 1;
        }

        /* Sidebar Menu */
        .sidebar-menu {
            inline-size: 300px;
            background-color: #2c3e50;
            color: #ecf0f1;
            block-size: calc(100vh - 60px);
            position: sticky;
            inset-block-start: 60px;
            padding: 20px 0;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            overflow-y: auto;
        }

        .sidebar-title {
            font-size: 16px;
            font-weight: 600;
            padding: 0 20px 15px 20px;
            border-block-end: 1px solid rgba(236, 240, 241, 0.1);
            margin-block-end: 15px;
            color: #ecf0f1;
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
        }

        .side-link.active {
            background-color: #3498db;
            color: white;
        }

        .subject-header {
            font-weight: 600;
            color: #3498db;
            padding: 12px 15px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: space-between;
            text-decoration: none;
        }

        .subject-header:hover {
            background-color: rgba(52, 152, 219, 0.1);
        }

        .subject-header.active {
            background-color: rgba(52, 152, 219, 0.2);
        }

        .topics-list {
            padding-inline-start: 20px;
            margin-block-end: 10px;
        }

        .topic-link {
            padding: 10px 15px;
            margin-inline-start: 10px;
            border-inline-start: 2px solid rgba(236, 240, 241, 0.3);
        }

        .topic-link.active {
            border-inline-start-color: #3498db;
            background-color: rgba(52, 152, 219, 0.1);
        }

        .breadcrumb {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 20px 30px;
            margin-block-end: 20px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .breadcrumb a {
            color: #3498db;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 30px;
            background-color: #f5f7fa;
        }

        .page-title {
            color: #2c3e50;
            margin-block-end: 20px;
            font-size: 28px;
        }

        .content-area {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            min-block-size: 400px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .welcome-message {
            text-align: center;
            padding: 60px 20px;
        }

        .welcome-icon {
            font-size: 64px;
            color: #bdc3c7;
            margin-block-end: 20px;
        }

        .welcome-message h3 {
            color: #7f8c8d;
            margin-block-end: 10px;
        }

        .welcome-message p {
            color: #95a5a6;
            max-inline-size: 500px;
            margin: 0 auto 20px;
        }

        .subject-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-block-start: 20px;
        }

        .subject-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .subject-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.4);
        }

        .subject-card i {
            font-size: 48px;
            margin-block-end: 15px;
        }

        .subject-card h3 {
            font-size: 20px;
            margin-block-end: 10px;
        }

        .topic-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-block-start: 20px;
        }

        .topic-card {
            background: linear-gradient(135deg, #3498db 0%, #2c3e50 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(52, 152, 219, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .topic-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(52, 152, 219, 0.4);
        }

        .topic-card i {
            font-size: 36px;
            margin-block-end: 15px;
        }

        /* Questions Section */
        .questions-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .question-count {
            color: #7f8c8d;
            margin-block-end: 20px;
            font-size: 16px;
        }

        .question-item {
            background: rgba(255, 255, 255, 0.8);
            padding: 30px;
            border-radius: 15px;
            margin-block-end: 25px;
            border-inline-start: 4px solid #667eea;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .question-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-block-end: 15px;
        }

        .question-text {
            color: #2c3e50;
            font-size: 18px;
            font-weight: 600;
            flex: 1;
        }

        .question-meta {
            display: flex;
            gap: 10px;
            margin-inline-start: 20px;
        }

        .meta-badge {
            background: #e9ecef;
            color: #495057;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .options-container {
            margin-block-start: 15px;
        }

        .option-item {
            margin-block-end: 10px;
        }

        .option-label {
            display: flex;
            align-items: center;
            padding: 12px;
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .option-label:hover {
            border-color: #3498db;
            background: #f8f9fa;
        }

        .option-input {
            margin-inline-end: 10px;
        }

        .answer-input {
            inline-size: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .answer-input:focus {
            outline: none;
            border-color: #3498db;
        }

        .true-false-options {
            display: flex;
            gap: 15px;
        }

        .true-false-btn {
            flex: 1;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            background: white;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s ease;
        }

        .true-false-btn:hover {
            border-color: #3498db;
            background: #f8f9fa;
        }

        .true-false-btn.selected {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }

        .submit-section {
            text-align: center;
            margin-block-start: 30px;
            padding-block-start: 20px;
            border-block-start: 2px solid #e9ecef;
        }

        .submit-btn {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            border: none;
            padding: 18px 45px;
            border-radius: 25px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.4);
        }

        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(39, 174, 96, 0.6);
        }

        /* Enhanced Results Section */
        .results-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            margin-block-end: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .score-display {
            text-align: center;
            margin-block-end: 30px;
        }

        .score-circle {
            inline-size: 120px;
            block-size: 120px;
            border-radius: 50%;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            margin: 0 auto 20px;
            transition: all 0.3s ease;
        }

        .score-circle.pass {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
        }

        .score-circle.fail {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }

        .pass-text {
            color: #27ae60;
            font-weight: 600;
        }

        .fail-text {
            color: #e74c3c;
            font-weight: 600;
        }

        .performance-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-block-start: 20px;
            border-inline-start: 4px solid #3498db;
        }

        .performance-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-block-end: 1px solid #e9ecef;
        }

        .performance-item:last-child {
            border-block-end: none;
        }

        .performance-label {
            font-weight: 600;
            color: #2c3e50;
        }

        .performance-value {
            font-weight: 600;
            color: #3498db;
        }

        .result-item {
            background: rgba(255, 255, 255, 0.8);
            padding: 25px;
            border-radius: 12px;
            margin-block-end: 15px;
            border-inline-start: 4px solid #27ae60;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .result-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .result-item.incorrect {
            border-inline-start-color: #e74c3c;
        }

        .result-status {
            font-weight: 600;
            margin-block-end: 10px;
        }

        .result-status.correct {
            color: #27ae60;
        }

        .result-status.incorrect {
            color: #e74c3c;
        }

        .user-answer, .correct-answer {
            margin: 5px 0;
            padding: 8px;
            border-radius: 4px;
        }

        .user-answer {
            background: #fff3cd;
            border-inline-start: 3px solid #ffc107;
        }

        .correct-answer {
            background: #d1ecf1;
            border-inline-start: 3px solid #17a2b8;
        }

        .points-earned {
            background: #d5f4e6;
            color: #27ae60;
            padding: 8px 12px;
            border-radius: 4px;
            margin-block-start: 8px;
            font-weight: 600;
            border-inline-start: 3px solid #27ae60;
        }

        /* Progress bar for visual representation */
        .progress-container {
            background: #ecf0f1;
            border-radius: 10px;
            block-size: 20px;
            margin: 15px 0;
            overflow: hidden;
        }

        .progress-bar {
            block-size: 100%;
            background: linear-gradient(135deg, #3498db 0%, #2c3e50 100%);
            border-radius: 10px;
            transition: width 0.5s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: 600;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-block-start: 30px;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background: #219653;
            transform: translateY(-2px);
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 6px;
            margin-block-end: 20px;
            border-inline-start: 4px solid #e74c3c;
        }

        .success-message {
            background: #d1edff;
            color: #155724;
            padding: 15px;
            border-radius: 6px;
            margin-block-end: 20px;
            border-inline-start: 4px solid #3498db;
        }

        @media (max-inline-size: 768px) {
            .main-container {
                flex-direction: column;
            }
            
            .sidebar-menu {
                inline-size: 100%;
                block-size: auto;
                position: relative;
                inset-block-start: 0;
            }
            
            .question-header {
                flex-direction: column;
                gap: 10px;
            }
            
            .question-meta {
                margin-inline-start: 0;
            }
            
            .subject-grid,
            .topic-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .form-buttons {
                flex-direction: column;
            }
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
            <a class="header-link" href="logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>
    </div>

    <!-- Form Selection -->
    <div class="form-selection">
        <h2 class="form-title">Select Form Level</h2>
        <div class="form-buttons">
            <?php if (!empty($available_forms)): ?>
                <?php foreach ($available_forms as $form): ?>
                    <a href="secondary_subject.php?form_level=<?php echo $form['form_number']; ?>" 
                       class="form-btn <?php echo $form_level == $form['form_number'] ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($form['form_name']); ?>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: #7f8c8d; text-align: center; padding: 20px;">
                    <i class="fas fa-info-circle"></i> No forms have been added yet. Please contact your administrator.
                </p>
            <?php endif; ?>
        </div>
    </div>

    <div class="main-container">
        <!-- Sidebar Menu -->
        <aside class="sidebar-menu">
            <div class="sidebar-title">
                <?php 
                $current_form_name = 'FORM ' . $form_level;
                foreach ($available_forms as $form) {
                    if ($form['form_number'] == $form_level) {
                        $current_form_name = strtoupper($form['form_name']) . ' SUBJECTS';
                        break;
                    }
                }
                echo $current_form_name;
                ?>
            </div>
            <nav class="sidebar-nav">
                <a class="side-link <?php echo !$selected_subject_id ? 'active' : ''; ?>" 
                   href="secondary_subject.php?form_level=<?php echo $form_level; ?>">
                    <i class="fas fa-home"></i> All Subjects
                </a>
                
                <?php foreach ($subjects as $subject): ?>
                    <?php
                    $is_subject_active = $selected_subject_id == $subject['id'];
                    $subject_topics = array_filter($topics, function($topic) use ($subject) {
                        return $topic['subject_id'] == $subject['id'];
                    });
                    ?>
                    
                    <div class="subject-section">
                        <a class="subject-header <?php echo $is_subject_active ? 'active' : ''; ?>" 
                           href="secondary_subject.php?form_level=<?php echo $form_level; ?>&subject_id=<?php echo $subject['id']; ?>">
                            <span>
                                <i class="fas fa-book"></i> <?php echo htmlspecialchars($subject['subject_name']); ?>
                            </span>
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        
                        <?php if ($is_subject_active && !empty($subject_topics)): ?>
                            <div class="topics-list">
                                <?php foreach ($subject_topics as $topic): ?>
                                    <a class="side-link topic-link <?php echo $selected_topic_id == $topic['id'] ? 'active' : ''; ?>" 
                                       href="secondary_subject.php?form_level=<?php echo $form_level; ?>&subject_id=<?php echo $subject['id']; ?>&topic_id=<?php echo $topic['id']; ?>">
                                        <i class="fas fa-folder"></i> <?php echo htmlspecialchars($topic['topic_name']); ?>
                                        <?php if (isset($topic['question_count']) && $topic['question_count'] > 0): ?>
                                            <span style="margin-inline-start: auto; font-size: 12px; background: rgba(52, 152, 219, 0.3); padding: 2px 6px; border-radius: 10px;">
                                                <?php echo $topic['question_count']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="secondary_subject.php?form_level=<?php echo $form_level; ?>">
                    <?php 
                    $breadcrumb_form_name = 'Form ' . $form_level;
                    foreach ($available_forms as $form) {
                        if ($form['form_number'] == $form_level) {
                            $breadcrumb_form_name = $form['form_name'];
                            break;
                        }
                    }
                    echo htmlspecialchars($breadcrumb_form_name);
                    ?>
                </a>
                <?php if ($selected_subject_id && $current_subject): ?>
                    &raquo; <a href="secondary_subject.php?form_level=<?php echo $form_level; ?>&subject_id=<?php echo $selected_subject_id; ?>"><?php echo htmlspecialchars($current_subject['subject_name']); ?></a>
                    <?php if ($selected_topic_id && $current_topic): ?>
                        &raquo; <span><?php echo htmlspecialchars($current_topic['topic_name']); ?></span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <h1 class="page-title">
                <i class="fas fa-graduation-cap"></i> 
                <?php 
                $page_title_form_name = 'Form ' . $form_level . ' Subjects';
                foreach ($available_forms as $form) {
                    if ($form['form_number'] == $form_level) {
                        $page_title_form_name = $form['form_name'] . ' Subjects';
                        break;
                    }
                }
                echo htmlspecialchars($page_title_form_name);
                ?>
                <?php if ($selected_subject_id && $current_subject): ?>
                    - <?php echo htmlspecialchars($current_subject['subject_name']); ?>
                    <?php if ($selected_topic_id && $current_topic): ?>
                        : <?php echo htmlspecialchars($current_topic['topic_name']); ?>
                    <?php endif; ?>
                <?php endif; ?>
            </h1>

            <div class="content-area" <?php if ($selected_subject_id && $current_subject): ?><?php if (strtolower($current_subject['subject_name']) === 'biology'): ?>style="background-image: url('../img/karya AQJ Chanel.jpeg'); background-size: cover; background-position: center;"<?php elseif (strtolower($current_subject['subject_name']) === 'chemistry'): ?>style="background-image: url('../img/chemistry _33.jpeg'); background-size: cover; background-position: center;"<?php elseif (strtolower($current_subject['subject_name']) === 'english'): ?>style="background-image: url('../img/✔️ Portadas de Inglés 2025_ Descarga las Mejores Aquí!!!.jpeg'); background-size: cover; background-position: center;"<?php endif; ?><?php endif; ?>>
                <?php if (isset($error)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
                    <div class="error-message" style="background: #d1ecf1; color: #0c5460; border-left-color: #17a2b8;">
                        <strong>Debug Info:</strong><br>
                        Show Results: <?php echo isset($show_results) ? ($show_results ? 'true' : 'false') : 'not set'; ?><br>
                        Score: <?php echo $score; ?><br>
                        Max Score: <?php echo $max_possible_score; ?><br>
                        Percentage: <?php echo $percentage; ?>%<br>
                        Total Questions: <?php echo $total_questions; ?><br>
                        Questions Count: <?php echo count($questions); ?><br>
                        Results Count: <?php echo count($results); ?><br>
                        Session Data: <?php echo isset($_SESSION['secondary_quiz_results']) ? 'exists' : 'not set'; ?><br>
                        Topic ID: <?php echo $selected_topic_id; ?><br>
                    </div>
                <?php endif; ?>

                <?php if (isset($show_results) && $show_results): ?>
                    <!-- Enhanced Results Display -->
                    <div class="results-container">
                        <a id="results"></a>
                        <div class="score-display">
                            <div class="score-circle <?php echo $percentage >= 70 ? 'pass' : 'fail'; ?>">
                                <?php echo $score; ?>/<?php echo $max_possible_score; ?>
                            </div>
                            <h2>Your Score: <?php echo $percentage; ?>%</h2>
                            <p class="<?php echo $percentage >= 70 ? 'pass-text' : 'fail-text'; ?>">
                                <strong>
                                    <?php echo $percentage >= 70 ? 'PASS' : 'FAIL'; ?>
                                </strong>
                                - You earned <?php echo $score; ?> out of <?php echo $max_possible_score; ?> possible points
                            </p>
                            
                            <div class="performance-summary">
                                <div class="performance-item">
                                    <span class="performance-label">Correct Answers:</span>
                                    <span class="performance-value">
                                        <?php echo $correct_count . '/' . $total_questions; ?>
                                    </span>
                                </div>
                                <div class="performance-item">
                                    <span class="performance-label">Percentage:</span>
                                    <span class="performance-value"><?php echo $percentage; ?>%</span>
                                </div>
                                <div class="performance-item">
                                    <span class="performance-label">Status:</span>
                                    <span class="performance-value <?php echo $percentage >= 70 ? 'pass-text' : 'fail-text'; ?>">
                                        <?php echo $percentage >= 70 ? 'PASSED' : 'NEEDS IMPROVEMENT'; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="progress-container">
                                <div class="progress-bar" style="inline-size: <?php echo $percentage; ?>%">
                                    <?php echo $percentage; ?>%
                                </div>
                            </div>
                        </div>

                        <h3>Detailed Results:</h3>
                        <?php foreach ($questions as $index => $question): 
                            $question_id = $question['id'];
                            $is_correct = $results[$question_id]['correct'] ?? false;
                        ?>
                            <div class="result-item <?php echo $is_correct ? '' : 'incorrect'; ?>">
                                <div class="result-status <?php echo $is_correct ? 'correct' : 'incorrect'; ?>">
                                    <i class="fas fa-<?php echo $is_correct ? 'check' : 'times'; ?>"></i>
                                    Question <?php echo $index + 1; ?>: 
                                    <?php echo $is_correct ? 'Correct' : 'Incorrect'; ?>
                                    (<?php echo $results[$question_id]['points']; ?> points)
                                </div>
                                <p><strong>Question:</strong> <?php echo htmlspecialchars($question['question_text']); ?></p>
                                
                                <div class="user-answer">
                                    <strong>Your Answer:</strong> <?php echo htmlspecialchars($results[$question_id]['user_answer_text']); ?>
                                </div>
                                
                                <?php if (!empty($question['correct_answers'])): ?>
                                    <div class="correct-answer">
                                        <strong>Correct Answer:</strong> 
                                        <?php 
                                        $correct_answers = array_map(function($answer) {
                                            return $answer['answer_text'];
                                        }, $question['correct_answers']);
                                        echo htmlspecialchars(implode(' OR ', $correct_answers));
                                        ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($is_correct): ?>
                                    <div class="points-earned">
                                        <i class="fas fa-star"></i> Earned <?php echo $results[$question_id]['points']; ?> points
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <div class="action-buttons">
                            <a href="secondary_subject.php?form_level=<?php echo $form_level; ?>&subject_id=<?php echo $selected_subject_id; ?>&topic_id=<?php echo $selected_topic_id; ?>" class="btn btn-primary">
                                <i class="fas fa-redo"></i> Try Again
                            </a>
                            <a href="secondary_subject.php?form_level=<?php echo $form_level; ?>&subject_id=<?php echo $selected_subject_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-book"></i> Back to Topics
                            </a>
                            <a href="progress.php" class="btn btn-success">
                                <i class="fas fa-chart-line"></i> View Progress
                            </a>
                        </div>
                    </div>
                <?php elseif ($selected_topic_id && !empty($questions)): ?>
                    <!-- Questions Form -->
                    <form method="POST" id="questionsForm" onsubmit="return validateForm()">
                        <input type="hidden" name="form_level" value="<?php echo htmlspecialchars($form_level); ?>">
                        <input type="hidden" name="subject_id" value="<?php echo htmlspecialchars($selected_subject_id); ?>">
                        <input type="hidden" name="topic_id" value="<?php echo htmlspecialchars($selected_topic_id); ?>">
                        <div class="questions-container">
                            <div class="question-count">
                                <i class="fas fa-list-ol"></i> 
                                <?php echo count($questions); ?> question(s) found in <?php echo htmlspecialchars($current_topic['topic_name']); ?>
                                <br>
                                <small>Total possible points: <?php echo array_sum(array_column($questions, 'points')); ?></small>
                            </div>

                            <?php foreach ($questions as $index => $question): ?>
                                <div class="question-item">
                                    <div class="question-header">
                                        <div class="question-text">
                                            Q<?php echo $index + 1; ?>: <?php echo htmlspecialchars($question['question_text']); ?>
                                        </div>
                                        <div class="question-meta">
                                            <span class="meta-badge">
                                                <i class="fas fa-star"></i> <?php echo $question['points']; ?> pts
                                            </span>
                                            <span class="meta-badge">
                                                <i class="fas fa-signal"></i> <?php echo ucfirst($question['difficulty_level']); ?>
                                            </span>
                                            <span class="meta-badge">
                                                <i class="fas fa-question-circle"></i> <?php echo ucfirst(str_replace('_', ' ', $question['question_type'])); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="options-container">
                                        <?php if ($question['question_type'] === 'multiple_choice' && !empty($question['options'])): ?>
                                            <?php foreach ($question['options'] as $option): ?>
                                                <div class="option-item">
                                                    <label class="option-label">
                                                        <input type="radio" 
                                                               name="answer_<?php echo $question['id']; ?>" 
                                                               value="<?php echo $option['id']; ?>"
                                                               class="option-input">
                                                        <span><?php echo htmlspecialchars($option['option_text']); ?></span>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>

                                        <?php elseif ($question['question_type'] === 'one_word'): ?>
                                            <input type="text"
                                                   name="answer_<?php echo $question['id']; ?>"
                                                   class="answer-input"
                                                   placeholder="Type your answer here..."
                                                   autocomplete="off">

                                        <?php elseif ($question['question_type'] === 'true_false'): ?>
                                            <div class="true-false-options">
                                                <label class="true-false-btn" onclick="selectTrueFalse(this, <?php echo $question['id']; ?>)">
                                                    <input type="radio" 
                                                           name="answer_<?php echo $question['id']; ?>" 
                                                           value="True" 
                                                           style="display: none;">
                                                    <i class="fas fa-check-circle"></i> True
                                                </label>
                                                <label class="true-false-btn" onclick="selectTrueFalse(this, <?php echo $question['id']; ?>)">
                                                    <input type="radio" 
                                                           name="answer_<?php echo $question['id']; ?>" 
                                                           value="False" 
                                                           style="display: none;">
                                                    <i class="fas fa-times-circle"></i> False
                                                </label>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div class="submit-section">
                                <button type="submit" name="submit_answers" class="submit-btn">
                                    <i class="fas fa-paper-plane"></i> Submit Answers
                                </button>
                                <a href="secondary_subject.php?form_level=<?php echo $form_level; ?>&subject_id=<?php echo $selected_subject_id; ?>&topic_id=<?php echo $selected_topic_id; ?>&show_results=1#results" class="btn btn-secondary" style="margin-inline-start: 10px;">
                                    <i class="fas fa-list-check"></i> View Scores
                                </a>
                                <p style="margin-block-start: 10px; color: #7f8c8d; font-size: 14px;">
                                    <i class="fas fa-info-circle"></i> You cannot change your answers after submission
                                </p>
                            </div>
                        </div>
                    </form>
                <?php elseif ($selected_topic_id && empty($questions) && $current_topic): ?>
                    <!-- No questions message for selected topic -->
                    <div class="welcome-message">
                        <i class="fas fa-circle-info welcome-icon"></i>
                        <h3><?php echo htmlspecialchars($current_topic['topic_name']); ?></h3>
                        <p>No questions have been added for this topic yet. Please check back later.</p>
                        <div class="action-buttons" style="margin-top: 20px;">
                            <a href="secondary_subject.php?form_level=<?php echo $form_level; ?>&subject_id=<?php echo $selected_subject_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-book"></i> Back to Topics
                            </a>
                        </div>
                    </div>
                <?php elseif ($selected_subject_id && !empty($topics)): ?>
                    <!-- Topics Grid View -->
                    <div class="welcome-message">
                        <i class="fas fa-folder-open welcome-icon"></i>
                        <h3>Select a Topic</h3>
                        <p style="font-size: 18px; font-weight: bold; background: rgba(255,255,255,0.8); padding: 10px; border-radius: 8px; display: inline-block;">Choose a topic from <?php echo htmlspecialchars($current_subject['subject_name']); ?> to start answering questions.</p>
                    </div>
                    
                    <div class="topic-grid">
                        <?php foreach ($topics as $topic): ?>
                            <?php if ($topic['subject_id'] == $selected_subject_id): ?>
                                <a href="secondary_subject.php?form_level=<?php echo $form_level; ?>&subject_id=<?php echo $selected_subject_id; ?>&topic_id=<?php echo $topic['id']; ?>" class="topic-card">
                                    <i class="fas fa-folder"></i>
                                    <h3><?php echo htmlspecialchars($topic['topic_name']); ?></h3>
                                    <?php if (isset($topic['question_count']) && (int)$topic['question_count'] > 0): ?>
                                        <p><?php echo (int)$topic['question_count']; ?> question(s) available</p>
                                    <?php else: ?>
                                        <p>No questions yet</p>
                                    <?php endif; ?>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <!-- Subjects Grid View -->
                    <div class="welcome-message">
                        <i class="fas fa-book welcome-icon"></i>
                        <h3>Welcome to 
                            <?php 
                            $welcome_form_name = 'Form ' . $form_level;
                            foreach ($available_forms as $form) {
                                if ($form['form_number'] == $form_level) {
                                    $welcome_form_name = $form['form_name'];
                                    break;
                                }
                            }
                            echo htmlspecialchars($welcome_form_name);
                            ?> Subjects</h3>
                        <p>Select a subject from the sidebar or choose one below to start answering questions. Each subject contains various topics with practice questions.</p>
                    </div>
                    
                    <div class="subject-grid">
                        <?php foreach ($subjects as $subject): ?>
                            <a href="secondary_subject.php?form_level=<?php echo $form_level; ?>&subject_id=<?php echo $subject['id']; ?>" class="subject-card">
                                <i class="fas fa-book"></i>
                                <h3><?php echo htmlspecialchars($subject['subject_name']); ?></h3>
                                <p>Click to view topics</p>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Auto-scroll to active elements
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($selected_subject_id): ?>
                // Scroll to the active subject
                const activeSubject = document.querySelector('.subject-header.active');
                if (activeSubject) {
                    activeSubject.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            <?php endif; ?>
            
            <?php if ($selected_topic_id): ?>
                // Scroll to the active topic
                const activeTopic = document.querySelector('.topic-link.active');
                if (activeTopic) {
                    activeTopic.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            <?php endif; ?>
            
            <?php if (isset($show_results) && $show_results): ?>
                // Animate progress bar on results page
                const progressBar = document.querySelector('.progress-bar');
                if (progressBar) {
                    // Reset width to 0 for animation
                    progressBar.style.width = '0%';
                    setTimeout(() => {
                        progressBar.style.width = '<?php echo $percentage; ?>%';
                    }, 300);
                }
            <?php endif; ?>
        });

        function validateForm() {
            const questions = document.querySelectorAll('.question-item');
            for (let question of questions) {
                const metaBadges = question.querySelectorAll('.question-meta .meta-badge');
                const typeBadge = metaBadges[metaBadges.length - 1]; // Last badge is the type
                const questionType = typeBadge.textContent.trim().toLowerCase().replace(' ', '_');

                if (questionType === 'multiple_choice') {
                    const radios = question.querySelectorAll('input[type="radio"]');
                    let answered = false;
                    for (let radio of radios) {
                        if (radio.checked) {
                            answered = true;
                            break;
                        }
                    }
                    if (!answered) {
                        alert('Please answer all questions before submitting.');
                        return false;
                    }
                } else if (questionType === 'one_word') {
                    const input = question.querySelector('input[type="text"]');
                    if (!input.value.trim()) {
                        alert('Please answer all questions before submitting.');
                        return false;
                    }
                } else if (questionType === 'true_false') {
                    const radios = question.querySelectorAll('input[type="radio"]');
                    let answered = false;
                    for (let radio of radios) {
                        if (radio.checked) {
                            answered = true;
                            break;
                        }
                    }
                    if (!answered) {
                        alert('Please answer all questions before submitting.');
                        return false;
                    }
                }
            }
            return true;
        }

        function selectTrueFalse(button, questionId) {
            // Remove selected class from all buttons in this question
            const buttons = button.parentElement.querySelectorAll('.true-false-btn');
            buttons.forEach(btn => btn.classList.remove('selected'));
            
            // Add selected class to clicked button
            button.classList.add('selected');
            
            // Check the radio input
            const radioInput = button.querySelector('input[type="radio"]');
            radioInput.checked = true;
        }

        // Submit UX: show loading state
        document.getElementById('questionsForm')?.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Grading Your Answers...';
            }
        });

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // Auto-scroll to results if present after load
        document.addEventListener('DOMContentLoaded', function() {
            const resultsAnchor = document.getElementById('results');
            if (resultsAnchor) {
                resultsAnchor.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    </script>
</body>
</html>