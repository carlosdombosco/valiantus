<?php
// inc/repositories/CorRepository.php
declare(strict_types=1);

/**
 * Retorna as cores como uma lista de objetos (COR_CODIGO_PK, COR_DESCRICAO).
 * Lança PDOException em erro de DB.
 */
function listarCores(PDO $pdo): array
{
    $sql = "SELECT COR_CODIGO_PK, COR_DESCRICAO
            FROM tb_cor
            ORDER BY COR_DESCRICAO";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}
