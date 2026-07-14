<?php
if (!defined('PATH_INC')) require_once __DIR__ . '/../../inc/config.php';
require_once PATH_INC . '/db.php';
require_once PATH_INC . '/repositories/CorRepository.php';
require_once PATH_INC . '/repositories/GrupoRepository.php';
require_once PATH_INC . '/repositories/ComboRepository.php';
require_once PATH_INC . '/repositories/RastreadorRepository.php';
require_once PATH_INC . '/csrf.php';
$csrf = csrf_token();

try {
    $cores        = listarCores($pdo);
} catch (Throwable $e) {
    $cores = [];
}
try {
    $grupos       = listarGrupos($pdo);
} catch (Throwable $e) {
    $grupos = [];
}
try {
    $combos       = listarCombos($pdo);
} catch (Throwable $e) {
    $combos = [];
}
try {
    $rastreadores = listarRastreadores($pdo);
} catch (Throwable $e) {
    $rastreadores = [];
}
try {
    $vistoriadores = $pdo->query("SELECT VIS_CODIGO_PK, VIS_NOME FROM tb_vistoriador WHERE VIS_STATUS='ATIVO' ORDER BY VIS_NOME")->fetchAll(PDO::FETCH_OBJ);
} catch (Throwable $e) {
    $vistoriadores = [];
}

require_once('modal_cad_associado.php');
require_once('modal_cad_veiculo.php');

