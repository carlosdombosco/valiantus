<?php
declare(strict_types=1);

require __DIR__ . '/../inc/config.php';
require PATH_INC . '/db.php';
require PATH_INC . '/csrf.php';

if (session_status() === PHP_SESSION_NONE) session_start();

@header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

/* Auth */
if (empty($_SESSION['SessUsuCodigo'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}

$usuCodigo = (int)$_SESSION['SessUsuCodigo'];

/* Garante coluna USU_FOTO */
static $fotoColChecked = false;
if (!$fotoColChecked) {
    $colExists = $pdo->query("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tb_usuario' AND COLUMN_NAME = 'USU_FOTO'
    ")->fetchColumn();
    if (!$colExists) {
        $pdo->exec("ALTER TABLE `tb_usuario` ADD COLUMN `USU_FOTO` VARCHAR(500) NOT NULL DEFAULT '' AFTER `USU_TIPO`");
    }
    $fotoColChecked = true;
}

/* CSRF em POSTs */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tok = $_POST['csrf'] ?? null;
    if (!csrf_check($tok)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Token de segurança inválido.']);
        exit;
    }
}

$acao = $_GET['acao'] ?? ($_POST['acao'] ?? '');

/* ── Salvar foto base64 ── */
function salvar_foto_base64(string $dataUri, int $usuCodigo): ?string
{
    if (!preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,/', $dataUri, $m)) return null;
    $ext     = $m[1] === 'jpeg' ? 'jpg' : $m[1];
    $base64  = substr($dataUri, strpos($dataUri, ',') + 1);
    $binData = base64_decode($base64, true);
    if (!$binData || strlen($binData) > 5 * 1024 * 1024) return null;

    $dir = PATH_UPLOAD . '/usuarios/fotos';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);

    $fname = 'usu_' . $usuCodigo . '_' . date('YmdHis') . '.' . $ext;
    $dest  = $dir . '/' . $fname;
    if (!file_put_contents($dest, $binData)) return null;
    @chmod($dest, 0644);

    return UPLOAD_URL . '/usuarios/fotos/' . $fname;
}

/* ── POST: atualizar_dados ── */
if ($acao === 'atualizar_dados' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    if ($nome === '') {
        echo json_encode(['success' => false, 'message' => 'O nome é obrigatório.']);
        exit;
    }

    $fotoUrl = null;
    $fotoData = trim($_POST['foto_data'] ?? '');
    if ($fotoData !== '') {
        $fotoUrl = salvar_foto_base64($fotoData, $usuCodigo);
        if (!$fotoUrl) {
            echo json_encode(['success' => false, 'message' => 'Formato de imagem inválido ou arquivo muito grande (máx. 5 MB).']);
            exit;
        }
    }

    $sets   = ['`USU_NOME` = ?'];
    $params = [$nome];
    if ($fotoUrl !== null) {
        $sets[]   = '`USU_FOTO` = ?';
        $params[] = $fotoUrl;
    }
    $params[] = $usuCodigo;

    $pdo->prepare("UPDATE `tb_usuario` SET " . implode(', ', $sets) . " WHERE USU_CODIGO_PK = ?")
        ->execute($params);

    $_SESSION['SessUsuNome'] = $nome;
    if ($fotoUrl !== null) $_SESSION['SessUsuFoto'] = $fotoUrl;

    $row = $pdo->prepare("SELECT USU_NOME, USU_FOTO FROM tb_usuario WHERE USU_CODIGO_PK = ?");
    $row->execute([$usuCodigo]);
    $updated = $row->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Dados atualizados com sucesso.',
        'data'    => $updated,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ── POST: alterar_email ── */
if ($acao === 'alterar_email' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $novoEmail    = trim($_POST['novo_email']    ?? '');
    $confirmaEmail= trim($_POST['confirma_email']?? '');
    $senhaAtual   = $_POST['senha_atual'] ?? '';

    if (!filter_var($novoEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'E-mail inválido.']);
        exit;
    }
    if ($novoEmail !== $confirmaEmail) {
        echo json_encode(['success' => false, 'message' => 'Os e-mails não coincidem.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT USU_SENHA, USU_EMAIL FROM tb_usuario WHERE USU_CODIGO_PK = ?");
    $stmt->execute([$usuCodigo]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($senhaAtual, rtrim((string)($user['USU_SENHA'] ?? '')))) {
        echo json_encode(['success' => false, 'message' => 'Senha incorreta.']);
        exit;
    }
    if (strtolower($novoEmail) === strtolower($user['USU_EMAIL'])) {
        echo json_encode(['success' => false, 'message' => 'O novo e-mail é igual ao atual.']);
        exit;
    }

    $check = $pdo->prepare("SELECT USU_CODIGO_PK FROM tb_usuario WHERE USU_EMAIL = ? AND USU_CODIGO_PK != ?");
    $check->execute([$novoEmail, $usuCodigo]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Este e-mail já está em uso por outro usuário.']);
        exit;
    }

    $pdo->prepare("UPDATE `tb_usuario` SET `USU_EMAIL` = ? WHERE USU_CODIGO_PK = ?")
        ->execute([$novoEmail, $usuCodigo]);

    $_SESSION['SessUsuEmail'] = $novoEmail;

    echo json_encode([
        'success'    => true,
        'message'    => 'E-mail alterado com sucesso.',
        'novo_email' => htmlspecialchars($novoEmail, ENT_QUOTES, 'UTF-8'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── POST: alterar_senha ── */
if ($acao === 'alterar_senha' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $senhaAtual    = $_POST['senha_atual']    ?? '';
    $novaSenha     = $_POST['nova_senha']     ?? '';
    $confirmaSenha = $_POST['confirma_senha'] ?? '';

    if (strlen($novaSenha) < 6) {
        echo json_encode(['success' => false, 'message' => 'A nova senha deve ter pelo menos 6 caracteres.']);
        exit;
    }
    if ($novaSenha !== $confirmaSenha) {
        echo json_encode(['success' => false, 'message' => 'As senhas não coincidem.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT USU_SENHA FROM tb_usuario WHERE USU_CODIGO_PK = ?");
    $stmt->execute([$usuCodigo]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($senhaAtual, rtrim((string)($user['USU_SENHA'] ?? '')))) {
        echo json_encode(['success' => false, 'message' => 'Senha atual incorreta.']);
        exit;
    }
    if (password_verify($novaSenha, rtrim((string)$user['USU_SENHA']))) {
        echo json_encode(['success' => false, 'message' => 'A nova senha não pode ser igual à atual.']);
        exit;
    }

    $hash = password_hash($novaSenha, PASSWORD_BCRYPT);
    $pdo->prepare("UPDATE `tb_usuario` SET `USU_SENHA` = ? WHERE USU_CODIGO_PK = ?")
        ->execute([$hash, $usuCodigo]);

    echo json_encode(['success' => true, 'message' => 'Senha alterada com sucesso.']);
    exit;
}

http_response_code(400);
echo json_encode(['error' => true, 'message' => 'Ação inválida.']);
