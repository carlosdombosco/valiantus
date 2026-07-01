<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/config.php';
require_once PATH_INC . '/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['SessUsuCodigo'])) {
    header('Location: ' . rtrim(BASE_URL, '/') . '/login.php');
    exit;
}

$sinId = (int)($_GET['id'] ?? 0);
if ($sinId <= 0) { http_response_code(400); echo '<p>ID de sinistro inválido.</p>'; exit; }

/* ── Carrega sinistro com joins ── */
$st = $pdo->prepare(
    "SELECT s.*,
            p.PES_NOME, p.PES_CPF_CNPJ, p.PES_RG, p.PES_FONE_CELULAR_1,
            v.VEI_PLACA, v.VEI_MARCA, v.VEI_MODELO, v.VEI_ANO_FABRICACAO,
            v.VEI_CHASSI
       FROM tb_sinistro s
       LEFT JOIN tb_pessoa  p ON p.PES_CODIGO_PK = s.PES_CODIGO_FK
       LEFT JOIN tb_veiculo v ON v.VEI_CODIGO_PK = s.VEI_CODIGO_FK
      WHERE s.SIN_CODIGO_PK = ? LIMIT 1"
);
$st->execute([$sinId]);
$s = $st->fetch(PDO::FETCH_ASSOC);
if (!$s) { http_response_code(404); echo '<p>Sinistro não encontrado.</p>'; exit; }

/* ── Carrega imagens ── */
$imgs = $pdo->prepare(
    "SELECT SIM_CODIGO_PK, SIM_TIPO, SIM_CAMINHO FROM tb_sinistro_imagem WHERE SIN_CODIGO_FK = ? ORDER BY SIM_TIPO, SIM_CODIGO_PK"
);
$imgs->execute([$sinId]);
$imagens = $imgs->fetchAll(PDO::FETCH_ASSOC);
$imgAntes  = array_filter($imagens, fn($i) => $i['SIM_TIPO'] === 'ANTES');
$imgDepois = array_filter($imagens, fn($i) => $i['SIM_TIPO'] === 'DEPOIS');

