<?php
require_once 'database.php';

// دالة لإرسال البريد الإلكتروني
function sendEmail($to, $subject, $message) {
    try {
        $db = getDatabaseConnection();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        
        // الحصول على إعدادات الموقع
        $stmt->execute(['site_name']);
        $site_name = $stmt->fetch()['setting_value'] ?? 'متجر تطبيقاتي';
        
        $stmt->execute(['admin_email']);
        $admin_email = $stmt->fetch()['setting_value'] ?? 'admin@example.com';
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: $site_name <$admin_email>" . "\r\n";
        $headers .= "Reply-To: $admin_email" . "\r\n";
        
        $html_message = "
        <!DOCTYPE html>
        <html lang='ar' dir='rtl'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>$subject</title>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; direction: rtl; background-color: #f9f9f9; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; background-color: white; }
                .header { background: linear-gradient(135deg, #2c3e50, #1a2530); color: white; padding: 30px 20px; text-align: center; }
                .content { padding: 30px; line-height: 1.8; color: #333; }
                .footer { background-color: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 14px; border-top: 1px solid #eee; }
                .button { display: inline-block; background-color: #3498db; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                .notice { background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>$site_name</h2>
                </div>
                <div class='content'>
                    $message
                </div>
                <div class='footer'>
                    <p>© " . date('Y') . " $site_name. جميع الحقوق محفوظة.</p>
                    <p style='font-size: 12px; margin-top: 10px;'>
                        هذا البريد الإلكتروني مرسل تلقائياً، يرجى عدم الرد عليه.
                    </p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return mail($to, $subject, $html_message, $headers);
        
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

// دالة لإرسال بريد إعادة تعيين كلمة المرور
function sendPasswordResetEmail($email, $token) {
    $reset_link = SITE_URL . "reset_password.html?token=" . urlencode($token);
    
    $subject = "إعادة تعيين كلمة المرور";
    
    $message = "
    <h3>مرحباً،</h3>
    <p>لقد طلبت إعادة تعيين كلمة المرور لحسابك.</p>
    <p>لإعادة تعيين كلمة المرور، اضغط على الرابط التالي:</p>
    <p style='text-align: center;'>
        <a href='$reset_link' class='button'>إعادة تعيين كلمة المرور</a>
    </p>
    <p>إذا لم تتمكن من النقر على الزر، يمكنك نسخ الرابط التالي ولصقه في متصفحك:</p>
    <p style='background-color: #f8f9fa; padding: 10px; border-radius: 5px; word-break: break-all;'>
        $reset_link
    </p>
    <div class='notice'>
        <strong>ملاحظة مهمة:</strong>
        <p>هذا الرابط سينتهي خلال ساعة واحدة. إذا لم تقم بإعادة التعيين خلال هذه المدة، ستحتاج إلى طلب رابط جديد.</p>
    </div>
    <p>إذا لم تطلب إعادة التعيين، يمكنك تجاهل هذا البريد وستظل كلمة مرورك كما هي.</p>
    <p>مع خالص التقدير،<br>فريق $site_name</p>
    ";
    
    return sendEmail($email, $subject, $message);
}

// دالة للحصول على الإحصائيات
function getDashboardStats() {
    try {
        $db = getDatabaseConnection();
        $stats = [];
        
        // إجمالي التطبيقات النشطة
        $stmt = $db->query("SELECT COUNT(*) as total FROM apps WHERE is_active = 1");
        $stats['total_apps'] = $stmt->fetch()['total'] ?? 0;
        
        // إجمالي التحميلات
        $stmt = $db->query("SELECT COUNT(*) as total FROM downloads");
        $stats['total_downloads'] = $stmt->fetch()['total'] ?? 0;
        
        // إجمالي المساحة المستخدمة (بالميجابايت)
        $stmt = $db->query("SELECT COALESCE(SUM(size_mb), 0) as total FROM apps WHERE is_active = 1");
        $stats['total_storage'] = round($stmt->fetch()['total'] ?? 0, 2);
        
        // التطبيقات حسب النظام
        $stmt = $db->query("SELECT COUNT(*) as total FROM apps WHERE is_active = 1 AND os_requirements LIKE '%Windows%'");
        $stats['windows_apps'] = $stmt->fetch()['total'] ?? 0;
        
        // الرسائل الجديدة
        $stmt = $db->query("SELECT COUNT(*) as total FROM contact_messages WHERE status = 'new'");
        $stats['new_messages'] = $stmt->fetch()['total'] ?? 0;
        
        // التحميلات اليومية
        $stmt = $db->query("SELECT COUNT(*) as total FROM downloads WHERE DATE(download_date) = CURDATE()");
        $stats['today_downloads'] = $stmt->fetch()['total'] ?? 0;
        
        // التطبيقات الأكثر تحميلاً
        $stmt = $db->query("
            SELECT a.name, COUNT(d.id) as downloads 
            FROM apps a 
            LEFT JOIN downloads d ON a.id = d.app_id 
            WHERE a.is_active = 1 
            GROUP BY a.id 
            ORDER BY downloads DESC 
            LIMIT 5
        ");
        $stats['top_apps'] = $stmt->fetchAll();
        
        return $stats;
        
    } catch (Exception $e) {
        error_log("Error getting dashboard stats: " . $e->getMessage());
        return [
            'total_apps' => 0,
            'total_downloads' => 0,
            'total_storage' => 0,
            'windows_apps' => 0,
            'new_messages' => 0,
            'today_downloads' => 0,
            'top_apps' => []
        ];
    }
}

// دالة لتحميل الملفات
function uploadFile($file, $type = 'app') {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'خطأ في رفع الملف'];
    }
    
    // التحقق من نوع الملف
    $allowed_types = [];
    $max_size = 0;
    $upload_dir = '';
    
    switch ($type) {
        case 'app':
            $allowed_types = ['exe', 'msi', 'zip', 'rar', '7z'];
            $max_size = 500 * 1024 * 1024; // 500MB
            $upload_dir = UPLOAD_DIR . 'apps/';
            break;
            
        case 'image':
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $max_size = 5 * 1024 * 1024; // 5MB
            $upload_dir = UPLOAD_DIR . 'images/';
            break;
            
        default:
            return ['success' => false, 'message' => 'نوع الملف غير مدعوم'];
    }
    
    // التحقق من حجم الملف
    if ($file['size'] > $max_size) {
        $size_mb = $max_size / (1024 * 1024);
        return ['success' => false, 'message' => "حجم الملف كبير جداً! الحد الأقصى $size_mb ميجابايت"];
    }
    
    // الحصول على امتداد الملف
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_ext, $allowed_types)) {
        $allowed_list = implode(', ', $allowed_types);
        return ['success' => false, 'message' => "نوع الملف غير مدعوم! المسموح: $allowed_list"];
    }
    
    // إنشاء اسم فريد للملف
    $file_name = uniqid() . '_' . time() . '.' . $file_ext;
    $file_path = $upload_dir . $file_name;
    
    // نقل الملف إلى المجلد المحدد
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        return [
            'success' => true,
            'file_name' => $file_name,
            'file_path' => $file_path,
            'original_name' => $file['name'],
            'file_size' => $file['size']
        ];
    }
    
    return ['success' => false, 'message' => 'فشل في حفظ الملف'];
}

// دالة لحذف ملف
function deleteFile($file_path) {
    if (file_exists($file_path) && is_file($file_path)) {
        return unlink($file_path);
    }
    return false;
}

// دالة لتنسيق حجم الملف
function formatFileSize($bytes) {
    if ($bytes === 0) return '0 بايت';
    
    $k = 1024;
    $sizes = ['بايت', 'كيلوبايت', 'ميجابايت', 'جيجابايت'];
    $i = floor(log($bytes) / log($k));
    
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

// دالة للتحقق من رمز إعادة التعيين
function validateResetToken($token) {
    try {
        $db = getDatabaseConnection();
        $stmt = $db->prepare("SELECT id, email FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()");
        $stmt->execute([$token]);
        
        return $stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}
?>