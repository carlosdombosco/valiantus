<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/config.php';
require_once PATH_INC . '/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['SessUsuCodigo'])) {
    header('Location: ' . rtrim(BASE_URL, '/') . '/login.php'); exit;
}

$ctrId = (int)($_GET['ctr_id'] ?? 0);
$pesId = (int)($_GET['pes_id'] ?? 0);
if ($ctrId <= 0) { http_response_code(400); exit('Parâmetro ctr_id obrigatório.'); }

/* ── Configurações da empresa ── */
$cfg = [];
try {
    $cfg = $pdo->query("SELECT * FROM tb_configuracoes WHERE CFG_CODIGO_PK = 1 LIMIT 1")
                ->fetch(PDO::FETCH_ASSOC) ?: [];
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

$endCompleto = implode(' — ', array_filter([
    $endereco, $bairro,
    $cidade && $uf ? "$cidade/$uf" : ($cidade ?: $uf),
    $cep ? "CEP: $cep" : '',
]));

/* ── Contrato + Veículo + Vistoriador ── */
$sql = "
    SELECT c.CTR_CODIGO_PK, c.CTR_DATA_CAD, c.CTR_VALOR_VEICULO, c.CTR_VISTORIADO_FK,
           v.VEI_CODIGO_PK, v.VEI_PLACA, v.VEI_MARCA, v.VEI_MODELO,
           v.VEI_ANO_FABRICACAO, v.VEI_ANO_MODELO, v.VEI_CIDADE, v.VEI_UF,
           vt.VIS_NOME AS VISTORIADOR_NOME
    FROM tb_contrato c
    JOIN tb_veiculo v ON v.VEI_CODIGO_PK = c.VEI_CODIGO_FK
    LEFT JOIN tb_vistoriador vt ON vt.VIS_CODIGO_PK = c.CTR_VISTORIADO_FK
    WHERE c.CTR_CODIGO_PK = ?
";
if ($pesId > 0) $sql .= " AND c.PES_CODIGO_FK = ?";
$sql .= " LIMIT 1";

$stmt = $pdo->prepare($sql);
$pesId > 0 ? $stmt->execute([$ctrId, $pesId]) : $stmt->execute([$ctrId]);
$contrato = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$contrato) { http_response_code(404); exit('Contrato não encontrado.'); }

$veiId = (int)$contrato['VEI_CODIGO_PK'];

/* ── Vistoria do veículo ── */
$vist = $pdo->prepare("SELECT * FROM tb_vistoria WHERE VEI_CODIGO_FK = ? ORDER BY VIS_CODIGO_PK DESC LIMIT 1");
$vist->execute([$veiId]);
$vistoria = $vist->fetch(PDO::FETCH_ASSOC) ?: [];

