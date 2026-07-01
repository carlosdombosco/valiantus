<?php
declare(strict_types=1);

require __DIR__ . '/../inc/config.php';
require PATH_INC . '/db.php';
require PATH_INC . '/log_evento.php';
require PATH_INC . '/csrf.php';

@header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$LOG_DIR = realpath(__DIR__ . '/../') . '/storage/logs';
if (!is_dir($LOG_DIR)) @mkdir($LOG_DIR, 0775, true);
$LOG_FILE = $LOG_DIR . '/actions_sinistros.log';
if (is_writable(dirname($LOG_FILE))) @ini_set('error_log', $LOG_FILE);

$RID = bin2hex(random_bytes(6));

function log_sin(string $rid, string $msg, array $data = []): void
{
    $p = '[' . date('Y-m-d H:i:s') . "][$rid] $msg";
    if (!empty($data)) $p .= ' | ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    error_log($p);
}

function br2dec(?string $v): ?string
{
    if ($v === null || trim($v) === '') return null;
    $v = str_replace(['R$', ' ', "\xC2\xA0"], '', trim($v));
    $v = preg_replace('/[^0-9,\.]/', '', $v);
    if (strrpos($v, ',') > strrpos($v, '.')) {
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
    } else {
        $v = str_replace(',', '', $v);
    }
    return is_numeric($v) ? number_format((float)$v, 2, '.', '') : null;
}

function str_post(string $k, string $d = ''): string { return trim($_POST[$k] ?? $d); }
function int_post(string $k): int                    { return (int)($_POST[$k] ?? 0); }
function null_str(string $v): ?string                { return $v === '' ? null : $v; }

