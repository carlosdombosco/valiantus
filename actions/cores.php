<?php

declare(strict_types=1);

require __DIR__ . '/../inc/config.php';
require PATH_INC . '/db.php';

@header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

function post_str_cor(string $name, string $default = ''): string
{
    return trim($_POST[$name] ?? $default);
}

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
}

$acao = $_GET['acao'] ?? ($_POST['acao'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && function_exists('csrf_check')) {
    $tok = $_POST['csrf'] ?? null;
    if ($tok !== null && !csrf_check($tok)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'CSRF inválido.']);
        exit;
    }
}

try {
    if ($acao === 'cadastrar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $descricao = post_str_cor('descricao');

        if ($descricao === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Descrição é obrigatória.']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO tb_cor (COR_DESCRICAO) VALUES (?)");
        $stmt->execute([$descricao]);

        echo json_encode([
            'success' => true,
            'id'      => (int)$pdo->lastInsertId(),
            'message' => 'Cor cadastrada com sucesso.'
        ]);
        exit;
    }

    if ($acao === 'editar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id        = (int)($_POST['id'] ?? 0);
        $descricao = post_str_cor('descricao');

        if ($id <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            exit;
        }
        if ($descricao === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Descrição é obrigatória.']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE tb_cor SET COR_DESCRICAO = ? WHERE COR_CODIGO_PK = ?");
        $stmt->execute([$descricao, $id]);

        echo json_encode(['success' => true, 'message' => 'Cor atualizada com sucesso.']);
        exit;
    }

    if ($acao === 'excluir' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM tb_cor WHERE COR_CODIGO_PK = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Cor excluída com sucesso.']);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'Não é possível excluir: cor vinculada a veículos cadastrados.']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Erro ao excluir cor.']);
            }
        }
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Falha no servidor.']);
}
