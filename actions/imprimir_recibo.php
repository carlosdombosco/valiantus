<?php
declare(strict_types=1);
require __DIR__ . '/../inc/config.php';
require PATH_INC . '/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

if (empty($_SESSION['SessUsuCodigo'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

try {
    $cfg = $pdo->query("SELECT * FROM tb_configuracoes WHERE CFG_CODIGO_PK = 1")->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { $cfg = []; }

function moedaPt(mixed $v): float {
    return (float) str_replace(['.', ','], ['', '.'], (string)($v ?? '0'));
}
function h(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function gerarVia(array $d, array $cfg, string $labelVia): string {
    $empresa = h($cfg['CFG_RAZAO_SOCIAL'] ?? 'Valiantus');
    $subParts = array_filter([
        !empty($cfg['CFG_CNPJ'])     ? 'CNPJ: ' . h($cfg['CFG_CNPJ']) : '',
        !empty($cfg['CFG_ENDERECO']) ? h(trim($cfg['CFG_ENDERECO'] . (($cfg['CFG_BAIRRO'] ?? '') ? ', ' . $cfg['CFG_BAIRRO'] : ''), ', ')) : '',
        !empty($cfg['CFG_CIDADE'])   ? h($cfg['CFG_CIDADE'] . (($cfg['CFG_UF'] ?? '') ? ' - ' . $cfg['CFG_UF'] : '')) : '',
        !empty($cfg['CFG_FONE'])     ? 'Tel: ' . h($cfg['CFG_FONE']) : '',
    ]);

    $ln = fn($lbl, $val) => "<div class='ln'><span class='lbl'>$lbl</span><span class='val'>$val</span></div>\n";

    $linhas  = $ln('Parcela #',        h($d['numParcela']));
    $linhas .= $ln('Nosso Nº',         h($d['nossoNumero']));
    $linhas .= $ln('Vencimento',       h($d['vencimento']));
    $linhas .= $ln('Associado',        h($d['nomeAssoc']));
    $linhas .= $ln('Placa',            h($d['placa']));
    $linhas .= "<div class='sep'></div>\n";
    $linhas .= $ln('Valor da Parcela', 'R$ ' . h($d['valorParcela']));
    if (moedaPt($d['desconto'] ?? 0) > 0) $linhas .= $ln('Desconto', '- R$ ' . h($d['desconto']));
    if (moedaPt($d['juros']    ?? 0) > 0) $linhas .= $ln('Juros',    '+ R$ ' . h($d['juros']));
    if (moedaPt($d['multa']    ?? 0) > 0) $linhas .= $ln('Multa',    '+ R$ ' . h($d['multa']));
    $linhas .= "<div class='sep'></div>\n";
    $linhas .= "<div class='total'><span>VALOR PAGO</span><span>R$&nbsp;" . h($d['valorPagar']) . "</span></div>\n";
    if (moedaPt($d['valorRecebido'] ?? 0) > moedaPt($d['valorPagar'] ?? 0)) {
        $linhas .= $ln('Recebido', 'R$ ' . h($d['valorRecebido']));
        $linhas .= $ln('Troco',    'R$ ' . h($d['troco']));
    }
    $linhas .= "<div class='sep'></div>\n";
    $linhas .= $ln('Data/Hora', h($d['dataHora']));
    $linhas .= $ln('Operador',  h($d['operador']));

    $sub = implode('<br>', $subParts);

    return "<div class='via'>
        <div class='empresa'>$empresa</div>"
        . ($sub ? "<div class='sub'>$sub</div>" : '')
        . "<div class='titulo'>RECIBO DE PAGAMENTO</div>
        $linhas
        <div class='assinatura'>_________________________<br>Assinatura do Associado</div>
        <div class='label-via'>$labelVia</div>
    </div>";
}

$via1  = gerarVia($data, $cfg, '1ª VIA — CLIENTE');
$corte = "<div class='corte'>✂ — — — — — RECORTAR — — — — — ✂</div>";
$via2  = gerarVia($data, $cfg, '2ª VIA — ESTABELECIMENTO');

$html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><title>Recibo</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Courier New",monospace;font-size:9pt;width:72mm;margin:0 auto;color:#000}
.via{width:100%;padding:2mm 0}
.empresa{text-align:center;font-weight:bold;font-size:10pt;margin-bottom:1mm}
.sub{text-align:center;font-size:7.5pt;margin-bottom:3mm;line-height:1.5}
.titulo{text-align:center;font-weight:bold;font-size:9pt;border-top:1px solid #000;border-bottom:1px solid #000;padding:1.5mm 0;margin:2mm 0;letter-spacing:1px}
.ln{display:flex;justify-content:space-between;margin-bottom:1.5mm;font-size:8.5pt}
.ln .lbl{color:#555}
.ln .val{font-weight:bold;text-align:right;max-width:60%;word-break:break-word}
.sep{border-top:1px dashed #ccc;margin:2mm 0}
.total{display:flex;justify-content:space-between;font-size:10pt;font-weight:bold;margin:2mm 0}
.assinatura{border-top:1px solid #000;margin-top:7mm;padding-top:1mm;text-align:center;font-size:7.5pt;color:#555}
.label-via{text-align:center;font-size:7pt;font-style:italic;margin-top:2mm;color:#555}
.corte{border-top:1px dashed #000;border-bottom:1px dashed #000;text-align:center;font-size:7.5pt;padding:1.5mm 0;margin:2mm 0}
@page{margin:2mm;size:80mm auto}
</style>
</head>
<body>
$via1
$corte
$via2
</body>
</html>
HTML;

/* ── Gera arquivos temporários ── */
$id       = uniqid('rec_');
$tmpDir   = rtrim(sys_get_temp_dir(), '\\/');
$htmlFile = $tmpDir . '\\' . $id . '.html';
$pdfFile  = $tmpDir . '\\' . $id . '.pdf';

file_put_contents($htmlFile, $html);

/* ── HTML → PDF via Edge headless ── */
$edgePaths = [
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
    'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
];
$edge = '';
foreach ($edgePaths as $p) {
    if (file_exists($p)) { $edge = $p; break; }
}

if (!$edge) {
    @unlink($htmlFile);
    echo json_encode(['success' => false, 'message' => 'Microsoft Edge não encontrado no servidor.']);
    exit;
}

$htmlUrl = 'file:///' . str_replace('\\', '/', $htmlFile);
exec("\"$edge\" --headless --disable-gpu --no-pdf-header-footer --print-to-pdf=\"$pdfFile\" \"$htmlUrl\" 2>&1", $out, $ret);
@unlink($htmlFile);

if (!file_exists($pdfFile)) {
    echo json_encode(['success' => false, 'message' => 'Falha ao gerar PDF: ' . implode(' ', $out)]);
    exit;
}

/* ── Impressão silenciosa via SumatraPDF ── */
$sumatra = PATH_ROOT . '\\SumatraPDF.exe';
if (!file_exists($sumatra)) {
    @unlink($pdfFile);
    echo json_encode(['success' => false, 'message' => 'SumatraPDF.exe não encontrado em ' . PATH_ROOT . '. Baixe em https://www.sumatrapdfreader.org/download-free-pdf-viewer']);
    exit;
}

exec("\"$sumatra\" -print-to-default -silent \"$pdfFile\" 2>&1", $out2, $ret2);
@unlink($pdfFile);

echo json_encode([
    'success' => $ret2 === 0,
    'message' => $ret2 === 0 ? 'Impresso com sucesso' : 'Falha na impressão: ' . implode(' ', $out2),
]);
