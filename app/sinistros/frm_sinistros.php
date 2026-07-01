<?php
if (!defined('PATH_INC')) require_once __DIR__ . '/../../inc/config.php';
require_once PATH_INC . '/db.php';
require_once PATH_INC . '/csrf.php';
$csrf = csrf_token();

$UFS = ['AC','AL','AM','AP','BA','CE','DF','ES','GO','MA','MG','MS','MT',
        'PA','PB','PE','PI','PR','RJ','RN','RO','RR','RS','SC','SE','SP','TO'];
$TIPOS_OCORRENCIA = ['ROUBO','FURTO','COLISÃO','INCÊNDIO','ALAGAMENTO','QUEBRA DE VIDRO',
                     'DANO ELÉTRICO','ACIDENTE NATURAL','TERCEIROS','OUTROS'];
?>
<link rel="stylesheet" href="../valiantus-tables.css">

<style>
/* ── Busca ── */
.sin-search-card { background:#fff; border-radius:16px; border:1px solid #e9ecef; box-shadow:0 4px 20px rgba(30,40,80,.07); padding:28px; margin-bottom:22px; }
.sin-search-row  { display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; }
.sin-search-row .form-group { margin:0; flex:1; min-width:220px; }
.sin-search-row label { font-size:12px; font-weight:700; color:#495057; display:block; margin-bottom:6px; }
.sin-search-row .form-control { height:42px; border:1.5px solid #dbe2ea; border-radius:11px; font-size:13.5px; padding:0 14px; }
.sin-search-row .form-control:focus { border-color:#3b5bdb; box-shadow:0 0 0 3px rgba(59,91,219,.1); outline:none; }
.sin-btn-search { height:42px; padding:0 22px; border-radius:11px; background:#3b5bdb; color:#fff; border:none; font-weight:700; font-size:14px; cursor:pointer; transition:.15s; white-space:nowrap; }
.sin-btn-search:hover { background:#2f52d6; transform:translateY(-1px); box-shadow:0 6px 16px rgba(59,91,219,.25); }

/* ── Resultados ── */
.sin-results { display:none; }
.sin-veiculo-card { background:#fff; border:1.5px solid #e9ecef; border-radius:14px; padding:16px 20px; margin-bottom:12px; display:flex; align-items:center; gap:18px; cursor:pointer; transition:.15s; }
.sin-veiculo-card:hover { border-color:#3b5bdb; box-shadow:0 4px 16px rgba(59,91,219,.12); transform:translateY(-1px); }
.sin-veiculo-card.selected { border-color:#2f9e44; background:#f1fbf4; }
.sin-vei-icon { width:50px; height:50px; border-radius:14px; background:#e8edff; color:#3b5bdb; display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0; }
.sin-vei-info { flex:1; }
.sin-vei-placa { font-size:18px; font-weight:800; font-family:monospace; color:#1a1d2e; }
.sin-vei-model { font-size:13px; color:#868e96; margin-top:2px; }
.sin-vei-assoc { font-size:13.5px; font-weight:600; color:#3b5bdb; margin-top:4px; }
.sin-vei-cpf   { font-size:12px; color:#868e96; }
.sin-vei-status span { font-size:11.5px; font-weight:700; padding:3px 10px; border-radius:20px; }
.sin-vei-status .s-a { background:#d3f9d8; color:#2f9e44; }
.sin-vei-status .s-c { background:#ffe3e3; color:#c92a2a; }
.sin-vei-actions { display:flex; gap:8px; flex-shrink:0; }
.sin-btn-novo { padding:8px 16px; border-radius:9px; background:#3b5bdb; color:#fff; border:none; font-size:13px; font-weight:700; cursor:pointer; transition:.15s; }
.sin-btn-novo:hover { background:#2f52d6; }
.sin-btn-hist { padding:8px 16px; border-radius:9px; background:#f0f3ff; color:#3b5bdb; border:1.5px solid #c5d0ff; font-size:13px; font-weight:700; cursor:pointer; transition:.15s; }
.sin-btn-hist:hover { background:#e0e8ff; }

/* ── Histórico ── */
.sin-hist-wrap { background:#f8f9fb; border-radius:14px; border:1px solid #e9ecef; margin-bottom:22px; overflow:hidden; }
.sin-hist-head { padding:14px 20px; background:#fff; border-bottom:1px solid #e9ecef; display:flex; align-items:center; gap:10px; }
.sin-hist-head h4 { font-size:15px; font-weight:700; color:#1a1d2e; margin:0; }
.sin-hist-table { width:100%; border-collapse:collapse; font-size:13px; }
.sin-hist-table th { background:#3b5bdb; color:#fff; padding:11px 14px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; }
.sin-hist-table td { padding:10px 14px; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
.sin-hist-table tr:last-child td { border-bottom:none; }
.sin-hist-table tr:hover td { background:#f8f9fb; }

/* ── Status badges ── */
.sin-badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
.sin-badge-aberto    { background:#fff3cd; color:#856404; }
.sin-badge-encerrado { background:#d3f9d8; color:#2f9e44; }
.sin-badge-cancelado { background:#ffe3e3; color:#c92a2a; }

/* (estilos do modal movidos para modal_sin.php) */

/* ── SweetAlert2 animação moderna ── */
@keyframes vtSwalIn {
    0%   { opacity:0; transform:scale(.88) translateY(-24px); }
    70%  { opacity:1; transform:scale(1.03) translateY(4px); }
    100% { opacity:1; transform:scale(1)   translateY(0); }
}
@keyframes vtSwalOut {
    0%   { opacity:1; transform:scale(1)   translateY(0); }
    100% { opacity:0; transform:scale(.9)  translateY(-16px); }
}
.vt-swal-in  { animation: vtSwalIn  .32s cubic-bezier(.34,1.4,.64,1) both !important; }
.vt-swal-out { animation: vtSwalOut .18s ease-in               both !important; }
.vt-swal-popup {
    border-radius: 20px !important;
    box-shadow: 0 24px 60px rgba(22,28,45,.22) !important;
    padding-bottom: 24px !important;
}
.vt-swal-popup .swal2-title  { font-size: 1.25rem !important; font-weight: 800 !important; }
.vt-swal-popup .swal2-icon   { margin-top: 20px !important; }
.vt-swal-popup .swal2-confirm,
.vt-swal-popup .swal2-cancel { border-radius: 10px !important; font-weight: 700 !important; height: 40px !important; padding: 0 24px !important; }

/* Garante que toast apareça acima do modal Bootstrap (z-index: 1050) */
.swal2-container { z-index: 99999 !important; }

/* ── Listagem geral ── */
.sin-list-card { background:#fff; border:1px solid #e9ecef; border-radius:16px; box-shadow:0 4px 20px rgba(30,40,80,.07); overflow:hidden; margin-bottom:22px; }
.sin-list-head { padding:12px 22px; border-bottom:1px solid #e9ecef; display:flex; align-items:center; gap:10px; }
.sin-list-head h4 { font-size:15px; font-weight:800; color:#1a1d2e; margin:0; white-space:nowrap; }
.sin-list-filters { display:flex; gap:8px; align-items:center; flex:1; min-width:0; }
.sin-list-filters .form-control-q { flex:1; min-width:0; height:36px; border:1.5px solid #dbe2ea; border-radius:9px; font-size:13px; padding:0 12px; }
.sin-list-filters .form-control-q:focus { border-color:#3b5bdb; box-shadow:0 0 0 3px rgba(59,91,219,.1); outline:none; }
.sin-list-filters .form-control { height:36px; border:1.5px solid #dbe2ea; border-radius:9px; font-size:13px; padding:0 12px; }
.sin-list-filters .form-control:focus { border-color:#3b5bdb; box-shadow:0 0 0 3px rgba(59,91,219,.1); outline:none; }
.sin-list-refresh { height:36px; padding:0 16px; border-radius:9px; background:#f0f3ff; color:#3b5bdb; border:1.5px solid #c5d0ff; font-size:13px; font-weight:700; cursor:pointer; transition:.15s; }
.sin-list-refresh:hover { background:#e0e8ff; }
.sin-list-table { width:100%; border-collapse:collapse; font-size:12.5px; }
.sin-list-table th { background:#1a1d2e; color:#fff; padding:10px 14px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; white-space:nowrap; }
.sin-list-table td { padding:9px 14px; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
.sin-list-table tbody tr:last-child td { border-bottom:none; }
.sin-list-table tbody tr:hover td { background:#f8f9fb; }
.sin-list-empty { text-align:center; padding:32px; color:#868e96; }
.sin-list-wrap { overflow-x:auto; }

/* ── Toggle grade/lista ── */
.sin-view-toggle { display:flex; gap:4px; }
.sin-view-toggle button { width:32px; height:32px; border:1.5px solid #e2e8f0; background:#fff; border-radius:7px; cursor:pointer; color:#94a3b8; display:flex; align-items:center; justify-content:center; font-size:13px; transition:.15s; }
.sin-view-toggle button.active { background:#3b5bdb; border-color:#3b5bdb; color:#fff; }
.sin-view-toggle button:not(.active):hover { background:#edf2ff; border-color:#3b5bdb; color:#3b5bdb; }

/* ── Cards de sinistro ── */
#sinViewGrid { display:none; padding:16px 20px; }
.sin-card-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); gap:14px; }
.sin-card { background:#fff; border:1.5px solid #e2e8f0; border-radius:13px; padding:16px; display:flex; flex-direction:column; gap:8px; transition:box-shadow .15s, border-color .15s; }
.sin-card:hover { box-shadow:0 4px 16px rgba(59,91,219,.10); border-color:#bac8ff; }
.sin-card-top { display:flex; align-items:center; justify-content:space-between; }
.sin-card-id { font-size:11.5px; font-weight:700; color:#94a3b8; }
.sin-card-plate { font-size:20px; font-weight:800; color:#1a1d2e; letter-spacing:.04em; }
.sin-card-type { font-size:11.5px; color:#64748b; font-weight:600; text-transform:uppercase; letter-spacing:.04em; }
.sin-card-assoc { font-size:12.5px; font-weight:600; color:#3b5bdb; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.sin-card-date { font-size:11.5px; color:#94a3b8; }
.sin-card-actions { display:flex; gap:6px; margin-top:4px; }
.sin-card-actions a, .sin-card-actions button { height:28px; padding:0 10px; border-radius:7px; background:#f1f5f9; border:1px solid #e2e8f0; color:#475569; font-size:12px; cursor:pointer; display:inline-flex; align-items:center; gap:5px; text-decoration:none; transition:.15s; }
.sin-card-actions a:hover, .sin-card-actions button:hover { background:#edf2ff; border-color:#3b5bdb; color:#3b5bdb; }

/* ── Paginação ── */
.sin-list-footer { display:flex; align-items:center; gap:12px; padding:11px 20px; border-top:1px solid #f0f0f0; flex-wrap:wrap; }
.sin-list-footer-info { flex:1; font-size:12.5px; color:#64748b; }
.sin-pag-controls { display:flex; align-items:center; gap:4px; }
.sin-pag-controls button { background:#fff; border:1px solid #e2e8f0; color:#334155; min-width:32px; height:32px; padding:0 8px; border-radius:7px; font-size:12px; font-family:inherit; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:.15s; }
.sin-pag-controls button:hover:not(:disabled) { background:#edf2ff; border-color:#3b5bdb; color:#3b5bdb; }
.sin-pag-controls button:disabled { opacity:.38; cursor:not-allowed; }
.sin-pag-controls button.active { background:#3b5bdb; border-color:#3b5bdb; color:#fff; }
.sin-pag-controls .pg-ellipsis { font-size:12px; color:#94a3b8; padding:0 4px; user-select:none; }
.sin-list-footer-per { flex:1; display:flex; justify-content:flex-end; align-items:center; gap:7px; font-size:12.5px; color:#64748b; }
.sin-list-footer-per select { border:1.5px solid #e2e8f0; border-radius:8px; font-size:12.5px; padding:3px 8px; color:#334155; cursor:pointer; }
</style>

<!-- ══════════ PÁGINA ══════════ -->
<div class="vt-page">

    <!-- ══════════ LISTAGEM GERAL ══════════ -->
    <div class="sin-list-card">
        <div class="sin-list-head">
            <h4><i class="fa-solid fa-car-burst mr-2" style="color:#c92a2a;"></i>Sinistros</h4>
            <div class="sin-list-filters">
                <input type="text" id="sinListQ" class="form-control-q" placeholder="Buscar por placa, associado, tipo, B.O...">
                <select id="sinListStatus" class="form-control" style="width:140px;height:36px;border:1.5px solid #dbe2ea;border-radius:9px;font-size:13px;padding:0 10px;">
                    <option value="">Todos</option>
                    <option value="ABERTO">Aberto</option>
                    <option value="ENCERRADO">Encerrado</option>
                    <option value="CANCELADO">Cancelado</option>
                </select>
                <button type="button" class="sin-list-refresh" id="sinListRefresh" title="Atualizar">
                    <i class="fa-solid fa-rotate-right"></i>
                </button>
                <div class="sin-view-toggle">
                    <button type="button" id="btnSinList" title="Lista" class="active"><i class="fa-solid fa-list"></i></button>
                    <button type="button" id="btnSinGrid" title="Grade"><i class="fa-solid fa-grip"></i></button>
                </div>
            </div>
            <button type="button" class="sin-btn-novo" id="sinBtnNovoGeral" style="height:36px;font-size:13px;">
                <i class="fa-solid fa-plus mr-1"></i>Novo Sinistro
            </button>
        </div>
        <div id="sinViewList" class="sin-list-wrap">
            <table class="sin-list-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Data Ocorr.</th>
                        <th>Tipo</th>
                        <th>Placa</th>
                        <th>Associado</th>
                        <th>Nº B.O.</th>
                        <th>Status</th>
                        <th>WA</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="sinListTbody">
                    <tr><td colspan="9" class="sin-list-empty"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Carregando...</td></tr>
                </tbody>
            </table>
        </div>
        <div id="sinViewGrid">
            <div class="sin-card-grid" id="sinCardGrid"></div>
        </div>
        <div id="sinListFooter" class="sin-list-footer">
            <div class="sin-list-footer-info" id="sinFooterInfo"></div>
            <div class="sin-pag-controls" id="sinPagControls"></div>
            <div class="sin-list-footer-per">
                Por página:
                <select id="sinPerPage">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>
    </div>


</div>

<?php require_once __DIR__ . '/modal_sin.php'; ?>

<!-- ══════════ SCRIPTS DA PÁGINA ══════════ -->
<script>
document.addEventListener('DOMContentLoaded', function () {

    var SIN_ACTION  = '<?= ACTION_URL ?>/sinistros.php';
    var APP_URL_JS  = '<?= APP_URL ?>';

    var vSwal = Swal.mixin({
        showClass: { popup: 'vt-swal-in' },
        hideClass: { popup: 'vt-swal-out' },
        customClass: { popup: 'vt-swal-popup' }
    });

    /* ──── Callback: recarrega listagem após salvar sinistro ──── */
    window.sinOnSalvo = function () { carregarListagem(); };

    /* ──── LISTAGEM GERAL ──── */
    var sinListTimer = null;
    var sinAllData   = [];
    var sinPage      = 1;
    var sinPerPage   = 10;
    var sinView      = localStorage.getItem('sinView') || 'list';

    function sinBadgeCls(status) {
        return ({'ABERTO':'sin-badge-aberto','ENCERRADO':'sin-badge-encerrado','CANCELADO':'sin-badge-cancelado'})[status] || 'sin-badge-aberto';
    }

    function sinSetView(v) {
        sinView = v;
        localStorage.setItem('sinView', v);
        if (v === 'grid') {
            document.getElementById('sinViewList').style.display = 'none';
            document.getElementById('sinViewGrid').style.display = 'block';
            document.getElementById('btnSinList').classList.remove('active');
            document.getElementById('btnSinGrid').classList.add('active');
        } else {
            document.getElementById('sinViewList').style.display = 'block';
            document.getElementById('sinViewGrid').style.display = 'none';
            document.getElementById('btnSinList').classList.add('active');
            document.getElementById('btnSinGrid').classList.remove('active');
        }
        sinRenderPage();
    }

    document.getElementById('btnSinList').addEventListener('click', function () { sinSetView('list'); });
    document.getElementById('btnSinGrid').addEventListener('click', function () { sinSetView('grid'); });

    function carregarListagem() {
        var q      = $('#sinListQ').val().trim();
        var status = $('#sinListStatus').val();
        var url    = SIN_ACTION + '?acao=listar_todos';
        if (status) url += '&status=' + encodeURIComponent(status);
        if (q)      url += '&q='      + encodeURIComponent(q);
        $('#sinListTbody').html('<tr><td colspan="9" class="sin-list-empty"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Carregando...</td></tr>');
        document.getElementById('sinFooterInfo').textContent = '';
        document.getElementById('sinPagControls').innerHTML = '';
        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (list) { renderListagem(list); })
            .catch(function () { $('#sinListTbody').html('<tr><td colspan="9" class="sin-list-empty" style="color:#c92a2a;">Erro ao carregar dados.</td></tr>'); });
    }

    function renderListagem(list) {
        sinAllData = list;
        sinPage    = 1;
        sinRenderPage();
    }

    function sinRenderPage() {
        var total = sinAllData.length;
        var start = (sinPage - 1) * sinPerPage;
        var slice = sinAllData.slice(start, start + sinPerPage);

        if (sinView === 'grid') {
            sinRenderCards(slice);
        } else {
            sinRenderRows(slice, total);
        }
        sinRenderFooter(total, start, slice.length);
        sinRenderPagination(total);
    }

    function sinRenderRows(slice, total) {
        var $tbody = $('#sinListTbody').empty();
        if (!total) {
            $tbody.html('<tr><td colspan="9" class="sin-list-empty">Nenhum sinistro encontrado.</td></tr>');
            return;
        }
        if (!slice.length) {
            $tbody.html('<tr><td colspan="9" class="sin-list-empty">—</td></tr>');
            return;
        }
        slice.forEach(function (s) {
            var badge     = sinBadgeCls(s.SIN_STATUS);
            var wa        = parseInt(s.SIN_WHATSAPP_ENVIADO)
                ? '<span style="color:#25d366;" title="Enviado"><i class="fa-brands fa-whatsapp"></i></span>'
                : '<span style="color:#dee2e6;">—</span>';
            var dataOcorr = s.SIN_DATA_OCORRENCIA ? s.SIN_DATA_OCORRENCIA.split('-').reverse().join('/') : '—';
            $tbody.append('<tr>' +
                '<td style="font-weight:700;color:#3b5bdb;white-space:nowrap;">#' + s.SIN_CODIGO_PK + '</td>' +
                '<td style="white-space:nowrap;">' + dataOcorr + '</td>' +
                '<td>' + (s.SIN_TIPO_OCORRENCIA || '—') + '</td>' +
                '<td style="font-family:monospace;font-weight:700;">' + (s.VEI_PLACA || '—') + '</td>' +
                '<td>' + (s.PES_NOME || '—') + '</td>' +
                '<td style="font-family:monospace;font-size:11px;">' + (s.SIN_NUM_BO || '—') + '</td>' +
                '<td><span class="sin-badge ' + badge + '">' + s.SIN_STATUS + '</span></td>' +
                '<td style="text-align:center;">' + wa + '</td>' +
                '<td style="white-space:nowrap;">' +
                '<button class="sin-btn-hist btn-editar-sin" data-sin-id="' + s.SIN_CODIGO_PK + '" style="font-size:12px;padding:4px 9px;" title="Editar / Adicionar Fotos"><i class="fa-solid fa-pen"></i></button>' +
                '<a href="' + APP_URL_JS + '/sinistros/imprimir.php?id=' + s.SIN_CODIGO_PK + '" target="_blank" class="sin-btn-hist" style="font-size:12px;padding:4px 9px;display:inline-block;text-decoration:none;" title="Imprimir"><i class="fa-solid fa-print"></i></a>' +
                '</td></tr>');
        });
    }

    function sinRenderCards(slice) {
        var grid = document.getElementById('sinCardGrid');
        if (!sinAllData.length) { grid.innerHTML = '<div style="padding:32px;text-align:center;color:#868e96;">Nenhum sinistro encontrado.</div>'; return; }
        if (!slice.length)       { grid.innerHTML = '<div style="padding:32px;text-align:center;color:#868e96;">—</div>'; return; }
        var html = '';
        slice.forEach(function (s) {
            var badge     = sinBadgeCls(s.SIN_STATUS);
            var dataOcorr = s.SIN_DATA_OCORRENCIA ? s.SIN_DATA_OCORRENCIA.split('-').reverse().join('/') : '—';
            var wa        = parseInt(s.SIN_WHATSAPP_ENVIADO)
                ? '<span style="color:#25d366;font-size:14px;" title="WA Enviado"><i class="fa-brands fa-whatsapp"></i></span>'
                : '';
            html +=
                '<div class="sin-card">' +
                '<div class="sin-card-top">' +
                '<span class="sin-badge ' + badge + '">' + (s.SIN_STATUS || '—') + '</span>' +
                '<span class="sin-card-id">#' + s.SIN_CODIGO_PK + (wa ? ' ' + wa : '') + '</span>' +
                '</div>' +
                '<div class="sin-card-plate">' + (s.VEI_PLACA || '—') + '</div>' +
                '<div class="sin-card-type"><i class="fa-solid fa-car-burst" style="color:#c92a2a;margin-right:4px;font-size:10px;"></i>' + (s.SIN_TIPO_OCORRENCIA || '—') + '</div>' +
                '<div class="sin-card-assoc" title="' + (s.PES_NOME || '') + '">' + (s.PES_NOME || '—') + '</div>' +
                '<div class="sin-card-date"><i class="fa-regular fa-calendar" style="margin-right:4px;"></i>' + dataOcorr + (s.SIN_NUM_BO ? ' · B.O. ' + s.SIN_NUM_BO : '') + '</div>' +
                '<div class="sin-card-actions">' +
                '<button class="btn-editar-sin" data-sin-id="' + s.SIN_CODIGO_PK + '" title="Editar"><i class="fa-solid fa-pen"></i></button>' +
                '<a href="' + APP_URL_JS + '/sinistros/imprimir.php?id=' + s.SIN_CODIGO_PK + '" target="_blank" title="Imprimir"><i class="fa-solid fa-print"></i></a>' +
                '</div>' +
                '</div>';
        });
        grid.innerHTML = html;
    }

    function sinRenderFooter(total, start, count) {
        var info = document.getElementById('sinFooterInfo');
        if (!total) { info.textContent = '0 registros'; return; }
        var end = start + count;
        info.innerHTML = 'Mostrando <strong>' + (start + 1) + '–' + end + '</strong> de <strong>' + total + '</strong> sinistro' + (total !== 1 ? 's' : '');
    }

    function sinRenderPagination(total) {
        var wrap = document.getElementById('sinPagControls');
        var tot  = Math.ceil(total / sinPerPage);
        if (tot <= 1) { wrap.innerHTML = ''; return; }
        var cur  = sinPage;
        function btn(icon, page, disabled, active) {
            return '<button onclick="sinGoPage(' + page + ')" ' + (disabled ? 'disabled' : '') + ' class="' + (active ? 'active' : '') + '">' + icon + '</button>';
        }
        var parts = [];
        parts.push(btn('<i class="fa-solid fa-angles-left"></i>',  1,       cur === 1,   false));
        parts.push(btn('<i class="fa-solid fa-angle-left"></i>',   cur - 1, cur === 1,   false));
        var from = Math.max(1, cur - 2), to = Math.min(tot, from + 4);
        from = Math.max(1, to - 4);
        if (from > 1) parts.push('<span class="pg-ellipsis">…</span>');
        for (var i = from; i <= to; i++) parts.push(btn(i, i, false, i === cur));
        if (to < tot) parts.push('<span class="pg-ellipsis">…</span>');
        parts.push(btn('<i class="fa-solid fa-angle-right"></i>',  cur + 1, cur === tot, false));
        parts.push(btn('<i class="fa-solid fa-angles-right"></i>', tot,     cur === tot, false));
        wrap.innerHTML = parts.join('');
    }

    window.sinGoPage = function (page) {
        var tot = Math.ceil(sinAllData.length / sinPerPage);
        sinPage = Math.max(1, Math.min(page, tot));
        sinRenderPage();
    };

    document.getElementById('sinPerPage').addEventListener('change', function () {
        sinPerPage = parseInt(this.value) || 10;
        sinPage    = 1;
        sinRenderPage();
    });

    $('#sinListQ').on('input', function () { clearTimeout(sinListTimer); sinListTimer = setTimeout(carregarListagem, 400); });
    $('#sinListStatus').on('change', carregarListagem);
    $('#sinListRefresh').on('click', carregarListagem);

    /* ── Botão Novo Sinistro ── */
    document.getElementById('sinBtnNovoGeral').addEventListener('click', function () {
        window.sinAbrirNovo();
    });

    // apply saved view preference before first load
    (function () {
        var saved = localStorage.getItem('sinView') || 'list';
        if (saved === 'grid') {
            document.getElementById('sinViewList').style.display = 'none';
            document.getElementById('sinViewGrid').style.display = 'block';
            document.getElementById('btnSinList').classList.remove('active');
            document.getElementById('btnSinGrid').classList.add('active');
            sinView = 'grid';
        }
    })();

    carregarListagem();

    // Recarrega ao fechar o modal
    $('#modalSinistro').on('hidden.bs.modal', function () { carregarListagem(); });

});
</script>