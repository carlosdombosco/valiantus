<?php

declare(strict_types=1);

ob_start(); // captura qualquer saída acidental (warnings de startup, etc.)

require __DIR__ . '/../inc/config.php';
require PATH_INC . '/db.php';
require PATH_INC . '/log_evento.php';
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// descarta qualquer output acumulado antes de enviar o header JSON
ob_clean();
@header('Content-Type: application/json; charset=utf-8');

// ========= LOG =========
$LOG_DIR  = realpath(__DIR__ . '/../') . '/storage/logs';
if (!is_dir($LOG_DIR)) {
    @mkdir($LOG_DIR, 0775, true);
}
$LOG_FILE = $LOG_DIR . '/actions_veiculos.log';
if (is_writable(dirname($LOG_FILE))) {
    @ini_set('error_log', $LOG_FILE);
}
$RID = bin2hex(random_bytes(6));
function log_line(string $rid, string $msg, array $data = []): void
{
    $payload = '[' . date('Y-m-d H:i:s') . "][$rid] $msg";
    if (!empty($data)) $payload .= ' | ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    error_log($payload);
}
function safe_post_snapshot(): array
{
    $out = [];
    foreach ($_POST as $k => $v) {
        if ($k === 'csrf') continue;
        $out[$k] = is_array($v) ? '(array:' . count($v) . ')' : (strlen((string)$v) > 200 ? substr((string)$v, 0, 200) . '…(trunc)' : (string)$v);
    }
    return $out;
}
function safe_files_snapshot(): array
{
    $o = [];
    foreach ($_FILES as $k => $f) {
        if (is_array($f['name'])) {
            $rows = [];
            foreach (($f['name'] ?? []) as $i => $nm) {
                $rows[] = ['name' => $nm, 'size' => $f['size'][$i] ?? null];
            }
            $o[$k] = $rows;
        } else {
            $o[$k] = ['name' => $f['name'] ?? null, 'size' => $f['size'] ?? null];
        }
    }
    return $o;
}
function pdo_meta(Throwable $e): array
{
    $m = ['type' => get_class($e), 'code' => $e->getCode(), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
    if ($e instanceof PDOException && isset($e->errorInfo)) $m['errorInfo'] = $e->errorInfo;
    return $m;
}
function only_digits(string $v): string
{
    return preg_replace('/\D+/', '', $v) ?? '';
}
function br_money_to_decimal(?string $v): ?string
{
    if ($v === null) return null;
    $v = trim($v);
    if ($v === '') return null;
    $v = str_replace(["R$", " ", "\xC2\xA0"], '', $v);
    $v = preg_replace('/[^0-9,\.]/', '', $v);
    $hasComma = strpos($v, ',') !== false;
    $hasDot = strpos($v, '.') !== false;
    if ($hasComma && (!$hasDot || strrpos($v, ',') > strrpos($v, '.'))) {
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
    } else {
        $v = str_replace(',', '', $v);
    }
    if (!is_numeric($v)) return null;
    return number_format((float)$v, 2, '.', '');
}
function post_str(string $name, string $default = ''): string
{
    return trim($_POST[$name] ?? $default);
}
function try_decimal(string $name): ?string
{
    return br_money_to_decimal($_POST[$name] ?? null);
}
function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}
function column_exists(PDO $pdo, string $table, string $column): bool
{
    $st = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    $st->execute([$table, $column]);
    return (bool)$st->fetchColumn();
}
function ensure_vistoria_tables(PDO $pdo): void
{
    // Cria tb_itens_vistoria se não existir
    $pdo->exec("CREATE TABLE IF NOT EXISTS tb_itens_vistoria (
        ITV_CODIGO_PK INT NOT NULL AUTO_INCREMENT,
        ITV_CHAVE     VARCHAR(80)  NOT NULL,
        ITV_DESCRICAO VARCHAR(120) NOT NULL,
        ITV_ATIVO     CHAR(1)     NOT NULL DEFAULT 'S',
        ITV_ORDEM     INT         NOT NULL DEFAULT 0,
        PRIMARY KEY (ITV_CODIGO_PK),
        UNIQUE KEY uq_chave (ITV_CHAVE)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Adiciona colunas novas se a tabela já existia sem elas
    if (!column_exists($pdo, 'tb_itens_vistoria', 'ITV_ATIVO'))
        $pdo->exec("ALTER TABLE tb_itens_vistoria ADD COLUMN ITV_ATIVO CHAR(1) NOT NULL DEFAULT 'S'");
    if (!column_exists($pdo, 'tb_itens_vistoria', 'ITV_ORDEM'))
        $pdo->exec("ALTER TABLE tb_itens_vistoria ADD COLUMN ITV_ORDEM INT NOT NULL DEFAULT 0");

    // Cria tb_vistoria_itens se não existir
    $pdo->exec("CREATE TABLE IF NOT EXISTS tb_vistoria_itens (
        VVI_CODIGO_PK INT NOT NULL AUTO_INCREMENT,
        VIS_CODIGO_FK INT NOT NULL,
        ITV_CODIGO_FK INT NOT NULL,
        PRIMARY KEY (VVI_CODIGO_PK),
        UNIQUE KEY uq_vis_item (VIS_CODIGO_FK, ITV_CODIGO_FK)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Cria tb_vistoria se não existir
    $pdo->exec("CREATE TABLE IF NOT EXISTS tb_vistoria (
        VIS_CODIGO_PK    INT NOT NULL AUTO_INCREMENT,
        VEI_CODIGO_FK    INT NOT NULL,
        VIS_CODIGO_VIDRO VARCHAR(60) NULL,
        VIS_PNEUS        VARCHAR(30) NULL,
        VIS_OBSERVACAO   TEXT NULL,
        PRIMARY KEY (VIS_CODIGO_PK)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Seed: popula itens padrão se a tabela estiver vazia
    $count = (int)$pdo->query("SELECT COUNT(*) FROM tb_itens_vistoria")->fetchColumn();
    if ($count === 0) {
        $defaults = [
            ['ar_condicionado',          'Ar condicionado',            1],
            ['banco_couro',              'Banco de couro',             2],
            ['direcao_hidraulica',       'Direção hidráulica',         3],
            ['vidro_eletrico_dianteiro', 'Vidro elétrico dianteiro',   4],
            ['vidro_eletrico_traseiro',  'Vidro elétrico traseiro',    5],
            ['trava_eletrica',           'Trava elétrica',             6],
            ['teto_solar',               'Teto solar',                 7],
            ['alarme',                   'Alarme',                     8],
            ['rastreador',               'Rastreador',                 9],
            ['central_multimidia',       'Central multimídia',         10],
            ['computador_bordo',         'Computador de bordo',        11],
            ['sensor_estacionamento',    'Sensor de estacionamento',   12],
            ['airbags',                  'Airbags',                    13],
            ['roda_liga_leve',           'Roda liga leve',             14],
            ['tracao_4x4',               'Tração 4x4',                 15],
            ['cambio_automatico',        'Câmbio automático',          16],
            ['pelicula',                 'Película',                   17],
            ['desembacador',             'Desembaçador',               18],
            ['farol_milha',              'Farol de milha',             19],
            ['farol_neblina',            'Farol de neblina',           20],
            ['limpador_traseiro',        'Limpador traseiro',          21],
            ['antena_externa',           'Antena externa',             22],
            ['retrovisor_regulagem',     'Retrovisor c/ regulagem',    23],
            ['cinto_3p_dianteiro',       'Cinto 3pt dianteiro',        24],
            ['cinto_3p_traseiro',        'Cinto 3pt traseiro',         25],
            ['chave_codificada',         'Chave codificada',           26],
            ['calotas',                  'Calotas',                    27],
            ['cd_player',                'CD Player',                  28],
            ['santo_antonio',            'Santo antônio',              29],
            ['kit_gas',                  'Kit gás',                    30],
        ];
        $ins = $pdo->prepare("INSERT IGNORE INTO tb_itens_vistoria (ITV_CHAVE, ITV_DESCRICAO, ITV_ATIVO, ITV_ORDEM) VALUES (?,?,'S',?)");
        foreach ($defaults as [$chave, $desc, $ordem]) $ins->execute([$chave, $desc, $ordem]);
    }
}

function ensure_cancel_columns(PDO $pdo): void
{
    if (!column_exists($pdo, 'tb_contrato', 'CTR_DATA_CANCELAMENTO'))
        $pdo->exec("ALTER TABLE tb_contrato ADD COLUMN CTR_DATA_CANCELAMENTO DATE NULL DEFAULT NULL");
    if (!column_exists($pdo, 'tb_contrato', 'CTR_MOTIVO_CANCELAMENTO'))
        $pdo->exec("ALTER TABLE tb_contrato ADD COLUMN CTR_MOTIVO_CANCELAMENTO TEXT NULL DEFAULT NULL");
    if (!column_exists($pdo, 'tb_contrato', 'CTR_CANCELADO_POR'))
        $pdo->exec("ALTER TABLE tb_contrato ADD COLUMN CTR_CANCELADO_POR VARCHAR(150) NULL DEFAULT NULL");
}

function ensure_fipe_cod_columns(PDO $pdo): void
{
    if (!column_exists($pdo, 'tb_veiculo', 'VEI_FIPE_MARCA_COD'))
        $pdo->exec("ALTER TABLE tb_veiculo ADD COLUMN VEI_FIPE_MARCA_COD VARCHAR(20) NULL DEFAULT NULL");
    if (!column_exists($pdo, 'tb_veiculo', 'VEI_FIPE_MODELO_COD'))
        $pdo->exec("ALTER TABLE tb_veiculo ADD COLUMN VEI_FIPE_MODELO_COD VARCHAR(20) NULL DEFAULT NULL");
}

// Utilitário de compatibilidade — delega ao log geral e, opcionalmente, à tb_contrato_evento legada
function registrar_evento_contrato(PDO $pdo, string $rid, int $contratoId, ?int $veiculoId, string $tipo, ?int $usuarioId = null, ?string $motivo = null, ?string $obs = null): void
{
    // Grava no log geral
    log_evento($pdo, 'tb_contrato', $contratoId, $tipo, [
        'usuario_id'   => $usuarioId,
        'usuario_nome' => null,
        'motivo'       => $motivo,
        'obs'          => array_filter(['veiculo_id' => $veiculoId, 'obs' => $obs]),
    ]);

    // Mantém gravação na tabela legada apenas se ela já existir
    try {
        if (!table_exists($pdo, 'tb_contrato_evento')) return;
        $pdo->prepare("INSERT INTO tb_contrato_evento
                (EV_CONTRATO_FK, EV_VEICULO_FK, EV_TIPO, EV_DATA, EV_USUARIO_FK, EV_MOTIVO, EV_OBSERVACAO)
                VALUES (?,?,?,NOW(),?,?,?)")
            ->execute([$contratoId, $veiculoId, $tipo, $usuarioId, $motivo, $obs]);
        log_line($rid, 'Evento legado registrado', ['tipo' => $tipo, 'contratoId' => $contratoId]);
    } catch (Throwable $e) {
        log_line($rid, 'Falha ao registrar evento legado', ['err' => pdo_meta($e)]);
    }
}

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
}

log_line($RID, 'REQUEST IN', ['method' => $_SERVER['REQUEST_METHOD'] ?? null, 'uri' => $_SERVER['REQUEST_URI'] ?? null, 'get' => $_GET, 'post' => safe_post_snapshot(), 'files' => safe_files_snapshot()]);

// CSRF (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && function_exists('csrf_check')) {
    $csrfToken = $_POST['csrf'] ?? null;
    if ($csrfToken !== null && !csrf_check($csrfToken)) {
        log_line($RID, 'CSRF inválido');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'CSRF inválido.', 'rid' => $RID]);
        exit;
    }
}