$pessoas = $pdo->query("
    SELECT
        p.PES_CODIGO_PK,
        p.PES_NOME,
        p.PES_CPF_CNPJ,
        p.PES_FONE_CELULAR_1,
        p.PES_FOTO,
        GROUP_CONCAT(DISTINCT v.VEI_PLACA SEPARATOR ' ')     AS PLACAS,
        GROUP_CONCAT(DISTINCT c.CTR_CODIGO_PK SEPARATOR ' ') AS CONTRATOS
    FROM tb_pessoa p
    LEFT JOIN tb_veiculo  v ON v.PES_CODIGO_FK = p.PES_CODIGO_PK
    LEFT JOIN tb_contrato c ON c.VEI_CODIGO_FK = v.VEI_CODIGO_PK
    GROUP BY p.PES_CODIGO_PK
    ORDER BY p.PES_CODIGO_PK DESC
    LIMIT 1000
")->fetchAll(PDO::FETCH_OBJ);

// Stats para os cards
try {
    $statsRow = $pdo->query("
        SELECT
            COUNT(DISTINCT p.PES_CODIGO_PK)                                              AS total_assoc,
            COUNT(DISTINCT CASE WHEN c.CTR_STATUS = 'A' THEN p.PES_CODIGO_PK END)       AS assoc_ativos,
            COUNT(DISTINCT CASE WHEN c.CTR_STATUS != 'A' OR c.CTR_STATUS IS NULL THEN p.PES_CODIGO_PK END) AS assoc_inativos,
            COUNT(DISTINCT v.VEI_CODIGO_PK)                                              AS total_veiculos,
            COUNT(DISTINCT CASE WHEN c.CTR_STATUS = 'A' THEN v.VEI_CODIGO_PK END)       AS veic_ativos,
            COUNT(DISTINCT CASE WHEN c.CTR_STATUS != 'A' THEN v.VEI_CODIGO_PK END)      AS veic_inativos
        FROM tb_pessoa p
        LEFT JOIN tb_veiculo  v ON v.PES_CODIGO_FK = p.PES_CODIGO_PK
        LEFT JOIN tb_contrato c ON c.VEI_CODIGO_FK = v.VEI_CODIGO_PK
    ")->fetch(PDO::FETCH_OBJ);
} catch (Throwable $e) {
    $statsRow = (object)['total_assoc' => 0, 'assoc_ativos' => 0, 'assoc_inativos' => 0, 'total_veiculos' => 0, 'veic_ativos' => 0, 'veic_inativos' => 0];
}
?>

<link rel="stylesheet" href="../valiantus-tables.css">

<style>
    /* ── Feedback visual ao consultar CEP ── */
    .cep-loading {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 50 50'%3E%3Ccircle cx='25' cy='25' r='20' fill='none' stroke='%233f51b5' stroke-width='5' stroke-dasharray='31.4 94.2'%3E%3CanimateTransform attributeName='transform' type='rotate' from='0 25 25' to='360 25 25' dur='0.8s' repeatCount='indefinite'/%3E%3C/circle%3E%3C/svg%3E") !important;
        background-repeat: no-repeat !important;
        background-position: right 8px center !important;
        background-size: 16px 16px !important;
        padding-right: 30px !important;
        opacity: 0.8;
    }
</style>

<style>
    /* ── Stats cards ── */
    .asc-stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 22px;
    }

    .asc-stat {
        background: #fff;
        border-radius: 14px;
        border: 1px solid #e9ecef;
        box-shadow: 0 2px 12px rgba(30, 40, 80, .06);
        padding: 18px 20px;
        display: flex;
        align-items: center;
        gap: 14px;
        position: relative;
        overflow: hidden;
        transition: transform .15s, box-shadow .15s;
    }

    .asc-stat:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(30, 40, 80, .1);
    }

    .asc-stat::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        width: 4px;
        height: 100%;
        border-radius: 4px 0 0 4px;
        background: var(--sc, #3b5bdb);
    }

    .asc-stat-icon {
        width: 46px;
        height: 46px;
        border-radius: 12px;
        background: var(--si, #e8edff);
        color: var(--sc, #3b5bdb);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }

    .asc-stat-body {}

    .asc-stat-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #868e96;
        margin-bottom: 3px;
    }

    .asc-stat-value {
        font-size: 26px;
        font-weight: 800;
        color: #1a1d2e;
        letter-spacing: -.5px;
        line-height: 1;
    }

    /* ── DataTables overrides ── */
    /* ── Correção rolagem horizontal ── */
    .vt-card {
        overflow: visible !important;
    }

    .vt-card-body {
        overflow: visible !important;
    }

    #html_table_wrapper {
        width: 100% !important;
        overflow-x: visible !important;
    }

    /* Desktop/tablet */
    .vt-card-body {
        overflow-x: visible !important;
    }

    /* Só no mobile */
    @media (max-width: 768px) {
        .vt-card-body {
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch;
        }

        #html_table_wrapper {
            overflow-x: auto !important;
        }

        #html_table {
            min-width: 760px;
        }
    }

    /* Impede que qualquer elemento ancestral crie barra desnecessária */
    .vt-page {
        overflow-x: hidden;
    }

    .dataTables_wrapper {
        padding: 0 !important;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 13px;
    }

    /* ── Footer bar: info | paginação | por página ── */
    .dt-footer {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 11px 20px;
        border-top: 1px solid #e2e8f0;
        background: #fff;
        border-radius: 0 0 14px 14px;
    }
    .dt-footer-info { flex: 1; }
    .dt-footer-paging { flex: 0 0 auto; }
    .dt-footer-paging .dataTables_paginate { display: none !important; }

    /* ── Paginação customizada ── */
    .asc-pag-controls {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .asc-pag-controls button {
        background: #fff;
        border: 1px solid #e2e8f0;
        color: #334155;
        min-width: 32px;
        height: 32px;
        padding: 0 8px;
        border-radius: 7px;
        font-size: 12px;
        font-family: inherit;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: background .15s, border-color .15s, color .15s;
    }
    .asc-pag-controls button:hover:not(:disabled) {
        background: #edf2ff;
        border-color: #3b5bdb;
        color: #3b5bdb;
    }
    .asc-pag-controls button:disabled {
        opacity: .38;
        cursor: not-allowed;
    }
    .asc-pag-controls button.active {
        background: #3b5bdb;
        border-color: #3b5bdb;
        color: #fff;
    }
    .asc-pag-controls .pg-ellipsis {
        font-size: 12px;
        color: #94a3b8;
        padding: 0 4px;
        user-select: none;
    }
    .dt-footer-per {
        flex: 1;
        display: flex;
        justify-content: flex-end;
    }

    .dataTables_wrapper .dataTables_filter { display: none !important; }

    .dataTables_wrapper .dataTables_info {
        font-size: 12.5px;
        color: #64748b;
        padding: 0 !important;
        float: none !important;
        clear: none !important;
    }

    /* ── Por página ── */
    .dataTables_wrapper .dataTables_length {
        float: none !important;
        padding: 0 !important;
    }
    .dataTables_wrapper .dataTables_length label {
        display: flex !important;
        align-items: center;
        gap: 7px;
        font-size: 12.5px;
        color: #64748b;
        margin: 0;
        white-space: nowrap;
    }
    .dataTables_wrapper .dataTables_length select {
        height: 30px !important;
        padding: 0 8px !important;
        border: 1.5px solid #e2e8f0 !important;
        border-radius: 7px !important;
        font-size: 12.5px !important;
        color: #334155 !important;
        background: #f8fafc !important;
        outline: none !important;
        cursor: pointer;
    }
    .dataTables_wrapper .dataTables_length select:focus {
        border-color: #0891b2 !important;
        box-shadow: 0 0 0 3px rgba(8, 145, 178, .1) !important;
    }

    /* ── Botões de paginação ── */
    .dataTables_wrapper .dataTables_paginate {
        padding: 0 !important;
        float: none !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        border-radius: 8px !important;
        font-size: 13px !important;
        font-family: inherit !important;
        padding: 4px 10px !important;
        margin: 0 2px !important;
        border: 1.5px solid #e2e8f0 !important;
        background: #fff !important;
        color: #475569 !important;
        transition: all .12s !important;
        min-width: 32px;
        text-align: center;
        line-height: 1.6 !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current,
    .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
        background: #0891b2 !important;
        border-color: #0891b2 !important;
        color: #fff !important;
        box-shadow: 0 2px 8px rgba(8, 145, 178, .28) !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button:not(.disabled):not(.current):hover {
        background: #ecfeff !important;
        border-color: #67e8f9 !important;
        color: #0891b2 !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.disabled,
    .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover {
        opacity: .35 !important;
        cursor: not-allowed !important;
        background: #f8fafc !important;
        border-color: #e2e8f0 !important;
        color: #94a3b8 !important;
    }

    /* ── Barra de busca própria ── */
    .asc-search-bar {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px 20px;
        border-bottom: 1px solid #e9ecef;
        background: #fafbfd;
    }

    .asc-search-wrap {
        position: relative;
        flex: 1;
        max-width: 380px;
    }

    .asc-search-wrap i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #adb5bd;
        font-size: 13px;
        pointer-events: none;
    }

    .asc-search-input {
        width: 100%;
        height: 36px;
        padding: 0 12px 0 34px;
        border: 1.5px solid #e2e8f0;
        border-radius: 9px;
        font-family: inherit;
        font-size: 13.5px;
        color: #1a1d2e;
        background: #fff;
        outline: none;
        transition: border-color .15s, box-shadow .15s;
    }

    .asc-search-input:focus {
        border-color: #3b5bdb;
        box-shadow: 0 0 0 3px rgba(59, 91, 219, .1);
    }

    .asc-search-input::placeholder {
        color: #adb5bd;
    }

    /* ── Cabeçalho da tabela principal ── */
    table.vt-table thead th,
    #html_table thead th {
        background: #f8fafc !important;
        color: #475569 !important;
        font-size: 10.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .07em;
        border-bottom: 2px solid #e2e8f0 !important;
        border-top: none !important;
        padding: 11px 16px;
    }

    /* seta de ordenação do DataTables */
    table.dataTable thead .sorting::after,
    table.dataTable thead .sorting_asc::after,
    table.dataTable thead .sorting_desc::after,
    table.dataTable thead .sorting::before,
    table.dataTable thead .sorting_asc::before,
    table.dataTable thead .sorting_desc::before {
        color: rgba(71, 85, 105, .45) !important;
    }

    @media (max-width: 900px) {
        .asc-stats {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 500px) {
        .asc-stats {
            grid-template-columns: 1fr;
        }
    }

    table.dataTable {
        width: 100% !important;
    }

    .vt-table {
        table-layout: fixed;
    }

    .vt-table th,
    .vt-table td {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }




    /* ── Botões de ação sempre coloridos ── */
    .vt-action-btn--edit {
        background: #eff6ff;
        border-color: #bfdbfe;
        color: #3b82f6;
    }
    .vt-action-btn--edit:hover {
        background: #dbeafe !important;
        border-color: #3b82f6 !important;
        color: #2563eb !important;
    }
    .vt-action-btn--del {
        background: #fef2f2;
        border-color: #fecaca;
        color: #ef4444;
    }
    .vt-action-btn--del:hover {
        background: #fee2e2 !important;
        border-color: #ef4444 !important;
        color: #dc2626 !important;
    }
    .vt-action-btn--car {
        background: #fffbeb;
        border-color: #fde68a;
        color: #f59e0b;
        width: 38px;
        gap: 2px;
    }
    .vt-action-btn--car:hover {
        background: #fef3c7 !important;
        border-color: #f59e0b !important;
        color: #d97706 !important;
    }
    .vt-action-btn--ok {
        background: #f0fdf4;
        border-color: #bbf7d0;
        color: #22c55e;
    }
    .vt-action-btn--ok:hover {
        background: #dcfce7 !important;
        border-color: #22c55e !important;
        color: #16a34a !important;
    }

    /* ── Toggle grade/lista ── */
    .asc-view-toggle {
        display: flex;
        gap: 4px;
        margin-left: 8px;
    }
    .asc-view-toggle button {
        width: 34px;
        height: 34px;
        border: 1.5px solid #e2e8f0;
        background: #fff;
        border-radius: 8px;
        cursor: pointer;
        color: #94a3b8;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        transition: background .15s, border-color .15s, color .15s;
    }
    .asc-view-toggle button.active {
        background: #3b5bdb;
        border-color: #3b5bdb;
        color: #fff;
    }
    .asc-view-toggle button:not(.active):hover {
        background: #edf2ff;
        border-color: #3b5bdb;
        color: #3b5bdb;
    }

    /* ── Grid de cards ── */
    #assocViewGrid {
        display: none;
        padding: 20px;
    }
    .assoc-card-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
        gap: 16px;
    }
    .assoc-card {
        background: #fff;
        border: 1.5px solid #e2e8f0;
        border-radius: 14px;
        padding: 20px 16px 14px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        transition: box-shadow .18s, border-color .18s;
        cursor: default;
    }
    .assoc-card:hover {
        box-shadow: 0 4px 18px rgba(59,91,219,.10);
        border-color: #bac8ff;
    }
    .assoc-card-avatar {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #e8edff;
        background: #e2e8f0;
        flex-shrink: 0;
    }
    .assoc-card-id {
        font-size: 11px;
        font-weight: 700;
        color: #94a3b8;
        text-align: center;
        margin-bottom: 2px;
        letter-spacing: .03em;
    }
    .assoc-card-name {
        font-weight: 700;
        font-size: 13.5px;
        color: #1e293b;
        text-align: center;
        line-height: 1.3;
    }
    .assoc-card-info {
        font-size: 11.5px;
        color: #64748b;
        text-align: center;
        line-height: 1.6;
        width: 100%;
    }
    .assoc-card-plates {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        justify-content: center;
    }
    .assoc-card-plates span {
        font-size: 10.5px;
        font-weight: 700;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 5px;
        padding: 1px 7px;
        color: #334155;
        letter-spacing: .04em;
    }
    .assoc-card-actions {
        display: flex;
        gap: 6px;
        margin-top: 2px;
    }

