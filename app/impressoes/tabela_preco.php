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

$empresa  = $cfg['CFG_RAZAO_SOCIAL']    ?? 'VALIANTUS ASSOCIAÇÃO VEICULAR';
$endereco = $cfg['CFG_ENDERECO']        ?? '';
$bairro   = $cfg['CFG_BAIRRO']         ?? '';
$cidade   = $cfg['CFG_CIDADE']         ?? '';
$uf       = $cfg['CFG_UF']            ?? '';
$cep      = $cfg['CFG_CEP']           ?? '';
$fone     = $cfg['CFG_FONE']          ?? '';
$email    = $cfg['CFG_EMAIL']         ?? '';
$cnpj     = $cfg['CFG_CNPJ']          ?? '';
$logoPath = $cfg['CFG_LOGO_PATH']     ?? '';

$grupos = [];
try {
    $grupos = $pdo->query("SELECT * FROM tb_grupo ORDER BY GRU_SEQUENCIA ASC, GRU_DESCRICAO ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$endCompleto = implode(' — ', array_filter([
    $endereco, $bairro,
    $cidade && $uf ? "$cidade/$uf" : ($cidade ?: $uf),
    $cep ? "CEP: $cep" : '',
]));

$h   = fn(?string $v) => htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
$brl = fn($v) => 'R$ ' . number_format((float)($v ?? 0), 2, ',', '.');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Tabela de Preços — <?= $h($empresa) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11.5px; color: #1a1d2e; background: #e9ecef; padding: 20px;
        }

        .imp-page {
            max-width: 920px; margin: 0 auto; background: #fff;
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
            color: #1a1d2e; margin-bottom: 24px;
        }
        .imp-page-title small {
            display: block; font-size: 11px; font-weight: 400;
            color: #868e96; letter-spacing: .04em; margin-top: 4px;
        }

        /* Grupos agrupados por tipo */
        .tipo-section { margin-bottom: 28px; }
        .tipo-titulo {
            font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .1em;
            color: #fff; background: #1a1d2e; padding: 7px 14px;
            border-radius: 6px; margin-bottom: 0;
        }

        table.preco-table {
            width: 100%; border-collapse: collapse; margin-bottom: 8px;
        }
        table.preco-table thead th {
            background: #f1f3f5; font-size: 10px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .07em;
            padding: 8px 10px; text-align: right; color: #495057;
            border-bottom: 2px solid #dee2e6;
        }
        table.preco-table thead th:first-child { text-align: left; }
        table.preco-table tbody tr:nth-child(even) td { background: #f8f9fa; }
        table.preco-table tbody td {
            padding: 9px 10px; font-size: 12px; border-bottom: 1px solid #f1f3f5;
            text-align: right; color: #2c3041;
        }
        table.preco-table tbody td:first-child { text-align: left; font-weight: 600; }
        .badge-status {
            display: inline-block; padding: 2px 8px; border-radius: 20px;
            font-size: 10px; font-weight: 700; text-transform: uppercase;
        }
        .badge-ativo   { background: #d3f9d8; color: #1a7431; }
        .badge-inativo { background: #f1f3f5; color: #868e96; }

        .imp-footer {
            text-align: center; font-size: 9px; color: #adb5bd;
            padding: 14px 0 0; margin-top: 16px; border-top: 1px solid #f0f0f0;
        }

        @media print {
            html, body { background: #fff !important; padding: 0 !important; }
            .imp-page    { box-shadow: none; border-radius: 0; max-width: 100%; }
            .imp-toolbar { display: none !important; }
            .imp-doc     { padding: 6px 12px; }
            .imp-header  { padding-bottom: 8px; margin-bottom: 12px; }
            .imp-logo img { max-height: 52px; }
            .imp-page-title { font-size: 16px; margin-bottom: 16px; }
            .tipo-section { page-break-inside: avoid; }
            .imp-footer  { margin-top: 10px; page-break-inside: avoid; }
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
                <div class="imp-doc-title">Tabela de Preços</div>
                <div class="imp-doc-date">Emitido em <?= date('d/m/Y') ?></div>
            </div>
        </div>

        <div class="imp-page-title">
            Tabela de Preços
            <small><?= $h($empresa) ?></small>
        </div>

        <?php if (empty($grupos)): ?>
            <p style="text-align:center;color:#adb5bd;padding:40px 0;font-style:italic;">
                Nenhum grupo cadastrado. Acesse <strong>Cadastros → Tabela de Preço</strong> para adicionar.
            </p>
        <?php else:
            // Agrupa por tipo de veículo
            $porTipo = [];
            foreach ($grupos as $g) {
                $tipo = $g['GRU_TIPO_VEICULO'] ?? 'OUTROS';
                $porTipo[$tipo][] = $g;
            }
            ksort($porTipo);
            foreach ($porTipo as $tipo => $itens):
        ?>
            <div class="tipo-section">
                <div class="tipo-titulo"><?= $h($tipo) ?></div>
                <table class="preco-table">
                    <thead>
                        <tr>
                            <th>Plano / Grupo</th>
                            <th>Mensalidade</th>
                            <th>Mín. Cobertura</th>
                            <th>Máx. Cobertura</th>
                            <th>Adesão</th>
                            <th>Renovação</th>
                            <th>Situação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itens as $g): ?>
                            <tr>
                                <td><?= $h($g['GRU_DESCRICAO'] ?? '') ?></td>
                                <td><?= $brl($g['GRU_VALOR_MENSALIDADE']) ?></td>
                                <td><?= $brl($g['GRU_VALOR_MINIMO']) ?></td>
                                <td><?= $brl($g['GRU_VALOR_MAXIMO']) ?></td>
                                <td><?= $brl($g['GRU_VALOR_ADESAO']) ?></td>
                                <td><?= $brl($g['GRU_VALOR_RENOVACAO']) ?></td>
                                <td>
                                    <?php $s = strtoupper($g['GRU_STATUS'] ?? ''); ?>
                                    <span class="badge-status <?= $s === 'ATIVO' ? 'badge-ativo' : 'badge-inativo' ?>">
                                        <?= $h($g['GRU_STATUS'] ?? '') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; endif; ?>

        <div class="imp-footer">
            <?= $h($empresa) ?> &nbsp;·&nbsp; CNPJ: <?= $h($cnpj ?: '—') ?> &nbsp;·&nbsp;
            Documento gerado em <?= date('d/m/Y \à\s H:i') ?>
        </div>

    </div>
</div>
</body>
</html>
