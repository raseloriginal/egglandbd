<?php
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_HOST'] = 'localhost';
session_start();
$_SESSION['user_id'] = 2;
$_SESSION['role'] = 'agent';
$_SESSION['agent_id'] = 2;
$_SESSION['status'] = 'active';
