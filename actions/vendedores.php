<?php

declare(strict_types=1);

require __DIR__ . '/../inc/config.php';
require PATH_INC . '/db.php';
require PATH_INC . '/csrf.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

function only_digits_ven(?string $v): string
{
    return preg_replace('/\D+/', '', (string)$v) ?? '';
}

function save_jpeg_ven(string $binary, string $destFs, int $maxKB = 500, int $maxDim = 1200): bool
{
    if ($binary === '' || !function_exists('imagecreatefromstring')) return false;
    $im = @imagecreatefromstring($binary);
    if (!$im) return false;
    $w = imagesx($im);
    $h = imagesy($im);
    $scale = (max($w, $h) > $maxDim) ? ($maxDim / max($w, $h)) : 1.0;
    $nw = (int)max(1, round($w * $scale));
    $nh = (int)max(1, round($h * $scale));
    $canvas = imagecreatetruecolor($nw, $nh);
    imagecopyresampled($canvas, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($im);
    $ok = false;
    $lastData = null;
    for ($q = 85; $q >= 50; $q -= 5) {
        ob_start();
        imagejpeg($canvas, null, $q);
        $data = ob_get_clean();
        if ($data === false) continue;
        if (strlen($data) <= ($maxKB * 1024)) {
            if (@file_put_contents($destFs, $data) !== false) {
                $ok = true;
                break;
            }
        }
        $lastData = $data;
    }
    if (!$ok && isset($lastData)) {
        @file_put_contents($destFs, $lastData);
        $ok = true;
    }
    imagedestroy($canvas);
    return $ok;
}

function processar_foto_vendedor(int $id, ?array $fileFoto, ?string $fotoBase64): ?string
{
    $baseFs = PATH_UPLOAD . DIRECTORY_SEPARATOR . 'vendedor';
    if (!is_dir($baseFs)) @mkdir($baseFs, 0775, true);
    $token     = substr(bin2hex(random_bytes(4)), 0, 8);
    $folder    = sprintf('ven_%d_%s_%s', $id, date('Ymd_His'), $token);
    $targetDir = $baseFs . DIRECTORY_SEPARATOR . $folder;
    if (!is_dir($targetDir)) @mkdir($targetDir, 0775, true);
    $destFs  = $targetDir . DIRECTORY_SEPARATOR . 'foto.jpg';
    $destUrl = rtrim(UPLOAD_URL, '/') . '/vendedor/' . $folder . '/foto.jpg';

    if ($fileFoto && isset($fileFoto['tmp_name']) && is_uploaded_file($fileFoto['tmp_name'])) {
        $bin = @file_get_contents($fileFoto['tmp_name']);
        if ($bin !== false && save_jpeg_ven($bin, $destFs)) return $destUrl;
    }
    if ($fotoBase64) {
        $fotoBase64 = preg_replace('#^data:image/\w+;base64,#i', '', $fotoBase64);
        $bin = base64_decode($fotoBase64, true);
        if ($bin !== false && save_jpeg_ven($bin, $destFs)) return $destUrl;
    }
    return null;
}

function coletar_campos_ven(): array
{
    $od = fn(?string $v): ?string => trim($v ?? '') ?: null;
    return [
        'nome'     => trim($_POST['nome'] ?? ''),
        'cpf'      => only_digits_ven($_POST['cpf'] ?? ''),
        'rg'       => $od($_POST['rg'] ?? null),
        'orgao'    => $od($_POST['orgao'] ?? null),
        'sexo'     => $od($_POST['sexo'] ?? null),
        'nasc'     => $od($_POST['data_nascimento'] ?? null),
        'ecivil'   => $od($_POST['estado_civil'] ?? null),
        'email'    => strtolower(trim($_POST['email'] ?? '')),
        'celular'  => only_digits_ven($_POST['celular'] ?? ''),
        'fone'     => only_digits_ven($_POST['fone_fixo'] ?? ''),
        'cep'      => only_digits_ven($_POST['cep'] ?? ''),
        'end'      => $od($_POST['endereco'] ?? null),
        'bairro'   => $od($_POST['bairro'] ?? null),
        'num'      => $od($_POST['numero'] ?? null),
        'comp'     => $od($_POST['complemento'] ?? null),
        'ref'      => $od($_POST['referencia'] ?? null),
        'uf'       => $od($_POST['uf'] ?? null),
        'cidade'   => $od($_POST['cidade'] ?? null),
        'comissao' => (float)str_replace(',', '.', $_POST['comissao'] ?? '0'),
        'status'   => trim($_POST['status'] ?? 'ATIVO'),
        'obs'      => $od($_POST['observacao'] ?? null),
    ];
}

// ── GET: obter ────────────────────────────────────────────────────────────────
if (($_GET['acao'] ?? '') === 'obter' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }
    try {
        $st = $pdo->prepare("SELECT * FROM tb_vendedor WHERE VEN_CODIGO_PK = :id LIMIT 1");
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Vendedor não encontrado']);
            exit;
        }
        echo json_encode(['success' => true, 'data' => $row], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao consultar']);
    }
    exit;
}

// ── CSRF ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_check($_POST['csrf'] ?? '')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'CSRF inválido.']);
    exit;
}

$acao = $_POST['acao'] ?? '';

