/**
 * ملف JavaScript الرئيسي لمتجر تطبيقاتي
 * يحتوي على جميع الدوال والوظائف المشتركة
 */

// ============ إعدادات النظام ============
const API_CONFIG = {
    BASE_URL: window.location.origin + '/',
    AUTH_API: 'api_auth.php',
    APPS_API: 'api_apps.php',
    MESSAGES_API: 'api_messages.php',
    SETTINGS_API: 'api_settings.php'
};

// ============ دوال مساعدة ============

/**
 * التحقق من تسجيل الدخول
 * @returns {Promise<boolean>}
 */
async function checkLoginStatus() {
    try {
        const response = await fetch(`${API_CONFIG.AUTH_API}?action=check_session`);
        const data = await response.json();
        return data.success && data.data.is_logged_in;
    } catch (error) {
        console.error('خطأ في التحقق من حالة الدخول:', error);
        return false;
    }
}

/**
 * إظهار رسالة تنبيه
 * @param {string} message - نص الرسالة
 * @param {string} type - نوع الرسالة (success, error, info, warning)
 * @param {string} containerId - معرف الحاوية (اختياري)
 */
function showAlert(message, type = 'info', containerId = null) {
    // إنشاء عنصر التنبيه
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.innerHTML = `
        <span>${message}</span>
        <button class="close-alert">&times;</button>
    `;
    
    // تحديد مكان العرض
    let container;
    if (containerId) {
        container = document.getElementById(containerId);
    }
    
    if (!container || !document.body.contains(container)) {
        // إنشاء حاوية جديدة إذا لم تكن موجودة
        container = document.createElement('div');
        container.id = 'globalAlert';
        container.style.position = 'fixed';
        container.style.top = '20px';
        container.style.right = '20px';
        container.style.zIndex = '9999';
        container.style.maxWidth = '400px';
        document.body.appendChild(container);
    }
    
    // إضافة التنبيه
    container.appendChild(alertDiv);
    container.style.display = 'block';
    
    // إضافة حدث الإغلاق
    const closeBtn = alertDiv.querySelector('.close-alert');
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            alertDiv.style.opacity = '0';
            alertDiv.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.parentNode.removeChild(alertDiv);
                }
                if (container && container.children.length === 0) {
                    container.style.display = 'none';
                }
            }, 300);
        });
    }
    
    // إخفاء تلقائي للرسائل الناجحة والمعلوماتية
    if (type === 'success' || type === 'info') {
        setTimeout(() => {
            if (closeBtn && closeBtn.parentNode) {
                closeBtn.click();
            }
        }, 5000);
    }
    
    // إضافة تأثير الظهور
    setTimeout(() => {
        alertDiv.style.opacity = '1';
        alertDiv.style.transform = 'translateX(0)';
    }, 10);
}

/**
 * تحميل إعدادات الموقع
 * @returns {Promise<Object>}
 */
async function loadSiteSettings() {
    try {
        const response = await fetch(`${API_CONFIG.SETTINGS_API}?action=get_site_info`);
        const data = await response.json();
        
        if (data.success) {
            return data.data;
        }
        return {};
    } catch (error) {
        console.error('خطأ في تحميل إعدادات الموقع:', error);
        return {};
    }
}

/**
 * تطبيق إعدادات الموقع على الصفحة
 */