function ensure_sin_tables(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $pdo->exec("CREATE TABLE IF NOT EXISTS `tb_sinistro` (
        `SIN_CODIGO_PK`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
        `SIN_DATA_LANCAMENTO`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `SIN_TIPO_OCORRENCIA`    VARCHAR(50)   NOT NULL,
        `VEI_CODIGO_FK`          INT UNSIGNED  NULL,
        `CTR_CODIGO_FK`          INT UNSIGNED  NULL,
        `PES_CODIGO_FK`          INT UNSIGNED  NULL,
        `SIN_DATA_OCORRENCIA`    DATE          NULL,
        `SIN_HORA_OCORRENCIA`    TIME          NULL,
        `SIN_PRECISA_REBOQUE`    ENUM('S','N') NOT NULL DEFAULT 'N',
        `SIN_HOUVE_VITIMAS`      ENUM('S','N') NOT NULL DEFAULT 'N',
        `SIN_NUM_BO`             VARCHAR(100)  NULL,
        `SIN_DATA_BO`            DATE          NULL,
        `SIN_HORA_BO`            TIME          NULL,
        `SIN_ORGAO_COMPETENCIA`  VARCHAR(150)  NULL,
        `SIN_VALOR_FIPE`         DECIMAL(12,2) NULL,
        `SIN_NUM_SINISTROS_ANT`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        `SIN_FRANQUIA_PERC`      DECIMAL(5,2)  NULL,
        `SIN_VALOR_FRANQUIA`     DECIMAL(12,2) NULL,
        `SIN_NOME_CONDUTOR`      VARCHAR(150)  NULL,
        `SIN_DATA_NASC_CONDUTOR` DATE          NULL,
        `SIN_SEXO_CONDUTOR`      ENUM('M','F','O') NULL,
        `SIN_CNH_CONDUTOR`       VARCHAR(50)   NULL,
        `SIN_VALIDADE_CNH`       DATE          NULL,
        `SIN_BAIRRO_OCORRENCIA`  VARCHAR(100)  NULL,
        `SIN_PONTO_REFERENCIA`   VARCHAR(200)  NULL,
        `SIN_CIDADE_OCORRENCIA`  VARCHAR(100)  NULL,
        `SIN_UF_OCORRENCIA`      CHAR(2)       NULL,
        `SIN_DETALHE`            TEXT          NULL,
        `SIN_DANOS_VEICULO`      TEXT          NULL,
        `SIN_STATUS`             VARCHAR(20)   NOT NULL DEFAULT 'ABERTO'
                                               COMMENT 'ABERTO | ENCERRADO | CANCELADO',
        `SIN_USUARIO_ID`         INT           NULL,
        `SIN_USUARIO_NOME`       VARCHAR(150)  NULL,
        `SIN_WHATSAPP_ENVIADO`   TINYINT(1)    NOT NULL DEFAULT 0,
        PRIMARY KEY (`SIN_CODIGO_PK`),
        KEY `idx_sin_veiculo` (`VEI_CODIGO_FK`),
        KEY `idx_sin_pessoa`  (`PES_CODIGO_FK`),
        KEY `idx_sin_status`  (`SIN_STATUS`),
        KEY `idx_sin_data`    (`SIN_DATA_OCORRENCIA`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro de sinistros'");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `tb_sinistro_imagem` (
        `SIM_CODIGO_PK` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `SIN_CODIGO_FK` INT UNSIGNED NOT NULL,
        `SIM_TIPO`      ENUM('ANTES','DEPOIS') NOT NULL DEFAULT 'ANTES',
        `SIM_CAMINHO`   VARCHAR(500) NOT NULL,
        `SIM_DATA`      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`SIM_CODIGO_PK`),
        KEY `idx_sim_sin`  (`SIN_CODIGO_FK`),
        KEY `idx_sim_tipo` (`SIM_TIPO`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Imagens de sinistros'");
    $done = true;
}

$acao = $_GET['acao'] ?? ($_POST['acao'] ?? '');

/* ═══════════ GET: buscar veículo/associado ═══════════ */
if ($acao === 'buscar' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 2) { echo json_encode([]); exit; }
    try {
        $where  = [];
        $params = [];
        $digits = preg_replace('/\D/', '', $q);

        if (is_numeric($q) && strlen($q) <= 10) {
            $where[] = 'v.VEI_CODIGO_PK = ?'; $params[] = (int)$q;
            $where[] = 'p.PES_CODIGO_PK = ?'; $params[] = (int)$q;
        }
        $where[] = 'v.VEI_PLACA LIKE ?'; $params[] = '%' . strtoupper($q) . '%';
        $where[] = 'p.PES_NOME LIKE ?';  $params[] = '%' . $q . '%';
        if ($digits !== '') {
            $where[] = "REPLACE(REPLACE(REPLACE(p.PES_CPF_CNPJ,'.',''),'-',''),'/','') LIKE ?";
            $params[] = '%' . $digits . '%';
        }

        $st = $pdo->prepare(
            "SELECT v.VEI_CODIGO_PK, v.VEI_PLACA, v.VEI_MARCA, v.VEI_MODELO, v.VEI_ANO_FABRICACAO,
                    p.PES_CODIGO_PK, p.PES_NOME, p.PES_CPF_CNPJ, p.PES_FONE_CELULAR_1,
                    c.CTR_CODIGO_PK, c.CTR_STATUS, c.CTR_VALOR_VEICULO
               FROM tb_veiculo v
               LEFT JOIN tb_pessoa p ON p.PES_CODIGO_PK = v.PES_CODIGO_FK
               LEFT JOIN (
                   SELECT t1.* FROM tb_contrato t1
                   JOIN (SELECT VEI_CODIGO_FK, MAX(CTR_CODIGO_PK) mx FROM tb_contrato GROUP BY VEI_CODIGO_FK) t2
                     ON t2.VEI_CODIGO_FK = t1.VEI_CODIGO_FK AND t2.mx = t1.CTR_CODIGO_PK
               ) c ON c.VEI_CODIGO_FK = v.VEI_CODIGO_PK
              WHERE " . implode(' OR ', $where) . "
              ORDER BY p.PES_NOME, v.VEI_PLACA
              LIMIT 30"
        );
        $st->execute($params);
        echo json_encode($st->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        echo json_encode([]);
    }
    exit;
}

/* ═══════════ GET: listar sinistros do veículo ═══════════ */
if ($acao === 'listar' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $veiculoId = (int)($_GET['veiculo_id'] ?? 0);
    if ($veiculoId <= 0) { echo json_encode([]); exit; }
    try {
        ensure_sin_tables($pdo);
        $st = $pdo->prepare(
            "SELECT s.SIN_CODIGO_PK, s.SIN_DATA_LANCAMENTO, s.SIN_TIPO_OCORRENCIA,
                    s.SIN_DATA_OCORRENCIA, s.SIN_STATUS, s.SIN_WHATSAPP_ENVIADO,
                    s.SIN_NUM_BO, s.SIN_NOME_CONDUTOR,
                    v.VEI_PLACA, v.VEI_MODELO
               FROM tb_sinistro s
               LEFT JOIN tb_veiculo v ON v.VEI_CODIGO_PK = s.VEI_CODIGO_FK
              WHERE s.VEI_CODIGO_FK = ?
              ORDER BY s.SIN_CODIGO_PK DESC"
        );
        $st->execute([$veiculoId]);
        echo json_encode($st->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        echo json_encode([]);
    }
    exit;
}

/* ═══════════ GET: obter sinistro ═══════════ */
if ($acao === 'obter' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $sinId = (int)($_GET['id'] ?? 0);
    if ($sinId <= 0) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'ID inválido']); exit; }
    try {
        ensure_sin_tables($pdo);
        $st = $pdo->prepare(
            "SELECT s.*, p.PES_NOME, p.PES_FONE_CELULAR_1, p.PES_CPF_CNPJ,
                    v.VEI_PLACA, v.VEI_MODELO, v.VEI_MARCA, v.VEI_ANO_FABRICACAO
               FROM tb_sinistro s
               LEFT JOIN tb_pessoa p ON p.PES_CODIGO_PK = s.PES_CODIGO_FK
               LEFT JOIN tb_veiculo v ON v.VEI_CODIGO_PK = s.VEI_CODIGO_FK
              WHERE s.SIN_CODIGO_PK = ? LIMIT 1"
        );
        $st->execute([$sinId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) { http_response_code(404); echo json_encode(['success' => false, 'message' => 'Sinistro não encontrado']); exit; }

        $imgs = $pdo->prepare("SELECT * FROM tb_sinistro_imagem WHERE SIN_CODIGO_FK = ? ORDER BY SIM_CODIGO_PK");
        $imgs->execute([$sinId]);
        $row['imagens'] = $imgs->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $row], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao consultar sinistro']);
    }
    exit;
}