$acao = $_GET['acao'] ?? ($_POST['acao'] ?? '');

/* ── Setup uma vez por request (DDL leve, usa tabela information_schema) ── */
try { ensure_vistoria_tables($pdo); } catch (Throwable $e) { log_line($RID, 'setup_vistoria falhou', ['err' => $e->getMessage()]); }
try { ensure_fipe_cod_columns($pdo); } catch (Throwable $e) { log_line($RID, 'setup_fipe_cod falhou', ['err' => $e->getMessage()]); }

/* ============== GET: listar_itens_vistoria ============== */
if ($acao === 'listar_itens_vistoria' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $rows = $pdo->query("SELECT ITV_CODIGO_PK, ITV_CHAVE, ITV_DESCRICAO FROM tb_itens_vistoria WHERE ITV_ATIVO = 'S' ORDER BY ITV_ORDEM, ITV_DESCRICAO")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'items' => $rows], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao listar itens.']);
    }
    exit;
}

/* ============== POST: adicionar_item_vistoria ============== */
if ($acao === 'adicionar_item_vistoria' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nome = trim($_POST['nome'] ?? '');
        if ($nome === '') { echo json_encode(['success' => false, 'message' => 'Nome obrigatório.']); exit; }
        $chave = preg_replace('/[^a-z0-9_]/', '_', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $nome) ?: $nome));
        $chave = trim(preg_replace('/_+/', '_', $chave), '_');
        if ($chave === '') $chave = 'item_' . time();
        $ordem = (int)($pdo->query("SELECT COALESCE(MAX(ITV_ORDEM),0)+1 FROM tb_itens_vistoria")->fetchColumn());
        $st = $pdo->prepare("INSERT INTO tb_itens_vistoria (ITV_CHAVE, ITV_DESCRICAO, ITV_ATIVO, ITV_ORDEM) VALUES (?,?,'S',?)
                             ON DUPLICATE KEY UPDATE ITV_DESCRICAO=VALUES(ITV_DESCRICAO), ITV_ATIVO='S'");
        $st->execute([$chave, $nome, $ordem]);
        $id = (int)$pdo->lastInsertId();
        if ($id === 0) {
            $id = (int)$pdo->prepare("SELECT ITV_CODIGO_PK FROM tb_itens_vistoria WHERE ITV_CHAVE=?")->execute([$chave]) ? (int)$pdo->query("SELECT ITV_CODIGO_PK FROM tb_itens_vistoria WHERE ITV_CHAVE='$chave' LIMIT 1")->fetchColumn() : 0;
        }
        echo json_encode(['success' => true, 'id' => $id, 'chave' => $chave, 'nome' => $nome], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao adicionar item: ' . $e->getMessage()]);
    }
    exit;
}

/* ============== POST: remover_item_vistoria ============== */
if ($acao === 'remover_item_vistoria' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['success' => false, 'message' => 'ID inválido.']); exit; }
        $pdo->prepare("UPDATE tb_itens_vistoria SET ITV_ATIVO = 'N' WHERE ITV_CODIGO_PK = ?")->execute([$id]);
        echo json_encode(['success' => true]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao remover item.']);
    }
    exit;
}