</style>

<!-- Cards de estatísticas -->
<div class="vt-page" style="padding-bottom:0;">
    <div class="asc-stats">
        <div class="asc-stat" style="--sc:#3b5bdb;--si:#e8edff;">
            <div class="asc-stat-icon"><i class="fa-solid fa-users"></i></div>
            <div class="asc-stat-body">
                <div class="asc-stat-label">Total de Associados</div>
                <div class="asc-stat-value"><?= number_format($statsRow->total_assoc, 0, ',', '.') ?></div>
            </div>
        </div>
        <div class="asc-stat" style="--sc:#2f9e44;--si:#d3f9d8;">
            <div class="asc-stat-icon"><i class="fa-solid fa-user-check"></i></div>
            <div class="asc-stat-body">
                <div class="asc-stat-label">Assoc. Ativos</div>
                <div class="asc-stat-value"><?= number_format($statsRow->assoc_ativos, 0, ',', '.') ?></div>
            </div>
        </div>
        <div class="asc-stat" style="--sc:#0b7285;--si:#e3fafc;">
            <div class="asc-stat-icon"><i class="fa-solid fa-car"></i></div>
            <div class="asc-stat-body">
                <div class="asc-stat-label">Veículos Ativos</div>
                <div class="asc-stat-value"><?= number_format($statsRow->veic_ativos, 0, ',', '.') ?></div>
            </div>
        </div>
        <div class="asc-stat" style="--sc:#c92a2a;--si:#ffe3e3;">
            <div class="asc-stat-icon"><i class="fa-solid fa-car-burst"></i></div>
            <div class="asc-stat-body">
                <div class="asc-stat-label">Veículos Inativos</div>
                <div class="asc-stat-value"><?= number_format($statsRow->veic_inativos, 0, ',', '.') ?></div>
            </div>
        </div>
    </div>
</div>

<div class="vt-page" style="padding-top:0;">
    <div class="vt-card" style="overflow:visible;">

        <!-- Cabeçalho -->
        <div class="vt-card-header">
            <div class="vt-card-title">
                <div class="vt-icon-wrap"><i class="fa-solid fa-users"></i></div>
                <h3>Associados</h3>
            </div>
            <div class="vt-card-tools">
                <a href="#" class="vt-btn-new" data-toggle="modal" data-target="#CadastrarCliente">
                    <i class="fa-solid fa-user-plus"></i> Novo Associado
                </a>
            </div>
        </div>

        <!-- Barra de busca -->
        <div class="asc-search-bar">
            <div class="asc-search-wrap">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="generalSearch" class="asc-search-input" placeholder="Buscar por ID, nome, CPF, telefone...">
            </div>
            <div class="asc-view-toggle">
                <button id="btnAssocList" class="active" onclick="assocSetView('list')" title="Lista"><i class="fa-solid fa-list"></i></button>
                <button id="btnAssocGrid" onclick="assocSetView('grid')" title="Cards"><i class="fa-solid fa-grip"></i></button>
            </div>
        </div>

        <!-- Cards (grade) -->
        <div id="assocViewGrid">
            <div class="assoc-card-grid" id="assocCardGrid"></div>
        </div>

        <!-- Tabela (lista) -->
        <div class="vt-card-body" id="assocViewList">
            <table class="vt-table" id="html_table" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:70px;">Código</th>
                        <th>Nome</th>
                        <th>CPF / CNPJ</th>
                        <th>Telefone</th>
                        <th>Veículo(s)</th>
                        <th class="d-none">Filtro</th>
                        <th class="text-end" style="width:90px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$pessoas): ?>
                        <tr>
                            <td colspan="7" class="text-center" style="padding:40px;color:#868e96;">
                                <i class="fa-solid fa-users-slash" style="font-size:32px;margin-bottom:10px;display:block;opacity:.3;"></i>
                                Nenhum associado encontrado.
                            </td>
                        </tr>
                        <?php else: foreach ($pessoas as $p): ?>
                            <tr data-id="<?= (int)$p->PES_CODIGO_PK ?>"
                                data-foto="<?= htmlspecialchars($p->PES_FOTO ?? '') ?>"
                                data-nome="<?= htmlspecialchars($p->PES_NOME) ?>"
                                data-cpf="<?= htmlspecialchars($p->PES_CPF_CNPJ) ?>"
                                data-tel="<?= htmlspecialchars($p->PES_FONE_CELULAR_1) ?>"
                                data-placas="<?= htmlspecialchars(trim($p->PLACAS ?? '')) ?>"
                                data-cpf-raw="<?= htmlspecialchars(preg_replace('/\D+/', '', $p->PES_CPF_CNPJ)) ?>">
                                <td>#<?= (int)$p->PES_CODIGO_PK ?></td>
                                <td style="font-weight:600;"><?= htmlspecialchars($p->PES_NOME) ?></td>
                                <td><?= htmlspecialchars($p->PES_CPF_CNPJ) ?></td>
                                <td><?= htmlspecialchars($p->PES_FONE_CELULAR_1) ?></td>
                                <td>
                                    <?php foreach (array_filter(explode(' ', $p->PLACAS ?? '')) as $placa): ?>
                                        <span style="display:inline-block;font-size:11.5px;font-weight:700;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:5px;padding:1px 7px;margin:1px 2px 1px 0;color:#334155;letter-spacing:.04em;"><?= htmlspecialchars($placa) ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td class="d-none"><?= htmlspecialchars(trim(($p->CONTRATOS ?? ''))) ?></td>
                                <td>
                                    <div class="vt-actions">
                                        <a href="#"
                                            class="vt-action-btn vt-action-btn--edit btn-editar-associado"
                                            title="Editar associado"
                                            data-id="<?= (int)$p->PES_CODIGO_PK ?>">
                                            <i class="fa-solid fa-pen"></i>
                                        </a>
                                        <a href="#"
                                            class="vt-action-btn vt-action-btn--car"
                                            title="Adicionar veículo"
                                            data-toggle="modal" data-target="#modalVeiculo"
                                            data-nome="<?= htmlspecialchars($p->PES_NOME) ?>"
                                            data-codigo="<?= (int)$p->PES_CODIGO_PK ?>"
                                            data-cpf="<?= htmlspecialchars(preg_replace('/\D+/', '', $p->PES_CPF_CNPJ)) ?>">
                                            <i class="fa-solid fa-car" style="font-size:11px;"></i>
                                            <i class="fa-solid fa-plus" style="font-size:7px;margin-left:1px;"></i>
                                        </a>
                                        <a href="<?= APP_URL ?>/associados/ficha.php?id=<?= (int)$p->PES_CODIGO_PK ?>"
                                            class="vt-action-btn"
                                            title="Abrir ficha completa"
                                            style="background:#edf2ff;color:#3b5bdb;border-color:#bac8ff;">
                                            <i class="fa-solid fa-address-card"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<!-- Edição de associado: usa o modal #CadastrarCliente em modo editar (ver handler btn-editar-associado abaixo) -->