/* ═══════════ POST: CSRF check ═══════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tok = $_POST['csrf'] ?? null;
    if ($tok !== null && function_exists('csrf_check') && !csrf_check($tok)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'CSRF inválido.', 'rid' => $RID]);
        exit;
    }
}

/* ═══════════ POST: cadastrar ═══════════ */
if ($acao === 'cadastrar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        ensure_sin_tables($pdo);

        $veiculoId  = int_post('veiculo_id');
        $pessoaId   = int_post('pessoa_id');
        $contratoId = int_post('contrato_id') ?: null;

        if ($veiculoId <= 0 || $pessoaId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Veículo/Associado inválido.', 'rid' => $RID]);
            exit;
        }

        $tipo        = null_str(str_post('tipo_ocorrencia'));
        $dataOcorr   = null_str(str_post('data_ocorrencia'));
        $horaOcorr   = null_str(str_post('hora_ocorrencia'));
        $reboque     = str_post('precisa_reboque', 'N') === 'S' ? 'S' : 'N';
        $vitimas     = str_post('houve_vitimas',   'N') === 'S' ? 'S' : 'N';
        $numBO       = null_str(str_post('num_bo'));
        $dataBO      = null_str(str_post('data_bo'));
        $horaBO      = null_str(str_post('hora_bo'));
        $orgao       = null_str(str_post('orgao_competencia'));
        $valorFipe   = br2dec($_POST['valor_fipe']    ?? null);
        $numSinAnt   = int_post('num_sinistros_ant');
        $franqPerc   = null_str(str_post('franquia_perc'));
        $valorFranq  = br2dec($_POST['valor_franquia'] ?? null);
        $nomeCondutor = null_str(str_post('nome_condutor'));
        $dataNasc    = null_str(str_post('data_nasc_condutor'));
        $sexo        = null_str(str_post('sexo_condutor'));
        $cnh         = null_str(str_post('cnh_condutor'));
        $validCnh    = null_str(str_post('validade_cnh'));
        $bairro      = null_str(str_post('bairro_ocorrencia'));
        $pontoRef    = null_str(str_post('ponto_referencia'));
        $cidade      = null_str(str_post('cidade_ocorrencia'));
        $uf          = null_str(strtoupper(str_post('uf_ocorrencia')));
        $detalhe     = null_str(str_post('detalhe'));
        $danos       = null_str(str_post('danos_veiculo'));
        $usuarioId   = (int)($_SESSION['SessUsuCodigo'] ?? 0) ?: null;
        $usuarioNome = $_SESSION['SessUsuNome'] ?? null;

        $pdo->beginTransaction();
        $pdo->prepare(
            "INSERT INTO tb_sinistro (
                SIN_TIPO_OCORRENCIA, VEI_CODIGO_FK, CTR_CODIGO_FK, PES_CODIGO_FK,
                SIN_DATA_OCORRENCIA, SIN_HORA_OCORRENCIA, SIN_PRECISA_REBOQUE, SIN_HOUVE_VITIMAS,
                SIN_NUM_BO, SIN_DATA_BO, SIN_HORA_BO, SIN_ORGAO_COMPETENCIA,
                SIN_VALOR_FIPE, SIN_NUM_SINISTROS_ANT, SIN_FRANQUIA_PERC, SIN_VALOR_FRANQUIA,
                SIN_NOME_CONDUTOR, SIN_DATA_NASC_CONDUTOR, SIN_SEXO_CONDUTOR,
                SIN_CNH_CONDUTOR, SIN_VALIDADE_CNH,
                SIN_BAIRRO_OCORRENCIA, SIN_PONTO_REFERENCIA, SIN_CIDADE_OCORRENCIA, SIN_UF_OCORRENCIA,
                SIN_DETALHE, SIN_DANOS_VEICULO, SIN_USUARIO_ID, SIN_USUARIO_NOME
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        )->execute([
            $tipo, $veiculoId, $contratoId, $pessoaId,
            $dataOcorr, $horaOcorr, $reboque, $vitimas,
            $numBO, $dataBO, $horaBO, $orgao,
            $valorFipe, $numSinAnt, $franqPerc, $valorFranq,
            $nomeCondutor, $dataNasc, $sexo, $cnh, $validCnh,
            $bairro, $pontoRef, $cidade, $uf,
            $detalhe, $danos, $usuarioId, $usuarioNome,
        ]);
        $sinId = (int)$pdo->lastInsertId();
        $pdo->commit();

        log_evento($pdo, 'tb_sinistro', $sinId, 'CRIACAO', [
            'usuario_id'   => $usuarioId,
            'usuario_nome' => $usuarioNome,
            'obs'          => ['veiculo_id' => $veiculoId, 'tipo' => $tipo],
        ]);
        log_sin($RID, 'Sinistro cadastrado', ['sin_id' => $sinId, 'veiculo' => $veiculoId]);
        echo json_encode(['success' => true, 'message' => 'Sinistro cadastrado com sucesso.', 'sin_id' => $sinId, 'rid' => $RID]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        log_sin($RID, 'Erro ao cadastrar sinistro', ['err' => $e->getMessage()]);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Não foi possível cadastrar o sinistro.', 'rid' => $RID, 'debug' => $e->getMessage()]);
    }
    exit;
}