/* ── Itens da vistoria (marcados) ── */
$itmMarcados = [];
if (!empty($vistoria['VIS_CODIGO_PK'])) {
    $si = $pdo->prepare("
        SELECT i.ITV_CODIGO_PK, i.ITV_CHAVE, i.ITV_DESCRICAO
        FROM tb_vistoria_itens vi
        JOIN tb_itens_vistoria i ON i.ITV_CODIGO_PK = vi.ITV_CODIGO_FK
        WHERE vi.VIS_CODIGO_FK = ?
    ");
    $si->execute([$vistoria['VIS_CODIGO_PK']]);
    foreach ($si->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $itmMarcados[$r['ITV_CODIGO_PK']] = true;
    }
}

/* ── Todos os itens ativos ── */
$todosItens = $pdo->query("SELECT * FROM tb_itens_vistoria WHERE ITV_ATIVO='S' ORDER BY ITV_ORDEM, ITV_CODIGO_PK")
                  ->fetchAll(PDO::FETCH_ASSOC);

/* ── Foto do chassi ── */
$fotoChassi = null;
try {
    $sfc = $pdo->prepare("SELECT IMG_CAMINHO FROM tb_imagens WHERE IMG_VEICULO_FK = ? AND IMG_CHASSI = 'SIM' ORDER BY IMG_CODIGO_PK DESC LIMIT 1");
    $sfc->execute([$veiId]);
    $fotoChassi = $sfc->fetchColumn() ?: null;
} catch (Throwable $e) {}

/* ── Helpers ── */
$h  = fn(?string $v) => htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
$dt = fn(?string $v) => $v ? date('d/m/Y H:i:s', strtotime($v)) : '—';
$money = fn(?string $v) => $v !== null && $v !== '' ? 'R$ ' . number_format((float)$v, 2, ',', '.') : '—';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Vistoria — <?= $h($contrato['VEI_PLACA']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px; color: #1a1d2e; background: #e9ecef; padding: 20px;
        }

        .imp-page {
            max-width: 820px; margin: 0 auto; background: #fff;
            border-radius: 10px; box-shadow: 0 4px 24px rgba(0,0,0,.12); overflow: hidden;
        }

        /* Toolbar */
        .imp-toolbar {
            display: flex; justify-content: flex-end; gap: 10px;
            padding: 12px 20px; background: #f8f9fb; border-bottom: 1px solid #e9ecef;
        }
        .imp-btn {
            height: 38px; padding: 0 20px; border-radius: 9px; font-size: 13px; font-weight: 700;
            cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 7px;
        }
        .imp-btn-print { background: #3b5bdb; color: #fff; }
        .imp-btn-close { background: #e9ecef; color: #495057; }
        .imp-btn:hover { opacity: .88; }

        .imp-doc { padding: 24px 32px; }

        /* Header da empresa */
        .imp-header {
            display: flex; align-items: center; gap: 18px;
            border-bottom: 3px solid #3b5bdb; padding-bottom: 12px; margin-bottom: 16px;
        }
        .imp-logo img { max-height: 64px; max-width: 130px; object-fit: contain; }
        .imp-logo-placeholder {
            width: 90px; height: 55px; border: 2px dashed #dee2e6; border-radius: 8px;
            display: flex; align-items: center; justify-content: center; color: #adb5bd; font-size: 10px;
        }
        .imp-header-info { flex: 1; }
        .imp-empresa-nome {
            font-size: 13px; font-weight: 800; color: #3b5bdb;
            text-transform: uppercase; letter-spacing: .03em; margin-bottom: 2px;
        }
        .imp-empresa-end { font-size: 10px; color: #495057; line-height: 1.5; }
        .imp-header-badge { text-align: right; flex-shrink: 0; }
        .imp-doc-title  { font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: .07em; color: #3b5bdb; }
        .imp-doc-sub    { font-size: 10px; color: #868e96; margin-top: 3px; }

        /* Título principal */
        .doc-title {
            text-align: center; font-size: 16px; font-weight: 900;
            text-transform: uppercase; letter-spacing: .1em;
            color: #1a1d2e; margin-bottom: 14px;
        }

        /* Bloco de info: linha com label + valor */
        .info-grid {
            display: grid; gap: 0;
            border: 1px solid #dee2e6; border-radius: 6px;
            overflow: hidden; margin-bottom: 12px;
        }
        .info-row {
            display: flex; border-bottom: 1px solid #dee2e6;
        }
        .info-row:last-child { border-bottom: none; }
        .info-cell {
            flex: 1; padding: 5px 10px; display: flex; align-items: center; gap: 6px;
            border-right: 1px solid #dee2e6;
        }
        .info-cell:last-child { border-right: none; }
        .info-label { font-size: 10px; font-weight: 700; color: #868e96; text-transform: uppercase; white-space: nowrap; }
        .info-value { font-size: 12px; font-weight: 600; color: #1a1d2e; }

        /* Seção */
        .section-title {
            background: #3b5bdb; color: #fff;
            font-size: 10px; font-weight: 800; letter-spacing: .08em;
            text-transform: uppercase; padding: 5px 10px;
            border-radius: 4px; margin: 12px 0 8px;
        }

        /* Observação / Pneus */
        .obs-box {
            border: 1px solid #dee2e6; border-radius: 6px;
            padding: 7px 12px; font-size: 12px; min-height: 28px;
            color: #1a1d2e; margin-bottom: 10px; background: #f9fafb;
        }

        /* Grid de acessórios */
        .acc-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2px 4px;
            margin-bottom: 12px;
        }
        .acc-item {
            display: flex; align-items: center; gap: 6px;
            padding: 3px 4px; font-size: 11px;
        }
        .acc-check {
            width: 15px; height: 15px; border: 1.5px solid #adb5bd;
            border-radius: 3px; flex-shrink: 0; display: flex;
            align-items: center; justify-content: center;
            font-size: 11px; font-weight: 900; color: #1a1d2e;
        }
        .acc-check.marked { border-color: #3b5bdb; background: #3b5bdb; color: #fff; }

        /* Foto chassi */
        .chassi-section { margin-bottom: 14px; }
        .chassi-img {
            max-width: 260px; max-height: 160px; object-fit: cover;
            border: 1px solid #dee2e6; border-radius: 6px; display: block; margin-top: 6px;
        }
        .chassi-placeholder {
            width: 260px; height: 120px; border: 2px dashed #dee2e6;
            border-radius: 6px; display: flex; align-items: center; justify-content: center;
            color: #adb5bd; font-size: 11px; margin-top: 6px;
        }

        /* Assinaturas */
        .assin-area {
            display: flex; gap: 40px; margin-top: 20px;
            padding-top: 14px; border-top: 2px solid #e9ecef;
        }
        .assin-bloco { flex: 1; text-align: center; }
        .assin-img   { max-height: 60px; max-width: 200px; object-fit: contain; margin-bottom: 4px; }
        .assin-line  { border-top: 1.5px solid #1a1d2e; margin: 0 10px 5px; }
        .assin-nome  { font-size: 10px; font-weight: 700; }
        .assin-cargo { font-size: 9px; color: #868e96; margin-top: 2px; }

        .imp-footer {
            text-align: center; font-size: 9px; color: #adb5bd;
            padding: 10px 0 0; margin-top: 12px; border-top: 1px solid #f0f0f0;
        }

        /* ── Print ── */
        @media print {
            html, body { background: #fff !important; padding: 0 !important; }
            .imp-page    { box-shadow: none; border-radius: 0; max-width: 100%; }
            .imp-toolbar { display: none !important; }
            .imp-doc     { padding: 6px 14px; }
            .imp-header  { padding-bottom: 8px; margin-bottom: 10px; }
            .imp-logo img { max-height: 50px; }
            .doc-title   { font-size: 14px; margin-bottom: 10px; }
            .section-title { padding: 3px 8px; margin: 8px 0 5px; }
            .info-cell   { padding: 3px 8px; }
            .info-value  { font-size: 11px; }
            .acc-item    { font-size: 10px; }
            .acc-check   { width: 13px; height: 13px; font-size: 10px; }
            .chassi-img  { max-width: 220px; max-height: 130px; }
            .assin-area  { margin-top: 14px; page-break-inside: avoid; }
            .imp-footer  { page-break-inside: avoid; }
        }

        @page { size: A4; margin: 8mm; }
    </style>
</head>
<body>
<div class="imp-page">

    <!-- Toolbar -->
    <div class="imp-toolbar">
        <button class="imp-btn imp-btn-close" onclick="window.close()">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            Fechar
        </button>
        <button class="imp-btn imp-btn-print" onclick="window.print()">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Imprimir
        </button>
    </div>

    <div class="imp-doc">

        <!-- Cabeçalho empresa -->
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
                <div class="imp-doc-title">Vistoria</div>
                <div class="imp-doc-sub">Emitido em <?= date('d/m/Y') ?></div>
            </div>
        </div>

        <!-- Título -->
        <div class="doc-title">Checklist Veicular</div>

        <!-- Datas e Vistoriador -->
        <div class="info-grid">
            <div class="info-row">
                <div class="info-cell">
                    <span class="info-label">Data Cadastro:</span>
                    <span class="info-value"><?= $h($dt($contrato['CTR_DATA_CAD'])) ?></span>
                </div>
                <div class="info-cell">
                    <span class="info-label">Data Atualização:</span>
                    <span class="info-value"><?= $h($dt($vistoria['VIS_DATA_CAD'] ?? null)) ?></span>
                </div>
                <div class="info-cell">
                    <span class="info-label">Vistoriador:</span>
                    <span class="info-value"><?= $h($contrato['VISTORIADOR_NOME'] ?? '—') ?></span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-cell">
                    <span class="info-label">Placa:</span>
                    <span class="info-value"><?= $h($contrato['VEI_PLACA']) ?></span>
                </div>
                <div class="info-cell">
                    <span class="info-label">Cidade/UF:</span>
                    <span class="info-value">
                        <?php
                            $cid = trim($contrato['VEI_CIDADE'] ?? '');
                            $ufv = trim($contrato['VEI_UF'] ?? '');
                            echo $h($cid && $ufv ? "$cid / $ufv" : ($cid ?: ($ufv ?: '—')));
                        ?>
                    </span>
                </div>
                <div class="info-cell">
                    <span class="info-label">Código do Vidro:</span>
                    <span class="info-value"><?= $h($vistoria['VIS_CODIGO_VIDRO'] ?? '—') ?></span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-cell" style="flex:2;">
                    <span class="info-label">Veículo:</span>
                    <span class="info-value">
                        <?= $h(trim(($contrato['VEI_MARCA'] ?? '') . ' — ' . ($contrato['VEI_MODELO'] ?? ''))) ?>
                        <?php if (!empty($contrato['VEI_ANO_FABRICACAO'])): ?>
                            <span style="color:#868e96;font-size:10px;margin-left:6px;">
                                <?= $h($contrato['VEI_ANO_FABRICACAO']) ?><?= !empty($contrato['VEI_ANO_MODELO']) ? '/' . $h($contrato['VEI_ANO_MODELO']) : '' ?>
                            </span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-cell">
                    <span class="info-label">Valor Avaliado:</span>
                    <span class="info-value"><?= $h($money($contrato['CTR_VALOR_VEICULO'])) ?></span>
                </div>
            </div>
        </div>

        <!-- Observação -->
        <div class="section-title">Observação</div>
        <div class="obs-box"><?= $h($vistoria['VIS_OBSERVACAO'] ?? '') ?: '&nbsp;' ?></div>

        <!-- Pneus -->
        <div class="section-title">Pneus</div>
        <div class="obs-box"><?= $h($vistoria['VIS_PNEUS'] ?? '') ?: '&nbsp;' ?></div>

        <!-- Acessórios de Fábrica -->
        <div class="section-title">Acessórios de Fábrica</div>
        <?php if ($todosItens): ?>
        <div class="acc-grid">
            <?php foreach ($todosItens as $item): ?>
            <div class="acc-item">
                <div class="acc-check <?= isset($itmMarcados[$item['ITV_CODIGO_PK']]) ? 'marked' : '' ?>">
                    <?= isset($itmMarcados[$item['ITV_CODIGO_PK']]) ? '✓' : '' ?>
                </div>
                <span><?= $h($item['ITV_DESCRICAO']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p style="color:#adb5bd;font-style:italic;padding:8px 0;">Nenhum item de vistoria cadastrado.</p>
        <?php endif; ?>

        <!-- Foto do Chassi -->
        <div class="chassi-section">
            <div class="section-title">Foto Chassi</div>
            <?php if ($fotoChassi): ?>
                <img class="chassi-img" src="<?= $h($fotoChassi) ?>" alt="Foto do Chassi">
            <?php else: ?>
                <div class="chassi-placeholder">Sem foto de chassi cadastrada</div>
            <?php endif; ?>
        </div>

        <!-- Assinaturas -->
        <div class="assin-area">
            <div class="assin-bloco">
                <?php if ($assinPath): ?>
                    <img class="assin-img" src="<?= $h($assinPath) ?>" alt="Assinatura">
                <?php else: ?>
                    <div style="height:55px;"></div>
                <?php endif; ?>
                <div class="assin-line"></div>
                <div class="assin-nome">Contratada</div>
                <div class="assin-cargo">Assinatura com Registro em Cartório</div>
            </div>
            <div class="assin-bloco">
                <div style="height:55px;"></div>
                <div class="assin-line"></div>
                <div class="assin-nome"><?= $h($nomeResp ?: 'Responsável Legal') ?></div>
                <div class="assin-cargo"><?= $h($cargoResp ?: 'Representante') ?></div>
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