<!-- ════════════════════ Modal: Cancelar / Reativar Contrato ════════════════════ -->
<div class="modal fade" id="modalCancelarVeiculo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header" id="cancModalHeader" style="background:#c92a2a;color:#fff;">
                <h5 class="modal-title">
                    <i class="fa-solid fa-ban mr-2" id="cancModalIcon"></i>
                    <span id="cancModalTitle">Cancelar Contrato</span>
                </h5>
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:1;">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="cancelContratoId">
                <input type="hidden" id="cancModalMode" value="cancelar">
                <div class="form-group mb-3">
                    <label class="form-label font-weight-bold" id="cancPorLabel">Cancelado por</label>
                    <input type="text" class="form-control form-control-sm bg-light"
                           id="canceladoPor"
                           value="<?= htmlspecialchars($_SESSION['SessUsuNome'] ?? 'Usuário', ENT_QUOTES, 'UTF-8') ?>"
                           readonly>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label font-weight-bold" id="cancDataLabel">Data do cancelamento</label>
                    <input type="date" class="form-control form-control-sm" id="dataCancelamento"
                           value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group mb-1">
                    <label class="form-label font-weight-bold" id="cancMotivoLabel">
                        Motivo <span class="text-danger">*</span>
                    </label>
                    <textarea class="form-control form-control-sm" id="motivoCancelamento" rows="4"
                              placeholder="Descreva o motivo do cancelamento..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                <button type="button" id="btnConfirmarCancelamento" class="btn btn-danger">
                    <i class="fa-solid fa-ban mr-1" id="cancBtnIcon"></i>
                    <span id="cancBtnText">Confirmar Cancelamento</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════ Modal: Transferir Veículo ════════════════════ -->
<div class="modal fade" id="modalTransferirVeiculo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:#5c7cfa;color:#fff;">
                <h5 class="modal-title">
                    <i class="fa-solid fa-right-left mr-2"></i>Transferir Veículo
                </h5>
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:1;">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="transfContratoId">
                <input type="hidden" id="transfVeiculoId">
                <input type="hidden" id="transfDestinatarioId">

                <div class="form-group mb-3">
                    <label class="form-label font-weight-bold">Associado atual</label>
                    <input type="text" class="form-control form-control-sm bg-light"
                           id="transfAssoAtual" readonly>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label font-weight-bold">
                        Associado de destino <span class="text-danger">*</span>
                    </label>
                    <div style="position:relative;">
                        <input type="text" class="form-control form-control-sm"
                               id="transfBusca"
                               placeholder="Buscar por nome, CPF ou código..."
                               autocomplete="off">
                        <div id="transfDropdown"
                             style="display:none;position:absolute;top:100%;left:0;right:0;z-index:9999;
                                    background:#fff;border:1.5px solid #c5d0ff;border-top:none;
                                    border-radius:0 0 10px 10px;max-height:220px;overflow-y:auto;
                                    box-shadow:0 6px 20px rgba(59,91,219,.12);"></div>
                    </div>
                    <div id="transfSelecionado"
                         style="display:none;margin-top:8px;padding:10px 14px;
                                background:#e8f5e9;border:1.5px solid #2f9e44;
                                border-radius:8px;align-items:center;justify-content:space-between;gap:8px;">
                        <div>
                            <div style="font-weight:700;font-size:14px;" id="transfNomeSel"></div>
                            <div style="font-size:12px;color:#555;" id="transfCpfSel"></div>
                        </div>
                        <button type="button" id="transfLimparSel"
                                class="btn btn-sm btn-outline-danger" style="flex-shrink:0;">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label font-weight-bold">Data da transferência</label>
                    <input type="date" class="form-control form-control-sm" id="transfData"
                           value="<?= date('Y-m-d') ?>">
                </div>

                <div class="form-group mb-1">
                    <label class="form-label font-weight-bold">
                        Motivo <span class="text-danger">*</span>
                    </label>
                    <textarea class="form-control form-control-sm" id="transfMotivo" rows="3"
                              placeholder="Descreva o motivo da transferência..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                <button type="button" id="btnConfirmarTransferencia" class="btn btn-primary">
                    <i class="fa-solid fa-right-left mr-1"></i>
                    <span id="transfBtnText">Confirmar Transferência</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════ Scripts (idênticos ao original) ════════════════════ -->
<script>
    /* ── DataTable + expand veículos ── */
    (function() {
        function initDT() {
            if (!window.jQuery || !$.fn.DataTable) return setTimeout(initDT, 60);

            const dt = $('#html_table').DataTable({
                responsive: false,
                autoWidth: false,
                scrollX: false,
                pageLength: 10,
                dom: 't<"dt-footer"<"dt-footer-info"i><"dt-footer-paging"p><"dt-footer-per"l>>',
                order: [
                    [0, 'desc']
                ],
                columnDefs: [{
                        targets: -1,
                        orderable: false,
                        searchable: false
                    }
                ],
                initComplete: function() {
                    const wrapper = document.getElementById('html_table_wrapper');
                    const body    = document.getElementById('assocViewList');
                    if (wrapper && body && !body.contains(wrapper)) {
                        body.appendChild(wrapper);
                    }
                    if (wrapper) wrapper.style.overflowX = window.innerWidth <= 768 ? 'auto' : 'visible';
                    // Move o footer para fora das views, assim fica visível em ambos os modos
                    const footer   = wrapper?.querySelector('.dt-footer');
                    const listView = document.getElementById('assocViewList');
                    if (footer && listView && listView.parentNode) {
                        listView.parentNode.insertBefore(footer, listView.nextSibling);
                    }
                },
                drawCallback: function() {
                    const api  = this.api();
                    const info = api.page.info();
                    const cur  = info.page + 1;
                    const tot  = info.pages;
                    const wrap = document.querySelector('.dt-footer-paging');
                    if (!wrap) return;

                    const btn = (icon, page, disabled, active) =>
                        `<button onclick="window._dtAssoc&&window._dtAssoc.page(${page-1}).draw(false)"
                            ${disabled ? 'disabled' : ''}
                            class="${active ? 'active' : ''}">${icon}</button>`;

                    const parts = [];
                    parts.push(btn('<i class="fa-solid fa-angles-left"></i>', 1, cur===1, false));
                    parts.push(btn('<i class="fa-solid fa-angle-left"></i>', cur-1, cur===1, false));

                    let from = Math.max(1, cur - 2);
                    let to   = Math.min(tot, from + 4);
                    from = Math.max(1, to - 4);
                    if (from > 1) parts.push('<span class="pg-ellipsis">…</span>');
                    for (let i = from; i <= to; i++) {
                        parts.push(btn(i, i, false, i === cur));
                    }
                    if (to < tot) parts.push('<span class="pg-ellipsis">…</span>');

                    parts.push(btn('<i class="fa-solid fa-angle-right"></i>', cur+1, cur===tot, false));
                    parts.push(btn('<i class="fa-solid fa-angles-right"></i>', tot, cur===tot, false));

                    const existing = wrap.querySelector('.asc-pag-controls');
                    const html = `<div class="asc-pag-controls">${parts.join('')}</div>`;
                    if (existing) existing.outerHTML = html;
                    else wrap.insertAdjacentHTML('afterbegin', html);

                    renderAssocCards();
                },
                language: {
                    sEmptyTable: "Nenhum registro encontrado",
                    sInfo: "Mostrando <strong>_START_–_END_</strong> de <strong>_TOTAL_</strong> associados",
                    sInfoEmpty: "Nenhum associado encontrado",
                    sInfoFiltered: "(filtrado de _MAX_)",
                    sLengthMenu: "Por página: _MENU_",
                    sSearch: "Buscar:",
                    sZeroRecords: "Nenhum registro encontrado",
                    oPaginate: {
                        sFirst: '«',
                        sPrevious: '‹',
                        sNext: '›',
                        sLast: '»'
                    }
                }
            });
            window._dtAssoc = dt;

            // Busca própria
            const search = document.getElementById('generalSearch');
            if (search) {
                let tid;
                search.addEventListener('input', function() {
                    clearTimeout(tid);
                    tid = setTimeout(() => dt.search(this.value).draw(), 250);
                });
            }

        }

        document.addEventListener('DOMContentLoaded', initDT);
    })();
</script>

