<?php

declare(strict_types=1);

require __DIR__ . '/../inc/config.php';
require PATH_INC . '/db.php';

@header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

function post_str(string $name, string $default = ''): string
{
    return trim($_POST[$name] ?? $default);
}
function br_money_to_decimal(?string $v): ?string
{
    if ($v === null) return null;
    $v = trim($v);
    if ($v === '') return null;
    // remove R$, espaços, separador de milhar e troca vírgula por ponto
    $v = str_replace(['R$', ' '], '', $v);
    $v = str_replace('.', '', $v);
    $v = str_replace(',', '.', $v);
    return is_numeric($v) ? $v : null;
}

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
}

$acao = $_GET['acao'] ?? ($_POST['acao'] ?? '');

// CSRF (se existir no projeto)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && function_exists('csrf_check')) {
    $tok = $_POST['csrf'] ?? null;
    if ($tok !== null && !csrf_check($tok)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'CSRF inválido.']);
        exit;
    }
}

try {
    if ($acao === 'obter' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            exit;
        }
        $st = $pdo->prepare("SELECT COM_CODIGO_PK, COM_DESCRICAO, COM_VALOR FROM tb_combo WHERE COM_CODIGO_PK = ? LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Combo não encontrado.']);
            exit;
        }
        echo json_encode(['success' => true, 'combo' => $row]);
        exit;
    }

    if ($acao === 'cadastrar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $descricao = post_str('descricao');
        $valorBR   = post_str('valor');
        $valor     = br_money_to_decimal($valorBR);

        if ($descricao === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Descrição é obrigatória.']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO tb_combo (COM_DESCRICAO, COM_VALOR) VALUES (?,?)");
        $stmt->execute([$descricao, $valor]);

        echo json_encode([
            'success' => true,
            'id'     => (int)$pdo->lastInsertId(),
            'message' => 'Combo cadastrado com sucesso'
        ]);
        exit;
    }

    if ($acao === 'editar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id        = (int)($_POST['id'] ?? 0);
        $descricao = post_str('descricao');
        $valor     = br_money_to_decimal(post_str('valor'));

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

        $stmt = $pdo->prepare("UPDATE tb_combo SET COM_DESCRICAO = ?, COM_VALOR = ? WHERE COM_CODIGO_PK = ?");
        $stmt->execute([$descricao, $valor, $id]);

        echo json_encode(['success' => true, 'message' => 'Combo atualizado com sucesso']);
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
            $stmt = $pdo->prepare("DELETE FROM tb_combo WHERE COM_CODIGO_PK = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Combo excluído com sucesso']);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'Não é possível excluir: combo vinculado a outros registros.']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Erro ao excluir combo.']);
            }
        }
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ação inválida']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Falha no servidor.']);
}
