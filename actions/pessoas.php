<?php

declare(strict_types=1);

require __DIR__ . '/../inc/config.php';
require PATH_INC . '/db.php';
require PATH_INC . '/csrf.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

// ========= Helpers =========
function only_digits(?string $v): string
{
    return preg_replace('/\D+/', '', (string)$v) ?? '';
}
function column_exists(PDO $pdo, string $table, string $column): bool
{
    $sql = "SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([$table, $column]);
    return (bool)$st->fetchColumn();
}

/**
 * Redimensiona e salva JPEG <= $maxKB (mantendo proporção).
 * Retorna caminho FS salvo ou null.
 */
function save_jpeg_under_limit(string $binary, string $destFs, int $maxKB = 500, int $maxDim = 1200): ?string
{
    if ($binary === '' || !function_exists('imagecreatefromstring')) return null;

    $im = @imagecreatefromstring($binary);
    if (!$im) return null;

    $w = imagesx($im);
    $h = imagesy($im);
    $maxSide = max($w, $h);
    $scale = ($maxSide > $maxDim) ? ($maxDim / $maxSide) : 1.0;

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
    return $ok ? $destFs : null;
}

/**
 * Salva foto do associado (arquivo 'foto' OU base64 'foto_base64') em
 * /uploads/associado/<assoc_ID_data_token>/foto.jpg e retorna a URL pública.
 */
function processar_foto_associado(int $pessoaId, ?string $nome, ?array $fileFoto, ?string $fotoBase64): ?string
{
    $baseFs = PATH_UPLOAD . DIRECTORY_SEPARATOR . 'associado';
    if (!is_dir($baseFs)) @mkdir($baseFs, 0775, true);

    $token  = substr(bin2hex(random_bytes(4)), 0, 8);
    $folder = sprintf('assoc_%d_%s_%s', $pessoaId, date('Ymd_His'), $token);

    $targetDir = $baseFs . DIRECTORY_SEPARATOR . $folder;
    if (!is_dir($targetDir)) @mkdir($targetDir, 0775, true);

    $destFs  = $targetDir . DIRECTORY_SEPARATOR . 'foto.jpg';
    $destUrl = rtrim(UPLOAD_URL, '/') . '/associado/' . $folder . '/foto.jpg';

    // Preferência: arquivo enviado
    if ($fileFoto && isset($fileFoto['tmp_name']) && is_uploaded_file($fileFoto['tmp_name'])) {
        $bin = @file_get_contents($fileFoto['tmp_name']);
        if ($bin !== false) {
            if (save_jpeg_under_limit($bin, $destFs, 500, 1200)) return $destUrl;
        }
    }

    // Alternativa: base64 da câmera
    if ($fotoBase64) {
        if (preg_match('#^data:image/\w+;base64,#i', $fotoBase64)) {
            $fotoBase64 = preg_replace('#^data:image/\w+;base64,#i', '', $fotoBase64);
        }
        $bin = base64_decode($fotoBase64, true);
        if ($bin !== false) {
            if (save_jpeg_under_limit($bin, $destFs, 500, 1200)) return $destUrl;
        }
    }

    return null;
}

// ========= Rota GET: obter =========
// (colocada antes do CSRF de POST)
if (($_GET['acao'] ?? '') === 'obter' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }
    try {
        $st = $pdo->prepare("SELECT * FROM tb_pessoa WHERE PES_CODIGO_PK = :id LIMIT 1");
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Associado não encontrado']);
            exit;
        }
        echo json_encode(['success' => true, 'data' => $row], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao consultar']);
    }
    exit;
}

