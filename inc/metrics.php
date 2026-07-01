<?php
// app/includes/metrics.php

if (!function_exists('total_contratos_ativos')) {
    /**
     * Retorna o total de contratos com CTR_STATUS = 'A'
     */
    function total_contratos_ativos(PDO $pdo): int
    {
        try {
            $sql = "SELECT COUNT(*) AS total FROM tb_contrato WHERE CTR_STATUS = 'A'";
            $st  = $pdo->query($sql);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return (int)($row['total'] ?? 0);
        } catch (Throwable $e) {
            // Log se quiser: error_log("metrics.total_contratos_ativos: " . $e->getMessage());
            return 0;
        }
    }
}


// Já existe a total_contratos_ativos($pdo). Vamos adicionar:

if (!function_exists('total_cancelamentos_mes_atual')) {
    function total_cancelamentos_mes_atual(PDO $pdo): int
    {
        try {
            // Detecta driver para montar SQL compatível (MySQL vs SQL Server)
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

            if ($driver === 'sqlsrv' || $driver === 'dblib' || $driver === 'mssql') {
                $sql = "
                    SELECT COUNT(*) AS total
                    FROM tb_contrato_evento
                    WHERE EV_TIPO = 'CANCELAMENTO'
                      AND EV_DATA >= DATEFROMPARTS(YEAR(GETDATE()), MONTH(GETDATE()), 1)
                      AND EV_DATA <  DATEADD(MONTH, 1, DATEFROMPARTS(YEAR(GETDATE()), MONTH(GETDATE()), 1))
                ";
            } else {
                // MySQL / MariaDB
                $sql = "
                    SELECT COUNT(*) AS total
                    FROM tb_contrato_evento
                    WHERE EV_TIPO = 'CANCELAMENTO'
                      AND EV_DATA >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                      AND EV_DATA <  DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
                ";
            }

            $st  = $pdo->query($sql);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return (int)($row['total'] ?? 0);
        } catch (Throwable $e) {
            // error_log("metrics.total_cancelamentos_mes_atual: " . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('dash_stats')) {
    function dash_stats(PDO $pdo): array
    {
        $defaults = [
            'veic_ativos'          => 0,
            'veic_inativos'        => 0,
            'total_veiculos'       => 0,
            'assoc_ativos'         => 0,
            'assoc_inativos'       => 0,
            'total_assoc'          => 0,
            'aniversariantes_hoje' => 0,
        ];
        try {
            $row = $pdo->query("
                SELECT
                    COUNT(DISTINCT p.PES_CODIGO_PK)                                                                AS total_assoc,
                    COUNT(DISTINCT CASE WHEN c.CTR_STATUS = 'A' THEN p.PES_CODIGO_PK END)                         AS assoc_ativos,
                    COUNT(DISTINCT CASE WHEN c.CTR_STATUS != 'A' OR c.CTR_STATUS IS NULL THEN p.PES_CODIGO_PK END) AS assoc_inativos,
                    COUNT(DISTINCT v.VEI_CODIGO_PK)                                                                AS total_veiculos,
                    COUNT(DISTINCT CASE WHEN c.CTR_STATUS = 'A' THEN v.VEI_CODIGO_PK END)                         AS veic_ativos,
                    COUNT(DISTINCT CASE WHEN c.CTR_STATUS != 'A' THEN v.VEI_CODIGO_PK END)                        AS veic_inativos
                FROM tb_pessoa p
                LEFT JOIN tb_veiculo  v ON v.PES_CODIGO_FK = p.PES_CODIGO_PK
                LEFT JOIN tb_contrato c ON c.VEI_CODIGO_FK = v.VEI_CODIGO_PK
            ")->fetch(PDO::FETCH_ASSOC);
            if ($row) $defaults = array_merge($defaults, array_map('intval', $row));
        } catch (Throwable $e) {}

        try {
            $aniv = $pdo->query("
                SELECT COUNT(*) AS total FROM tb_pessoa
                WHERE DAY(PES_DATA_NASCIMENTO)   = DAY(CURDATE())
                  AND MONTH(PES_DATA_NASCIMENTO) = MONTH(CURDATE())
            ")->fetch(PDO::FETCH_ASSOC);
            $defaults['aniversariantes_hoje'] = (int)($aniv['total'] ?? 0);
        } catch (Throwable $e) {}

        return $defaults;
    }
}
