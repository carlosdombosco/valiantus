<?php
declare(strict_types=1);

require __DIR__ . '/../inc/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require PATH_INC . '/db.php';
require PATH_INC . '/csrf.php';

@header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

// Garante tabela
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `tb_usuario` (
        `USU_CODIGO_PK` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `USU_NOME`      VARCHAR(150) NOT NULL DEFAULT '',
        `USU_EMAIL`     VARCHAR(150) NOT NULL DEFAULT '',
        `USU_SENHA`     VARCHAR(255) NOT NULL DEFAULT '',
        `USU_TIPO`      VARCHAR(20)  NOT NULL DEFAULT 'USUARIO',
        `USU_ATIVO`     TINYINT(1)   NOT NULL DEFAULT 1,
        `USU_CRIADO_EM` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`USU_CODIGO_PK`),
        UNIQUE KEY `idx_usu_email` (`USU_EMAIL`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {}

$acao = $_GET['acao'] ?? ($_POST['acao'] ?? '');

/* ── CSRF para POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tok = $_POST['csrf'] ?? null;
    if ($tok !== null && function_exists('csrf_check') && !csrf_check($tok)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'CSRF inválido.']);
        exit;
    }
}

/* ── GET: obter usuário ── */
if ($acao === 'obter' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'ID inválido.']); exit; }
    $row = $pdo->prepare("SELECT USU_CODIGO_PK, USU_NOME, USU_EMAIL, USU_TIPO, USU_ATIVO FROM tb_usuario WHERE USU_CODIGO_PK = ?");
    $row->execute([$id]);
    $data = $row->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['success' => (bool)$data, 'data' => $data ?: null], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── POST: cadastrar ── */
if ($acao === 'cadastrar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nome  = trim($_POST['nome']  ?? '');
        $email = trim(strtolower($_POST['email'] ?? ''));
        $senha = $_POST['senha'] ?? '';
        $tipo  = in_array($_POST['tipo'] ?? '', ['ADMIN', 'USUARIO']) ? $_POST['tipo'] : 'USUARIO';
        $ativo = (int)(($_POST['ativo'] ?? '1') === '1');

        if (!$nome || !$email || !$senha) {
            echo json_encode(['success' => false, 'message' => 'Nome, e-mail e senha são obrigatórios.']);
            exit;
        }
        if (strlen($senha) < 6) {
            echo json_encode(['success' => false, 'message' => 'A senha deve ter no mínimo 6 caracteres.']);
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'E-mail inválido.']);
            exit;
        }

        // Verifica duplicidade
        $ck = $pdo->prepare("SELECT USU_CODIGO_PK FROM tb_usuario WHERE USU_EMAIL = ?");
        $ck->execute([$email]);
        if ($ck->fetchColumn()) {
            echo json_encode(['success' => false, 'message' => 'Já existe um usuário com este e-mail.']);
            exit;
        }

        $hash = password_hash($senha, PASSWORD_BCRYPT);
        $pdo->prepare("INSERT INTO tb_usuario (USU_NOME, USU_EMAIL, USU_SENHA, USU_TIPO, USU_ATIVO) VALUES (?, ?, ?, ?, ?)")
            ->execute([$nome, $email, $hash, $tipo, $ativo]);

        echo json_encode(['success' => true, 'message' => 'Usuário cadastrado com sucesso.'], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Não foi possível cadastrar.', 'debug' => $e->getMessage()]);
    }
    exit;
}

/* ── POST: editar ── */
if ($acao === 'editar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id    = (int)($_POST['id'] ?? 0);
        $nome  = trim($_POST['nome']  ?? '');
        $email = trim(strtolower($_POST['email'] ?? ''));
        $senha = $_POST['senha'] ?? '';
        $tipo  = in_array($_POST['tipo'] ?? '', ['ADMIN', 'USUARIO']) ? $_POST['tipo'] : 'USUARIO';
        $ativo = (int)(($_POST['ativo'] ?? '1') === '1');

        if (!$id || !$nome || !$email) {
            echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
            exit;
        }
        if ($senha && strlen($senha) < 6) {
            echo json_encode(['success' => false, 'message' => 'A senha deve ter no mínimo 6 caracteres.']);
            exit;
        }

        // Verifica duplicidade (excluindo o próprio)
        $ck = $pdo->prepare("SELECT USU_CODIGO_PK FROM tb_usuario WHERE USU_EMAIL = ? AND USU_CODIGO_PK <> ?");
        $ck->execute([$email, $id]);
        if ($ck->fetchColumn()) {
            echo json_encode(['success' => false, 'message' => 'Outro usuário já usa este e-mail.']);
            exit;
        }

        if ($senha) {
            $hash = password_hash($senha, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE tb_usuario SET USU_NOME=?, USU_EMAIL=?, USU_SENHA=?, USU_TIPO=?, USU_ATIVO=? WHERE USU_CODIGO_PK=?")
                ->execute([$nome, $email, $hash, $tipo, $ativo, $id]);
        } else {
            $pdo->prepare("UPDATE tb_usuario SET USU_NOME=?, USU_EMAIL=?, USU_TIPO=?, USU_ATIVO=? WHERE USU_CODIGO_PK=?")
                ->execute([$nome, $email, $tipo, $ativo, $id]);
        }

        echo json_encode(['success' => true, 'message' => 'Usuário atualizado com sucesso.'], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Não foi possível salvar.', 'debug' => $e->getMessage()]);
    }
    exit;
}

/* ── POST: excluir ── */
if ($acao === 'excluir' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'message' => 'ID inválido.']); exit; }

        // Impede excluir o próprio usuário logado
        if ((int)($_SESSION['SessUsuCodigo'] ?? 0) === $id) {
            echo json_encode(['success' => false, 'message' => 'Você não pode excluir o seu próprio usuário.']);
            exit;
        }

        $pdo->prepare("DELETE FROM tb_usuario WHERE USU_CODIGO_PK = ?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Usuário excluído.'], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Não foi possível excluir.', 'debug' => $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => true, 'message' => 'Ação inválida.']);
