<?php
/**
 * actions/migrar.php — Importação Firebird → MySQL
 * Retorna JSON para cada ação.
 * Ordem recomendada: tb_cor → tb_grupo → tb_combo → tb_pessoa
 *   → tb_veiculo → tb_contrato → tb_vistoria → tb_cobranca
 *   → tb_sinistro → tb_imagens → tb_sinistro_imagem → tb_historico_vistoria
 */
require_once __DIR__ . '/../inc/config.php';
require_once PATH_INC  . '/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$acoesPublicas = ['schema_fb'];
$acao_check = $_POST['acao'] ?? '';
if (!in_array($acao_check, $acoesPublicas) && empty($_SESSION['SessUsuCodigo'])) {
    j(['success'=>false,'message'=>'Não autenticado']);
}

header('Content-Type: application/json; charset=utf-8');

$acao    = $_POST['acao']     ?? '';
$fdbPath = trim($_POST['fdb'] ?? 'C:\\CICLO\\CACHOEIRINHA.FDB');
$imgBase = rtrim(trim($_POST['img_base'] ?? 'C:\\CICLO\\imagens'), '\\/');
$offset  = max(0, (int)($_POST['offset'] ?? 0));
$limit   = min(2000, max(50, (int)($_POST['limit'] ?? 500)));

/* ───────────────────── helpers ───────────────────── */
function j($data) { echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }

