<?php
require_once '../config/database.php';
require_once '../config/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$response = ['success' => false, 'message' => '', 'data' => null];

try {
    $db = getDatabaseConnection();
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'send':
            if (!checkRequestMethod('POST')) {
                $response['message'] = 'طريقة الطلب غير صحيحة';
                break;
            }
            
            $name = sanitize($_POST['name'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $subject = sanitize($_POST['subject'] ?? '');
            $message = sanitize($_POST['message'] ?? '');
            
            if (empty($name) || empty($email) || empty($subject) || empty($message)) {
                $response['message'] = 'يرجى ملء جميع الحقول المطلوبة';
                break;
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $response['message'] = 'البريد الإلكتروني غير صحيح';
                break;
            }
            
            // إدخال الرسالة في قاعدة البيانات
            $stmt = $db->prepare("
                INSERT INTO contact_messages (name, email, subject, message) 
                VALUES (?, ?, ?, ?)
            ");
            
            $stmt->execute([$name, $email, $subject, $message]);
            
            // إرسال إشعار بالبريد الإلكتروني للمشرف
            $settings_stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'admin_email'");
            $settings_stmt->execute();
            $admin_email = $settings_stmt->fetch()['setting_value'] ?? 'admin@example.com';
            
            $email_subject = "رسالة جديدة: $subject";
            $email_message = "
            <h3>رسالة جديدة من $name</h3>
            <p><strong>البريد الإلكتروني:</strong> $email</p>
            <p><strong>الموضوع:</strong> $subject</p>
            <p><strong>الرسالة:</strong></p>
            <div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;'>
                $message
            </div>
            <p>يمكنك الرد على هذه الرسالة من لوحة التحكم.</p>
            ";
            
            sendEmail($admin_email, $email_subject, $email_message);
            
            $response['success'] = true;
            $response['message'] = 'تم إرسال رسالتك بنجاح. سنرد عليك في أقرب وقت ممكن.';
            break;
            
        case 'get_all':
            if (!isAdmin()) {
                $response['message'] = 'غير مصرح لك بمشاهدة الرسائل';
                break;
            }
            
            $status = $_GET['status'] ?? '';
            $page = intval($_GET['page'] ?? 1);
            $limit = intval($_GET['limit'] ?? 10);
            $offset = ($page - 1) * $limit;
            
            $query = "SELECT * FROM contact_messages";
            $params = [];
            
            if (!empty($status)) {
                $query .= " WHERE status = ?";
                $params[] = $status;
            }
            
            // الحصول على إجمالي عدد الرسائل
            $count_query = "SELECT COUNT(*) as total FROM (" . str_replace("SELECT *", "SELECT id", $query) . ") as count_table";
            $count_stmt = $db->prepare($count_query);
            $count_stmt->execute($params);
            $total = $count_stmt->fetch()['total'];
            
            $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $messages = $stmt->fetchAll();
            
            // تنسيق التاريخ
            foreach ($messages as &$message) {
                $message['created_at_formatted'] = date('Y-m-d H:i', strtotime($message['created_at']));
                if ($message['replied_at']) {
                    $message['replied_at_formatted'] = date('Y-m-d H:i', strtotime($message['replied_at']));
                }
            }
            
            // الحصول على إحصائيات الرسائل
            $stats_stmt = $db->query("
                SELECT 
                    status,
                    COUNT(*) as count
                FROM contact_messages 
                GROUP BY status
            ");
            $message_stats = $stats_stmt->fetchAll();
            
            $response['success'] = true;
            $response['data'] = [
                'messages' => $messages,
                'stats' => $message_stats,
                'total' => $total,
                'page' => $page,
                'pages' => ceil($total / $limit)
            ];
            break;
            
        case 'get_by_id':
            if (!isAdmin()) {
                $response['message'] = 'غير مصرح لك بمشاهدة الرسالة';
                break;
            }
            
            $id = intval($_GET['id'] ?? 0);
            
            if ($id <= 0) {
                $response['message'] = 'معرف الرسالة غير صالح';
                break;
            }
            
            // تحديث حالة الرسالة إلى مقروء
            $update_stmt = $db->prepare("UPDATE contact_messages SET status = 'read' WHERE id = ? AND status = 'new'");
            $update_stmt->execute([$id]);
            
            // الحصول على الرسالة
            $stmt = $db->prepare("SELECT * FROM contact_messages WHERE id = ?");
            $stmt->execute([$id]);
            $message = $stmt->fetch();
            
            if ($message) {
                $message['created_at_formatted'] = date('Y-m-d H:i', strtotime($message['created_at']));
                if ($message['replied_at']) {
                    $message['replied_at_formatted'] = date('Y-m-d H:i', strtotime($message['replied_at']));
                }
                
                $response['success'] = true;
                $response['data'] = $message;
            } else {
                $response['message'] = 'الرسالة غير موجودة';
            }
            break;
            
        case 'update_status':
            if (!isAdmin()) {
                $response['message'] = 'غير مصرح لك بتحديث حالة الرسالة';
                break;
            }
            
            if (!checkRequestMethod('POST')) {
                $response['message'] = 'طريقة الطلب غير صحيحة';
                break;
            }
            
            $id = intval($_POST['id'] ?? 0);
            $status = sanitize($_POST['status'] ?? '');
            
            if ($id <= 0) {
                $response['message'] = 'معرف الرسالة غير صالح';
                break;
            }
            
            if (!in_array($status, ['new', 'read', 'replied', 'archived'])) {
                $response['message'] = 'حالة غير صحيحة';
                break;
            }
            
            $update_data = ['status = ?'];
            $params = [$status];
            
            if ($status === 'replied') {
                $update_data[] = 'replied_at = NOW()';
            }
            
            $update_data[] = 'admin_notes = ?';
            $params[] = sanitize($_POST['notes'] ?? '');
            
            $params[] = $id;
            
            $update_query = "UPDATE contact_messages SET " . implode(", ", $update_data) . " WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute($params);
            
            // تسجيل النشاط
            $log_stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
            $log_stmt->execute([
                $_SESSION['user_id'],
                'message_updated',
                'تم تحديث حالة الرسالة إلى: ' . $status,
                $_SERVER['REMOTE_ADDR']
            ]);
            
            $response['success'] = true;
            $response['message'] = 'تم تحديث حالة الرسالة بنجاح';
            break;
            
        case 'delete':
            if (!isAdmin()) {
                $response['message'] = 'غير مصرح لك بحذف الرسائل';
                break;
            }
            
            $id = intval($_GET['id'] ?? 0);
            
            if ($id <= 0) {
                $response['message'] = 'معرف الرسالة غير صالح';
                break;
            }
            
            $delete_stmt = $db->prepare("DELETE FROM contact_messages WHERE id = ?");
            $delete_stmt->execute([$id]);
            
            // تسجيل النشاط
            $log_stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
            $log_stmt->execute([
                $_SESSION['user_id'],
                'message_deleted',
                'تم حذف رسالة',
                $_SERVER['REMOTE_ADDR']
            ]);
            
            $response['success'] = true;
            $response['message'] = 'تم حذف الرسالة بنجاح';
            break;
            
        case 'send_reply':
            if (!isAdmin()) {
                $response['message'] = 'غير مصرح لك بالرد على الرسائل';
                break;
            }
            
            if (!checkRequestMethod('POST')) {
                $response['message'] = 'طريقة الطلب غير صحيحة';
                break;
            }
            
            $message_id = intval($_POST['message_id'] ?? 0);
            $reply_subject = sanitize($_POST['reply_subject'] ?? '');
            $reply_message = sanitize($_POST['reply_message'] ?? '');
            
            if ($message_id <= 0 || empty($reply_subject) || empty($reply_message)) {
                $response['message'] = 'يرجى ملء جميع الحقول المطلوبة';
                break;
            }
            
            // الحصول على معلومات الرسالة الأصلية
            $stmt = $db->prepare("SELECT name, email, subject FROM contact_messages WHERE id = ?");
            $stmt->execute([$message_id]);
            $original_message = $stmt->fetch();
            
            if (!$original_message) {
                $response['message'] = 'الرسالة غير موجودة';
                break;
            }
            
            // إرسال الرد بالبريد الإلكتروني
            $email_message = "
            <h3>رد على رسالتك: {$original_message['subject']}</h3>
            <p>مرحباً {$original_message['name']},</p>
            <div style='background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin: 15px 0;'>
                $reply_message
            </div>
            <p>مع خالص التقدير،<br>فريق " . ($original_message['subject'] ?? 'متجر تطبيقاتي') . "</p>
            ";
            
            if (sendEmail($original_message['email'], $reply_subject, $email_message)) {
                // تحديث حالة الرسالة
                $update_stmt = $db->prepare("UPDATE contact_messages SET status = 'replied', replied_at = NOW(), admin_notes = ? WHERE id = ?");
                $update_stmt->execute([$reply_message, $message_id]);
                
                // تسجيل النشاط
                $log_stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
                $log_stmt->execute([
                    $_SESSION['user_id'],
                    'message_replied',
                    'تم الرد على رسالة: ' . $original_message['subject'],
                    $_SERVER['REMOTE_ADDR']
                ]);
                
                $response['success'] = true;
                $response['message'] = 'تم إرسال الرد بنجاح';
            } else {
                $response['message'] = 'حدث خطأ في إرسال الرد';
            }
            break;
            
        case 'get_stats':
            if (!isAdmin()) {
                $response['message'] = 'غير مصرح لك بالوصول إلى الإحصائيات';
                break;
            }
            
            // إحصائيات الرسائل
            $stmt = $db->query("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as count
                FROM contact_messages 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date
            ");
            $daily_stats = $stmt->fetchAll();
            
            // إحصائيات الحالات
            $status_stmt = $db->query("
                SELECT 
                    status,
                    COUNT(*) as count
                FROM contact_messages 
                GROUP BY status
            ");
            $status_stats = $status_stmt->fetchAll();
            
            $response['success'] = true;
            $response['data'] = [
                'daily_stats' => $daily_stats,
                'status_stats' => $status_stats
            ];
            break;
            
        default:
            $response['message'] = 'عملية غير معروفة';
    }
    
} catch (Exception $e) {
    error_log("API Error (messages.php): " . $e->getMessage());
    $response['message'] = 'حدث خطأ في النظام';
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>