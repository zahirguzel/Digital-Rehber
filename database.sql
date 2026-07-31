-- Yerel İşletme Rehberi — Temiz veritabanı kurulumu
-- Sadece tablo yapısı + minimum başlangıç ayarları içerir.
-- İşletme, blog, influencer, etkinlik, ilçe sayfası veya demo içerik YOKTUR.
-- phpMyAdmin → Yeni veritabanı oluştur → İçe Aktar (Import) ile yükleyin.

-- 1. Categories Table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NULL UNIQUE,
    icon VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Businesses Table
CREATE TABLE IF NOT EXISTS businesses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    city VARCHAR(100) NOT NULL DEFAULT '',
    district VARCHAR(100) NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    address TEXT,
    phone VARCHAR(50),
    whatsapp VARCHAR(50),
    google_maps_iframe TEXT,
    instagram VARCHAR(255) NULL,
    tiktok VARCHAR(255) NULL,
    facebook VARCHAR(255) NULL,
    menu_url VARCHAR(255) NULL,
    website VARCHAR(255) NULL,
    yemeksepeti VARCHAR(255) NULL,
    theme_color VARCHAR(7) DEFAULT '#1e3932',
    logo_path VARCHAR(255),
    cover_image_path VARCHAR(255),
    is_premium TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Business Images Table
CREATE TABLE IF NOT EXISTS business_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT,
    image_path VARCHAR(255) NOT NULL,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Advertisements (Ads) Table
CREATE TABLE IF NOT EXISTS advertisements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255),
    image_path VARCHAR(255) NOT NULL,
    target_url VARCHAR(255),
    position VARCHAR(50) DEFAULT 'home_banner',
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Admins Table
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('superadmin','admin','editor') NOT NULL DEFAULT 'admin',
    last_login TIMESTAMP NULL DEFAULT NULL,
    two_factor_secret  VARCHAR(32)  NULL DEFAULT NULL,
    two_factor_enabled TINYINT(1)   NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6a. Menu Categories
CREATE TABLE IF NOT EXISTS menu_categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    name        VARCHAR(100) NOT NULL,
    sort_order  INT DEFAULT 0,
    is_active   TINYINT(1) DEFAULT 1,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6b. Menu Items
CREATE TABLE IF NOT EXISTS menu_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    business_id INT NOT NULL,
    name        VARCHAR(255) NOT NULL,
    description TEXT NULL,
    price       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    image_path  VARCHAR(255) NULL,
    is_active   TINYINT(1) DEFAULT 1,
    sort_order  INT DEFAULT 0,
    FOREIGN KEY (category_id) REFERENCES menu_categories(id) ON DELETE CASCADE,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6c. Business Users (işletme paneli girişi)
CREATE TABLE IF NOT EXISTS business_users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL UNIQUE,
    username    VARCHAR(100) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin Activity Logs Table
CREATE TABLE IF NOT EXISTS admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NULL,
    admin_username VARCHAR(100) NOT NULL,
    action VARCHAR(30) NOT NULL COMMENT 'login,logout,create,update,delete',
    module VARCHAR(80) NOT NULL COMMENT 'businesses,categories,ads,blogs,settings,admins...',
    target_name VARCHAR(255) NULL,
    target_id INT NULL,
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings Table
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_title VARCHAR(255) NULL,
    default_city VARCHAR(150) NULL,
    default_seo_title VARCHAR(255) NULL,
    default_seo_desc TEXT NULL,
    site_description TEXT NULL,
    site_keywords TEXT NULL,
    site_logo VARCHAR(255) NULL,
    home_hero_video VARCHAR(255) NULL,
    home_hero_poster VARCHAR(255) NULL,
    home_hero_title VARCHAR(255) NULL,
    home_hero_subtitle VARCHAR(255) NULL,
    home_hero_description TEXT NULL,
    home_hero_primary_text VARCHAR(120) NULL,
    home_hero_primary_url VARCHAR(255) NULL,
    home_hero_secondary_text VARCHAR(120) NULL,
    home_hero_secondary_url VARCHAR(255) NULL,
    home_hero_consumer_text VARCHAR(255) NULL,
    home_hero_consumer_link_text VARCHAR(120) NULL,
    home_search_label VARCHAR(160) NULL,
    home_services_title VARCHAR(160) NULL,
    home_services_desc TEXT NULL,
    home_influencer_title VARCHAR(160) NULL,
    home_influencer_desc TEXT NULL,
    home_events_title VARCHAR(160) NULL,
    home_events_desc TEXT NULL,
    home_blog_title VARCHAR(160) NULL,
    home_blog_desc TEXT NULL,
    home_banner_fallback_title VARCHAR(160) NULL,
    home_banner_fallback_description TEXT NULL,
    contact_email VARCHAR(255) NULL,
    contact_phone VARCHAR(255) NULL,
    contact_whatsapp VARCHAR(255) NULL,
    contact_address TEXT NULL,
    social_instagram VARCHAR(255) NULL,
    social_facebook VARCHAR(255) NULL,
    social_tiktok VARCHAR(255) NULL,
    social_youtube VARCHAR(255) NULL,
    google_analytics TEXT NULL,
    eczane_api_key VARCHAR(255) NULL,
    duty_pharmacy_last_sync DATETIME NULL,
    contact_captcha TINYINT(1) DEFAULT 1,
    admin_primary_color VARCHAR(20) NOT NULL DEFAULT '#D62828',
    telegram_enabled TINYINT(1) NOT NULL DEFAULT 0,
    telegram_bot_token VARCHAR(255) NULL,
    telegram_chat_id_1 VARCHAR(64) NULL,
    telegram_chat_id_2 VARCHAR(64) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nöbetçi Eczane Cache (EczaneAPI — Türkiye; isteğe bağlı modül)