/* ═══════════ POST: upload_imagem ═══════════ */
if ($acao === 'upload_imagem' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        ensure_sin_tables($pdo);
        $sinId = int_post('sin_id');
        $tipo  = in_array(strtoupper($_POST['tipo'] ?? ''), ['ANTES', 'DEPOIS'])
                 ? strtoupper($_POST['tipo'])
                 : 'ANTES';

        if ($sinId <= 0) { echo json_encode(['success' => false, 'message' => 'ID do sinistro inválido.']); exit; }

        $f = $_FILES['imagem'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            echo json_encode(['success' => false, 'message' => 'Nenhum arquivo válido enviado.']); exit;
        }

        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            echo json_encode(['success' => false, 'message' => 'Tipo de arquivo não permitido.']); exit;
        }

        $dir = PATH_UPLOAD . '/sinistro/' . $sinId . '/' . strtolower($tipo);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        $fname  = date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 6) . '.' . $ext;
        $destFs = $dir . '/' . $fname;
        if (!@move_uploaded_file($f['tmp_name'], $destFs)) {
            echo json_encode(['success' => false, 'message' => 'Falha ao salvar arquivo.']); exit;
        }
        @chmod($destFs, 0644);

        $url = UPLOAD_URL . '/sinistro/' . $sinId . '/' . strtolower($tipo) . '/' . $fname;
        $pdo->prepare("INSERT INTO tb_sinistro_imagem (SIN_CODIGO_FK, SIM_TIPO, SIM_CAMINHO) VALUES (?,?,?)")
            ->execute([$sinId, $tipo, $url]);
        $imgId = (int)$pdo->lastInsertId();

        echo json_encode(['success' => true, 'id' => $imgId, 'url' => $url, 'tipo' => $tipo]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Erro no upload.', 'debug' => $e->getMessage()]);
    }
    exit;
}

