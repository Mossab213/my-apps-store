<?php
require_once '../config/database.php';
require_once '../config/functions.php';

header('Content-Type: application/json; charset=utf-8');

// السماح بطلبات CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$response = ['success' => false, 'message' => '', 'data' => null];

try {
    $db = getDatabaseConnection();
    
    // الحصول على الإجراء المطلوب
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'login':
            if (!checkRequestMethod('POST')) {
                $response['message'] = 'طريقة الطلب غير صحيحة';
                break;
            }
            
            $username = sanitize($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $remember = isset($_POST['remember']) && $_POST['remember'] == 'true';
            
            if (empty($username) || empty($password)) {
                $response['message'] = 'يرجى ملء جميع الحقول المطلوبة';
                break;
            }
            
            // البحث عن المستخدم
            $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // تحديث وقت آخر دخول
                $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);
                
                // إنشاء الجلسة
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                
                // إذا طلب تذكر الدخول
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
                    
                    $stmt = $db->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                    $stmt->execute([$token, $user['id']]);
                    
                    setcookie('remember_token', $token, [
                        'expires' => time() + (86400 * 30),
                        'path' => '/',
                        'secure' => isset($_SERVER['HTTPS']),
                        'httponly' => true,
                        'samesite' => 'Strict'
                    ]);
                }
                
                // تسجيل النشاط
                $logStmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
                $logStmt->execute([
                    $user['id'],
                    'login',
                    'تسجيل دخول ناجح',
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
                
                $response['success'] = true;
                $response['message'] = 'تم تسجيل الدخول بنجاح';
                $response['data'] = [
                    'redirect' => 'admin.html',
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email']
                    ]
                ];
                
            } else {
                $response['message'] = 'اسم المستخدم أو كلمة المرور غير صحيحة';
                
                // تسجيل محاولة فاشلة
                $logStmt = $db->prepare("INSERT INTO activity_logs (action, details, ip_address, user_agent) VALUES (?, ?, ?, ?)");
                $logStmt->execute([
                    'failed_login',
                    'محاولة تسجيل دخول فاشلة للمستخدم: ' . $username,
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
            }
            break;
            
        case 'logout':
            // تسجيل النشاط
            if (isLoggedIn()) {
                $logStmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
                $logStmt->execute([
                    $_SESSION['user_id'],
                    'logout',
                    'تسجيل خروج',
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
            }
            
            // تدمير الجلسة
            session_destroy();
            
            // حذف كوكي تذكر الدخول
            setcookie('remember_token', '', [
                'expires' => time() - 3600,
                'path' => '/'
            ]);
            
            $response['success'] = true;
            $response['message'] = 'تم تسجيل الخروج بنجاح';
            $response['data'] = ['redirect' => 'index.html'];
            break;
            
        case 'check_session':
            $response['success'] = isLoggedIn();
            $response['message'] = $response['success'] ? 'الجلسة نشطة' : 'الجلسة منتهية';
            $response['data'] = [
                'is_logged_in' => isLoggedIn(),
                'user' => getCurrentUser()
            ];
            break;
            
        case 'forgot_password':
            if (!checkRequestMethod('POST')) {
                $response['message'] = 'طريقة الطلب غير صحيحة';
                break;
            }
            
            $email = sanitize($_POST['email'] ?? '');
            
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $response['message'] = 'يرجى إدخال بريد إلكتروني صحيح';
                break;
            }
            
            // التحقق من وجود البريد الإلكتروني
            $stmt = $db->prepare("SELECT id, username, email FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // إنشاء رمز إعادة التعيين
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                $updateStmt = $db->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
                $updateStmt->execute([$token, $expiry, $user['id']]);
                
                // إرسال البريد الإلكتروني
                if (sendPasswordResetEmail($email, $token)) {
                    // تسجيل النشاط
                    $logStmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
                    $logStmt->execute([
                        $user['id'],
                        'password_reset_request',
                        'طلب إعادة تعيين كلمة المرور',
                        $_SERVER['REMOTE_ADDR']
                    ]);
                    
                    $response['success'] = true;
                    $response['message'] = 'تم إرسال رابط إعادة التعيين إلى بريدك الإلكتروني';
                } else {
                    $response['message'] = 'حدث خطأ في إرسال البريد الإلكتروني';
                }
            } else {
                $response['message'] = 'البريد الإلكتروني غير مسجل في النظام';
            }
            break;
            
        case 'reset_password':
            if (!checkRequestMethod('POST')) {
                $response['message'] = 'طريقة الطلب غير صحيحة';
                break;
            }
            
            $token = sanitize($_POST['token'] ?? '');
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($token) || empty($new_password) || empty($confirm_password)) {
                $response['message'] = 'يرجى ملء جميع الحقول';
                break;
            }
            
            if ($new_password !== $confirm_password) {
                $response['message'] = 'كلمتا المرور غير متطابقتين';
                break;
            }
            
            if (strlen($new_password) < 6) {
                $response['message'] = 'كلمة المرور يجب أن تكون على الأقل 6 أحرف';
                break;
            }
            
            // التحقق من الرمز
            $stmt = $db->prepare("SELECT id, email FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()");
            $stmt->execute([$token]);
            $user = $stmt->fetch();
            
            if ($user) {
                // تحديث كلمة المرور
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                $updateStmt = $db->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
                $updateStmt->execute([$password_hash, $user['id']]);
                
                // تسجيل النشاط
                $logStmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
                $logStmt->execute([
                    $user['id'],
                    'password_reset',
                    'تم إعادة تعيين كلمة المرور',
                    $_SERVER['REMOTE_ADDR']
                ]);
                
                $response['success'] = true;
                $response['message'] = 'تم إعادة تعيين كلمة المرور بنجاح';
                $response['data'] = ['redirect' => 'login.html'];
            } else {
                $response['message'] = 'رابط إعادة التعيين غير صالح أو منتهي الصلاحية';
            }
            break;
            
        case 'change_password':
            if (!checkRequestMethod('POST')) {
                $response['message'] = 'طريقة الطلب غير صحيحة';
                break;
            }
            
            if (!isLoggedIn()) {
                $response['message'] = 'غير مصرح لك بتنفيذ هذه العملية';
                break;
            }
            
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $response['message'] = 'يرجى ملء جميع الحقول';
                break;
            }
            
            if ($new_password !== $confirm_password) {
                $response['message'] = 'كلمتا المرور غير متطابقتين';
                break;
            }
            
            if (strlen($new_password) < 6) {
                $response['message'] = 'كلمة المرور الجديدة يجب أن تكون على الأقل 6 أحرف';
                break;
            }
            
            // الحصول على كلمة المرور الحالية
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($current_password, $user['password_hash'])) {
                // تحديث كلمة المرور
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                $updateStmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $updateStmt->execute([$password_hash, $_SESSION['user_id']]);
                
                // تسجيل النشاط
                $logStmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
                $logStmt->execute([
                    $_SESSION['user_id'],
                    'password_change',
                    'تم تغيير كلمة المرور',
                    $_SERVER['REMOTE_ADDR']
                ]);
                
                $response['success'] = true;
                $response['message'] = 'تم تغيير كلمة المرور بنجاح';
            } else {
                $response['message'] = 'كلمة المرور الحالية غير صحيحة';
            }
            break;
            
        default:
            $response['message'] = 'عملية غير معروفة';
    }
    
} catch (Exception $e) {
    error_log("API Error (auth.php): " . $e->getMessage());
    $response['message'] = 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى.';
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>