<script>
    const ASSOC_NO_PHOTO = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 80 80'%3E%3Crect width='80' height='80' rx='40' fill='%23e2e8f0'/%3E%3Ccircle cx='40' cy='30' r='15' fill='%2394a3b8'/%3E%3Cellipse cx='40' cy='68' rx='23' ry='16' fill='%2394a3b8'/%3E%3C/svg%3E";

    function assocFotoUrl(raw) {
        if (!raw) return ASSOC_NO_PHOTO;
        return /^https?:\/\//i.test(raw) ? raw : window.location.origin + (raw.startsWith('/') ? '' : '/') + raw;
    }

    function renderAssocCards() {
        const grid = document.getElementById('assocCardGrid');
        if (!grid) return;
        const rows = document.querySelectorAll('#html_table tbody tr:not(.dataTables_empty)');
        if (!rows.length) {
            grid.innerHTML = '<div style="padding:40px;text-align:center;color:#94a3b8;">Nenhum associado encontrado.</div>';
            return;
        }
        grid.innerHTML = Array.from(rows).map(tr => {
            const id     = tr.dataset.id    || '';
            const nome   = tr.dataset.nome  || '';
            const cpf    = tr.dataset.cpf   || '';
            const tel    = tr.dataset.tel   || '';
            const foto   = assocFotoUrl(tr.dataset.foto || '');
            const cpfRaw = tr.dataset.cpfRaw || '';
            const placas = (tr.dataset.placas || '').split(' ').filter(Boolean);

            const plateBadges = placas.map(p =>
                `<span>${p}</span>`
            ).join('');

            return `<div class="assoc-card">
                <img class="assoc-card-avatar" src="${foto}" alt="Foto"
                     onerror="this.src='${ASSOC_NO_PHOTO}'">
                <div class="assoc-card-id">#${id}</div>
                <div class="assoc-card-name">${nome}</div>
                <div class="assoc-card-info">
                    ${cpf ? `<div>${cpf}</div>` : ''}
                    ${tel ? `<div>${tel}</div>` : ''}
                </div>
                ${plateBadges ? `<div class="assoc-card-plates">${plateBadges}</div>` : ''}
                <div class="assoc-card-actions">
                    <a href="#" class="vt-action-btn vt-action-btn--edit btn-editar-associado"
                       title="Editar associado" data-id="${id}">
                        <i class="fa-solid fa-pen"></i>
                    </a>
                    <a href="#" class="vt-action-btn vt-action-btn--car"
                       title="Adicionar veículo"
                       data-nome="${nome}" data-codigo="${id}" data-cpf="${cpfRaw}"
                       onclick="vtAbrirModalVeiculo(this);return false;"
                       style="width:38px;gap:2px;">
                        <i class="fa-solid fa-car" style="font-size:11px;"></i>
                        <i class="fa-solid fa-plus" style="font-size:7px;margin-left:1px;"></i>
                    </a>
                    <a href="<?= APP_URL ?>/associados/ficha.php?id=${id}"
                       class="vt-action-btn" title="Abrir ficha completa"
                       style="background:#edf2ff;color:#3b5bdb;border-color:#bac8ff;">
                        <i class="fa-solid fa-address-card"></i>
                    </a>
                </div>
            </div>`;
        }).join('');
    }

    function assocSetView(v) {
        const grid = document.getElementById('assocViewGrid');
        const list = document.getElementById('assocViewList');
        document.getElementById('btnAssocList')?.classList.toggle('active', v === 'list');
        document.getElementById('btnAssocGrid')?.classList.toggle('active', v === 'grid');
        if (grid) grid.style.display = v === 'grid' ? 'block' : 'none';
        if (list) list.style.display = v === 'list' ? '' : 'none';
        localStorage.setItem('assocView', v);
        if (v === 'grid') renderAssocCards();
    }

    document.addEventListener('DOMContentLoaded', function() {
        const saved = localStorage.getItem('assocView');
        if (saved === 'grid') assocSetView('grid');
    });
</script>

<?php /* ── Scripts de edição de associado/veículo: idênticos ao original ── */ ?>
<script>
    const ensureSelectValue = window.ensureSelectValue || function($sel, value) {
        if (!value && value !== 0) return;
        $sel.prop('disabled', false);
        const valStr = String(value);
        const $opt = $sel.find('option').filter(function() {
            return $(this).val() === valStr || $(this).text() === valStr;
        });
        if ($opt.length) $sel.val($opt.val());
        else $sel.append(new Option(valStr, valStr, true, true));
        $sel.trigger('change');
    };
</script>

<script>
    // Função global: chamada diretamente pelo onclick do botão de card
    function vtAbrirModalVeiculo(btn) {
        var nome   = btn.getAttribute('data-nome')   || '';
        var codigo = btn.getAttribute('data-codigo') || '';
        var cpf    = btn.getAttribute('data-cpf')    || '';
        var $m = $('#modalVeiculo');
        var $hidden = $m.find('#codigo_associado');
        $hidden.val(codigo);
        $m.attr({ 'data-assoc-nome': nome, 'data-assoc-codigo': codigo, 'data-assoc-cpf': codigo ? cpf : '' });
        var label = nome ? ('Novo Veículo para: ' + nome + ' - ID: ' + codigo) : (codigo ? 'Novo Veículo — Associado #' + codigo : 'Novo Veículo');
        $m.find('#tituloModalVeiculo').text(label);
        if ($m.find('#cpf_proprietario').length) $m.find('#cpf_proprietario').val(cpf.replace(/\D/g,''));
        if ($m.find('#nome_proprietario').length) $m.find('#nome_proprietario').val(nome);
        $m.modal('show');
    }
</script>
<script>
    (function() {
        function toDigits(v) {
            return String(v == null ? '' : v).replace(/\D/g, '');
        }

        function aplicarAssociado($modal, nome, codigo, cpf) {
            const $titulo = $modal.find('#tituloModalVeiculo');
            const $hidden = $modal.find('#codigo_associado');
            const $cpfProp = $modal.find('#cpf_proprietario');
            const $nomProp = $modal.find('#nome_proprietario');
            if (codigo != null && String(codigo).trim() !== '') {
                const label = nome ? `Novo Veículo para: ${nome} - ID: ${codigo}` : `Novo Veículo — Associado #${codigo}`;
                $titulo.text(label);
                $hidden.val(codigo);
                $modal.attr({
                    'data-assoc-nome': nome || '',
                    'data-assoc-codigo': codigo
                });
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

        $(document).on('input', '#cpf_proprietario', function() {
            this.value = this.value.replace(/\D/g, '');
        });
        // Handler para botões da tabela (view lista) — cards usam vtAbrirModalVeiculo() diretamente
        $(document).on('click', '#html_table [data-target="#modalVeiculo"]', function() {
            aplicarAssociado($('#modalVeiculo'), $(this).attr('data-nome'), $(this).attr('data-codigo'), $(this).attr('data-cpf'));
        });
        $(document).on('shown.bs.modal', '#modalVeiculo', function(e) {
            const $m = $(this);
            const $t = e.relatedTarget ? $(e.relatedTarget) : null;
            const codigo = $t ? $t.attr('data-codigo') : $m.attr('data-assoc-codigo');
            if (codigo != null && String(codigo).trim() !== '') {
                const nome = $t ? $t.attr('data-nome') : $m.attr('data-assoc-nome');
                const cpf  = $t ? $t.attr('data-cpf')  : $m.attr('data-assoc-cpf');
                aplicarAssociado($m, nome, codigo, cpf);
            }
        });
        $(document).on('hidden.bs.modal', '#modalVeiculo', function() {
            aplicarAssociado($(this), null, null, null);
        });
    })();
</script>

<script>
    (function() {
        const form = document.getElementById('formVeiculo');
        if (!form) return;
        const moneyToDecimal = v => {
            if (v == null) return '';
            let s = String(v).trim().replace(/[R$\s\u00A0]/g, '');
            if (!s) return '';
            const lc = s.lastIndexOf(','),
                ld = s.lastIndexOf('.');
            if (lc !== -1 || ld !== -1) {
                if (lc > ld) s = s.replace(/\./g, '').replace(',', '.');
                else s = s.replace(/,/g, '');
            } else {
                s = s.replace(/\D/g, '');
            }
            return s;
        };
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;
            const fd = new FormData(form);
            // Fallback: se codigo_associado estiver vazio, tenta pegar do atributo do modal
            if (!fd.get('codigo_associado')) {
                const codigoFallback = $('#modalVeiculo').attr('data-assoc-codigo') || '';
                if (codigoFallback) fd.set('codigo_associado', codigoFallback);
            }

            // Validação client-side com Swal específico por campo
            const assocId = (fd.get('codigo_associado') || '').trim();
            const placa   = (fd.get('placa') || '').replace(/[^A-Z0-9]/gi, '');
            const chassi  = (fd.get('chassi') || '').trim();
            const campos  = [
                [!assocId, 'Associado não identificado', 'Feche e reabra o modal para tentar novamente.'],
                [!placa,   'Placa obrigatória',          'Preencha a placa do veículo (aba Dados do Veículo).'],
                [!chassi,  'Chassi obrigatório',         'Preencha o chassi do veículo (aba Dados do Veículo).'],
            ];
            for (const [erro, titulo, texto] of campos) {
                if (erro) {
                    Swal.fire({ icon: 'warning', title: titulo, text: texto });
                    if (submitBtn) submitBtn.disabled = false;
                    return;
                }
            }

            if (fd.get('valor')) fd.set('valor', moneyToDecimal(fd.get('valor')));
            if (fd.get('valorCobertura')) fd.set('valorCobertura', moneyToDecimal(fd.get('valorCobertura')));
            fetch(form.action, {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(json => {
                    if (json.success) {
                        Swal.fire({
                            icon: 'success',
                            title: (fd.get('acao') === 'atualizar' ? 'Veículo atualizado!' : 'Veículo cadastrado!'),
                            text: json.message || 'Operação concluída.'
                        }).then(() => location.reload());
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro',
                            text: json.message || 'Não foi possível salvar.'
                        });
                    }
                })
                .catch(() => Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: 'Falha ao comunicar com o servidor.'
                }))
                .finally(() => {
                    if (submitBtn) submitBtn.disabled = false;
                });
        });
    })();
