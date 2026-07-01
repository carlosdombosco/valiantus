<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/config.php';
require_once PATH_INC . '/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['SessUsuCodigo'])) {
    header('Location: ' . rtrim(BASE_URL, '/') . '/login.php'); exit;
}

$cfg = [];
try {
    $cfg = $pdo->query("SELECT * FROM tb_configuracoes WHERE CFG_CODIGO_PK = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {}

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
$texto     = $cfg['CFG_TEXTO_REGIMENTO']  ?? '';

// Fallback: se não há texto no banco, serve o PDF estático
if (!trim((string)$texto)) {
    $pdfPath = PATH_ROOT . '/Documentos/REGIMENTO.pdf';
    if (file_exists($pdfPath)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="Regimento-Interno.pdf"');
        header('Content-Length: ' . filesize($pdfPath));
        header('Cache-Control: private, max-age=0, must-revalidate');
        readfile($pdfPath);
        exit;
    }
}

$endCompleto = implode(' — ', array_filter([
    $endereco, $bairro,
    $cidade && $uf ? "$cidade/$uf" : ($cidade ?: $uf),
    $cep ? "CEP: $cep" : '',
]));

$h = fn(?string $v) => htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Regimento — <?= $h($empresa) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11.5px; color: #1a1d2e; background: #e9ecef; padding: 20px;
        }

        .imp-page {
            max-width: 800px; margin: 0 auto; background: #fff;
            border-radius: 10px; box-shadow: 0 4px 24px rgba(0,0,0,.12); overflow: hidden;
        }

        .imp-toolbar {
            display: flex; justify-content: flex-end; gap: 10px;
            padding: 12px 20px; background: #f8f9fb; border-bottom: 1px solid #e9ecef;
        }
        .imp-btn {
            height: 38px; padding: 0 20px; border-radius: 9px; font-size: 13px; font-weight: 700;
            cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 7px;
        }
        .imp-btn-print { background: #2f9e44; color: #fff; }
        .imp-btn-close { background: #e9ecef; color: #495057; }
        .imp-btn:hover { opacity: .88; }

        .imp-doc { padding: 28px 36px; }

        .imp-header {
            display: flex; align-items: center; gap: 20px;
            border-bottom: 3px solid #2f9e44;
            padding-bottom: 14px; margin-bottom: 20px;
        }
        .imp-logo img { max-height: 70px; max-width: 140px; object-fit: contain; }
        .imp-logo-placeholder {
            width: 100px; height: 60px; border: 2px dashed #dee2e6; border-radius: 8px;
            display: flex; align-items: center; justify-content: center; color: #adb5bd; font-size: 10px;
        }
        .imp-header-info { flex: 1; }
        .imp-empresa-nome {
            font-size: 14px; font-weight: 800; color: #2f9e44;
            text-transform: uppercase; letter-spacing: .04em; margin-bottom: 3px;
        }
        .imp-empresa-end { font-size: 10px; color: #495057; line-height: 1.5; }
        .imp-header-badge { text-align: right; flex-shrink: 0; }
        .imp-doc-title {
            font-size: 13px; font-weight: 800; text-transform: uppercase;
            letter-spacing: .08em; color: #2f9e44;
        }
        .imp-doc-date { font-size: 10px; color: #868e96; margin-top: 4px; }

        .imp-page-title {
            text-align: center; font-size: 18px; font-weight: 900;
            text-transform: uppercase; letter-spacing: .12em;
            color: #1a1d2e; margin-bottom: 20px;
        }
        .imp-page-title small {
            display: block; font-size: 11px; font-weight: 400;
            color: #868e96; letter-spacing: .04em; margin-top: 4px;
        }

        .doc-body {
            font-size: 12px; line-height: 1.9; color: #2c3041;
            white-space: pre-wrap; margin-bottom: 30px;
        }
        .doc-body-vazio {
            color: #adb5bd; font-style: italic; text-align: center;
            padding: 50px 0; font-size: 12px;
        }

        .imp-assinaturas {
            display: flex; gap: 40px; margin-top: 30px;
            padding-top: 16px; border-top: 2px solid #e9ecef;
        }
        .imp-assin-bloco { flex: 1; text-align: center; }
        .imp-assin-img { max-height: 55px; max-width: 180px; object-fit: contain; margin-bottom: 4px; }
        .imp-assin-line { border-top: 1.5px solid #1a1d2e; margin: 0 10px 5px; }
        .imp-assin-nome { font-size: 10px; font-weight: 700; }
        .imp-assin-cargo { font-size: 9px; color: #868e96; margin-top: 2px; }

        .imp-footer {
            text-align: center; font-size: 9px; color: #adb5bd;
            padding: 10px 0 0; margin-top: 12px; border-top: 1px solid #f0f0f0;
        }

        @media print {
            html, body { background: #fff !important; padding: 0 !important; }
            .imp-page    { box-shadow: none; border-radius: 0; max-width: 100%; }
            .imp-toolbar { display: none !important; }
            .imp-doc     { padding: 6px 12px; }
            .imp-header  { padding-bottom: 8px; margin-bottom: 12px; }
            .imp-logo img { max-height: 52px; }
            .imp-page-title { font-size: 16px; margin-bottom: 14px; }
            .doc-body    { font-size: 11px; line-height: 1.7; }
            .imp-assinaturas { margin-top: 16px; page-break-inside: avoid; }
            .imp-footer  { margin-top: 6px; page-break-inside: avoid; }
        }

        @page { size: A4; margin: 10mm; }
    </style>
</head>
<body>
<div class="imp-page">

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

    <div class="imp-doc">

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
                <?php if ($endCompleto): ?><div class="imp-empresa-end"><?= $h($endCompleto) ?></div><?php endif; ?>
                <?php if ($fone || $email): ?>
                    <div class="imp-empresa-end">
                        <?= $fone ? 'Tel: ' . $h($fone) : '' ?>
                        <?= ($fone && $email) ? ' &nbsp;|&nbsp; ' : '' ?>
                        <?= $email ? $h($email) : '' ?>
                    </div>
                <?php endif; ?>
                <?php if ($cnpj): ?><div class="imp-empresa-end">CNPJ: <?= $h($cnpj) ?></div><?php endif; ?>
            </div>
            <div class="imp-header-badge">
                <div class="imp-doc-title">Regimento</div>
                <div class="imp-doc-date">Emitido em <?= date('d/m/Y') ?></div>
            </div>
        </div>

        <div class="imp-page-title">
            Regimento Interno
            <small><?= $h($empresa) ?></small>
        </div>

        <?php if ($texto): ?>
            <div class="doc-body"><?= nl2br($h($texto)) ?></div>
        <?php else: ?>
            <div class="doc-body-vazio">
                Nenhum texto de regimento cadastrado.<br>
                Acesse <strong>Configurações → Documentos</strong> para adicionar o conteúdo.
            </div>
        <?php endif; ?>

        <div class="imp-assinaturas">
            <div class="imp-assin-bloco">
                <div style="height:50px;"></div>
                <div class="imp-assin-line"></div>
                <div class="imp-assin-nome">Presidente</div>
                <div class="imp-assin-cargo"><?= $h($empresa) ?></div>
            </div>
            <div class="imp-assin-bloco">
                <?php if ($assinPath): ?>
                    <img class="imp-assin-img" src="<?= $h($assinPath) ?>" alt="Assinatura">
                <?php else: ?>
                    <div style="height:50px;"></div>
                <?php endif; ?>
                <div class="imp-assin-line"></div>
                <div class="imp-assin-nome"><?= $h($nomeResp ?: 'Responsável Legal') ?></div>
                <div class="imp-assin-cargo"><?= $h($cargoResp ?: 'Representante') ?></div>
            </div>
        </div>

        <div class="imp-footer">
            <?= $h($empresa) ?> &nbsp;·&nbsp; CNPJ: <?= $h($cnpj ?: '—') ?> &nbsp;·&nbsp;
            Documento gerado em <?= date('d/m/Y \à\s H:i') ?>
        </div>

    </div>
</div>
</body>
</html>