async function applySiteSettings() {
    const settings = await loadSiteSettings();
    
    // تحديث اسم الموقع في العلامة
    if (settings.site_name) {
        const siteNameElements = document.querySelectorAll('[data-site-name]');
        siteNameElements.forEach(element => {
            element.textContent = settings.site_name;
        });
        
        // تحديث عنوان الصفحة
        const pageTitle = document.querySelector('title');
        if (pageTitle && !pageTitle.textContent.includes(settings.site_name)) {
            pageTitle.textContent = pageTitle.textContent.replace('متجر تطبيقاتي', settings.site_name);
        }
    }
    
    // تحديث وصف الموقع
    if (settings.site_description) {
        const descElements = document.querySelectorAll('[data-site-description]');
        descElements.forEach(element => {
            element.textContent = settings.site_description;
        });
    }
    
    // تحديث البريد الإلكتروني
    if (settings.contact_email || settings.admin_email) {
        const email = settings.contact_email || settings.admin_email;
        const emailElements = document.querySelectorAll('[data-site-email]');
        emailElements.forEach(element => {
            if (element.tagName === 'A' && element.href.startsWith('mailto:')) {
                element.href = `mailto:${email}`;
                element.textContent = email;
            } else {
                element.textContent = email;
            }
        });
    }
    
    // تحديث سنة حقوق النشر
    const yearElements = document.querySelectorAll('[data-current-year]');
    yearElements.forEach(element => {
        element.textContent = new Date().getFullYear();
    });
}

/**
 * تنسيق حجم الملف
 * @param {number} bytes - الحجم بالبايت
 * @returns {string}
 */
