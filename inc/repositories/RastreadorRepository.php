<?php
// /valiantus/inc/repositories/RastreadorRepository.php
if (!defined('PATH_INC')) exit;

function rr_column_exists(PDO $pdo, string $table, string $column): bool
{
    $sql = "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";
    $st  = $pdo->prepare($sql);
    $st->execute([$table, $column]);
    return (bool)$st->fetchColumn();
}

/**
 * Lista rastreadores. Se sua tabela NÃO tiver RAS_VALOR_MENSALIDADE,
 * vou devolver 0.00 nessa coluna (para evitar erro).
 */
function listarRastreadores(PDO $pdo): array
{
    $hasMens = rr_column_exists($pdo, 'tb_rastreador', 'RAS_VALOR_MENSALIDADE');

    $sql = "SELECT 
                RAS_CODIGO_PK,
                RAS_CODIGO,
                RAS_MODELO,
                RAS_OPERADORA,
                RAS_VALOR_EQUIPAMENTO,
                RAS_VALOR_INSTALACAO" .
        ($hasMens ? ", RAS_VALOR_MENSALIDADE" : ", CAST(0.00 AS DECIMAL(10,2)) AS RAS_VALOR_MENSALIDADE") . "
            FROM tb_rastreador
            WHERE COALESCE(RAS_STATUS, 'ATIVO') <> 'INATIVO'
            ORDER BY RAS_CODIGO_PK DESC";

    $st = $pdo->query($sql);
    return $st->fetchAll(PDO::FETCH_OBJ);
}
