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

// Create tables if they don't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS secondary_forms (
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
        UNIQUE KEY uk_subjects_form (subject_name, form_id),
        INDEX idx_subjects_form (form_id)
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
        INDEX idx_topics_subject (subject_id),
        INDEX idx_topics_order (subject_id, order_index)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS secondary_questions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        topic_id INT UNSIGNED NOT NULL,
        question_text TEXT NOT NULL,
        question_type ENUM('multiple_choice', 'one_word', 'true_false') NOT NULL,
        difficulty_level ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
        points INT UNSIGNED DEFAULT 1,
        order_index INT UNSIGNED DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_questions_topic (topic_id),
        INDEX idx_questions_type (question_type),
        INDEX idx_questions_order (topic_id, order_index)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS secondary_question_options (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        question_id INT UNSIGNED NOT NULL,
        option_text TEXT NOT NULL,
        is_correct BOOLEAN DEFAULT FALSE,
        order_index INT UNSIGNED DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_options_question (question_id),
        INDEX idx_options_correct (question_id, is_correct)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS secondary_question_answers (
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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_subject'])) {
        // Add Subject
        $subject_name = trim($_POST['subject_name']);
        $form_id = (int)$_POST['form_id'];
        $description = trim($_POST['description']);
        
        if (empty($subject_name) || empty($form_id)) {
            $error = 'Subject name and form are required.';
        } else {
            try {
                // Check if subject already exists for this form
                $check_stmt = $pdo->prepare("SELECT id FROM secondary_subjects WHERE subject_name = ? AND form_id = ?");
                $check_stmt->execute([$subject_name, $form_id]);
                
                if ($check_stmt->rowCount() > 0) {
                    $error = 'Subject already exists for this form.';
                } else {
                    // Insert new subject
                    $stmt = $pdo->prepare("INSERT INTO secondary_subjects (subject_name, form_id, description) VALUES (?, ?, ?)");
                    $stmt->execute([$subject_name, $form_id, $description]);
                    
                    $message = 'Subject added successfully!';
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['add_topic'])) {
        // Add Topic
        $topic_name = trim($_POST['topic_name']);
        $topic_description = trim($_POST['topic_description']);
        $subject_id = (int)$_POST['subject_id'];
        
        if (empty($topic_name) || empty($subject_id)) {
            $error = 'Topic name and subject are required.';
        } else {
            try {
                // Get next order index
                $order_stmt = $pdo->prepare("SELECT COALESCE(MAX(order_index), 0) + 1 as next_order FROM secondary_topics WHERE subject_id = ?");
                $order_stmt->execute([$subject_id]);
                $next_order = $order_stmt->fetchColumn();
                
                // Insert new topic
                $stmt = $pdo->prepare("INSERT INTO secondary_topics (topic_name, subject_id, description, order_index) VALUES (?, ?, ?, ?)");
                $stmt->execute([$topic_name, $subject_id, $topic_description, $next_order]);
                
                $message = 'Topic added successfully!';
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['add_question'])) {
        // Add Question
        $question_text = trim($_POST['question_text']);
        $question_type = $_POST['question_type'];
        $difficulty = $_POST['difficulty_level'];
        $points = (int)$_POST['points'];
        $topic_id = (int)$_POST['topic_id'];
        
        if (empty($question_text) || empty($question_type) || empty($topic_id)) {
            $error = 'Question text, type, and topic are required.';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Get next order index
                $order_stmt = $pdo->prepare("SELECT COALESCE(MAX(order_index), 0) + 1 as next_order FROM secondary_questions WHERE topic_id = ?");
                $order_stmt->execute([$topic_id]);
                $next_order = $order_stmt->fetchColumn();
                
                // Insert question
                $stmt = $pdo->prepare("INSERT INTO secondary_questions (topic_id, question_text, question_type, difficulty_level, points, order_index) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$topic_id, $question_text, $question_type, $difficulty, $points, $next_order]);
                
                $question_id = $pdo->lastInsertId();
                
                // Handle answers based on question type
                if ($question_type === 'multiple_choice') {
                    $options = $_POST['options'] ?? [];
                    $correct_option = isset($_POST['correct_option']) ? (int)$_POST['correct_option'] : -1;
                    
                    // Validate that a correct option was selected
                    if ($correct_option === -1) {
                        throw new Exception('Please select the correct answer for the multiple choice question.');
                    }
                    
                    foreach ($options as $index => $option_text) {
                        if (!empty(trim($option_text))) {
                            $is_correct = ($index == $correct_option) ? 1 : 0;
                            $option_stmt = $pdo->prepare("INSERT INTO secondary_question_options (question_id, option_text, is_correct, order_index) VALUES (?, ?, ?, ?)");
                            $option_stmt->execute([$question_id, trim($option_text), $is_correct, $index]);
                        }
                    }
                } elseif ($question_type === 'one_word') {
                    $answer_text = trim($_POST['answer_text']);
                    $is_case_sensitive = isset($_POST['case_sensitive']) ? 1 : 0;
                    
                    if (!empty($answer_text)) {
                        $answer_stmt = $pdo->prepare("INSERT INTO secondary_question_answers (question_id, answer_text, is_case_sensitive) VALUES (?, ?, ?)");
                        $answer_stmt->execute([$question_id, $answer_text, $is_case_sensitive]);
                    }
                } elseif ($question_type === 'true_false') {
                    $correct_answer = $_POST['true_false_answer'];
                    $answer_stmt = $pdo->prepare("INSERT INTO secondary_question_answers (question_id, answer_text, is_case_sensitive) VALUES (?, ?, 0)");
                    $answer_stmt->execute([$question_id, $correct_answer]);
                }
                
                $pdo->commit();
                $message = 'Question added successfully!';
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Fetch data
$forms = [];
$subjects = [];
$topics = [];
$questions = [];

try {
    // Fetch forms
    $forms_stmt = $pdo->prepare("SELECT * FROM secondary_forms ORDER BY form_number ASC");
    $forms_stmt->execute();
    $forms = $forms_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch subjects with form information
    $subjects_stmt = $pdo->prepare("
        SELECT s.*, f.form_name, 
               (SELECT COUNT(*) FROM secondary_topics t WHERE t.subject_id = s.id) as topic_count
        FROM secondary_subjects s 
        JOIN secondary_forms f ON s.form_id = f.id 
        ORDER BY f.form_number, s.subject_name
    ");
    $subjects_stmt->execute();
    $subjects = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch topics with subject information
    $topics_stmt = $pdo->prepare("
        SELECT t.*, s.subject_name, f.form_name,
               (SELECT COUNT(*) FROM secondary_questions q WHERE q.topic_id = t.id) as question_count
        FROM secondary_topics t 
        JOIN secondary_subjects s ON t.subject_id = s.id 
        JOIN secondary_forms f ON s.form_id = f.id 
        ORDER BY f.form_number, s.subject_name, t.order_index
    ");
    $topics_stmt->execute();
    $topics = $topics_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch questions with topic and subject information
    $questions_stmt = $pdo->prepare("
        SELECT q.*, t.topic_name, s.subject_name, f.form_name 
        FROM secondary_questions q 
        JOIN secondary_topics t ON q.topic_id = t.id 
        JOIN secondary_subjects s ON t.subject_id = s.id 
        JOIN secondary_forms f ON s.form_id = f.id 
        ORDER BY f.form_number, s.subject_name, t.topic_name, q.order_index
    ");
    $questions_stmt->execute();
    $questions = $questions_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secondary Level Content - SmartLearn Admin</title>
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
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .section {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .full-width {
            grid-column: 1 / -1;
        }

        .section h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 22px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
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

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #555;
            font-weight: 600;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
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
            min-height: 80px;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            transition: transform 0.3s;
            width: 100%;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
            width: auto;
        }

        .btn-secondary {
            background: #6c757d;
            margin-right: 10px;
            width: auto;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .question-type-options {
            display: none;
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }

        .mcq-options {
            margin-top: 10px;
        }

        .option-group {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }

        .option-group input[type="radio"] {
            width: auto;
            margin-right: 8px;
        }

        .option-group input[type="text"] {
            flex: 1;
            margin-left: 8px;
        }

        .data-list {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            max-height: 300px;
            overflow-y: auto;
        }

        .data-item {
            background: white;
            padding: 12px;
            margin-bottom: 8px;
            border-radius: 5px;
            border-left: 4px solid #667eea;
        }

        .data-item:last-child {
            margin-bottom: 0;
        }

        .data-item h4 {
            color: #333;
            margin-bottom: 5px;
            font-size: 16px;
        }

        .data-item p {
            color: #666;
            font-size: 13px;
        }

        .question-item {
            border-left-color: #28a745;
        }

        .topic-item {
            border-left-color: #ffc107;
        }

        .subject-item {
            border-left-color: #17a2b8;
        }

        .meta-info {
            display: flex;
            gap: 10px;
            margin-top: 8px;
            font-size: 12px;
        }

        .meta-info span {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .add-more-btn {
            background: #28a745;
            margin-top: 8px;
        }

        .add-more-btn:hover {
            background: #218838;
        }

        .empty-state {
            text-align: center;
            padding: 30px;
            color: #666;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 10px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .container {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-section">
            <h1>Secondary Level Content Management</h1>
        </div>
        <div class="nav-links">
            <a href="admin.php" class="back-link">Back to Dashboard</a>
            <a href="manage_secondary.php" class="primary-link">Manage Secondary Content</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="message full-width"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error full-width"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Add Subject Section -->
        <div class="section">
            <h2>📚 Add Subject</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="form_id">Select Form:</label>
                    <select id="form_id" name="form_id" required>
                        <option value="">Choose Form</option>
                        <?php foreach ($forms as $form): ?>
                            <option value="<?php echo $form['id']; ?>">
                                <?php echo htmlspecialchars($form['form_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="subject_name">Subject Name:</label>
                    <input type="text" id="subject_name" name="subject_name" 
                           placeholder="e.g., Mathematics, English, Biology, Chemistry" required>
                </div>

                <div class="form-group">
                    <label for="description">Description (Optional):</label>
                    <textarea id="description" name="description" 
                              placeholder="Brief description of the subject"></textarea>
                </div>

                <button type="submit" name="add_subject" class="btn">Add Subject</button>
            </form>
        </div>

        <!-- Add Topic Section -->
        <div class="section">
            <h2>📝 Add Topic</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="subject_id">Select Subject:</label>
                    <select id="subject_id" name="subject_id" required>
                        <option value="">Choose Subject</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['id']; ?>">
                                <?php echo htmlspecialchars($subject['form_name'] . ' - ' . $subject['subject_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="topic_name">Topic Name:</label>
                    <input type="text" id="topic_name" name="topic_name" 
                           placeholder="e.g., Algebra, Cell Biology, Chemical Bonding" required>
                </div>

                <div class="form-group">
                    <label for="topic_description">Topic Description (Optional):</label>
                    <textarea id="topic_description" name="topic_description" 
                              placeholder="Brief description of the topic"></textarea>
                </div>

                <button type="submit" name="add_topic" class="btn">Add Topic</button>
            </form>
        </div>

        <!-- Add Question Section -->
        <div class="section full-width">
            <h2>❓ Add Question</h2>
            <form method="POST" id="questionForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="topic_id">Select Topic:</label>
                        <select id="topic_id" name="topic_id" required>
                            <option value="">Choose Topic</option>
                            <?php foreach ($topics as $topic): ?>
                                <option value="<?php echo $topic['id']; ?>">
                                    <?php echo htmlspecialchars($topic['form_name'] . ' - ' . $topic['subject_name'] . ' - ' . $topic['topic_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="question_type">Question Type:</label>
                        <select id="question_type" name="question_type" required onchange="showQuestionOptions()">
                            <option value="">Choose Type</option>
                            <option value="multiple_choice">Multiple Choice</option>
                            <option value="one_word">One Word Answer</option>
                            <option value="true_false">True/False</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="question_text">Question:</label>
                    <textarea id="question_text" name="question_text" 
                              placeholder="Enter your question here..." required></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="difficulty_level">Difficulty:</label>
                        <select id="difficulty_level" name="difficulty_level">
                            <option value="easy">Easy</option>
                            <option value="medium" selected>Medium</option>
                            <option value="hard">Hard</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="points">Points:</label>
                        <input type="number" id="points" name="points" value="1" min="1" max="10">
                    </div>
                </div>

                <!-- Multiple Choice Options -->
                <div id="mcq_options" class="question-type-options">
                    <h4>Answer Options:</h4>
                    <div class="mcq-options">
                        <div class="option-group">
                            <input type="radio" name="correct_option" value="0" id="correct_0">
                            <label for="correct_0">A.</label>
                            <input type="text" name="options[0]" placeholder="Option A">
                        </div>
                        <div class="option-group">
                            <input type="radio" name="correct_option" value="1" id="correct_1">
                            <label for="correct_1">B.</label>
                            <input type="text" name="options[1]" placeholder="Option B">
                        </div>
                        <div class="option-group">
                            <input type="radio" name="correct_option" value="2" id="correct_2">
                            <label for="correct_2">C.</label>
                            <input type="text" name="options[2]" placeholder="Option C">
                        </div>
                        <div class="option-group">
                            <input type="radio" name="correct_option" value="3" id="correct_3">
                            <label for="correct_3">D.</label>
                            <input type="text" name="options[3]" placeholder="Option D">
                        </div>
                    </div>
                    <p style="color: #666; font-size: 12px; margin-top: 8px;">
                        Select the radio button next to the correct answer.
                    </p>
                </div>

                <!-- One Word Answer -->
                <div id="one_word_options" class="question-type-options">
                    <div class="form-group">
                        <label for="answer_text">Correct Answer:</label>
                        <input type="text" id="answer_text" name="answer_text" placeholder="Enter the correct answer">
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="case_sensitive" value="1">
                            Case sensitive answer
                        </label>
                    </div>
                </div>

                <!-- True/False Options -->
                <div id="true_false_options" class="question-type-options">
                    <div class="form-group">
                        <label>Correct Answer:</label>
                        <div style="margin-top: 8px;">
                            <label style="margin-right: 15px;">
                                <input type="radio" name="true_false_answer" value="True" style="margin-right: 5px;">
                                True
                            </label>
                            <label>
                                <input type="radio" name="true_false_answer" value="False" style="margin-right: 5px;">
                                False
                            </label>
                        </div>
                    </div>
                </div>

                <button type="submit" name="add_question" class="btn">Add Question</button>
                <button type="submit" name="add_question" class="btn add-more-btn">Add Question & Add Another</button>
            </form>
        </div>

        <!-- Data Display Sections -->
        <div class="section">
            <h2>🏫 Existing Subjects</h2>
            <div class="data-list">
                <?php if (!empty($subjects)): ?>
                    <?php foreach ($subjects as $subject): ?>
                        <div class="data-item subject-item">
                            <h4><?php echo htmlspecialchars($subject['subject_name']); ?></h4>
                            <p><?php echo htmlspecialchars($subject['form_name']); ?></p>
                            <?php if ($subject['description']): ?>
                                <p style="margin-top: 5px;"><?php echo htmlspecialchars($subject['description']); ?></p>
                            <?php endif; ?>
                            <div class="meta-info">
                                <span><?php echo $subject['topic_count']; ?> topic(s)</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div>📚</div>
                        <p>No subjects added yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="section">
            <h2>📖 Existing Topics</h2>
            <div class="data-list">
                <?php if (!empty($topics)): ?>
                    <?php foreach ($topics as $topic): ?>
                        <div class="data-item topic-item">
                            <h4><?php echo htmlspecialchars($topic['topic_name']); ?></h4>
                            <p><?php echo htmlspecialchars($topic['form_name'] . ' - ' . $topic['subject_name']); ?></p>
                            <?php if ($topic['description']): ?>
                                <p style="margin-top: 5px;"><?php echo htmlspecialchars($topic['description']); ?></p>
                            <?php endif; ?>
                            <div class="meta-info">
                                <span><?php echo $topic['question_count']; ?> question(s)</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div>📝</div>
                        <p>No topics added yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="section full-width">
            <h2>❓ Existing Questions</h2>
            <div class="data-list">
                <?php if (!empty($questions)): ?>
                    <?php foreach ($questions as $question): ?>
                        <div class="data-item question-item">
                            <h4><?php echo htmlspecialchars($question['question_text']); ?></h4>
                            <p><?php echo htmlspecialchars($question['form_name'] . ' - ' . $question['subject_name'] . ' - ' . $question['topic_name']); ?></p>
                            <div class="meta-info">
                                <span>Type: <?php echo ucfirst(str_replace('_', ' ', $question['question_type'])); ?></span>
                                <span>Difficulty: <?php echo ucfirst($question['difficulty_level']); ?></span>
                                <span>Points: <?php echo $question['points']; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div>❓</div>
                        <p>No questions added yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function showQuestionOptions() {
            const questionType = document.getElementById('question_type').value;
            const allOptions = document.querySelectorAll('.question-type-options');
            
            // Hide all options
            allOptions.forEach(option => {
                option.style.display = 'none';
            });
            
            // Show relevant options
            if (questionType === 'multiple_choice') {
                document.getElementById('mcq_options').style.display = 'block';
            } else if (questionType === 'one_word') {
                document.getElementById('one_word_options').style.display = 'block';
            } else if (questionType === 'true_false') {
                document.getElementById('true_false_options').style.display = 'block';
            }
        }

        // Form validation
        document.getElementById('questionForm')?.addEventListener('submit', function(e) {
            const questionType = document.getElementById('question_type').value;
            
            if (questionType === 'multiple_choice') {
                const options = document.querySelectorAll('input[name^="options"]');
                const correctOption = document.querySelector('input[name="correct_option"]:checked');
                
                let filledOptions = 0;
                options.forEach(option => {
                    if (option.value.trim()) filledOptions++;
                });
                
                if (filledOptions < 2) {
                    alert('Please provide at least 2 options for multiple choice questions.');
                    e.preventDefault();
                    return;
                }
                
                if (!correctOption) {
                    alert('Please select the correct answer.');
                    e.preventDefault();
                    return;
                }
            } else if (questionType === 'one_word') {
                const answer = document.getElementById('answer_text').value.trim();
                if (!answer) {
                    alert('Please provide the correct answer.');
                    e.preventDefault();
                    return;
                }
            } else if (questionType === 'true_false') {
                const answer = document.querySelector('input[name="true_false_answer"]:checked');
                if (!answer) {
                    alert('Please select True or False as the correct answer.');
                    e.preventDefault();
                    return;
                }
            }
        });
    </script>
</body>
</html>