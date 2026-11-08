<?php
require_once __DIR__ . '/includes/init.php';

session_unset();
session_destroy();

header('Location: ' . BASE_URL . '/login.php?logged_out=1');
exit;
