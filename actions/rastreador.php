<?php

declare(strict_types=1);

require __DIR__ . '/../inc/config.php';
require PATH_INC . '/db.php'; // expõe $pdo
@header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$acao = $_GET['acao'] ?? ($_POST['acao'] ?? '');

// ==== helpers ====
function post_str(string $k, string $def = ''): string
{
    return trim($_POST[$k] ?? $def);
}
function only_digits(?string $v): string
{
    return preg_replace('/\D+/', '', (string)$v) ?? '';
}
function br_money_to_decimal(?string $v): ?string
{
    if ($v === null) return null;
    $v = trim($v);
    if ($v === '') return null;
    $v = str_replace(['R$', ' '], '', $v);
    $v = str_replace('.', '', $v);
    $v = str_replace(',', '.', $v);
    return is_numeric($v) ? $v : null;
}
function br_date_to_iso(?string $v): ?string
{
    // aceita "2025-08-31" (já ISO) ou "31/08/2025"
    $v = trim((string)$v);
    if ($v === '') return null;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v;
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $v, $m)) {
        return "{$m[3]}-{$m[2]}-{$m[1]}";
    }
    return null;
}

// CSRF (se houver)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && function_exists('csrf_check')) {
    $tok = $_POST['csrf'] ?? null;
    if ($tok !== null && !csrf_check($tok)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'CSRF inválido.']);
        exit;
    }
}

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
}

// ============== GET /obter?id=... (preencher modal editar) ==============
if ($acao === 'obter' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }
    $stmt = $pdo->prepare("SELECT * FROM tb_rastreador WHERE RAS_CODIGO_PK = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Registro não encontrado']);
        exit;
    }
    echo json_encode(['success' => true, 'data' => $row], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ============== POST /cadastrar ==============
if ($acao === 'cadastrar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $codigo      = post_str('codigo');
        $modelo      = post_str('modelo');
        $operadora   = post_str('operadora');
        $num_chip    = only_digits(post_str('num_chip'));
        $usuario     = post_str('usuario');
        $senha       = post_str('senha');
        $val_equip   = br_money_to_decimal($_POST['valor_equip'] ?? null);
        $val_inst    = br_money_to_decimal($_POST['valor_inst'] ?? null);
        $dt_recarga  = br_date_to_iso($_POST['data_ultima_recarga'] ?? null);
        $obs         = $_POST['observacao'] ?? null;
        $status      = post_str('status') ?: 'ATIVO';

        $sql = "INSERT INTO tb_rastreador (
                    RAS_CODIGO, RAS_MODELO, RAS_OPERADORA, RAS_NUM_CHIP, RAS_USUARIO, RAS_SENHA,
                    RAS_VALOR_EQUIPAMENTO, RAS_VALOR_INSTALACAO, RAS_DATA_ULTIMA_RECARGA, RAS_OBSERVACAO, RAS_STATUS
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $codigo ?: null,
            $modelo ?: null,
            $operadora ?: null,
            $num_chip ?: null,
            $usuario ?: null,
            $senha ?: null,
            $val_equip,
            $val_inst,
            $dt_recarga,
            $obs,
            $status ?: null
        ]);

        echo json_encode(['success' => true, 'message' => 'Rastreador cadastrado com sucesso.']);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Não foi possível salvar.']);
    }
    exit;
}

// ============== POST /editar ==============
if ($acao === 'editar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id          = (int)($_POST['id'] ?? 0);
        if ($id <= 0) throw new RuntimeException('ID inválido');

        $codigo      = post_str('codigo');
        $modelo      = post_str('modelo');
        $operadora   = post_str('operadora');
        $num_chip    = only_digits(post_str('num_chip'));
        $usuario     = post_str('usuario');
        $senha       = post_str('senha');
        $val_equip   = br_money_to_decimal($_POST['valor_equip'] ?? null);
        $val_inst    = br_money_to_decimal($_POST['valor_inst'] ?? null);
        $dt_recarga  = br_date_to_iso($_POST['data_ultima_recarga'] ?? null);
        $obs         = $_POST['observacao'] ?? null;
        $status      = post_str('status') ?: null;

        $sql = "UPDATE tb_rastreador SET
                    RAS_CODIGO = ?, RAS_MODELO = ?, RAS_OPERADORA = ?, RAS_NUM_CHIP = ?,
                    RAS_USUARIO = ?, RAS_SENHA = ?, RAS_VALOR_EQUIPAMENTO = ?, RAS_VALOR_INSTALACAO = ?,
                    RAS_DATA_ULTIMA_RECARGA = ?, RAS_OBSERVACAO = ?, RAS_STATUS = ?
                WHERE RAS_CODIGO_PK = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $codigo ?: null,
            $modelo ?: null,
            $operadora ?: null,
            $num_chip ?: null,
            $usuario ?: null,
            $senha ?: null,
            $val_equip,
            $val_inst,
            $dt_recarga,
            $obs,
            $status,
            $id
        ]);

        echo json_encode(['success' => true, 'message' => 'Rastreador atualizado com sucesso.']);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Não foi possível atualizar.']);
    }
    exit;
}

// ============== POST /excluir ==============
if ($acao === 'excluir' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) throw new RuntimeException('ID inválido');

        $stmt = $pdo->prepare("DELETE FROM tb_rastreador WHERE RAS_CODIGO_PK = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true, 'message' => 'Rastreador excluído.']);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Não foi possível excluir.']);
    }
    exit;
}

// inválido
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Ação inválida']);
