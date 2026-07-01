<?php
declare(strict_types=1);

require __DIR__ . '/../inc/config.php';
require PATH_INC . '/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

@header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

if (empty($_SESSION['SessUsuCodigo'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}

$acao = $_GET['acao'] ?? ($_POST['acao'] ?? '');

/* ── GET: listar_por_pessoa ────────────────────────────────────────────── */
if ($acao === 'listar_por_pessoa' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $pesId   = (int)($_GET['pessoa_id']  ?? 0);
    $veiId   = (int)($_GET['veiculo_id'] ?? 0);
    $limit   = min((int)($_GET['limit']  ?? 200), 500);
    $offset  = max((int)($_GET['offset'] ?? 0), 0);

    if ($pesId <= 0) {
        echo json_encode([]);
        exit;
    }

    try {
        $where  = ['c.PES_CODIGO_FK = ?'];
        $params = [$pesId];

        if ($veiId > 0) {
            $where[]  = 'c.VEI_CODIGO_FK = ?';
            $params[] = $veiId;
        }

        $sql = "SELECT
                    c.COB_CODIGO_PK,
                    c.COB_DATA_CRIACAO,
                    c.COB_DATA_VENCIMENTO,
                    c.COB_VALOR,
                    c.COB_DESCONTO,
                    c.COB_ACRESCIMO,
                    c.COB_JUROS,
                    c.COB_MULTA,
                    c.COB_DATA_QUITACAO,
                    c.COB_VALOR_QUITADO,
                    c.COB_PAGO,
                    c.COB_NOSSO_NUMERO,
                    c.COB_TIPO_BOLETO,
                    c.COB_BOLETO_IMPRESSO,
                    c.COB_ENVIADO_BANCO,
                    c.COB_NOME_ARQUIVO_REMESSA,
                    c.COB_DATA_ENVIO_BANCO,
                    c.COB_BOLETO_CANCELADO,
                    c.COB_MOTIVO_CANCELAMENTO,
                    c.COB_OCORRENCIA,
                    c.COB_STATUS_BOLETO,
                    c.COB_PLACAS,
                    c.COB_OBSERVACAO,
                    c.VEI_CODIGO_FK,
                    v.VEI_PLACA,
                    v.VEI_MARCA,
                    v.VEI_MODELO
                FROM tb_cobranca c
                LEFT JOIN tb_veiculo v ON v.VEI_CODIGO_PK = c.VEI_CODIGO_FK
                WHERE " . implode(' AND ', $where) . "
                ORDER BY c.COB_DATA_VENCIMENTO DESC, c.COB_CODIGO_PK DESC
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'total'   => count($rows),
            'data'    => $rows,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao listar cobranças.', 'debug' => $e->getMessage()]);
    }
    exit;
}

/* ── GET: resumo_por_pessoa (totais para o header da ficha) ───────────── */
if ($acao === 'resumo_por_pessoa' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $pesId = (int)($_GET['pessoa_id'] ?? 0);
    if ($pesId <= 0) { echo json_encode(['success' => false]); exit; }

    try {
        $stmt = $pdo->prepare("
            SELECT
                COUNT(*)                                                     AS total,
                SUM(COB_VALOR)                                               AS valor_total,
                SUM(CASE WHEN COB_PAGO = 'SIM'               THEN 1 ELSE 0 END) AS total_pagas,
                SUM(CASE WHEN COB_PAGO = 'NÃO'
                         AND COB_BOLETO_CANCELADO != 'SIM'
                         AND COB_DATA_VENCIMENTO < CURDATE()  THEN 1 ELSE 0 END) AS total_vencidas,
                SUM(CASE WHEN COB_PAGO = 'NÃO'
                         AND COB_BOLETO_CANCELADO != 'SIM'
                         AND COB_DATA_VENCIMENTO >= CURDATE() THEN 1 ELSE 0 END) AS total_abertas
            FROM tb_cobranca
            WHERE PES_CODIGO_FK = ?
        ");
        $stmt->execute([$pesId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $row], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'data' => []]);
    }
    exit;
}

/* ── GET: buscar_nosso_numero ──────────────────────────────────────────── */
if ($acao === 'buscar_nosso_numero' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $codigo = trim($_GET['nosso_numero'] ?? '');
    if ($codigo === '') {
        echo json_encode(['success' => false, 'message' => 'Informe o código da parcela.']);
        exit;
    }
    /* Busca por COB_CODIGO_PK (nº interno) OU COB_NOSSO_NUMERO (boleto bancário).
       Prioriza COB_CODIGO_PK quando ambos batem no mesmo valor. */
    $cobPk = is_numeric($codigo) ? (int)$codigo : 0;
    try {
        $st = $pdo->prepare("
            SELECT c.COB_CODIGO_PK,
                   c.COB_NOSSO_NUMERO,
                   c.COB_DATA_VENCIMENTO,
                   c.COB_VALOR,
                   c.COB_DESCONTO,
                   c.COB_JUROS,
                   c.COB_MULTA,
                   c.COB_PAGO,
                   c.COB_DATA_QUITACAO,
                   c.COB_VALOR_QUITADO,
                   c.COB_BOLETO_CANCELADO,
                   c.COB_PLACAS,
                   v.VEI_PLACA,
                   p.PES_NOME,
                   p.PES_CODIGO_PK AS PES_ID
              FROM tb_cobranca c
              LEFT JOIN tb_veiculo v ON v.VEI_CODIGO_PK = c.VEI_CODIGO_FK
              LEFT JOIN tb_pessoa  p ON p.PES_CODIGO_PK = c.PES_CODIGO_FK
             WHERE c.COB_CODIGO_PK = ? OR c.COB_NOSSO_NUMERO = ?
             ORDER BY CASE WHEN c.COB_CODIGO_PK = ? THEN 0 ELSE 1 END
             LIMIT 1
        ");
        $st->execute([$cobPk, $codigo, $cobPk]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Parcela não encontrada.']);
        } else {
            echo json_encode(['success' => true, 'data' => $row]);
        }
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao buscar: ' . $e->getMessage()]);
    }
    exit;
}

/* ── POST: baixar_manual ───────────────────────────────────────────────── */
if ($acao === 'baixar_manual' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $cobId         = (int)($_POST['cob_id']        ?? 0);
    $desconto      = (float)str_replace(',', '.', $_POST['desconto']       ?? '0');
    $juros         = (float)str_replace(',', '.', $_POST['juros']          ?? '0');
    $multa         = (float)str_replace(',', '.', $_POST['multa']          ?? '0');
    $valorPagar    = (float)str_replace(',', '.', $_POST['valor_pagar']    ?? '0');
    $valorRecebido = (float)str_replace(',', '.', $_POST['valor_recebido'] ?? '0');
    $hoje          = date('Y-m-d');
    $nomeUsuario   = $_SESSION['SessUsuNome'] ?? 'Desconhecido';


    if ($cobId <= 0 || $valorPagar <= 0) {
        echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
        exit;
    }
    try {
        $obs = 'Baixa manual por: ' . $nomeUsuario
             . ' | Recebido: R$ ' . number_format($valorRecebido, 2, ',', '.')
             . ' | Data: ' . date('d/m/Y H:i');

        $st = $pdo->prepare("
            UPDATE tb_cobranca
               SET COB_PAGO                = 'SIM',
                   COB_DATA_QUITACAO       = ?,
                   COB_DATA_PROCESSAMENTO  = ?,
                   COB_VALOR_QUITADO       = ?,
                   COB_DESCONTO            = ?,
                   COB_JUROS               = ?,
                   COB_MULTA               = ?,
                   COB_OCORRENCIA          = 'MANUAL',
                   COB_STATUS_BOLETO       = 'LIQUIDADO',
                   COB_OBSERVACAO          = CONCAT(
                                                IFNULL(COB_OBSERVACAO, ''),
                                                IF(COB_OBSERVACAO IS NOT NULL AND COB_OBSERVACAO <> '', '\n', ''),
                                                ?
                                            )
             WHERE COB_CODIGO_PK = ?
               AND COB_PAGO = 'NÃO'
        ");
        $st->execute([$hoje, $hoje, $valorPagar, $desconto, $juros, $multa, $obs, $cobId]);

        $rows = $st->rowCount();
        if ($rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Parcela não encontrada ou já baixada.']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Parcela baixada com sucesso!']);
        }
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao baixar: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => true, 'message' => 'Ação inválida.']);