/* ============== GET: verificar_placa ============== */
if (($acao === 'verificar_placa') && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $placa = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $_GET['placa'] ?? ''));
    $selfId = (int)($_GET['veiculo_id'] ?? 0); // <<< permite ignorar a própria placa em edição
    if ($placa === '') {
        echo json_encode(['success' => false, 'message' => 'Placa inválida.']);
        exit;
    }
    try {
        $sql = "SELECT v.VEI_CODIGO_PK, v.VEI_PLACA, p.PES_CODIGO_PK, p.PES_NOME, p.PES_FONE_CELULAR_1
          FROM tb_veiculo v LEFT JOIN tb_pessoa p ON p.PES_CODIGO_PK=v.PES_CODIGO_FK
          WHERE v.VEI_PLACA=:placa";
        if ($selfId > 0) $sql .= " AND v.VEI_CODIGO_PK<>:self";
        $sql .= " LIMIT 1";
        $st = $pdo->prepare($sql);
        $params = [':placa' => $placa];
        if ($selfId > 0) $params[':self'] = $selfId;
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            echo json_encode([
                'success' => true,
                'existe' => true,
                'veiculo' => ['id' => (int)$row['VEI_CODIGO_PK'], 'placa' => $row['VEI_PLACA']],
                'associado' => ['id' => $row['PES_CODIGO_PK'] ? (int)$row['PES_CODIGO_PK'] : null, 'nome' => $row['PES_NOME'] ?? null, 'fone1' => $row['PES_FONE_CELULAR_1'] ?? null]
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['success' => true, 'existe' => false]);
        }
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao consultar placa.']);
    }
    exit;
}

/* ============== GET: listar_por_associado ============== */
if ($acao === 'listar_por_associado' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $pessoaId = (int)($_GET['pessoa_id'] ?? 0);
    if ($pessoaId <= 0) {
        echo json_encode([]);
        exit;
    }
    try {

        if (table_exists($pdo, 'tb_contrato')) {
            $sql = "SELECT v.VEI_CODIGO_PK, v.VEI_PLACA, v.VEI_MARCA, v.VEI_MODELO, v.VEI_ANO_FABRICACAO,
               c.CTR_CODIGO_PK, c.VEI_CODIGO_FK, c.CTR_STATUS
        FROM tb_veiculo v
        LEFT JOIN (
          SELECT t1.* FROM tb_contrato t1
          JOIN (SELECT VEI_CODIGO_FK, MAX(CTR_CODIGO_PK) max_ctr FROM tb_contrato GROUP BY VEI_CODIGO_FK) t2
            ON t2.VEI_CODIGO_FK=t1.VEI_CODIGO_FK AND t2.max_ctr=t1.CTR_CODIGO_PK
        ) c ON c.VEI_CODIGO_FK=v.VEI_CODIGO_PK
        WHERE v.PES_CODIGO_FK=:id
        ORDER BY v.VEI_CODIGO_PK DESC";
        }

        $st = $pdo->prepare($sql);
        $st->execute([':id' => $pessoaId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        // adiciona status formatado (texto + badge Metronic)
        foreach ($rows as &$r) {
            $code = strtoupper($r['CTR_STATUS'] ?? '');
            if ($code === 'A') {
                $r['ctr_status_text']  = 'ATIVO';
                $r['ctr_status_badge'] = '<span class="m-badge m-badge--success m-badge--wide">ATIVO</span>';
            } elseif ($code === 'C') {
                $r['ctr_status_text']  = 'CANCELADO';
                $r['ctr_status_badge'] = '<span class="m-badge m-badge--danger m-badge--wide">CANCELADO</span>';
            } else {
                // quando não há contrato ou status desconhecido
                $r['ctr_status_text']  = '—';
                $r['ctr_status_badge'] = '<span class="m-badge m-badge--metal m-badge--wide">—</span>';
            }
        }
        unset($r);

        echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => true, 'message' => 'DB error', 'rid' => $RID]);
    }
    exit;
}

