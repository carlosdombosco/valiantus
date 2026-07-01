<?php
// inc/repositories/CorRepository.php
declare(strict_types=1);

/**
 * Retorna as cores como uma lista de objetos (COR_CODIGO_PK, COR_DESCRICAO).
 * Lança PDOException em erro de DB.
 */
function listarGrupos(PDO $pdo): array
{
    $sql = "SELECT * FROM tb_grupo";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}
