<?php
// /app/logout/logout.php
declare(strict_types=1);

// Carrega as constantes (BASE_URL, LOGIN_URL, etc.)
require_once __DIR__ . '/../../inc/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Limpa variáveis de sessão
$_SESSION = [];

// Remove o cookie da sessão (se existir)
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

// Destroi a sessão
session_destroy();

// Evita cache no back
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Redireciona para a tela de login (absoluto)
$target = (defined('LOGIN_URL') ? rtrim(LOGIN_URL, '/') : rtrim(BASE_URL, '/') . '/login') . '/';
header('Location: ' . $target, true, 302);
exit;