/* ── Carrega configurações da empresa ── */
$cfg = [];
try {
    $cfgSt = $pdo->query("SELECT * FROM tb_configuracoes WHERE CFG_CODIGO_PK = 1 LIMIT 1");
    $cfg   = $cfgSt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { /* tabela pode não existir ainda */ }

$empresa   = $cfg['CFG_RAZAO_SOCIAL']      ?? 'VALIANTUS ASSOCIAÇÃO VEICULAR';
$endereco  = $cfg['CFG_ENDERECO']          ?? '';
$bairro    = $cfg['CFG_BAIRRO']            ?? '';
$cidade    = $cfg['CFG_CIDADE']            ?? '';
$uf        = $cfg['CFG_UF']               ?? '';
$cep       = $cfg['CFG_CEP']              ?? '';
$fone      = $cfg['CFG_FONE']             ?? '';
$email     = $cfg['CFG_EMAIL']            ?? '';
$cnpj      = $cfg['CFG_CNPJ']             ?? '';
$logoPath  = $cfg['CFG_LOGO_PATH']        ?? '';
$assinPath = $cfg['CFG_ASSINATURA_PATH']  ?? '';
$nomeResp  = $cfg['CFG_NOME_RESPONSAVEL'] ?? '';
$cargoResp = $cfg['CFG_CARGO_RESPONSAVEL'] ?? '';

/* ── Helpers ── */
$h  = fn(?string $v) => htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
$dt = function(?string $v): string {
    if (!$v) return '—';
    try { return (new DateTime($v))->format('d/m/Y'); } catch (Exception $e) { return $v; }
};
$dtHr = function(?string $d, ?string $t): string {
    $ds = '';
    if ($d) try { $ds = (new DateTime($d))->format('d/m/Y'); } catch (Exception $e) { $ds = $d; }
    return trim(($ds ?: '') . ($t ? ' às ' . substr($t,0,5) : ''));
};
$money = fn(?string $v): string => $v && (float)$v ? 'R$ ' . number_format((float)$v, 2, ',', '.') : '—';
$yn    = fn(?string $v): string => $v === 'S' ? 'SIM' : ($v === 'N' ? 'NÃO' : '—');
$sexo  = fn(?string $v): string => match($v) { 'M' => 'Masculino', 'F' => 'Feminino', 'O' => 'Outro', default => '—' };
$local = array_filter([$s['SIN_BAIRRO_OCORRENCIA'], $s['SIN_CIDADE_OCORRENCIA'], $s['SIN_UF_OCORRENCIA']]);
$endCompleto = implode(' — ', array_filter([$endereco, $bairro, $cidade && $uf ? "$cidade/$uf" : ($cidade ?: $uf), $cep ? "CEP: $cep" : '']));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Sinistro #<?= $sinId ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #1a1d2e;
            background: #e9ecef;
            padding: 20px;
        }

        /* ── Wrapper ── */
        .imp-page {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 24px rgba(0,0,0,.12);
            overflow: hidden;
        }

        /* ── Print button bar ── */
        .imp-toolbar {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 12px 20px;
            background: #f8f9fb;
            border-bottom: 1px solid #e9ecef;
        }
        .imp-btn {
            height: 38px;
            padding: 0 20px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            text-decoration: none;
        }
        .imp-btn-print  { background: #3b5bdb; color: #fff; }
        .imp-btn-close  { background: #e9ecef; color: #495057; }
        .imp-btn:hover  { opacity: .88; }

        /* ── Documento ── */
        .imp-doc {
            padding: 28px 32px;
        }

        /* ── Cabeçalho ── */
        .imp-header {
            display: flex;
            align-items: center;
            gap: 20px;
            border-bottom: 3px solid #c92a2a;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        .imp-logo img {
            max-height: 70px;
            max-width: 140px;
            object-fit: contain;
        }
        .imp-logo-placeholder {
            width: 100px;
            height: 60px;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #adb5bd;
            font-size: 10px;
        }
        .imp-header-info { flex: 1; }
        .imp-empresa-nome {
            font-size: 14px;
            font-weight: 800;
            color: #c92a2a;
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: 3px;
        }
        .imp-empresa-end  { font-size: 10px; color: #495057; line-height: 1.5; }
        .imp-header-badge {
            text-align: right;
            flex-shrink: 0;
        }
        .imp-doc-title {
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #c92a2a;
        }
        .imp-doc-num {
            font-size: 22px;
            font-weight: 900;
            color: #1a1d2e;
            font-family: monospace;
        }
        .imp-doc-date { font-size: 10px; color: #868e96; margin-top: 2px; }

        /* ── Seções ── */
        .imp-section {
            margin-bottom: 14px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
        }
        .imp-section-head {
            background: #1a1d2e;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            padding: 6px 12px;
        }
        .imp-section-head.red   { background: #c92a2a; }
        .imp-section-head.blue  { background: #3b5bdb; }
        .imp-section-head.green { background: #2f9e44; }
        .imp-section-head.gray  { background: #495057; }

        .imp-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 0;
        }
        .imp-field {
            padding: 7px 12px;
            border-right: 1px solid #e9ecef;
            border-bottom: 1px solid #e9ecef;
        }
        .imp-field:last-child, .imp-field.no-border-r { border-right: none; }
        .imp-field-label {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #868e96;
            margin-bottom: 2px;
        }
        .imp-field-value {
            font-size: 12px;
            font-weight: 600;
            color: #1a1d2e;
            word-break: break-word;
        }
        .imp-field-value.big {
            font-size: 14px;
            font-weight: 800;
            font-family: monospace;
            color: #c92a2a;
        }
        .imp-field-value.mono { font-family: monospace; }

        /* span helpers */
        .c1  { grid-column: span 1; }
        .c2  { grid-column: span 2; }
        .c3  { grid-column: span 3; }
        .c4  { grid-column: span 4; }
        .c5  { grid-column: span 5; }
        .c6  { grid-column: span 6; }
        .c7  { grid-column: span 7; }
        .c8  { grid-column: span 8; }
        .c9  { grid-column: span 9; }
        .c10 { grid-column: span 10; }
        .c12 { grid-column: span 12; }

        /* ── Texto livre ── */
        .imp-text-box {
            padding: 10px 12px;
            min-height: 60px;
            font-size: 11.5px;
            color: #1a1d2e;
            line-height: 1.7;
            white-space: pre-wrap;
        }

        /* ── Badges inline ── */
        .imp-badge {
            display: inline-block;
            padding: 2px 9px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
        }
        .imp-badge-sim { background: #d3f9d8; color: #2f9e44; }
        .imp-badge-nao { background: #e9ecef; color: #495057; }
        .imp-badge-aberto    { background: #fff3cd; color: #856404; }
        .imp-badge-encerrado { background: #d3f9d8; color: #2f9e44; }
        .imp-badge-cancelado { background: #ffe3e3; color: #c92a2a; }

        /* ── Imagens ── */
        .imp-img-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 12px;
        }
        .imp-img-item {
            width: calc(25% - 6px);
            aspect-ratio: 4/3;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #e9ecef;
        }
        .imp-img-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* ── Assinaturas ── */
        .imp-assinaturas {
            display: flex;
            gap: 40px;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 2px solid #e9ecef;
        }
        .imp-assin-bloco {
            flex: 1;
            text-align: center;
        }
        .imp-assin-img {
            max-height: 60px;
            max-width: 200px;
            object-fit: contain;
            margin-bottom: 4px;
        }
        .imp-assin-line {
            border-top: 1.5px solid #1a1d2e;
            margin: 0 10px 6px;
        }
        .imp-assin-nome { font-size: 10px; font-weight: 700; }
        .imp-assin-cargo { font-size: 9px; color: #868e96; }

        /* ── Rodapé ── */
        .imp-footer {
            text-align: center;
            font-size: 9px;
            color: #adb5bd;
            padding: 10px 0 0;
            margin-top: 10px;
            border-top: 1px solid #f0f0f0;
        }

        /* ── Print ── */
        @media print {
            html, body { background: #fff !important; padding: 0 !important; }
            .imp-page    { box-shadow: none; border-radius: 0; max-width: 100%; }
            .imp-toolbar { display: none !important; }
            .imp-doc     { padding: 6px 10px; }

            .imp-header         { padding-bottom: 8px;  margin-bottom: 10px; }
            .imp-logo img       { max-height: 52px; }
            .imp-doc-num        { font-size: 18px; }
            .imp-section        { margin-bottom: 6px; }
            .imp-section-head   { padding: 4px 10px; }
            .imp-field          { padding: 3px 8px; }
            .imp-field-value    { font-size: 11px; }
            .imp-field-value.big{ font-size: 13px; }
            .imp-text-box       { min-height: 20px; padding: 5px 10px; line-height: 1.4; font-size: 11px; }
            .imp-assinaturas    { margin-top: 10px; padding-top: 8px; page-break-inside: avoid; }
            .imp-footer         { margin-top: 4px; padding: 5px 0 0; page-break-inside: avoid; }
            .imp-tipo-banner    { padding: 5px 12px !important; margin-bottom: 8px !important; }
            .imp-tipo-banner span[style*="font-size:16px"] { font-size: 14px !important; }
        }

        @page {
            size: A4;
            margin: 10mm 10mm;
        }
    </style>
</head>
<body>

<div class="imp-page">

    <!-- Barra de ferramentas (oculta ao imprimir) -->
    <div class="imp-toolbar">
        <button class="imp-btn imp-btn-close" onclick="window.close()">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            Fechar
        </button>
        <button class="imp-btn imp-btn-print" onclick="window.print()">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Imprimir
        </button>
    </div>

    <!-- Documento -->
    <div class="imp-doc">

        <!-- CABEÇALHO -->
        <div class="imp-header">
            <div class="imp-logo">
                <?php if ($logoPath): ?>
                    <img src="<?= $h($logoPath) ?>" alt="Logo">
                <?php else: ?>
                    <div class="imp-logo-placeholder">SEM LOGO</div>
                <?php endif; ?>
            </div>

            <div class="imp-header-info">
                <div class="imp-empresa-nome"><?= $h($empresa) ?></div>
                <?php if ($endCompleto): ?>
                    <div class="imp-empresa-end"><?= $h($endCompleto) ?></div>
                <?php endif; ?>
                <?php if ($fone || $email): ?>
                    <div class="imp-empresa-end">
                        <?= $fone ? 'Tel: ' . $h($fone) : '' ?>
                        <?= ($fone && $email) ? ' &nbsp;|&nbsp; ' : '' ?>
                        <?= $email ? $h($email) : '' ?>
                    </div>
                <?php endif; ?>
                <?php if ($cnpj): ?>
                    <div class="imp-empresa-end">CNPJ: <?= $h($cnpj) ?></div>
                <?php endif; ?>
            </div>

            <div class="imp-header-badge">
                <div class="imp-doc-title">Boletim de Sinistro</div>
                <div class="imp-doc-num">#<?= $sinId ?></div>
                <div class="imp-doc-date">Emitido em <?= date('d/m/Y H:i') ?></div>
                <?php
                    $statusCls = ['ABERTO' => 'imp-badge-aberto', 'ENCERRADO' => 'imp-badge-encerrado', 'CANCELADO' => 'imp-badge-cancelado'][$s['SIN_STATUS']] ?? 'imp-badge-aberto';
                ?>
                <div style="margin-top:5px;">
                    <span class="imp-badge <?= $statusCls ?>"><?= $h($s['SIN_STATUS']) ?></span>
                </div>
            </div>
        </div>

        <!-- TIPO DE OCORRÊNCIA destaque -->
        <div class="imp-tipo-banner" style="background:#fff5f5;border:2px solid #f03e3e;border-radius:8px;padding:8px 16px;margin-bottom:14px;display:flex;align-items:center;gap:12px;">
            <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#c92a2a;">Tipo de Ocorrência</span>
            <span style="font-size:16px;font-weight:800;color:#1a1d2e;"><?= $h($s['SIN_TIPO_OCORRENCIA'] ?: '—') ?></span>
            <?php if ($s['SIN_DATA_OCORRENCIA'] || $s['SIN_HORA_OCORRENCIA']): ?>
                <span style="margin-left:auto;font-size:11px;color:#495057;">
                    <strong>Data/Hora:</strong>
                    <?= $h($dtHr($s['SIN_DATA_OCORRENCIA'], $s['SIN_HORA_OCORRENCIA'])) ?>
                </span>
            <?php endif; ?>
        </div>

        <!-- DADOS DO ASSOCIADO / CONDUTOR -->
        <div class="imp-section">
            <div class="imp-section-head blue">Dados do Associado e Condutor</div>
            <div class="imp-grid">
                <div class="imp-field c6">
                    <div class="imp-field-label">Associado</div>
                    <div class="imp-field-value"><?= $h($s['PES_NOME'] ?: '—') ?></div>
                </div>
                <div class="imp-field c3">
                    <div class="imp-field-label">CPF / CNPJ</div>
                    <div class="imp-field-value mono"><?= $h($s['PES_CPF_CNPJ'] ?: '—') ?></div>
                </div>
                <div class="imp-field c3 no-border-r">
                    <div class="imp-field-label">RG</div>
                    <div class="imp-field-value mono"><?= $h($s['PES_RG'] ?: '—') ?></div>
                </div>

                <div class="imp-field c5">
                    <div class="imp-field-label">Nome do Condutor</div>
                    <div class="imp-field-value"><?= $h($s['SIN_NOME_CONDUTOR'] ?: '—') ?></div>
                </div>
                <div class="imp-field c2">
                    <div class="imp-field-label">Nasc.</div>
                    <div class="imp-field-value"><?= $h($dt($s['SIN_DATA_NASC_CONDUTOR'])) ?></div>
                </div>
                <div class="imp-field c2">
                    <div class="imp-field-label">Sexo</div>
                    <div class="imp-field-value"><?= $h($sexo($s['SIN_SEXO_CONDUTOR'])) ?></div>
                </div>
                <div class="imp-field c2">
                    <div class="imp-field-label">CNH</div>
                    <div class="imp-field-value mono"><?= $h($s['SIN_CNH_CONDUTOR'] ?: '—') ?></div>
                </div>
                <div class="imp-field c1 no-border-r">
                    <div class="imp-field-label">Val. CNH</div>
                    <div class="imp-field-value"><?= $h($dt($s['SIN_VALIDADE_CNH'])) ?></div>
                </div>
            </div>
        </div>

        <!-- DADOS DO VEÍCULO -->
        <div class="imp-section">
            <div class="imp-section-head">Dados do Veículo</div>
            <div class="imp-grid">
                <div class="imp-field c3">
                    <div class="imp-field-label">Placa</div>
                    <div class="imp-field-value big"><?= $h($s['VEI_PLACA'] ?: '—') ?></div>
                </div>
                <div class="imp-field c6">
                    <div class="imp-field-label">Marca / Modelo</div>
                    <div class="imp-field-value"><?= $h(trim(($s['VEI_MARCA'] ?? '') . ' ' . ($s['VEI_MODELO'] ?? '')) ?: '—') ?></div>
                </div>
                <div class="imp-field c3 no-border-r">
                    <div class="imp-field-label">Ano Fabricação</div>
                    <div class="imp-field-value"><?= $h($s['VEI_ANO_FABRICACAO'] ?: '—') ?></div>
                </div>
                <div class="imp-field c6">
                    <div class="imp-field-label">Chassi</div>
                    <div class="imp-field-value mono"><?= $h($s['VEI_CHASSI'] ?: '—') ?></div>
                </div>
                <div class="imp-field c3">
                    <div class="imp-field-label">Valor FIPE</div>
                    <div class="imp-field-value"><?= $h($money($s['SIN_VALOR_FIPE'])) ?></div>
                </div>
                <div class="imp-field c3 no-border-r">
                    <div class="imp-field-label">Nº Sinistros Ant.</div>
                    <div class="imp-field-value"><?= (int)$s['SIN_NUM_SINISTROS_ANT'] ?></div>
                </div>
            </div>
        </div>

        <!-- BOLETIM DE OCORRÊNCIA -->
        <?php if ($s['SIN_NUM_BO'] || $s['SIN_ORGAO_COMPETENCIA'] || $s['SIN_DATA_BO']): ?>
        <div class="imp-section">
            <div class="imp-section-head gray">Boletim de Ocorrência</div>
            <div class="imp-grid">
                <div class="imp-field c4">
                    <div class="imp-field-label">Nº B.O.</div>
                    <div class="imp-field-value mono"><?= $h($s['SIN_NUM_BO'] ?: '—') ?></div>
                </div>
                <div class="imp-field c4">
                    <div class="imp-field-label">Data / Hora B.O.</div>
                    <div class="imp-field-value"><?= $h($dtHr($s['SIN_DATA_BO'], $s['SIN_HORA_BO']) ?: '—') ?></div>
                </div>
                <div class="imp-field c4 no-border-r">
                    <div class="imp-field-label">Órgão / Autoridade</div>
                    <div class="imp-field-value"><?= $h($s['SIN_ORGAO_COMPETENCIA'] ?: '—') ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- LOCAL DA OCORRÊNCIA -->
        <div class="imp-section">
            <div class="imp-section-head gray">Local da Ocorrência</div>
            <div class="imp-grid">
                <div class="imp-field c3">
                    <div class="imp-field-label">UF</div>
                    <div class="imp-field-value"><?= $h($s['SIN_UF_OCORRENCIA'] ?: '—') ?></div>
                </div>
                <div class="imp-field c4">
                    <div class="imp-field-label">Cidade</div>
                    <div class="imp-field-value"><?= $h($s['SIN_CIDADE_OCORRENCIA'] ?: '—') ?></div>
                </div>
                <div class="imp-field c3">
                    <div class="imp-field-label">Bairro</div>
                    <div class="imp-field-value"><?= $h($s['SIN_BAIRRO_OCORRENCIA'] ?: '—') ?></div>
                </div>
                <div class="imp-field c2 no-border-r">
                    <div class="imp-field-label">Infomações em</div>
                    <div class="imp-field-value"><?= $h($dt($s['SIN_DATA_LANCAMENTO'])) ?></div>
                </div>
                <?php if ($s['SIN_PONTO_REFERENCIA']): ?>
                <div class="imp-field c12 no-border-r" style="border-bottom:none;">
                    <div class="imp-field-label">Ponto de Referência</div>
                    <div class="imp-field-value"><?= $h($s['SIN_PONTO_REFERENCIA']) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- DETALHES DO SINISTRO -->
        <?php if ($s['SIN_DETALHE']): ?>
        <div class="imp-section">
            <div class="imp-section-head red">Detalhes do Sinistro</div>
            <div class="imp-text-box"><?= $h($s['SIN_DETALHE']) ?></div>
        </div>
        <?php endif; ?>

        <!-- DANOS AO VEÍCULO -->
        <?php if ($s['SIN_DANOS_VEICULO']): ?>
        <div class="imp-section">
            <div class="imp-section-head">Danos ao Veículo</div>
            <div class="imp-text-box"><?= $h($s['SIN_DANOS_VEICULO']) ?></div>
        </div>
        <?php endif; ?>

        <!-- DADOS FINANCEIROS + REBOQUE/VÍTIMAS -->
        <div class="imp-section">
            <div class="imp-section-head gray">Informações Adicionais</div>
            <div class="imp-grid">
                <div class="imp-field c3">
                    <div class="imp-field-label">Necessitou Reboque</div>
                    <div class="imp-field-value">
                        <span class="imp-badge <?= $s['SIN_PRECISA_REBOQUE'] === 'S' ? 'imp-badge-sim' : 'imp-badge-nao' ?>">
                            <?= $yn($s['SIN_PRECISA_REBOQUE']) ?>
                        </span>
                    </div>
                </div>
                <div class="imp-field c3">
                    <div class="imp-field-label">Houve Vítimas</div>
                    <div class="imp-field-value">
                        <span class="imp-badge <?= $s['SIN_HOUVE_VITIMAS'] === 'S' ? 'imp-badge-sim' : 'imp-badge-nao' ?>">
                            <?= $yn($s['SIN_HOUVE_VITIMAS']) ?>
                        </span>
                    </div>
                </div>
                <div class="imp-field c3">
                    <div class="imp-field-label">Franquia</div>
                    <div class="imp-field-value">
                        <?= $s['SIN_FRANQUIA_PERC'] ? $h($s['SIN_FRANQUIA_PERC']) . '%' : '—' ?>
                        <?= $s['SIN_VALOR_FRANQUIA'] && (float)$s['SIN_VALOR_FRANQUIA'] ? ' — ' . $h($money($s['SIN_VALOR_FRANQUIA'])) : '' ?>
                    </div>
                </div>
                <div class="imp-field c3 no-border-r">
                    <div class="imp-field-label">Lançamento</div>
                    <div class="imp-field-value"><?= $h($dt($s['SIN_DATA_LANCAMENTO'])) ?></div>
                </div>
            </div>
        </div>

        <!-- IMAGENS DO SINISTRO -->
        <?php if (!empty($imgAntes)): ?>
        <div class="imp-section">
            <div class="imp-section-head">Fotos do Sinistro (antes/durante)</div>
            <div class="imp-img-grid">
                <?php foreach ($imgAntes as $img): ?>
                    <div class="imp-img-item">
                        <img src="<?= $h($img['SIM_CAMINHO']) ?>" alt="Sinistro">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- IMAGENS PÓS SINISTRO -->
        <?php if (!empty($imgDepois)): ?>
        <div class="imp-section">
            <div class="imp-section-head green">Fotos Pós Sinistro (reparo)</div>
            <div class="imp-img-grid">
                <?php foreach ($imgDepois as $img): ?>
                    <div class="imp-img-item">
                        <img src="<?= $h($img['SIM_CAMINHO']) ?>" alt="Pós sinistro">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ASSINATURAS -->
        <div class="imp-assinaturas">
            <div class="imp-assin-bloco">
                <?php if ($assinPath): ?>
                    <img class="imp-assin-img" src="<?= $h($assinPath) ?>" alt="Assinatura">
                <?php else: ?>
                    <div style="height:50px;"></div>
                <?php endif; ?>
                <div class="imp-assin-line"></div>
                <div class="imp-assin-nome"><?= $h($nomeResp ?: $empresa) ?></div>
                <div class="imp-assin-cargo"><?= $h($cargoResp ?: 'Assinatura com registro em cartório') ?></div>
            </div>

            <div class="imp-assin-bloco">
                <div style="height:50px;"></div>
                <div class="imp-assin-line"></div>
                <div class="imp-assin-nome"><?= $h($s['PES_NOME'] ?: 'Associado') ?></div>
                <div class="imp-assin-cargo">Associado / Responsável</div>
            </div>
        </div>

        <!-- Rodapé do documento -->
        <div class="imp-footer">
            Documento gerado em <?= date('d/m/Y \à\s H:i:s') ?> &nbsp;·&nbsp;
            <?= $h($empresa) ?>
            <?php if ($cnpj): ?>&nbsp;·&nbsp; CNPJ <?= $h($cnpj) ?><?php endif; ?>
        </div>

    </div><!-- /imp-doc -->
</div><!-- /imp-page -->

</body>
</html>
