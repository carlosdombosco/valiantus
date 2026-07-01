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
