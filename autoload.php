<?php
/**
 * Simple Autoloader
 * Automatically loads core, models, controllers, middleware, services, helpers
 */

// Önce Composer Autoload (PHPMailer vb. kütüphaneler için)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

spl_autoload_register(function ($class) {
    // Sınıf adından namespace'i çıkar (Örn: App\Services\EmailService -> EmailService)
    $className = basename(str_replace('\\', '/', $class));

    $directories = [
        __DIR__ . '/core/',
        __DIR__ . '/models/',
        __DIR__ . '/controllers/',
        __DIR__ . '/middleware/',
        __DIR__ . '/services/',
        __DIR__ . '/helpers/'
    ];

    foreach ($directories as $directory) {
        $file = $directory . $className . '.php';
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
    }

    return false;
});

// Load environment
require_once __DIR__ . '/config/environment.php';
Environment::load();

// Initialize core components
Cache::init();
Session::start();

// Load helpers
require_once __DIR__ . '/includes/image-helpers.php';

// Load global site settings for white-labeling
global $siteSettings;
try {
    $db = Database::getInstance();
    $siteSettings = $db->query("SELECT * FROM settings WHERE id = 1")->fetch();
    if (!$siteSettings) {
        $db->query("INSERT INTO settings (site_title, default_city) VALUES ('Proje Adı', 'Şehir')");
        $siteSettings = $db->query("SELECT * FROM settings WHERE id = 1")->fetch();
    }
} catch (Exception $e) {
    $siteSettings = [
        'site_title' => 'Rehber Medya',
        'default_city' => 'Şehir',
        'default_seo_title' => '',
        'default_seo_desc' => ''
    ];
}
