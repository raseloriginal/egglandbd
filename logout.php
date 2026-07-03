<?php
require_once __DIR__ . '/config/auth.php';
logoutUser();
header('Location: /egglandbangladesh/index.php');
exit;
