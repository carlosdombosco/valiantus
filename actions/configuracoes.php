<?php
declare(strict_types=1);

require __DIR__ . '/../inc/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require PATH_INC . '/db.php';
require PATH_INC . '/csrf.php';

@header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

function ensure_cfg_table(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $pdo->exec("CREATE TABLE IF NOT EXISTS `tb_configuracoes` (
        `CFG_CODIGO_PK`         INT UNSIGNED  NOT NULL DEFAULT 1,
        `CFG_RAZAO_SOCIAL`      VARCHAR(200)  NOT NULL DEFAULT '',
        `CFG_ENDERECO`          VARCHAR(300)  NOT NULL DEFAULT '',
        `CFG_BAIRRO`            VARCHAR(100)  NOT NULL DEFAULT '',
        `CFG_CIDADE`            VARCHAR(100)  NOT NULL DEFAULT '',
        `CFG_UF`                CHAR(2)       NOT NULL DEFAULT '',
        `CFG_CEP`               VARCHAR(10)   NOT NULL DEFAULT '',
        `CFG_FONE`              VARCHAR(50)   NOT NULL DEFAULT '',
        `CFG_EMAIL`             VARCHAR(150)  NOT NULL DEFAULT '',
        `CFG_SITE`              VARCHAR(150)  NOT NULL DEFAULT '',
        `CFG_CNPJ`              VARCHAR(20)   NOT NULL DEFAULT '',
        `CFG_NOME_RESPONSAVEL`  VARCHAR(150)  NOT NULL DEFAULT '',
        `CFG_CARGO_RESPONSAVEL` VARCHAR(100)  NOT NULL DEFAULT '',
        `CFG_LOGO_PATH`         VARCHAR(500)  NOT NULL DEFAULT '',
        `CFG_ASSINATURA_PATH`   VARCHAR(500)  NOT NULL DEFAULT '',
        `CFG_TEXTO_ESTATUTO`    MEDIUMTEXT    NULL,
        `CFG_TEXTO_REGIMENTO`   MEDIUMTEXT    NULL,
        `CFG_TEXTO_TERMO`       MEDIUMTEXT    NULL,
        `CFG_UPDATED_AT`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`CFG_CODIGO_PK`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configurações gerais da empresa'");

    // Garante colunas de documentos em instalações já existentes
    foreach (['CFG_TEXTO_ESTATUTO', 'CFG_TEXTO_REGIMENTO', 'CFG_TEXTO_TERMO'] as $col) {
        try {
            $pdo->exec("ALTER TABLE `tb_configuracoes` ADD COLUMN `$col` MEDIUMTEXT NULL");
        } catch (Throwable $e) { /* coluna já existe */ }
    }

    // Garante colunas Sicoob (API futura)
    foreach ([
        "CFG_SICOOB_CLIENT_ID      VARCHAR(200) NOT NULL DEFAULT ''",
        "CFG_SICOOB_CLIENT_SECRET  VARCHAR(200) NOT NULL DEFAULT ''",
        "CFG_SICOOB_COOPERATIVA    VARCHAR(10)  NOT NULL DEFAULT ''",
        "CFG_SICOOB_CONTA          VARCHAR(20)  NOT NULL DEFAULT ''",
        "CFG_SICOOB_MODALIDADE     TINYINT      NOT NULL DEFAULT 1",
        "CFG_SICOOB_CARTEIRA       TINYINT      NOT NULL DEFAULT 1",
        "CFG_SICOOB_NUM_CONVENIO   VARCHAR(20)  NOT NULL DEFAULT ''",
        "CFG_SICOOB_CERT_PATH      VARCHAR(500) NOT NULL DEFAULT ''",
        "CFG_SICOOB_CERT_KEY       VARCHAR(500) NOT NULL DEFAULT ''",
        "CFG_SICOOB_AMBIENTE       VARCHAR(10)  NOT NULL DEFAULT 'producao'",
    ] as $def) {
        try {
            $pdo->exec("ALTER TABLE `tb_configuracoes` ADD COLUMN $def");
        } catch (Throwable $e) { /* coluna já existe */ }
    }

    // Garante colunas CNAB Remessa/Retorno
    foreach ([
        "CFG_CNAB_BANCO              VARCHAR(10)  NOT NULL DEFAULT '756'",
        "CFG_CNAB_AGENCIA            VARCHAR(10)  NOT NULL DEFAULT ''",
        "CFG_CNAB_AGENCIA_DIGITO     CHAR(1)      NOT NULL DEFAULT ''",
        "CFG_CNAB_ENDERECO           VARCHAR(300) NOT NULL DEFAULT ''",
        "CFG_CNAB_CNPJ               VARCHAR(20)  NOT NULL DEFAULT ''",
        "CFG_CNAB_CODIGO_CEDENTE     VARCHAR(20)  NOT NULL DEFAULT ''",
        "CFG_CNAB_CONTA              VARCHAR(20)  NOT NULL DEFAULT ''",
        "CFG_CNAB_CONTA_DIGITO       CHAR(1)      NOT NULL DEFAULT ''",
        "CFG_CNAB_DV_AGENCIA_CONTA   CHAR(1)      NOT NULL DEFAULT ''",
        "CFG_CNAB_TIPO_CARTEIRA      VARCHAR(20)  NOT NULL DEFAULT 'Registrada'",
        "CFG_CNAB_REMESSA_PATH       VARCHAR(500) NOT NULL DEFAULT 'C:\\\\remessa'",
        "CFG_CNAB_LAYOUT             VARCHAR(10)  NOT NULL DEFAULT 'CNAB240'",
        "CFG_CNAB_SEQUENCIAL_ARQUIVO INT UNSIGNED NOT NULL DEFAULT 1",
    ] as $def) {
        try {
            $pdo->exec("ALTER TABLE `tb_configuracoes` ADD COLUMN $def");
        } catch (Throwable $e) { /* coluna já existe */ }
    }

    // Garante linha única
    $pdo->exec("INSERT IGNORE INTO `tb_configuracoes` (CFG_CODIGO_PK) VALUES (1)");
    $done = true;
}

function upload_image(array $file, string $subfolder): ?string
{
    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed  = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
    if (!in_array($ext, $allowed, true)) return null;
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) return null;

    $dir = PATH_UPLOAD . '/empresa/' . $subfolder;
    if (!is_dir($dir)) @mkdir($dir, 0775, true);

    $fname  = date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 6) . '.' . $ext;
    $dest   = $dir . '/' . $fname;
    if (!@move_uploaded_file($file['tmp_name'], $dest)) return null;
    @chmod($dest, 0644);

    return UPLOAD_URL . '/empresa/' . $subfolder . '/' . $fname;
}