// ========= Rota GET: buscar (autocomplete de associados) =========
if (($_GET['acao'] ?? '') === 'buscar' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 2) {
        echo json_encode([]);
        exit;
    }
    try {
        $digits = preg_replace('/\D/', '', $q);
        $where  = [];
        $params = [];

        if (is_numeric($q) && strlen($q) <= 10) {
            $where[]  = 'p.PES_CODIGO_PK = ?';
            $params[] = (int)$q;
        }

        $where[]  = 'p.PES_NOME LIKE ?';
        $params[] = '%' . $q . '%';

        if ($digits !== '') {
            $where[]  = "REPLACE(REPLACE(REPLACE(p.PES_CPF_CNPJ,'.',''),'-',''),'/','') LIKE ?";
            $params[] = '%' . $digits . '%';
            $where[]  = "REPLACE(REPLACE(REPLACE(p.PES_FONE_CELULAR_1,'-',''),'(',''),')','') LIKE ?";
            $params[] = '%' . $digits . '%';
            $where[]  = "REPLACE(REPLACE(REPLACE(p.PES_FONE_CELULAR_2,'-',''),'(',''),')','') LIKE ?";
            $params[] = '%' . $digits . '%';
        }

        $st = $pdo->prepare(
            "SELECT p.PES_CODIGO_PK, p.PES_NOME, p.PES_CPF_CNPJ, p.PES_FONE_CELULAR_1
               FROM tb_pessoa p
              WHERE (" . implode(' OR ', $where) . ")
              ORDER BY p.PES_NOME
              LIMIT 15"
        );
        $st->execute($params);
        echo json_encode($st->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        echo json_encode([]);
    }
    exit;
}

// ========= POST (CSRF) =========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_check($_POST['csrf'] ?? '')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'CSRF inválido.']);
    exit;
}

$acao = $_POST['acao'] ?? '';

// ========= POST: verificar_cpf =========
if ($acao === 'verificar_cpf') {
    try {
        $cpf   = only_digits($_POST['cpf'] ?? '');
        $excId = (int)($_POST['id'] ?? 0);
        $where = $excId > 0 ? 'AND PES_CODIGO_PK <> :id' : '';
        $st = $pdo->prepare("
            SELECT PES_CODIGO_PK, PES_NOME, PES_CPF_CNPJ,
                   COALESCE(PES_FONE_CELULAR_1, PES_FONE_CELULAR_2, PES_FONE_FIXO, '') AS telefone
            FROM tb_pessoa
            WHERE PES_CPF_CNPJ = :cpf $where
            LIMIT 1
        ");
        $params = [':cpf' => $cpf];
        if ($excId > 0) $params[':id'] = $excId;
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            echo json_encode([
                'success' => true,
                'existe'  => true,
                'pessoa'  => [
                    'id'       => $row['PES_CODIGO_PK'],
                    'nome'     => $row['PES_NOME'],
                    'cpf'      => $row['PES_CPF_CNPJ'],
                    'telefone' => $row['telefone'],
                ],
            ]);
        } else {
            echo json_encode(['success' => true, 'existe' => false]);
        }
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'existe' => false]);
    }
    exit;
}