/* ═══════════ POST: excluir_imagem ═══════════ */
if ($acao === 'excluir_imagem' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        ensure_sin_tables($pdo);
        $imgId = int_post('img_id');
        if ($imgId <= 0) { echo json_encode(['success' => false, 'message' => 'ID inválido.']); exit; }
        $st = $pdo->prepare("SELECT SIM_CAMINHO FROM tb_sinistro_imagem WHERE SIM_CODIGO_PK = ? LIMIT 1");
        $st->execute([$imgId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) { echo json_encode(['success' => false, 'message' => 'Imagem não encontrada.']); exit; }
        $pdo->prepare("DELETE FROM tb_sinistro_imagem WHERE SIM_CODIGO_PK = ?")->execute([$imgId]);
        // Reconstrói o caminho físico a partir de UPLOAD_URL → PATH_UPLOAD
        $urlPath  = parse_url($row['SIM_CAMINHO'], PHP_URL_PATH) ?? '';
        $urlBase  = rtrim(UPLOAD_URL, '/');
        $fsPath   = '';
        if ($urlPath && strpos($urlPath, $urlBase) === 0) {
            $fsPath = PATH_UPLOAD . str_replace('\\', '/', substr($urlPath, strlen($urlBase)));
        }
        if ($fsPath && file_exists($fsPath)) @unlink($fsPath);
        echo json_encode(['success' => true]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir imagem.']);
    }
    exit;
}

/* ═══════════ POST: marcar_whatsapp ═══════════ */
if ($acao === 'marcar_whatsapp' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        ensure_sin_tables($pdo);
        $sinId = int_post('sin_id');
        if ($sinId <= 0) { echo json_encode(['success' => false]); exit; }
        $pdo->prepare("UPDATE tb_sinistro SET SIN_WHATSAPP_ENVIADO = 1 WHERE SIN_CODIGO_PK = ?")
            ->execute([$sinId]);
        echo json_encode(['success' => true]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false]);
    }
    exit;
}

/* ═══════════ POST: atualizar ═══════════ */
if ($acao === 'atualizar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        ensure_sin_tables($pdo);

        $sinId = int_post('sin_id');
        if ($sinId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID do sinistro inválido.', 'rid' => $RID]);
            exit;
        }

        $tipo         = null_str(str_post('tipo_ocorrencia'));
        $dataOcorr    = null_str(str_post('data_ocorrencia'));
        $horaOcorr    = null_str(str_post('hora_ocorrencia'));
        $reboque      = str_post('precisa_reboque', 'N') === 'S' ? 'S' : 'N';
        $vitimas      = str_post('houve_vitimas',   'N') === 'S' ? 'S' : 'N';
        $numBO        = null_str(str_post('num_bo'));
        $dataBO       = null_str(str_post('data_bo'));
        $horaBO       = null_str(str_post('hora_bo'));
        $orgao        = null_str(str_post('orgao_competencia'));
        $valorFipe    = br2dec($_POST['valor_fipe']     ?? null);
        $numSinAnt    = int_post('num_sinistros_ant');
        $franqPerc    = null_str(str_post('franquia_perc'));
        $valorFranq   = br2dec($_POST['valor_franquia'] ?? null);
        $nomeCondutor = null_str(str_post('nome_condutor'));
        $dataNasc     = null_str(str_post('data_nasc_condutor'));
        $sexo         = null_str(str_post('sexo_condutor'));
        $cnh          = null_str(str_post('cnh_condutor'));
        $validCnh     = null_str(str_post('validade_cnh'));
        $bairro       = null_str(str_post('bairro_ocorrencia'));
        $pontoRef     = null_str(str_post('ponto_referencia'));
        $cidade       = null_str(str_post('cidade_ocorrencia'));
        $uf           = null_str(strtoupper(str_post('uf_ocorrencia')));
        $detalhe      = null_str(str_post('detalhe'));
        $danos        = null_str(str_post('danos_veiculo'));
        $usuarioId    = (int)($_SESSION['SessUsuCodigo'] ?? 0) ?: null;
        $usuarioNome  = $_SESSION['SessUsuNome'] ?? null;

        $pdo->beginTransaction();
        $pdo->prepare(
            "UPDATE tb_sinistro SET
                SIN_TIPO_OCORRENCIA    = ?,
                SIN_DATA_OCORRENCIA    = ?, SIN_HORA_OCORRENCIA    = ?,
                SIN_PRECISA_REBOQUE    = ?, SIN_HOUVE_VITIMAS      = ?,
                SIN_NUM_BO             = ?, SIN_DATA_BO             = ?,
                SIN_HORA_BO            = ?, SIN_ORGAO_COMPETENCIA   = ?,
                SIN_VALOR_FIPE         = ?, SIN_NUM_SINISTROS_ANT   = ?,
                SIN_FRANQUIA_PERC      = ?, SIN_VALOR_FRANQUIA       = ?,
                SIN_NOME_CONDUTOR      = ?, SIN_DATA_NASC_CONDUTOR  = ?,
                SIN_SEXO_CONDUTOR      = ?, SIN_CNH_CONDUTOR         = ?,
                SIN_VALIDADE_CNH       = ?,
                SIN_BAIRRO_OCORRENCIA  = ?, SIN_PONTO_REFERENCIA    = ?,
                SIN_CIDADE_OCORRENCIA  = ?, SIN_UF_OCORRENCIA        = ?,
                SIN_DETALHE            = ?, SIN_DANOS_VEICULO        = ?
             WHERE SIN_CODIGO_PK = ?"
        )->execute([
            $tipo,
            $dataOcorr, $horaOcorr,
            $reboque,   $vitimas,
            $numBO,     $dataBO,
            $horaBO,    $orgao,
            $valorFipe, $numSinAnt,
            $franqPerc, $valorFranq,
            $nomeCondutor, $dataNasc,
            $sexo,      $cnh,
            $validCnh,
            $bairro,    $pontoRef,
            $cidade,    $uf,
            $detalhe,   $danos,
            $sinId,
        ]);
        $pdo->commit();

        log_evento($pdo, 'tb_sinistro', $sinId, 'ATUALIZACAO', [
            'usuario_id'   => $usuarioId,
            'usuario_nome' => $usuarioNome,
        ]);

        echo json_encode(['success' => true, 'message' => 'Sinistro atualizado com sucesso.', 'sin_id' => $sinId, 'rid' => $RID]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Não foi possível atualizar.', 'rid' => $RID, 'debug' => $e->getMessage()]);
    }
    exit;
}

