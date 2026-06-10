<?php
/**
 * Logout — On Time Adventure
 * Destroys the current session and redirects to login.
 */

session_start();
session_unset();
session_destroy();

header('Location: /ontimeadventure/login.php');
exit;