</script>

<!-- Handler: editar associado (abre #CadastrarCliente em modo editar) -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ASSOC_ACTION = '<?= ACTION_URL ?>/pessoas.php';

    $(document).on('click', '.btn-editar-associado', function (e) {
        e.preventDefault();
        const id = $(this).data('id');
        if (!id) return;

        fetch(`${ASSOC_ACTION}?acao=obter&id=${encodeURIComponent(id)}&_=${Date.now()}`)
            .then(r => r.json())
            .then(j => {
                if (!j.success) { Swal.fire('Erro', j.message || 'Não foi possível carregar.', 'error'); return; }
                const p  = j.data || j;
                const $f = $('#formCadastroPessoa');

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

                // UF → popula cidades (sync) → seleciona cidade
                $('#uf').val(p.PES_UF || '').trigger('change');
                if (p.PES_CIDADE) {
                    const $cid = $('#cidade');
                    if (!$cid.find('option[value="' + p.PES_CIDADE + '"]').length) {
                        $cid.append(new Option(p.PES_CIDADE, p.PES_CIDADE, true, true));
                    } else {
                        $cid.val(p.PES_CIDADE);
                    }
                }

                // foto
                if (p.PES_FOTO) {
                    const url = /^https?:\/\//i.test(p.PES_FOTO)
                        ? p.PES_FOTO
                        : window.location.origin + (p.PES_FOTO.startsWith('/') ? '' : '/') + p.PES_FOTO;
                    $('#fotoPreview').attr('src', url + '?v=' + Date.now());
                }

                // modo editar
                document.getElementById('pessoaId').value             = id;
                document.getElementById('pessoaAcao').value            = 'editar';
                document.getElementById('modalAssocTitulo').textContent = 'Editar Associado — ' + (p.PES_NOME || '');
                document.getElementById('modalAssocIcon').className     = 'fa fa-user-pen';

                $('#CadastrarCliente').modal('show');
            })
            .catch(() => Swal.fire('Erro', 'Falha ao comunicar com o servidor.', 'error'));
    });
});
</script>
<!-- Handler: editar veículo (abre modal preenchido) -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const VEI_ACTION = '<?= ACTION_URL ?>/veiculos.php';

    $(document).on('click', '.btn-editar-veiculo', function (e) {
        e.preventDefault();
        const $btn       = $(this);
        const veiculoId  = $btn.data('veiculo-id')  || '';
        const contratoId = $btn.data('contrato-id') || '';
        if (!veiculoId && !contratoId) return;

        const url = contratoId
            ? `${VEI_ACTION}?acao=obter&contrato_id=${encodeURIComponent(contratoId)}&_=${Date.now()}`
            : `${VEI_ACTION}?acao=obter&id=${encodeURIComponent(veiculoId)}&_=${Date.now()}`;

        fetch(url)
            .then(r => r.json())
            .then(j => {
                if (!j.success) { Swal.fire('Erro', j.message || 'Não foi possível carregar.', 'error'); return; }

                const v    = j.veiculo   || {};
                const vis  = j.vistoria  || {};
                const contr = j.contrato || {};
                const visItens = j.vistoria_itens || [];
                const imgs = j.imagens   || [];

                const $m = $('#modalVeiculo');
                const $f = $('#formVeiculo');

                // modo edição
                $f.find('#acao').val('atualizar');
                $f.find('#codigo_veiculo').val(v.VEI_CODIGO_PK || '');
                $f.find('#codigo_contrato').val(contr.CTR_CODIGO_PK || '');
                $f.find('#codigo_associado').val(v.PES_CODIGO_FK || '');
                $m.find('#tituloModalVeiculo').text('Editar Veículo — ' + (v.VEI_PLACA || ''));

                // aba Dados
                $f.find('#placa').val(v.VEI_PLACA || '');
                $f.find('#chassi').val(v.VEI_CHASSI || '');
                $f.find('#renavam').val(v.VEI_RENAVAM || '');
                $f.find('#ano').val(v.VEI_ANO_FABRICACAO || '');
                $f.find('#anoModelo').val(v.VEI_ANO_MODELO || '');
                $f.find('#combustivel').val(v.VEI_COMBUSTIVEL || '');
                $f.find('#codigoFipe').val(v.VEI_CODIGO_FIPE || '');
                $f.find('[name="cambio"]').val(v.VEI_CAMBIO || '');
                $f.find('[name="cpf_proprietario"]').val(v.VEI_CPF_CNPJ_PROPRIETARIO || '');
                $f.find('[name="nome_proprietario"]').val(v.VEI_NOME_PROPRIETARIO || '');
                // hidden inputs (usados no POST)
                $f.find('#marca').val(v.VEI_MARCA || '');
                $f.find('#modelo').val(v.VEI_MODELO || '');

                // tipoVeiculo: usa .val() direto (sem trigger) para não disparar o fipe.js
                // que limparia os selects de marca/modelo logo abaixo
                $f.find('#tipoVeiculo').val(v.VEI_TIPO || '');
                if (window.ensureSelectValue) {
                    window.ensureSelectValue($f.find('#cor'), v.VEI_COD_COR_FK);
                } else {
                    $f.find('#cor').val(v.VEI_COD_COR_FK || '');
                }

                // selects visíveis do FIPE: adiciona o valor salvo como opção selecionada
                // (o usuário pode ver o que estava antes; se quiser trocar, usa os dropdowns FIPE)
                (function populateFipeSelect(sel, txt) {
                    if (!txt) return;
                    const $s = $f.find(sel);
                    $s.prop('disabled', false).empty().append(new Option(txt, txt, true, true));
                })('#marcas', v.VEI_MARCA || '');
                (function populateFipeSelect(sel, txt) {
                    if (!txt) return;
                    const $s = $f.find(sel);
                    $s.prop('disabled', false).empty().append(new Option(txt, txt, true, true));
                })('#modelos', v.VEI_MODELO || '');
                (function() {
                    const anoFab = v.VEI_ANO_FABRICACAO || '';
                    const anoMod = v.VEI_ANO_MODELO || '';
                    const label  = anoFab && anoMod ? anoFab + '/' + anoMod : (anoFab || anoMod || '');
                    if (!label) return;
                    const $s = $f.find('#anos');
                    $s.prop('disabled', false).empty().append(new Option(label, label, true, true));
                })();

                // UF + cidade do veículo
                $f.find('#ufCarro').val(v.VEI_UF || '').trigger('change');
                if (v.VEI_UF && window.popularCidadesUFCarro) {
                    window.popularCidadesUFCarro(v.VEI_UF, v.VEI_CIDADE || null);
                }

                // valor (FIPE)
                $f.find('#valor').val(contr.CTR_VALOR_VEICULO || '');
                $f.find('#valorCobertura').val(contr.CTR_VALOR_COBERTURA || '');

                // aba Contrato (aba Dados)
                if (window.ensureSelectValue) {
                    window.ensureSelectValue($f.find('#grupo'), contr.GRU_CODIGO_FK);
                    window.ensureSelectValue($f.find('#combo'), contr.COM_CODIGO_FK);
                    window.ensureSelectValue($f.find('#rastreador'), contr.CON_RASTREADOR_FK);
                }
                $f.find('#adesao').val(contr.CTR_VALOR_ADESAO || '');
                $f.find('#mensalidade').val(contr.CTR_VALOR_MENSALIDADE || '');
                $f.find('#valorCombo').val(contr.CTR_VALOR_COMBO || '');
                $f.find('#valorRastreador').val(contr.CON_VALOR_RASTREADOR || '');
                $f.find('#totalFinal').val(contr.CTR_VALOR_TOTAL || '');
                // normaliza acentos (ex: BANCÁRIO → BANCARIO) para bater com os values do select
                const normTipoBoleto = (contr.CTR_TIPO_BOLETO || '')
                    .normalize('NFD').replace(/[̀-ͯ]/g, '').toUpperCase().trim();
                $f.find('#tipoBoleto').val(normTipoBoleto);
                // recalcula o total com os valores vindos do banco
                if (window.atualizarTotalVeiculo) window.atualizarTotalVeiculo();

                // aba Vistoria — vistoriador
                $f.find('#vistoriado_fk').val(contr.CTR_VISTORIADO_FK || '');

                // aba Vistoria — checkboxes (vis_itens agora é [{id, chave}])
                $f.find('input[name="vis_checked[]"]').prop('checked', false);
                visItens.forEach(function (entry) {
                    var id    = entry.id    || entry;   // compat: aceita int legacy ou objeto {id, chave}
                    var chave = entry.chave || entry;
                    // tenta por ID primeiro, depois por data-key (fallback)
                    var $cb = $f.find('input[name="vis_checked[]"][value="' + id + '"]');
                    if (!$cb.length) $cb = $f.find('#visCheckGrid .vis-item[data-key="' + chave + '"] input');
                    $cb.prop('checked', true);
                });
                $f.find('[name="codigo_vidro"]').val(vis.VIS_CODIGO_VIDRO || '');
                $f.find('[name="pneus"]').val(vis.VIS_PNEUS || 'Bons');
                $f.find('[name="observacao_vistoria"]').val(vis.VIS_OBSERVACAO || '');

                // aba Imagens — mostrar existentes
                const $preview = $('#previewImagens');
                // mantém só os novos (isNew), remove os do servidor anteriores
                $preview.find('[data-is-new!="1"]').remove();
                imgs.forEach(function (img, idx) {
                    $preview.prepend(`
                        <div class="col-md-3 mb-3" data-file-name="" data-is-new="0">
                            <div class="card">
                                <img src="${img.url}" class="card-img-top img-thumbnail" style="height:150px;object-fit:cover;">
                                <div class="card-body p-2">
                                    <span class="badge badge-${img.chassi === 'SIM' ? 'success' : 'secondary'} small">${img.chassi === 'SIM' ? 'Chassi' : ''}</span>
                                </div>
                            </div>
                        </div>`);
                });

                $m.modal('show');
            })
            .catch(() => Swal.fire('Erro', 'Falha ao comunicar com o servidor.', 'error'));
    });

    // ao fechar: volta ao modo cadastro
    $('#modalVeiculo').on('hidden.bs.modal', function () {
        const $f = $('#formVeiculo');
        $f.find('#acao').val('cadastrar');
        $f.find('#codigo_veiculo, #codigo_contrato').val('');
        $f.find('#vistoriado_fk').val('');
        // reseta selects FIPE visíveis para estado inicial (disabled, vazio)
        ['#marcas', '#modelos', '#anos'].forEach(function(sel) {
            $f.find(sel).prop('disabled', true).empty().append(new Option('Selecione', '', true, false));
        });
        $('#previewImagens').find('[data-is-new!="1"]').remove();
    });
});
</script>