/* ============== POST: cadastrar ============== */
if ($acao === 'cadastrar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        $pessoaId         = (int)($_POST['codigo_associado'] ?? 0);
        $placa            = strtoupper(preg_replace('/[^A-Z0-9]/i', '', post_str('placa')));
        $chassi           = strtoupper(trim(post_str('chassi')));
        $renavam          = only_digits(post_str('renavam'));

        $marca            = post_str('marca') ?: post_str('marcas');
        $modelo           = post_str('modelo') ?: post_str('modelos');
        $fipeMarcaCod     = post_str('fipe_marca_cod') ?: null;
        $fipeModeloCod    = post_str('fipe_modelo_cod') ?: null;
        $anoFab           = post_str('ano') ?: '';
        $anoModelo        = post_str('anoModelo') ?: '';
        $combustivel      = post_str('combustivel');
        $codigoFipe       = post_str('codigoFipe');
        $cambio           = post_str('cambio');
        $tipoVeiculo      = post_str('tipoVeiculo');

        $ufCarro          = strtoupper(post_str('ufCarro'));
        $cidadeCarro      = post_str('cidadeCarro');

        $corId            = (int)($_POST['cor'] ?? 0);
        $cpfProprietario  = post_str('cpf_proprietario') ? only_digits(post_str('cpf_proprietario')) : null;
        $nomeProprietario = post_str('nome_proprietario');

        if ($pessoaId <= 0 || $placa === '' || $chassi === '') {
            throw new RuntimeException('Obrigatórios ausentes (associado, placa ou chassi).');
        }

        // INSERT VEÍCULO (sem valores financeiros)
        $sqlVeiculo = "INSERT INTO tb_veiculo (
      VEI_PLACA, VEI_UF, VEI_CIDADE, VEI_MARCA, VEI_MODELO,
      VEI_ANO_FABRICACAO, VEI_ANO_MODELO, VEI_CODIGO_FIPE,
      VEI_CHASSI, VEI_RENAVAM, VEI_CPF_CNPJ_PROPRIETARIO, VEI_NOME_PROPRIETARIO,
      VEI_COD_COR_FK, VEI_STATUS, VEI_COMBUSTIVEL, VEI_CAMBIO, PES_CODIGO_FK, VEI_TIPO,
      VEI_FIPE_MARCA_COD, VEI_FIPE_MODELO_COD
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $pdo->prepare($sqlVeiculo)->execute([
            $placa,
            $ufCarro ?: null,
            $cidadeCarro ?: null,
            $marca ?: null,
            $modelo ?: null,
            $anoFab ?: null,
            $anoModelo ?: null,
            $codigoFipe ?: null,
            $chassi ?: null,
            $renavam ?: null,
            $cpfProprietario ?: null,
            $nomeProprietario ?: null,
            $corId > 0 ? $corId : null,
            'A',
            $combustivel ?: null,
            $cambio ?: null,
            $pessoaId,
            $tipoVeiculo ?: null,
            $fipeMarcaCod,
            $fipeModeloCod
        ]);
        $veiculoId = (int)$pdo->lastInsertId();

        // Vistoria
        $vistoriaId = null;
        $pdo->prepare("INSERT INTO tb_vistoria (VEI_CODIGO_FK, VIS_CODIGO_VIDRO, VIS_PNEUS, VIS_OBSERVACAO) VALUES (?,?,?,?)")
            ->execute([$veiculoId, post_str('codigo_vidro') ?: null, post_str('pneus') ?: null, post_str('observacao_vistoria') ?: null]);
        $vistoriaId = (int)$pdo->lastInsertId();
        $checkedIds = array_map('intval', (array)($_POST['vis_checked'] ?? []));
        if ($vistoriaId && $checkedIds) {
            $insVit = $pdo->prepare("INSERT IGNORE INTO tb_vistoria_itens (VIS_CODIGO_FK, ITV_CODIGO_FK) VALUES (?,?)");
            foreach (array_filter($checkedIds) as $itemId) $insVit->execute([$vistoriaId, $itemId]);
        }

        // CONTRATO (insere só se houver dados)
        if (table_exists($pdo, 'tb_contrato')) {
            $grupoId          = (int)($_POST['grupo'] ?? 0);
            $valorAdesao      = try_decimal('adesao');
            $valorMensal      = try_decimal('mensalidade');
            $comboId          = (int)($_POST['combo'] ?? 0);
            $valorCombo       = try_decimal('valorCombo');
            $rastreadorId     = (int)($_POST['rastreador'] ?? 0);
            $valorRastreador  = try_decimal('valorRastreador');
            $valorTotal       = try_decimal('totalFinal');
            $tipoBoleto       = post_str('tipoBoleto') ?: null;

            $valorVeiculo     = try_decimal('valor');
            $valorCobertura   = try_decimal('valorCobertura');
            $vistoriadoId     = (int)($_POST['vistoriado_fk'] ?? 0);

            if (
                $grupoId || $comboId || $rastreadorId || $valorAdesao !== null || $valorMensal !== null ||
                $valorCombo !== null || $valorRastreador !== null || $valorTotal !== null || $tipoBoleto ||
                $valorVeiculo !== null || $valorCobertura !== null || $vistoriadoId
            ) {
                $sqlC = "INSERT INTO tb_contrato (
          PES_CODIGO_FK, VEI_CODIGO_FK, GRU_CODIGO_FK, COM_CODIGO_FK, CON_RASTREADOR_FK,
          CTR_VALOR_ADESAO, CTR_VALOR_MENSALIDADE, CTR_VALOR_COMBO,
          CTR_VALOR_VEICULO, CTR_VALOR_COBERTURA,
          CON_VALOR_RASTREADOR, CTR_VALOR_TOTAL, CTR_TIPO_BOLETO, CTR_VISTORIADO_FK
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                $pdo->prepare($sqlC)->execute([
                    $pessoaId,
                    $veiculoId,
                    ($grupoId ?: null),
                    ($comboId ?: null),
                    ($rastreadorId ?: null),
                    $valorAdesao,
                    $valorMensal,
                    $valorCombo,
                    $valorVeiculo,
                    $valorCobertura,
                    $valorRastreador,
                    $valorTotal,
                    $tipoBoleto,
                    ($vistoriadoId ?: null)
                ]);

                // >>> ADDED: registra evento CRIACAO do contrato inserido
                $contratoId = (int)$pdo->lastInsertId();
                if ($contratoId > 0) {
                    $usuarioId = $_SESSION['usuarioSession'] ?? ($_SESSION['usuario_id'] ?? null);
                    registrar_evento_contrato($pdo, $RID, $contratoId, $veiculoId, 'CRIACAO', is_numeric($usuarioId) ? (int)$usuarioId : null, null, 'Contrato criado via cadastro de veículo');
                }
            }
        }

        // Imagens
        if (table_exists($pdo, 'tb_imagens') && isset($_FILES['imagens']) && is_array($_FILES['imagens']['name'])) {
            $baseFs = defined('PATH_UPLOAD') ? PATH_UPLOAD : (realpath(__DIR__ . '/../uploads') ?: __DIR__ . '/../uploads');
            if (!is_dir($baseFs)) @mkdir($baseFs, 0775, true);
            $placaSafe = preg_replace('/[^A-Z0-9]/i', '', $placa);
            $folder = sprintf('%s_%d_%s_%s', $placaSafe, $pessoaId, date('Ymd_His'), substr(bin2hex(random_bytes(4)), 0, 8));
            $targetDirFs = $baseFs . DIRECTORY_SEPARATOR . $folder;
            if (!is_dir($targetDirFs)) @mkdir($targetDirFs, 0775, true);
            $publicPrefix = rtrim((defined('UPLOAD_URL') ? UPLOAD_URL : (rtrim(BASE_URL ?? '', '/') . '/uploads')), '/') . '/' . $folder . '/';

            $allowMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $allowExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $insImg = $pdo->prepare("INSERT INTO tb_imagens (IMG_VEICULO_FK, IMG_CAMINHO, IMG_CHASSI) VALUES (?,?,?)");

            $names = $_FILES['imagens']['name'];
            $tmps = $_FILES['imagens']['tmp_name'];
            $errs = $_FILES['imagens']['error'];
            $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;

            $chassiMarcados = [];
            if (isset($_POST['is_chassi']) && is_array($_POST['is_chassi'])) {
                foreach ($_POST['is_chassi'] as $nm) $chassiMarcados[$nm] = true;
            }

            for ($i = 0; $i < count($names); $i++) {
                $orig = (string)($names[$i] ?? '');
                $tmp = (string)($tmps[$i] ?? '');
                $err = (int)($errs[$i] ?? UPLOAD_ERR_NO_FILE);
                if ($orig === '' || $err !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) continue;
                $mime = $finfo ? @finfo_file($finfo, $tmp) : null;
                if ($mime && !in_array($mime, $allowMime, true)) continue;
                $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowExt, true)) continue;

                $newName = sprintf('%s_%02d_%s.%s', $placaSafe, $i + 1, substr(bin2hex(random_bytes(3)), 0, 6), $ext);
                $destFs = $targetDirFs . DIRECTORY_SEPARATOR . $newName;
                $destUrl = $publicPrefix . $newName;
                if (!@move_uploaded_file($tmp, $destFs)) {
                    if (!@rename($tmp, $destFs)) {
                        if (!@copy($tmp, $destFs)) continue;
                        @unlink($tmp);
                    }
                }
                @chmod($destFs, 0644);
                $isChassi = (isset($chassiMarcados[$orig]) || isset($chassiMarcados[$newName])) ? 'SIM' : 'NÃO';
                $insImg->execute([$veiculoId, $destUrl, $isChassi]);
            }
            if ($finfo) @finfo_close($finfo);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Veículo cadastrado com sucesso', 'veiculo_id' => $veiculoId, 'vistoria_id' => $vistoriaId, 'rid' => $RID]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        log_line($RID, 'cadastrar ERRO', ['msg' => $e->getMessage(), 'code' => $e->getCode(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
        if ($e instanceof PDOException && $e->getCode() === '23000') {
            $em  = $e->getMessage();
            $msg = 'Não foi possível salvar os dados.';
            if (stripos($em, 'VEI_PLACA')   !== false) $msg = 'Placa já cadastrada.';
            elseif (stripos($em, 'VEI_CHASSI')  !== false) $msg = 'Chassi já cadastrado.';
            elseif (stripos($em, 'VEI_RENAVAM') !== false) $msg = 'RENAVAM já cadastrado.';
        } elseif ($e instanceof PDOException) {
            $msg = 'Não foi possível salvar os dados.';
        } elseif ($e instanceof RuntimeException) {
            $msg = $e->getMessage();
        } else {
            $msg = 'Não foi possível salvar os dados.';
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $msg, 'rid' => $RID]);
    }
    exit;
}

