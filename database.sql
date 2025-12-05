-- إنشاء قاعدة البيانات
CREATE DATABASE IF NOT EXISTS app_store_db 
DEFAULT CHARACTER SET utf8mb4 
DEFAULT COLLATE utf8mb4_unicode_ci;

USE app_store_db;

-- جدول المستخدمين (المشرفين)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    remember_token VARCHAR(255) DEFAULT NULL,
    reset_token VARCHAR(255) DEFAULT NULL,
    reset_token_expiry DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_reset_token (reset_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول التطبيقات
CREATE TABLE IF NOT EXISTS apps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    version VARCHAR(20) NOT NULL,
    size_mb DECIMAL(10,2) NOT NULL,
    downloads INT DEFAULT 0,
    developer VARCHAR(100),
    os_requirements VARCHAR(255) DEFAULT 'Windows 7 أو أحدث',
    architecture VARCHAR(20) DEFAULT 'x64',
    license_type VARCHAR(50) DEFAULT 'مجاني',
    website_url VARCHAR(255),
    whats_new TEXT,
    file_name VARCHAR(255),
    file_path VARCHAR(255),
    image_name VARCHAR(255),
    image_path VARCHAR(255),
    rating DECIMAL(3,2) DEFAULT 4.5,
    is_featured BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FULLTEXT idx_search (name, description, developer),
    INDEX idx_category (category),
    INDEX idx_is_active (is_active),
    INDEX idx_is_featured (is_featured),
    INDEX idx_created_at (created_at),
    INDEX idx_downloads (downloads DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول التحميلات
CREATE TABLE IF NOT EXISTS downloads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    app_id INT NOT NULL,
    user_ip VARCHAR(45),
    user_agent TEXT,
    referrer VARCHAR(255),
    download_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (app_id) REFERENCES apps(id) ON DELETE CASCADE,
    INDEX idx_app_id (app_id),
    INDEX idx_download_date (download_date),
    INDEX idx_user_ip (user_ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول رسائل الاتصال
CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('new', 'read', 'replied', 'archived') DEFAULT 'new',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    replied_at TIMESTAMP NULL,
    
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول الإعدادات
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_group VARCHAR(50) DEFAULT 'general',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_setting_key (setting_key),
    INDEX idx_setting_group (setting_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول سجلات النشاط
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إدراج المستخدم الافتراضي (كلمة المرور: admin123)
INSERT INTO users (username, email, password_hash) VALUES 
('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- إدراج الإعدادات الافتراضية
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
('site_name', 'متجر تطبيقاتي', 'general'),
('site_description', 'مرحبًا بك في متجر تطبيقاتي الرسمي', 'general'),
('admin_email', 'admin@example.com', 'general'),
('contact_email', 'contact@example.com', 'general'),
('items_per_page', '12', 'general'),
('enable_registration', '0', 'general'),
('maintenance_mode', '0', 'general'),

('smtp_host', 'smtp.gmail.com', 'email'),
('smtp_port', '587', 'email'),
('smtp_username', 'your-email@gmail.com', 'email'),
('smtp_password', 'your-app-password', 'email'),
('smtp_encryption', 'tls', 'email'),

('max_upload_size_app', '500', 'uploads'),
('max_upload_size_image', '5', 'uploads'),
('allowed_app_extensions', 'exe,msi,zip,rar,7z', 'uploads'),
('allowed_image_extensions', 'jpg,jpeg,png,gif,webp', 'uploads'),

('default_currency', 'USD', 'payment'),
('enable_payments', '0', 'payment'),

('ga_tracking_id', '', 'analytics'),
('fb_pixel_id', '', 'analytics');

-- إدراج تطبيقات تجريبية
INSERT INTO apps (name, category, description, version, size_mb, developer, os_requirements, license_type, is_featured) VALUES
('متصفح فايرفوكس', 'productivity', 'متصفح ويب سريع وآمن ومفتوح المصدر من موزيلا', '120.0', 65.5, 'Mozilla Foundation', 'Windows 7 أو أحدث', 'مجاني', 1),
('برنامج VLC للميديا', 'multimedia', 'مشغل وسائط قوي يدعم جميع تنسيقات الفيديو والصوت', '3.0.18', 42.3, 'VideoLAN', 'Windows XP أو أحدث', 'مجاني', 1),
('برنامج 7-Zip', 'utility', 'مبرمج ضغط ملفات سريع وفعال مع دعم لجميع الصيغ', '23.01', 2.1, 'Igor Pavlov', 'Windows 7 أو أحدث', 'مجاني', 1),
('GIMP للتصميم', 'design', 'برنامج تحرير صور احترافي ومجاني', '2.10.34', 200.5, 'The GIMP Team', 'Windows 8 أو أحدث', 'مجاني', 0),
('أوفيس ليبر', 'productivity', 'حزمة مكتبية مجانية بديلة عن مايكروسوفت أوفيس', '7.6.4', 350.2, 'The Document Foundation', 'Windows 7 أو أحدث', 'مجاني', 1);

-- إدراج تحميلات تجريبية
INSERT INTO downloads (app_id, user_ip, user_agent) VALUES
(1, '192.168.1.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'),
(2, '192.168.1.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0'),
(1, '192.168.1.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Firefox/121.0'),
(3, '192.168.1.4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Edge/120.0.0.0'),
(2, '192.168.1.5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Safari/537.36');

-- إدراج رسائل تجريبية
INSERT INTO contact_messages (name, email, subject, message, status) VALUES
('أحمد محمد', 'ahmed@example.com', 'استفسار عن تطبيق', 'مرحباً، أريد معرفة المزيد عن تطبيق فايرفوكس', 'new'),
('سارة خالد', 'sara@example.com', 'طلب تطبيق مخصص', 'أحتاج إلى تطبيق لإدارة المتجر الخاص بي', 'read'),
('محمد علي', 'mohammed@example.com', 'مشكلة في التحميل', 'لا أستطيع تحميل تطبيق VLC', 'replied');

-- إدراج سجلات نشاط
INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES
(1, 'login', 'تسجيل دخول ناجح', '192.168.1.100'),
(1, 'app_added', 'تم إضافة تطبيق جديد: فايرفوكس', '192.168.1.100'),
(1, 'settings_updated', 'تم تحديث إعدادات الموقع', '192.168.1.100');