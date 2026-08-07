<?php
require_once 'autoload.php';
try {
    $e = new App\Services\EmailService();
    echo 'OK';
} catch (Throwable $e) {
    echo 'ERR: ' . $e->getMessage();
}
