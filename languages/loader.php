<?php
// languages/loader.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$default_lang = 'en';
$available_langs = ['en', 'ku'];

if (isset($_GET['lang']) && in_array($_GET['lang'], $available_langs)) {
    $_SESSION['lang'] = $_GET['lang'];
    setcookie('lang', $_GET['lang'], time() + (86400 * 30), '/');
} elseif (isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], $available_langs)) {
    $_SESSION['lang'] = $_COOKIE['lang'];
} elseif (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = $default_lang;
}

$current_lang = $_SESSION['lang'];
$translations = require __DIR__ . '/' . $current_lang . '.php';

function __($key) {
    global $translations;
    return $translations[$key] ?? $key;
}
?>