/* ============== GET: obter (detalhes para edição) ============== */
if ($acao === 'obter' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $contratoId = (int)($_GET['contrato_id'] ?? 0);
        $id         = (int)($_GET['id'] ?? 0);
        if ($contratoId > 0) {
            $stc = $pdo->prepare("SELECT VEI_CODIGO_FK FROM tb_contrato WHERE CTR_CODIGO_PK=:cid LIMIT 1");
            $stc->execute([':cid' => $contratoId]);
            $id = (int)($stc->fetchColumn() ?: 0);
        }
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            exit;
        }

        $st = $pdo->prepare("SELECT * FROM tb_veiculo WHERE VEI_CODIGO_PK=:id LIMIT 1");
        $st->execute([':id' => $id]);
        $v = $st->fetch(PDO::FETCH_ASSOC);
        if (!$v) {
            echo json_encode(['success' => false, 'message' => 'Veículo não encontrado']);
            exit;
        }

        $vist = null;
        $vist_itens = [];
        $sv = $pdo->prepare("SELECT * FROM tb_vistoria WHERE VEI_CODIGO_FK=:id ORDER BY VIS_CODIGO_PK DESC LIMIT 1");
        $sv->execute([':id' => $id]);
        $vist = $sv->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($vist) {
            $spi = $pdo->prepare("SELECT vi.ITV_CODIGO_FK, i.ITV_CHAVE FROM tb_vistoria_itens vi JOIN tb_itens_vistoria i ON i.ITV_CODIGO_PK=vi.ITV_CODIGO_FK WHERE vi.VIS_CODIGO_FK=:vis");
            $spi->execute([':vis' => $vist['VIS_CODIGO_PK']]);
            foreach ($spi->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $vist_itens[] = ['id' => (int)$r['ITV_CODIGO_FK'], 'chave' => $r['ITV_CHAVE']];
            }
        }

        $contr = null;
        if (table_exists($pdo, 'tb_contrato')) {
            $sc = $pdo->prepare("SELECT * FROM tb_contrato WHERE VEI_CODIGO_FK=:id ORDER BY CTR_CODIGO_PK DESC LIMIT 1");
            $sc->execute([':id' => $id]);
            $contr = $sc->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $imgs = [];
        if (table_exists($pdo, 'tb_imagens')) {
            $si = $pdo->prepare("SELECT IMG_CAMINHO, IMG_CHASSI FROM tb_imagens WHERE IMG_VEICULO_FK=:id ORDER BY IMG_CODIGO_PK DESC");
            $si->execute([':id' => $id]);
            foreach ($si->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $imgs[] = ['url' => $r['IMG_CAMINHO'], 'chassi' => $r['IMG_CHASSI']];
            }
        }

        echo json_encode(['success' => true, 'veiculo' => $v, 'vistoria' => $vist, 'vistoria_itens' => $vist_itens, 'contrato' => $contr, 'imagens' => $imgs], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao obter dados.']);
    }
    exit;
}

/* ============== POST: excluir_imagem ============== */
if ($acao === 'excluir_imagem' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $veiculoId = (int)($_POST['veiculo_id'] ?? 0);
        $caminho   = trim($_POST['caminho'] ?? '');
        if ($veiculoId <= 0 || $caminho === '') {
            echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos']);
            exit;
        }
        $del = $pdo->prepare("DELETE FROM tb_imagens WHERE IMG_VEICULO_FK=? AND IMG_CAMINHO=? LIMIT 1");
        $del->execute([$veiculoId, $caminho]);
        $fs = $_SERVER['DOCUMENT_ROOT'] ? $_SERVER['DOCUMENT_ROOT'] . parse_url($caminho, PHP_URL_PATH) : null;
        if ($fs && file_exists($fs)) @unlink($fs);
        echo json_encode(['success' => true]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Falha ao excluir']);
    }
    exit;
}

