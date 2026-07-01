<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/../../inc/config.php';
require PATH_INC . '/db.php';
require PATH_INC . '/BoletoSicoob.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['SessUsuCodigo'])) {
    http_response_code(403);
    die('<p style="font-family:sans-serif;padding:40px;color:#c92a2a;">Acesso negado. Faça login primeiro.</p>');
}

$cobId = (int)($_GET['id'] ?? 0);
if ($cobId <= 0) {
    die('<p style="font-family:sans-serif;padding:40px;color:#c92a2a;">Cobrança não informada.</p>');
}

/* ── Dados da cobrança + pessoa + veículo ── */
try {
    $stmt = $pdo->prepare("
        SELECT
            c.COB_CODIGO_PK, c.COB_NOSSO_NUMERO, c.COB_DATA_VENCIMENTO,
            c.COB_DATA_CRIACAO, c.COB_VALOR, c.COB_DESCONTO, c.COB_ACRESCIMO,
            c.COB_JUROS, c.COB_MULTA, c.COB_OBSERVACAO, c.COB_TIPO_BOLETO,
            c.COB_PAGO, c.COB_BOLETO_CANCELADO, c.COB_PLACAS,
            p.PES_NOME, p.PES_CPF_CNPJ, p.PES_ENDERECO, p.PES_BAIRRO,
            p.PES_CIDADE, p.PES_UF, p.PES_CEP,
            v.VEI_PLACA, v.VEI_MARCA, v.VEI_MODELO, v.VEI_ANO_FABRICACAO
        FROM tb_cobranca c
        LEFT JOIN tb_pessoa   p ON p.PES_CODIGO_PK = c.PES_CODIGO_FK
        LEFT JOIN tb_veiculo  v ON v.VEI_CODIGO_PK = c.VEI_CODIGO_FK
        WHERE c.COB_CODIGO_PK = ?
        LIMIT 1
    ");
    $stmt->execute([$cobId]);
    $cob = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    die('<p style="font-family:sans-serif;padding:40px;color:#c92a2a;">Erro ao carregar cobrança: ' . htmlspecialchars($e->getMessage()) . '</p>');
}

if (!$cob) {
    die('<p style="font-family:sans-serif;padding:40px;color:#c92a2a;">Cobrança #' . $cobId . ' não encontrada.</p>');
}

/* ── Configurações (cedente / banco) ── */
try {
    $cfg = $pdo->query("SELECT * FROM tb_configuracoes WHERE CFG_CODIGO_PK = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $cfg = [];
}

/* ── Cálculos do boleto ── */
$nossoNum   = preg_replace('/\D/', '', $cob['COB_NOSSO_NUMERO'] ?? '');
$vencimento = substr($cob['COB_DATA_VENCIMENTO'] ?? '', 0, 10);
$valor      = (float)($cob['COB_VALOR'] ?? 0);

$codBarras  = BoletoSicoob::codigoBarras($cfg, $nossoNum, $valor, $vencimento);
$linhaDigit = BoletoSicoob::linhaDigitavel($codBarras);
$nnFmt      = BoletoSicoob::nossoNumeroFormatado($cfg, $nossoNum);
$barcodeSvg = BoletoSicoob::barcodeSvg($codBarras);

/* ── Helpers ── */
$fmtMoeda = fn($v) => number_format((float)$v, 2, ',', '.');
$fmtData  = function ($d): string {
    if (!$d) return '';
    $p = explode('-', substr((string)$d, 0, 10));
    return count($p) === 3 ? "$p[2]/$p[1]/$p[0]" : (string)$d;
};
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

/* ── Dados do cedente / sacado ── */
$banco        = preg_replace('/\D/', '', $cfg['CFG_CNAB_BANCO'] ?? '756');
$bancoDig     = '0';

// Agência: 4 dígitos / Cedente formatado (ex: 4293/202298-2)
$ag4          = preg_replace('/\D/', '', $cfg['CFG_CNAB_AGENCIA'] ?? '');
$cedRaw       = preg_replace('/\D/', '', $cfg['CFG_CNAB_CODIGO_CEDENTE'] ?? '');
$cedFmt       = strlen($cedRaw) > 1 ? substr($cedRaw, 0, -1) . '-' . substr($cedRaw, -1) : $cedRaw;
$agCedente    = $h($ag4 . '/' . $cedFmt);

$razao        = $cfg['CFG_RAZAO_SOCIAL'] ?? '';
$cnpjCfg      = $cfg['CFG_CNPJ'] ?? '';
$endCfg       = $cfg['CFG_CNAB_ENDERECO'] ?: ($cfg['CFG_ENDERECO'] ?? '');
$benefLabel   = trim(implode(' — ', array_filter([$razao, $cnpjCfg ? 'CNPJ: ' . $cnpjCfg : ''])));
$benefEnd     = $endCfg;

$sacado       = $h($cob['PES_NOME'] ?? '');
$sacadoCpf    = $h($cob['PES_CPF_CNPJ'] ?? '');
$sacadoEnd    = trim(implode(', ', array_filter([
    $cob['PES_ENDERECO'] ?? '', $cob['PES_BAIRRO'] ?? '',
    $cob['PES_CIDADE'] ?? '', $cob['PES_UF'] ?? '',
    $cob['PES_CEP'] ?? '',
])));

$veiculo      = trim(implode(' ', array_filter([
    $cob['VEI_PLACA'] ?? $cob['COB_PLACAS'] ?? '',
    $cob['VEI_MARCA'] ?? '', $cob['VEI_MODELO'] ?? '',
    $cob['VEI_ANO_FABRICACAO'] ?? '',
])));
$descricao    = $h($cob['COB_TIPO_BOLETO'] ?? 'Mensalidade');
$instrucoes   = $h(trim($cob['COB_OBSERVACAO'] ?? ''));
$localPgto    = 'PAGÁVEL EM QUALQUER AGÊNCIA ATÉ O VENCIMENTO';

// Logo: usa img/logo_sicoob.png se existir
$logoFile     = PATH_ROOT . '/img/logo_sicoob.png';
$logoUrl      = file_exists($logoFile) ? (BASE_URL . '/img/logo_sicoob.png') : null;

// Desconto / acréscimos
$desconto     = (float)($cob['COB_DESCONTO'] ?? 0);
$multa        = (float)($cob['COB_MULTA'] ?? 0);
$juros        = (float)($cob['COB_JUROS'] ?? 0);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Boleto <?= $h($cob['COB_CODIGO_PK']) ?></title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

@media print {
    @page { size: A4 portrait; margin: 6mm 8mm; }
    .no-print { display: none !important; }
    body { background: #fff !important; padding: 0 !important; font-size: 8.5px; }
    .boleto-wrap { box-shadow: none !important; }
}

body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 9.5px;
    background: #e8eaed;
    color: #000;
    padding: 16px;
}

/* ── Botões de ação ── */
.page-actions {
    display: flex; gap: 10px; margin-bottom: 16px; align-items: center;
}
.btn-print {
    padding: 8px 22px; background: #005c26; color: #fff; border: none;
    border-radius: 6px; font-size: 13px; font-weight: 700; cursor: pointer;
    display: inline-flex; align-items: center; gap: 6px;
}
.btn-close {
    padding: 8px 18px; background: #6c757d; color: #fff; border: none;
    border-radius: 6px; font-size: 13px; font-weight: 700; cursor: pointer;
}

/* ── Wrapper ── */
.boleto-wrap {
    background: #fff;
    max-width: 800px;
    margin: 0 auto;
    padding: 10px 12px;
    box-shadow: 0 2px 16px rgba(0,0,0,.15);
}

/* ── Header do canhão ── */
.bh {
    display: flex;
    align-items: stretch;
    border: 1.5px solid #000;
    border-bottom: none;
    min-height: 38px;
}
.bh-logo {
    display: flex; align-items: center; justify-content: center;
    padding: 4px 8px;
    border-right: 2px solid #000;
    min-width: 90px; max-width: 120px;
}
.bh-logo img { max-height: 32px; max-width: 110px; object-fit: contain; }
.bh-logo-text {
    font-size: 13px; font-weight: 900; color: #005c26; letter-spacing: -0.5px;
}
.bh-cod {
    display: flex; align-items: center;
    padding: 4px 10px;
    border-right: 2px solid #000;
    font-size: 15px; font-weight: 900;
    white-space: nowrap;
}
.bh-right {
    flex: 1; display: flex; align-items: center;
    padding: 4px 8px;
    font-size: 10px; font-weight: 700;
    justify-content: flex-end;
}
.bh-right-label {
    font-family: Arial, sans-serif;
    font-size: 11px; font-weight: 700;
    letter-spacing: 0;
}

/* ── Tabela do boleto ── */
.bt {
    width: 100%;
    border-collapse: collapse;
    border: 1.5px solid #000;
    border-top: none;
    font-size: 9px;
}
.bt td {
    border: 1px solid #000;
    padding: 1px 4px;
    vertical-align: top;
}
.lbl {
    display: block;
    font-size: 6.5px;
    color: #333;
    line-height: 1.3;
    margin-bottom: 1px;
}
.val {
    display: block;
    font-size: 9.5px;
    font-weight: 700;
    min-height: 13px;
    line-height: 1.3;
}
.val-mono {
    font-size: 10px;
}
.val-right {
    text-align: right;
}
.val-lg {
    font-size: 11px;
}
.cell-instr {
    font-size: 9px;
    line-height: 1.5;
}
.right-col {
    width: 148px;
    min-width: 148px;
}

/* ── Linha de corte ── */
.corte-wrap {
    display: flex; align-items: center; margin: 6px 0 4px;
    font-size: 8px; color: #555; gap: 6px;
}
.corte-wrap::before { content: '✂'; font-size: 11px; }
.corte-line { flex: 1; border-top: 1px dashed #999; }
.corte-linha-dig {
    font-size: 9.5px; font-weight: 700;
    color: #000;
    white-space: nowrap;
    padding: 0 6px;
}

/* ── Código de barras ── */
.barcode-cell {
    padding: 8px 4px 4px;
    border-top: 1px solid #000;
}
.barcode-cell svg { display: block; width: 100%; height: 52px; }
</style>
</head>
<body>

<div class="no-print page-actions">
    <button class="btn-print" onclick="window.print()">
        <svg width="15" height="15" fill="none" viewBox="0 0 24 24"><path stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v8H6z"/></svg>
        Imprimir
    </button>
    <button class="btn-close" onclick="window.close()">Fechar</button>
</div>

<div class="boleto-wrap">

<?php /* ═══════════════════════════════════════════
         CANHÃO — RECIBO DO PAGADOR
         ═══════════════════════════════════════════ */ ?>

<div class="bh">
    <div class="bh-logo">
        <?php if ($logoUrl): ?>
        <img src="<?= $h($logoUrl) ?>" alt="Sicoob">
        <?php else: ?>
        <span class="bh-logo-text">SICOOB</span>
        <?php endif; ?>
    </div>
    <div class="bh-cod"><?= $banco ?>-<?= $bancoDig ?></div>
    <div class="bh-right"><span class="bh-right-label">Recibo do Pagador</span></div>
</div>

<table class="bt">
    <colgroup>
        <col style="width:16%"><col style="width:17%"><col style="width:14%">
        <col style="width:6%"><col style="width:6%">
        <col class="right-col">
    </colgroup>
    <tr>
        <td colspan="5">
            <span class="lbl">Local de Pagamento</span>
            <span class="val"><?= $h($localPgto) ?></span>
        </td>
        <td>
            <span class="lbl">Vencimento</span>
            <span class="val val-right val-lg"><?= $fmtData($vencimento) ?></span>
        </td>
    </tr>
    <tr>
        <td colspan="5">
            <span class="lbl">Beneficiário</span>
            <span class="val"><?= $h($benefLabel) ?></span>
            <?php if ($benefEnd): ?><span style="font-size:8.5px;display:block;"><?= $h($benefEnd) ?></span><?php endif; ?>
        </td>
        <td>
            <span class="lbl">Agência / Código Beneficiário</span>
            <span class="val val-right"><?= $agCedente ?></span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="lbl">Data do Documento</span>
            <span class="val"><?= $fmtData($cob['COB_DATA_CRIACAO']) ?></span>
        </td>
        <td>
            <span class="lbl">Número do Documento</span>
            <span class="val"><?= $h($cob['COB_CODIGO_PK']) ?></span>
        </td>
        <td>
            <span class="lbl">Espécie Doc.</span>
            <span class="val">DM</span>
        </td>
        <td>
            <span class="lbl">Aceite</span>
            <span class="val">N</span>
        </td>
        <td>
            <span class="lbl">Data do Processamento</span>
            <span class="val"><?= $fmtData(date('Y-m-d')) ?></span>
        </td>
        <td>
            <span class="lbl">Nosso Número</span>
            <span class="val val-right val-mono"><?= $h($nnFmt) ?></span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="lbl">Uso do Banco</span>
            <span class="val"></span>
        </td>
        <td>
            <span class="lbl">Carteira</span>
            <span class="val">1</span>
        </td>
        <td colspan="2">
            <span class="lbl">Espécie</span>
            <span class="val">R$</span>
        </td>
        <td>
            <span class="lbl">Quantidade</span>
            <span class="val"></span>
        </td>
        <td>
            <span class="lbl">( = ) Valor do Documento</span>
            <span class="val val-right val-lg"><?= $fmtMoeda($valor) ?></span>
        </td>
    </tr>
    <tr>
        <td colspan="5" rowspan="5" style="vertical-align:top; padding:3px 4px;">
            <span class="lbl">Instruções (Texto de responsabilidade do beneficiário.)</span>
            <div class="cell-instr" style="min-height:56px; padding-top:2px;">
                <?php if ($instrucoes): ?>
                    <?= nl2br($instrucoes) ?>
                <?php else: ?>
                    NAO ACEITAR APOS O VENCIMENTO
                <?php endif; ?>
                <?php if ($veiculo): ?><br><span style="font-size:8.5px;">Placa(s): <?= $h($veiculo) ?></span><?php endif; ?>
            </div>
        </td>
        <td><span class="lbl">( - ) Desconto / Abatimento</span><span class="val val-right"><?= $desconto > 0 ? $fmtMoeda($desconto) : '' ?></span></td>
    </tr>
    <tr><td><span class="lbl">( - ) Outras Deduções</span><span class="val"></span></td></tr>
    <tr><td><span class="lbl">( + ) Mora / Multa / Juros</span><span class="val val-right"><?= $multa > 0 ? $fmtMoeda($multa) : '' ?></span></td></tr>
    <tr><td><span class="lbl">( + ) Outros Acréscimos</span><span class="val val-right"><?= $juros > 0 ? $fmtMoeda($juros) : '' ?></span></td></tr>
    <tr><td><span class="lbl">( = ) Valor Cobrado</span><span class="val"></span></td></tr>
    <tr>
        <td colspan="5">
            <span class="lbl">Pagador</span>
            <span class="val"><?= $sacado ?></span>
            <span style="font-size:8.5px;display:block;"><?= $h($sacadoEnd) ?></span>
        </td>
        <td>
            <span class="lbl">CPF / CNPJ</span>
            <span class="val val-right"><?= $sacadoCpf ?></span>
        </td>
    </tr>
    <tr>
        <td colspan="4">
            <span class="lbl">Sacador / Avalista</span>
            <span class="val"></span>
        </td>
        <td colspan="2" style="text-align:right;">
            <span class="lbl">Código de Baixa</span>
            <span class="val"></span>
            <span style="font-size:7px;display:block;margin-top:8px;">Autenticação Mecânica</span>
        </td>
    </tr>
</table>

<?php /* ═══════════════════════════════════════════
         LINHA DE CORTE (com linha digitável)
         ═══════════════════════════════════════════ */ ?>

<div class="corte-wrap">
    <div class="corte-line"></div>
    <span class="corte-linha-dig"><?= $banco ?>-<?= $bancoDig ?> &nbsp; <?= $h($linhaDigit) ?></span>
    <div class="corte-line"></div>
</div>

<?php /* ═══════════════════════════════════════════
         FICHA DE COMPENSAÇÃO
         ═══════════════════════════════════════════ */ ?>

<div class="bh">
    <div class="bh-logo">
        <?php if ($logoUrl): ?>
        <img src="<?= $h($logoUrl) ?>" alt="Sicoob">
        <?php else: ?>
        <span class="bh-logo-text">SICOOB</span>
        <?php endif; ?>
    </div>
    <div class="bh-cod"><?= $banco ?>-<?= $bancoDig ?></div>
    <div class="bh-right"><?= $h($linhaDigit) ?></div>
</div>

<table class="bt">
    <colgroup>
        <col style="width:16%"><col style="width:17%"><col style="width:14%">
        <col style="width:6%"><col style="width:6%">
        <col class="right-col">
    </colgroup>
    <tr>
        <td colspan="5">
            <span class="lbl">Local de Pagamento</span>
            <span class="val"><?= $h($localPgto) ?></span>
        </td>
        <td>
            <span class="lbl">Vencimento</span>
            <span class="val val-right val-lg"><?= $fmtData($vencimento) ?></span>
        </td>
    </tr>
    <tr>
        <td colspan="5">
            <span class="lbl">Beneficiário</span>
            <span class="val"><?= $h($benefLabel) ?></span>
            <?php if ($benefEnd): ?><span style="font-size:8.5px;display:block;"><?= $h($benefEnd) ?></span><?php endif; ?>
        </td>
        <td>
            <span class="lbl">Agência / Código Beneficiário</span>
            <span class="val val-right"><?= $agCedente ?></span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="lbl">Data do Documento</span>
            <span class="val"><?= $fmtData($cob['COB_DATA_CRIACAO']) ?></span>
        </td>
        <td>
            <span class="lbl">Número do Documento</span>
            <span class="val"><?= $h($cob['COB_CODIGO_PK']) ?></span>
        </td>
        <td>
            <span class="lbl">Espécie Doc.</span>
            <span class="val">DM</span>
        </td>
        <td>
            <span class="lbl">Aceite</span>
            <span class="val">N</span>
        </td>
        <td>
            <span class="lbl">Data do Processamento</span>
            <span class="val"><?= $fmtData(date('Y-m-d')) ?></span>
        </td>
        <td>
            <span class="lbl">Nosso Número</span>
            <span class="val val-right val-mono"><?= $h($nnFmt) ?></span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="lbl">Uso do Banco</span>
            <span class="val"></span>
        </td>
        <td>
            <span class="lbl">Carteira</span>
            <span class="val">1</span>
        </td>
        <td>
            <span class="lbl">Espécie Moeda</span>
            <span class="val">R$</span>
        </td>
        <td>
            <span class="lbl">Quantidade</span>
            <span class="val"></span>
        </td>
        <td>
            <span class="lbl">Valor</span>
            <span class="val"></span>
        </td>
        <td>
            <span class="lbl">( = ) Valor do Documento</span>
            <span class="val val-right val-lg"><?= $fmtMoeda($valor) ?></span>
        </td>
    </tr>
    <tr>
        <td colspan="5" rowspan="5" style="vertical-align:top; padding:3px 4px;">
            <span class="lbl">Instruções (Texto de responsabilidade do beneficiário.)</span>
            <div class="cell-instr" style="padding-top:2px;">
                <?php if ($instrucoes): ?>
                    <?= nl2br($instrucoes) ?>
                <?php else: ?>
                    NAO ACEITAR APOS O VENCIMENTO
                <?php endif; ?>
                <?php if ($veiculo): ?><br><span style="font-size:8.5px;">Placa(s): <?= $h($veiculo) ?></span><?php endif; ?>
            </div>
            <div style="margin-top:6px;">
                <span class="lbl">Pagador</span>
                <span class="val"><?= $sacado ?></span>
                <span style="font-size:8.5px;display:block;"><?= $h($sacadoEnd) ?></span>
            </div>
        </td>
        <td><span class="lbl">( - ) Desconto / Abatimento</span><span class="val val-right"><?= $desconto > 0 ? $fmtMoeda($desconto) : '' ?></span></td>
    </tr>
    <tr><td><span class="lbl">( - ) Outras Deduções</span><span class="val"></span></td></tr>
    <tr><td><span class="lbl">( + ) Mora / Multa / Juros</span><span class="val val-right"><?= $multa > 0 ? $fmtMoeda($multa) : '' ?></span></td></tr>
    <tr><td><span class="lbl">( + ) Outros Acréscimos</span><span class="val val-right"><?= $juros > 0 ? $fmtMoeda($juros) : '' ?></span></td></tr>
    <tr><td><span class="lbl">( = ) Valor Cobrado</span><span class="val"></span></td></tr>
    <tr>
        <td colspan="4">
            <span class="lbl">Sacador / Avalista</span>
            <span class="val"></span>
        </td>
        <td>
            <span class="lbl">CPF / CNPJ</span>
            <span class="val"><?= $sacadoCpf ?></span>
        </td>
        <td>
            <span class="lbl">Código de Baixa</span>
            <span class="val"></span>
            <span style="font-size:7px;display:block;margin-top:8px;text-align:right;">Autenticação - Ficha de Compensação</span>
        </td>
    </tr>
    <tr>
        <td colspan="6" style="padding:0;">
            <div class="barcode-cell">
                <?= $barcodeSvg ?>
            </div>
        </td>
    </tr>
</table>

</div><!-- /.boleto-wrap -->

<script>
if (new URLSearchParams(location.search).get('print') === '1') {
    window.addEventListener('load', function() { window.print(); });
}
</script>
</body>
</html>
