<?php
declare(strict_types=1);

/**
 * log_evento — registra um evento no log geral do sistema (tb_log_evento).
 *
 * A tabela é criada automaticamente na primeira chamada (idempotente).
 * A função nunca lança exceção — erros vão só para o error_log.
 *
 * @param PDO    $pdo        Conexão PDO já aberta.
 * @param string $tabela     Tabela afetada: 'tb_contrato', 'tb_veiculo', 'tb_pessoa', ...
 * @param int    $registroId PK do registro afetado.
 * @param string $tipo       Tipo do evento: CRIACAO | EDICAO | CANCELAMENTO | REATIVACAO | TRANSFERENCIA | ...
 * @param array  $opts {
 *   data_evento  => string (Y-m-d)    data informada pelo operador
 *   usuario_id   => int               ID do usuário logado
 *   usuario_nome => string            Nome do usuário logado
 *   motivo       => string            Motivo livre
 *   obs          => string|array      Dados extras (array → serializado como JSON)
 * }
 */
function log_evento(PDO $pdo, string $tabela, int $registroId, string $tipo, array $opts = []): void
{
    static $ready = false;
    try {
        if (!$ready) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `tb_log_evento` (
                `LOG_CODIGO_PK`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `LOG_TABELA`       VARCHAR(60)  NOT NULL                          COMMENT 'Tabela afetada (tb_contrato, tb_veiculo, ...)',
                `LOG_REGISTRO_FK`  INT UNSIGNED NOT NULL                          COMMENT 'PK do registro na tabela afetada',
                `LOG_TIPO`         VARCHAR(50)  NOT NULL                          COMMENT 'CRIACAO | EDICAO | CANCELAMENTO | REATIVACAO | TRANSFERENCIA | ...',
                `LOG_DATA`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp automático do sistema',
                `LOG_DATA_EVENTO`  DATE         NULL     DEFAULT NULL             COMMENT 'Data informada pelo operador (pode diferir de LOG_DATA)',
                `LOG_USUARIO_ID`   INT          NULL     DEFAULT NULL,
                `LOG_USUARIO_NOME` VARCHAR(150) NULL     DEFAULT NULL,
                `LOG_MOTIVO`       TEXT         NULL,
                `LOG_OBS`          TEXT         NULL                              COMMENT 'JSON com dados adicionais livres',
                PRIMARY KEY (`LOG_CODIGO_PK`),
                KEY `idx_log_entidade` (`LOG_TABELA`, `LOG_REGISTRO_FK`),
                KEY `idx_log_tipo`     (`LOG_TIPO`),
                KEY `idx_log_data`     (`LOG_DATA`),
                KEY `idx_log_usuario`  (`LOG_USUARIO_ID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Log geral de eventos do sistema'");
            $ready = true;
        }

        $obs = $opts['obs'] ?? null;
        if (is_array($obs)) {
            $obs = json_encode($obs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $pdo->prepare("INSERT INTO `tb_log_evento`
            (LOG_TABELA, LOG_REGISTRO_FK, LOG_TIPO, LOG_DATA_EVENTO,
             LOG_USUARIO_ID, LOG_USUARIO_NOME, LOG_MOTIVO, LOG_OBS)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([
                $tabela,
                $registroId,
                strtoupper($tipo),
                $opts['data_evento']  ?? null,
                $opts['usuario_id']   ?? null,
                $opts['usuario_nome'] ?? null,
                $opts['motivo']       ?? null,
                $obs,
            ]);
    } catch (Throwable $e) {
        // Nunca propaga — log nunca deve quebrar o fluxo principal
        error_log('[log_evento] ' . $e->getMessage());
    }
}