function getFb(string $path): PDO {
    if (!extension_loaded('pdo_firebird')) {
        throw new RuntimeException('Extensão pdo_firebird não carregada. Reinicie o Apache após habilitar no php.ini.');
    }
    if (!file_exists($path)) {
        throw new RuntimeException("Arquivo FDB não encontrado: {$path}");
    }
    $dsn = "firebird:dbname=localhost:{$path};charset=UTF8";
    return new PDO($dsn, 'SYSDBA', 'ciclo@2022', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

/** Lê um campo BLOB do Firebird (retorna string ou null) */
function blob($v): ?string {
    if ($v === null) return null;
    if (is_resource($v)) return stream_get_contents($v);
    return (string)$v;
}

/** Converte SIM/NÃO (Firebird) → S/N */
function simNao(?string $v): string {
    return (strtoupper(trim($v ?? '')) === 'SIM' || $v === 'S') ? 'S' : 'N';
}

/** Mapeia status do sinistro: A→ABERTO, E→ENCERRADO, C→CANCELADO */
function sinStatus(?string $v): string {
    return ['A'=>'ABERTO','E'=>'ENCERRADO','C'=>'CANCELADO'][strtoupper($v ?? '')] ?? 'ABERTO';
}

/**
 * Copia e redimensiona imagem para <= $maxBytes.
 * Converte tudo para JPEG se necessário.
 */
function copiarResizindo(string $src, string $dest, int $maxBytes = 512000): bool {
    $size = @filesize($src);
    if ($size !== false && $size <= $maxBytes) {
        return (bool)@copy($src, $dest);
    }
    if (!function_exists('imagecreatefromjpeg')) {
        return (bool)@copy($src, $dest);
    }
    $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
    $img = null;
    switch ($ext) {
        case 'jpg': case 'jpeg': $img = @imagecreatefromjpeg($src); break;
        case 'png':  $img = @imagecreatefrompng($src);  break;
        case 'webp': $img = @imagecreatefromwebp($src); break;
        default:     $img = @imagecreatefromjpeg($src);
    }
    if (!$img) return (bool)@copy($src, $dest);

    $w = imagesx($img); $h = imagesy($img);
    // Tenta reduzir qualidade primeiro
    for ($q = 85; $q >= 40; $q -= 10) {
        ob_start(); imagejpeg($img, null, $q); $data = ob_get_clean();
        if (strlen($data) <= $maxBytes) { imagedestroy($img); return (bool)file_put_contents($dest, $data); }
    }
    // Reduz dimensões
    for ($sc = 80; $sc >= 30; $sc -= 10) {
        $nw = (int)($w * $sc / 100); $nh = (int)($h * $sc / 100);
        $r2 = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($r2, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        ob_start(); imagejpeg($r2, null, 70); $data = ob_get_clean();
        imagedestroy($r2);
        if (strlen($data) <= $maxBytes) { imagedestroy($img); return (bool)file_put_contents($dest, $data); }
    }
    imagedestroy($img);
    return (bool)@copy($src, $dest);
}

/**
 * Copia imagem do Firebird para uploads/ no formato do sistema novo.
 * Formato: PLACA_PESID_legado_HASH8 / PLACA_NN_HASH6.jpg
 */
function copiarImagem(string $caminhoFb, string $imgBase, string $uploadRoot,
                      int $veiId, string $placa, int $pesId, int $imgIdx): array {
    $parts    = preg_split('/[\\\\\\/]/', $caminhoFb);
    $filename = end($parts) ?: 'img.jpg';
    $srcPlaca = (count($parts) >= 2) ? $parts[count($parts) - 2] : '';
    $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'], true)) $ext = 'jpg';

    $placaSafe = preg_replace('/[^A-Z0-9]/i', '', $placa ?: $srcPlaca);
    $folder    = sprintf('%s_%d_legado_%s', $placaSafe, $pesId, substr(md5('leg_' . $veiId), 0, 8));
    $newName   = sprintf('%s_%02d_%s.jpg', $placaSafe, $imgIdx, substr(md5($filename . $veiId), 0, 6));
    $destDir   = $uploadRoot . DIRECTORY_SEPARATOR . $folder;
    $destPath  = $destDir   . DIRECTORY_SEPARATOR . $newName;
    $destUrl   = UPLOAD_URL . '/' . $folder . '/' . $newName;

    if (file_exists($destPath)) return [true, $destUrl];

    $fontes = [
        $caminhoFb,
        rtrim($imgBase, '\\/') . DIRECTORY_SEPARATOR . $srcPlaca . DIRECTORY_SEPARATOR . $filename,
        rtrim($imgBase, '\\/') . DIRECTORY_SEPARATOR . $srcPlaca . DIRECTORY_SEPARATOR . strtolower($filename),
    ];
    foreach ($fontes as $src) {
        if (file_exists($src)) {
            if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
            if (copiarResizindo($src, $destPath)) return [true, $destUrl];
        }
    }
    return [false, $destUrl];
}

/* ── DIAGNÓSTICO MySQL ── */
if ($acao === 'diagn_mysql') {
    global $pdo;
    $info = [];
    try {
        $info['database']   = $pdo->query("SELECT DATABASE()")->fetchColumn();
        $info['version']    = $pdo->query("SELECT VERSION()")->fetchColumn();
        $info['autocommit'] = $pdo->query("SELECT @@autocommit")->fetchColumn();
        $info['sql_mode']   = $pdo->query("SELECT @@sql_mode")->fetchColumn();
        $info['trx_level']  = $pdo->query("SELECT @@transaction_isolation")->fetchColumn();
        // Triggers
        $trigs = $pdo->query("SHOW TRIGGERS WHERE `Table` = 'tb_contrato'")->fetchAll();
        $info['triggers'] = array_column($trigs, 'Trigger');
        if (!empty($info['triggers'])) {
            $tdef = $pdo->query("SHOW CREATE TRIGGER `trg_tb_contrato_after_insert`")->fetch(PDO::FETCH_ASSOC);
            $info['trigger_body'] = $tdef['SQL Original Statement'] ?? ($tdef[2] ?? 'n/a');
        }

        // Testa exec() simples
        $pdo->exec("DELETE FROM tb_contrato WHERE CTR_CODIGO_PK IN (99994,99995,99996,99997)");
        $before = (int)$pdo->query("SELECT COUNT(*) FROM tb_contrato")->fetchColumn();
        $pdo->exec("INSERT INTO tb_contrato (CTR_CODIGO_PK, PES_CODIGO_FK, VEI_CODIGO_FK, CTR_STATUS) VALUES (99997, 1, 1, 'A')");
        $info['exec_after'] = (int)$pdo->query("SELECT COUNT(*) FROM tb_contrato WHERE CTR_CODIGO_PK=99997")->fetchColumn();
        $pdo->exec("DELETE FROM tb_contrato WHERE CTR_CODIGO_PK=99997");

        // Testa prepared statement simples
        $st = $pdo->prepare("INSERT INTO tb_contrato (CTR_CODIGO_PK, PES_CODIGO_FK, VEI_CODIGO_FK, CTR_STATUS) VALUES (?,?,?,?)");
        $st->execute([99996, 1, 1, 'A']);
        $info['prepared_after'] = (int)$pdo->query("SELECT COUNT(*) FROM tb_contrato WHERE CTR_CODIGO_PK=99996")->fetchColumn();
        $pdo->exec("DELETE FROM tb_contrato WHERE CTR_CODIGO_PK=99996");

        // Testa prepared + ON DUPLICATE KEY UPDATE
        $st2 = $pdo->prepare("INSERT INTO tb_contrato (CTR_CODIGO_PK, PES_CODIGO_FK, VEI_CODIGO_FK, CTR_STATUS) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE CTR_CODIGO_PK=CTR_CODIGO_PK");
        $st2->execute([99995, 1, 1, 'A']);
        $info['odku_after'] = (int)$pdo->query("SELECT COUNT(*) FROM tb_contrato WHERE CTR_CODIGO_PK=99995")->fetchColumn();
        $pdo->exec("DELETE FROM tb_contrato WHERE CTR_CODIGO_PK=99995");

        // Testa com PK baixo (igual ao Firebird)
        $st3 = $pdo->prepare("INSERT INTO tb_contrato (CTR_CODIGO_PK, PES_CODIGO_FK, VEI_CODIGO_FK, CTR_STATUS) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE CTR_CODIGO_PK=CTR_CODIGO_PK");
        $st3->execute([1, 1, 1, 'A']);
        $info['low_pk_after'] = (int)$pdo->query("SELECT COUNT(*) FROM tb_contrato WHERE CTR_CODIGO_PK=1")->fetchColumn();
        $pdo->exec("DELETE FROM tb_contrato WHERE CTR_CODIGO_PK=1");

    } catch (Throwable $e) { $info['error'] = $e->getMessage(); }
    j(['success'=>true, 'info'=>$info]);
}

/* ───────────────────── SCHEMA FIREBIRD (diagnóstico) ───────────────────── */
if ($acao === 'schema_fb') {
    try {
        $fb = getFb($fdbPath);
        $tabelas = ['TB_PESSOA','TB_VEICULO','TB_IMAGEM_VEICULO','TB_VEICULO_CONTRATO','TB_CONTAS','TB_VISTORIA','TB_TIPO_VEICULO'];
        $schema = [];
        foreach ($tabelas as $t) {
            $cols = $fb->query("SELECT TRIM(f.RDB\$FIELD_NAME) AS COL FROM RDB\$RELATION_FIELDS f WHERE TRIM(f.RDB\$RELATION_NAME)='$t' ORDER BY f.RDB\$FIELD_POSITION")->fetchAll(PDO::FETCH_COLUMN);
            $schema[$t] = $cols;
        }
        // Amostra de 1 linha das tabelas críticas
        $amostras = [];
        foreach (['TB_IMAGEM_VEICULO','TB_VEICULO_CONTRATO','TB_CONTAS','TB_VISTORIA'] as $t) {
            try {
                $row = $fb->query("SELECT FIRST 1 * FROM $t")->fetch();
                $amostras[$t] = array_map(fn($v) => is_resource($v) ? '[BLOB]' : $v, $row ?: []);
            } catch (Throwable $e) { $amostras[$t] = ['erro' => $e->getMessage()]; }
        }
        j(['success'=>true, 'schema'=>$schema, 'amostras'=>$amostras]);
    } catch (Throwable $e) { j(['success'=>false,'message'=>$e->getMessage()]); }
}

/* ───────────────────── TESTAR CONEXÃO ───────────────────── */
if ($acao === 'testar_conexao') {
    try {
        $fb  = getFb($fdbPath);
        $cnt = $fb->query("SELECT COUNT(*) FROM TB_PESSOA")->fetchColumn();
        j(['success'=>true, 'message'=>"Conexão OK — {$cnt} pessoas no banco."]);
    } catch (Throwable $e) {
        j(['success'=>false, 'message'=>$e->getMessage()]);
    }
}

/* ───────────────────── STATUS (CONTAGENS) ───────────────────── */
if ($acao === 'status') {
    try {
        $fb = getFb($fdbPath);
        $mapFb = [
            'tb_cor'               => "SELECT COUNT(*) FROM TB_VEICULO_COR",
            'tb_grupo'             => "SELECT COUNT(*) FROM TB_GRUPO",
            'tb_combo'             => "SELECT COUNT(*) FROM TB_COMBO",
            'tb_pessoa'            => "SELECT COUNT(*) FROM TB_PESSOA",
            'tb_veiculo'           => "SELECT COUNT(*) FROM TB_VEICULO",
            'tb_contrato'          => "SELECT COUNT(*) FROM TB_VEICULO_CONTRATO",
            'tb_vistoria'          => "SELECT COUNT(*) FROM TB_VISTORIA",
            'tb_cobranca'          => "SELECT COUNT(*) FROM TB_CONTAS",
            'tb_sinistro'          => "SELECT COUNT(*) FROM TB_SINISTRO",
            'tb_imagens'           => "SELECT COUNT(*) FROM TB_IMAGEM_VEICULO",
            'tb_sinistro_imagem'   => "SELECT COUNT(*) FROM TB_IMAGEM_SINISTRO",
            'tb_historico_vistoria'=> "SELECT COUNT(*) FROM TB_HISTORICO",
        ];
        $mapMy = [
            'tb_cor'               => "SELECT COUNT(*) FROM tb_cor",
            'tb_grupo'             => "SELECT COUNT(*) FROM tb_grupo",
            'tb_combo'             => "SELECT COUNT(*) FROM tb_combo",
            'tb_pessoa'            => "SELECT COUNT(*) FROM tb_pessoa",
            'tb_veiculo'           => "SELECT COUNT(*) FROM tb_veiculo",
            'tb_contrato'          => "SELECT COUNT(*) FROM tb_contrato",
            'tb_vistoria'          => "SELECT COUNT(*) FROM tb_vistoria",
            'tb_cobranca'          => "SELECT COUNT(*) FROM tb_cobranca",
            'tb_sinistro'          => "SELECT COUNT(*) FROM tb_sinistro",
            'tb_imagens'           => "SELECT COUNT(*) FROM tb_imagens",
            'tb_sinistro_imagem'   => "SELECT COUNT(*) FROM tb_sinistro_imagem",
            'tb_historico_vistoria'=> "SELECT COUNT(*) FROM tb_historico_vistoria",
        ];
        global $pdo;
        $out = [];
        foreach ($mapFb as $tbl => $sql) {
            $fbCnt = (int)$fb->query($sql)->fetchColumn();
            try { $myCnt = (int)$pdo->query($mapMy[$tbl])->fetchColumn(); }
            catch (Throwable $e) { $myCnt = 0; }
            $out[$tbl] = ['fb'=>$fbCnt, 'my'=>$myCnt];
        }
        j($out);
    } catch (Throwable $e) {
        j(['success'=>false, 'message'=>$e->getMessage()]);
    }
}

/* ───────────────────── LIMPAR TABELA ───────────────────── */
if ($acao === 'limpar') {
    $tabela = preg_replace('/[^a-z_]/', '', strtolower($_POST['tabela'] ?? ''));
    $allowed = ['tb_cor','tb_grupo','tb_combo','tb_pessoa','tb_veiculo','tb_contrato',
                'tb_vistoria','tb_cobranca','tb_sinistro','tb_imagens','tb_sinistro_imagem','tb_historico_vistoria'];
    if (!in_array($tabela, $allowed)) j(['success'=>false,'message'=>'Tabela inválida.']);
    global $pdo;
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        // Tabelas dependentes de tb_contrato e tb_pessoa precisam ser limpas junto
        $dependentes = [
            'tb_contrato' => ['tb_contrato_evento'],
            'tb_pessoa'   => ['tb_contrato_evento','tb_contrato','tb_veiculo','tb_cobranca','tb_sinistro','tb_imagens','tb_vistoria'],
            'tb_veiculo'  => ['tb_contrato_evento','tb_contrato','tb_cobranca','tb_sinistro','tb_imagens','tb_vistoria'],
        ];
        if (isset($dependentes[$tabela])) {
            foreach ($dependentes[$tabela] as $dep) {
                try { $pdo->exec("TRUNCATE TABLE `{$dep}`"); } catch (Throwable $e2) {}
            }
        }
        $pdo->exec("TRUNCATE TABLE `{$tabela}`");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        j(['success'=>true, 'message'=>"Tabela {$tabela} limpa."]);
    } catch (Throwable $e) {
        try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 1"); } catch (Throwable $e2) {}
        j(['success'=>false, 'message'=>$e->getMessage()]);
    }
}

/* ===================================================================
   MIGRAÇÕES POR TABELA
   =================================================================== */
global $pdo;

/* ── 1. tb_cor ← TB_VEICULO_COR ── */
if ($acao === 'migrar_tb_cor') {
    try {
        $fb   = getFb($fdbPath);
        $rows = $fb->query("SELECT VCO_CODIGO_PK, VCO_NOME, VCO_METALICO FROM TB_VEICULO_COR ORDER BY VCO_CODIGO_PK")->fetchAll();
        $stmt = $pdo->prepare("INSERT IGNORE INTO tb_cor (COR_CODIGO_PK, COR_DESCRICAO) VALUES (?,?)");
        $ok = 0;
        foreach ($rows as $r) {
            $nome = trim($r['VCO_NOME'] ?? '');
            if (trim($r['VCO_METALICO']) === 'S') $nome .= ' Metálico';
            $stmt->execute([$r['VCO_CODIGO_PK'], $nome]);
            $ok += $stmt->rowCount();
        }
        j(['success'=>true,'done'=>true,'imported'=>$ok,'total'=>count($rows),'errors'=>0]);
    } catch (Throwable $e) { j(['success'=>false,'message'=>$e->getMessage()]); }
}

/* ── 2. tb_grupo ← TB_GRUPO ── */
if ($acao === 'migrar_tb_grupo') {
    try {
        $fb   = getFb($fdbPath);
        $rows = $fb->query("SELECT g.*, t.TVE_DESCRICAO FROM TB_GRUPO g LEFT JOIN TB_TIPO_VEICULO t ON t.TVE_CODIGO_PK = g.GRU_TVE_CODIGO_FK ORDER BY g.GRU_CODIGO_PK")->fetchAll();
        $stmt = $pdo->prepare("INSERT INTO tb_grupo
            (GRU_CODIGO_PK,GRU_DESCRICAO,GRU_VALOR_MENSALIDADE,GRU_VALOR_MINIMO,GRU_VALOR_MAXIMO,
             GRU_VALOR_TERCEIRO,GRU_VALOR_RESERVA,GRU_TIPO_VEICULO,GRU_VALOR_ADESAO,GRU_VALOR_RENOVACAO,
             GRU_LIMITE_CADASTRO,GRU_TAXA_REGULARIZACAO,GRU_SEQUENCIA,GRU_TVE_CODIGO_FK)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE GRU_DESCRICAO=VALUES(GRU_DESCRICAO),
              GRU_VALOR_MENSALIDADE=VALUES(GRU_VALOR_MENSALIDADE)");
        $ok = 0;
        foreach ($rows as $r) {
            $stmt->execute([
                $r['GRU_CODIGO_PK'],  $r['GRU_DESCRICAO'],     $r['GRU_VALOR_MENSALIDADE'],
                $r['GRU_VALOR_MINIMO'],$r['GRU_VALOR_MAXIMO'],  $r['GRU_VALOR_TERCEIRO'],
                $r['GRU_VALOR_RESERVA'],trim($r['TVE_DESCRICAO']??''),
                $r['GRU_VALOR_ADESAO'],$r['GRU_VALOR_RENOVACAO'],$r['GRU_LIMITE_CADASTRO'],
                $r['GRU_TAXA_REGULARIZACAO'],$r['GRU_SEQUENCIA'],$r['GRU_TVE_CODIGO_FK'],
            ]);
            $ok++;
        }
        j(['success'=>true,'done'=>true,'imported'=>$ok,'total'=>count($rows),'errors'=>0]);
    } catch (Throwable $e) { j(['success'=>false,'message'=>$e->getMessage()]); }
}

/* ── 3. tb_combo ← TB_COMBO + TB_SERVICOS → tb_servico_combo ── */
if ($acao === 'migrar_tb_combo') {
    try {
        $fb   = getFb($fdbPath);
        $combos = $fb->query("SELECT * FROM TB_COMBO ORDER BY COM_CODIGO_PK")->fetchAll();
        $servs  = $fb->query("SELECT * FROM TB_SERVICOS ORDER BY SER_CODIGO_PK")->fetchAll();
        $links  = $fb->query("SELECT * FROM TB_ITENS_COMBO")->fetchAll();

        $stmtC = $pdo->prepare("INSERT IGNORE INTO tb_combo (COM_CODIGO_PK,COM_VALOR,COM_DESCRICAO) VALUES (?,?,?)");
        // tb_servico_combo: se existir coluna compatível; adaptamos ao esquema MySQL
        // MySQL: tb_servico_combo → veja a estrutura real
        $ok = 0;
        foreach ($combos as $c) {
            $stmtC->execute([$c['COM_CODIGO_PK'],$c['COM_VALOR'],$c['COM_DESCRICAO']]);
            $ok += $stmtC->rowCount();
        }

        // Verifica se tem coluna SER_CODIGO_PK em tb_servico_combo
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM tb_servico_combo")->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) { $cols = []; }

        if (!empty($cols)) {
            $stmtS = $pdo->prepare("INSERT IGNORE INTO tb_servico_combo (SEC_COMBO_FK, SEC_DECRICAO) VALUES (?,?)");
            $srvMap = array_column($servs, null, 'SER_CODIGO_PK');
            foreach ($links as $lk) {
                $srv = $srvMap[$lk['ITC_SER_CODIGO_FK']] ?? null;
                if ($srv) {
                    try { $stmtS->execute([$lk['ITC_COM_CODIGO_FK'], $srv['SER_DESCRICAO']]); } catch (Throwable $e) {}
                }
            }
        }

        j(['success'=>true,'done'=>true,'imported'=>$ok,'total'=>count($combos),'errors'=>0]);
    } catch (Throwable $e) { j(['success'=>false,'message'=>$e->getMessage()]); }
}

/* ── 4. tb_pessoa ← TB_PESSOA ── */
if ($acao === 'migrar_tb_pessoa') {
    try {
        $fb    = getFb($fdbPath);
        $total = (int)$fb->query("SELECT COUNT(*) FROM TB_PESSOA")->fetchColumn();
        $rows  = $fb->query("
            SELECT p.*, c.CID_NOME, c.CID_UF
            FROM TB_PESSOA p
            LEFT JOIN TB_CIDADES c ON c.CID_CODIGO = p.PES_CID_COD_FK
            ORDER BY p.PES_CODIGO_PK
            ROWS " . ($offset + 1) . " TO " . ($offset + $limit)
        )->fetchAll();

        $stmt = $pdo->prepare("INSERT INTO tb_pessoa
            (PES_CODIGO_PK,PES_NOME,PES_CPF_CNPJ,PES_TIPO,PES_RG,PES_ORG_EXP,PES_STATUS,
             PES_DATA_CADASTRO,PES_CEP,PES_ENDERECO,PES_BAIRRO,PES_NUMERO,PES_COMPLEMENTO,
             PES_PONTO_REFERENCIA,PES_UF,PES_CIDADE,PES_FONE_FIXO,PES_FONE_CELULAR_1,
             PES_FONE_CELULAR_2,PES_SEXO,PES_DATA_NASCIMENTO,PES_ESTADO_CIVIL,PES_EMAIL,
             PES_PROFISSAO,PES_OBSERVACAO,PES_NUM_CNH,PES_CATEGORIA_CNH,PES_VALIDADE,
             PES_RESPONSAVEL_LEGAL,PES_CPF_REP_LEGAL,PES_ORGAO_EMISSOR,PES_DATA_EXPEDICAO,
             PES_MOTIVO_CANCELAMENTO,PES_DATA_CANCELAMENTO,PES_CANCELADO_POR)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
              PES_UF=VALUES(PES_UF), PES_CIDADE=VALUES(PES_CIDADE),
              PES_SEXO=VALUES(PES_SEXO), PES_NUM_CNH=VALUES(PES_NUM_CNH),
              PES_CATEGORIA_CNH=VALUES(PES_CATEGORIA_CNH), PES_VALIDADE=VALUES(PES_VALIDADE),
              PES_TIPO=VALUES(PES_TIPO), PES_STATUS=VALUES(PES_STATUS)");

        $ok = 0; $erros = 0; $logs = [];
        foreach ($rows as $r) {
            $st = strtoupper(trim($r['PES_STATUS'] ?? 'A'));
            $status = ($st === 'C') ? 'CANCELADO' : 'ATIVO';
            $tipo = trim($r['PES_NATUREZA'] ?? '');
            try {
                $stmt->execute([
                    $r['PES_CODIGO_PK'],
                    trim($r['PES_RAZAO_SOCIAL'] ?? ''),
                    trim($r['PES_CPF_CNPJ']     ?? ''),
                    $tipo,
                    trim($r['PES_RG']            ?? ''),
                    trim($r['PES_ORG_EXP']       ?? ''),
                    $status,
                    $r['PES_DATA_CADASTRO']      ?? null,
                    trim($r['PES_CEP']           ?? ''),
                    trim($r['PES_ENDERECO']      ?? ''),
                    trim($r['PES_BAIRRO']        ?? ''),
                    trim($r['PES_NUMERO']        ?? ''),
                    trim($r['PES_COMPLEMENTO']   ?? ''),
                    trim($r['PES_PONTO_REFERENCIA'] ?? ''),
                    trim($r['CID_UF']            ?? ''),
                    trim($r['CID_NOME']          ?? ''),
                    trim($r['PES_FONE_FIXO']     ?? ''),
                    trim($r['PES_FONE_CELULAR_1']?? ''),
                    trim($r['PES_FONE_CELULAR_2']?? ''),
                    (strtoupper(trim($r['PES_SEXO'] ?? '')) === 'M' ? 'MASCULINO' : (strtoupper(trim($r['PES_SEXO'] ?? '')) === 'F' ? 'FEMININO' : '')),
                    $r['PES_DATA_NASCIMENTO']    ?? null,
                    trim($r['PES_ESTADO_CIVIL']  ?? ''),
                    trim($r['PES_EMAIL']         ?? ''),
                    trim($r['PES_PROFISSAO']     ?? ''),
                    blob($r['PES_OBSERVACAO'])   ?? '',
                    trim($r['PES_NUM_CNH']       ?? ''),
                    trim($r['PES_CATEGORIA_CNH'] ?? ''),
                    $r['PES_VALIDADE']           ?? null,
                    trim($r['PES_RESPONSAVEL_LEGAL'] ?? ''),
                    trim($r['PES_CPF_REP_LEGAL'] ?? ''),
                    trim($r['PES_ORGAO_EMISSOR'] ?? ''),
                    $r['PES_DATA_EXPEDICAO']     ?? null,
                    trim($r['PES_MOTIVO_CANCELAMENTO'] ?? ''),
                    $r['PES_DATA_CANCELAMENTO']  ?? null,
                    $r['PES_CANCELADO_POR']      ?? null,
                ]);
                $ok++;
            } catch (Throwable $e) {
                $erros++;
                if (count($logs) < 5) $logs[] = "✗ PK={$r['PES_CODIGO_PK']}: " . $e->getMessage();
            }
        }

        $done = (count($rows) < $limit);
        j(['success'=>true,'done'=>$done,'imported'=>$ok,'errors'=>$erros,'total'=>$total,'next_offset'=>$offset+$limit,'log'=>$logs]);
    } catch (Throwable $e) { j(['success'=>false,'message'=>$e->getMessage()]); }
}

/* ── 5. tb_veiculo ← TB_VEICULO ── */
if ($acao === 'migrar_tb_veiculo') {
    try {
        $fb    = getFb($fdbPath);
        $total = (int)$fb->query("SELECT COUNT(*) FROM TB_VEICULO")->fetchColumn();
        $rows  = $fb->query("
            SELECT v.*, c.CID_NOME, c.CID_UF, m.MAR_DESCRICAO, m.MAR_TIPO, mo.MOD_DESCRICAO
            FROM TB_VEICULO v
            LEFT JOIN TB_CIDADES c ON c.CID_CODIGO  = v.VEI_CID_COD_FK
            LEFT JOIN TB_MARCA   m ON m.MAR_CODIGO_PK = v.VEI_MAR_COD_FK
            LEFT JOIN TB_MODELO mo ON mo.MOD_CODIGO_PK = v.VEI_MOD_COD_FK
            ORDER BY v.VEI_CODIGO_PK
            ROWS " . ($offset + 1) . " TO " . ($offset + $limit)
        )->fetchAll();

        $stmt = $pdo->prepare("INSERT INTO tb_veiculo
            (VEI_CODIGO_PK,VEI_PLACA,VEI_DATA_CADASTRO,VEI_UF,VEI_CIDADE,VEI_MARCA,VEI_MODELO,
             VEI_ANO_FABRICACAO,VEI_ANO_MODELO,VEI_VALOR_CADASTRO,VEI_CODIGO_FIPE,VEI_CHASSI,
             VEI_RENAVAM,VEI_CPF_CNPJ_PROPRIETARIO,VEI_NOME_PROPRIETARIO,VEI_ALIENADO,
             VEI_COD_COR_FK,VEI_STATUS,VEI_COMBUSTIVEL,VEI_CAMBIO,VEI_LEILAO,VEI_RECUPERADO,
             VEI_MONTA,VEI_ESPECIFIQUE_MONTA,VAI_ISENTO_TAXAS,VEI_ESPECIFIQUE_TAXAS,
             VEI_LOCACAO,VEI_TAXI,VEI_PLACA_VERMELHA,VEI_CHASSI_REMARCADO,
             VEI_IMPEDIMENTO_JUDICIAL,VEI_ESPECIFIQUE_IMPEDIMENTO,PES_CODIGO_FK,VEI_DATA_ALTERACAO,VEI_TIPO)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
              VEI_UF=VALUES(VEI_UF), VEI_CIDADE=VALUES(VEI_CIDADE),
              VEI_MARCA=VALUES(VEI_MARCA), VEI_MODELO=VALUES(VEI_MODELO),
              VEI_ANO_FABRICACAO=VALUES(VEI_ANO_FABRICACAO), VEI_ANO_MODELO=VALUES(VEI_ANO_MODELO),
              VEI_CAMBIO=VALUES(VEI_CAMBIO), VEI_TIPO=VALUES(VEI_TIPO)");

        $ok = 0; $erros = 0;
        foreach ($rows as $r) {
            try {
                $stmt->execute([
                    $r['VEI_CODIGO_PK'],
                    trim($r['VEI_PLACA']    ?? ''),
                    $r['VEI_DATA_CADASTRO'] ?? null,
                    trim($r['CID_UF']       ?? ''),
                    trim($r['CID_NOME']     ?? ''),
                    trim($r['MAR_DESCRICAO']?? trim($r['MARCA_ANTIGA'] ?? '')),
                    trim($r['MOD_DESCRICAO']?? trim($r['MODELO_ANTIGO']?? '')),
                    trim($r['VEI_ANO_FABRICACAO']?? ''),
                    trim($r['VEI_ANO_MODELO']    ?? ''),
                    $r['VEI_VALOR_CADASTRO'] ?? null,
                    trim($r['VEI_CODIGO_FIPE']   ?? ''),
                    trim($r['VEI_CHASSI']         ?? ''),
                    trim($r['VEI_RENAVAM']         ?? ''),
                    trim($r['VEI_CPF_CNPJ_PROPRIETARIO'] ?? ''),
                    trim($r['VEI_NOME_PROPRIETARIO']     ?? ''),
                    trim($r['VEI_ALIENADO']    ?? 'NÃO'),
                    $r['VEI_VCO_COD_COR_FK']  ?? null,
                    trim($r['VEI_STATUS']      ?? 'A'),
                    trim($r['VEI_COMBUSTIVEL'] ?? ''),
                    (function($v){ $m=['AUTOMATICO'=>'AUTOMÁTICO','AUTOMATIZADO'=>'AUTOMATIZADO','MANUAL'=>'MANUAL']; return $m[strtoupper(trim($v))] ?? trim($v); })($r['VEI_CAMBIO'] ?? ''),
                    trim($r['VEI_LEILAO']      ?? 'NÃO'),
                    trim($r['VEI_RECUPERADO']  ?? 'NÃO'),
                    trim($r['VEI_MONTA']       ?? 'NÃO'),
                    trim($r['VEI_ESPECIFIQUE_MONTA'] ?? ''),
                    trim($r['VAI_ISENTO_TAXAS']?? 'NÃO'),
                    trim($r['VEI_ESPECIFIQUE_TAXAS'] ?? ''),
                    trim($r['VEI_LOCACAO']     ?? 'NÃO'),
                    trim($r['VEI_TAXI']        ?? 'NÃO'),
                    trim($r['VEI_PLACA_VERMELHA']   ?? 'NÃO'),
                    trim($r['VEI_CHASSI_REMARCADO'] ?? 'NÃO'),
                    trim($r['VEI_IMPEDIMENTO_JUDICIAL'] ?? 'NÃO'),
                    trim($r['VEI_ESPECIFIQUE_IMPEDIMENTO'] ?? ''),
                    $r['CLI_ID']               ?? null,
                    $r['VEI_DATA_ALTERACAO']   ?? null,
                    strtoupper(trim($r['MAR_TIPO'] ?? 'CARRO')),
                ]);
                $ok++;
            } catch (Throwable $e) { $erros++; }
        }

        $done = (count($rows) < $limit);
        j(['success'=>true,'done'=>$done,'imported'=>$ok,'errors'=>$erros,'total'=>$total,'next_offset'=>$offset+$limit]);
    } catch (Throwable $e) { j(['success'=>false,'message'=>$e->getMessage()]); }
}

/* ── 6. tb_contrato ← TB_VEICULO_CONTRATO ── */
if ($acao === 'migrar_tb_contrato') {
    try {
        // Limpa tb_contrato_evento antes de migrar para evitar violação de unique key
        // causada pelo trigger trg_tb_contrato_after_insert (INSERT CRIACAO duplicado)
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        $pdo->exec("TRUNCATE TABLE tb_contrato_evento");
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

        $fb    = getFb($fdbPath);
        $total = (int)$fb->query("SELECT COUNT(*) FROM TB_VEICULO_CONTRATO")->fetchColumn();
        $rows  = $fb->query("
            SELECT vco.*, p.PES_RAZAO_SOCIAL AS CANCEL_NOME
            FROM TB_VEICULO_CONTRATO vco
            LEFT JOIN TB_PESSOA p ON p.PES_CODIGO_PK = vco.VCO_CANCELADO_POR
            ORDER BY vco.VCO_CODIGO_PK
            ROWS " . ($offset + 1) . " TO " . ($offset + $limit)
        )->fetchAll();

        // mapa combo_id → valor para popular CTR_VALOR_COMBO
        $comboMap = $pdo->query("SELECT COM_CODIGO_PK, COM_VALOR FROM tb_combo")
                        ->fetchAll(PDO::FETCH_KEY_PAIR);

        // Usa INSERT normal (não IGNORE) para capturar erros reais.
        // Duplicatas (1062) são ignoradas silenciosamente; outros erros vão para o log.
        $stmt = $pdo->prepare("INSERT INTO tb_contrato
            (CTR_CODIGO_PK,PES_CODIGO_FK,VEI_CODIGO_FK,GRU_CODIGO_FK,COM_CODIGO_FK,
             CTR_VISTORIADO_FK,CTR_VALOR_ADESAO,CTR_VALOR_MENSALIDADE,CTR_VALOR_COMBO,
             CON_VALOR_RASTREADOR,CTR_VALOR_VEICULO,CTR_VALOR_COBERTURA,CTR_VALOR_TOTAL,CTR_TIPO_BOLETO,
             CTR_STATUS,CTR_DATA_CAD,CTR_DATA_CANCELAMENTO,CTR_MOTIVO_CANCELAMENTO,CTR_CANCELADO_POR)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
              CTR_VALOR_VEICULO=VALUES(CTR_VALOR_VEICULO),
              CTR_VALOR_COBERTURA=VALUES(CTR_VALOR_COBERTURA),
              CTR_VALOR_COMBO=VALUES(CTR_VALOR_COMBO),
              CTR_TIPO_BOLETO=VALUES(CTR_TIPO_BOLETO)");

        $ok = 0; $erros = 0; $logs = [];
        foreach ($rows as $r) {
            $pesId  = (int)($r['VCO_PES_CODIGO_FK'] ?? 0);
            $veiId  = (int)($r['VCO_VEI_CODIGO_FK'] ?? 0);
            $status = trim($r['VCO_STATUS'] ?? '');
            if (strlen($status) !== 1) $status = 'A'; // char(1) NOT NULL

            // Contrato sem pessoa ou sem veículo é órfão — pula
            if ($pesId === 0 || $veiId === 0) {
                $erros++;
                if (count($logs) < 8) $logs[] = "⚠ VCO_PK={$r['VCO_CODIGO_PK']} pulado: sem pessoa/veículo no Firebird";
                continue;
            }

            try {
                $comId      = (int)($r['VCO_COM_CODIGO_FK'] ?? 0);
                $comboValor = $comId > 0 ? ($comboMap[$comId] ?? null) : null;
                $stmt->execute([
                    $r['VCO_CODIGO_PK'],
                    $pesId,
                    $veiId,
                    $r['VCO_GRU_CODIGO_FK']   ?? null,
                    $comId > 0 ? $comId : null,
                    null, // CTR_VISTORIADO_FK → tb_vistoriador: IDs do Firebird não mapeiam
                    $r['VCO_VALOR_ADESAO']    ?? null,
                    $r['VCO_VALOR_MENSALIDADE']?? null,
                    $comboValor,               // CTR_VALOR_COMBO
                    $r['VCO_VALOR_RASTREADOR'] ?? null,
                    $r['VCO_VALOR_CADASTRO']  ?? null,  // CTR_VALOR_VEICULO = valor do veículo
                    $r['VCO_VALOR_PROTEGIDO']  ?? null, // CTR_VALOR_COBERTURA = valor protegido
                    $r['VCO_VALOR_TOTAL']      ?? null,
                    (function($v){ $v=strtoupper(trim($v)); return ($v==='BANCÁRIO'||$v==='BANCARIO') ? 'BANCARIO' : $v; })($r['VCO_TIPO_BOLETO'] ?? ''),
                    $status,
                    $r['VCO_DATA_INCLUSAO']    ?? null,
                    $r['VCO_DATA_CANCELAMENTO']?? null,
                    blob($r['VCO_MOTIVO_CANCELAMENTO']) ?? '',
                    trim($r['CANCEL_NOME']     ?? ''),
                ]);
                $ok++;
            } catch (Throwable $e) {
                $code = (int)($e->errorInfo[1] ?? 0);
                if ($code === 1062) { // duplicate key — ok, já existe
                    $ok++;
                } else {
                    $erros++;
                    if (count($logs) < 8) {
                        $logs[] = "✗ VCO_PK={$r['VCO_CODIGO_PK']} PES={$pesId} VEI={$veiId} ST={$status}: " . $e->getMessage();
                    }
                }
            }
        }

        $done = (count($rows) < $limit);
        j(['success'=>true,'done'=>$done,'imported'=>$ok,'errors'=>$erros,'total'=>$total,'next_offset'=>$offset+$limit,'log'=>$logs]);
    } catch (Throwable $e) { j(['success'=>false,'message'=>$e->getMessage()]); }
}

/* ── 7. tb_vistoria ← TB_VISTORIA ── */
if ($acao === 'migrar_tb_vistoria') {
    try {
        $fb    = getFb($fdbPath);
        $total = (int)$fb->query("SELECT COUNT(*) FROM TB_VISTORIA")->fetchColumn();
        // VEI_ID obtido diretamente do Firebird via TB_VEICULO_CONTRATO
        $rows  = $fb->query("
            SELECT v.VIS_CODIGO_PK, v.VIS_NUM_VIDRO, v.VIS_PNEUS, v.VIS_OBSERVACAO, v.VIS_DATA,
                   (SELECT FIRST 1 vc.VCO_VEI_CODIGO_FK
                    FROM TB_VEICULO_CONTRATO vc
                    WHERE vc.VCO_VIS_CODIGO_FK = v.VIS_CODIGO_PK) AS VEI_ID
            FROM TB_VISTORIA v
            ORDER BY v.VIS_CODIGO_PK
            ROWS " . ($offset + 1) . " TO " . ($offset + $limit)
        )->fetchAll();

        $stmt = $pdo->prepare("INSERT IGNORE INTO tb_vistoria
            (VIS_CODIGO_PK, VEI_CODIGO_FK, VIS_CODIGO_VIDRO, VIS_PNEUS, VIS_OBSERVACAO, VIS_DATA_CAD)
            VALUES (?,?,?,?,?,?)");

        $ok = 0; $erros = 0;
        foreach ($rows as $r) {
            try {
                $stmt->execute([
                    $r['VIS_CODIGO_PK'],
                    $r['VEI_ID']             ?? null,
                    trim($r['VIS_NUM_VIDRO'] ?? ''),
                    trim($r['VIS_PNEUS']     ?? ''),
                    blob($r['VIS_OBSERVACAO'])?? '',
                    $r['VIS_DATA']           ?? null,
                ]);
                $ok++;
            } catch (Throwable $e) { $erros++; }
        }

        $done = (count($rows) < $limit);
        j(['success'=>true,'done'=>$done,'imported'=>$ok,'errors'=>$erros,'total'=>$total,'next_offset'=>$offset+$limit]);
    } catch (Throwable $e) { j(['success'=>false,'message'=>$e->getMessage()]); }
}

/* ── 8. tb_cobranca ← TB_CONTAS (histórico de boletos) ── */
if ($acao === 'migrar_tb_cobranca') {
    try {
        // Garante tabela existe
        $pdo->exec("CREATE TABLE IF NOT EXISTS `tb_cobranca` (
            `COB_CODIGO_PK`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `COB_ID_ORIGINAL`          INT          NULL COMMENT 'TB_CONTAS.CON_CODIGO_PK',
            `PES_CODIGO_FK`            INT          NULL,
            `VEI_CODIGO_FK`            INT          NULL,
            `COB_DATA_CRIACAO`         DATE         NULL,
            `COB_DATA_VENCIMENTO`      DATE         NULL,
            `COB_VALOR`                DECIMAL(15,2) NULL,
            `COB_DESCONTO`             DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `COB_ACRESCIMO`            DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `COB_JUROS`                DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `COB_MULTA`                DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `COB_DATA_QUITACAO`        DATE          NULL,
            `COB_VALOR_QUITADO`        DECIMAL(15,2) NULL,
            `COB_PAGO`                 VARCHAR(3)    NOT NULL DEFAULT 'NÃO',
            `COB_NOSSO_NUMERO`         VARCHAR(30)   NULL,
            `COB_TIPO_BOLETO`          VARCHAR(15)   NULL COMMENT 'INTERNO ou BANCÁRIO',
            `COB_BOLETO_IMPRESSO`      VARCHAR(3)    NOT NULL DEFAULT 'NÃO',
            `COB_ENVIADO_BANCO`        VARCHAR(3)    NOT NULL DEFAULT 'NÃO',
            `COB_NOME_ARQUIVO_REMESSA` VARCHAR(100)  NULL,
            `COB_DATA_ENVIO_BANCO`     DATETIME      NULL,
            `COB_BOLETO_CANCELADO`     VARCHAR(3)    NOT NULL DEFAULT 'NÃO',
            `COB_MOTIVO_CANCELAMENTO`  TEXT          NULL,
            `COB_OCORRENCIA`           VARCHAR(100)  NULL COMMENT 'Código retorno Sicoob',
            `COB_STATUS_BOLETO`        VARCHAR(30)   NULL,
            `COB_PLACAS`               VARCHAR(200)  NULL,
            `COB_OBSERVACAO`           TEXT          NULL,
            `COB_DATA_PROCESSAMENTO`   DATE          NULL,
            `COB_DATA_CAD`             TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`COB_CODIGO_PK`),
            UNIQUE KEY `UK_id_original` (`COB_ID_ORIGINAL`),
            INDEX (`PES_CODIGO_FK`),
            INDEX (`VEI_CODIGO_FK`),
            INDEX (`COB_DATA_VENCIMENTO`),
            INDEX (`COB_PAGO`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
          COMMENT='Histórico de cobranças importado do sistema Ciclo (Firebird)'");

        $fb    = getFb($fdbPath);
        $total = (int)$fb->query("SELECT COUNT(*) FROM TB_CONTAS")->fetchColumn();

        // Tenta JOIN com TB_VEICULO_CONTRATO para derivar PES e VEI quando os campos diretos estão nulos
        try {
            $rows = $fb->query("
                SELECT c.*,
                       COALESCE(c.CON_CLI_CODIGO_FK, vc.VCO_PES_CODIGO_FK) AS PES_DERIVADO,
                       COALESCE(c.CON_VEI_CODIGO_FK, vc.VCO_VEI_CODIGO_FK) AS VEI_DERIVADO
                FROM TB_CONTAS c
                LEFT JOIN TB_VEICULO_CONTRATO vc ON vc.VCO_CODIGO_PK = c.CON_VCO_CODIGO_FK
                ORDER BY c.CON_CODIGO_PK
                ROWS " . ($offset+1) . " TO " . ($offset+$limit)
            )->fetchAll();
        } catch (Throwable $fbJoinErr) {
            // CON_VCO_CODIGO_FK não existe — tenta via subquery por pessoa
            try {
                $rows = $fb->query("
                    SELECT c.*,
                           c.CON_CLI_CODIGO_FK AS PES_DERIVADO,
                           (SELECT FIRST 1 vc2.VCO_VEI_CODIGO_FK
                            FROM TB_VEICULO_CONTRATO vc2
                            WHERE vc2.VCO_PES_CODIGO_FK = c.CON_CLI_CODIGO_FK
                            ORDER BY vc2.VCO_CODIGO_PK DESC) AS VEI_DERIVADO
                    FROM TB_CONTAS c
                    ORDER BY c.CON_CODIGO_PK
                    ROWS " . ($offset+1) . " TO " . ($offset+$limit)
                )->fetchAll();
            } catch (Throwable $fbSubErr) {
                // Último recurso: query simples sem derivação
                $rows = $fb->query("
                    SELECT * FROM TB_CONTAS
                    ORDER BY CON_CODIGO_PK
                    ROWS " . ($offset+1) . " TO " . ($offset+$limit)
                )->fetchAll();
            }
        }

        $stmt = $pdo->prepare("INSERT IGNORE INTO tb_cobranca
            (COB_ID_ORIGINAL,PES_CODIGO_FK,VEI_CODIGO_FK,COB_DATA_CRIACAO,COB_DATA_VENCIMENTO,
             COB_VALOR,COB_DESCONTO,COB_ACRESCIMO,COB_JUROS,COB_MULTA,COB_DATA_QUITACAO,
             COB_VALOR_QUITADO,COB_PAGO,COB_NOSSO_NUMERO,COB_TIPO_BOLETO,COB_BOLETO_IMPRESSO,
             COB_ENVIADO_BANCO,COB_NOME_ARQUIVO_REMESSA,COB_DATA_ENVIO_BANCO,COB_BOLETO_CANCELADO,
             COB_MOTIVO_CANCELAMENTO,COB_OCORRENCIA,COB_STATUS_BOLETO,COB_PLACAS,COB_OBSERVACAO,
             COB_DATA_PROCESSAMENTO)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

        $ok = 0; $erros = 0;
        foreach ($rows as $r) {
            try {
                $stmt->execute([
                    $r['CON_CODIGO_PK'],
                    $r['PES_DERIVADO'] ?? ($r['CON_CLI_CODIGO_FK'] ?? null),
                    $r['VEI_DERIVADO'] ?? ($r['CON_VEI_CODIGO_FK'] ?? null),
                    $r['CON_DATA_CRIACAO']         ?? null,
                    $r['CON_DATA_VENCIMENTO']      ?? null,
                    $r['CON_VALOR']                ?? null,
                    $r['CON_DESCONTO']             ?? 0,
                    $r['CON_ACRESCIMO']            ?? 0,
                    $r['CON_JUROS']                ?? 0,
                    $r['CON_MULTA']                ?? 0,
                    $r['CON_DATA_QUITACAO']        ?? null,
                    $r['CON_VALOR_QUITADO']        ?? null,
                    trim($r['CON_PAGO']            ?? 'NÃO'),
                    trim($r['CON_NOSSO_NUMERO']    ?? ''),
                    trim($r['CON_TIPO_BOLETO']     ?? ''),
                    trim($r['CON_BOLETO_IMPRESSO'] ?? 'NÃO'),
                    trim($r['CON_ENVIADO_BANCO']   ?? 'NÃO'),
                    trim($r['CON_NOME_ARQUIVO_REMESSA'] ?? ''),
                    $r['CON_DATA_ENVIO_BANCO']     ?? null,
                    trim($r['CON_BOLETO_CANCELADO']?? 'NÃO'),
                    blob($r['CON_MOTIVO_CANCELAMENTO']) ?? '',
                    trim($r['CON_OCORRENCIA']      ?? ''),
                    trim($r['CON_STATUS_BOLETO']   ?? ''),
                    trim($r['CON_PLACAS']          ?? ''),
                    blob($r['CON_OBSERVACAO'])     ?? '',
                    $r['CON_DATA_PROCESSAMENTO']   ?? null,
                ]);
                $ok++;
            } catch (Throwable $e) { $erros++; }
        }

        $done = (count($rows) < $limit);
        j(['success'=>true,'done'=>$done,'imported'=>$ok,'errors'=>$erros,'total'=>$total,'next_offset'=>$offset+$limit]);
    } catch (Throwable $e) { j(['success'=>false,'message'=>$e->getMessage()]); }
}

/* ── 8b. corrigir_vinculos_cobranca — vincula PES/VEI usando COB_PLACAS como chave ── */
if ($acao === 'migrar_corrigir_vinculos_cobranca') {
    try {
        // Passo 1: vincula VEI e PES via placa (COB_PLACAS → tb_veiculo.VEI_PLACA)
        // CON_PLACAS do Firebird sempre tem a placa — é a chave mais confiável
        $p1 = (int)$pdo->exec("
            UPDATE tb_cobranca c
            JOIN tb_veiculo v ON TRIM(v.VEI_PLACA) COLLATE utf8mb4_unicode_ci = TRIM(c.COB_PLACAS) COLLATE utf8mb4_unicode_ci
            SET c.VEI_CODIGO_FK = v.VEI_CODIGO_PK,
                c.PES_CODIGO_FK = COALESCE(NULLIF(c.PES_CODIGO_FK, 0), v.PES_CODIGO_FK)
            WHERE (c.VEI_CODIGO_FK IS NULL OR c.VEI_CODIGO_FK = 0)
              AND c.COB_PLACAS IS NOT NULL
              AND TRIM(c.COB_PLACAS) != ''
        ");

        // Passo 2: cobranças que já têm VEI mas não têm PES — busca pelo dono do veículo
        $p2 = (int)$pdo->exec("
            UPDATE tb_cobranca c
            JOIN tb_veiculo v ON v.VEI_CODIGO_PK = c.VEI_CODIGO_FK
            SET c.PES_CODIGO_FK = v.PES_CODIGO_FK
            WHERE c.VEI_CODIGO_FK > 0
              AND (c.PES_CODIGO_FK IS NULL OR c.PES_CODIGO_FK = 0)
        ");

        // Passo 3: restante sem veículo mas com pessoa — usa contrato mais recente da pessoa
        $p3 = (int)$pdo->exec("
            UPDATE tb_cobranca c
            JOIN (
                SELECT t.PES_CODIGO_FK, t.VEI_CODIGO_FK
                FROM tb_contrato t
                JOIN (
                    SELECT PES_CODIGO_FK, MAX(CTR_CODIGO_PK) AS mx
                    FROM tb_contrato
                    WHERE VEI_CODIGO_FK IS NOT NULL AND VEI_CODIGO_FK > 0
                    GROUP BY PES_CODIGO_FK
                ) ul ON ul.PES_CODIGO_FK = t.PES_CODIGO_FK AND ul.mx = t.CTR_CODIGO_PK
            ) vc ON vc.PES_CODIGO_FK = c.PES_CODIGO_FK
            SET c.VEI_CODIGO_FK = vc.VEI_CODIGO_FK
            WHERE (c.VEI_CODIGO_FK IS NULL OR c.VEI_CODIGO_FK = 0)
              AND c.PES_CODIGO_FK > 0
        ");

        $semVei    = (int)$pdo->query("SELECT COUNT(*) FROM tb_cobranca WHERE VEI_CODIGO_FK IS NULL OR VEI_CODIGO_FK = 0")->fetchColumn();
        $semPessoa = (int)$pdo->query("SELECT COUNT(*) FROM tb_cobranca WHERE PES_CODIGO_FK IS NULL OR PES_CODIGO_FK = 0")->fetchColumn();
        $total     = (int)$pdo->query("SELECT COUNT(*) FROM tb_cobranca")->fetchColumn();

        j([
            'success' => true,
            'done'    => true,
            'total'   => $total,
            'imported'=> $p1 + $p2 + $p3,
            'errors'  => $semVei,
            'message' => "Passo 1 (placa): {$p1} | Passo 2 (dono do veículo): {$p2} | Passo 3 (contrato): {$p3}. "
                       . "Restam sem veículo: {$semVei} | sem pessoa: {$semPessoa}.",
        ]);
    } catch (Throwable $e) { j(['success'=>false,'message'=>$e->getMessage()]); }
}

/* ── 9. tb_sinistro ← TB_SINISTRO ── */
if ($acao === 'migrar_tb_sinistro') {
    try {
        $fb    = getFb($fdbPath);
        $total = (int)$fb->query("SELECT COUNT(*) FROM TB_SINISTRO")->fetchColumn();
        $rows  = $fb->query("
            SELECT s.*, e.EST_UF, c.CID_NOME
            FROM TB_SINISTRO s
            LEFT JOIN TB_ESTADO e ON e.EST_CODIGO    = s.SIN_UF_COD_FK
            LEFT JOIN TB_CIDADE c ON c.CID_CODIGO_PK = s.SIN_CID_COD_FK
            ORDER BY s.SIN_CODIGO_PK
            ROWS " . ($offset + 1) . " TO " . ($offset + $limit)
        )->fetchAll();

        $stmt = $pdo->prepare("INSERT IGNORE INTO tb_sinistro
            (SIN_CODIGO_PK,SIN_DATA_LANCAMENTO,SIN_TIPO_OCORRENCIA,VEI_CODIGO_FK,PES_CODIGO_FK,
             SIN_DATA_OCORRENCIA,SIN_HORA_OCORRENCIA,SIN_PRECISA_REBOQUE,SIN_HOUVE_VITIMAS,
             SIN_NUM_BO,SIN_DATA_BO,SIN_HORA_BO,SIN_ORGAO_COMPETENCIA,SIN_VALOR_FIPE,
             SIN_NUM_SINISTROS_ANT,SIN_FRANQUIA_PERC,SIN_VALOR_FRANQUIA,SIN_NOME_CONDUTOR,
             SIN_DATA_NASC_CONDUTOR,SIN_SEXO_CONDUTOR,SIN_CNH_CONDUTOR,SIN_VALIDADE_CNH,
             SIN_BAIRRO_OCORRENCIA,SIN_PONTO_REFERENCIA,SIN_CIDADE_OCORRENCIA,SIN_UF_OCORRENCIA,
             SIN_DETALHE,SIN_DANOS_VEICULO,SIN_STATUS,SIN_USUARIO_NOME)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

        $ok = 0; $erros = 0;
        foreach ($rows as $r) {
            try {
                $sx = strtoupper(trim($r['SIN_SEXO_CONDUTOR'] ?? ''));
                $sexo = ($sx === 'M') ? 'M' : (($sx === 'F') ? 'F' : null);
                $stmt->execute([
                    $r['SIN_CODIGO_PK'],
                    $r['SIN_DATA_LANCAMENTO']       ?? null,
                    trim($r['SIN_TIPO_SINISTRO']    ?? ''),
                    $r['SIN_VEI_COD_FK']            ?? null,
                    $r['SIN_PES_COD_FK']            ?? null,
                    $r['SIN_DATA_OCORRENCIA']       ?? null,
                    $r['SIN_HORA_OCORRENCIA']       ?? null,
                    simNao($r['SIN_REBOQUE']        ?? 'N'),
                    simNao($r['SIN_VITIMAS']        ?? 'N'),
                    trim($r['SIN_NUM_BO']           ?? ''),
                    $r['SIN_DATA_BO']               ?? null,
                    $r['SIN_HORA_BO']               ?? null,
                    trim($r['SIN_ORGAO']            ?? ''),
                    $r['SIN_VALOR_FIPE']            ?? null,
                    $r['SIN_NUM_SINISTROS']         ?? 0,
                    $r['SIN_PORC_FRANQUIA']         ?? null,
                    $r['SIN_VALOR_FRANQUIA']        ?? null,
                    trim($r['SIN_NOME_CONDUTOR']    ?? ''),
                    $r['SIN_DATA_NASC_CONDUTOR']    ?? null,
                    $sexo,
                    trim($r['SIN_CNH_CONDUTOR']     ?? ''),
                    $r['SIN_VALIDADE_CNH_CONDUTOR'] ?? null,
                    trim($r['SIN_BAIRRO_OCORRENCIA']?? ''),
                    trim($r['SIN_PONTO_REF_OCORRENCIA']?? ''),
                    trim($r['CID_NOME']             ?? ''),
                    trim($r['EST_UF']               ?? ''),
                    blob($r['SIN_DETALHE_SINISTRO'])?? '',
                    blob($r['SIN_DANOS_VEICULO'])   ?? '',
                    sinStatus(trim($r['SIN_STATUS']  ?? 'A')),
                    trim($r['SIN_NOME_ASSOCIADO']   ?? ''),
                ]);
                $ok++;
            } catch (Throwable $e) { $erros++; }
        }

        $done = (count($rows) < $limit);
        j(['success'=>true,'done'=>$done,'imported'=>$ok,'errors'=>$erros,'total'=>$total,'next_offset'=>$offset+$limit]);
    } catch (Throwable $e) { j(['success'=>false,'message'=>$e->getMessage()]); }
}

/* ── 10. tb_imagens ← TB_IMAGEM_VEICULO (+ cópia de arquivos) ── */
if ($acao === 'migrar_tb_imagens') {
    try {
        $fb    = getFb($fdbPath);
        $total = (int)$fb->query("SELECT COUNT(*) FROM TB_IMAGEM_VEICULO")->fetchColumn();
        $rows  = $fb->query("
            SELECT i.*,
                   v.VEI_PLACA,
                   (SELECT FIRST 1 vc.VCO_PES_CODIGO_FK
                    FROM TB_VEICULO_CONTRATO vc
                    WHERE vc.VCO_VEI_CODIGO_FK = i.IVE_VEI_CODIGO_FK
                      AND vc.VCO_PES_CODIGO_FK IS NOT NULL
                      AND vc.VCO_PES_CODIGO_FK <> 0
                    ORDER BY vc.VCO_CODIGO_PK DESC) AS PES_ID
            FROM TB_IMAGEM_VEICULO i
            LEFT JOIN TB_VEICULO v ON v.VEI_CODIGO_PK = i.IVE_VEI_CODIGO_FK
            ORDER BY i.IVE_VEI_CODIGO_FK, i.IVE_CODIGO_PK
            ROWS " . ($offset+1) . " TO " . ($offset+$limit)
        )->fetchAll();

        $stmt = $pdo->prepare("INSERT INTO tb_imagens
            (IMG_CODIGO_PK, IMG_VEICULO_FK, IMG_CAMINHO, IMG_CHASSI)
            VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE IMG_CHASSI=VALUES(IMG_CHASSI)");

        $ok = 0; $erros = 0; $semArquivo = 0; $logs = [];
        $imgIdxPorVei = [];
        foreach ($rows as $r) {
            $veiId  = (int)($r['IVE_VEI_CODIGO_FK'] ?? 0);
            $placa  = trim($r['VEI_PLACA'] ?? '');
            $pesId  = (int)($r['PES_ID']   ?? 0);
            if (!isset($imgIdxPorVei[$veiId])) $imgIdxPorVei[$veiId] = 0;
            $imgIdx = ++$imgIdxPorVei[$veiId];

            [$copiou, $url] = copiarImagem($r['IVE_CAMINHO'], $imgBase, PATH_UPLOAD, $veiId, $placa, $pesId, $imgIdx);
            if (!$copiou) {
                $semArquivo++;
                if ($semArquivo <= 3) $logs[] = '⚠ Arquivo não encontrado: ' . basename($r['IVE_CAMINHO']);
            }
            $ehChassi = (trim($r['IVE_TIPO'] ?? '') === 'C') ? 'SIM' : 'NÃO';
            try {
                $stmt->execute([$r['IVE_CODIGO_PK'], $veiId, $url, $ehChassi]);
                $ok++;
            } catch (Throwable $e) { $erros++; }
        }

        if ($semArquivo > 3) $logs[] = "... e mais " . ($semArquivo-3) . " arquivos não encontrados.";
        if ($semArquivo > 0) $logs[] = "Dica: extraia o ZIP das imagens em C:\\CICLO\\imagens\\";

        $done = (count($rows) < $limit);
        j(['success'=>true,'done'=>$done,'imported'=>$ok,'errors'=>$erros,'total'=>$total,
           'next_offset'=>$offset+$limit,'log'=>$logs]);
    } catch (Throwable $e) { j(['success'=>false,'message'=>$e->getMessage()]); }
}

/* ── 11. tb_sinistro_imagem ← TB_IMAGEM_SINISTRO ── */
if ($acao === 'migrar_tb_sinistro_imagem') {
    try {
        $fb    = getFb($fdbPath);
        $total = (int)$fb->query("SELECT COUNT(*) FROM TB_IMAGEM_SINISTRO")->fetchColumn();
        $rows  = $fb->query("
            SELECT i.*,
                   v.VEI_PLACA,
                   (SELECT FIRST 1 vc.VCO_PES_CODIGO_FK
                    FROM TB_VEICULO_CONTRATO vc
                    WHERE vc.VCO_VEI_CODIGO_FK = i.ISI_VEI_CODIGO_FK
                      AND vc.VCO_PES_CODIGO_FK IS NOT NULL
                      AND vc.VCO_PES_CODIGO_FK <> 0
                    ORDER BY vc.VCO_CODIGO_PK DESC) AS PES_ID
            FROM TB_IMAGEM_SINISTRO i
            LEFT JOIN TB_VEICULO v ON v.VEI_CODIGO_PK = i.ISI_VEI_CODIGO_FK
            ORDER BY i.ISI_VEI_CODIGO_FK, i.ISI_CODIGO_PK
            ROWS " . ($offset+1) . " TO " . ($offset+$limit)
        )->fetchAll();

        $stmt = $pdo->prepare("INSERT IGNORE INTO tb_sinistro_imagem
            (SIM_CODIGO_PK, SIN_CODIGO_FK, SIM_TIPO, SIM_CAMINHO)
            VALUES (?,
              (SELECT SIN_CODIGO_PK FROM tb_sinistro WHERE VEI_CODIGO_FK=? ORDER BY SIN_CODIGO_PK DESC LIMIT 1),
              ?,?)");

        $ok = 0; $erros = 0; $semArquivo = 0; $logs = [];
        $imgIdxPorVei = [];
        foreach ($rows as $r) {
            $veiId  = (int)($r['ISI_VEI_CODIGO_FK'] ?? 0);
            $placa  = trim($r['VEI_PLACA'] ?? '');
            $pesId  = (int)($r['PES_ID']   ?? 0);
            if (!isset($imgIdxPorVei[$veiId])) $imgIdxPorVei[$veiId] = 0;
            $imgIdx = ++$imgIdxPorVei[$veiId];

            $it   = strtoupper(trim($r['ISI_TIPO'] ?? 'A'));
            $tipo = ($it === 'P' || $it === 'D') ? 'DEPOIS' : 'ANTES';

            [$copiou, $url] = copiarImagem($r['ISI_CAMINHO'], $imgBase, PATH_UPLOAD, $veiId, $placa, $pesId, $imgIdx);
            if (!$copiou) {
                $semArquivo++;
                if ($semArquivo <= 3) $logs[] = '⚠ Arquivo não encontrado: ' . basename($r['ISI_CAMINHO']);
            }
            try {
                $stmt->execute([$r['ISI_CODIGO_PK'], $veiId, $tipo, $url]);
                $ok++;
            } catch (Throwable $e) { $erros++; }
        }

        if ($semArquivo > 3) $logs[] = "... e mais " . ($semArquivo-3) . " arquivos não encontrados.";

        $done = (count($rows) < $limit);
        j(['success'=>true,'done'=>$done,'imported'=>$ok,'errors'=>$erros,'total'=>$total,'next_offset'=>$offset+$limit,'log'=>$logs]);
    } catch (Throwable $e) { j(['success'=>false,'message'=>$e->getMessage()]); }
}

/* ── 12. tb_historico_vistoria ← TB_HISTORICO ── */
if ($acao === 'migrar_tb_historico_vistoria') {
    try {
        $fb    = getFb($fdbPath);
        $total = (int)$fb->query("SELECT COUNT(*) FROM TB_HISTORICO")->fetchColumn();
        $rows  = $fb->query("
            SELECT h.*, p.PES_RAZAO_SOCIAL AS VISTORIADOR_NOME
            FROM TB_HISTORICO h
            LEFT JOIN TB_PESSOA p ON p.PES_CODIGO_PK = h.HIS_VISTORIADOR_FK
            ORDER BY h.HIS_DATA
            ROWS " . ($offset+1) . " TO " . ($offset+$limit)
        )->fetchAll();

        $stmt = $pdo->prepare("INSERT IGNORE INTO tb_historico_vistoria
            (HIS_TIPO, HIS_DATA, HIS_VEI_CODIGO_FK, HIS_PES_CODIGO_FK, HIS_VISTORIADOR)
            VALUES (?,?,?,?,?)");

        $ok = 0; $erros = 0;
        foreach ($rows as $r) {
            try {
                $stmt->execute([
                    trim($r['HIS_TIPO']         ?? ''),
                    $r['HIS_DATA']              ?? null,
                    $r['HIS_VEI_CODIGO_FK']     ?? null,
                    $r['HIS_PES_CODIGO_FK']     ?? null,
                    trim($r['VISTORIADOR_NOME'] ?? ''),
                ]);
                $ok++;
            } catch (Throwable $e) { $erros++; }
        }

        $done = (count($rows) < $limit);
        j(['success'=>true,'done'=>$done,'imported'=>$ok,'errors'=>$erros,'total'=>$total,'next_offset'=>$offset+$limit]);
    } catch (Throwable $e) { j(['success'=>false,'message'=>$e->getMessage()]); }
}

/* ── corrigir_vinculos — atualiza PES_CODIGO_FK no tb_veiculo via tb_contrato ── */
if ($acao === 'migrar_corrigir_vinculos') {
    global $pdo;
    try {
        $sql = "UPDATE tb_veiculo v
                JOIN (
                    SELECT c1.VEI_CODIGO_FK, c1.PES_CODIGO_FK
                    FROM tb_contrato c1
                    WHERE c1.CTR_CODIGO_PK = (
                        SELECT MAX(c2.CTR_CODIGO_PK)
                        FROM tb_contrato c2
                        WHERE c2.VEI_CODIGO_FK = c1.VEI_CODIGO_FK
                          AND c2.PES_CODIGO_FK IS NOT NULL
                          AND c2.PES_CODIGO_FK != 0
                    )
                ) latest ON latest.VEI_CODIGO_FK = v.VEI_CODIGO_PK
                SET v.PES_CODIGO_FK = latest.PES_CODIGO_FK
                WHERE v.PES_CODIGO_FK IS NULL OR v.PES_CODIGO_FK = 0";
        $affected = $pdo->exec($sql);
        j(['success'=>true,'done'=>true,'imported'=>$affected,'total'=>$affected,'errors'=>0,
           'message'=>"{$affected} veículo(s) vinculado(s) ao associado via contrato."]);
    } catch (Throwable $e) {
        j(['success'=>false,'message'=>$e->getMessage()]);
    }
}

/* ── resetar_tudo — limpa todas as tabelas de migração, preserva usuários/config/fipe ── */
if ($acao === 'resetar_tudo') {
    global $pdo;
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $tabelas = [
            'tb_historico_vistoria', 'tb_sinistro_imagem', 'tb_imagens',
            'tb_sinistro', 'tb_cobranca', 'tb_vistoria',
            'tb_contrato_evento', 'tb_contrato', 'tb_veiculo', 'tb_pessoa',
            'tb_servico_combo', 'tb_combo', 'tb_grupo', 'tb_cor',
        ];
        $truncados = [];
        foreach ($tabelas as $t) {
            try {
                $pdo->exec("TRUNCATE TABLE `{$t}`");
                $truncados[] = $t;
            } catch (Throwable $e) { /* tabela pode não existir ainda */ }
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        j(['success'=>true,
           'message'=>'Base limpa (' . count($truncados) . ' tabelas). Usuários, configurações e FIPE preservados.',
           'tabelas'=>$truncados]);
    } catch (Throwable $e) {
        try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 1"); } catch (Throwable $e2) {}
        j(['success'=>false,'message'=>$e->getMessage()]);
    }
}

j(['success'=>false,'message'=>"Ação desconhecida: {$acao}"]);