// ========= POST: cadastrar =========
if ($acao === 'cadastrar') {
    try {
        $nome   = trim($_POST['nome'] ?? '');
        $cpf    = only_digits($_POST['cpf'] ?? '');
        $tipo   = trim($_POST['tipo'] ?? '');
        $cel1   = only_digits($_POST['celular1'] ?? '');
        $cel2   = only_digits($_POST['celular2'] ?? '');
        $fone   = only_digits($_POST['telefone'] ?? '');
        $nasc   = trim($_POST['data_nascimento'] ?? '');
        $rg     = trim($_POST['rg'] ?? '');
        $orgao  = trim($_POST['orgao'] ?? '');
        $cnh    = trim($_POST['cnh'] ?? '');
        $cat    = trim($_POST['categoria'] ?? '');
        $val    = trim($_POST['validade'] ?? '');
        $ecivil = trim($_POST['estado_civil'] ?? '');
        $sexo   = trim($_POST['sexo'] ?? '');
        $email  = strtolower(trim($_POST['email'] ?? ''));
        $prof   = trim($_POST['profissao'] ?? '');
        $cep    = only_digits($_POST['cep'] ?? '');
        $end    = trim($_POST['endereco'] ?? '');
        $num    = trim($_POST['numero'] ?? '');
        $comp   = trim($_POST['complemento'] ?? '');
        $bairro = trim($_POST['bairro'] ?? '');
        $ref    = trim($_POST['referencia'] ?? '');
        $uf     = trim($_POST['uf'] ?? '');
        $cidade = trim($_POST['cidade'] ?? '');
        $obs    = trim($_POST['observacao'] ?? '');

        if ($nome === '' || $cpf === '') {
            echo json_encode(['success' => false, 'message' => 'Nome e CPF/CNPJ são obrigatórios.']);
            exit;
        }

        $nasc = ($nasc !== '') ? $nasc : null;
        $val  = ($val  !== '') ? $val  : null;

        $pdo->beginTransaction();

        $sql = "INSERT INTO tb_pessoa (
            PES_TIPO, PES_NOME, PES_CPF_CNPJ, PES_FONE_CELULAR_1, PES_FONE_CELULAR_2, PES_FONE_FIXO,
            PES_DATA_NASCIMENTO, PES_RG, PES_ORG_EXP, PES_NUM_CNH, PES_CATEGORIA_CNH, PES_VALIDADE,
            PES_ESTADO_CIVIL, PES_EMAIL, PES_PROFISSAO, PES_CEP, PES_ENDERECO, PES_NUMERO,
            PES_COMPLEMENTO, PES_BAIRRO, PES_PONTO_REFERENCIA, PES_UF, PES_CIDADE, PES_SEXO, PES_OBSERVACAO
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $st = $pdo->prepare($sql);
        $st->execute([
            $tipo,
            $nome,
            $cpf,
            $cel1,
            $cel2,
            $fone,
            $nasc,
            $rg,
            $orgao,
            $cnh,
            $cat,
            $val,
            $ecivil,
            $email,
            $prof,
            $cep,
            $end,
            $num,
            $comp,
            $bairro,
            $ref,
            $uf,
            $cidade,
            $sexo,
            $obs
        ]);
        $id = (int)$pdo->lastInsertId();

        // FOTO: arquivo ou base64
        $urlFoto = processar_foto_associado($id, $nome, $_FILES['foto'] ?? null, $_POST['foto_base64'] ?? null);
        if ($urlFoto && column_exists($pdo, 'tb_pessoa', 'PES_FOTO')) {
            $up = $pdo->prepare("UPDATE tb_pessoa SET PES_FOTO = ? WHERE PES_CODIGO_PK = ?");
            $up->execute([$urlFoto, $id]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Cadastro efetuado com sucesso', 'id' => $id, 'foto' => $urlFoto]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $msg = 'Não foi possível salvar os dados.';
        if ($e->getCode() === '23000') {
            $emsg = $e->getMessage();
            if (stripos($emsg, 'PES_CPF_CNPJ') !== false) $msg = 'CPF ou CNPJ já cadastrado.';
            elseif (stripos($emsg, 'PES_EMAIL') !== false) $msg = 'E-mail já cadastrado.';
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $msg]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Falha inesperada.']);
    }
    exit;
}

// ========= POST: editar =========
if ($acao === 'editar') {
    try {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            exit;
        }

        // Coleta/normaliza
        $nome   = trim($_POST['nome'] ?? '');
        $cpf    = only_digits($_POST['cpf'] ?? '');
        $tipo   = trim($_POST['tipo'] ?? '');
        $cel1   = only_digits($_POST['celular1'] ?? '');
        $cel2   = only_digits($_POST['celular2'] ?? '');
        $fone   = only_digits($_POST['telefone'] ?? '');
        $nasc   = trim($_POST['data_nascimento'] ?? '');
        $rg     = trim($_POST['rg'] ?? '');
        $orgao  = trim($_POST['orgao'] ?? '');
        $cnh    = trim($_POST['cnh'] ?? '');
        $cat    = trim($_POST['categoria'] ?? '');
        $val    = trim($_POST['validade'] ?? '');
        $ecivil = trim($_POST['estado_civil'] ?? '');
        $sexo   = trim($_POST['sexo'] ?? '');
        $email  = strtolower(trim($_POST['email'] ?? ''));
        $prof   = trim($_POST['profissao'] ?? '');
        $cep    = only_digits($_POST['cep'] ?? '');
        $end    = trim($_POST['endereco'] ?? '');
        $num    = trim($_POST['numero'] ?? '');
        $comp   = trim($_POST['complemento'] ?? '');
        $bairro = trim($_POST['bairro'] ?? '');
        $ref    = trim($_POST['referencia'] ?? '');
        $uf     = trim($_POST['uf'] ?? '');
        $cidade = trim($_POST['cidade'] ?? '');
        $obs    = trim($_POST['observacao'] ?? '');

        if ($nome === '' || $cpf === '') {
            echo json_encode(['success' => false, 'message' => 'Nome e CPF/CNPJ são obrigatórios.']);
            exit;
        }

        // Evita duplicidade de CPF em outro registro
        $ck = $pdo->prepare("SELECT COUNT(*) FROM tb_pessoa WHERE PES_CPF_CNPJ = :cpf AND PES_CODIGO_PK <> :id");
        $ck->execute([':cpf' => $cpf, ':id' => $id]);
        if ($ck->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'CPF/CNPJ já cadastrado em outro associado.']);
            exit;
        }

        $nasc = ($nasc !== '') ? $nasc : null;
        $val  = ($val  !== '') ? $val  : null;

        $pdo->beginTransaction();

        $up = $pdo->prepare("UPDATE tb_pessoa SET
            PES_TIPO = ?, PES_NOME = ?, PES_CPF_CNPJ = ?, PES_FONE_CELULAR_1 = ?, PES_FONE_CELULAR_2 = ?, PES_FONE_FIXO = ?,
            PES_DATA_NASCIMENTO = ?, PES_RG = ?, PES_ORG_EXP = ?, PES_NUM_CNH = ?, PES_CATEGORIA_CNH = ?, PES_VALIDADE = ?,
            PES_ESTADO_CIVIL = ?, PES_EMAIL = ?, PES_PROFISSAO = ?, PES_CEP = ?, PES_ENDERECO = ?, PES_NUMERO = ?,
            PES_COMPLEMENTO = ?, PES_BAIRRO = ?, PES_PONTO_REFERENCIA = ?, PES_UF = ?, PES_CIDADE = ?, PES_SEXO = ?, PES_OBSERVACAO = ?
            WHERE PES_CODIGO_PK = ?");
        $up->execute([
            $tipo,
            $nome,
            $cpf,
            $cel1,
            $cel2,
            $fone,
            $nasc,
            $rg,
            $orgao,
            $cnh,
            $cat,
            $val,
            $ecivil,
            $email,
            $prof,
            $cep,
            $end,
            $num,
            $comp,
            $bairro,
            $ref,
            $uf,
            $cidade,
            $sexo,
            $obs,
            $id
        ]);

        // Foto nova?
        $novaUrl = processar_foto_associado($id, $nome, $_FILES['foto'] ?? null, $_POST['foto_base64'] ?? null);
        if ($novaUrl && column_exists($pdo, 'tb_pessoa', 'PES_FOTO')) {
            $upf = $pdo->prepare("UPDATE tb_pessoa SET PES_FOTO = ? WHERE PES_CODIGO_PK = ?");
            $upf->execute([$novaUrl, $id]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Dados atualizados com sucesso', 'id' => $id, 'foto' => $novaUrl]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Não foi possível atualizar os dados.']);
    }
    exit;
}

// ========= Default =========
http_response_code(404);
echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