<!-- Handler: cancelar / reativar contrato -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const CANC_ACTION = '<?= ACTION_URL ?>/veiculos.php';
    const CSRF        = '<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>';

    const MODES = {
        cancelar: {
            headerBg:    '#c92a2a',
            icon:        'fa-solid fa-ban mr-2',
            title:       'Cancelar Contrato',
            porLabel:    'Cancelado por',
            dataLabel:   'Data do cancelamento',
            placeholder: 'Descreva o motivo do cancelamento...',
            btnClass:    'btn-danger',
            btnRemove:   'btn-success',
            btnIcon:     'fa-solid fa-ban mr-1',
            btnText:     'Confirmar Cancelamento',
            spinner:     'Cancelando...',
        },
        reativar: {
            headerBg:    '#2f9e44',
            icon:        'fa-solid fa-rotate-left mr-2',
            title:       'Reativar Contrato',
            porLabel:    'Reativado por',
            dataLabel:   'Data da reativação',
            placeholder: 'Descreva o motivo da reativação...',
            btnClass:    'btn-success',
            btnRemove:   'btn-danger',
            btnIcon:     'fa-solid fa-rotate-left mr-1',
            btnText:     'Confirmar Reativação',
            spinner:     'Reativando...',
        }
    };

    function openCancModal(mode, contratoId) {
        const cfg = MODES[mode];
        $('#cancModalMode').val(mode);
        $('#cancelContratoId').val(contratoId);
        $('#motivoCancelamento').val('');
        $('#dataCancelamento').val(new Date().toISOString().split('T')[0]);

        $('#cancModalHeader').css('background', cfg.headerBg);
        $('#cancModalIcon').attr('class', cfg.icon);
        $('#cancModalTitle').text(cfg.title);
        $('#cancPorLabel').text(cfg.porLabel);
        $('#cancDataLabel').text(cfg.dataLabel);
        $('#motivoCancelamento').attr('placeholder', cfg.placeholder);
        $('#cancMotivoLabel').html('Motivo <span class="text-danger">*</span>');
        $('#btnConfirmarCancelamento')
            .removeClass(cfg.btnRemove).addClass(cfg.btnClass);
        $('#cancBtnIcon').attr('class', cfg.btnIcon);
        $('#cancBtnText').text(cfg.btnText);

        $('#modalCancelarVeiculo').modal('show');
    }

    // Botão cancelar/reativar na sub-tabela de veículos
    $(document).on('click', '.btn-cancelar-veiculo', function (e) {
        e.preventDefault();
        const $btn       = $(this);
        const contratoId = $btn.data('contrato-id') || '';
        const status     = ($btn.data('status') || '').toUpperCase();

        if (!contratoId) {
            Swal.fire('Atenção', 'Nenhum contrato encontrado para este veículo.', 'warning');
            return;
        }

        openCancModal(status === 'C' ? 'reativar' : 'cancelar', contratoId);
    });

    // Confirma a operação (cancelamento ou reativação)
    $('#btnConfirmarCancelamento').on('click', function () {
        const mode   = $('#cancModalMode').val();
        const cfg    = MODES[mode] || MODES.cancelar;
        const motivo = $('#motivoCancelamento').val().trim();

        if (!motivo) {
            Swal.fire('Atenção', 'Informe o motivo.', 'warning');
            $('#motivoCancelamento').focus();
            return;
        }

        const btn  = this;
        btn.disabled = true;
        const orig = btn.innerHTML;
        btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-1"></i> ${cfg.spinner}`;

        const fd = new FormData();
        fd.append('acao',        mode);
        fd.append('csrf',        CSRF);
        fd.append('contrato_id', $('#cancelContratoId').val());
        fd.append('motivo',      motivo);
        fd.append('data_evento', $('#dataCancelamento').val());
        fd.append('cancelado_por', $('#canceladoPor').val());

        fetch(CANC_ACTION, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(j => {
                if (j.success) {
                    $('#modalCancelarVeiculo').modal('hide');
                    const title = mode === 'reativar' ? 'Reativado!' : 'Cancelado!';
                    Swal.fire({ icon: 'success', title, text: j.message })
                        .then(() => location.reload());
                } else {
                    Swal.fire({ icon: 'error', title: 'Erro', text: j.message || 'Não foi possível concluir.' });
                }
            })
            .catch(() => Swal.fire({ icon: 'error', title: 'Erro', text: 'Falha ao comunicar com o servidor.' }))
            .finally(() => { btn.disabled = false; btn.innerHTML = orig; });
    });
});
</script>

