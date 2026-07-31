<?php
require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/includes/seo-meta.php';

Session::start();

if (Session::get('user_logged_in') === true) {
    Logger::info('User logged out', ['user_id' => Session::get('user_id')]);
}

Session::destroy();

header('Location: ' . rtrim(seoGetBaseUrl(), '/') . '/');
exit;
