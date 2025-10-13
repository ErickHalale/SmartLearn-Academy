-- Schema for SmartLearn registration
-- Run this file in MySQL to create the database and users table

-- Create database (adjust name if needed)
CREATE DATABASE IF NOT EXISTS smartlearn CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smartlearn;

-- Users table inferred from registration.php
-- Fields used by the insert:
--   first_name, last_name, school_name, education_level, grade, form, password
-- Admins table for admin management
CREATE TABLE IF NOT EXISTS admins (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  username VARCHAR(100) NOT NULL,
  email VARCHAR(191) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_admins_email (email),
  UNIQUE KEY uk_admins_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS primary_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  username VARCHAR(100) NOT NULL,
  school_name VARCHAR(255) NOT NULL,
  education_level ENUM('primary','secondary') NOT NULL,
  grade TINYINT UNSIGNED NULL,
  form TINYINT UNSIGNED NULL,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_users_username (username),
  INDEX idx_users_education_level (education_level),
  INDEX idx_users_grade (grade),
  INDEX idx_users_form (form)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Grades table for primary level
CREATE TABLE IF NOT EXISTS primary_grades (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  grade_name VARCHAR(100) NOT NULL,
  grade_number TINYINT UNSIGNED NOT NULL,
  description TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_grades_name (grade_name),
  UNIQUE KEY uk_grades_number (grade_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Subjects table for primary level
CREATE TABLE IF NOT EXISTS primary_subjects (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  subject_name VARCHAR(100) NOT NULL,
  grade_id INT UNSIGNED NOT NULL,
  description TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (grade_id) REFERENCES primary_grades(id) ON DELETE CASCADE,
  UNIQUE KEY uk_subjects_grade (subject_name, grade_id),
  INDEX idx_subjects_grade (grade_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Topics table for subjects
CREATE TABLE IF NOT EXISTS primary_topics (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  topic_name VARCHAR(150) NOT NULL,
  subject_id INT UNSIGNED NOT NULL,
  description TEXT,
  order_index INT UNSIGNED DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (subject_id) REFERENCES primary_subjects(id) ON DELETE CASCADE,
  INDEX idx_topics_subject (subject_id),
  INDEX idx_topics_order (subject_id, order_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Questions table
CREATE TABLE IF NOT EXISTS primary_questions (
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
  FOREIGN KEY (topic_id) REFERENCES primary_topics(id) ON DELETE CASCADE,
  INDEX idx_questions_topic (topic_id),
  INDEX idx_questions_type (question_type),
  INDEX idx_questions_order (topic_id, order_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Question options table (for multiple choice questions)
CREATE TABLE IF NOT EXISTS primary_question_options (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  question_id INT UNSIGNED NOT NULL,
  option_text TEXT NOT NULL,
  is_correct BOOLEAN DEFAULT FALSE,
  order_index INT UNSIGNED DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (question_id) REFERENCES primary_questions(id) ON DELETE CASCADE,
  INDEX idx_options_question (question_id),
  INDEX idx_options_correct (question_id, is_correct)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Question answers table (for one word and true/false questions)
CREATE TABLE IF NOT EXISTS primary_question_answers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  question_id INT UNSIGNED NOT NULL,
  answer_text TEXT NOT NULL,
  is_case_sensitive BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (question_id) REFERENCES primary_questions(id) ON DELETE CASCADE,
  INDEX idx_answers_question (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Attempts table for recording primary-level quiz submissions
CREATE TABLE IF NOT EXISTS primary_question_attempts (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Forms table for secondary level
CREATE TABLE IF NOT EXISTS secondary_forms (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  form_name VARCHAR(100) NOT NULL,
  form_number TINYINT UNSIGNED NOT NULL,
  description TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_forms_name (form_name),
  UNIQUE KEY uk_forms_number (form_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS secondary_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  username VARCHAR(100) NOT NULL,
  school_name VARCHAR(255) NOT NULL,
  education_level ENUM('primary','secondary') NOT NULL,
  grade TINYINT UNSIGNED NULL,
  form TINYINT UNSIGNED NULL,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_users_username (username),
  INDEX idx_users_education_level (education_level),
  INDEX idx_users_grade (grade),
  INDEX idx_users_form (form)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Subjects table for secondary level
CREATE TABLE IF NOT EXISTS secondary_subjects (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  subject_name VARCHAR(100) NOT NULL,
  form_id INT UNSIGNED NOT NULL,
  description TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (form_id) REFERENCES secondary_forms(id) ON DELETE CASCADE,
  UNIQUE KEY uk_subjects_form (subject_name, form_id),
  INDEX idx_subjects_form (form_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Topics table for secondary subjects
CREATE TABLE IF NOT EXISTS secondary_topics (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  topic_name VARCHAR(150) NOT NULL,
  subject_id INT UNSIGNED NOT NULL,
  description TEXT,
  order_index INT UNSIGNED DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (subject_id) REFERENCES secondary_subjects(id) ON DELETE CASCADE,
  INDEX idx_topics_subject (subject_id),
  INDEX idx_topics_order (subject_id, order_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Questions table for secondary topics
CREATE TABLE IF NOT EXISTS secondary_questions (
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
  FOREIGN KEY (topic_id) REFERENCES secondary_topics(id) ON DELETE CASCADE,
  INDEX idx_questions_topic (topic_id),
  INDEX idx_questions_type (question_type),
  INDEX idx_questions_order (topic_id, order_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Question options table for secondary questions (for multiple choice questions)
CREATE TABLE IF NOT EXISTS secondary_question_options (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  question_id INT UNSIGNED NOT NULL,
  option_text TEXT NOT NULL,
  is_correct BOOLEAN DEFAULT FALSE,
  order_index INT UNSIGNED DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (question_id) REFERENCES secondary_questions(id) ON DELETE CASCADE,
  INDEX idx_options_question (question_id),
  INDEX idx_options_correct (question_id, is_correct)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Question answers table for secondary questions (for one word and true/false questions)
CREATE TABLE IF NOT EXISTS secondary_question_answers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  question_id INT UNSIGNED NOT NULL,
  answer_text TEXT NOT NULL,
  is_case_sensitive BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (question_id) REFERENCES secondary_questions(id) ON DELETE CASCADE,
  INDEX idx_answers_question (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Attempts table for recording secondary-level quiz submissions
CREATE TABLE IF NOT EXISTS secondary_question_attempts (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Did You Know table for primary level
CREATE TABLE IF NOT EXISTS primary_did_you_know (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  fact_text TEXT NOT NULL,
  category ENUM('science', 'math', 'history', 'geography', 'general', 'language', 'arts', 'technology') NOT NULL,
  grade_id INT UNSIGNED NULL,
  subject_id INT UNSIGNED NULL,
  topic_id INT UNSIGNED NULL,
  image_url VARCHAR(255) NULL,
  source VARCHAR(255) NULL,
  is_active BOOLEAN DEFAULT TRUE,
  display_order INT UNSIGNED DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (grade_id) REFERENCES primary_grades(id) ON DELETE SET NULL,
  FOREIGN KEY (subject_id) REFERENCES primary_subjects(id) ON DELETE SET NULL,
  FOREIGN KEY (topic_id) REFERENCES primary_topics(id) ON DELETE SET NULL,
  INDEX idx_dyk_category (category),
  INDEX idx_dyk_grade (grade_id),
  INDEX idx_dyk_subject (subject_id),
  INDEX idx_dyk_topic (topic_id),
  INDEX idx_dyk_active (is_active),
  INDEX idx_dyk_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Did You Know table for secondary level
CREATE TABLE IF NOT EXISTS secondary_did_you_know (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  fact_text TEXT NOT NULL,
  category ENUM('science', 'math', 'history', 'geography', 'general', 'language', 'arts', 'technology', 'biology', 'chemistry', 'physics', 'literature') NOT NULL,
  form_id INT UNSIGNED NULL,
  subject_id INT UNSIGNED NULL,
  topic_id INT UNSIGNED NULL,
  image_url VARCHAR(255) NULL,
  source VARCHAR(255) NULL,
  is_active BOOLEAN DEFAULT TRUE,
  display_order INT UNSIGNED DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (form_id) REFERENCES secondary_forms(id) ON DELETE SET NULL,
  FOREIGN KEY (subject_id) REFERENCES secondary_subjects(id) ON DELETE SET NULL,
  FOREIGN KEY (topic_id) REFERENCES secondary_topics(id) ON DELETE SET NULL,
  INDEX idx_dyk_category (category),
  INDEX idx_dyk_form (form_id),
  INDEX idx_dyk_subject (subject_id),
  INDEX idx_dyk_topic (topic_id),
  INDEX idx_dyk_active (is_active),
  INDEX idx_dyk_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User fact views table to track which facts have been shown to which users
CREATE TABLE IF NOT EXISTS user_fact_views (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  fact_id INT UNSIGNED NOT NULL,
  education_level ENUM('primary','secondary') NOT NULL,
  viewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_user_fact_view (user_id, fact_id, education_level),
  INDEX idx_views_user (user_id),
  INDEX idx_views_fact (fact_id, education_level),
  INDEX idx_views_timestamp (viewed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;