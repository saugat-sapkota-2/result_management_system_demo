<?php
/**
 * RMS -- Bootstrap include for every page
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/bs_date.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

start_session();