<?php

$isWindows = true; // Asumimos que PHP se está ejecutando en Windows por defecto.

if (strcasecmp(substr(PHP_OS, 0, 3), 'WIN') == 0) {
    $isWindows = true;
}

global $_GLOBALS;

if (!$_GLOBALS) {
    $_GLOBALS = [];
}

if (!isset($_GLOBALS["DataAccessManager_DB_CONFIG"])) {
    $_GLOBALS["DataAccessManager_DB_CONFIG"] = [];
}

$_GLOBALS["RUN_CREATE_TABLE"] = false;

// Configuraciones de correo electrónico para pruebas
$applyEmailConfig = false; // Desactivar configuraciones de correo para pruebas

if ($applyEmailConfig) {
    $_GLOBALS["EMAIL_QUEUE_USER"]       = "apikey";
    $_GLOBALS["EMAIL_QUEUE_PASSWORD"]   = "Disparate";
    $_GLOBALS["EMAIL_QUEUE_PORT"]       = 465;
    $_GLOBALS["EMAIL_QUEUE_SMTP_HOST"]  = "smtp.net";
    $_GLOBALS["EMAIL_QUEUE_SEND_FROM"]  = "Reserva@stonewood.com.do";
}

// Configuraciones de la base de datos para pruebas
$_GLOBALS['DB_HOST'] = 'localhost';
$_GLOBALS['DB_NAME'] = ':memory:'; // Usar base de datos en memoria para pruebas
$_GLOBALS['DB_USER'] = 'root';
$_GLOBALS['DB_PASS'] = '';
$_GLOBALS['DB_PORT'] = '3306';

// Configuración para MySQL
$_GLOBALS["DataAccessManager_DB_CONFIG"]["default"] = [
    "connectionString"  => "sqlite::memory:", // Cambiar a SQLite en memoria para pruebas
    "userName"          => $_GLOBALS['DB_USER'],
    "password"          => $_GLOBALS['DB_PASS'],
    "connectionOptions" => [
        PDO::ATTR_PERSISTENT => false, // No usar conexiones persistentes en pruebas
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ],
];

// Asegúrate de devolver un array al final del archivo
return $_GLOBALS;