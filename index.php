<?php
/**
 * RMS -- Home / entry point
 */
require_once __DIR__ . '/includes/init.php';

if (empty($_SESSION['user_id'])) {
    redirect(url('auth/login.php'));
}
$role = $_SESSION['role'];
redirect(url(($role === 'student' ? 'student' : ($role === 'teacher' ? 'teacher' : 'admin')) . '/index.php'));