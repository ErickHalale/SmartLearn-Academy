<?php
include 'includes/config.php';
include 'login_check.php';

requireLogin();

$user = getCurrentUser();

// Calculate overall statistics
try {
    if ($user['education_level'] == 'primary') {
        $sql = "SELECT 
                    COUNT(*) as total_attempts,
                    SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct_answers,
                    COUNT(DISTINCT DATE(attempted_at)) as days_active,
                    COUNT(DISTINCT qa.question_id) as unique_questions
                FROM primary_question_attempts qa
                WHERE qa.user_id = :user_id";
    } else {
        $sql = "SELECT 
                    COUNT(*) as total_attempts,
                    SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct_answers,
                    COUNT(DISTINCT DATE(attempted_at)) as days_active,
                    COUNT(DISTINCT qa.question_id) as unique_questions
                FROM secondary_question_attempts qa
                WHERE qa.user_id = :user_id";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $user['id']);
    $stmt->execute();
    $stats = $stmt->fetch();
    
    // Calculate overall percentage
    $overall_percentage = $stats['total_attempts'] > 0 
        ? round(($stats['correct_answers'] / $stats['total_attempts']) * 100, 1)
        : 0;
    
    // Determine achievement level
    $achievement_level = '';
    $achievement_color = '';
    $achievement_icon = '';
    
    if ($overall_percentage >= 90) {
        $achievement_level = 'Gold';
        $achievement_color = '#f39c12';
        $achievement_icon = 'fas fa-medal';
    } elseif ($overall_percentage >= 75) {
        $achievement_level = 'Silver';
        $achievement_color = '#95a5a6';
        $achievement_icon = 'fas fa-medal';
    } elseif ($overall_percentage >= 60) {
        $achievement_level = 'Bronze';
        $achievement_color = '#cd7f32';
        $achievement_icon = 'fas fa-medal';
    } else {
        $achievement_level = 'Participant';
        $achievement_color = '#7f8c8d';
        $achievement_icon = 'fas fa-certificate';
    }
    
    // Get recent achievements (last 5 quiz sessions)
    if ($user['education_level'] == 'primary') {
        $recent_sql = "SELECT 
                        DATE(qa.attempted_at) as quiz_date,
                        COUNT(*) as total_questions,
                        SUM(CASE WHEN qa.is_correct = 1 THEN 1 ELSE 0 END) as correct_answers,
                        s.subject_name
                    FROM primary_question_attempts qa
                    JOIN primary_questions q ON qa.question_id = q.id
                    JOIN primary_topics t ON q.topic_id = t.id
                    JOIN primary_subjects s ON t.subject_id = s.id
                    WHERE qa.user_id = :user_id
                    GROUP BY DATE(qa.attempted_at), s.subject_name
                    ORDER BY quiz_date DESC
                    LIMIT 5";
    } else {
        $recent_sql = "SELECT 
                        DATE(qa.attempted_at) as quiz_date,
                        COUNT(*) as total_questions,
                        SUM(CASE WHEN qa.is_correct = 1 THEN 1 ELSE 0 END) as correct_answers,
                        s.subject_name
                    FROM secondary_question_attempts qa
                    JOIN secondary_questions q ON qa.question_id = q.id
                    JOIN secondary_topics t ON q.topic_id = t.id
                    JOIN secondary_subjects s ON t.subject_id = s.id
                    WHERE qa.user_id = :user_id
                    GROUP BY DATE(qa.attempted_at), s.subject_name
                    ORDER BY quiz_date DESC
                    LIMIT 5";
    }
    
    $stmt = $pdo->prepare($recent_sql);
    $stmt->bindParam(':user_id', $user['id']);
    $stmt->execute();
    $recent_achievements = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $stats = ['total_attempts' => 0, 'correct_answers' => 0, 'days_active' => 0, 'unique_questions' => 0];
    $overall_percentage = 0;
    $achievement_level = 'Participant';
    $achievement_color = '#7f8c8d';
    $achievement_icon = 'fas fa-certificate';
    $recent_achievements = [];
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Achievement - SmartLearn</title>
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
        
        .achievement-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .achievement-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .achievement-title {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .achievement-subtitle {
            color: #7f8c8d;
            font-size: 16px;
        }
        
        .overall-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .achievement-badge {
            text-align: center;
            padding: 40px;
            background: linear-gradient(135deg, <?php echo $achievement_color; ?>, <?php echo $achievement_color; ?>dd);
            color: white;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        }
        
        .badge-icon {
            font-size: 64px;
            margin-bottom: 15px;
        }
        
        .badge-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .badge-percentage {
            font-size: 18px;
            opacity: 0.9;
        }
        
        .recent-achievements {
            margin-top: 30px;
        }
        
        .section-title {
            font-size: 20px;
            color: #2c3e50;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .achievement-item {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 4px solid #667eea;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .achievement-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .achievement-info h4 {
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .achievement-meta {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .achievement-score {
            text-align: right;
        }
        
        .score-value {
            font-size: 18px;
            font-weight: bold;
            color: #27ae60;
        }
        
        .score-label {
            color: #7f8c8d;
            font-size: 12px;
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
            
            .achievement-card {
                padding: 20px;
            }
            
            .overall-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .achievement-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .achievement-score {
                text-align: left;
            }
            
            .header-nav {
                flex-direction: column;
                gap: 10px;
            }
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
            <a class="header-link" href="progress.php">
                <i class="fas fa-chart-line"></i> Progress
            </a>
            <a class="header-link" href="logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>
    </div>

    <div class="main-container">
        <div class="achievement-card">
            <div class="achievement-header">
                <h1 class="achievement-title">Your Achievements</h1>
                <p class="achievement-subtitle">Track your learning milestones and accomplishments</p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="overall-stats">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_attempts']; ?></div>
                    <div class="stat-label">Total Questions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['correct_answers']; ?></div>
                    <div class="stat-label">Correct Answers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['days_active']; ?></div>
                    <div class="stat-label">Active Days</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['unique_questions']; ?></div>
                    <div class="stat-label">Unique Questions</div>
                </div>
            </div>
            
            <div class="achievement-badge">
                <div class="badge-icon">
                    <i class="<?php echo $achievement_icon; ?>"></i>
                </div>
                <div class="badge-title"><?php echo $achievement_level; ?> Level</div>
                <div class="badge-percentage">Overall Score: <?php echo $overall_percentage; ?>%</div>
            </div>
            
            <div class="recent-achievements">
                <h2 class="section-title">Recent Quiz Results</h2>
                
                <?php if (empty($recent_achievements)): ?>
                    <div class="no-data">
                        <i class="fas fa-trophy" style="font-size: 48px; margin-bottom: 15px; color: #bdc3c7;"></i>
                        <p>No recent quiz results found. Start taking quizzes to earn achievements!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_achievements as $achievement): ?>
                        <?php 
                        $score = $achievement['total_questions'] > 0 
                            ? round(($achievement['correct_answers'] / $achievement['total_questions']) * 100, 1)
                            : 0;
                        ?>
                        <div class="achievement-item">
                            <div class="achievement-info">
                                <h4><?php echo htmlspecialchars($achievement['subject_name']); ?></h4>
                                <div class="achievement-meta">
                                    <?php echo date('M j, Y', strtotime($achievement['quiz_date'])); ?> • 
                                    <?php echo $achievement['total_questions']; ?> questions
                                </div>
                            </div>
                            <div class="achievement-score">
                                <div class="score-value"><?php echo $score; ?>%</div>
                                <div class="score-label">Score</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <a href="pupil.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</body>
</html>

