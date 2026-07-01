<?php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../inc/config.php';
require_once PATH_INC . '/db.php';

$fdb = 'C:\\CICLO\\CACHOEIRINHA.FDB';
$fb  = new PDO("firebird:dbname=localhost:{$fdb};charset=UTF8", 'SYSDBA', 'ciclo@2022', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

echo "=== MAR_TIPO distintos ===\n";
$rows = $fb->query("SELECT DISTINCT MAR_TIPO FROM TB_MARCA WHERE MAR_TIPO IS NOT NULL")->fetchAll();
foreach ($rows as $r) echo '  [' . trim($r['MAR_TIPO']) . "]\n";

echo "\n=== VEI_CAMBIO distintos ===\n";
$rows = $fb->query("SELECT DISTINCT VEI_CAMBIO FROM TB_VEICULO WHERE VEI_CAMBIO IS NOT NULL")->fetchAll();
foreach ($rows as $r) echo '  [' . trim($r['VEI_CAMBIO']) . "]\n";

echo "\n=== Colunas TB_VEICULO ===\n";
$cols = $fb->query("SELECT f.RDB\$FIELD_NAME FROM RDB\$RELATION_FIELDS f WHERE f.RDB\$RELATION_NAME = 'TB_VEICULO' ORDER BY f.RDB\$FIELD_POSITION")->fetchAll(PDO::FETCH_COLUMN);
foreach ($cols as $c) echo '  ' . trim($c) . "\n";

echo "\n=== Colunas TB_VEICULO_CONTRATO ===\n";
$cols = $fb->query("SELECT f.RDB\$FIELD_NAME FROM RDB\$RELATION_FIELDS f WHERE f.RDB\$RELATION_NAME = 'TB_VEICULO_CONTRATO' ORDER BY f.RDB\$FIELD_POSITION")->fetchAll(PDO::FETCH_COLUMN);
foreach ($cols as $c) echo '  ' . trim($c) . "\n";

echo "\n=== Colunas TB_CIDADES ===\n";
$cols = $fb->query("SELECT f.RDB\$FIELD_NAME FROM RDB\$RELATION_FIELDS f WHERE f.RDB\$RELATION_NAME = 'TB_CIDADES' ORDER BY f.RDB\$FIELD_POSITION")->fetchAll(PDO::FETCH_COLUMN);
foreach ($cols as $c) echo '  ' . trim($c) . "\n";

echo "\n=== Colunas TB_MARCA ===\n";
$cols = $fb->query("SELECT f.RDB\$FIELD_NAME FROM RDB\$RELATION_FIELDS f WHERE f.RDB\$RELATION_NAME = 'TB_MARCA' ORDER BY f.RDB\$FIELD_POSITION")->fetchAll(PDO::FETCH_COLUMN);
foreach ($cols as $c) echo '  ' . trim($c) . "\n";

echo "\n=== Colunas TB_MODELO ===\n";
$cols = $fb->query("SELECT f.RDB\$FIELD_NAME FROM RDB\$RELATION_FIELDS f WHERE f.RDB\$RELATION_NAME = 'TB_MODELO' ORDER BY f.RDB\$FIELD_POSITION")->fetchAll(PDO::FETCH_COLUMN);
foreach ($cols as $c) echo '  ' . trim($c) . "\n";

echo "\n=== Amostra TB_VEICULO (1 linha) ===\n";
$row = $fb->query("SELECT FIRST 1 * FROM TB_VEICULO")->fetch();
foreach ($row as $k => $v) echo "  $k = " . (is_resource($v) ? '[BLOB]' : substr((string)$v, 0, 80)) . "\n";

echo "\n=== Amostra TB_VEICULO_CONTRATO (1 linha) ===\n";
$row = $fb->query("SELECT FIRST 1 * FROM TB_VEICULO_CONTRATO")->fetch();
foreach ($row as $k => $v) echo "  $k = " . (is_resource($v) ? '[BLOB]' : substr((string)$v, 0, 80)) . "\n";
