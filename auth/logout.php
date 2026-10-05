<?php
require_once __DIR__ . '/../includes/init.php';
perform_logout();
redirect(url('auth/login.php'));