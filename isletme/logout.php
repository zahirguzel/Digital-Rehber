<?php
require_once __DIR__ . '/../autoload.php';
Session::start();
Session::destroy();
header('Location: login.php');
exit;
