<?php
// ── Guards & Includes ─────────────────────────────────────────────────────
if (!defined('PATH_INC')) require_once __DIR__ . '/../../inc/config.php';
require_once PATH_INC . '/db.php';
require_once PATH_INC . '/csrf.php';
require_once PATH_INC . '/repositories/CorRepository.php';
require_once PATH_INC . '/repositories/GrupoRepository.php';
require_once PATH_INC . '/repositories/ComboRepository.php';
require_once PATH_INC . '/repositories/RastreadorRepository.php';

$csrf = csrf_token();

// ── Valida ID ────────────────────────────────────────────────────────────
$pesId = (int)($_GET['id'] ?? 0);
if ($pesId <= 0) {
    echo '<script>location.replace(' . json_encode(APP_URL . '/associados/') . ');</script>';
    exit;
}

$stPes = $pdo->prepare("SELECT * FROM tb_pessoa WHERE PES_CODIGO_PK = ? LIMIT 1");
$stPes->execute([$pesId]);
$p = $stPes->fetch(PDO::FETCH_ASSOC);
if (!$p) {
    echo '<script>location.replace(' . json_encode(APP_URL . '/associados/') . ');</script>';
    exit;
}

// ── Veículos + último contrato por veículo ────────────────────────────────
$veiculos = [];
try {
    $stVei = $pdo->prepare("
        SELECT v.*,
               c.CTR_CODIGO_PK, c.CTR_STATUS,
               c.CTR_VALOR_MENSALIDADE, c.CTR_VALOR_TOTAL,
               g.GRU_DESCRICAO
        FROM tb_veiculo v
        LEFT JOIN (
            SELECT t1.* FROM tb_contrato t1
            JOIN (SELECT VEI_CODIGO_FK, MAX(CTR_CODIGO_PK) mx
                  FROM tb_contrato GROUP BY VEI_CODIGO_FK) t2
              ON t2.VEI_CODIGO_FK=t1.VEI_CODIGO_FK AND t2.mx=t1.CTR_CODIGO_PK
        ) c ON c.VEI_CODIGO_FK = v.VEI_CODIGO_PK
        LEFT JOIN tb_grupo g ON g.GRU_CODIGO_PK = c.GRU_CODIGO_FK
        WHERE v.PES_CODIGO_FK = ?
        ORDER BY v.VEI_CODIGO_PK DESC
    ");
    $stVei->execute([$pesId]);
    $veiculos = $stVei->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $veiculos = []; }

// ── Todos os contratos (com joins) ────────────────────────────────────────
$contratos = [];
try {
    $stCtr = $pdo->prepare("
        SELECT c.*,
               v.VEI_PLACA, v.VEI_MARCA, v.VEI_MODELO, v.VEI_ANO_FABRICACAO,
               g.GRU_DESCRICAO,
               cb.COM_DESCRICAO,
               r.RAS_MODELO, r.RAS_OPERADORA
        FROM tb_contrato c
        LEFT JOIN tb_veiculo   v  ON v.VEI_CODIGO_PK  = c.VEI_CODIGO_FK
        LEFT JOIN tb_grupo     g  ON g.GRU_CODIGO_PK  = c.GRU_CODIGO_FK
        LEFT JOIN tb_combo     cb ON cb.COM_CODIGO_PK  = c.COM_CODIGO_FK
        LEFT JOIN tb_rastreador r  ON r.RAS_CODIGO_PK  = c.CON_RASTREADOR_FK
        WHERE c.PES_CODIGO_FK = ?
        ORDER BY c.CTR_CODIGO_PK DESC
    ");
    $stCtr->execute([$pesId]);
    $contratos = $stCtr->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $contratos = []; }

// ── Sinistros ─────────────────────────────────────────────────────────────
$sinistros = [];
try {
    $stSin = $pdo->prepare("
        SELECT s.SIN_CODIGO_PK, s.SIN_DATA_OCORRENCIA, s.SIN_TIPO_OCORRENCIA,
               s.SIN_NUM_BO, s.SIN_STATUS, s.SIN_NOME_CONDUTOR,
               s.SIN_VALOR_FIPE, s.SIN_VALOR_FRANQUIA,
               v.VEI_PLACA, v.VEI_MARCA, v.VEI_MODELO
        FROM tb_sinistro s
        LEFT JOIN tb_veiculo v ON v.VEI_CODIGO_PK = s.VEI_CODIGO_FK
        WHERE s.PES_CODIGO_FK = ?
        ORDER BY s.SIN_CODIGO_PK DESC
    ");
    $stSin->execute([$pesId]);
    $sinistros = $stSin->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $sinistros = []; }

// ── Stats ────────────────────────────────────────────────────────────────
$totalVeiculos   = count($veiculos);
$contratosAtivos = count(array_filter($contratos, fn($c) => ($c['CTR_STATUS'] ?? '') === 'A'));
$totalSinistros  = count($sinistros);

// ── Para modais ──────────────────────────────────────────────────────────
try { $cores        = listarCores($pdo); }        catch (Throwable $e) { $cores        = []; }
try { $grupos       = listarGrupos($pdo); }       catch (Throwable $e) { $grupos       = []; }
try { $combos       = listarCombos($pdo); }       catch (Throwable $e) { $combos       = []; }
try { $rastreadores = listarRastreadores($pdo); }  catch (Throwable $e) { $rastreadores = []; }
try {
    $vistoriadores = $pdo->query("SELECT VIS_CODIGO_PK, VIS_NOME FROM tb_vistoriador WHERE VIS_STATUS='ATIVO' ORDER BY VIS_NOME")->fetchAll(PDO::FETCH_OBJ);
} catch (Throwable $e) { $vistoriadores = []; }

// ── Helpers ──────────────────────────────────────────────────────────────
$h       = fn(string $k) => htmlspecialchars($p[$k] ?? '', ENT_QUOTES, 'UTF-8');
$money   = fn($v) => ($v === null || $v === '' || (float)$v === 0.0) ? '—' : 'R$ ' . number_format((float)$v, 2, ',', '.');
$fmtDate = fn($d) => ($d && $d !== '0000-00-00') ? date('d/m/Y', strtotime($d)) : '—';
$hv      = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');

$pesNome  = $p['PES_NOME'] ?? 'Associado';
$pesFoto  = $p['PES_FOTO'] ?? '';
$iniciais = strtoupper(implode('', array_map(
    fn($pt) => $pt[0],
    array_slice(explode(' ', trim($pesNome ?: 'AS')), 0, 2)
)));

// Monta lista de veículos do associado para o JS do modal de sinistro
$veiculosParaSinistro = array_map(fn($v) => [
    'veiId'  => (string)($v['VEI_CODIGO_PK']   ?? ''),
    'ctrId'  => (string)($v['CTR_CODIGO_PK']   ?? ''),
    'pesId'  => (string)$pesId,
    'placa'  => $v['VEI_PLACA']   ?? '',
    'modelo' => trim(($v['VEI_MARCA'] ?? '') . ' ' . ($v['VEI_MODELO'] ?? '')),
    'assoc'  => $pesNome,
    'cpf'    => $p['PES_CPF_CNPJ'] ?? '',
    'fone'   => $p['PES_FONE_CELULAR_1'] ?? '',
    'fipe'   => $v['CTR_VALOR_VEICULO'] ?? '',
], $veiculos);
?>

<link rel="stylesheet" href="../valiantus-tables.css">

<style>
/* ── Header ── */
.fi-header {
    background: linear-gradient(135deg, #3b5bdb 0%, #2f4abf 100%);
    border-radius: 20px; padding: 28px 32px;
    display: flex; align-items: center; gap: 24px;
    margin-bottom: 22px;
    box-shadow: 0 8px 32px rgba(59,91,219,.28);
    position: relative; overflow: hidden;
}
.fi-header::before {
    content: ''; position: absolute;
    width: 340px; height: 340px; border-radius: 50%;
    border: 1px solid rgba(255,255,255,.07);
    top: -140px; right: 60px; pointer-events: none;
}
.fi-avatar {
    width: 88px; height: 88px; border-radius: 50%; flex-shrink: 0;
    border: 3px solid rgba(255,255,255,.35);
    object-fit: cover; display: block;
}
.fi-avatar-initials {
    width: 88px; height: 88px; border-radius: 50%; flex-shrink: 0;
    border: 3px solid rgba(255,255,255,.3);
    background: rgba(255,255,255,.18);
    display: flex; align-items: center; justify-content: center;
    font-size: 28px; font-weight: 800; color: #fff; letter-spacing: .02em;
}
.fi-info { flex: 1; min-width: 0; z-index: 1; }
.fi-name { font-size: 22px; font-weight: 800; color: #fff; margin: 0 0 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.fi-sub  { color: rgba(255,255,255,.72); font-size: 13px; margin: 0 0 14px; display: flex; gap: 16px; flex-wrap: wrap; }
.fi-sub span { display: flex; align-items: center; gap: 5px; }
.fi-pills { display: flex; gap: 8px; flex-wrap: wrap; }
.fi-pill {
    background: rgba(255,255,255,.13); border: 1px solid rgba(255,255,255,.22);
    border-radius: 99px; padding: 5px 14px;
    font-size: 12px; font-weight: 700; color: rgba(255,255,255,.92);
    display: flex; align-items: center; gap: 5px;
}
.fi-pill.green { background: rgba(47,158,68,.3); border-color: rgba(47,158,68,.5); color: #b2f2bb; }
.fi-pill.red   { background: rgba(201,42,42,.3); border-color: rgba(201,42,42,.5); color: #ffc9c9; }
.fi-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-start; flex-shrink: 0; z-index: 1; }
.fi-btn-back {
    height: 38px; padding: 0 16px; border-radius: 10px;
    background: rgba(255,255,255,.1); color: rgba(255,255,255,.85);
    border: 1.5px solid rgba(255,255,255,.25); font-size: 13px; font-weight: 600;
    cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
    text-decoration: none; transition: .15s;
}
.fi-btn-back:hover { background: rgba(255,255,255,.18); color: #fff; text-decoration: none; }
.fi-btn-primary {
    height: 38px; padding: 0 18px; border-radius: 10px;
    background: #fff; color: #3b5bdb;
    border: none; font-size: 13px; font-weight: 700;
    cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
    text-decoration: none; transition: .15s;
}
.fi-btn-primary:hover { background: #e8edff; color: #2f4abf; text-decoration: none; }
.fi-btn-outline {
    height: 38px; padding: 0 16px; border-radius: 10px;
    background: rgba(255,255,255,.12); color: #fff;
    border: 1.5px solid rgba(255,255,255,.28); font-size: 13px; font-weight: 600;
    cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
    text-decoration: none; transition: .15s;
}
.fi-btn-outline:hover { background: rgba(255,255,255,.22); color: #fff; text-decoration: none; }

/* ── Breadcrumb ── */
.fi-breadcrumb { font-size: 12.5px; color: #868e96; margin-bottom: 14px; display: flex; align-items: center; gap: 6px; }
.fi-breadcrumb a { color: #3b5bdb; text-decoration: none; font-weight: 600; }
.fi-breadcrumb a:hover { text-decoration: underline; }

/* ── Tabs ── */
.fi-tabs {
    display: flex; gap: 2px; margin-bottom: 18px;
    border-bottom: 2px solid #e9ecef; overflow-x: auto;
}
.fi-tab {
    padding: 10px 20px; border: none; background: transparent;
    font-size: 13.5px; font-weight: 600; color: #868e96;
    cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px;
    transition: .15s; border-radius: 8px 8px 0 0;
    display: flex; align-items: center; gap: 7px; white-space: nowrap; flex-shrink: 0;
}
.fi-tab:hover  { color: #3b5bdb; background: #f0f3ff; }
.fi-tab.active { color: #3b5bdb; border-bottom-color: #3b5bdb; background: #f0f3ff; }
.fi-tab .fi-tab-count {
    background: #e9ecef; color: #495057; border-radius: 99px;
    font-size: 11px; font-weight: 700; padding: 2px 7px; min-width: 20px; text-align: center;
}
.fi-tab.active .fi-tab-count { background: #3b5bdb; color: #fff; }
.fi-panel { display: none; }
.fi-panel.active { display: block; }

/* ── Cards ── */
.fi-card {
    background: #fff; border: 1px solid #e9ecef; border-radius: 16px;
    box-shadow: 0 4px 20px rgba(30,40,80,.06); margin-bottom: 20px; overflow: hidden;
}
.fi-card-header {
    padding: 14px 22px; border-bottom: 1px solid #e9ecef;
    display: flex; align-items: center; justify-content: space-between; background: #fafbfc;
}
.fi-card-header-left { display: flex; align-items: center; gap: 10px; }
.fi-card-header i { color: #3b5bdb; font-size: 15px; }
.fi-card-header h3 { margin: 0; font-size: 15px; font-weight: 700; color: #1a1d2e; }
.fi-card-body { padding: 22px; }

/* ── Dados: grid de info ── */
.fi-info-section { margin-bottom: 24px; }
.fi-info-section-title {
    font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .08em;
    color: #3b5bdb; margin-bottom: 12px; display: flex; align-items: center; gap: 7px;
    padding-bottom: 8px; border-bottom: 1px solid #e9ecef;
}
.fi-info-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0; }
.fi-info-item { padding: 10px 0 10px; }
.fi-info-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #adb5bd; margin-bottom: 3px; }
.fi-info-value { font-size: 13.5px; font-weight: 600; color: #1a1d2e; word-break: break-word; }
.fi-info-value.muted { color: #adb5bd; font-weight: 400; font-style: italic; }
.fi-obs-box {
    background: #f8f9fb; border: 1px solid #e9ecef; border-radius: 10px;
    padding: 14px 16px; font-size: 13.5px; color: #495057; line-height: 1.6;
    min-height: 60px; white-space: pre-wrap;
}

/* ── Veículos ── */
.fi-vei-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 14px; }
.fi-vei-card {
    background: #fff; border: 1.5px solid #e9ecef; border-radius: 14px;
    padding: 18px 20px; display: flex; gap: 16px; align-items: flex-start;
    transition: .15s; position: relative;
}
.fi-vei-card:hover { border-color: #c5d0ff; box-shadow: 0 4px 18px rgba(59,91,219,.1); }
.fi-vei-icon {
    width: 52px; height: 52px; border-radius: 14px; background: #e8edff;
    color: #3b5bdb; display: flex; align-items: center; justify-content: center;
    font-size: 20px; flex-shrink: 0;
}
.fi-vei-body { flex: 1; min-width: 0; }
.fi-vei-placa { font-size: 20px; font-weight: 800; font-family: monospace; color: #1a1d2e; letter-spacing: .05em; }
.fi-vei-modelo { font-size: 13px; color: #495057; margin: 2px 0 8px; }
.fi-vei-meta { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
.fi-vei-tag { font-size: 11.5px; color: #868e96; background: #f1f3f5; border-radius: 6px; padding: 2px 8px; }
.fi-vei-footer { display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
.fi-vei-btns { display: flex; gap: 6px; }

/* ── Tabela genérica ── */
.fi-table-wrap { overflow-x: auto; }
.fi-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.fi-table th {
    background: #3b5bdb; color: #fff; padding: 11px 14px;
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .06em; text-align: left; white-space: nowrap;
}
.fi-table th:first-child { border-radius: 0; }
.fi-table td { padding: 11px 14px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
.fi-table tbody tr:last-child td { border-bottom: none; }
.fi-table tbody tr:hover td { background: #f8f9fb; }
.fi-table .fi-empty td { text-align: center; color: #adb5bd; padding: 48px 14px; }

/* ── Status badges ── */
.fi-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 11px; border-radius: 20px; font-size: 11px; font-weight: 700; }
.fi-badge-ativo     { background: #d3f9d8; color: #2f9e44; }
.fi-badge-cancelado { background: #ffe3e3; color: #c92a2a; }
.fi-badge-aberto    { background: #fff3cd; color: #856404; }
.fi-badge-encerrado { background: #d3f9d8; color: #2f9e44; }
.fi-badge-sem       { background: #f1f3f5; color: #868e96; }

/* ── Botões da tabela ── */
.fi-action-btn {
    width: 32px; height: 32px; border-radius: 8px; border: 1.5px solid #e9ecef;
    background: #fff; color: #495057; font-size: 13px;
    display: inline-flex; align-items: center; justify-content: center;
    cursor: pointer; transition: .15s; text-decoration: none;
}
.fi-action-btn:hover { border-color: #3b5bdb; color: #3b5bdb; background: #f0f3ff; text-decoration: none; }
.fi-action-btn.danger:hover { border-color: #c92a2a; color: #c92a2a; background: #fff5f5; }

/* ── Placeholder ── */
.fi-placeholder {
    text-align: center; padding: 64px 24px;
    color: #adb5bd;
}
.fi-placeholder i { font-size: 52px; display: block; margin-bottom: 16px; opacity: .35; }
.fi-placeholder h4 { font-size: 17px; font-weight: 700; color: #868e96; margin-bottom: 8px; }
.fi-placeholder p  { font-size: 13.5px; max-width: 400px; margin: 0 auto; line-height: 1.6; }

/* ── Add vehicle button ── */
.fi-add-btn {
    height: 38px; padding: 0 18px; border-radius: 10px;
    background: #3b5bdb; color: #fff; border: none;
    font-size: 13px; font-weight: 700; cursor: pointer;
    display: inline-flex; align-items: center; gap: 7px; transition: .15s;
    text-decoration: none;
}
.fi-add-btn:hover { background: #2f4abf; transform: translateY(-1px); box-shadow: 0 6px 16px rgba(59,91,219,.25); color: #fff; text-decoration: none; }

/* ── Sinistro tipo ── */
.fi-sin-tipo { font-size: 12px; font-weight: 700; color: #495057; background: #f1f3f5; border-radius: 6px; padding: 2px 8px; }
</style>

<div class="vt-page">

    <!-- Breadcrumb -->
    <div class="fi-breadcrumb">
        <a href="<?= APP_URL ?>/associados/"><i class="fa-solid fa-users"></i> Associados</a>
        <i class="fa-solid fa-chevron-right" style="font-size:10px;"></i>
        <span><?= htmlspecialchars($pesNome) ?></span>
    </div>

    <!-- ── Header ── -->
    <div class="fi-header">
        <?php if ($pesFoto): ?>
            <img src="<?= htmlspecialchars($pesFoto) ?>" class="fi-avatar" alt="Foto">
        <?php else: ?>
            <div class="fi-avatar-initials"><?= $iniciais ?></div>
        <?php endif; ?>

        <div class="fi-info">
            <h2 class="fi-name"><?= htmlspecialchars($pesNome) ?></h2>
            <div class="fi-sub">
                <?php if ($p['PES_CPF_CNPJ']): ?>
                    <span><i class="fa-regular fa-id-card"></i> <?= $h('PES_CPF_CNPJ') ?></span>
                <?php endif; ?>
                <?php if ($p['PES_FONE_CELULAR_1']): ?>
                    <span><i class="fa-solid fa-phone"></i> <?= $h('PES_FONE_CELULAR_1') ?></span>
                <?php endif; ?>
                <?php if ($p['PES_EMAIL']): ?>
                    <span><i class="fa-regular fa-envelope"></i> <?= $h('PES_EMAIL') ?></span>
                <?php endif; ?>
            </div>
            <div class="fi-pills">
                <span class="fi-pill"><i class="fa-solid fa-car"></i> <?= $totalVeiculos ?> veículo<?= $totalVeiculos !== 1 ? 's' : '' ?></span>
                <span class="fi-pill <?= $contratosAtivos > 0 ? 'green' : '' ?>">
                    <i class="fa-solid fa-file-contract"></i>
                    <?= $contratosAtivos ?> contrato<?= $contratosAtivos !== 1 ? 's' : '' ?> ativo<?= $contratosAtivos !== 1 ? 's' : '' ?>
                </span>
                <?php if ($totalSinistros > 0): ?>
                    <span class="fi-pill red"><i class="fa-solid fa-car-burst"></i> <?= $totalSinistros ?> sinistro<?= $totalSinistros !== 1 ? 's' : '' ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="fi-actions">
            <button class="fi-btn-primary btn-editar-associado" data-id="<?= $pesId ?>">
                <i class="fa-solid fa-pen"></i> Editar
            </button>
            <a href="#" class="fi-btn-outline"
               data-toggle="modal" data-target="#modalVeiculo"
               data-nome="<?= htmlspecialchars($pesNome) ?>"
               data-codigo="<?= $pesId ?>"
               data-cpf="<?= htmlspecialchars(preg_replace('/\D/', '', $p['PES_CPF_CNPJ'] ?? '')) ?>">
                <i class="fa-solid fa-car"></i> + Veículo
            </a>
            <a href="<?= APP_URL ?>/associados/" class="fi-btn-back">
                <i class="fa-solid fa-arrow-left"></i> Voltar
            </a>
        </div>
    </div>

    <!-- ── Tabs ── -->
    <div class="fi-tabs">
        <button class="fi-tab active" onclick="fiTab('dados',this)">
            <i class="fa-regular fa-address-card"></i> Dados Pessoais
        </button>
        <button class="fi-tab" onclick="fiTab('veiculos',this)">
            <i class="fa-solid fa-car"></i> Veículos
            <span class="fi-tab-count"><?= $totalVeiculos ?></span>
        </button>
        <button class="fi-tab" onclick="fiTab('contratos',this)">
            <i class="fa-solid fa-file-contract"></i> Contratos
            <span class="fi-tab-count"><?= count($contratos) ?></span>
        </button>
        <button class="fi-tab" data-fi-tab="cobrancas" onclick="fiTab('cobrancas',this)">
            <i class="fa-solid fa-barcode"></i> Cobranças
        </button>
        <button class="fi-tab" onclick="fiTab('sinistros',this)">
            <i class="fa-solid fa-car-burst"></i> Sinistros
            <span class="fi-tab-count"><?= $totalSinistros ?></span>
        </button>
    </div>

    <!-- ══════════════════════════════════════════════════════
         Tab 1 — Dados Pessoais
    ═══════════════════════════════════════════════════════ -->
    <div class="fi-panel active" id="fiPanelDados">
        <div class="fi-card">
            <div class="fi-card-header">
                <div class="fi-card-header-left">
                    <i class="fa-regular fa-address-card"></i>
                    <h3>Identificação</h3>
                </div>
            </div>
            <div class="fi-card-body" style="display:flex; gap:28px; align-items:flex-start;">
                <div style="flex-shrink:0; text-align:center;">
                    <?php if ($pesFoto): ?>
                        <?php
                            $fotoUrl = preg_match('#^https?://#i', $pesFoto)
                                ? $pesFoto
                                : '/' . ltrim(str_replace('\\', '/', $pesFoto), '/');
                        ?>
                        <img src="<?= htmlspecialchars($fotoUrl) ?>"
                             style="width:120px;height:120px;border-radius:10px;object-fit:cover;border:2px solid #dee2f6;display:block;"
                             alt="Foto">
                    <?php else: ?>
                        <div style="width:120px;height:120px;border-radius:10px;background:#eef0ff;display:flex;align-items:center;justify-content:center;font-size:40px;font-weight:800;color:#3b5bdb;">
                            <?= htmlspecialchars($iniciais) ?>
                        </div>
                    <?php endif; ?>
                    <div style="margin-top:8px;font-size:11px;color:#868e96;font-weight:600;letter-spacing:.04em;text-transform:uppercase;">Foto</div>
                </div>
                <div style="flex:1;min-width:0;">
                    <div class="fi-info-grid">
                        <div class="fi-info-item">
                            <div class="fi-info-label">Nome completo</div>
                            <div class="fi-info-value"><?= $h('PES_NOME') ?: '<span class="muted">—</span>' ?></div>
                        </div>
                        <div class="fi-info-item">
                            <div class="fi-info-label">CPF / CNPJ</div>
                            <div class="fi-info-value" style="font-family:monospace;"><?= $h('PES_CPF_CNPJ') ?: '<span class="muted">—</span>' ?></div>
                        </div>
                        <div class="fi-info-item">
                            <div class="fi-info-label">Data de Nascimento</div>
                            <div class="fi-info-value"><?= $fmtDate($p['PES_DATA_NASCIMENTO'] ?? null) ?></div>
                        </div>
                        <div class="fi-info-item">
                            <div class="fi-info-label">Sexo</div>
                            <div class="fi-info-value"><?= $h('PES_SEXO') ?: '<span class="muted">—</span>' ?></div>
                        </div>
                        <div class="fi-info-item">
                            <div class="fi-info-label">Estado Civil</div>
                            <div class="fi-info-value"><?= $h('PES_ESTADO_CIVIL') ?: '<span class="muted">—</span>' ?></div>
                        </div>
                        <div class="fi-info-item">
                            <div class="fi-info-label">Profissão</div>
                            <div class="fi-info-value"><?= $h('PES_PROFISSAO') ?: '<span class="muted">—</span>' ?></div>
                        </div>
                        <div class="fi-info-item">
                            <div class="fi-info-label">RG</div>
                            <div class="fi-info-value"><?= $h('PES_RG') ?: '<span class="muted">—</span>' ?></div>
                        </div>
                        <div class="fi-info-item">
                            <div class="fi-info-label">Órgão Expedidor</div>
                            <div class="fi-info-value"><?= $h('PES_ORG_EXP') ?: '<span class="muted">—</span>' ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="fi-card">
                    <div class="fi-card-header">
                        <div class="fi-card-header-left">
                            <i class="fa-solid fa-phone"></i><h3>Contato</h3>
                        </div>
                    </div>
                    <div class="fi-card-body">
                        <div class="fi-info-grid" style="grid-template-columns:1fr 1fr;">
                            <div class="fi-info-item">
                                <div class="fi-info-label">Celular 1</div>
                                <div class="fi-info-value"><?= $h('PES_FONE_CELULAR_1') ?: '<span class="muted">—</span>' ?></div>
                            </div>
                            <div class="fi-info-item">
                                <div class="fi-info-label">Celular 2</div>
                                <div class="fi-info-value"><?= $h('PES_FONE_CELULAR_2') ?: '<span class="muted">—</span>' ?></div>
                            </div>
                            <div class="fi-info-item">
                                <div class="fi-info-label">Telefone Fixo</div>
                                <div class="fi-info-value"><?= $h('PES_FONE_FIXO') ?: '<span class="muted">—</span>' ?></div>
                            </div>
                            <div class="fi-info-item">
                                <div class="fi-info-label">E-mail</div>
                                <div class="fi-info-value" style="font-size:12.5px;"><?= $h('PES_EMAIL') ?: '<span class="muted">—</span>' ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="fi-card">
                    <div class="fi-card-header">
                        <div class="fi-card-header-left">
                            <i class="fa-solid fa-id-badge"></i><h3>CNH</h3>
                        </div>
                    </div>
                    <div class="fi-card-body">
                        <div class="fi-info-grid" style="grid-template-columns:1fr 1fr 1fr;">
                            <div class="fi-info-item">
                                <div class="fi-info-label">Nº CNH</div>
                                <div class="fi-info-value"><?= $h('PES_NUM_CNH') ?: '<span class="muted">—</span>' ?></div>
                            </div>
                            <div class="fi-info-item">
                                <div class="fi-info-label">Categoria</div>
                                <div class="fi-info-value"><?= $h('PES_CATEGORIA_CNH') ?: '<span class="muted">—</span>' ?></div>
                            </div>
                            <div class="fi-info-item">
                                <div class="fi-info-label">Validade</div>
                                <div class="fi-info-value"><?= $fmtDate($p['PES_VALIDADE'] ?? null) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="fi-card">
            <div class="fi-card-header">
                <div class="fi-card-header-left">
                    <i class="fa-solid fa-location-dot"></i><h3>Endereço</h3>
                </div>
            </div>
            <div class="fi-card-body">
                <div class="fi-info-grid">
                    <div class="fi-info-item">
                        <div class="fi-info-label">CEP</div>
                        <div class="fi-info-value"><?= $h('PES_CEP') ?: '<span class="muted">—</span>' ?></div>
                    </div>
                    <div class="fi-info-item" style="grid-column: span 2;">
                        <div class="fi-info-label">Logradouro</div>
                        <div class="fi-info-value"><?= $h('PES_ENDERECO') ?: '<span class="muted">—</span>' ?></div>
                    </div>
                    <div class="fi-info-item">
                        <div class="fi-info-label">Número</div>
                        <div class="fi-info-value"><?= $h('PES_NUMERO') ?: '<span class="muted">—</span>' ?></div>
                    </div>
                    <div class="fi-info-item">
                        <div class="fi-info-label">Complemento</div>
                        <div class="fi-info-value"><?= $h('PES_COMPLEMENTO') ?: '<span class="muted">—</span>' ?></div>
                    </div>
                    <div class="fi-info-item">
                        <div class="fi-info-label">Bairro</div>
                        <div class="fi-info-value"><?= $h('PES_BAIRRO') ?: '<span class="muted">—</span>' ?></div>
                    </div>
                    <div class="fi-info-item">
                        <div class="fi-info-label">Cidade</div>
                        <div class="fi-info-value"><?= $h('PES_CIDADE') ?: '<span class="muted">—</span>' ?></div>
                    </div>
                    <div class="fi-info-item">
                        <div class="fi-info-label">UF</div>
                        <div class="fi-info-value"><?= $h('PES_UF') ?: '<span class="muted">—</span>' ?></div>
                    </div>
                    <div class="fi-info-item">
                        <div class="fi-info-label">Ponto de Referência</div>
                        <div class="fi-info-value"><?= $h('PES_PONTO_REFERENCIA') ?: '<span class="muted">—</span>' ?></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($p['PES_OBSERVACAO'])): ?>
        <div class="fi-card">
            <div class="fi-card-header">
                <div class="fi-card-header-left">
                    <i class="fa-regular fa-note-sticky"></i><h3>Observações</h3>
                </div>
            </div>
            <div class="fi-card-body">
                <div class="fi-obs-box"><?= htmlspecialchars($p['PES_OBSERVACAO']) ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ══════════════════════════════════════════════════════
         Tab 2 — Veículos
    ═══════════════════════════════════════════════════════ -->
    <div class="fi-panel" id="fiPanelVeiculos">
        <div class="fi-card">
            <div class="fi-card-header">
                <div class="fi-card-header-left">
                    <i class="fa-solid fa-car"></i>
                    <h3>Veículos do Associado</h3>
                </div>
                <a href="#" class="fi-add-btn"
                   data-toggle="modal" data-target="#modalVeiculo"
                   data-nome="<?= htmlspecialchars($pesNome) ?>"
                   data-codigo="<?= $pesId ?>"
                   data-cpf="<?= htmlspecialchars(preg_replace('/\D/', '', $p['PES_CPF_CNPJ'] ?? '')) ?>">
                    <i class="fa-solid fa-plus"></i> Adicionar Veículo
                </a>
            </div>
            <div class="fi-card-body">
                <?php if (empty($veiculos)): ?>
                    <div class="fi-placeholder">
                        <i class="fa-solid fa-car-side"></i>
                        <h4>Nenhum veículo cadastrado</h4>
                        <p>Este associado ainda não possui veículos. Clique em "Adicionar Veículo" para começar.</p>
                    </div>
                <?php else: ?>
                    <div class="fi-vei-grid">
                        <?php foreach ($veiculos as $v):
                            $ctrStatus = strtoupper($v['CTR_STATUS'] ?? '');
                            $badgeCls  = $ctrStatus === 'A' ? 'fi-badge-ativo' : ($ctrStatus === 'C' ? 'fi-badge-cancelado' : 'fi-badge-sem');
                            $badgeTxt  = $ctrStatus === 'A' ? 'Ativo' : ($ctrStatus === 'C' ? 'Cancelado' : 'Sem contrato');
                            $tipoIcon  = strtolower($v['VEI_TIPO'] ?? '') === 'motos' ? 'fa-motorcycle' : (strtolower($v['VEI_TIPO'] ?? '') === 'caminhoes' ? 'fa-truck' : 'fa-car-side');
                        ?>
                        <div class="fi-vei-card">
                            <div class="fi-vei-icon">
                                <i class="fa-solid <?= $tipoIcon ?>"></i>
                            </div>
                            <div class="fi-vei-body">
                                <div class="fi-vei-placa"><?= $hv($v['VEI_PLACA']) ?></div>
                                <div class="fi-vei-modelo">
                                    <?= $hv(trim(($v['VEI_MARCA'] ?? '') . ' ' . ($v['VEI_MODELO'] ?? ''))) ?>
                                    <?php if ($v['VEI_ANO_FABRICACAO']): ?>
                                        &nbsp;<?= $hv($v['VEI_ANO_FABRICACAO']) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="fi-vei-meta">
                                    <?php if ($v['VEI_COMBUSTIVEL']): ?>
                                        <span class="fi-vei-tag"><?= $hv($v['VEI_COMBUSTIVEL']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($v['GRU_DESCRICAO']): ?>
                                        <span class="fi-vei-tag"><?= $hv($v['GRU_DESCRICAO']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($v['CTR_VALOR_MENSALIDADE']): ?>
                                        <span class="fi-vei-tag"><?= $money($v['CTR_VALOR_MENSALIDADE']) ?>/mês</span>
                                    <?php endif; ?>
                                </div>
                                <div class="fi-vei-footer">
                                    <span class="fi-badge <?= $badgeCls ?>">
                                        <i class="fa-solid fa-circle" style="font-size:7px;"></i> <?= $badgeTxt ?>
                                    </span>
                                    <div class="fi-vei-btns">
                                        <a href="#" class="fi-action-btn btn-editar-veiculo"
                                           title="Editar veículo"
                                           data-veiculo-id="<?= (int)$v['VEI_CODIGO_PK'] ?>"
                                           data-contrato-id="<?= (int)($v['CTR_CODIGO_PK'] ?? 0) ?>">
                                            <i class="fa-solid fa-pen"></i>
                                        </a>
                                        <?php if ($v['CTR_CODIGO_PK']): ?>
                                            <a href="#" class="fi-action-btn" title="Ver contrato"
                                               onclick="fiTab('contratos', document.querySelectorAll('.fi-tab')[2]); return false;">
                                                <i class="fa-solid fa-file-contract"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════
         Tab 3 — Contratos
    ═══════════════════════════════════════════════════════ -->
    <div class="fi-panel" id="fiPanelContratos">
        <div class="fi-card">
            <div class="fi-card-header">
                <div class="fi-card-header-left">
                    <i class="fa-solid fa-file-contract"></i>
                    <h3>Histórico de Contratos</h3>
                </div>
            </div>
            <div class="fi-card-body" style="padding:0;">
                <?php if (empty($contratos)): ?>
                    <div class="fi-placeholder" style="padding:48px;">
                        <i class="fa-solid fa-file-circle-xmark"></i>
                        <h4>Nenhum contrato encontrado</h4>
                        <p>Adicione um veículo com plano para gerar o primeiro contrato.</p>
                    </div>
                <?php else: ?>
                    <div class="fi-table-wrap">
                        <table class="fi-table">
                            <thead>
                                <tr>
                                    <th>#CTR</th>
                                    <th>Placa</th>
                                    <th>Plano</th>
                                    <th>Combo</th>
                                    <th>Mensal.</th>
                                    <th>Adesão</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Cancelamento</th>
                                    <th style="text-align:center;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($contratos as $c):
                                    $cStatus = strtoupper($c['CTR_STATUS'] ?? '');
                                    $cBadge  = $cStatus === 'A' ? 'fi-badge-ativo' : ($cStatus === 'C' ? 'fi-badge-cancelado' : 'fi-badge-sem');
                                    $cLabel  = $cStatus === 'A' ? 'Ativo' : ($cStatus === 'C' ? 'Cancelado' : '—');
                                ?>
                                <tr>
                                    <td style="font-weight:700;color:#3b5bdb;">#<?= (int)$c['CTR_CODIGO_PK'] ?></td>
                                    <td style="font-family:monospace;font-weight:700;"><?= $hv($c['VEI_PLACA']) ?></td>
                                    <td><?= $hv($c['GRU_DESCRICAO']) ?: '<span style="color:#adb5bd;">—</span>' ?></td>
                                    <td><?= $hv($c['COM_DESCRICAO']) ?: '<span style="color:#adb5bd;">—</span>' ?></td>
                                    <td><?= $money($c['CTR_VALOR_MENSALIDADE']) ?></td>
                                    <td><?= $money($c['CTR_VALOR_ADESAO']) ?></td>
                                    <td style="font-weight:700;"><?= $money($c['CTR_VALOR_TOTAL']) ?></td>
                                    <td>
                                        <span class="fi-badge <?= $cBadge ?>">
                                            <i class="fa-solid fa-circle" style="font-size:7px;"></i> <?= $cLabel ?>
                                        </span>
                                    </td>
                                    <td style="font-size:12px;color:#868e96;">
                                        <?php if ($cStatus === 'C' && !empty($c['CTR_DATA_CANCELAMENTO'])): ?>
                                            <?= $fmtDate($c['CTR_DATA_CANCELAMENTO']) ?>
                                            <?php if (!empty($c['CTR_MOTIVO_CANCELAMENTO'])): ?>
                                                <br><span title="<?= $hv($c['CTR_MOTIVO_CANCELAMENTO']) ?>">
                                                    <?= $hv(mb_strimwidth($c['CTR_MOTIVO_CANCELAMENTO'], 0, 30, '…')) ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;white-space:nowrap;">
                                        <div style="display:flex;gap:5px;justify-content:center;">
                                            <?php if ($cStatus === 'A'): ?>
                                                <button class="fi-action-btn fi-btn-cancelar" title="Cancelar contrato"
                                                        data-contrato-id="<?= (int)$c['CTR_CODIGO_PK'] ?>"
                                                        data-placa="<?= $hv($c['VEI_PLACA']) ?>">
                                                    <i class="fa-solid fa-ban"></i>
                                                </button>
                                                <button class="fi-action-btn fi-btn-transferir" title="Transferir veículo"
                                                        data-contrato-id="<?= (int)$c['CTR_CODIGO_PK'] ?>"
                                                        data-veiculo-id="<?= (int)$c['VEI_CODIGO_FK'] ?>"
                                                        data-placa="<?= $hv($c['VEI_PLACA']) ?>">
                                                    <i class="fa-solid fa-right-left"></i>
                                                </button>
                                            <?php elseif ($cStatus === 'C'): ?>
                                                <button class="fi-action-btn fi-btn-reativar" title="Reativar contrato"
                                                        data-contrato-id="<?= (int)$c['CTR_CODIGO_PK'] ?>"
                                                        data-placa="<?= $hv($c['VEI_PLACA']) ?>">
                                                    <i class="fa-solid fa-rotate-left"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button class="fi-action-btn fi-btn-boletos" title="Ver cobranças"
                                                    data-veiculo-id="<?= (int)$c['VEI_CODIGO_FK'] ?>"
                                                    data-placa="<?= $hv($c['VEI_PLACA']) ?>">
                                                <i class="fa-solid fa-barcode"></i>
                                            </button>
                                            <button class="fi-action-btn fi-btn-imprimir-doc" title="Imprimir documentos"
                                                    data-contrato-id="<?= (int)$c['CTR_CODIGO_PK'] ?>"
                                                    style="color:#6741d9;">
                                                <i class="fa-solid fa-print"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════
         Tab 4 — Cobranças
    ═══════════════════════════════════════════════════════ -->
    <div class="fi-panel" id="fiPanelCobrancas">

        <!-- Sumário de totais -->
        <div id="cobSumario" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px;margin-bottom:18px;"></div>

        <div class="fi-card">
            <div class="fi-card-header">
                <div class="fi-card-header-left">
                    <i class="fa-solid fa-barcode"></i>
                    <h3>Cobranças e Boletos</h3>
                </div>
                <div style="display:flex;align-items:center;gap:10px;">
                    <span id="cobFilterTag" style="display:none;background:#e8edff;color:#3b5bdb;font-size:12px;font-weight:700;padding:4px 12px;border-radius:99px;display:none;align-items:center;gap:6px;">
                        <i class="fa-solid fa-filter"></i>
                        <span id="cobFilterLabel"></span>
                        <button onclick="fiCobCarregar(0,'')" style="background:none;border:none;cursor:pointer;color:#3b5bdb;padding:0;margin-left:4px;font-size:13px;" title="Remover filtro">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </span>
                </div>
            </div>
            <div id="cobContent" style="min-height:120px;">
                <div style="padding:40px;text-align:center;color:#adb5bd;">
                    <i class="fa-solid fa-spinner fa-spin" style="font-size:22px;"></i>
                    <div style="margin-top:8px;font-size:13px;">Clique na aba para carregar...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════
         Tab 5 — Sinistros
    ═══════════════════════════════════════════════════════ -->
    <div class="fi-panel" id="fiPanelSinistros">
        <div class="fi-card">
            <div class="fi-card-header">
                <div class="fi-card-header-left">
                    <i class="fa-solid fa-car-burst"></i>
                    <h3>Sinistros do Associado</h3>
                </div>
                <button type="button" id="btnRegistrarSinistro" class="fi-add-btn">
                    <i class="fa-solid fa-plus"></i> Registrar Sinistro
                </button>
            </div>
            <div class="fi-card-body" style="padding:0;">
                <?php if (empty($sinistros)): ?>
                    <div class="fi-placeholder" style="padding:48px;">
                        <i class="fa-solid fa-shield-check"></i>
                        <h4>Nenhum sinistro registrado</h4>
                        <p>Este associado não possui histórico de sinistros.</p>
                    </div>
                <?php else: ?>
                    <div class="fi-table-wrap">
                        <table class="fi-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Data Ocorrência</th>
                                    <th>Tipo</th>
                                    <th>Placa</th>
                                    <th>Condutor</th>
                                    <th>Nº BO</th>
                                    <th>Valor FIPE</th>
                                    <th>Franquia</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sinistros as $s):
                                    $sStatus = strtoupper($s['SIN_STATUS'] ?? 'ABERTO');
                                    $sBadge  = match($sStatus) {
                                        'ENCERRADO' => 'fi-badge-encerrado',
                                        'CANCELADO' => 'fi-badge-cancelado',
                                        default     => 'fi-badge-aberto',
                                    };
                                    $sLabel = match($sStatus) {
                                        'ENCERRADO' => 'Encerrado',
                                        'CANCELADO' => 'Cancelado',
                                        default     => 'Aberto',
                                    };
                                ?>
                                <tr>
                                    <td style="color:#3b5bdb;font-weight:700;">#<?= (int)$s['SIN_CODIGO_PK'] ?></td>
                                    <td><?= $fmtDate($s['SIN_DATA_OCORRENCIA'] ?? null) ?></td>
                                    <td><span class="fi-sin-tipo"><?= $hv($s['SIN_TIPO_OCORRENCIA']) ?></span></td>
                                    <td style="font-family:monospace;font-weight:700;"><?= $hv($s['VEI_PLACA']) ?></td>
                                    <td><?= $hv($s['SIN_NOME_CONDUTOR']) ?: '<span style="color:#adb5bd;">—</span>' ?></td>
                                    <td style="font-family:monospace;"><?= $hv($s['SIN_NUM_BO']) ?: '—' ?></td>
                                    <td><?= $money($s['SIN_VALOR_FIPE'] ?? null) ?></td>
                                    <td><?= $money($s['SIN_VALOR_FRANQUIA'] ?? null) ?></td>
                                    <td>
                                        <span class="fi-badge <?= $sBadge ?>">
                                            <i class="fa-solid fa-circle" style="font-size:7px;"></i> <?= $sLabel ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div><!-- /vt-page -->

<?php
// ── Modais reutilizados ──────────────────────────────────────────────────
require_once __DIR__ . '/modal_cad_associado.php';
require_once __DIR__ . '/modal_cad_veiculo.php';
require_once __DIR__ . '/../sinistros/modal_sin.php';
?>

<!-- ═══════════════════════════════════════════
     Modal: Cancelar Contrato
════════════════════════════════════════════ -->
<div class="modal fade" id="modalCancelar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
        <div class="modal-content" style="border-radius:16px;overflow:hidden;">
            <div class="modal-header" style="background:linear-gradient(135deg,#e03131,#c92a2a);color:#fff;border:none;">
                <h5 class="modal-title"><i class="fa-solid fa-ban mr-2"></i> Cancelar Contrato</h5>
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:.8;"><span>&times;</span></button>
            </div>
            <form id="formCancelar">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <input type="hidden" name="acao" value="cancelar">
                <input type="hidden" name="contrato_id" id="cancelContratoId">
                <div class="modal-body" style="padding:24px;">
                    <div id="cancelInfo" style="background:#fff5f5;border:1px solid #ffc9c9;border-radius:10px;padding:12px 16px;margin-bottom:18px;font-size:13.5px;color:#c92a2a;">
                        <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                        Contrato do veículo <strong id="cancelPlaca"></strong> será cancelado.
                    </div>
                    <div class="mb-3">
                        <label style="font-size:12px;font-weight:700;color:#495057;display:block;margin-bottom:6px;">
                            Motivo do cancelamento <span style="color:#c92a2a">*</span>
                        </label>
                        <textarea name="motivo" id="cancelMotivo" rows="3" required
                                  style="width:100%;border:1.5px solid #dbe2ea;border-radius:9px;padding:10px 12px;font-size:13.5px;resize:vertical;font-family:inherit;"
                                  placeholder="Descreva o motivo do cancelamento..."></textarea>
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:700;color:#495057;display:block;margin-bottom:6px;">Data do cancelamento</label>
                        <input type="date" name="data_evento" value="<?= date('Y-m-d') ?>"
                               style="width:100%;height:40px;border:1.5px solid #dbe2ea;border-radius:9px;padding:0 12px;font-size:13.5px;">
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #e9ecef;padding:14px 20px;justify-content:flex-end;gap:8px;">
                    <button type="button" data-dismiss="modal" style="height:38px;padding:0 18px;border-radius:9px;background:#f1f3f5;color:#495057;border:none;font-weight:600;cursor:pointer;">Cancelar</button>
                    <button type="submit" id="btnConfCancelar" style="height:38px;padding:0 20px;border-radius:9px;background:#e03131;color:#fff;border:none;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fa-solid fa-ban"></i> <span id="txtBtnCancelar">Confirmar Cancelamento</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     Modal: Reativar Contrato
════════════════════════════════════════════ -->
<div class="modal fade" id="modalReativar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
        <div class="modal-content" style="border-radius:16px;overflow:hidden;">
            <div class="modal-header" style="background:linear-gradient(135deg,#2f9e44,#2b8a3e);color:#fff;border:none;">
                <h5 class="modal-title"><i class="fa-solid fa-rotate-left mr-2"></i> Reativar Contrato</h5>
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:.8;"><span>&times;</span></button>
            </div>
            <form id="formReativar">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <input type="hidden" name="acao" value="reativar">
                <input type="hidden" name="contrato_id" id="reativarContratoId">
                <div class="modal-body" style="padding:24px;">
                    <div style="background:#ebfbee;border:1px solid #b2f2bb;border-radius:10px;padding:12px 16px;margin-bottom:18px;font-size:13.5px;color:#2f9e44;">
                        <i class="fa-solid fa-circle-check mr-1"></i>
                        Reativar contrato do veículo <strong id="reativarPlaca"></strong>.
                    </div>
                    <div class="mb-3">
                        <label style="font-size:12px;font-weight:700;color:#495057;display:block;margin-bottom:6px;">
                            Motivo da reativação <span style="color:#c92a2a">*</span>
                        </label>
                        <textarea name="motivo" id="reativarMotivo" rows="3" required
                                  style="width:100%;border:1.5px solid #dbe2ea;border-radius:9px;padding:10px 12px;font-size:13.5px;resize:vertical;font-family:inherit;"
                                  placeholder="Descreva o motivo da reativação..."></textarea>
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:700;color:#495057;display:block;margin-bottom:6px;">Data da reativação</label>
                        <input type="date" name="data_evento" value="<?= date('Y-m-d') ?>"
                               style="width:100%;height:40px;border:1.5px solid #dbe2ea;border-radius:9px;padding:0 12px;font-size:13.5px;">
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #e9ecef;padding:14px 20px;justify-content:flex-end;gap:8px;">
                    <button type="button" data-dismiss="modal" style="height:38px;padding:0 18px;border-radius:9px;background:#f1f3f5;color:#495057;border:none;font-weight:600;cursor:pointer;">Cancelar</button>
                    <button type="submit" id="btnConfReativar" style="height:38px;padding:0 20px;border-radius:9px;background:#2f9e44;color:#fff;border:none;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fa-solid fa-rotate-left"></i> <span id="txtBtnReativar">Confirmar Reativação</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     Modal: Transferir Veículo
════════════════════════════════════════════ -->
<div class="modal fade" id="modalTransferir" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:560px;">
        <div class="modal-content" style="border-radius:16px;overflow:hidden;">
            <div class="modal-header" style="background:linear-gradient(135deg,#3b5bdb,#2f4abf);color:#fff;border:none;">
                <h5 class="modal-title"><i class="fa-solid fa-right-left mr-2"></i> Transferir Veículo</h5>
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:.8;"><span>&times;</span></button>
            </div>
            <form id="formTransferir">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <input type="hidden" name="acao" value="transferir">
                <input type="hidden" name="contrato_id" id="transContratoId">
                <input type="hidden" name="veiculo_id"  id="transVeiculoId">
                <input type="hidden" name="destinatario_id" id="transDestinatarioId">
                <div class="modal-body" style="padding:24px;">
                    <div style="background:#f0f3ff;border:1px solid #bac8ff;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:13.5px;color:#3b5bdb;">
                        <i class="fa-solid fa-circle-info mr-1"></i>
                        Transferência do veículo <strong id="transPlaca"></strong>:
                        o contrato atual será <strong>cancelado</strong> e um novo contrato será criado para o associado destino.
                    </div>

                    <!-- Busca de associado destino -->
                    <div class="mb-3">
                        <label style="font-size:12px;font-weight:700;color:#495057;display:block;margin-bottom:6px;">
                            Buscar associado destino <span style="color:#c92a2a">*</span>
                        </label>
                        <div style="position:relative;">
                            <input type="text" id="transBusca" autocomplete="off"
                                   placeholder="Nome, CPF, ID ou celular..."
                                   style="width:100%;height:42px;border:1.5px solid #dbe2ea;border-radius:9px;padding:0 12px 0 38px;font-size:13.5px;">
                            <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#adb5bd;font-size:13px;pointer-events:none;"></i>
                        </div>
                        <!-- Dropdown de resultados -->
                        <div id="transBuscaResults" style="display:none;border:1.5px solid #dbe2ea;border-radius:9px;background:#fff;margin-top:4px;max-height:200px;overflow-y:auto;box-shadow:0 4px 16px rgba(0,0,0,.1);z-index:9999;position:relative;"></div>
                    </div>

                    <!-- Card do associado selecionado -->
                    <div id="transDestinatarioCard" style="display:none;background:#f8f9fb;border:1.5px solid #c5d0ff;border-radius:10px;padding:14px 16px;margin-bottom:18px;">
                        <div style="display:flex;align-items:center;gap:12px;">
                            <div style="width:40px;height:40px;border-radius:50%;background:#3b5bdb;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:14px;flex-shrink:0;" id="transDestAvatar"></div>
                            <div>
                                <div style="font-size:14px;font-weight:700;color:#1a1d2e;" id="transDestNome"></div>
                                <div style="font-size:12px;color:#868e96;" id="transDestInfo"></div>
                            </div>
                            <button type="button" onclick="fiTransLimpar()" style="margin-left:auto;background:none;border:none;cursor:pointer;color:#868e96;font-size:16px;" title="Alterar seleção">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label style="font-size:12px;font-weight:700;color:#495057;display:block;margin-bottom:6px;">
                            Motivo da transferência <span style="color:#c92a2a">*</span>
                        </label>
                        <textarea name="motivo" id="transMotivo" rows="2" required
                                  style="width:100%;border:1.5px solid #dbe2ea;border-radius:9px;padding:10px 12px;font-size:13.5px;resize:vertical;font-family:inherit;"
                                  placeholder="Ex.: Venda do veículo, transferência familiar..."></textarea>
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:700;color:#495057;display:block;margin-bottom:6px;">Data da transferência</label>
                        <input type="date" name="data_evento" value="<?= date('Y-m-d') ?>"
                               style="width:100%;height:40px;border:1.5px solid #dbe2ea;border-radius:9px;padding:0 12px;font-size:13.5px;">
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #e9ecef;padding:14px 20px;justify-content:flex-end;gap:8px;">
                    <button type="button" data-dismiss="modal" style="height:38px;padding:0 18px;border-radius:9px;background:#f1f3f5;color:#495057;border:none;font-weight:600;cursor:pointer;">Cancelar</button>
                    <button type="submit" id="btnConfTransferir" style="height:38px;padding:0 20px;border-radius:9px;background:#3b5bdb;color:#fff;border:none;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fa-solid fa-right-left"></i> <span id="txtBtnTransferir">Confirmar Transferência</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── JS: Tabs ── -->
<script>
function fiTab(id, btn) {
    document.querySelectorAll('.fi-panel').forEach(function(p) { p.classList.remove('active'); });
    document.querySelectorAll('.fi-tab').forEach(function(b)  { b.classList.remove('active'); });
    document.getElementById('fiPanel' + id.charAt(0).toUpperCase() + id.slice(1)).classList.add('active');
    btn.classList.add('active');
}
</script>

<!-- ── JS: Modal veículo — aplicar associado ── -->
<script>
(function () {
    const ensureSelectValue = window.ensureSelectValue || function ($sel, value) {
        if (!value && value !== 0) return;
        $sel.prop('disabled', false);
        var valStr = String(value);
        var $opt = $sel.find('option').filter(function () { return $(this).val() === valStr || $(this).text() === valStr; });
        if ($opt.length) $sel.val($opt.val());
        else $sel.append(new Option(valStr, valStr, true, true));
        $sel.trigger('change');
    };
    window.ensureSelectValue = ensureSelectValue;

    function toDigits(v) { return String(v == null ? '' : v).replace(/\D/g, ''); }
    function aplicarAssociado($modal, nome, codigo, cpf) {
        var $titulo  = $modal.find('#tituloModalVeiculo');
        var $hidden  = $modal.find('#codigo_associado');
        var $cpfProp = $modal.find('#cpf_proprietario');
        var $nomProp = $modal.find('#nome_proprietario');
        if (codigo != null && String(codigo).trim() !== '') {
            var label = nome ? ('Novo Veículo para: ' + nome + ' - ID: ' + codigo) : ('Novo Veículo — Associado #' + codigo);
            $titulo.text(label);
            $hidden.val(codigo);
            $modal.attr({ 'data-assoc-nome': nome || '', 'data-assoc-codigo': codigo });
            if (cpf != null) $modal.attr('data-assoc-cpf', toDigits(cpf));
            if ($cpfProp.length) $cpfProp.val(toDigits(cpf));
            if ($nomProp.length) $nomProp.val(nome || '');
        } else {
            $titulo.text('Novo Veículo');
            $hidden.val('');
            $modal.removeAttr('data-assoc-nome data-assoc-codigo data-assoc-cpf');
            if ($cpfProp.length) $cpfProp.val('');
            if ($nomProp.length) $nomProp.val('');
        }
    }
    $(document).on('click', '[data-target="#modalVeiculo"], [href="#modalVeiculo"]', function () {
        aplicarAssociado($('#modalVeiculo'), $(this).attr('data-nome'), $(this).attr('data-codigo'), $(this).attr('data-cpf'));
    });
    $(document).on('shown.bs.modal', '#modalVeiculo', function (e) {
        if (!e.relatedTarget) return;
        var $m = $(this), $t = $(e.relatedTarget);
        var codigo = $t.attr('data-codigo');
        if (codigo != null && String(codigo).trim() !== '') {
            aplicarAssociado($m, $t.attr('data-nome'), codigo, $t.attr('data-cpf'));
        }
    });
    $(document).on('hidden.bs.modal', '#modalVeiculo', function () {
        var $m = $(this);
        // Reseta todos os campos críticos para o próximo uso do modal
        $m.find('#acao').val('cadastrar');
        $m.find('#codigo_veiculo').val('');
        $m.find('#codigo_contrato').val('');
        $m.find('#placa').val('').prop('readonly', false);
        $m.find('#tituloModalVeiculo').text('Novo Veículo');
        aplicarAssociado($m, null, null, null);
    });
    $(document).on('input', '#cpf_proprietario', function () { this.value = this.value.replace(/\D/g, ''); });
})();
</script>

<!-- ── JS: Modal veículo — submit ── -->
<script>
(function () {
    var form = document.getElementById('formVeiculo');
    if (!form) return;
    var moneyToDecimal = function (v) {
        if (v == null) return '';
        var s = String(v).trim().replace(/[R$\s ]/g, '');
        if (!s) return '';
        var lc = s.lastIndexOf(','), ld = s.lastIndexOf('.');
        if (lc !== -1 || ld !== -1) {
            if (lc > ld) s = s.replace(/\./g, '').replace(',', '.');
            else s = s.replace(/,/g, '');
        } else { s = s.replace(/\D/g, ''); }
        return s;
    };
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;
        var fd = new FormData(form);
        if (fd.get('valor'))         fd.set('valor',         moneyToDecimal(fd.get('valor')));
        if (fd.get('valorCobertura')) fd.set('valorCobertura', moneyToDecimal(fd.get('valorCobertura')));
        fetch(form.action, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (json.success) {
                    Swal.fire({ icon: 'success', title: fd.get('acao') === 'atualizar' ? 'Veículo atualizado!' : 'Veículo cadastrado!', text: json.message || 'Operação concluída.' }).then(function () { location.reload(); });
                } else {
                    Swal.fire({ icon: 'error', title: 'Erro', text: json.message || 'Não foi possível salvar.' });
                }
            })
            .catch(function () { Swal.fire({ icon: 'error', title: 'Erro', text: 'Falha ao comunicar com o servidor.' }); })
            .finally(function () { if (submitBtn) submitBtn.disabled = false; });
    });
})();
</script>

<!-- ── JS: Editar associado ── -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    var ASSOC_ACTION = '<?= ACTION_URL ?>/pessoas.php';

    $(document).on('click', '.btn-editar-associado', function (e) {
        e.preventDefault();
        var id = $(this).data('id');
        if (!id) return;
        fetch(ASSOC_ACTION + '?acao=obter&id=' + encodeURIComponent(id) + '&_=' + Date.now())
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j.success) { Swal.fire('Erro', j.message || 'Não foi possível carregar.', 'error'); return; }
                var p  = j.data || j;
                var $f = $('#formCadastroPessoa');
                $f.find('[name="tipo"]').val(p.PES_TIPO || '');
                $f.find('[name="nome"]').val(p.PES_NOME || '');
                $('#cpf').val(p.PES_CPF_CNPJ || '');
                $('#celular1').val(p.PES_FONE_CELULAR_1 || '');
                $('#celular2').val(p.PES_FONE_CELULAR_2 || '');
                $('#telefone').val(p.PES_FONE_FIXO || '');
                $f.find('[name="data_nascimento"]').val(p.PES_DATA_NASCIMENTO || '');
                $f.find('[name="rg"]').val(p.PES_RG || '');
                $f.find('[name="orgao"]').val(p.PES_ORG_EXP || '');
                $f.find('[name="cnh"]').val(p.PES_NUM_CNH || '');
                $f.find('[name="categoria"]').val(p.PES_CATEGORIA_CNH || '');
                $f.find('[name="validade"]').val(p.PES_VALIDADE || '');
                $f.find('[name="estado_civil"]').val(p.PES_ESTADO_CIVIL || '');
                $f.find('[name="sexo"]').val(p.PES_SEXO || '');
                $('#email').val((p.PES_EMAIL || '').toLowerCase());
                $f.find('[name="profissao"]').val(p.PES_PROFISSAO || '');
                $('#cep').val(p.PES_CEP || '');
                $('#endereco').val(p.PES_ENDERECO || '');
                $f.find('[name="numero"]').val(p.PES_NUMERO || '');
                $f.find('[name="complemento"]').val(p.PES_COMPLEMENTO || '');
                $('#bairro').val(p.PES_BAIRRO || '');
                $f.find('[name="referencia"]').val(p.PES_PONTO_REFERENCIA || '');
                $('#observacao').val(p.PES_OBSERVACAO || '');
                $('#uf').val(p.PES_UF || '').trigger('change');
                if (p.PES_CIDADE) {
                    var $cid = $('#cidade');
                    if (!$cid.find('option[value="' + p.PES_CIDADE + '"]').length)
                        $cid.append(new Option(p.PES_CIDADE, p.PES_CIDADE, true, true));
                    else $cid.val(p.PES_CIDADE);
                }
                if (p.PES_FOTO) {
                    var url = /^https?:\/\//i.test(p.PES_FOTO) ? p.PES_FOTO : window.location.origin + (p.PES_FOTO.startsWith('/') ? '' : '/') + p.PES_FOTO;
                    $('#fotoPreview').attr('src', url + '?v=' + Date.now());
                }
                document.getElementById('pessoaId').value              = id;
                document.getElementById('pessoaAcao').value             = 'editar';
                document.getElementById('modalAssocTitulo').textContent  = 'Editar Associado — ' + (p.PES_NOME || '');
                document.getElementById('modalAssocIcon').className      = 'fa fa-user-pen';
                $('#CadastrarCliente').modal('show');
            })
            .catch(function () { Swal.fire('Erro', 'Falha ao comunicar com o servidor.', 'error'); });
    });
});
</script>

<!-- ── JS: Editar veículo ── -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    var VEI_ACTION = '<?= ACTION_URL ?>/veiculos.php';

    $(document).on('click', '.btn-editar-veiculo', function (e) {
        e.preventDefault();
        var $btn       = $(this);
        var veiculoId  = $btn.data('veiculo-id')  || '';
        var contratoId = $btn.data('contrato-id') || '';
        if (!veiculoId && !contratoId) return;
        var url = contratoId
            ? VEI_ACTION + '?acao=obter&contrato_id=' + encodeURIComponent(contratoId) + '&_=' + Date.now()
            : VEI_ACTION + '?acao=obter&id=' + encodeURIComponent(veiculoId) + '&_=' + Date.now();
        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j.success) { Swal.fire('Erro', j.message || 'Não foi possível carregar.', 'error'); return; }
                var v        = j.veiculo      || {};
                var vis      = j.vistoria     || {};
                var contr    = j.contrato     || {};
                var visItens = j.vistoria_itens || [];
                var imgs     = j.imagens      || [];
                var $m = $('#modalVeiculo');
                // Modo editar
                $m.find('#acao').val('atualizar');
                $m.find('#codigo_veiculo').val(v.VEI_CODIGO_PK || '');
                $m.find('#codigo_contrato').val(contr.CTR_CODIGO_PK || '');
                $m.find('#codigo_associado').val(v.PES_CODIGO_FK || '');
                $('#tituloModalVeiculo').text('Editar Veículo: ' + (v.VEI_PLACA || ''));
                // Campos veículo
                $('#placa').val(v.VEI_PLACA || '').prop('readonly', true);
                $('#chassi').val(v.VEI_CHASSI || '');
                $('#renavam').val(v.VEI_RENAVAM || '');
                $('#ano').val(v.VEI_ANO_FABRICACAO || '');
                $('#anoModelo').val(v.VEI_ANO_MODELO || '');
                $('#combustivel').val(v.VEI_COMBUSTIVEL || '');
                $('#codigoFipe').val(v.VEI_CODIGO_FIPE || '');
                $('#cpf_proprietario').val(v.VEI_CPF_CNPJ_PROPRIETARIO || '');
                $('#nome_proprietario').val(v.VEI_NOME_PROPRIETARIO || '');
                // Preenche tipoVeiculo diretamente (sem trigger de change, o cascade é feito abaixo)
                $('#tipoVeiculo').prop('disabled', false).val(v.VEI_TIPO || '');
                // Mantém marca/modelo nos campos hidden (garantia para o save independente do FIPE)
                $('#marca').val(v.VEI_MARCA || '');
                $('#modelo').val(v.VEI_MODELO || '');
                // Carrega marcas/modelos da API FIPE e pré-seleciona os valores do banco
                if (window.fipeCarregarParaEdicao) {
                    window.fipeCarregarParaEdicao(
                        v.VEI_TIPO || '',
                        v.VEI_MARCA || '',
                        v.VEI_MODELO || '',
                        v.VEI_FIPE_MARCA_COD || '',
                        v.VEI_FIPE_MODELO_COD || ''
                    );
                }
                $('#formVeiculo').find('select[name="cambio"]').val(v.VEI_CAMBIO || '');
                window.ensureSelectValue($('#cor'), v.VEI_COD_COR_FK || '');
                window.ensureSelectValue($('#ufCarro'), v.VEI_UF || '');
                if (window.popularCidadesUFCarro) {
                    window.popularCidadesUFCarro(v.VEI_UF || '', v.VEI_CIDADE || null);
                } else if (v.VEI_CIDADE) {
                    window.ensureSelectValue($('#cidadeCarro'), v.VEI_CIDADE);
                }
                // Contrato
                if (contr.GRU_CODIGO_FK)   window.ensureSelectValue($('#grupo'),     contr.GRU_CODIGO_FK);
                if (contr.COM_CODIGO_FK)   window.ensureSelectValue($('#combo'),     contr.COM_CODIGO_FK);
                if (contr.CON_RASTREADOR_FK) window.ensureSelectValue($('#rastreador'), contr.CON_RASTREADOR_FK);
                $('#adesao').val(contr.CTR_VALOR_ADESAO || '');
                $('#mensalidade').val(contr.CTR_VALOR_MENSALIDADE || '');
                $('#valorCombo').val(contr.CTR_VALOR_COMBO || '');
                $('#valorRastreador').val(contr.CON_VALOR_RASTREADOR || '');
                $('#valor').val(contr.CTR_VALOR_VEICULO || '');
                $('#valorCobertura').val(contr.CTR_VALOR_COBERTURA || '');
                window.ensureSelectValue($('#tipoBoleto'), contr.CTR_TIPO_BOLETO || '');
                // Recalcula totalFinal imediatamente com os valores já carregados
                if (window.recalcularTotal) window.recalcularTotal();
                // Atualiza label do total conforme presença de rastreador
                var $lbl = $('#labelTotalFinal');
                if ($lbl.length) $lbl.text(contr.CON_RASTREADOR_FK ? 'R$ Mensalidade + Combo + Rastreador' : 'R$ Mensalidade + Combo');
                // Vistoria — restaura checkboxes
                $m.find('input[name="vis_checked[]"]').prop('checked', false);
                visItens.forEach(function (entry) {
                    var id    = entry.id    || entry;
                    var chave = entry.chave || entry;
                    var $cb = $m.find('input[name="vis_checked[]"][value="' + id + '"]');
                    if (!$cb.length) $cb = $m.find('#visCheckGrid .vis-item[data-key="' + chave + '"] input');
                    $cb.prop('checked', true);
                });
                $m.modal('show');
            })
            .catch(function () { Swal.fire('Erro', 'Falha ao comunicar com o servidor.', 'error'); });
    });
});
</script>

<!-- ═══════════════════════════════════════════
     JS: Cobranças + Modais Cancelar / Reativar / Transferir
════════════════════════════════════════════ -->
<script>
(function () {
    'use strict';

    var PESSOA_ID   = <?= (int)$pesId ?>;
    var ACTION_COB  = '<?= ACTION_URL ?>/cobrancas.php';
    var ACTION_VEI  = '<?= ACTION_URL ?>/veiculos.php';
    var ACTION_PES  = '<?= ACTION_URL ?>/pessoas.php';

    /* ────────────────────────────────────────────────────────────
       Aba Cobranças — carregamento AJAX
    ──────────────────────────────────────────────────────────── */
    var cobLoaded = false;
    var cobVeiId  = 0;
    var cobPlaca  = '';

    window.fiCobCarregar = function (veiId, placa) {
        cobVeiId = veiId || 0;
        cobPlaca = placa || '';

        var tag   = document.getElementById('cobFilterTag');
        var label = document.getElementById('cobFilterLabel');
        var cont  = document.getElementById('cobContent');

        if (cobVeiId > 0 && tag) {
            tag.style.display = 'inline-flex';
            if (label) label.textContent = cobPlaca || ('Veículo #' + cobVeiId);
        } else if (tag) {
            tag.style.display = 'none';
        }

        if (cont) cont.innerHTML = '<div style="text-align:center;padding:40px 0;color:#adb5bd;"><i class="fa-solid fa-spinner fa-spin fa-2x"></i></div>';

        var url = ACTION_COB + '?acao=listar_por_pessoa&pessoa_id=' + PESSOA_ID;
        if (cobVeiId > 0) url += '&veiculo_id=' + cobVeiId;

        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (j) { fiCobRenderizar(j); })
            .catch(function () {
                if (cont) cont.innerHTML = '<p style="text-align:center;color:#e03131;padding:30px;">Erro ao carregar cobranças.</p>';
            });

        fetch(ACTION_COB + '?acao=resumo_por_pessoa&pessoa_id=' + PESSOA_ID)
            .then(function (r) { return r.json(); })
            .then(function (j) { if (j.success) fiCobSumario(j.data); })
            .catch(function () {});
    };

    function fiCobSumario(d) {
        var el = document.getElementById('cobSumario');
        if (!el) return;
        var fmt = function (v) { return 'R$ ' + parseFloat(v || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2}); };
        var cards = [
            { label: 'Total',     val: d.total || 0,         color: '#3b5bdb', icon: 'fa-receipt' },
            { label: 'Valor',     val: fmt(d.valor_total),   color: '#1971c2', icon: 'fa-money-bill-wave' },
            { label: 'Pagas',     val: d.total_pagas || 0,   color: '#2f9e44', icon: 'fa-circle-check' },
            { label: 'Em aberto', val: d.total_abertas || 0, color: '#f59f00', icon: 'fa-clock' },
            { label: 'Vencidas',  val: d.total_vencidas || 0,color: '#e03131', icon: 'fa-triangle-exclamation' },
        ];
        el.innerHTML = cards.map(function (c) {
            return '<div style="background:#fff;border:1px solid #e9ecef;border-radius:12px;padding:14px 16px;display:flex;align-items:center;gap:12px;">' +
                '<div style="width:38px;height:38px;border-radius:10px;background:' + c.color + '1a;color:' + c.color + ';display:flex;align-items:center;justify-content:center;font-size:15px;">' +
                '<i class="fa-solid ' + c.icon + '"></i></div>' +
                '<div><div style="font-size:18px;font-weight:800;color:#1a1d2e;">' + c.val + '</div>' +
                '<div style="font-size:11px;color:#868e96;text-transform:uppercase;letter-spacing:.4px;">' + c.label + '</div></div></div>';
        }).join('');
    }

    function fiCobRenderizar(j) {
        var cont = document.getElementById('cobContent');
        if (!cont) return;
        var rows = (j && j.data) ? j.data : [];
        if (!rows.length) {
            cont.innerHTML = '<p style="text-align:center;color:#adb5bd;padding:40px 0;">Nenhuma cobrança encontrada.</p>';
            return;
        }
        var fmtMoeda = function (v) {
            if (v == null || v === '') return '—';
            return 'R$ ' + parseFloat(v).toLocaleString('pt-BR', {minimumFractionDigits: 2});
        };
        var fmtData = function (d) {
            if (!d) return '—';
            var p = d.split(' ')[0].split('-');
            return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : d;
        };
        var badge = function (row) {
            if (row.COB_BOLETO_CANCELADO === 'SIM') return '<span style="background:#fff5f5;color:#e03131;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;">Cancelado</span>';
            if (row.COB_PAGO === 'SIM') return '<span style="background:#ebfbee;color:#2f9e44;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;">Pago</span>';
            var hoje = new Date(); hoje.setHours(0, 0, 0, 0);
            var venc = new Date(row.COB_DATA_VENCIMENTO);
            if (venc < hoje) return '<span style="background:#fff9db;color:#e67700;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;">Vencido</span>';
            return '<span style="background:#f0f3ff;color:#3b5bdb;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;">Em aberto</span>';
        };
        var BOLETO_URL  = '<?= APP_URL ?>/boleto/imprimir.php';
        var PES_CELULAR = '<?= preg_replace('/\D/', '', $p['PES_FONE_CELULAR_1'] ?? '') ?>';
        var PES_NOME_WA = '<?= addslashes($pesNome) ?>';
        var html = '<div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;font-size:13px;">' +
            '<thead><tr style="background:#f8f9fb;">' +
            '<th style="padding:10px 12px;text-align:left;font-weight:700;color:#495057;white-space:nowrap;">#</th>' +
            '<th style="padding:10px 12px;text-align:left;font-weight:700;color:#495057;white-space:nowrap;">Veículo</th>' +
            '<th style="padding:10px 12px;text-align:left;font-weight:700;color:#495057;white-space:nowrap;">Vencimento</th>' +
            '<th style="padding:10px 12px;text-align:right;font-weight:700;color:#495057;white-space:nowrap;">Valor</th>' +
            '<th style="padding:10px 12px;text-align:center;font-weight:700;color:#495057;white-space:nowrap;">Status</th>' +
            '<th style="padding:10px 12px;text-align:left;font-weight:700;color:#495057;white-space:nowrap;">Quitação</th>' +
            '<th style="padding:10px 12px;text-align:left;font-weight:700;color:#495057;white-space:nowrap;">Tipo</th>' +
            '<th style="padding:10px 12px;text-align:left;font-weight:700;color:#495057;white-space:nowrap;">Nosso Nº</th>' +
            '<th style="padding:10px 12px;text-align:center;font-weight:700;color:#495057;white-space:nowrap;">Ações</th>' +
            '</tr></thead><tbody>';

        rows.forEach(function (r, i) {
            var placa = r.VEI_PLACA ? (r.VEI_PLACA + ' — ' + (r.VEI_MARCA || '') + ' ' + (r.VEI_MODELO || '')) : (r.COB_PLACAS || '—');
            var cancelado = r.COB_BOLETO_CANCELADO === 'SIM';
            var temNn     = r.COB_NOSSO_NUMERO && r.COB_NOSSO_NUMERO !== '—';
            var btnImprimir = (temNn && !cancelado)
                ? '<a href="' + BOLETO_URL + '?id=' + r.COB_CODIGO_PK + '" target="_blank" title="Imprimir boleto" ' +
                  'style="display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:6px;' +
                  'background:#1a5c1a;color:#fff;font-size:13px;text-decoration:none;">' +
                  '<i class="fa-solid fa-print"></i></a>'
                : '<span style="color:#ced4da;font-size:11px;">—</span>';

            var foneWa = PES_CELULAR ? PES_CELULAR.replace(/\D/g, '') : '';
            if (foneWa && foneWa.length >= 10 && foneWa.indexOf('55') !== 0) foneWa = '55' + foneWa;
            var waText = 'Olá ' + PES_NOME_WA + ', segue sua cobrança no valor de ' + fmtMoeda(r.COB_VALOR) +
                ' com vencimento em ' + fmtData(r.COB_DATA_VENCIMENTO) + '.' +
                (temNn && !cancelado ? ' Para imprimir o boleto acesse: ' + BOLETO_URL + '?id=' + r.COB_CODIGO_PK : '');
            var btnWa = (foneWa && foneWa.length >= 12)
                ? '<a href="https://wa.me/' + foneWa + '?text=' + encodeURIComponent(waText) + '" target="_blank" title="Enviar por WhatsApp" ' +
                  'style="display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:6px;' +
                  'background:#25D366;color:#fff;font-size:14px;text-decoration:none;margin-left:4px;">' +
                  '<i class="fa-brands fa-whatsapp"></i></a>'
                : '';
            html += '<tr style="border-top:1px solid #f0f2f5;' + (i % 2 === 0 ? '' : 'background:#fafbfc;') + '">' +
                '<td style="padding:10px 12px;color:#868e96;font-size:11px;">' + r.COB_CODIGO_PK + '</td>' +
                '<td style="padding:10px 12px;white-space:nowrap;">' + placa + '</td>' +
                '<td style="padding:10px 12px;white-space:nowrap;">' + fmtData(r.COB_DATA_VENCIMENTO) + '</td>' +
                '<td style="padding:10px 12px;text-align:right;font-weight:700;color:#1a1d2e;">' + fmtMoeda(r.COB_VALOR) + '</td>' +
                '<td style="padding:10px 12px;text-align:center;">' + badge(r) + '</td>' +
                '<td style="padding:10px 12px;white-space:nowrap;">' + (r.COB_PAGO === 'SIM' ? fmtData(r.COB_DATA_QUITACAO) : '—') + '</td>' +
                '<td style="padding:10px 12px;white-space:nowrap;">' + (r.COB_TIPO_BOLETO || '—') + '</td>' +
                '<td style="padding:10px 12px;font-family:monospace;font-size:12px;">' + (r.COB_NOSSO_NUMERO || '—') + '</td>' +
                '<td style="padding:10px 12px;text-align:center;white-space:nowrap;">' + btnImprimir + btnWa + '</td>' +
                '</tr>';
        });
        html += '</tbody></table></div>';
        cont.innerHTML = html;
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-fi-tab="cobrancas"]');
        if (btn) { cobLoaded = true; fiCobCarregar(cobVeiId, cobPlaca); }
    });

    /* ────────────────────────────────────────────────────────────
       Botões da aba Contratos
    ──────────────────────────────────────────────────────────── */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.fi-action-btn');
        if (!btn) return;
        var cid   = btn.dataset.contratoId || '';
        var vid   = btn.dataset.veiculoId  || '';
        var placa = btn.dataset.placa       || '';

        if (btn.classList.contains('fi-btn-cancelar')) {
            document.getElementById('cancelContratoId').value = cid;
            document.getElementById('cancelPlaca').textContent = placa;
            document.getElementById('cancelMotivo').value = '';
            $('#modalCancelar').modal('show');
        }

        if (btn.classList.contains('fi-btn-reativar')) {
            document.getElementById('reativarContratoId').value = cid;
            document.getElementById('reativarPlaca').textContent = placa;
            document.getElementById('reativarMotivo').value = '';
            $('#modalReativar').modal('show');
        }

        if (btn.classList.contains('fi-btn-transferir')) {
            document.getElementById('transContratoId').value = cid;
            document.getElementById('transVeiculoId').value  = vid;
            document.getElementById('transPlaca').textContent = placa;
            document.getElementById('transDestinatarioId').value = '';
            document.getElementById('transBusca').value = '';
            document.getElementById('transBusca').style.display = '';
            document.getElementById('transBuscaResults').style.display = 'none';
            document.getElementById('transDestinatarioCard').style.display = 'none';
            document.getElementById('transMotivo').value = '';
            $('#modalTransferir').modal('show');
        }

        if (btn.classList.contains('fi-btn-boletos')) {
            cobLoaded = true;
            fiCobCarregar(parseInt(vid, 10) || 0, placa);
            var tabBtn = document.querySelector('[data-fi-tab="cobrancas"]');
            if (tabBtn && typeof fiTab === 'function') fiTab('cobrancas', tabBtn);
        }
    });

    /* ────────────────────────────────────────────────────────────
       Form: Cancelar Contrato
    ──────────────────────────────────────────────────────────── */
    var formCancelar = document.getElementById('formCancelar');
    if (formCancelar) {
        formCancelar.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = document.getElementById('btnConfCancelar');
            var txt = document.getElementById('txtBtnCancelar');
            btn.disabled = true;
            if (txt) txt.textContent = 'Aguarde...';
            fetch(ACTION_VEI, { method: 'POST', body: new FormData(formCancelar) })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    $('#modalCancelar').modal('hide');
                    if (j.success) {
                        Swal.fire({ icon: 'success', title: 'Contrato cancelado!', text: j.message || 'Operação realizada com sucesso.' })
                            .then(function () { location.reload(); });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Erro', text: j.message || 'Não foi possível cancelar.' });
                    }
                })
                .catch(function () { Swal.fire({ icon: 'error', title: 'Erro', text: 'Falha ao comunicar com o servidor.' }); })
                .finally(function () { btn.disabled = false; if (txt) txt.textContent = 'Confirmar Cancelamento'; });
        });
    }

    /* ────────────────────────────────────────────────────────────
       Form: Reativar Contrato
    ──────────────────────────────────────────────────────────── */
    var formReativar = document.getElementById('formReativar');
    if (formReativar) {
        formReativar.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = document.getElementById('btnConfReativar');
            var txt = document.getElementById('txtBtnReativar');
            btn.disabled = true;
            if (txt) txt.textContent = 'Aguarde...';
            fetch(ACTION_VEI, { method: 'POST', body: new FormData(formReativar) })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    $('#modalReativar').modal('hide');
                    if (j.success) {
                        Swal.fire({ icon: 'success', title: 'Contrato reativado!', text: j.message || 'Operação realizada com sucesso.' })
                            .then(function () { location.reload(); });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Erro', text: j.message || 'Não foi possível reativar.' });
                    }
                })
                .catch(function () { Swal.fire({ icon: 'error', title: 'Erro', text: 'Falha ao comunicar com o servidor.' }); })
                .finally(function () { btn.disabled = false; if (txt) txt.textContent = 'Confirmar Reativação'; });
        });
    }

    /* ────────────────────────────────────────────────────────────
       Transferir — Autocomplete de associado
    ──────────────────────────────────────────────────────────── */
    var transBusca   = document.getElementById('transBusca');
    var transResults = document.getElementById('transBuscaResults');
    var transTimer   = null;

    if (transBusca) {
        transBusca.addEventListener('input', function () {
            clearTimeout(transTimer);
            var q = this.value.trim();
            if (q.length < 2) { transResults.style.display = 'none'; return; }
            transTimer = setTimeout(function () {
                fetch(ACTION_PES + '?acao=buscar&q=' + encodeURIComponent(q) + '&limit=8')
                    .then(function (r) { return r.json(); })
                    .then(function (j) { fiTransResultados(Array.isArray(j) ? j : (j.data || [])); })
                    .catch(function () { transResults.style.display = 'none'; });
            }, 280);
        });

        document.addEventListener('click', function (e) {
            if (transBusca && !transBusca.contains(e.target) && !transResults.contains(e.target)) {
                transResults.style.display = 'none';
            }
        });
    }

    function fiTransResultados(lista) {
        if (!lista.length) {
            transResults.innerHTML = '<div style="padding:12px 16px;color:#868e96;font-size:13px;">Nenhum associado encontrado.</div>';
            transResults.style.display = 'block';
            return;
        }
        transResults.innerHTML = lista.map(function (p) {
            var info = [p.PES_CPF_CNPJ || '', p.PES_FONE_CELULAR_1 || ''].filter(Boolean).join(' · ');
            return '<div class="fi-trans-item" data-id="' + p.PES_CODIGO_PK + '" data-nome="' + (p.PES_NOME || '').replace(/"/g, '&quot;') + '" data-info="' + info.replace(/"/g, '&quot;') + '" ' +
                'style="padding:10px 14px;cursor:pointer;border-bottom:1px solid #f0f2f5;font-size:13px;display:flex;align-items:center;gap:10px;">' +
                '<div style="width:32px;height:32px;border-radius:50%;background:#3b5bdb;color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;">' +
                (p.PES_NOME || '?').charAt(0).toUpperCase() + '</div>' +
                '<div><div style="font-weight:600;color:#1a1d2e;">' + (p.PES_NOME || '—') + '</div>' +
                '<div style="font-size:11px;color:#868e96;">' + info + '</div></div></div>';
        }).join('');
        transResults.style.display = 'block';

        transResults.querySelectorAll('.fi-trans-item').forEach(function (el) {
            el.addEventListener('mouseenter', function () { this.style.background = '#f0f3ff'; });
            el.addEventListener('mouseleave', function () { this.style.background = ''; });
            el.addEventListener('click', function () {
                fiTransSelecionar(this.dataset.id, this.dataset.nome, this.dataset.info);
            });
        });
    }

    function fiTransSelecionar(id, nome, info) {
        document.getElementById('transDestinatarioId').value = id;
        var card   = document.getElementById('transDestinatarioCard');
        var avatar = document.getElementById('transDestAvatar');
        var nomeEl = document.getElementById('transDestNome');
        var infoEl = document.getElementById('transDestInfo');
        if (avatar) avatar.textContent = (nome || '?').charAt(0).toUpperCase();
        if (nomeEl) nomeEl.textContent = nome || '—';
        if (infoEl) infoEl.textContent = info || '';
        card.style.display = 'block';
        transResults.style.display = 'none';
        transBusca.value = '';
        transBusca.style.display = 'none';
    }

    window.fiTransLimpar = function () {
        document.getElementById('transDestinatarioId').value = '';
        document.getElementById('transDestinatarioCard').style.display = 'none';
        if (transBusca) { transBusca.style.display = ''; transBusca.value = ''; transBusca.focus(); }
    };

    /* ────────────────────────────────────────────────────────────
       Form: Transferir Veículo
    ──────────────────────────────────────────────────────────── */
    var formTransferir = document.getElementById('formTransferir');
    if (formTransferir) {
        formTransferir.addEventListener('submit', function (e) {
            e.preventDefault();
            var destId = document.getElementById('transDestinatarioId').value;
            if (!destId) { Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Selecione o associado destino.' }); return; }

            var btn = document.getElementById('btnConfTransferir');
            var txt = document.getElementById('txtBtnTransferir');
            btn.disabled = true;
            if (txt) txt.textContent = 'Aguarde...';
            fetch(ACTION_VEI, { method: 'POST', body: new FormData(formTransferir) })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    $('#modalTransferir').modal('hide');
                    if (j.success) {
                        Swal.fire({ icon: 'success', title: 'Transferência realizada!', text: j.message || 'Veículo transferido com sucesso.' })
                            .then(function () { location.reload(); });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Erro', text: j.message || 'Não foi possível transferir.' });
                    }
                })
                .catch(function () { Swal.fire({ icon: 'error', title: 'Erro', text: 'Falha ao comunicar com o servidor.' }); })
                .finally(function () { btn.disabled = false; if (txt) txt.textContent = 'Confirmar Transferência'; });
        });
    }

})();
</script>

<!-- ══ Modal: Imprimir Documentos ══ -->
<div id="modalImprimirDocs" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:18px;width:480px;max-width:96vw;box-shadow:0 16px 60px rgba(0,0,0,.25);overflow:hidden;">

        <!-- Header -->
        <div style="background:linear-gradient(135deg,#3b5bdb,#2f4abf);padding:20px 24px;display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:12px;color:#fff;">
                <div style="width:38px;height:38px;background:rgba(255,255,255,.15);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                    <i class="fa-solid fa-print" style="font-size:16px;"></i>
                </div>
                <div>
                    <div style="font-size:16px;font-weight:800;">Imprimir Documentos</div>
                    <div style="font-size:11px;opacity:.8;">Selecione o(s) documento(s) desejado(s)</div>
                </div>
            </div>
            <button onclick="fecharModalImprimir()" style="background:rgba(255,255,255,.15);border:none;color:#fff;width:32px;height:32px;border-radius:8px;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Body -->
        <div style="padding:24px;">

            <!-- Termo de Filiação -->
            <div class="imp-doc-opt" data-doc="termo" style="border:1.5px solid #dee2e6;border-radius:12px;padding:14px 16px;margin-bottom:10px;cursor:pointer;transition:.15s;">
                <label style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;">
                    <input type="checkbox" class="imp-chk" value="termo" style="margin-top:2px;width:16px;height:16px;accent-color:#3b5bdb;flex-shrink:0;">
                    <div>
                        <div style="font-weight:700;font-size:13px;color:#1a1d2e;">
                            <i class="fa-solid fa-file-signature" style="color:#3b5bdb;margin-right:6px;"></i>
                            Termo de Filiação
                        </div>
                        <div style="font-size:11px;color:#868e96;margin-top:3px;">2 páginas — dados do associado, veículo, plano e cláusulas</div>
                    </div>
                </label>
            </div>

            <!-- Estatuto -->
            <div class="imp-doc-opt" data-doc="estatuto" style="border:1.5px solid #dee2e6;border-radius:12px;padding:14px 16px;margin-bottom:10px;cursor:pointer;transition:.15s;">
                <label style="display:flex;align-items:center;gap:12px;cursor:pointer;">
                    <input type="checkbox" class="imp-chk" value="estatuto" style="width:16px;height:16px;accent-color:#3b5bdb;flex-shrink:0;">
                    <div>
                        <div style="font-weight:700;font-size:13px;color:#1a1d2e;">
                            <i class="fa-solid fa-scale-balanced" style="color:#3b5bdb;margin-right:6px;"></i>
                            Estatuto Social
                        </div>
                        <div style="font-size:11px;color:#868e96;margin-top:3px;">Estatuto da associação</div>
                    </div>
                </label>
            </div>

            <!-- Regimento -->
            <div class="imp-doc-opt" data-doc="regimento" style="border:1.5px solid #dee2e6;border-radius:12px;padding:14px 16px;margin-bottom:10px;cursor:pointer;transition:.15s;">
                <label style="display:flex;align-items:center;gap:12px;cursor:pointer;">
                    <input type="checkbox" class="imp-chk" value="regimento" style="width:16px;height:16px;accent-color:#3b5bdb;flex-shrink:0;">
                    <div>
                        <div style="font-weight:700;font-size:13px;color:#1a1d2e;">
                            <i class="fa-solid fa-book-open" style="color:#3b5bdb;margin-right:6px;"></i>
                            Regimento Interno
                        </div>
                        <div style="font-size:11px;color:#868e96;margin-top:3px;">Regimento interno da associação</div>
                    </div>
                </label>
            </div>

            <!-- Vistoria -->
            <div class="imp-doc-opt" data-doc="vistoria" style="border:1.5px solid #dee2e6;border-radius:12px;padding:14px 16px;cursor:pointer;transition:.15s;">
                <label style="display:flex;align-items:center;gap:12px;cursor:pointer;">
                    <input type="checkbox" class="imp-chk" value="vistoria" style="width:16px;height:16px;accent-color:#3b5bdb;flex-shrink:0;">
                    <div>
                        <div style="font-weight:700;font-size:13px;color:#1a1d2e;">
                            <i class="fa-solid fa-clipboard-check" style="color:#3b5bdb;margin-right:6px;"></i>
                            Vistoria
                        </div>
                        <div style="font-size:11px;color:#868e96;margin-top:3px;">Checklist com os dados reais da vistoria do contrato</div>
                    </div>
                </label>
            </div>
        </div>

        <!-- Footer -->
        <div style="padding:16px 24px;border-top:1px solid #e9ecef;display:flex;justify-content:flex-end;gap:10px;">
            <button onclick="fecharModalImprimir()" style="height:40px;padding:0 20px;border-radius:10px;border:1.5px solid #dee2e6;background:#fff;color:#495057;font-size:13px;font-weight:700;cursor:pointer;">
                Cancelar
            </button>
            <button id="btnConfirmarImprimir" style="height:40px;padding:0 24px;border-radius:10px;border:none;background:#3b5bdb;color:#fff;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:8px;">
                <i class="fa-solid fa-print"></i> Imprimir Selecionados
            </button>
        </div>
    </div>
</div>

<!-- ══ JS: Botão Registrar Sinistro ══ -->
<script>
(function () {
    var veiculosSin = <?= json_encode(array_values($veiculosParaSinistro), JSON_UNESCAPED_UNICODE) ?>;

    var btnReg = document.getElementById('btnRegistrarSinistro');
    if (!btnReg) return;

    btnReg.addEventListener('click', function () {
        if (!veiculosSin.length) {
            Swal.fire('Atenção', 'Este associado não possui veículos cadastrados.', 'warning');
            return;
        }
        if (veiculosSin.length === 1) {
            window.sinAbrirNovo(veiculosSin[0]);
        } else {
            window.sinAbrirNovo({
                pesId    : veiculosSin[0].pesId,
                assoc    : veiculosSin[0].assoc,
                cpf      : veiculosSin[0].cpf,
                fone     : veiculosSin[0].fone,
                veiculos : veiculosSin
            });
        }
    });

    window.sinOnSalvo = function () { location.reload(); };
})();
</script>

<!-- ══ JS: Modal Imprimir Documentos ══ -->
<script>
(function () {
    var PES_ID = <?= $pesId ?>;
    var APP    = '<?= APP_URL ?>';

    var modal      = document.getElementById('modalImprimirDocs');
    var btnConf    = document.getElementById('btnConfirmarImprimir');
    var chkTermo   = document.querySelector('.imp-chk[value="termo"]');
    var _ctrIdAtual = 0;

    function abrirModalImprimir(ctrId) {
        _ctrIdAtual = ctrId || 0;
        // Marca Termo automaticamente e destaca o card
        chkTermo.checked = true;
        var opt = document.querySelector('.imp-doc-opt[data-doc="termo"]');
        if (opt) { opt.style.borderColor = '#3b5bdb'; opt.style.background = '#f0f3ff'; }
        modal.style.display = 'flex';
    }
    window.abrirModalImprimir = abrirModalImprimir;

    // Botões de imprimir por linha
    document.querySelectorAll('.fi-btn-imprimir-doc').forEach(function (btn) {
        btn.addEventListener('click', function () {
            abrirModalImprimir(this.dataset.contratoId);
        });
    });

    function fecharModalImprimir() {
        modal.style.display = 'none';
    }
    window.fecharModalImprimir = fecharModalImprimir;

    // Fecha ao clicar fora
    modal.addEventListener('click', function (e) {
        if (e.target === modal) fecharModalImprimir();
    });

    // Highlight ao marcar
    document.querySelectorAll('.imp-doc-opt').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (e.target.tagName === 'SELECT' || e.target.tagName === 'OPTION') return;
            var chk = el.querySelector('.imp-chk');
            if (e.target !== chk) chk.checked = !chk.checked;
            chk.dispatchEvent(new Event('change'));
            el.style.borderColor = chk.checked ? '#3b5bdb' : '#dee2e6';
            el.style.background  = chk.checked ? '#f0f3ff' : '#fff';
        });
        var chk = el.querySelector('.imp-chk');
        chk.addEventListener('change', function () {
            el.style.borderColor = this.checked ? '#3b5bdb' : '#dee2e6';
            el.style.background  = this.checked ? '#f0f3ff' : '#fff';
        });
    });

    // Imprimir
    btnConf.addEventListener('click', function () {
        var selecionados = [];
        document.querySelectorAll('.imp-chk:checked').forEach(function (chk) {
            selecionados.push(chk.value);
        });
        if (!selecionados.length) {
            Swal.fire('Atenção', 'Selecione pelo menos um documento para imprimir.', 'warning');
            return;
        }
        selecionados.forEach(function (doc) {
            var url = '';
            if (doc === 'termo') {
                url = APP + '/impressoes/termo_filiacao.php?pes_id=' + PES_ID + '&ctr_id=' + _ctrIdAtual;
            } else if (doc === 'estatuto') {
                url = APP + '/impressoes/estatuto.php';
            } else if (doc === 'regimento') {
                url = APP + '/impressoes/regimento.php';
            } else if (doc === 'vistoria') {
                url = APP + '/impressoes/vistoria.php?pes_id=' + PES_ID + '&ctr_id=' + _ctrIdAtual;
            }
            if (url) window.open(url, '_blank');
        });
        fecharModalImprimir();
    });
})();
</script>
