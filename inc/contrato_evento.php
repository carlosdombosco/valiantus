<?php
function registrar_evento_contrato(PDO $pdo, int $contratoId, ?int $veiculoId, string $tipo, ?int $usuarioId, ?string $motivo = null, ?string $obs = null): bool
{
    $sql = "INSERT INTO tb_contrato_evento
            (EV_CONTRATO_FK, EV_VEICULO_FK, EV_TIPO, EV_DATA, EV_USUARIO_FK, EV_MOTIVO, EV_OBSERVACAO)
            VALUES (:contrato, :veiculo, :tipo, NOW(), :usuario, :motivo, :obs)";
    $st = $pdo->prepare($sql);
    return $st->execute([
        ':contrato' => $contratoId,
        ':veiculo'  => $veiculoId,
        ':tipo'     => $tipo,           // 'CRIACAO' | 'CANCELAMENTO' | ...
        ':usuario'  => $usuarioId,
        ':motivo'   => $motivo,
        ':obs'      => $obs,
    ]);
}
