<?php
include 'includes/config.php';
include 'login_check.php';

requireLogin();

$user = getCurrentUser();

// Get quiz attempts based on user's education level
try {
    if ($user['education_level'] == 'primary') {
        $sql = "SELECT 
                    qa.*,
                    q.question_text,
                    s.subject_name,
                    g.grade_name
                FROM primary_question_attempts qa
                JOIN primary_questions q ON qa.question_id = q.id
                JOIN primary_topics t ON q.topic_id = t.id
                JOIN primary_subjects s ON t.subject_id = s.id
                JOIN primary_grades g ON s.grade_id = g.id
                WHERE qa.user_id = :user_id
                ORDER BY qa.attempted_at DESC";
    } else {
        $sql = "SELECT 
                    qa.*,
                    q.question_text,
                    s.subject_name,
                    f.form_name
                FROM secondary_question_attempts qa
                JOIN secondary_questions q ON qa.question_id = q.id
                JOIN secondary_topics t ON q.topic_id = t.id
                JOIN secondary_subjects s ON t.subject_id = s.id
                JOIN secondary_forms f ON s.form_id = f.id
                WHERE qa.user_id = :user_id
                ORDER BY qa.attempted_at DESC";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $user['id']);
    $stmt->execute();
    $attempts = $stmt->fetchAll();
    
    // Group attempts by quiz session (same timestamp)
    $quiz_sessions = [];
    foreach ($attempts as $attempt) {
        $session_key = $attempt['attempted_at'];
        if (!isset($quiz_sessions[$session_key])) {
            $quiz_sessions[$session_key] = [
                'date' => $attempt['attempted_at'],
                'subject' => $attempt['subject_name'],
                'grade_form' => isset($attempt['grade_name']) ? $attempt['grade_name'] : $attempt['form_name'],
                'attempts' => [],
                'total_questions' => 0,
                'correct_answers' => 0,
                'score' => 0
            ];
        }
        $quiz_sessions[$session_key]['attempts'][] = $attempt;
        $quiz_sessions[$session_key]['total_questions']++;
        if ($attempt['is_correct']) {
            $quiz_sessions[$session_key]['correct_answers']++;
        }
    }
    
    // Calculate scores for each session
    foreach ($quiz_sessions as &$session) {
        $session['score'] = $session['total_questions'] > 0 
            ? round(($session['correct_answers'] / $session['total_questions']) * 100, 1)
            : 0;
    }
    
} catch(PDOException $e) {
    $quiz_sessions = [];
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress - SmartLearn</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
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
        
        .header {
            background-color: #2c3e50;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
            color: white;
        }
        
        .header-title {
            font-size: 24px;
            font-weight: 600;
        }
        
        .header-nav {
            display: flex;
            gap: 20px;
        }
        
        .header-link {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        
        .header-link:hover {
            background-color: rgba(255,255,255,0.1);
        }
        
        .main-container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .progress-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .progress-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .progress-title {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .progress-subtitle {
            color: #7f8c8d;
            font-size: 16px;
        }
        
        .quiz-session {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .quiz-session:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .session-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .session-info h3 {
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .session-meta {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .session-score {
            text-align: right;
        }
        
        .score-value {
            font-size: 24px;
            font-weight: bold;
            color: #27ae60;
        }
        
        .score-label {
            color: #7f8c8d;
            font-size: 12px;
        }
        
        .session-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .detail-item {
            text-align: center;
            padding: 15px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .detail-value {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .detail-label {
            color: #7f8c8d;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .no-data {
            text-align: center;
            color: #7f8c8d;
            padding: 40px;
            font-size: 16px;
        }
        
        .back-btn {
            display: inline-block;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            text-decoration: none;
            padding: 15px 30px;
            border-radius: 25px;
            margin-top: 30px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            font-weight: 600;
        }

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .main-container {
                margin: 20px auto;
                padding: 0 15px;
            }

            .progress-card {
                padding: 20px;
            }

            .session-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .session-score {
                text-align: left;
            }

            .session-details {
                grid-template-columns: repeat(2, 1fr);
            }

            .header-nav {
                flex-direction: column;
                gap: 10px;
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
        .dark-mode .progress-card {
            background: var(--center-bg);
            color: var(--text-color);
        }
        .dark-mode .quiz-session {
            background: rgba(40, 40, 40, 0.95);
            color: var(--text-color);
        }
        .dark-mode .detail-item {
            background: rgba(50, 50, 50, 0.95);
            color: var(--text-color);
        }
        .dark-mode .progress-title {
            color: var(--text-color);
        }
        .dark-mode .progress-subtitle {
            color: var(--text-color);
        }
        .dark-mode .session-info h3 {
            color: var(--text-color);
        }
        .dark-mode .session-meta {
            color: var(--text-color);
        }
        .dark-mode .score-value {
            color: var(--accent);
        }
        .dark-mode .detail-value {
            color: var(--text-color);
        }
        .dark-mode .detail-label {
            color: var(--text-color);
        }
        .dark-mode .no-data {
            color: var(--text-color);
        }
        .dark-mode .error {
            background-color: rgba(220, 53, 69, 0.2);
            color: #f8d7da;
            border-color: rgba(220, 53, 69, 0.3);
        }
    </style>
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
            <a class="header-link" href="pupil.php">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a class="header-link" href="profile.php">
                <i class="fas fa-user"></i> Profile
            </a>
            <a class="header-link" href="achievement.php">
                <i class="fas fa-trophy"></i> Achievement
            </a>
            <button id="theme-toggle" class="btn">🌙</button>
            <a class="header-link" href="logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>
    </div>

    <div class="main-container">
        <div class="progress-card">
            <div class="progress-header">
                <h1 class="progress-title">Your Progress</h1>
                <p class="progress-subtitle">Track your quiz performance and learning journey</p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if (empty($quiz_sessions)): ?>
                <div class="no-data">
                    <i class="fas fa-chart-line" style="font-size: 48px; margin-bottom: 15px; color: #bdc3c7;"></i>
                    <p>No quiz attempts found. Start taking quizzes to see your progress!</p>
                </div>
            <?php else: ?>
                <?php foreach ($quiz_sessions as $session): ?>
                    <div class="quiz-session">
                        <div class="session-header">
                            <div class="session-info">
                                <h3><?php echo htmlspecialchars($session['subject']); ?></h3>
                                <div class="session-meta">
                                    <?php echo htmlspecialchars($session['grade_form']); ?> • 
                                    <?php echo date('M j, Y g:i A', strtotime($session['date'])); ?>
                                </div>
                            </div>
                            <div class="session-score">
                                <div class="score-value"><?php echo $session['score']; ?>%</div>
                                <div class="score-label">Score</div>
                            </div>
                        </div>
                        
                        <div class="session-details">
                            <div class="detail-item">
                                <div class="detail-value"><?php echo $session['total_questions']; ?></div>
                                <div class="detail-label">Questions</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-value"><?php echo $session['correct_answers']; ?></div>
                                <div class="detail-label">Correct</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-value"><?php echo $session['total_questions'] - $session['correct_answers']; ?></div>
                                <div class="detail-label">Incorrect</div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <a href="pupil.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
    <script>
        document.getElementById('theme-toggle').addEventListener('click', function() {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            this.textContent = isDark ? '☀️' : '🌙';
        });
    </script>
</body>
</html>

