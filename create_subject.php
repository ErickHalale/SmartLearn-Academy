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

    $pdo->exec("CREATE TABLE IF NOT EXISTS primary_questions (
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

    $pdo->exec("CREATE TABLE IF NOT EXISTS primary_question_options (
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_subject'])) {
    try {
        $pdo->beginTransaction();
        
        // Get form data
        $grade_id = (int)$_POST['grade_id'];
        $subject_name = trim($_POST['subject_name']);
        $subject_description = trim($_POST['subject_description']);
        $topics = $_POST['topics'] ?? [];
        
        if (empty($grade_id) || empty($subject_name) || empty($topics)) {
            throw new Exception('Grade, subject name, and at least one topic are required.');
        }
        
        // Check if subject already exists for this grade
        $check_stmt = $pdo->prepare("SELECT id FROM primary_subjects WHERE subject_name = ? AND grade_id = ?");
        $check_stmt->execute([$subject_name, $grade_id]);
        
        if ($check_stmt->rowCount() > 0) {
            throw new Exception('Subject already exists for this grade.');
        }
        
        // Insert subject
        $subject_stmt = $pdo->prepare("INSERT INTO primary_subjects (subject_name, grade_id, description) VALUES (?, ?, ?)");
        $subject_stmt->execute([$subject_name, $grade_id, $subject_description]);
        $subject_id = $pdo->lastInsertId();
        
        // Insert topics only (questions are added via Quick Add section below)
        foreach ($topics as $topic_index => $topic_data) {
            if (empty(trim($topic_data['name']))) continue;
            
            // Insert topic
            $topic_stmt = $pdo->prepare("INSERT INTO primary_topics (topic_name, subject_id, description, order_index) VALUES (?, ?, ?, ?)");
            $topic_stmt->execute([
                trim($topic_data['name']), 
                $subject_id, 
                trim($topic_data['description'] ?? ''), 
                $topic_index
            ]);
        }
        
        $pdo->commit();
        $message = 'Subject and topics created successfully!';
        
        // Clear form data
        $_POST = [];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Handle quick add question to existing topic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_add_question'])) {
    try {
        $topic_id = (int)$_POST['qa_topic_id'];
        $question_text = trim($_POST['qa_question_text']);
        $question_type = $_POST['qa_question_type'] ?? '';
        $difficulty = $_POST['qa_difficulty_level'] ?? 'medium';
        $points = (int)($_POST['qa_points'] ?? 1);

        if (empty($topic_id) || empty($question_text) || empty($question_type)) {
            throw new Exception('Please select subject and topic, and provide question text and type.');
        }

        $pdo->beginTransaction();

        // Determine next order index within the topic
        $order_stmt = $pdo->prepare("SELECT COALESCE(MAX(order_index), 0) + 1 AS next_order FROM primary_questions WHERE topic_id = ?");
        $order_stmt->execute([$topic_id]);
        $next_order = (int)$order_stmt->fetchColumn();

        // Insert question
        $q_stmt = $pdo->prepare("INSERT INTO primary_questions (topic_id, question_text, question_type, difficulty_level, points, order_index) VALUES (?, ?, ?, ?, ?, ?)");
        $q_stmt->execute([$topic_id, $question_text, $question_type, $difficulty, $points, $next_order]);
        $new_question_id = (int)$pdo->lastInsertId();

        // Insert answers/options per type
        if ($question_type === 'multiple_choice') {
            $options = $_POST['qa_options'] ?? [];
            $correct = isset($_POST['qa_correct_option']) ? (int)$_POST['qa_correct_option'] : -1;
            if ($correct === -1) {
                throw new Exception('Please select the correct option for the multiple choice question.');
            }
            foreach ($options as $idx => $optText) {
                $trimmed = trim($optText);
                if ($trimmed === '') continue;
                $is_correct = ($idx === $correct) ? 1 : 0;
                $opt_stmt = $pdo->prepare("INSERT INTO primary_question_options (question_id, option_text, is_correct, order_index) VALUES (?, ?, ?, ?)");
                $opt_stmt->execute([$new_question_id, $trimmed, $is_correct, $idx]);
            }
        } elseif ($question_type === 'one_word') {
            $answer_text = trim($_POST['qa_answer_text'] ?? '');
            $is_case_sensitive = isset($_POST['qa_case_sensitive']) ? 1 : 0;
            if ($answer_text !== '') {
                $a_stmt = $pdo->prepare("INSERT INTO primary_question_answers (question_id, answer_text, is_case_sensitive) VALUES (?, ?, ?)");
                $a_stmt->execute([$new_question_id, $answer_text, $is_case_sensitive]);
            }
        } elseif ($question_type === 'true_false') {
            $tf_answer = $_POST['qa_true_false_answer'] ?? 'True';
            $a_stmt = $pdo->prepare("INSERT INTO primary_question_answers (question_id, answer_text, is_case_sensitive) VALUES (?, ?, 0)");
            $a_stmt->execute([$new_question_id, $tf_answer]);
        }

        $pdo->commit();
        $message = 'Question added successfully to selected topic!';
    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $error = $e->getMessage();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Fetch grades and existing subjects/topics
try {
    $grades_stmt = $pdo->prepare("SELECT * FROM primary_grades ORDER BY grade_number ASC");
    $grades_stmt->execute();
    $grades = $grades_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Subjects with grade for quick add section
    $subjects_stmt = $pdo->prepare("SELECT s.id, s.subject_name, s.grade_id, g.grade_name FROM primary_subjects s JOIN primary_grades g ON s.grade_id = g.id ORDER BY g.grade_number, s.subject_name");
    $subjects_stmt->execute();
    $qa_subjects = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Topics per subject
    $topics_stmt = $pdo->prepare("SELECT id, topic_name, subject_id FROM primary_topics ORDER BY subject_id, order_index, topic_name");
    $topics_stmt->execute();
    $qa_topics = $topics_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
    $grades = [];
    $qa_subjects = [];
    $qa_topics = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Subject - SmartLearn Admin</title>
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

        .back-link {
            background-color: #6c757d;
            color: white !important;
        }

        .back-link:hover {
            background-color: #5a6268 !important;
        }

        .container {
            max-width: 1000px;
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

        .form-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 4px solid #667eea;
        }

        .form-section h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 20px;
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
            min-height: 80px;
        }

        .form-row {
            display: flex;
            gap: 20px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .topic-container {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            background: white;
        }

        .topic-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }

        .topic-header h4 {
            color: #333;
            font-size: 18px;
        }

        .question-container {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background: #f8f9fa;
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .question-header h5 {
            color: #495057;
            font-size: 16px;
        }

        .question-options {
            margin-top: 15px;
            padding: 15px;
            background: white;
            border-radius: 5px;
            border: 1px solid #e9ecef;
        }

        .option-group {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .option-group input[type="radio"] {
            width: auto;
            margin-right: 10px;
        }

        .option-group input[type="text"] {
            flex: 1;
            margin-left: 10px;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: transform 0.3s;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-small {
            padding: 8px 15px;
            font-size: 14px;
        }

        .btn-secondary {
            background: #6c757d;
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

        .add-buttons {
            text-align: center;
            margin: 20px 0;
        }

        .submit-section {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e9ecef;
        }

        .hidden {
            display: none;
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }

            .form-row {
                flex-direction: column;
            }

            .option-group {
                flex-direction: column;
                align-items: flex-start;
            }

            .option-group input[type="text"] {
                margin-left: 0;
                margin-top: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-section">
            <h1>SmartLearn Admin - Create Subject</h1>
        </div>
        <div class="nav-links">
            <a href="admin.php" class="back-link">Back to Dashboard</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <h2>Create Complete Subject</h2>
        
        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" id="subjectForm">
            <!-- Subject Information -->
            <div class="form-section">
                <h3>📚 Subject Information</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="grade_id">Select Grade:</label>
                        <select id="grade_id" name="grade_id" required>
                            <option value="">Choose Grade</option>
                            <?php foreach ($grades as $grade): ?>
                                <option value="<?php echo $grade['id']; ?>">
                                    <?php echo htmlspecialchars($grade['grade_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="subject_name">Subject Name:</label>
                        <input type="text" id="subject_name" name="subject_name" 
                               placeholder="e.g., Mathematics, English, Science" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="subject_description">Subject Description (Optional):</label>
                    <textarea id="subject_description" name="subject_description" 
                              placeholder="Brief description of the subject"></textarea>
                </div>
            </div>

            <!-- Topics Only -->
            <div class="form-section">
                <h3>📝 Topics</h3>
                
                <div id="topicsContainer">
                    <!-- Topics will be added here dynamically -->
                </div>

                <div class="add-buttons">
                    <button type="button" class="btn btn-success btn-small" onclick="addTopic()">
                        + Add Topic
                    </button>
                </div>
            </div>

            <div class="submit-section">
                <button type="submit" name="create_subject" class="btn" style="padding: 15px 40px; font-size: 18px;">
                    🚀 Create Complete Subject
                </button>
            </div>
        </form>

        <!-- Quick Add: Select Subject & Topic then Add Question -->
        <div class="form-section" style="margin-top: 30px;">
            <h3>⚡ Quick Add Question to Existing Topic</h3>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="qa_subject_id">Select Subject:</label>
                        <select id="qa_subject_id" name="qa_subject_id">
                            <option value="">Choose Subject</option>
                            <?php foreach ($qa_subjects as $s): ?>
                                <option value="<?php echo $s['id']; ?>">
                                    <?php echo htmlspecialchars($s['grade_name'] . ' - ' . $s['subject_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="qa_topic_id">Select Topic:</label>
                        <select id="qa_topic_id" name="qa_topic_id" required>
                            <option value="">Choose Topic</option>
                            <?php foreach ($qa_topics as $t): ?>
                                <option value="<?php echo $t['id']; ?>" data-subject="<?php echo $t['subject_id']; ?>">
                                    <?php echo htmlspecialchars($t['topic_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Question Text:</label>
                    <textarea name="qa_question_text" placeholder="Enter question..." required></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Question Type:</label>
                        <select name="qa_question_type" id="qa_question_type" required>
                            <option value="">Choose Type</option>
                            <option value="multiple_choice">Multiple Choice</option>
                            <option value="one_word">One Word</option>
                            <option value="true_false">True/False</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Difficulty:</label>
                        <select name="qa_difficulty_level">
                            <option value="easy">Easy</option>
                            <option value="medium" selected>Medium</option>
                            <option value="hard">Hard</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Points:</label>
                        <input type="number" name="qa_points" value="1" min="1" max="10">
                    </div>
                </div>

                <div id="qa_options_container" class="question-options hidden"></div>

                <div class="submit-section">
                    <button type="submit" name="quick_add_question" class="btn btn-success">Add Question to Topic</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let topicCount = 0;
        let questionCounts = {};

        function addTopic() {
            topicCount++;
            
            const topicHtml = `
                <div class="topic-container" id="topic_${topicCount}">
                    <div class="topic-header">
                        <h4>Topic ${topicCount}</h4>
                        <button type="button" class="btn btn-danger btn-small" onclick="removeTopic(${topicCount})">
                            Remove Topic
                        </button>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Topic Name:</label>
                            <input type="text" name="topics[${topicCount}][name]" placeholder="e.g., Addition and Subtraction" required>
                        </div>
                        <div class="form-group">
                            <label>Topic Description:</label>
                            <textarea name="topics[${topicCount}][description]" placeholder="Brief description of the topic"></textarea>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('topicsContainer').insertAdjacentHTML('beforeend', topicHtml);
        }

        function removeTopic(topicId) {
            if (confirm('Are you sure you want to remove this topic and all its questions?')) {
                document.getElementById(`topic_${topicId}`).remove();
                delete questionCounts[topicId];
            }
        }


        // Form validation
        document.getElementById('subjectForm').addEventListener('submit', function(e) {
            if (topicCount === 0) {
                alert('Please add at least one topic.');
                e.preventDefault();
                return;
            }
            
            // Additional validation can be added here
        });

        // Add first topic automatically
        window.onload = function() {
            addTopic();
        };

        // Quick Add filtering: filter topics by selected subject
        document.getElementById('qa_subject_id')?.addEventListener('change', function() {
            const subjectId = this.value;
            const topicSelect = document.getElementById('qa_topic_id');
            Array.from(topicSelect.options).forEach(opt => {
                if (!opt.value) return;
                const sub = opt.getAttribute('data-subject');
                opt.style.display = (subjectId === '' || sub === subjectId) ? 'block' : 'none';
            });
            topicSelect.value = '';
        });

        // Quick Add dynamic options block by type
        document.getElementById('qa_question_type')?.addEventListener('change', function() {
            const container = document.getElementById('qa_options_container');
            const type = this.value;
            if (!type) { container.classList.add('hidden'); container.innerHTML = ''; return; }
            container.classList.remove('hidden');
            if (type === 'multiple_choice') {
                container.innerHTML = `
                    <h6>Answer Options</h6>
                    <div class="option-group">
                        <input type="radio" name="qa_correct_option" value="0"> A
                        <input type="text" name="qa_options[0]" placeholder="Option A">
                    </div>
                    <div class="option-group">
                        <input type="radio" name="qa_correct_option" value="1"> B
                        <input type="text" name="qa_options[1]" placeholder="Option B">
                    </div>
                    <div class="option-group">
                        <input type="radio" name="qa_correct_option" value="2"> C
                        <input type="text" name="qa_options[2]" placeholder="Option C">
                    </div>
                    <div class="option-group">
                        <input type="radio" name="qa_correct_option" value="3"> D
                        <input type="text" name="qa_options[3]" placeholder="Option D">
                    </div>
                `;
            } else if (type === 'one_word') {
                container.innerHTML = `
                    <div class="form-group">
                        <label>Correct Answer:</label>
                        <input type="text" name="qa_answer_text" placeholder="Enter correct answer">
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" name="qa_case_sensitive" value="1"> Case sensitive</label>
                    </div>
                `;
            } else if (type === 'true_false') {
                container.innerHTML = `
                    <div class="form-group">
                        <label>Correct Answer:</label>
                        <label style="margin-left: 10px;"><input type="radio" name="qa_true_false_answer" value="True"> True</label>
                        <label style="margin-left: 20px;"><input type="radio" name="qa_true_false_answer" value="False"> False</label>
                    </div>
                `;
            }
        });
    </script>
</body>
</html>

