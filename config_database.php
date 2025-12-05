<?php
// إعدادات قاعدة البيانات
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'app_store_db');
define('DB_CHARSET', 'utf8mb4');

// إعدادات الموقع
define('SITE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/');
define('UPLOAD_DIR', dirname(__DIR__) . '/assets/uploads/');

// إنشاء اتصال PDO
function getDatabaseConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        $pdo->exec("SET NAMES 'utf8mb4'");
        $pdo->exec("SET CHARACTER SET utf8mb4");
        
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        die("Connection failed. Please check your database configuration.");
    }
}

// بدء الجلسة
session_start();

// دالة لتأمين المدخلات
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// دالة لإعادة توجيه المستخدم
function redirect($url) {
    header("Location: $url");
    exit();
}

// دالة للتحقق من تسجيل الدخول
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

// دالة للتحقق من أن المستخدم هو مشرف
function isAdmin() {
    if (!isLoggedIn()) return false;
    
    try {
        $db = getDatabaseConnection();
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE id = ? AND username = ?");
        $stmt->execute([$_SESSION['user_id'], $_SESSION['username']]);
        $result = $stmt->fetch();
        
        return $result['count'] > 0;
    } catch (Exception $e) {
        return false;
    }
}

// دالة للحصول على معلومات المستخدم الحالي
function getCurrentUser() {
    if (isLoggedIn()) {
        try {
            $db = getDatabaseConnection();
            $stmt = $db->prepare("SELECT id, username, email FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            return $stmt->fetch();
        } catch (Exception $e) {
            return null;
        }
    }
    return null;
}

// دالة للتحقق من رأس الطلب
function checkRequestMethod($method = 'POST') {
    return $_SERVER['REQUEST_METHOD'] === strtoupper($method);
}

// دالة لإنشاء رد JSON
function jsonResponse($success = false, $message = '', $data = []) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// إنشاء مجلدات التحميل إذا لم تكن موجودة
function createUploadDirs() {
    $dirs = [
        UPLOAD_DIR,
        UPLOAD_DIR . 'apps/',
        UPLOAD_DIR . 'images/',
        UPLOAD_DIR . 'temp/'
    ];
    
    foreach ($dirs as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
    }
}

// استدعاء إنشاء المجلدات
createUploadDirs();
?>