$acao = $_GET['acao'] ?? ($_POST['acao'] ?? '');

/* ── GET: obter ── */
if ($acao === 'obter' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        ensure_cfg_table($pdo);
        $row = $pdo->query("SELECT * FROM tb_configuracoes WHERE CFG_CODIGO_PK = 1 LIMIT 1")
                   ->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $row ?: []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao obter configurações.']);
    }
    exit;
}

/* ── POST: CSRF ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tok = $_POST['csrf'] ?? null;
    if ($tok !== null && function_exists('csrf_check') && !csrf_check($tok)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'CSRF inválido.']);
        exit;
    }
}

/* ── POST: salvar ── */
if ($acao === 'salvar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        ensure_cfg_table($pdo);

        $logoPath  = '';
        $assinPath = '';

        // Upload logo
        if (!empty($_FILES['logo']['name'])) {
            $url = upload_image($_FILES['logo'], 'logo');
            if ($url) $logoPath = $url;
        }
        // Upload assinatura
        if (!empty($_FILES['assinatura']['name'])) {
            $url = upload_image($_FILES['assinatura'], 'assinatura');
            if ($url) $assinPath = $url;
        }

        $sets   = [];
        $params = [];
        $fields = [
            'CFG_RAZAO_SOCIAL'      => trim($_POST['razao_social']      ?? ''),
            'CFG_ENDERECO'          => trim($_POST['endereco']           ?? ''),
            'CFG_BAIRRO'            => trim($_POST['bairro']             ?? ''),
            'CFG_CIDADE'            => trim($_POST['cidade']             ?? ''),
            'CFG_UF'                => strtoupper(trim($_POST['uf']      ?? '')),
            'CFG_CEP'               => trim($_POST['cep']                ?? ''),
            'CFG_FONE'              => trim($_POST['fone']               ?? ''),
            'CFG_EMAIL'             => trim($_POST['email']              ?? ''),
            'CFG_SITE'              => trim($_POST['site']               ?? ''),
            'CFG_CNPJ'              => trim($_POST['cnpj']               ?? ''),
            'CFG_NOME_RESPONSAVEL'  => trim($_POST['nome_responsavel']   ?? ''),
            'CFG_CARGO_RESPONSAVEL' => trim($_POST['cargo_responsavel']  ?? ''),
            'CFG_TEXTO_ESTATUTO'    => trim($_POST['texto_estatuto']     ?? ''),
            'CFG_TEXTO_REGIMENTO'   => trim($_POST['texto_regimento']    ?? ''),
            'CFG_TEXTO_TERMO'       => trim($_POST['texto_termo']        ?? ''),
            // Sicoob
            'CFG_SICOOB_CLIENT_ID'     => trim($_POST['sicoob_client_id']     ?? ''),
            'CFG_SICOOB_CLIENT_SECRET' => trim($_POST['sicoob_client_secret'] ?? ''),
            'CFG_SICOOB_COOPERATIVA'   => trim($_POST['sicoob_cooperativa']   ?? ''),
            'CFG_SICOOB_CONTA'         => trim($_POST['sicoob_conta']         ?? ''),
            'CFG_SICOOB_NUM_CONVENIO'  => trim($_POST['sicoob_num_convenio']  ?? ''),
            'CFG_SICOOB_MODALIDADE'    => (int)($_POST['sicoob_modalidade']   ?? 1),
            'CFG_SICOOB_CARTEIRA'      => (int)($_POST['sicoob_carteira']     ?? 1),
            'CFG_SICOOB_CERT_PATH'     => trim($_POST['sicoob_cert_path']     ?? ''),
            'CFG_SICOOB_CERT_KEY'      => trim($_POST['sicoob_cert_key']      ?? ''),
            'CFG_SICOOB_AMBIENTE'      => in_array($_POST['sicoob_ambiente'] ?? '', ['producao','sandbox'])
                                            ? $_POST['sicoob_ambiente'] : 'producao',
            // CNAB Remessa/Retorno
            'CFG_CNAB_BANCO'            => trim($_POST['cnab_banco']            ?? '756'),
            'CFG_CNAB_AGENCIA'          => trim($_POST['cnab_agencia']          ?? ''),
            'CFG_CNAB_AGENCIA_DIGITO'   => substr(trim($_POST['cnab_agencia_digito']   ?? ''), 0, 1),
            'CFG_CNAB_ENDERECO'         => trim($_POST['cnab_endereco']         ?? ''),
            'CFG_CNAB_CNPJ'             => preg_replace('/\D/', '', $_POST['cnab_cnpj'] ?? ''),
            'CFG_CNAB_CODIGO_CEDENTE'   => trim($_POST['cnab_codigo_cedente']   ?? ''),
            'CFG_CNAB_CONTA'            => trim($_POST['cnab_conta']            ?? ''),
            'CFG_CNAB_CONTA_DIGITO'     => substr(trim($_POST['cnab_conta_digito']     ?? ''), 0, 1),
            'CFG_CNAB_DV_AGENCIA_CONTA' => substr(trim($_POST['cnab_dv_agencia_conta'] ?? ''), 0, 1),
            'CFG_CNAB_TIPO_CARTEIRA'    => in_array($_POST['cnab_tipo_carteira'] ?? '', ['Registrada','Simples','Eletronica'])
                                            ? $_POST['cnab_tipo_carteira'] : 'Registrada',
            'CFG_CNAB_REMESSA_PATH'     => trim($_POST['cnab_remessa_path']     ?? 'C:\\remessa'),
            'CFG_CNAB_LAYOUT'           => in_array($_POST['cnab_layout'] ?? '', ['CNAB240','CNAB400'])
                                            ? $_POST['cnab_layout'] : 'CNAB240',
        ];
        if ($logoPath)  $fields['CFG_LOGO_PATH']       = $logoPath;
        if ($assinPath) $fields['CFG_ASSINATURA_PATH']  = $assinPath;

        foreach ($fields as $col => $val) {
            $sets[]   = "`$col` = ?";
            $params[] = $val;
        }

        $pdo->prepare("UPDATE `tb_configuracoes` SET " . implode(', ', $sets) . " WHERE CFG_CODIGO_PK = 1")
            ->execute($params);

        // Retorna dados atualizados
        $row = $pdo->query("SELECT * FROM tb_configuracoes WHERE CFG_CODIGO_PK = 1 LIMIT 1")
                   ->fetch(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'message' => 'Configurações salvas com sucesso.', 'data' => $row],
                         JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Não foi possível salvar.', 'debug' => $e->getMessage()]);
    }
    exit;
}

/* ── GET: testar_sicoob ── */
if ($acao === 'testar_sicoob' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        ensure_cfg_table($pdo);
        $cfg = $pdo->query("SELECT * FROM tb_configuracoes WHERE CFG_CODIGO_PK = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$cfg) throw new RuntimeException('Configurações não encontradas.');

        if (empty($cfg['CFG_SICOOB_CLIENT_ID']) || empty($cfg['CFG_SICOOB_CLIENT_SECRET'])) {
            echo json_encode(['success' => false, 'message' => 'Client ID e Client Secret são obrigatórios.']);
            exit;
        }

        require_once PATH_INC . '/services/SicoobService.php';
        $sicoob = new SicoobService($cfg);
        $token  = $sicoob->autenticar();

        if ($token) {
            echo json_encode(['success' => true, 'message' => 'Autenticação OK.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Autenticação falhou (token vazio).']);
        }
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => true, 'message' => 'Ação inválida.']);