/* ═══════════ GET: listar_todos ═══════════ */
if ($acao === 'listar_todos' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        ensure_sin_tables($pdo);
        $status = strtoupper(trim($_GET['status'] ?? ''));
        $q      = trim($_GET['q'] ?? '');

        $where  = [];
        $params = [];

        if ($status && in_array($status, ['ABERTO','ENCERRADO','CANCELADO'], true)) {
            $where[] = 's.SIN_STATUS = ?';
            $params[] = $status;
        }
        if (strlen($q) >= 2) {
            $digits = preg_replace('/\D/', '', $q);
            $sub    = ['p.PES_NOME LIKE ?', 'v.VEI_PLACA LIKE ?',
                       's.SIN_TIPO_OCORRENCIA LIKE ?', 's.SIN_NUM_BO LIKE ?',
                       's.SIN_NOME_CONDUTOR LIKE ?'];
            $pArr   = ['%'.$q.'%', '%'.strtoupper($q).'%', '%'.$q.'%', '%'.$q.'%', '%'.$q.'%'];
            if ($digits) {
                $sub[]  = 's.SIN_CODIGO_PK = ?';
                $pArr[] = (int)$digits;
            }
            $where[]  = '(' . implode(' OR ', $sub) . ')';
            $params   = array_merge($params, $pArr);
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $st = $pdo->prepare(
            "SELECT s.SIN_CODIGO_PK, s.SIN_DATA_LANCAMENTO, s.SIN_TIPO_OCORRENCIA,
                    s.SIN_DATA_OCORRENCIA, s.SIN_STATUS, s.SIN_WHATSAPP_ENVIADO,
                    s.SIN_NUM_BO, s.SIN_NOME_CONDUTOR, s.SIN_USUARIO_NOME,
                    s.VEI_CODIGO_FK, s.PES_CODIGO_FK,
                    p.PES_NOME, v.VEI_PLACA,
                    CONCAT(COALESCE(v.VEI_MARCA,''),' ',COALESCE(v.VEI_MODELO,'')) AS VEI_DESCRICAO
               FROM tb_sinistro s
               LEFT JOIN tb_pessoa  p ON p.PES_CODIGO_PK = s.PES_CODIGO_FK
               LEFT JOIN tb_veiculo v ON v.VEI_CODIGO_PK = s.VEI_CODIGO_FK
              $whereSql
              ORDER BY s.SIN_CODIGO_PK DESC
              LIMIT 1000"
        );
        $st->execute($params);
        echo json_encode($st->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        echo json_encode([]);
    }
    exit;
}

// fallback
http_response_code(400);
echo json_encode(['error' => true, 'message' => 'Ação inválida', 'rid' => $RID]);
