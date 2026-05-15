<?php
require_once __DIR__ . '/../config/config.php';
adminLogout();
header('Location: index.php');
exit;
