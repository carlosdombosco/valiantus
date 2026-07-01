<?php

declare(strict_types=1);

require __DIR__ . '/../inc/config.php';
require PATH_INC . '/db.php'; // expõe $pdo (PDO)
@header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

// CSRF (se existir helper)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && function_exists('csrf_check')) {
    $tk = $_POST['csrf'] ?? null;
    if ($tk !== null && !csrf_check($tk)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'CSRF inválido.']);
        exit;
    }
}

$acao = $_GET['acao'] ?? ($_POST['acao'] ?? '');

// helpers -------------
// converte "1.234,56" -> "1234.56"
function br_money_to_decimal(?string $v): ?string
{
    if ($v === null) return null;
    $v = trim($v);
    if ($v === '') return null;
    // remove espaços e R$
    $v = str_replace(['R$', ' '], '', $v);
    // caso já venha com vírgula decimal pt-BR
    $v = str_replace('.', '', $v);
    $v = str_replace(',', '.', $v);
    return is_numeric($v) ? $v : null;
}
function post_s(string $k, string $d = ''): string
{
    return trim($_POST[$k] ?? $d);
}
function post_money(string $k): ?string
{
    return br_money_to_decimal($_POST[$k] ?? null);
}
function as_int(?string $s): ?int
{
    if ($s === null || $s === '') return null;
    return (int)$s;
}

// garantir exceptions
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ==== obter (GET) ====
if ($acao === 'obter' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }

    $st = $pdo->prepare("SELECT * FROM tb_grupo WHERE GRU_CODIGO_PK = ?");
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Registro não encontrado']);
        exit;
    }
    echo json_encode(['success' => true, 'data' => $row], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ==== cadastrar (POST) ====
if ($acao === 'cadastrar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $descricao   = post_s('descricao');
        $tipo        = post_s('tipo');

        $mensalidade = post_money('mensalidade');
        $minimo      = post_money('minimo');
        $maximo      = post_money('maximo');
        $terceiro    = post_money('terceiro');
        $reserva     = post_money('reserva');
        $adesao      = post_money('adesao');
        $renovacao   = post_money('renovacao');
        $limite      = post_money('limite');
        $regulariz   = post_money('regularizacao');
        $sequencia   = as_int($_POST['sequencia'] ?? null);
        $status      = post_s('status') ?: 'ATIVO';

        if ($descricao === '' || $tipo === '') {
            throw new RuntimeException('Preencha descrição e tipo.');
        }

        $sql = "INSERT INTO tb_grupo (
            GRU_DESCRICAO, GRU_VALOR_MENSALIDADE, GRU_VALOR_MINIMO, GRU_VALOR_MAXIMO,
            GRU_VALOR_TERCEIRO, GRU_VALOR_RESERVA, GRU_TIPO_VEICULO, GRU_VALOR_ADESAO,
            GRU_VALOR_RENOVACAO, GRU_LIMITE_CADASTRO, GRU_TAXA_REGULARIZACAO,
            GRU_SEQUENCIA, GRU_STATUS
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $st = $pdo->prepare($sql);
        $st->execute([
            $descricao,
            $mensalidade,
            $minimo,
            $maximo,
            $terceiro,
            $reserva,
            $tipo,
            $adesao,
            $renovacao,
            $limite,
            $regulariz,
            $sequencia,
            $status
        ]);

        echo json_encode(['success' => true, 'message' => 'Grupo cadastrado com sucesso.']);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Não foi possível salvar.']);
    }
    exit;
}

// ==== editar (POST) ====
if ($acao === 'editar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id          = (int)($_POST['id'] ?? 0);
        if ($id <= 0) throw new RuntimeException('ID inválido.');

        $descricao   = post_s('descricao');
        $tipo        = post_s('tipo');

        $mensalidade = post_money('mensalidade');
        $minimo      = post_money('minimo');
        $maximo      = post_money('maximo');
        $terceiro    = post_money('terceiro');
        $reserva     = post_money('reserva');
        $adesao      = post_money('adesao');
        $renovacao   = post_money('renovacao');
        $limite      = post_money('limite');
        $regulariz   = post_money('regularizacao');
        $sequencia   = as_int($_POST['sequencia'] ?? null);
        $status      = post_s('status') ?: 'ATIVO';

        if ($descricao === '' || $tipo === '') {
            throw new RuntimeException('Preencha descrição e tipo.');
        }

        $sql = "UPDATE tb_grupo SET
            GRU_DESCRICAO = ?, GRU_VALOR_MENSALIDADE = ?, GRU_VALOR_MINIMO = ?, GRU_VALOR_MAXIMO = ?,
            GRU_VALOR_TERCEIRO = ?, GRU_VALOR_RESERVA = ?, GRU_TIPO_VEICULO = ?, GRU_VALOR_ADESAO = ?,
            GRU_VALOR_RENOVACAO = ?, GRU_LIMITE_CADASTRO = ?, GRU_TAXA_REGULARIZACAO = ?,
            GRU_SEQUENCIA = ?, GRU_STATUS = ?
            WHERE GRU_CODIGO_PK = ?";
        $st = $pdo->prepare($sql);
        $st->execute([
            $descricao,
            $mensalidade,
            $minimo,
            $maximo,
            $terceiro,
            $reserva,
            $tipo,
            $adesao,
            $renovacao,
            $limite,
            $regulariz,
            $sequencia,
            $status,
            $id
        ]);

        echo json_encode(['success' => true, 'message' => 'Grupo atualizado com sucesso.']);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Não foi possível atualizar.']);
    }
    exit;
}

// ==== excluir (POST) ====
if ($acao === 'excluir' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) throw new RuntimeException('ID inválido.');

        $st = $pdo->prepare("DELETE FROM tb_grupo WHERE GRU_CODIGO_PK = ?");
        $st->execute([$id]);

        echo json_encode(['success' => true, 'message' => 'Grupo excluído com sucesso.']);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Não foi possível excluir.']);
    }
    exit;
}

// fallback
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Ação inválida']);