CREATE TABLE IF NOT EXISTS duty_pharmacies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    external_id VARCHAR(64) NULL,
    duty_date DATE NOT NULL,
    name VARCHAR(255) NOT NULL,
    address TEXT NULL,
    phone VARCHAR(50) NULL,
    district VARCHAR(100) NOT NULL,
    district_slug VARCHAR(100) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_duty_date (duty_date),
    INDEX idx_district_date (district, duty_date),
    INDEX idx_district_slug_date (district_slug, duty_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS duty_pharmacy_sync_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    duty_date DATE NOT NULL,
    status ENUM('success', 'error') NOT NULL DEFAULT 'success',
    pharmacy_count INT NOT NULL DEFAULT 0,
    message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contact Messages Table
CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NULL,
    subject VARCHAR(255) NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Blogs Table
CREATE TABLE IF NOT EXISTS blogs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    summary TEXT NULL,
    content LONGTEXT NOT NULL,
    image_path VARCHAR(255) NULL,
    meta_description VARCHAR(255) NULL,
    meta_keywords VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Services Table (hizmetlerimiz sayfası)
CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    icon VARCHAR(255) NOT NULL DEFAULT 'fa-solid fa-cube',
    description TEXT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Influencers Module
CREATE TABLE IF NOT EXISTS influencers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    city VARCHAR(100) NOT NULL DEFAULT '',
    district VARCHAR(100) NOT NULL,
    niche VARCHAR(50) NOT NULL DEFAULT 'diger',
    bio TEXT NULL,
    collaboration_types VARCHAR(500) NULL,
    instagram VARCHAR(255) NULL,
    tiktok VARCHAR(255) NULL,
    youtube VARCHAR(255) NULL,
    follower_instagram INT NULL DEFAULT NULL,
    follower_tiktok INT NULL DEFAULT NULL,
    follower_youtube INT NULL DEFAULT NULL,
    followers_verified_at DATE NULL,
    followers_verified_by VARCHAR(100) NULL,
    avatar_path VARCHAR(255) NULL,
    cover_path VARCHAR(255) NULL,
    featured_links TEXT NULL,
    contact_email VARCHAR(255) NULL,
    theme_color VARCHAR(7) DEFAULT '#1e3932',
    is_premium TINYINT(1) NOT NULL DEFAULT 0,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    consent_given TINYINT(1) NOT NULL DEFAULT 0,
    consent_date DATE NULL,
    meta_description VARCHAR(255) NULL,
    meta_keywords VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS influencer_business_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    influencer_id INT NOT NULL,
    business_id INT NOT NULL,
    UNIQUE KEY uniq_influencer_business (influencer_id, business_id),
    FOREIGN KEY (influencer_id) REFERENCES influencers(id) ON DELETE CASCADE,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS influencer_collaboration_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    influencer_id INT NOT NULL,
    business_name VARCHAR(255) NOT NULL,
    contact_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NULL,
    collab_type VARCHAR(50) NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (influencer_id) REFERENCES influencers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS influencer_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NULL,
    district VARCHAR(100) NOT NULL,
    niche VARCHAR(50) NOT NULL,
    instagram VARCHAR(255) NULL,
    tiktok VARCHAR(255) NULL,
    youtube VARCHAR(255) NULL,
    bio TEXT NULL,
    consent_profile TINYINT(1) NOT NULL DEFAULT 0,
    consent_kvkk TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS influencer_removal_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    influencer_id INT NULL,
    profile_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    request_type ENUM('removal','correction') NOT NULL DEFAULT 'removal',
    reason TEXT NOT NULL,
    status ENUM('pending','processed') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (influencer_id) REFERENCES influencers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Events Module
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    city VARCHAR(100) NOT NULL DEFAULT '',
    district VARCHAR(100) NOT NULL,
    venue_name VARCHAR(255) NULL,
    address TEXT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    start_time TIME NULL,
    end_time TIME NULL,
    category VARCHAR(50) NOT NULL DEFAULT 'diger',
    description TEXT NULL,
    cover_image_path VARCHAR(255) NULL,
    ticket_url VARCHAR(255) NULL,
    ticket_price VARCHAR(100) NULL,
    organizer VARCHAR(255) NULL,
    contact_phone VARCHAR(50) NULL,
    contact_email VARCHAR(255) NULL,
    google_maps_url TEXT NULL,
    is_featured TINYINT(1) DEFAULT 0,
    is_published TINYINT(1) DEFAULT 0,
    meta_description VARCHAR(255) NULL,
    meta_keywords VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_business_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    business_id INT NOT NULL,
    UNIQUE KEY uniq_event_business (event_id, business_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contact_name VARCHAR(255) NOT NULL,
    contact_email VARCHAR(255) NOT NULL,
    contact_phone VARCHAR(50) NULL,
    title VARCHAR(255) NOT NULL,
    district VARCHAR(100) NOT NULL,
    venue_name VARCHAR(255) NULL,
    address TEXT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    start_time TIME NULL,
    end_time TIME NULL,
    category VARCHAR(50) NOT NULL DEFAULT 'diger',
    description TEXT NULL,
    ticket_url VARCHAR(255) NULL,
    ticket_price VARCHAR(100) NULL,
    organizer VARCHAR(255) NULL,
    cover_image_url VARCHAR(255) NULL,
    notes TEXT NULL,
    consent_kvkk TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_notes TEXT NULL,
    event_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    business_id INT NULL,
    district VARCHAR(100) NOT NULL DEFAULT '',
    campaign_type VARCHAR(50) NOT NULL DEFAULT 'indirim',
    summary VARCHAR(500) NULL,
    description TEXT NULL,
    discount_label VARCHAR(120) NULL,
    original_price VARCHAR(100) NULL,
    sale_price VARCHAR(100) NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    cover_image_path VARCHAR(255) NULL,
    cta_url VARCHAR(255) NULL,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    meta_description VARCHAR(255) NULL,
    meta_keywords VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_campaign_published (is_published, start_date, end_date),
    INDEX idx_campaign_business (business_id),
    INDEX idx_campaign_district (district),
    CONSTRAINT fk_campaigns_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS district_pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    district_name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    tagline VARCHAR(255) NOT NULL DEFAULT '',
    intro TEXT NOT NULL,
    highlights TEXT NULL,
    blog_slug VARCHAR(120) NULL,
    faqs_json TEXT NULL,
    meta_description TEXT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_district_name (district_name),
    UNIQUE KEY uniq_district_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Minimum başlangıç verileri
-- --------------------------------------------------------

INSERT INTO categories (name, slug, icon) VALUES
('Yeme-İçme', 'yeme-icme', 'fa-utensils'),
('Otomotiv & Sanayi', 'otomotiv-sanayi', 'fa-car'),
('Giyim & Alışveriş', 'giyim-alisveris', 'fa-shirt'),
('Sağlık & Medikal', 'saglik-medikal', 'fa-heart-pulse'),
('İnşaat & Ev Dekorasyonu', 'insaat-ev-dekorasyonu', 'fa-hammer'),
('Hizmet & Danışmanlık', 'hizmet-danismanlik', 'fa-handshake'),
('Turizm & Otel', 'turizm-otel', 'fa-hotel'),
('Eğitim', 'egitim', 'fa-graduation-cap'),
('Finans & Danışmanlık', 'finans-danismanlik', 'fa-chart-line');

INSERT INTO admins (username, password, role) VALUES
('admin', '$2y$10$NYM8LN1us6uLCNf9oJDRpumrR8WLPmfc9dhYpAqrLYyhqzXKwtpLW', 'superadmin');

INSERT INTO settings (
    site_title,
    default_city,
    default_seo_title,
    default_seo_desc,
    site_description,
    site_keywords,
    home_hero_title,
    home_hero_subtitle,
    home_hero_description,
    home_hero_primary_text,
    home_hero_primary_url,
    home_hero_secondary_text,
    home_hero_secondary_url,
    home_hero_consumer_text,
    home_hero_consumer_link_text,
    home_search_label,
    home_services_title,
    home_services_desc,
    home_influencer_title,
    home_influencer_desc,
    home_events_title,
    home_events_desc,
    home_blog_title,
    home_blog_desc,
    home_banner_fallback_title,
    home_banner_fallback_description,
    contact_email,
    contact_phone,
    contact_whatsapp,
    contact_address,
    contact_captcha,
    admin_primary_color
) VALUES (
    'Yerel İşletme Rehberi',
    'Şehir',
    'Şehir Rehberi | Dijital İşletme Rehberi',
    'Bölgedeki işletmeleri, hizmetleri ve etkinlikleri keşfedin.',
    'Bölgenizdeki işletmeleri, hizmetleri ve etkinlikleri keşfedin.',
    'yerel rehber, işletme rehberi, esnaf, dijital menü, qr kartvizit',
    'QR Kod ve Dijital Kartvizitiniz <em>Görünsün mü?</em>',
    'Esnaf & İşletmelere Dijital Çözüm',
    'Şehir Rehberi ile işletmenizi dijital vitrine taşıyın. Müşterileriniz QR kodu okutarak menünüze, telefonunuza, WhatsApp ve konumunuza saniyeler içinde ulaşsın.',
    'İşletmemi Eklet',
    '/iletisim?subject=Yeni%20%C4%B0%C5%9Fletme%20Kayd%C4%B1',
    'Hizmetleri İncele',
    '/hizmetlerimiz',
    'Bölgedeki işletmeleri arıyorsanız',
    'rehberde keşfedin',
    'Şehir Rehber Arama',
    'İşletmenizi Dijital Dünyada Büyütelim',
    'Google Harita kaydından QR menüye, sosyal medyadan premium vitrine — uçtan uca dijital çözümler sunuyoruz.',
    'Şehir İçerik Üreticileri',
    'Doğrulanmış profiller · Manuel takipçi onayı · KVKK uyumlu',
    'Yaklaşan Şehir Etkinlikleri',
    'Konser, festival, spor ve kültür programları',
    'Şehir Rehberi Yazıları',
    '',
    'Buraya Reklam Verebilirsiniz',
    'Bölgenin dijital rehberinde yerinizi almak ve detaylı bilgi için tıklayın.',
    'info@example.com',
    '',
    '',
    '',
    1,
    '#D62828'
);

INSERT INTO services (title, slug, icon, description, subject) VALUES
('Google Harita Kaydı', 'google-harita', 'fa-solid fa-map-location-dot',
 'İşletmenizin Google Haritalar ve diğer harita servislerinde doğru adres, telefon ve çalışma saatleriyle listelenmesini sağlarız.',
 'Google Haritalar Kurulumu'),
('Sosyal Medya Yönetimi', 'sosyal-medya', 'fa-solid fa-share-nodes',
 'Instagram, Facebook ve TikTok hesaplarınız için içerik planı, tasarım ve reklam yönetimi sunarız.',
 'Sosyal Medya Yönetimi'),
('Görsel & AI Tasarım', 'gorsel-ai', 'fa-solid fa-wand-magic-sparkles',
 'Sosyal medya afişleri, ürün görselleri ve kampanya tasarımları için yapay zeka destekli görsel üretimi.',
 'Yapay Zeka Görsel Üretimi'),
('QR Menü & Dijital Kartvizit', 'qr-menu', 'fa-solid fa-qrcode',
 'Restoran ve işletmeler için temassız QR menü ve dijital kartvizit çözümleri.',
 'Dijital Kartvizit & QR Menü'),
('Özel Web Tasarımı', 'web-tasarim', 'fa-solid fa-code',
 'Mobil uyumlu, hızlı ve SEO dostu kurumsal web siteleri.',
 'Web Tasarımı'),
('Premium Rehber Vitrini', 'esnaf-vitrini', 'fa-solid fa-crown',
 'Rehber portalında premium listeleme, öne çıkan vitrin ve QR entegrasyonu.',
 'Reklam ve Sponsorluk');
