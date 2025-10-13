<?php
// Idempotent schema setup/repair for SmartLearn
// Ensures questions.topic_id exists (with indexes + FK) to avoid 42S22 errors

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/config.php';

function tableExists(PDO $pdo, $tableName) {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    $stmt->execute([$tableName]);
    return (bool)$stmt->fetchColumn();
}

function columnExists(PDO $pdo, $tableName, $columnName) {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1");
    $stmt->execute([$tableName, $columnName]);
    return (bool)$stmt->fetchColumn();
}

function indexExists(PDO $pdo, $tableName, $indexName) {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1");
    $stmt->execute([$tableName, $indexName]);
    return (bool)$stmt->fetchColumn();
}

function foreignKeyExists(PDO $pdo, $tableName, $constraintName) {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY' LIMIT 1");
    $stmt->execute([$tableName, $constraintName]);
    return (bool)$stmt->fetchColumn();
}

try {
    // 1) Ensure core parent tables exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS subjects (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        subject_name VARCHAR(100) NOT NULL,
        grade_id INT UNSIGNED NOT NULL,
        description TEXT,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_subjects_grade (grade_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS topics (
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

    // 2) Ensure questions table exists with topic_id
    $pdo->exec("CREATE TABLE IF NOT EXISTS questions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        topic_id INT UNSIGNED NOT NULL,
        question_text TEXT NOT NULL,
        question_type ENUM('multiple_choice','one_word','true_false') NOT NULL,
        difficulty_level ENUM('easy','medium','hard') DEFAULT 'medium',
        points INT UNSIGNED DEFAULT 1,
        order_index INT UNSIGNED DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_questions_topic (topic_id),
        INDEX idx_questions_type (question_type),
        INDEX idx_questions_order (topic_id, order_index)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 3) If questions table exists already, ensure topic_id column and indexes
    if (!columnExists($pdo, 'questions', 'topic_id')) {
        $pdo->exec("ALTER TABLE questions ADD COLUMN topic_id INT UNSIGNED NOT NULL AFTER id");
    }

    if (!indexExists($pdo, 'questions', 'idx_questions_topic')) {
        // Some MySQL setups auto-name indexes; use IF NOT EXISTS via try/catch to be safe
        try { $pdo->exec("CREATE INDEX idx_questions_topic ON questions (topic_id)"); } catch (Exception $e) {}
    }
    if (!indexExists($pdo, 'questions', 'idx_questions_order')) {
        try { $pdo->exec("CREATE INDEX idx_questions_order ON questions (topic_id, order_index)"); } catch (Exception $e) {}
    }

    // 4) Ensure foreign key from questions.topic_id -> topics.id exists
    $fkName = 'fk_questions_topic';
    if (!foreignKeyExists($pdo, 'questions', $fkName)) {
        // Drop any existing FK on topic_id with a different name to avoid duplicates
        try {
            $res = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'questions' AND COLUMN_NAME = 'topic_id' AND REFERENCED_TABLE_NAME IS NOT NULL");
            $existing = $res ? $res->fetchAll(PDO::FETCH_COLUMN) : [];
            foreach ($existing as $cname) {
                if ($cname !== $fkName) {
                    $pdo->exec("ALTER TABLE questions DROP FOREIGN KEY `" . str_replace("`", "``", $cname) . "`");
                }
            }
        } catch (Exception $e) {}

        try {
            $pdo->exec("ALTER TABLE questions ADD CONSTRAINT $fkName FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE");
        } catch (Exception $e) {
            // If topics doesn't exist yet or engine mismatch, ignore – app can still function
        }
    }

    // 5) Ensure auxiliary tables exist (answers/options)
    $pdo->exec("CREATE TABLE IF NOT EXISTS question_options (
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

    $pdo->exec("CREATE TABLE IF NOT EXISTS question_answers (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        question_id INT UNSIGNED NOT NULL,
        answer_text TEXT NOT NULL,
        is_case_sensitive BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_answers_question (question_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $_SESSION['getway_status'] = 'Schema verified and updated successfully';
} catch (PDOException $e) {
    $_SESSION['getway_status'] = 'Schema update error: ' . $e->getMessage();
}

// Optional: simple output when accessed directly in browser
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain');
    echo isset($_SESSION['getway_status']) ? $_SESSION['getway_status'] : 'Done';
}

?>