/* ============== POST: atualizar ============== */
if ($acao === 'atualizar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // resolve veiculo_id (pode vir por contrato)
        $veiculoId = (int)($_POST['veiculo_id'] ?? $_POST['codigo_veiculo'] ?? 0);
        if ($veiculoId <= 0) {
            $contratoId = (int)($_POST['contrato_id'] ?? $_POST['codigo_contrato'] ?? 0);
            if ($contratoId > 0) {
                $st = $pdo->prepare("SELECT VEI_CODIGO_FK FROM tb_contrato WHERE CTR_CODIGO_PK=? LIMIT 1");
                $st->execute([$contratoId]);
                $veiculoId = (int)($st->fetchColumn() ?: 0);
            }
        }
        if ($veiculoId <= 0) throw new RuntimeException('ID do veículo não localizado.');

        // pega pessoa do POST ou da base (para evitar 500)
        $pessoaId = (int)($_POST['codigo_associado'] ?? 0);
        if ($pessoaId <= 0) {
            $stp = $pdo->prepare("SELECT PES_CODIGO_FK FROM tb_veiculo WHERE VEI_CODIGO_PK=? LIMIT 1");
            $stp->execute([$veiculoId]);
            $pessoaId = (int)($stp->fetchColumn() ?: 0);
        }

        $placa            = strtoupper(preg_replace('/[^A-Z0-9]/i', '', post_str('placa')));
        $chassi           = strtoupper(trim(post_str('chassi')));
        $renavam          = only_digits(post_str('renavam'));

        $marca            = post_str('marca') ?: post_str('marcas');
        $modelo           = post_str('modelo') ?: post_str('modelos');
        $fipeMarcaCod     = post_str('fipe_marca_cod') ?: null;
        $fipeModeloCod    = post_str('fipe_modelo_cod') ?: null;
        $anoFab           = post_str('ano') ?: '';
        $anoModelo        = post_str('anoModelo') ?: '';
        $combustivel      = post_str('combustivel');
        $codigoFipe       = post_str('codigoFipe');
        $cambio           = post_str('cambio');
        $tipoVeiculo      = post_str('tipoVeiculo');

        $ufCarro          = strtoupper(post_str('ufCarro'));
        $cidadeCarro      = post_str('cidadeCarro');

        $corId            = (int)($_POST['cor'] ?? 0);
        $cpfProprietario  = post_str('cpf_proprietario') ? only_digits(post_str('cpf_proprietario')) : null;
        $nomeProprietario = post_str('nome_proprietario');

        if ($pessoaId <= 0 || $placa === '' || $chassi === '') {
            throw new RuntimeException('Obrigatórios ausentes (associado, placa ou chassi).');
        }

        // UPDATE veículo (sem valores financeiros)
        $sqlUp = "UPDATE tb_veiculo SET
      VEI_PLACA=?, VEI_UF=?, VEI_CIDADE=?, VEI_MARCA=?, VEI_MODELO=?,
      VEI_ANO_FABRICACAO=?, VEI_ANO_MODELO=?, VEI_CODIGO_FIPE=?,
      VEI_CHASSI=?, VEI_RENAVAM=?, VEI_CPF_CNPJ_PROPRIETARIO=?, VEI_NOME_PROPRIETARIO=?,
      VEI_COD_COR_FK=?, VEI_COMBUSTIVEL=?, VEI_CAMBIO=?, VEI_TIPO=?,
      VEI_FIPE_MARCA_COD=?, VEI_FIPE_MODELO_COD=?
      WHERE VEI_CODIGO_PK=? AND PES_CODIGO_FK=?";
        $pdo->prepare($sqlUp)->execute([
            $placa,
            $ufCarro ?: null,
            $cidadeCarro ?: null,
            $marca ?: null,
            $modelo ?: null,
            $anoFab ?: null,
            $anoModelo ?: null,
            $codigoFipe ?: null,
            $chassi ?: null,
            $renavam ?: null,
            $cpfProprietario ?: null,
            $nomeProprietario ?: null,
            $corId > 0 ? $corId : null,
            $combustivel ?: null,
            $cambio ?: null,
            $tipoVeiculo ?: null,
            $fipeMarcaCod,
            $fipeModeloCod,
            $veiculoId,
            $pessoaId
        ]);

        // Vistoria (upsert)
        $sv = $pdo->prepare("SELECT VIS_CODIGO_PK FROM tb_vistoria WHERE VEI_CODIGO_FK=? ORDER BY VIS_CODIGO_PK DESC LIMIT 1");
        $sv->execute([$veiculoId]);
        $vistoriaId = (int)($sv->fetchColumn() ?: 0);
        $codigoVidro = post_str('codigo_vidro') ?: null;
        $pneus       = post_str('pneus') ?: null;
        $obs         = post_str('observacao_vistoria') ?: null;
        if ($vistoriaId > 0) {
            $pdo->prepare("UPDATE tb_vistoria SET VIS_CODIGO_VIDRO=?, VIS_PNEUS=?, VIS_OBSERVACAO=? WHERE VIS_CODIGO_PK=?")
                ->execute([$codigoVidro, $pneus, $obs, $vistoriaId]);
        } else {
            $pdo->prepare("INSERT INTO tb_vistoria (VEI_CODIGO_FK, VIS_CODIGO_VIDRO, VIS_PNEUS, VIS_OBSERVACAO) VALUES (?,?,?,?)")
                ->execute([$veiculoId, $codigoVidro, $pneus, $obs]);
            $vistoriaId = (int)$pdo->lastInsertId();
        }
        if ($vistoriaId) {
            $pdo->prepare("DELETE FROM tb_vistoria_itens WHERE VIS_CODIGO_FK=?")->execute([$vistoriaId]);
            $checkedIds = array_map('intval', (array)($_POST['vis_checked'] ?? []));
            if ($checkedIds) {
                $insVit = $pdo->prepare("INSERT IGNORE INTO tb_vistoria_itens (VIS_CODIGO_FK, ITV_CODIGO_FK) VALUES (?,?)");
                foreach (array_filter($checkedIds) as $itemId) $insVit->execute([$vistoriaId, $itemId]);
            }
        }

        // ===== CONTRATO: AGORA PRIORIZA UPDATE =====
        if (table_exists($pdo, 'tb_contrato')) {
            $grupoId          = (int)($_POST['grupo'] ?? 0);
            $valorAdesao      = try_decimal('adesao');
            $valorMensal      = try_decimal('mensalidade');
            $comboId          = (int)($_POST['combo'] ?? 0);
            $valorCombo       = try_decimal('valorCombo');
            $rastreadorId     = (int)($_POST['rastreador'] ?? 0);
            $valorRastreador  = try_decimal('valorRastreador');
            $valorTotal       = try_decimal('totalFinal');
            $tipoBoleto       = post_str('tipoBoleto') ?: null;

            $valorVeiculo     = try_decimal('valor');
            $valorCobertura   = try_decimal('valorCobertura');
            $vistoriadoId     = (int)($_POST['vistoriado_fk'] ?? 0);

            $temDadosContrato =
                $grupoId || $comboId || $rastreadorId || $valorAdesao !== null || $valorMensal !== null ||
                $valorCombo !== null || $valorRastreador !== null || $valorTotal !== null || $tipoBoleto ||
                $valorVeiculo !== null || $valorCobertura !== null || $vistoriadoId;

            if ($temDadosContrato) {
                // tenta obter o contrato_id vindo do POST; se não vier, pega o mais recente do veículo
                $contratoId = (int)($_POST['contrato_id'] ?? $_POST['codigo_contrato'] ?? 0);
                if ($contratoId <= 0) {
                    $sc = $pdo->prepare("SELECT CTR_CODIGO_PK FROM tb_contrato WHERE VEI_CODIGO_FK=? ORDER BY CTR_CODIGO_PK DESC LIMIT 1");
                    $sc->execute([$veiculoId]);
                    $contratoId = (int)($sc->fetchColumn() ?: 0);
                }

                if ($contratoId > 0) {
                    // >>> UPDATE existente
                    $up = $pdo->prepare("UPDATE tb_contrato SET
                        PES_CODIGO_FK=?,
                        VEI_CODIGO_FK=?,
                        GRU_CODIGO_FK=?,
                        COM_CODIGO_FK=?,
                        CON_RASTREADOR_FK=?,
                        CTR_VALOR_ADESAO=?,
                        CTR_VALOR_MENSALIDADE=?,
                        CTR_VALOR_COMBO=?,
                        CTR_VALOR_VEICULO=?,
                        CTR_VALOR_COBERTURA=?,
                        CON_VALOR_RASTREADOR=?,
                        CTR_VALOR_TOTAL=?,
                        CTR_TIPO_BOLETO=?,
                        CTR_VISTORIADO_FK=?
                      WHERE CTR_CODIGO_PK=?");
                    $up->execute([
                        $pessoaId,
                        $veiculoId,
                        ($grupoId ?: null),
                        ($comboId ?: null),
                        ($rastreadorId ?: null),
                        $valorAdesao,
                        $valorMensal,
                        $valorCombo,
                        $valorVeiculo,
                        $valorCobertura,
                        $valorRastreador,
                        $valorTotal,
                        $tipoBoleto,
                        ($vistoriadoId ?: null),
                        $contratoId
                    ]);
                } else {
                    // >>> Não existe contrato anterior — cria um novo
                    $ins = $pdo->prepare("INSERT INTO tb_contrato (
                        PES_CODIGO_FK, VEI_CODIGO_FK, GRU_CODIGO_FK, COM_CODIGO_FK, CON_RASTREADOR_FK,
                        CTR_VALOR_ADESAO, CTR_VALOR_MENSALIDADE, CTR_VALOR_COMBO,
                        CTR_VALOR_VEICULO, CTR_VALOR_COBERTURA,
                        CON_VALOR_RASTREADOR, CTR_VALOR_TOTAL, CTR_TIPO_BOLETO, CTR_VISTORIADO_FK
                    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                    $ins->execute([
                        $pessoaId,
                        $veiculoId,
                        ($grupoId ?: null),
                        ($comboId ?: null),
                        ($rastreadorId ?: null),
                        $valorAdesao,
                        $valorMensal,
                        $valorCombo,
                        $valorVeiculo,
                        $valorCobertura,
                        $valorRastreador,
                        $valorTotal,
                        $tipoBoleto,
                        ($vistoriadoId ?: null)
                    ]);
                    $contratoId = (int)$pdo->lastInsertId();

                    // >>> ADDED: registra evento CRIACAO para o novo contrato criado no fluxo de atualização
                    if ($contratoId > 0) {
                        $usuarioId = $_SESSION['usuarioSession'] ?? ($_SESSION['usuario_id'] ?? null);
                        registrar_evento_contrato($pdo, $RID, $contratoId, $veiculoId, 'CRIACAO', is_numeric($usuarioId) ? (int)$usuarioId : null, null, 'Contrato criado via atualização de veículo');
                    }
                }
            }
        }

        // novas imagens (anexar)
        if (table_exists($pdo, 'tb_imagens') && isset($_FILES['imagens']) && is_array($_FILES['imagens']['name'])) {
            $baseFs = defined('PATH_UPLOAD') ? PATH_UPLOAD : (realpath(__DIR__ . '/../uploads') ?: __DIR__ . '/../uploads');
            if (!is_dir($baseFs)) @mkdir($baseFs, 0775, true);
            // obter placa e pessoa para o nome da pasta
            $row = $pdo->prepare("SELECT VEI_PLACA, PES_CODIGO_FK FROM tb_veiculo WHERE VEI_CODIGO_PK=?");
            $row->execute([$veiculoId]);
            $r = $row->fetch(PDO::FETCH_ASSOC) ?: ['VEI_PLACA' => '', 'PES_CODIGO_FK' => 0];
            $placaSafe = preg_replace('/[^A-Z0-9]/i', '', $r['VEI_PLACA'] ?? '');
            $pessoaIdForFolder = (int)($r['PES_CODIGO_FK'] ?? 0);

            $folder = sprintf('%s_%d_%s_%s', $placaSafe, $pessoaIdForFolder, date('Ymd_His'), substr(bin2hex(random_bytes(4)), 0, 8));
            $targetDirFs = $baseFs . DIRECTORY_SEPARATOR . $folder;
            if (!is_dir($targetDirFs)) @mkdir($targetDirFs, 0775, true);
            $publicPrefix = rtrim((defined('UPLOAD_URL') ? UPLOAD_URL : (rtrim(BASE_URL ?? '', '/') . '/uploads')), '/') . '/' . $folder . '/';

            $allowMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $allowExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $insImg = $pdo->prepare("INSERT INTO tb_imagens (IMG_VEICULO_FK, IMG_CAMINHO, IMG_CHASSI) VALUES (?,?,?)");
            $names = $_FILES['imagens']['name'];
            $tmps = $_FILES['imagens']['tmp_name'];
            $errs = $_FILES['imagens']['error'];
            $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;

            $chassiMarcados = [];
            if (isset($_POST['is_chassi']) && is_array($_POST['is_chassi'])) {
                foreach ($_POST['is_chassi'] as $nm) $chassiMarcados[$nm] = true;
            }

            for ($i = 0; $i < count($names); $i++) {
                $orig = (string)($names[$i] ?? '');
                $tmp = (string)($tmps[$i] ?? '');
                $err = (int)($errs[$i] ?? UPLOAD_ERR_NO_FILE);
                if ($orig === '' || $err !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) continue;
                $mime = $finfo ? @finfo_file($finfo, $tmp) : null;
                if ($mime && !in_array($mime, $allowMime, true)) continue;
                $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowExt, true)) continue;
                $newName = sprintf('%s_%02d_%s.%s', $placaSafe, $i + 1, substr(bin2hex(random_bytes(3)), 0, 6), $ext);
                $destFs = $targetDirFs . DIRECTORY_SEPARATOR . $newName;
                $destUrl = $publicPrefix . $newName;
                if (!@move_uploaded_file($tmp, $destFs)) {
                    if (!@rename($tmp, $destFs)) {
                        if (!@copy($tmp, $destFs)) continue;
                        @unlink($tmp);
                    }
                }
                @chmod($destFs, 0644);
                $isChassi = (isset($chassiMarcados[$orig]) || isset($chassiMarcados[$newName])) ? 'SIM' : 'NÃO';
                $insImg->execute([$veiculoId, $destUrl, $isChassi]);
            }
            if ($finfo) @finfo_close($finfo);
        }

        $pdo->commit();
        log_line($RID, 'atualizar OK', ['veiculo_id' => $veiculoId, 'contrato_id' => $contratoId ?? null]);
        echo json_encode(['success' => true, 'message' => 'Veículo atualizado com sucesso', 'veiculo_id' => $veiculoId]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        log_line($RID, 'atualizar ERRO', ['msg' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Não foi possível atualizar o veículo.', 'rid' => $RID, 'debug' => $e->getMessage()]);
    }
    exit;
}

/* ============== POST: cancelar ============== */
if ($acao === 'cancelar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $contratoId = (int)($_POST['contrato_id'] ?? 0);
        if ($contratoId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Contrato inválido.', 'rid' => $RID]);
            exit;
        }

        $motivo = trim($_POST['motivo'] ?? '');
        if ($motivo === '') {
            echo json_encode(['success' => false, 'message' => 'O motivo do cancelamento é obrigatório.', 'rid' => $RID]);
            exit;
        }

        $dataCanc     = trim($_POST['data_evento'] ?? $_POST['data_cancelamento'] ?? '') ?: date('Y-m-d');
        $canceladoPor = trim($_POST['cancelado_por'] ?? ($_SESSION['SessUsuNome'] ?? ''));
        $usuarioId    = (int)($_SESSION['SessUsuCodigo'] ?? 0) ?: null;

        ensure_cancel_columns($pdo);

        $chk = $pdo->prepare("SELECT CTR_CODIGO_PK FROM tb_contrato WHERE CTR_CODIGO_PK = ? LIMIT 1");
        $chk->execute([$contratoId]);
        if (!$chk->fetchColumn()) {
            echo json_encode(['success' => false, 'message' => 'Contrato não encontrado.', 'rid' => $RID]);
            exit;
        }

        $pdo->beginTransaction();

        $up = $pdo->prepare("UPDATE tb_contrato SET
            CTR_STATUS               = 'C',
            CTR_DATA_CANCELAMENTO    = ?,
            CTR_MOTIVO_CANCELAMENTO  = ?,
            CTR_CANCELADO_POR        = ?
            WHERE CTR_CODIGO_PK = ?");
        $up->execute([$dataCanc, $motivo, $canceladoPor, $contratoId]);

        $pdo->commit();

        log_evento($pdo, 'tb_contrato', $contratoId, 'CANCELAMENTO', [
            'data_evento'  => $dataCanc,
            'usuario_id'   => $usuarioId,
            'usuario_nome' => $canceladoPor,
            'motivo'       => $motivo,
        ]);
        log_line($RID, 'Contrato cancelado', ['contrato_id' => $contratoId, 'por' => $canceladoPor]);
        echo json_encode(['success' => true, 'message' => 'Contrato cancelado com sucesso.', 'rid' => $RID]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        log_line($RID, 'Erro ao cancelar contrato', ['err' => $e->getMessage()]);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Não foi possível cancelar o contrato.', 'rid' => $RID, 'debug' => $e->getMessage()]);
    }
    exit;
}

/* ============== POST: reativar ============== */
if ($acao === 'reativar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $contratoId = (int)($_POST['contrato_id'] ?? 0);
        if ($contratoId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Contrato inválido.', 'rid' => $RID]);
            exit;
        }

        $motivo       = trim($_POST['motivo'] ?? '');
        $dataEvento   = trim($_POST['data_evento'] ?? '') ?: date('Y-m-d');
        $reativadoPor = trim($_POST['cancelado_por'] ?? ($_SESSION['SessUsuNome'] ?? ''));
        $usuarioId    = (int)($_SESSION['SessUsuCodigo'] ?? 0) ?: null;

        if ($motivo === '') {
            echo json_encode(['success' => false, 'message' => 'O motivo da reativação é obrigatório.', 'rid' => $RID]);
            exit;
        }

        ensure_cancel_columns($pdo);

        $chk = $pdo->prepare("SELECT CTR_CODIGO_PK FROM tb_contrato WHERE CTR_CODIGO_PK = ? LIMIT 1");
        $chk->execute([$contratoId]);
        if (!$chk->fetchColumn()) {
            echo json_encode(['success' => false, 'message' => 'Contrato não encontrado.', 'rid' => $RID]);
            exit;
        }

        $pdo->beginTransaction();

        $up = $pdo->prepare("UPDATE tb_contrato SET
            CTR_STATUS               = 'A',
            CTR_DATA_CANCELAMENTO    = NULL,
            CTR_MOTIVO_CANCELAMENTO  = NULL,
            CTR_CANCELADO_POR        = NULL
            WHERE CTR_CODIGO_PK = ?");
        $up->execute([$contratoId]);

        $pdo->commit();

        log_evento($pdo, 'tb_contrato', $contratoId, 'REATIVACAO', [
            'data_evento'  => $dataEvento,
            'usuario_id'   => $usuarioId,
            'usuario_nome' => $reativadoPor,
            'motivo'       => $motivo,
        ]);
        log_line($RID, 'Contrato reativado', ['contrato_id' => $contratoId, 'por' => $reativadoPor]);
        echo json_encode(['success' => true, 'message' => 'Contrato reativado com sucesso.', 'rid' => $RID]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        log_line($RID, 'Erro ao reativar contrato', ['err' => $e->getMessage()]);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Não foi possível reativar o contrato.', 'rid' => $RID, 'debug' => $e->getMessage()]);
    }
    exit;
}

/* ============== POST: transferir ============== */
if ($acao === 'transferir' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $contratoId      = (int)($_POST['contrato_id'] ?? 0);
        $veiculoId       = (int)($_POST['veiculo_id']  ?? 0);
        $destinatarioId  = (int)($_POST['destinatario_id'] ?? 0);
        $motivo          = trim($_POST['motivo'] ?? '');
        $dataEvento      = trim($_POST['data_evento'] ?? '') ?: date('Y-m-d');
        $transferidoPor  = trim($_POST['transferido_por'] ?? ($_SESSION['SessUsuNome'] ?? ''));
        $usuarioId       = (int)($_SESSION['SessUsuCodigo'] ?? 0) ?: null;

        if ($contratoId <= 0 || $veiculoId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Veículo/Contrato inválido.', 'rid' => $RID]);
            exit;
        }
        if ($destinatarioId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Selecione o associado de destino.', 'rid' => $RID]);
            exit;
        }
        if ($motivo === '') {
            echo json_encode(['success' => false, 'message' => 'O motivo da transferência é obrigatório.', 'rid' => $RID]);
            exit;
        }

        // Verifica existência do contrato e obtém origem
        $chkC = $pdo->prepare("SELECT PES_CODIGO_FK FROM tb_contrato WHERE CTR_CODIGO_PK = ? LIMIT 1");
        $chkC->execute([$contratoId]);
        $origemId = $chkC->fetchColumn();
        if ($origemId === false) {
            echo json_encode(['success' => false, 'message' => 'Contrato não encontrado.', 'rid' => $RID]);
            exit;
        }

        // Verifica existência do destinatário
        $chkD = $pdo->prepare("SELECT PES_CODIGO_PK, PES_NOME FROM tb_pessoa WHERE PES_CODIGO_PK = ? LIMIT 1");
        $chkD->execute([$destinatarioId]);
        $destRow = $chkD->fetch(PDO::FETCH_ASSOC);
        if (!$destRow) {
            echo json_encode(['success' => false, 'message' => 'Associado de destino não encontrado.', 'rid' => $RID]);
            exit;
        }

        if ((int)$origemId === $destinatarioId) {
            echo json_encode(['success' => false, 'message' => 'O associado de destino é o mesmo que o de origem.', 'rid' => $RID]);
            exit;
        }

        // Carrega dados do contrato atual para replicar no novo
        $ctrRow = $pdo->prepare("SELECT * FROM tb_contrato WHERE CTR_CODIGO_PK = ? LIMIT 1");
        $ctrRow->execute([$contratoId]);
        $ctr = $ctrRow->fetch(PDO::FETCH_ASSOC);
        if (!$ctr) {
            echo json_encode(['success' => false, 'message' => 'Contrato não encontrado.', 'rid' => $RID]);
            exit;
        }

        ensure_cancel_columns($pdo);

        $pdo->beginTransaction();

        // 1. Cancela contrato atual
        $pdo->prepare("UPDATE tb_contrato SET
                CTR_STATUS              = 'C',
                CTR_DATA_CANCELAMENTO   = ?,
                CTR_MOTIVO_CANCELAMENTO = ?,
                CTR_CANCELADO_POR       = ?
            WHERE CTR_CODIGO_PK = ?")
            ->execute([
                $dataEvento,
                'Transferência para: ' . $destRow['PES_NOME'] . '. ' . $motivo,
                $transferidoPor,
                $contratoId,
            ]);

        // 2. Cria novo contrato para o destinatário (copia valores do atual)
        $pdo->prepare("INSERT INTO tb_contrato (
                PES_CODIGO_FK, VEI_CODIGO_FK, GRU_CODIGO_FK, COM_CODIGO_FK,
                CON_RASTREADOR_FK, CTR_VALOR_ADESAO, CTR_VALOR_MENSALIDADE,
                CTR_VALOR_COMBO, CTR_VALOR_VEICULO, CTR_VALOR_COBERTURA,
                CON_VALOR_RASTREADOR, CTR_VALOR_TOTAL, CTR_TIPO_BOLETO,
                CTR_VISTORIADO_FK, CTR_STATUS
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'A')")
            ->execute([
                $destinatarioId,
                $veiculoId,
                $ctr['GRU_CODIGO_FK']        ?: null,
                $ctr['COM_CODIGO_FK']        ?: null,
                $ctr['CON_RASTREADOR_FK']    ?: null,
                $ctr['CTR_VALOR_ADESAO']     ?: null,
                $ctr['CTR_VALOR_MENSALIDADE'] ?: null,
                $ctr['CTR_VALOR_COMBO']      ?: null,
                $ctr['CTR_VALOR_VEICULO']    ?: null,
                $ctr['CTR_VALOR_COBERTURA']  ?: null,
                $ctr['CON_VALOR_RASTREADOR'] ?: null,
                $ctr['CTR_VALOR_TOTAL']      ?: null,
                $ctr['CTR_TIPO_BOLETO']      ?: null,
                $ctr['CTR_VISTORIADO_FK']    ?: null,
            ]);
        $novoContratoId = (int)$pdo->lastInsertId();

        // 3. Atualiza dono do veículo
        $pdo->prepare("UPDATE tb_veiculo SET PES_CODIGO_FK = ? WHERE VEI_CODIGO_PK = ?")
            ->execute([$destinatarioId, $veiculoId]);

        $pdo->commit();

        log_evento($pdo, 'tb_contrato', $contratoId, 'TRANSFERENCIA', [
            'data_evento'  => $dataEvento,
            'usuario_id'   => $usuarioId,
            'usuario_nome' => $transferidoPor,
            'motivo'       => $motivo,
            'obs'          => [
                'veiculo_id'      => $veiculoId,
                'origem_id'       => (int)$origemId,
                'destinatario_id' => $destinatarioId,
                'destinatario'    => $destRow['PES_NOME'],
            ],
        ]);

        log_evento($pdo, 'tb_contrato', $novoContratoId, 'CRIACAO', [
            'usuario_id'   => $usuarioId,
            'usuario_nome' => $transferidoPor,
            'motivo'       => 'Novo contrato gerado por transferência do contrato #' . $contratoId,
            'obs'          => ['contrato_origem' => $contratoId, 'veiculo_id' => $veiculoId],
        ]);

        log_line($RID, 'Veículo transferido', [
            'contrato_cancelado' => $contratoId,
            'contrato_novo'      => $novoContratoId,
            'veiculo_id'         => $veiculoId,
            'de'                 => $origemId,
            'para'               => $destinatarioId,
        ]);
        echo json_encode([
            'success'         => true,
            'message'         => 'Transferência realizada. Contrato #' . $contratoId . ' cancelado e novo contrato #' . $novoContratoId . ' criado para ' . $destRow['PES_NOME'] . '.',
            'novo_contrato_id' => $novoContratoId,
            'rid'             => $RID,
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        log_line($RID, 'Erro ao transferir veículo', ['err' => $e->getMessage()]);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Não foi possível transferir o veículo.', 'rid' => $RID, 'debug' => $e->getMessage()]);
    }
    exit;
}

// fallback
http_response_code(400);
echo json_encode(['error' => true, 'message' => 'Ação inválida', 'rid' => $RID]);
