<?php

declare(strict_types=1);

function listarCombos(PDO $pdo): array
{
    $sql = "SELECT * FROM tb_combo";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}
