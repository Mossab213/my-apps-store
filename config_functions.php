<?php
require_once 'config_database.php'; // ✅ تصحيح اسم الملف

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

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8\r\n";
        $headers .= "From: $site_name <$admin_email>\r\n";
        $headers .= "Reply-To: $admin_email\r\n";

        $html_message = "
        <!DOCTYPE html>
        <html lang='ar' dir='rtl'>
        <head>
        <meta charset='UTF-8'>
        <title>$subject</title>
        </head>
        <body>
            <div style='padding:20px;font-family:tahoma'>
                $message
            </div>
        </body>
        </html>";

        return mail($to, $subject, $html_message, $headers);

    } catch (Exception $e) {
        error_log('Email sending failed: ' . $e->getMessage());
        return false;
    }
}

// دالة لإرسال بريد إعادة التعيين
function sendPasswordResetEmail($email, $token) {
    // جلب اسم الموقع
    $db = getDatabaseConnection();
    $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'site_name'");
    $stmt->execute();
    $site_name = $stmt->fetch()['setting_value'] ?? 'متجر تطبيقاتي';

    $reset_link = SITE_URL . "reset_password.html?token=" . urlencode($token);

    $subject = "إعادة تعيين كلمة المرور";

    $message = "
    <h3>مرحباً،</h3>
    <p>لقد طلبت إعادة تعيين كلمة المرور.</p>
    <p><a href='$reset_link'>اضغط هنا لإعادة تعيين كلمة المرور</a></p>
    <p>إذا لم تطلب ذلك، تجاهل هذا البريد.</p>
    <br>
    <p>مع تحيات فريق $site_name</p>
    ";

    return sendEmail($email, $subject, $message);
}
