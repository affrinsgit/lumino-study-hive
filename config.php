<?php
/**
 * Lumino: Study Hive
 * Global Configuration File
 * 
 * This file manages all database credentials and global settings
 * Update only this file for deployment
 * SECURITY FIX: Added CSRF token generation and validation
 */

// Database Connection Settings
define('DB_HOST', 'localhost');
define('DB_NAME', 'lumino_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Website Settings
define('SITE_NAME', 'Lumino: Study Hive');
define('SITE_URL', 'http://localhost/lumino');
define('LOGO_PATH', '/lumino/assets/images/logo.png');

// Application Settings
define('FINE_PER_DAY', 5);
define('DEFAULT_ISSUE_DAYS', 14);

// Security Settings
define('PASSWORD_HASH_ALGO', PASSWORD_BCRYPT);
define('SESSION_TIMEOUT', 3600); // 1 hour
define('CSRF_TOKEN_NAME', 'csrf_token');
define('CSRF_TOKEN_LENGTH', 32);

// Color Palette
define('COLOR_PRIMARY_PINK', '#FF69B4');
define('COLOR_LAVENDER', '#E6E6FA');
define('COLOR_MINT', '#98FF98');
define('COLOR_PEACH', '#FFDAB9');
define('COLOR_WHITE', '#FFFFFF');

// Database Connection Function
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die(json_encode(['status' => 'error', 'message' => 'Database connection failed']));
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Start Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Session Timeout Check
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    session_destroy();
    header('Location: ' . SITE_URL . '/login.php?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time();

// CSRF Token Management (FIXED: Issue #5)
function generateCSRFToken() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME]) || empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function getCSRFToken() {
    return generateCSRFToken();
}

function validateCSRFToken($token) {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        return false;
    }
    return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token ?? '');
}

// Helper Functions
function isAdmin() {
    return isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin';
}

function isStudent() {
    return isset($_SESSION['user_id']) && $_SESSION['role'] === 'student';
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function redirectToLogin() {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

function redirectToDashboard() {
    if (isAdmin()) {
        header('Location: ' . SITE_URL . '/admin/dashboard.php');
    } else {
        header('Location: ' . SITE_URL . '/student/dashboard.php');
    }
    exit;
}

function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

function calculateFine($dueDate, $returnDate) {
    $due = strtotime($dueDate);
    $return = strtotime($returnDate);
    
    if ($return <= $due) {
        return 0;
    }
    
    $lateDays = floor(($return - $due) / (60 * 60 * 24));
    return $lateDays * FINE_PER_DAY;
}

// Input Sanitization Helper (FIXED: Issue #6)
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

?>
