<?php
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require __DIR__ . '/../inc/config.php';
require PATH_INC . '/db.php';
require PATH_INC . '/Cnab240Sicoob.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['SessUsuCodigo'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}

$acao = $_GET['acao'] ?? ($_POST['acao'] ?? '');

/* ── GET: listar parcelas por vencimento ────────────────────────────────── */
if ($acao === 'listar' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    $dtIni = $_GET['dt_ini'] ?? '';
    $dtFim = $_GET['dt_fim'] ?? '';

    if (!$dtIni || !$dtFim) {
        echo json_encode(['success' => false, 'message' => 'Informe as datas.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT
                c.COB_CODIGO_PK,
                c.COB_NOSSO_NUMERO,
                c.COB_DATA_VENCIMENTO,
                c.COB_VALOR,
                c.COB_PAGO,
                c.COB_ENVIADO_BANCO,
                c.COB_BOLETO_CANCELADO,
                c.COB_TIPO_BOLETO,
                c.COB_NOME_ARQUIVO_REMESSA,
                c.COB_DATA_ENVIO_BANCO,
                p.PES_NOME,
                p.PES_CPF_CNPJ,
                p.PES_TIPO,
                p.PES_ENDERECO,
                p.PES_NUMERO,
                p.PES_BAIRRO,
                p.PES_CEP,
                p.PES_CIDADE,
                p.PES_UF
            FROM tb_cobranca c
            INNER JOIN tb_pessoa p ON p.PES_CODIGO_PK = c.PES_CODIGO_FK
            WHERE c.COB_DATA_VENCIMENTO BETWEEN ? AND ?
              AND c.COB_DATA_VENCIMENTO  >= CURDATE()
              AND UPPER(c.COB_TIPO_BOLETO) != 'INTERNO'
              AND UPPER(c.COB_PAGO)         = 'NÃO'
              AND (c.COB_BOLETO_CANCELADO IS NULL OR UPPER(c.COB_BOLETO_CANCELADO) != 'SIM')
            ORDER BY c.COB_DATA_VENCIMENTO ASC, p.PES_NOME ASC
        ");
        $stmt->execute([$dtIni, $dtFim]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = array_sum(array_column($rows, 'COB_VALOR'));

        echo json_encode([
            'success' => true,
            'total'   => count($rows),
            'soma'    => $total,
            'data'    => $rows,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao listar.', 'debug' => $e->getMessage()]);
    }
    exit;
}

/* ── POST: gerar arquivo de remessa CNAB 240 ────────────────────────────── */
if ($acao === 'gerar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));

    if (empty($ids)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Nenhuma parcela selecionada.']);
        exit;
    }

    try {
        /* carrega configurações */
        $cfg = $pdo->query("SELECT * FROM tb_configuracoes WHERE CFG_CODIGO_PK = 1 LIMIT 1")
                   ->fetch(PDO::FETCH_ASSOC);
        if (!$cfg) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Configurações não encontradas. Configure o sistema primeiro.']);
            exit;
        }

        /* busca os boletos selecionados com dados do sacado */
        $in     = implode(',', $ids);
        $boletos = $pdo->query("
            SELECT
                c.COB_CODIGO_PK,
                c.COB_NOSSO_NUMERO,
                c.COB_DATA_VENCIMENTO,
                c.COB_VALOR,
                p.PES_NOME,
                p.PES_CPF_CNPJ,
                p.PES_TIPO,
                p.PES_ENDERECO,
                p.PES_NUMERO,
                p.PES_BAIRRO,
                p.PES_CEP,
                p.PES_CIDADE,
                p.PES_UF
            FROM tb_cobranca c
            INNER JOIN tb_pessoa p ON p.PES_CODIGO_PK = c.PES_CODIGO_FK
            WHERE c.COB_CODIGO_PK IN ({$in})
            ORDER BY c.COB_DATA_VENCIMENTO ASC, p.PES_NOME ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        if (empty($boletos)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Nenhum boleto encontrado para os IDs informados.']);
            exit;
        }

        /* gera o conteúdo CNAB 240 */
        $conteudo = Cnab240Sicoob::gerarRemessa($cfg, $boletos);

        /* nome e caminho do arquivo */
        $seq      = (int)($cfg['CFG_CNAB_SEQUENCIAL_ARQUIVO'] ?? 1);
        $path     = rtrim($cfg['CFG_CNAB_REMESSA_PATH'] ?? 'C:\\remessa', '\\/');
        $nomeArq  = Cnab240Sicoob::nomeArquivo($path, $seq);
        $nomeBase = basename($nomeArq);

        /* salva no servidor */
        Cnab240Sicoob::escreverArquivo($nomeArq, $conteudo);

        /* incrementa sequencial no banco */
        $pdo->prepare("UPDATE tb_configuracoes SET CFG_CNAB_SEQUENCIAL_ARQUIVO = CFG_CNAB_SEQUENCIAL_ARQUIVO + 1 WHERE CFG_CODIGO_PK = 1")
            ->execute();

        /* marca boletos como enviados */
        $agora = date('Y-m-d H:i:s');
        $upd   = $pdo->prepare("
            UPDATE tb_cobranca
               SET COB_ENVIADO_BANCO       = 'SIM',
                   COB_NOME_ARQUIVO_REMESSA = ?,
                   COB_DATA_ENVIO_BANCO     = ?
             WHERE COB_CODIGO_PK = ?
        ");
        foreach ($boletos as $bol) {
            $upd->execute([$nomeBase, $agora, $bol['COB_CODIGO_PK']]);
        }

        /* envia o arquivo para download */
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $nomeBase . '"');
        header('Content-Length: ' . strlen($conteudo));
        header('Cache-Control: no-store, no-cache');
        echo $conteudo;
    } catch (Throwable $e) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao gerar remessa: ' . $e->getMessage()]);
    }
    exit;
}

/* ── GET: buscar individual por associado ou nosso número ───────────────── */
if ($acao === 'buscar_individual' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    $q = trim($_GET['q'] ?? '');

    if (mb_strlen($q) < 2) {
        echo json_encode(['success' => false, 'message' => 'Informe ao menos 2 caracteres.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT
                c.COB_CODIGO_PK,
                c.COB_NOSSO_NUMERO,
                c.COB_DATA_VENCIMENTO,
                c.COB_VALOR,
                c.COB_ENVIADO_BANCO,
                c.COB_TIPO_BOLETO,
                c.PES_CODIGO_FK,
                p.PES_NOME,
                p.PES_CPF_CNPJ,
                p.PES_TIPO,
                p.PES_ENDERECO,
                p.PES_NUMERO,
                p.PES_BAIRRO,
                p.PES_CEP,
                p.PES_CIDADE,
                p.PES_UF
            FROM tb_cobranca c
            INNER JOIN tb_pessoa p ON p.PES_CODIGO_PK = c.PES_CODIGO_FK
            WHERE (p.PES_NOME LIKE ? OR c.COB_NOSSO_NUMERO LIKE ? OR p.PES_CODIGO_PK LIKE ?)
              AND c.COB_DATA_VENCIMENTO  >= CURDATE()
              AND UPPER(c.COB_TIPO_BOLETO) != 'INTERNO'
              AND UPPER(c.COB_PAGO)         = 'NÃO'
              AND (c.COB_BOLETO_CANCELADO IS NULL OR UPPER(c.COB_BOLETO_CANCELADO) != 'SIM')
            ORDER BY c.COB_DATA_VENCIMENTO ASC, p.PES_NOME ASC
            LIMIT 30
        ");
        $like = '%' . $q . '%';
        $stmt->execute([$like, $like, $like]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro na busca.', 'debug' => $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
header('Content-Type: application/json');
echo json_encode(['error' => true, 'message' => 'Ação inválida.']);
