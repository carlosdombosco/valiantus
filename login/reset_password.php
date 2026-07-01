<?php
// /valiantus/login/reset_password.php
require __DIR__ . '/../inc/db.php';

$email = 'carlosdombosco@gmail.com';     // coloque o e-mail cadastrado
$nova  = '123';                // ou troque pelo que quiser

$hash = password_hash($nova, PASSWORD_DEFAULT);

$up = $pdo->prepare("UPDATE tb_usuario SET USU_SENHA = :h WHERE USU_EMAIL = :e");
$up->execute([':h' => $hash, ':e' => $email]);

echo $up->rowCount() ? "Senha atualizada para password_hash()." : "Usuário não encontrado.";






// reset_password.php
require __DIR__ . '/../inc/db.php';

$email = 'carlosdombosco@gmail.com';
$nova  = '123';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("Email inválido.");
}

$hash = password_hash($nova, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("UPDATE tb_usuario SET USU_SENHA = :h WHERE USU_EMAIL = :e");
$stmt->execute([':h' => $hash, ':e' => $email]);

echo $stmt->rowCount() ? "Senha atualizada com sucesso." : "Usuário não encontrado ou erro na atualização.";