function formatFileSize(bytes) {
    if (bytes === 0) return '0 بايت';
    
    const k = 1024;
    const sizes = ['بايت', 'كيلوبايت', 'ميجابايت', 'جيجابايت'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

/**
 * تنسيق التاريخ
 * @param {string} dateString - تاريخ بصيغة ISO
 * @returns {string}
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('ar-SA', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * إنشاء تقييم النجوم
 * @param {number} rating - التقييم من 0-5
 * @returns {string}
 */
function createStarRating(rating) {
    let stars = '';
    const fullStars = Math.floor(rating);
    const hasHalfStar = rating % 1 >= 0.5;
    
    for (let i = 0; i < fullStars; i++) {
        stars += '<i class="fas fa-star"></i>';
    }
    
    if (hasHalfStar) {
        stars += '<i class="fas fa-star-half-alt"></i>';
    }
    
    const emptyStars = 5 - fullStars - (hasHalfStar ? 1 : 0);
    for (let i = 0; i < emptyStars; i++) {
        stars += '<i class="far fa-star"></i>';
    }
    
    return stars + ` <small>(${rating.toFixed(1)})</small>`;
}

// ============ إدارة التطبيقات ============

/**
 * تحميل التطبيقات من الخادم
 * @param {Object} filters - عوامل التصفية
 * @returns {Promise<Array>}
 */
async function loadApps(filters = {}) {
    try {
        const params = new URLSearchParams();
        params.append('action', 'get_all');
        
        if (filters.category && filters.category !== 'all') {
            params.append('category', filters.category);
        }
        
        if (filters.search) {
            params.append('search', filters.search);
        }
        
        if (filters.page) {
            params.append('page', filters.page);
        }
        
        if (filters.limit) {
            params.append('limit', filters.limit);
        }
        
        const response = await fetch(`${API_CONFIG.APPS_API}?${params.toString()}`);
        const data = await response.json();
        
        if (data.success) {
            return data.data;
        }
        return { apps: [], total: 0, page: 1, limit: 12, pages: 1 };
    } catch (error) {
        console.error('خطأ في تحميل التطبيقات:', error);
        showAlert('حدث خطأ في تحميل التطبيقات', 'error');
        return { apps: [], total: 0, page: 1, limit: 12, pages: 1 };
    }
}

/**
 * عرض التطبيقات في الشبكة
 * @param {Array} apps - مصفوفة التطبيقات
 * @param {string} containerId - معرف الحاوية
 */
function renderAppsGrid(apps, containerId = 'appsGrid') {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    if (!apps || apps.length === 0) {
        container.innerHTML = `
            <div class="no-apps">
                <i class="fas fa-mobile-alt"></i>
                <h3>لا توجد تطبيقات متاحة حالياً</h3>
                <p>سيتم إضافة تطبيقات جديدة قريباً</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = '';
    
    apps.forEach(app => {
        const appCard = createAppCard(app);
        container.appendChild(appCard);
    });
}

/**
 * إنشاء بطاقة تطبيق
 * @param {Object} app - بيانات التطبيق
 * @returns {HTMLElement}
 */
function createAppCard(app) {
    const card = document.createElement('div');
    card.className = 'app-card';
    card.dataset.id = app.id;
    card.dataset.category = app.category;
    
    // تحويل الفئة إلى نص عربي
    const categoryText = getCategoryText(app.category);
    
    // تقليل الوصف إذا كان طويلاً
    let shortDescription = app.description;
    if (shortDescription.length > 100) {
        shortDescription = shortDescription.substring(0, 100) + '...';
    }
    
    // إنشاء البطاقة
    card.innerHTML = `
        <div class="app-image">
            <img src="${app.image_url}" alt="${app.name}" loading="lazy" 
                 onerror="this.src='https://images.unsplash.com/photo-1551650975-87deedd944c3?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80'">
            ${app.is_featured ? '<div class="featured-badge"><i class="fas fa-crown"></i> مميز</div>' : ''}
        </div>
        <div class="app-info">
            <span class="app-category">${categoryText}</span>
            <h3 class="app-title">${app.name}</h3>
            <p class="app-description">${shortDescription}</p>
            <div class="app-meta">
                <div>
                    <span class="app-version">الإصدار ${app.version}</span>
                    <div style="margin-top: 5px; color: #f39c12;">
                        ${createStarRating(app.rating || 4.5)}
                    </div>
                </div>
                <button class="btn btn-primary view-details-btn" data-id="${app.id}">
                    <i class="fas fa-info-circle"></i> التفاصيل
                </button>
            </div>
        </div>
    `;
    
    return card;
}

/**
 * تحويل رمز الفئة إلى نص عربي
 * @param {string} category - رمز الفئة
 * @returns {string}
 */
function getCategoryText(category) {
    const categories = {
        'productivity': 'الإنتاجية',
        'design': 'التصميم',
        'development': 'التطوير',
        'security': 'الأمان',
        'multimedia': 'الوسائط',
        'games': 'الألعاب',
        'utilities': 'الأدوات',
        'office': 'المكتب',
        'education': 'التعليم',
        'entertainment': 'الترفيه',
        'utility': 'الأدوات'
    };
    
    return categories[category] || category;
}

/**
 * تحميل الفئات
 * @returns {Promise<Array>}
 */
async function loadCategories() {
    try {
        const response = await fetch(`${API_CONFIG.APPS_API}?action=get_categories`);
        const data = await response.json();
        
        if (data.success) {
            return data.data;
        }
        return [];
    } catch (error) {
        console.error('خطأ في تحميل الفئات:', error);
        return [];
    }
}

/**
 * إنشاء أزرار التصفية بالفئات
 * @param {string} containerId - معرف الحاوية
 */
async function renderCategoryFilters(containerId = 'categoryFilters') {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    const categories = await loadCategories();
    
    let html = `
        <button class="filter-btn active" data-filter="all">الكل</button>
    `;
    
    categories.forEach(category => {
        const categoryText = getCategoryText(category.category);
        html += `
            <button class="filter-btn" data-filter="${category.category}">
                ${categoryText} <span class="category-count">(${category.count})</span>
            </button>
        `;
    });
    
    container.innerHTML = html;
    
    // إضافة مستمعي الأحداث
    container.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            // إزالة النشاط من جميع الأزرار
            container.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            // إضافة النشاط للزر المضغوط
            this.classList.add('active');
            
            // تنفيذ التصفية
            const filter = this.dataset.filter;
            const searchBox = document.getElementById('searchBox');
            const searchTerm = searchBox ? searchBox.value : '';
            
            loadApps({
                category: filter,
                search: searchTerm
            }).then(data => {
                renderAppsGrid(data.apps);
                updatePagination(data);
            });
        });
    });
}

// ============ التنقل والترقيم ============

/**
 * تحديث الترقيم
 * @param {Object} paginationData - بيانات الترقيم
 */
function updatePagination(paginationData) {
    const paginationContainer = document.getElementById('pagination');
    if (!paginationContainer) return;
    
    const { page, pages, total } = paginationData;
    
    if (pages <= 1) {
        paginationContainer.innerHTML = '';
        return;
    }
    
    let html = '';
    
    // زر الصفحة السابقة
    if (page > 1) {
        html += `<button class="page-link" data-page="${page - 1}"><i class="fas fa-chevron-right"></i> السابق</button>`;
    }
    
    // أرقام الصفحات
    const maxPagesToShow = 5;
    let startPage = Math.max(1, page - Math.floor(maxPagesToShow / 2));
    let endPage = Math.min(pages, startPage + maxPagesToShow - 1);
    
    if (endPage - startPage + 1 < maxPagesToShow) {
        startPage = Math.max(1, endPage - maxPagesToShow + 1);
    }
    
    for (let i = startPage; i <= endPage; i++) {
        if (i === page) {
            html += `<button class="page-link active" data-page="${i}">${i}</button>`;
        } else {
            html += `<button class="page-link" data-page="${i}">${i}</button>`;
        }
    }
    
    // زر الصفحة التالية
    if (page < pages) {
        html += `<button class="page-link" data-page="${page + 1}">التالي <i class="fas fa-chevron-left"></i></button>`;
    }
    
    // معلومات الترقيم
    html += `
        <div class="pagination-info">
            إظهار ${(page - 1) * 12 + 1}-${Math.min(page * 12, total)} من ${total}
        </div>
    `;
    
    paginationContainer.innerHTML = html;
    
    // إضافة مستمعي الأحداث
    paginationContainer.querySelectorAll('.page-link[data-page]').forEach(btn => {
        btn.addEventListener('click', function() {
            const pageNum = parseInt(this.dataset.page);
            const activeFilter = document.querySelector('.filter-btn.active');
            const filter = activeFilter ? activeFilter.dataset.filter : 'all';
            const searchBox = document.getElementById('searchBox');
            const searchTerm = searchBox ? searchBox.value : '';
            
            loadApps({
                category: filter,
                search: searchTerm,
                page: pageNum
            }).then(data => {
                renderAppsGrid(data.apps);
                updatePagination(data);
                
                // التمرير لأعلى الصفحة
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
        });
    });
}

// ============ البحث ============

/**
 * إعداد البحث
 */
function setupSearch() {
    const searchBox = document.getElementById('searchBox');
    if (!searchBox) return;
    
    let searchTimeout;
    
    searchBox.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        
        searchTimeout = setTimeout(() => {
            const searchTerm = this.value.trim();
            const activeFilter = document.querySelector('.filter-btn.active');
            const filter = activeFilter ? activeFilter.dataset.filter : 'all';
            
            loadApps({
                category: filter,
                search: searchTerm
            }).then(data => {
                renderAppsGrid(data.apps);
                updatePagination(data);
            });
        }, 500); // تأخير 500 مللي ثانية
    });
    
    // زر البحث
    const searchButton = document.getElementById('searchButton');
    if (searchButton) {
        searchButton.addEventListener('click', function() {
            const activeFilter = document.querySelector('.filter-btn.active');
            const filter = activeFilter ? activeFilter.dataset.filter : 'all';
            const searchTerm = searchBox.value.trim();
            
            loadApps({
                category: filter,
                search: searchTerm
            }).then(data => {
                renderAppsGrid(data.apps);
                updatePagination(data);
            });
        });
    }
}

// ============ تفاصيل التطبيق ============

/**
 * عرض تفاصيل التطبيق
 * @param {number} appId - معرف التطبيق
 */
async function showAppDetails(appId) {
    try {
        const response = await fetch(`${API_CONFIG.APPS_API}?action=get_by_id&id=${appId}`);
        const data = await response.json();
        
        if (!data.success) {
            showAlert(data.message || 'لم يتم العثور على التطبيق', 'error');
            return;
        }
        
        const app = data.data;
        showAppModal(app);
        
    } catch (error) {
        console.error('خطأ في عرض تفاصيل التطبيق:', error);
        showAlert('حدث خطأ في تحميل تفاصيل التطبيق', 'error');
    }
}

/**
 * عرض نافذة تفاصيل التطبيق
 * @param {Object} app - بيانات التطبيق
 */
function showAppModal(app) {
    // إنشاء النافذة إذا لم تكن موجودة
    let modal = document.getElementById('appDetailModal');
    
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'appDetailModal';
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content">
                <button class="close-modal">&times;</button>
                <div id="appDetailContent"></div>
            </div>
        `;
        document.body.appendChild(modal);
        
        // إضافة حدث الإغلاق
        modal.querySelector('.close-modal').addEventListener('click', () => {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        });
        
        // إغلاق عند النقر خارج النافذة
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        });
    }
    
    // تحضير المحتوى
    const categoryText = getCategoryText(app.category);
    
    const content = `
        <div class="app-detail-container">
            <div class="app-detail-image">
                <img src="${app.image_url}" alt="${app.name}">
            </div>
            <div class="app-detail-info">
                <h1>${app.name}</h1>
                
                <div class="app-meta-details">
                    <div class="meta-item">
                        <i class="fas fa-tag"></i>
                        <span>${categoryText}</span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-code-branch"></i>
                        <span>الإصدار ${app.version}</span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-hdd"></i>
                        <span>${app.size_mb} ميجابايت</span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-download"></i>
                        <span>${app.downloads} تحميل</span>
                    </div>
                </div>
                
                <div class="app-rating">
                    ${createStarRating(app.rating || 4.5)}
                </div>
                
                <div class="app-description-full">
                    <h3>عن التطبيق</h3>
                    <p>${app.description}</p>
                </div>
                
                ${app.developer ? `
                <div class="app-developer">
                    <h3>المطور</h3>
                    <p>${app.developer}</p>
                </div>
                ` : ''}
                
                <div class="app-requirements">
                    <h3>المتطلبات</h3>
                    <p><i class="fas fa-check-circle"></i> ${app.os_requirements || 'Windows 7 أو أحدث'}</p>
                </div>
                
                <div class="download-section">
                    <h3>تحميل التطبيق</h3>
                    <div class="download-info">
                        <div class="file-info">
                            <i class="fas fa-file-archive"></i>
                            <div>
                                <strong>${app.name} - ${app.version}</strong>
                                <span>${app.size_mb} ميجابايت</span>
                            </div>
                        </div>
                        <button class="btn btn-success download-app-btn" data-id="${app.id}">
                            <i class="fas fa-download"></i> تحميل التطبيق
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // تعبئة المحتوى
    modal.querySelector('#appDetailContent').innerHTML = content;
    
    // إضافة حدث التحميل
    const downloadBtn = modal.querySelector('.download-app-btn');
    if (downloadBtn) {
        downloadBtn.addEventListener('click', function() {
            downloadApp(app.id);
        });
    }
    
    // عرض النافذة
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    // إضافة تأثير الظهور
    setTimeout(() => {
        modal.style.opacity = '1';
        modal.querySelector('.modal-content').style.transform = 'scale(1)';
    }, 10);
}

// ============ تحميل التطبيقات ============

/**
 * تحميل تطبيق
 * @param {number} appId - معرف التطبيق
 */
async function downloadApp(appId) {
    try {
        const response = await fetch(`${API_CONFIG.APPS_API}?action=download&id=${appId}`);
        const data = await response.json();
        
        if (data.success) {
            // فتح رابط التحميل
            window.open(data.data.download_url, '_blank');
            
            // عرض رسالة نجاح
            showAlert(`يتم تحميل ${data.data.app_name}...`, 'success');
            
            // تحديث عدد التحميلات في الواجهة
            const downloadCount = document.querySelector(`[data-app-id="${appId}"] .app-downloads`);
            if (downloadCount) {
                const currentCount = parseInt(downloadCount.textContent) || 0;
                downloadCount.textContent = (currentCount + 1).toLocaleString();
            }
        } else {
            showAlert(data.message || 'حدث خطأ في التحميل', 'error');
        }
    } catch (error) {
        console.error('خطأ في تحميل التطبيق:', error);
        showAlert('حدث خطأ في تحميل التطبيق', 'error');
    }
}

// ============ إدارة القائمة المتنقلة ============

/**
 * إعداد القائمة المتنقلة
 */
function setupMobileMenu() {
    const menuToggle = document.getElementById('menuToggle');
    const mainNav = document.getElementById('mainNav');
    
    if (!menuToggle || !mainNav) return;
    
    menuToggle.addEventListener('click', function() {
        mainNav.classList.toggle('active');
        this.classList.toggle('active');
    });
    
    // إغلاق القائمة عند النقر على رابط
    const navLinks = mainNav.querySelectorAll('a');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                mainNav.classList.remove('active');
                menuToggle.classList.remove('active');
            }
        });
    });
}

// ============ التنقل السلس ============

/**
 * إعداد التنقل السلس
 */
function setupSmoothScroll() {
    // روابط التنقل الداخلية
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                const headerHeight = document.querySelector('header')?.offsetHeight || 80;
                const targetPosition = targetElement.offsetTop - headerHeight;
                
                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });
}

// ============ التهيئة ============

/**
 * تهيئة الموقع
 */
async function initWebsite() {
    console.log('🚀 بدء تهيئة متجر تطبيقاتي...');
    
    // التحقق من حالة الدخول
    const isLoggedIn = await checkLoginStatus();
    if (isLoggedIn) {
        console.log('✅ المستخدم مسجل الدخول');
    }
    
    // تطبيق إعدادات الموقع
    await applySiteSettings();
    
    // إعداد القائمة المتنقلة
    setupMobileMenu();
    
    // إعداد التنقل السلس
    setupSmoothScroll();
    
    // إعداد البحث إذا كان متاحاً
    if (document.getElementById('searchBox')) {
        setupSearch();
    }
    
    // إعداد أزرار تفاصيل التطبيقات (Event Delegation)
    document.addEventListener('click', function(e) {
        // زر التفاصيل في بطاقة التطبيق
        if (e.target.closest('.view-details-btn')) {
            const btn = e.target.closest('.view-details-btn');
            const appId = parseInt(btn.dataset.id);
            if (appId) {
                showAppDetails(appId);
            }
        }
        
        // زر التحميل في نافذة التفاصيل
        if (e.target.closest('.download-app-btn')) {
            const btn = e.target.closest('.download-app-btn');
            const appId = parseInt(btn.dataset.id);
            if (appId) {
                downloadApp(appId);
            }
        }
    });
    
    // تحميل التطبيقات إذا كانت الصفحة تحتوي على شبكة تطبيقات
    if (document.getElementById('appsGrid')) {
        console.log('📱 جاري تحميل التطبيقات...');
        
        // تحميل الفئات وعرضها
        if (document.getElementById('categoryFilters')) {
            await renderCategoryFilters();
        }
        
        // تحميل وعرض التطبيقات
        const data = await loadApps();
        renderAppsGrid(data.apps);
        updatePagination(data);
    }
    
    console.log('✅ تم تهيئة الموقع بنجاح');
}

// ============ الاستدعاء التلقائي ============

// تشغيل التهيئة عند تحميل الصفحة
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initWebsite);
} else {
    initWebsite();
}

// ============ التصدير للملفات الأخرى ============

// تصدير الدوال الهامة لاستخدامها في ملفات أخرى
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        showAlert,
        loadApps,
        renderAppsGrid,
        downloadApp,
        checkLoginStatus,
        applySiteSettings
    };
} else {
    // تعريف ككائن عام للاستخدام في المتصفح
    window.AppStore = {
        showAlert,
        loadApps,
        renderAppsGrid,
        downloadApp,
        checkLoginStatus,
        applySiteSettings
    };
}