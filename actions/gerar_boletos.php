<?php
declare(strict_types=1);

require __DIR__ . '/../inc/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require PATH_INC . '/db.php';
require PATH_INC . '/csrf.php';

@header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

if (empty($_SESSION['SessUsuCodigo'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}

$acao = $_GET['acao'] ?? ($_POST['acao'] ?? '');

/* ─── Valida data ─── */
function validar_data(string $dt): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dt)) return false;
    [$y, $m, $d] = explode('-', $dt);
    return checkdate((int)$m, (int)$d, (int)$y);
}

/* ─── Query base: contratos ativos sem cobrança na data ─── */
function contratos_sem_cobranca(PDO $pdo, string $vencimento): array
{
    return $pdo->prepare("
        SELECT
            c.CTR_CODIGO_PK,
            c.PES_CODIGO_FK,
            c.VEI_CODIGO_FK,
            COALESCE(c.CTR_VALOR_MENSALIDADE, 0) AS valor,
            COALESCE(c.CTR_TIPO_BOLETO, 'INTERNO') AS tipo_boleto,
            v.VEI_PLACA
        FROM tb_contrato c
        JOIN tb_veiculo v ON v.VEI_CODIGO_PK = c.VEI_CODIGO_FK
        WHERE c.CTR_STATUS = 'A'
          AND NOT EXISTS (
              SELECT 1 FROM tb_cobranca cb
              WHERE cb.VEI_CODIGO_FK = c.VEI_CODIGO_FK
                AND cb.COB_DATA_VENCIMENTO = ?
                AND (cb.COB_BOLETO_CANCELADO IS NULL OR cb.COB_BOLETO_CANCELADO != 'SIM')
          )
    ")->execute([$vencimento]) ? [] : [];
}

/* ─── GET: preview ─── */
if ($acao === 'preview' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $vencimento = trim($_GET['vencimento'] ?? '');
    if (!validar_data($vencimento)) {
        echo json_encode(['success' => false, 'message' => 'Data inválida.']);
        exit;
    }
    try {
        $totalAtivos = (int)$pdo->query("SELECT COUNT(*) FROM tb_contrato WHERE CTR_STATUS = 'A'")->fetchColumn();

        $existentes = (int)$pdo->prepare("
            SELECT COUNT(DISTINCT c.CTR_CODIGO_PK)
            FROM tb_contrato c
            JOIN tb_veiculo v ON v.VEI_CODIGO_PK = c.VEI_CODIGO_FK
            WHERE c.CTR_STATUS = 'A'
              AND EXISTS (
                  SELECT 1 FROM tb_cobranca cb
                  WHERE cb.VEI_CODIGO_FK = c.VEI_CODIGO_FK
                    AND cb.COB_DATA_VENCIMENTO = ?
                    AND (cb.COB_BOLETO_CANCELADO IS NULL OR cb.COB_BOLETO_CANCELADO != 'SIM')
              )
        ")->execute([$vencimento])
            ? (int)$pdo->prepare("
                SELECT COUNT(DISTINCT c.CTR_CODIGO_PK)
                FROM tb_contrato c
                JOIN tb_veiculo v ON v.VEI_CODIGO_PK = c.VEI_CODIGO_FK
                WHERE c.CTR_STATUS = 'A'
                  AND EXISTS (
                      SELECT 1 FROM tb_cobranca cb
                      WHERE cb.VEI_CODIGO_FK = c.VEI_CODIGO_FK
                        AND cb.COB_DATA_VENCIMENTO = ?
                        AND (cb.COB_BOLETO_CANCELADO IS NULL OR cb.COB_BOLETO_CANCELADO != 'SIM')
                  )
            ")->execute([$vencimento])
            : 0;

        // Usar query direta para evitar problema com execute() retornando bool
        $stEx = $pdo->prepare("
            SELECT COUNT(DISTINCT c.CTR_CODIGO_PK) AS total
            FROM tb_contrato c
            JOIN tb_veiculo v ON v.VEI_CODIGO_PK = c.VEI_CODIGO_FK
            WHERE c.CTR_STATUS = 'A'
              AND EXISTS (
                  SELECT 1 FROM tb_cobranca cb
                  WHERE cb.VEI_CODIGO_FK = c.VEI_CODIGO_FK
                    AND cb.COB_DATA_VENCIMENTO = ?
                    AND (cb.COB_BOLETO_CANCELADO IS NULL OR cb.COB_BOLETO_CANCELADO != 'SIM')
              )
        ");
        $stEx->execute([$vencimento]);
        $existentes = (int)$stEx->fetchColumn();

        $novos = $totalAtivos - $existentes;

        echo json_encode([
            'success'      => true,
            'total_ativos' => $totalAtivos,
            'existentes'   => $existentes,
            'novos'        => max(0, $novos),
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

/* ─── POST: gerar ─── */
if ($acao === 'gerar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $tok = $_POST['csrf'] ?? null;
    if ($tok !== null && !csrf_check($tok)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'CSRF inválido.']);
        exit;
    }

    $vencimento = trim($_POST['vencimento'] ?? '');
    if (!validar_data($vencimento)) {
        echo json_encode(['success' => false, 'message' => 'Data de vencimento inválida.']);
        exit;
    }

    try {
        $st = $pdo->prepare("
            SELECT
                c.CTR_CODIGO_PK,
                c.PES_CODIGO_FK,
                c.VEI_CODIGO_FK,
                COALESCE(c.CTR_VALOR_MENSALIDADE, 0.00) AS valor,
                COALESCE(c.CTR_TIPO_BOLETO, 'INTERNO')  AS tipo_boleto,
                v.VEI_PLACA
            FROM tb_contrato c
            JOIN tb_veiculo v ON v.VEI_CODIGO_PK = c.VEI_CODIGO_FK
            WHERE c.CTR_STATUS = 'A'
              AND NOT EXISTS (
                  SELECT 1 FROM tb_cobranca cb
                  WHERE cb.VEI_CODIGO_FK = c.VEI_CODIGO_FK
                    AND cb.COB_DATA_VENCIMENTO = ?
                    AND (cb.COB_BOLETO_CANCELADO IS NULL OR cb.COB_BOLETO_CANCELADO != 'SIM')
              )
        ");
        $st->execute([$vencimento]);
        $contratos = $st->fetchAll(PDO::FETCH_ASSOC);

        if (empty($contratos)) {
            echo json_encode(['success' => false, 'message' => 'Nenhum contrato elegível para esta data.']);
            exit;
        }

        $insert = $pdo->prepare("
            INSERT INTO tb_cobranca
                (PES_CODIGO_FK, VEI_CODIGO_FK, COB_DATA_CRIACAO, COB_DATA_VENCIMENTO,
                 COB_VALOR, COB_PAGO, COB_TIPO_BOLETO, COB_BOLETO_CANCELADO, COB_PLACAS)
            VALUES (?, ?, CURDATE(), ?, ?, 'NÃO', ?, 'NÃO', ?)
        ");

        $gerados = 0;
        $pdo->beginTransaction();
        foreach ($contratos as $c) {
            $insert->execute([
                $c['PES_CODIGO_FK'],
                $c['VEI_CODIGO_FK'],
                $vencimento,
                $c['valor'],
                $c['tipo_boleto'],
                $c['VEI_PLACA'],
            ]);
            $gerados++;
        }
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "$gerados cobrança(s) gerada(s) com sucesso.",
            'gerados' => $gerados,
        ], JSON_UNESCAPED_UNICODE);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao gerar cobranças.', 'debug' => $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => true, 'message' => 'Ação inválida.']);
