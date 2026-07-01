<?php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../inc/config.php';
require_once PATH_INC . '/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
// Sem auth check — acesso local apenas
$fdb = 'C:\\CACHOEIRINHA.FDB';
$fb  = new PDO("firebird:dbname=localhost:{$fdb};charset=UTF8", 'SYSDBA', 'ciclo@2022', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$tables = ['TB_PESSOA','TB_VEICULO','TB_IMAGEM_VEICULO','TB_VEICULO_CONTRATO','TB_CONTAS','TB_VISTORIA'];

foreach ($tables as $t) {
    echo "\n=== $t ===\n";
    $cols = $fb->query("SELECT f.RDB\$FIELD_NAME FROM RDB\$RELATION_FIELDS f WHERE f.RDB\$RELATION_NAME = '$t' ORDER BY f.RDB\$FIELD_POSITION")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($cols as $c) echo "  " . trim($c) . "\n";
}

// Amostra de 1 linha de cada
foreach ($tables as $t) {
    echo "\n=== AMOSTRA $t ===\n";
    try {
        $row = $fb->query("SELECT FIRST 1 * FROM $t")->fetch();
        if ($row) foreach ($row as $k => $v) echo "  $k = " . (is_resource($v) ? '[BLOB]' : substr((string)$v, 0, 80)) . "\n";
    } catch (Throwable $e) { echo "  ERRO: " . $e->getMessage() . "\n"; }
}
