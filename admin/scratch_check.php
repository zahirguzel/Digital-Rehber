<?php
require 'c:/xampp/htdocs/digitalrehber/autoload.php';
$db = Database::getInstance()->getPDO();
$settings = $db->query("SELECT * FROM settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
print_r($settings);