// ── verificar_cpf ─────────────────────────────────────────────────────────────
if ($acao === 'verificar_cpf') {
    $cpf   = only_digits_ven($_POST['cpf'] ?? '');
    $excId = (int)($_POST['id'] ?? 0);
    if ($excId > 0) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM tb_vendedor WHERE VEN_CPF = :cpf AND VEN_CODIGO_PK <> :id");
        $st->execute([':cpf' => $cpf, ':id' => $excId]);
    } else {
        $st = $pdo->prepare("SELECT COUNT(*) FROM tb_vendedor WHERE VEN_CPF = :cpf");
        $st->execute([':cpf' => $cpf]);
    }
    echo json_encode(['success' => true, 'existe' => $st->fetchColumn() > 0]);
    exit;
}

// ── cadastrar ─────────────────────────────────────────────────────────────────
if ($acao === 'cadastrar') {
    try {
        $d = coletar_campos_ven();
        if ($d['nome'] === '' || $d['cpf'] === '') {
            echo json_encode(['success' => false, 'message' => 'Nome e CPF são obrigatórios.']);
            exit;
        }
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO tb_vendedor
            (VEN_NOME, VEN_CPF, VEN_RG, VEN_ORG_EXP, VEN_SEXO, VEN_DATA_NASCIMENTO, VEN_ESTADO_CIVIL,
             VEN_EMAIL, VEN_FONE_CELULAR, VEN_FONE_FIXO, VEN_CEP, VEN_ENDERECO, VEN_BAIRRO, VEN_NUMERO,
             VEN_COMPLEMENTO, VEN_PONTO_REFERENCIA, VEN_UF, VEN_CIDADE, VEN_COMISSAO, VEN_STATUS, VEN_OBSERVACAO)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([
                $d['nome'],
                $d['cpf'],
                $d['rg'],
                $d['orgao'],
                $d['sexo'],
                $d['nasc'],
                $d['ecivil'],
                $d['email'],
                $d['celular'],
                $d['fone'],
                $d['cep'],
                $d['end'],
                $d['bairro'],
                $d['num'],
                $d['comp'],
                $d['ref'],
                $d['uf'],
                $d['cidade'],
                $d['comissao'],
                $d['status'],
                $d['obs']
            ]);
        $id = (int)$pdo->lastInsertId();
        $urlFoto = processar_foto_vendedor($id, $_FILES['foto'] ?? null, $_POST['foto_base64'] ?? null);
        if ($urlFoto) $pdo->prepare("UPDATE tb_vendedor SET VEN_FOTO = ? WHERE VEN_CODIGO_PK = ?")->execute([$urlFoto, $id]);
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Vendedor cadastrado com sucesso.', 'id' => $id, 'foto' => $urlFoto]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $msg = 'Não foi possível salvar.';
        if ($e->getCode() === '23000' && stripos($e->getMessage(), 'VEN_CPF') !== false) $msg = 'CPF já cadastrado.';
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $msg]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Falha inesperada.']);
    }
    exit;
}

// ── editar ────────────────────────────────────────────────────────────────────
if ($acao === 'editar') {
    try {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            exit;
        }
        $d = coletar_campos_ven();
        if ($d['nome'] === '' || $d['cpf'] === '') {
            echo json_encode(['success' => false, 'message' => 'Nome e CPF são obrigatórios.']);
            exit;
        }
        $ck = $pdo->prepare("SELECT COUNT(*) FROM tb_vendedor WHERE VEN_CPF = :cpf AND VEN_CODIGO_PK <> :id");
        $ck->execute([':cpf' => $d['cpf'], ':id' => $id]);
        if ($ck->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'CPF já cadastrado em outro vendedor.']);
            exit;
        }
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE tb_vendedor SET
            VEN_NOME=?, VEN_CPF=?, VEN_RG=?, VEN_ORG_EXP=?, VEN_SEXO=?, VEN_DATA_NASCIMENTO=?, VEN_ESTADO_CIVIL=?,
            VEN_EMAIL=?, VEN_FONE_CELULAR=?, VEN_FONE_FIXO=?, VEN_CEP=?, VEN_ENDERECO=?, VEN_BAIRRO=?, VEN_NUMERO=?,
            VEN_COMPLEMENTO=?, VEN_PONTO_REFERENCIA=?, VEN_UF=?, VEN_CIDADE=?, VEN_COMISSAO=?, VEN_STATUS=?, VEN_OBSERVACAO=?
            WHERE VEN_CODIGO_PK=?")
            ->execute([
                $d['nome'],
                $d['cpf'],
                $d['rg'],
                $d['orgao'],
                $d['sexo'],
                $d['nasc'],
                $d['ecivil'],
                $d['email'],
                $d['celular'],
                $d['fone'],
                $d['cep'],
                $d['end'],
                $d['bairro'],
                $d['num'],
                $d['comp'],
                $d['ref'],
                $d['uf'],
                $d['cidade'],
                $d['comissao'],
                $d['status'],
                $d['obs'],
                $id
            ]);
        $novaUrl = processar_foto_vendedor($id, $_FILES['foto'] ?? null, $_POST['foto_base64'] ?? null);
        if ($novaUrl) $pdo->prepare("UPDATE tb_vendedor SET VEN_FOTO = ? WHERE VEN_CODIGO_PK = ?")->execute([$novaUrl, $id]);
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Dados atualizados com sucesso.', 'id' => $id, 'foto' => $novaUrl]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Não foi possível atualizar.']);
    }
    exit;
}

// ── excluir ───────────────────────────────────────────────────────────────────
if ($acao === 'excluir') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido.']);
        exit;
    }
    try {
        $pdo->prepare("DELETE FROM tb_vendedor WHERE VEN_CODIGO_PK = ?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Vendedor excluído com sucesso.']);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Não foi possível excluir.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Ação não reconhecida.']);
