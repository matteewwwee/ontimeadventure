<?php
/**
 * Logout — On Time Adventure
 * Destroys the current session and redirects to login.
 */

session_start();
session_unset();
session_destroy();

$base_url = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false || strpos($_SERVER['HTTP_HOST'], 'ngrok') !== false) ? '/ontimeadventure/' : '/';

header('Location: ' . $base_url . 'login.php');
exit;