<!-- Handler: transferir veículo -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const TRANSF_ACTION  = '<?= ACTION_URL ?>/veiculos.php';
    const BUSCA_ACTION   = '<?= ACTION_URL ?>/pessoas.php';
    const CSRF           = '<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>';

    // ─── Abre o modal ───
    $(document).on('click', '.btn-transferir-veiculo', function (e) {
        e.preventDefault();
        const $btn       = $(this);
        const contratoId = $btn.data('contrato-id') || '';
        const veiculoId  = $btn.data('veiculo-id')  || '';
        const pessoaNome = $btn.data('pessoa-nome')  || '';

        if (!contratoId || !veiculoId) {
            Swal.fire('Atenção', 'Dados do veículo/contrato não encontrados.', 'warning');
            return;
        }

        $('#transfContratoId').val(contratoId);
        $('#transfVeiculoId').val(veiculoId);
        $('#transfDestinatarioId').val('');
        $('#transfAssoAtual').val(pessoaNome);
        $('#transfBusca').val('').prop('disabled', false);
        $('#transfDropdown').hide().empty();
        $('#transfSelecionado').hide();
        $('#transfNomeSel, #transfCpfSel').text('');
        $('#transfMotivo').val('');
        $('#transfData').val(new Date().toISOString().split('T')[0]);

        $('#modalTransferirVeiculo').modal('show');
    });

    // ─── Autocomplete ───
    let buscaTid;
    $('#transfBusca').on('input', function () {
        const q = $(this).val().trim();
        clearTimeout(buscaTid);
        $('#transfDropdown').hide().empty();
        if (q.length < 2) return;

        buscaTid = setTimeout(function () {
            fetch(BUSCA_ACTION + '?acao=buscar&q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(function (rows) {
                    const $dd = $('#transfDropdown').empty();
                    if (!rows.length) {
                        $dd.html('<div style="padding:10px 14px;color:#868e96;font-size:13px;">Nenhum associado encontrado.</div>').show();
                        return;
                    }
                    rows.forEach(function (p) {
                        const $item = $('<div>')
                            .css({padding:'9px 14px',cursor:'pointer',borderBottom:'1px solid #f0f0f0',fontSize:'13.5px'})
                            .html(`<strong>${p.PES_NOME}</strong> <span style="color:#868e96;font-size:12px;">#${p.PES_CODIGO_PK}</span>` +
                                  (p.PES_CPF_CNPJ ? `<br><small style="color:#666;">${p.PES_CPF_CNPJ}</small>` : ''))
                            .on('mouseenter', function () { $(this).css('background','#e8edff'); })
                            .on('mouseleave', function () { $(this).css('background','#fff'); })
                            .on('click', function () {
                                $('#transfDestinatarioId').val(p.PES_CODIGO_PK);
                                $('#transfNomeSel').text(p.PES_NOME + ' — #' + p.PES_CODIGO_PK);
                                $('#transfCpfSel').text(p.PES_CPF_CNPJ || '');
                                $('#transfSelecionado').css('display','flex');
                                $('#transfBusca').val('').prop('disabled', true);
                                $dd.hide().empty();
                            });
                        $dd.append($item);
                    });
                    $dd.show();
                })
                .catch(function () { });
        }, 300);
    });

    // Fecha dropdown ao clicar fora
    $(document).on('click', function (e) {
        if (!$(e.target).closest('#transfBusca, #transfDropdown').length) {
            $('#transfDropdown').hide();
        }
    });

    // Limpar seleção
    $('#transfLimparSel').on('click', function () {
        $('#transfDestinatarioId').val('');
        $('#transfSelecionado').hide();
        $('#transfNomeSel, #transfCpfSel').text('');
        $('#transfBusca').val('').prop('disabled', false).focus();
    });

    // ─── Confirmar transferência ───
    $('#btnConfirmarTransferencia').on('click', function () {
        const destinatarioId = $('#transfDestinatarioId').val();
        const motivo         = $('#transfMotivo').val().trim();

        if (!destinatarioId) {
            Swal.fire('Atenção', 'Selecione o associado de destino.', 'warning');
            $('#transfBusca').focus();
            return;
        }
        if (!motivo) {
            Swal.fire('Atenção', 'Informe o motivo da transferência.', 'warning');
            $('#transfMotivo').focus();
            return;
        }

        const btn  = this;
        btn.disabled = true;
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Transferindo...';

        const fd = new FormData();
        fd.append('acao',            'transferir');
        fd.append('csrf',            CSRF);
        fd.append('contrato_id',     $('#transfContratoId').val());
        fd.append('veiculo_id',      $('#transfVeiculoId').val());
        fd.append('destinatario_id', destinatarioId);
        fd.append('motivo',          motivo);
        fd.append('data_evento',     $('#transfData').val());
        fd.append('transferido_por', '<?= htmlspecialchars($_SESSION['SessUsuNome'] ?? 'Usuário', ENT_QUOTES, 'UTF-8') ?>');

        fetch(TRANSF_ACTION, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(function (j) {
                if (j.success) {
                    $('#modalTransferirVeiculo').modal('hide');
                    Swal.fire({ icon: 'success', title: 'Transferido!', text: j.message })
                        .then(() => location.reload());
                } else {
                    Swal.fire({ icon: 'error', title: 'Erro', text: j.message || 'Não foi possível concluir.' });
                }
            })
            .catch(function () {
                Swal.fire({ icon: 'error', title: 'Erro', text: 'Falha ao comunicar com o servidor.' });
            })
            .finally(function () {
                btn.disabled = false;
                btn.innerHTML = orig;
            });
    });

    // Reseta ao fechar
    $('#modalTransferirVeiculo').on('hidden.bs.modal', function () {
        $('#transfDestinatarioId').val('');
        $('#transfBusca').val('').prop('disabled', false);
        $('#transfDropdown').hide().empty();
        $('#transfSelecionado').hide();
        $('#transfMotivo').val('');
    });
});
</script>

<script src="<?= APP_URL ?>/associados/carregar_cidades.js"></script>