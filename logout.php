<?php
/**
 * logout.php
 */
require_once __DIR__ . '/core/bootstrap.php';

session_unset();
session_destroy();

// Kill the cookie
$params = session_get_cookie_params();
setcookie(
    session_name(),
    '',
    time() - 42000,
    $params['path'],
    $params['domain'],
    $params['secure'],
    $params['httponly']
);

header('Location: login.php');
exit;
