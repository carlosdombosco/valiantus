<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/config.php';
require_once PATH_INC . '/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['SessUsuCodigo'])) {
    header('Location: ' . rtrim(BASE_URL, '/') . '/login.php');
    exit;
}

/* ── Configurações da empresa ── */
$cfg = [];
try {
    $cfgSt = $pdo->query("SELECT * FROM tb_configuracoes WHERE CFG_CODIGO_PK = 1 LIMIT 1");
    $cfg   = $cfgSt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {}

$empresa   = $cfg['CFG_RAZAO_SOCIAL']     ?? 'VALIANTUS ASSOCIAÇÃO VEICULAR';
$endereco  = $cfg['CFG_ENDERECO']         ?? '';
$bairro    = $cfg['CFG_BAIRRO']          ?? '';
$cidade    = $cfg['CFG_CIDADE']          ?? '';
$uf        = $cfg['CFG_UF']             ?? '';
$cep       = $cfg['CFG_CEP']            ?? '';
$fone      = $cfg['CFG_FONE']           ?? '';
$email     = $cfg['CFG_EMAIL']          ?? '';
$cnpj      = $cfg['CFG_CNPJ']           ?? '';
$logoPath  = $cfg['CFG_LOGO_PATH']      ?? '';

$endCompleto = implode(' — ', array_filter([
    $endereco,
    $bairro,
    $cidade && $uf ? "$cidade/$uf" : ($cidade ?: $uf),
    $cep ? "CEP: $cep" : '',
]));

/* ── Itens de vistoria ativos ── */
$itens = [];
try {
    $st = $pdo->query(
        "SELECT ITV_CHAVE, ITV_DESCRICAO FROM tb_itens_vistoria
          WHERE ITV_ATIVO = 'S'
          ORDER BY ITV_ORDEM, ITV_DESCRICAO"
    );
    $itens = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$h = fn(?string $v) => htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Checklist de Vistoria — <?= $h($empresa) ?></title>
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

        /* ── Toolbar ── */
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
        .imp-btn-print { background: #3b5bdb; color: #fff; }
        .imp-btn-close { background: #e9ecef; color: #495057; }
        .imp-btn:hover { opacity: .88; }

        /* ── Documento ── */
        .imp-doc { padding: 28px 32px; }

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
        .imp-empresa-end { font-size: 10px; color: #495057; line-height: 1.5; }
        .imp-header-badge { text-align: right; flex-shrink: 0; }
        .imp-doc-title {
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #c92a2a;
        }
        .imp-doc-date { font-size: 10px; color: #868e96; margin-top: 4px; }

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
            padding: 7px 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .imp-section-head.blue  { background: #3b5bdb; }
        .imp-section-head.red   { background: #c92a2a; }
        .imp-section-head.green { background: #2f9e44; }

        /* ── Grid de campos ── */
        .imp-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 0;
        }
        .imp-field {
            padding: 8px 12px;
            border-right: 1px solid #e9ecef;
            border-bottom: 1px solid #e9ecef;
        }
        .imp-field:last-child { border-right: none; }
        .imp-field-label {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #868e96;
            margin-bottom: 3px;
        }
        .imp-field-line {
            border-bottom: 1.5px solid #adb5bd;
            min-height: 22px;
            width: 100%;
        }

        .c2  { grid-column: span 2; }
        .c3  { grid-column: span 3; }
        .c4  { grid-column: span 4; }
        .c5  { grid-column: span 5; }
        .c6  { grid-column: span 6; }
        .c7  { grid-column: span 7; }
        .c8  { grid-column: span 8; }
        .c12 { grid-column: span 12; }

        /* ── Checklist grid ── */
        .chk-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0;
            padding: 4px 0;
        }
        .chk-item {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 6px 14px;
            border-bottom: 1px solid #f1f3f5;
            transition: background .1s;
        }
        .chk-item:last-child,
        .chk-item:nth-last-child(-n+3):nth-child(3n+1),
        .chk-item:nth-last-child(-n+3):nth-child(3n+1) ~ .chk-item {
            border-bottom: none;
        }
        .chk-box {
            width: 15px;
            height: 15px;
            border: 1.8px solid #1a1d2e;
            border-radius: 3px;
            flex-shrink: 0;
        }
        .chk-label {
            font-size: 10.5px;
            font-weight: 600;
            color: #1a1d2e;
            line-height: 1.3;
        }

        /* ── Observações ── */
        .obs-box {
            padding: 10px 14px;
            min-height: 72px;
            border-top: 1px solid #e9ecef;
        }
        .obs-lines {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 6px;
        }
        .obs-line {
            border-bottom: 1px solid #c8ced4;
            height: 18px;
        }

        /* ── Assinatura ── */
        .imp-assinaturas {
            display: flex;
            gap: 40px;
            margin-top: 22px;
            padding-top: 16px;
            border-top: 2px solid #e9ecef;
        }
        .imp-assin-bloco {
            flex: 1;
            text-align: center;
        }
        .imp-assin-line {
            border-top: 1.5px solid #1a1d2e;
            margin: 0 10px 6px;
        }
        .imp-assin-nome  { font-size: 10px; font-weight: 700; color: #495057; }
        .imp-assin-cargo { font-size: 9px; color: #868e96; margin-top: 2px; }

        /* ── Rodapé ── */
        .imp-footer {
            text-align: center;
            font-size: 9px;
            color: #adb5bd;
            padding: 10px 0 0;
            margin-top: 12px;
            border-top: 1px solid #f0f0f0;
        }

        /* ── Print ── */
        @media print {
            html, body { background: #fff !important; padding: 0 !important; }
            .imp-page         { box-shadow: none; border-radius: 0; max-width: 100%; }
            .imp-toolbar      { display: none !important; }
            .imp-doc          { padding: 4px 8px; }
            .imp-header       { padding-bottom: 6px; margin-bottom: 8px; }
            .imp-logo img     { max-height: 44px; }
            .imp-empresa-nome { font-size: 12px; }
            .imp-doc-title    { font-size: 11px; }
            .imp-section      { margin-bottom: 5px; border-radius: 4px; }
            .imp-section-head { padding: 4px 10px; font-size: 9px; }
            .imp-field        { padding: 3px 8px; }
            .imp-field-label  { font-size: 8px; margin-bottom: 1px; }
            .imp-field-line   { min-height: 16px; }
            .chk-grid         { grid-template-columns: repeat(5, 1fr); padding: 2px 0; }
            .chk-item         { padding: 3px 8px; gap: 6px; }
            .chk-box          { width: 11px; height: 11px; }
            .chk-label        { font-size: 9px; }
            .obs-box          { padding: 6px 10px; min-height: 40px; }
            .obs-lines        { gap: 7px; margin-top: 4px; }
            .obs-line         { height: 14px; }
            .imp-assinaturas  { margin-top: 8px; padding-top: 6px; gap: 20px; page-break-inside: avoid; }
            .imp-assin-bloco  > div:first-child { height: 28px !important; }
            .imp-assin-nome   { font-size: 9px; }
            .imp-assin-cargo  { font-size: 8px; }
            .imp-footer       { margin-top: 4px; padding-top: 5px; font-size: 8px; page-break-inside: avoid; }
        }

        @page { size: A4; margin: 6mm; }
    </style>
</head>
<body>

<div class="imp-page">

    <!-- Toolbar -->
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
                <div class="imp-doc-title">Checklist de Vistoria</div>
                <div class="imp-doc-date">Emitido em <?= date('d/m/Y') ?></div>
            </div>
        </div>

        <!-- DADOS DO VEÍCULO -->
        <div class="imp-section">
            <div class="imp-section-head blue">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/></svg>
                Dados do Veículo
            </div>
            <div class="imp-grid">
                <div class="imp-field c3">
                    <div class="imp-field-label">Placa</div>
                    <div class="imp-field-line"></div>
                </div>
                <div class="imp-field c5">
                    <div class="imp-field-label">Modelo</div>
                    <div class="imp-field-line"></div>
                </div>
                <div class="imp-field c4" style="border-right:none;">
                    <div class="imp-field-label">Marca</div>
                    <div class="imp-field-line"></div>
                </div>
                <div class="imp-field c2">
                    <div class="imp-field-label">Ano Fab.</div>
                    <div class="imp-field-line"></div>
                </div>
                <div class="imp-field c2">
                    <div class="imp-field-label">Ano Mod.</div>
                    <div class="imp-field-line"></div>
                </div>
                <div class="imp-field c2">
                    <div class="imp-field-label">Cor</div>
                    <div class="imp-field-line"></div>
                </div>
                <div class="imp-field c3">
                    <div class="imp-field-label">Chassi</div>
                    <div class="imp-field-line"></div>
                </div>
                <div class="imp-field c3" style="border-right:none;">
                    <div class="imp-field-label">R$ Valor</div>
                    <div class="imp-field-line"></div>
                </div>
            </div>
        </div>

        <!-- DADOS DO ASSOCIADO -->
        <div class="imp-section">
            <div class="imp-section-head">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Dados do Associado
            </div>
            <div class="imp-grid">
                <div class="imp-field c7">
                    <div class="imp-field-label">Nome Completo</div>
                    <div class="imp-field-line"></div>
                </div>
                <div class="imp-field c3">
                    <div class="imp-field-label">CPF / CNPJ</div>
                    <div class="imp-field-line"></div>
                </div>
                <div class="imp-field c2" style="border-right:none;">
                    <div class="imp-field-label">Telefone</div>
                    <div class="imp-field-line"></div>
                </div>
                <div class="imp-field c4">
                    <div class="imp-field-label">Data da Vistoria</div>
                    <div class="imp-field-line"></div>
                </div>
                <div class="imp-field c4">
                    <div class="imp-field-label">Vistoriador</div>
                    <div class="imp-field-line"></div>
                </div>
                <div class="imp-field c4" style="border-right:none;">
                    <div class="imp-field-label">Código Vidro</div>
                    <div class="imp-field-line"></div>
                </div>
            </div>
        </div>

        <!-- ITENS DE VISTORIA -->
        <div class="imp-section">
            <div class="imp-section-head red">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                Itens de Vistoria
            </div>
            <?php if (empty($itens)): ?>
                <div style="padding:16px;color:#868e96;font-style:italic;text-align:center;">Nenhum item de vistoria cadastrado.</div>
            <?php else: ?>
            <div class="chk-grid">
                <?php foreach ($itens as $item): ?>
                <div class="chk-item">
                    <div class="chk-box"></div>
                    <span class="chk-label"><?= $h($item['ITV_DESCRICAO']) ?></span>
                </div>
                <?php endforeach; ?>
                <?php
                $resto = count($itens) % 3;
                if ($resto !== 0) {
                    for ($i = 0; $i < 3 - $resto; $i++):
                ?>
                <div class="chk-item" style="visibility:hidden;"></div>
                <?php endfor; } ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- PNEUS -->
        <div class="imp-section">
            <div class="imp-section-head green">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>
                Estado dos Pneus
            </div>
            <div class="imp-grid">
                <div class="imp-field c3">
                    <div class="imp-field-label">Dianteiro Esq.</div>
                    <div class="imp-field-line"></div>
                </div>
                <div class="imp-field c3">
                    <div class="imp-field-label">Dianteiro Dir.</div>
                    <div class="imp-field-line"></div>
                </div>
                <div class="imp-field c3">
                    <div class="imp-field-label">Traseiro Esq.</div>
                    <div class="imp-field-line"></div>
                </div>
                <div class="imp-field c3" style="border-right:none;">
                    <div class="imp-field-label">Traseiro Dir.</div>
                    <div class="imp-field-line"></div>
                </div>
            </div>
        </div>

        <!-- OBSERVAÇÕES -->
        <div class="imp-section">
            <div class="imp-section-head">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                Observações
            </div>
            <div class="obs-box">
                <div class="obs-lines">
                    <div class="obs-line"></div>
                    <div class="obs-line"></div>
                    <div class="obs-line"></div>
                    <div class="obs-line"></div>
                </div>
            </div>
        </div>

        <!-- ASSINATURAS -->
        <div class="imp-assinaturas">
            <div class="imp-assin-bloco">
                <div style="height:40px;"></div>
                <div class="imp-assin-line"></div>
                <div class="imp-assin-nome">Assinatura do Associado</div>
                <div class="imp-assin-cargo">Nome / CPF</div>
            </div>
            <div class="imp-assin-bloco">
                <div style="height:40px;"></div>
                <div class="imp-assin-line"></div>
                <div class="imp-assin-nome">Assinatura do Vistoriador</div>
                <div class="imp-assin-cargo">Nome / Matrícula</div>
            </div>
            <div class="imp-assin-bloco">
                <div style="height:40px;"></div>
                <div class="imp-assin-line"></div>
                <div class="imp-assin-nome">Responsável pela Empresa</div>
                <div class="imp-assin-cargo"><?= $h($empresa) ?></div>
            </div>
        </div>

        <!-- RODAPÉ -->
        <div class="imp-footer">
            <?= $h($empresa) ?> &nbsp;·&nbsp; Documento gerado em <?= date('d/m/Y \à\s H:i') ?>
            &nbsp;·&nbsp; Este documento não tem valor jurídico sem assinatura.
        </div>

    </div><!-- /.imp-doc -->
</div><!-- /.imp-page -->

</body>
</html>
