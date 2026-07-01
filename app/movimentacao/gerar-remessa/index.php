<?php
$_SESSION['sessionTitulo'] = 'Gerar Remessa — Valiantus';
include __DIR__ . '/../../../inc/header.php';
?>

<style>
.rem-page-header { display:flex; align-items:center; gap:14px; margin-bottom:24px; }
.rem-page-icon { width:48px; height:48px; border-radius:14px; background:linear-gradient(135deg,#3b5bdb,#4c6ef5); color:#fff; display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0; box-shadow:0 6px 16px rgba(59,91,219,.3); }
.rem-page-title { font-size:22px; font-weight:800; color:#1a1d2e; margin:0; line-height:1.2; }
.rem-page-sub   { font-size:13px; color:#868e96; margin:2px 0 0; }

/* ── Card filtro ── */
.rem-filter-card { background:#fff; border-radius:16px; border:1px solid #e9ecef; box-shadow:0 4px 20px rgba(30,40,80,.07); padding:24px 28px; margin-bottom:22px; }
.rem-filter-row  { display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; }
.rem-filter-row .fg { display:flex; flex-direction:column; gap:6px; flex:1; min-width:150px; }
.rem-filter-row label { font-size:11.5px; font-weight:700; color:#495057; text-transform:uppercase; letter-spacing:.04em; }
.rem-filter-row input[type=date],
.rem-filter-row input[type=text]  { height:42px; border:1.5px solid #dbe2ea; border-radius:11px; font-size:14px; padding:0 14px; color:#1a1d2e; transition:.15s; background:#fff; }
.rem-filter-row input:focus       { border-color:#3b5bdb; box-shadow:0 0 0 3px rgba(59,91,219,.1); outline:none; }

.rem-or-divider { display:flex; align-items:center; gap:10px; margin:18px 0; color:#adb5bd; font-size:12px; font-weight:700; letter-spacing:.06em; }
.rem-or-divider::before, .rem-or-divider::after { content:''; flex:1; height:1px; background:#e9ecef; }

/* ── Botões filtro ── */
.rem-btn-buscar { height:42px; padding:0 22px; border-radius:11px; background:#3b5bdb; color:#fff; border:none; font-weight:700; font-size:14px; cursor:pointer; transition:.15s; white-space:nowrap; display:flex; align-items:center; gap:8px; }
.rem-btn-buscar:hover    { background:#2f52d6; transform:translateY(-1px); box-shadow:0 6px 16px rgba(59,91,219,.25); }
.rem-btn-buscar:disabled { background:#adb5bd; cursor:not-allowed; transform:none; box-shadow:none; }

/* ── Resultados busca individual ── */
.rem-indiv-results { margin-top:12px; border:1.5px solid #e9ecef; border-radius:12px; overflow:hidden; display:none; }
.rem-indiv-item { display:flex; align-items:center; gap:12px; padding:10px 16px; border-bottom:1px solid #f0f0f0; font-size:13.5px; background:#fff; transition:.12s; }
.rem-indiv-item:last-child { border-bottom:none; }
.rem-indiv-item:hover { background:#f8f9fb; }
.rem-indiv-nome { font-weight:700; color:#1a1d2e; flex:1; min-width:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.rem-indiv-nn   { font-family:monospace; font-weight:700; color:#3b5bdb; font-size:13px; white-space:nowrap; }
.rem-indiv-dt   { color:#868e96; font-size:12px; white-space:nowrap; }
.rem-indiv-val  { font-weight:700; color:#2f9e44; white-space:nowrap; }
.rem-indiv-add  { height:32px; padding:0 14px; border-radius:8px; background:#3b5bdb; color:#fff; border:none; font-size:12px; font-weight:700; cursor:pointer; transition:.12s; white-space:nowrap; flex-shrink:0; }
.rem-indiv-add:hover    { background:#2f52d6; }
.rem-indiv-add:disabled { background:#d3f9d8; color:#2f9e44; cursor:default; }
.rem-indiv-empty { padding:16px; text-align:center; color:#adb5bd; font-size:13px; }

/* ── Sumário ── */
.rem-summary-bar { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:18px; }
.rem-chips { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
.rem-chip        { background:#f0f3ff; border:1.5px solid #c5d0ff; color:#3b5bdb; border-radius:20px; padding:5px 16px; font-size:13px; font-weight:700; }
.rem-chip-green  { background:#d3f9d8; border-color:#8ce99a; color:#2f9e44; }
.rem-chip-yellow { background:#fff3cd; border-color:#ffe066; color:#856404; }

.rem-btn-limpar { height:36px; padding:0 16px; border-radius:9px; background:#fff; border:1.5px solid #e9ecef; color:#868e96; font-size:13px; font-weight:600; cursor:pointer; transition:.12s; display:flex; align-items:center; gap:6px; }
.rem-btn-limpar:hover { border-color:#c92a2a; color:#c92a2a; background:#fff5f5; }

.rem-bar-right { display:flex; align-items:center; gap:10px; }
.rem-btn-gerar { height:46px; padding:0 28px; border-radius:12px; background:linear-gradient(135deg,#2f9e44,#40c057); color:#fff; border:none; font-weight:700; font-size:15px; cursor:pointer; transition:.15s; display:flex; align-items:center; gap:10px; box-shadow:0 4px 14px rgba(47,158,68,.3); }
.rem-btn-gerar:hover    { background:linear-gradient(135deg,#2b8a3e,#37b24d); transform:translateY(-1px); box-shadow:0 8px 20px rgba(47,158,68,.35); }
.rem-btn-gerar:disabled { background:#adb5bd; cursor:not-allowed; transform:none; box-shadow:none; }

/* ── Tabela ── */
.rem-table-card { background:#fff; border-radius:16px; border:1px solid #e9ecef; box-shadow:0 4px 20px rgba(30,40,80,.07); overflow:hidden; }
.rem-table-head { padding:14px 20px; display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid #f0f0f0; gap:12px; flex-wrap:wrap; }
.rem-table-head h5 { font-size:15px; font-weight:700; color:#1a1d2e; margin:0; }
.rem-sel-info { font-size:13px; color:#868e96; }
.rem-sel-info strong { color:#1a1d2e; }

.rem-table { width:100%; border-collapse:collapse; font-size:13.5px; }
.rem-table thead th { background:#3b5bdb; color:#fff; padding:11px 14px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; white-space:nowrap; text-align:left; }
.rem-table thead th.tc { text-align:center; width:40px; }
.rem-table tbody td { padding:10px 14px; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
.rem-table tbody tr:last-child td { border-bottom:none; }
.rem-table tbody tr:hover td { background:#f8f9fb; }

.rem-cb { width:16px; height:16px; accent-color:#3b5bdb; cursor:pointer; }
.rem-badge     { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; white-space:nowrap; }
.rem-badge-env { background:#e8edff; color:#3b5bdb; }
.rem-badge-pend{ background:#fff3cd; color:#856404; }

.rem-btn-rm { width:28px; height:28px; border-radius:7px; background:#fff0f0; border:1.5px solid #ffc9c9; color:#c92a2a; font-size:13px; cursor:pointer; transition:.12s; display:inline-flex; align-items:center; justify-content:center; }
.rem-btn-rm:hover { background:#c92a2a; color:#fff; border-color:#c92a2a; }

.rem-empty   { text-align:center; padding:48px 20px; color:#adb5bd; }
.rem-empty i { font-size:40px; margin-bottom:12px; display:block; }
.rem-empty p { font-size:14px; margin:0; }
.rem-loading { text-align:center; padding:40px; color:#868e96; font-size:14px; }
.rem-loading i { font-size:24px; animation:rem-spin 1s linear infinite; margin-right:8px; }
@keyframes rem-spin { to { transform:rotate(360deg); } }

#remResults { display:none; }
</style>

<div class="container-fluid" style="max-width:1300px;padding:28px 24px 40px;">

    <!-- cabeçalho -->
    <div class="rem-page-header">
        <div class="rem-page-icon"><i class="fa-solid fa-file-arrow-up"></i></div>
        <div>
            <h1 class="rem-page-title">Gerar Remessa CNAB 240</h1>
            <p class="rem-page-sub">Sicoob — Busque por período ou adicione parcelas individualmente</p>
        </div>
    </div>

    <!-- filtros -->
    <div class="rem-filter-card">

        <!-- busca por período -->
        <div class="rem-filter-row">
            <div class="fg">
                <label><i class="fa-regular fa-calendar" style="margin-right:4px"></i>Vencimento de</label>
                <input type="date" id="remDtIni" value="<?= date('Y-m-01') ?>">
            </div>
            <div class="fg">
                <label><i class="fa-regular fa-calendar" style="margin-right:4px"></i>até</label>
                <input type="date" id="remDtFim" value="<?= date('Y-m-t') ?>">
            </div>
            <button class="rem-btn-buscar" id="btnBuscar" onclick="remBuscar()">
                <i class="fa-solid fa-magnifying-glass"></i> Buscar por período
            </button>
        </div>

        <div class="rem-or-divider">OU ADICIONE INDIVIDUALMENTE</div>

        <!-- busca individual -->
        <div class="rem-filter-row">
            <div class="fg" style="max-width:480px">
                <label><i class="fa-solid fa-user-magnifying-glass" style="margin-right:4px"></i>ID, Nome do associado ou Nosso Número</label>
                <input type="text" id="remIndivQ" placeholder="ID, nome do associado ou nosso número..." onkeydown="if(event.key==='Enter') remBuscarIndiv()">
            </div>
            <button class="rem-btn-buscar" id="btnBuscarIndiv" onclick="remBuscarIndiv()" style="background:#495057">
                <i class="fa-solid fa-search"></i> Buscar
            </button>
        </div>

        <!-- resultados da busca individual -->
        <div class="rem-indiv-results" id="remIndivResults"></div>

    </div>

    <!-- resultados / staging -->
    <div id="remResults">

        <!-- sumário + botões -->
        <div class="rem-summary-bar">
            <div class="rem-chips" id="remChips"></div>
            <div class="rem-bar-right">
                <button class="rem-btn-limpar" onclick="remLimpar()">
                    <i class="fa-solid fa-trash-can"></i> Limpar lista
                </button>
                <button class="rem-btn-gerar" id="btnGerar" onclick="remGerar()">
                    <i class="fa-solid fa-file-arrow-down"></i> Gerar Remessa CNAB 240
                </button>
            </div>
        </div>

        <!-- tabela staging -->
        <div class="rem-table-card">
            <div class="rem-table-head">
                <h5><i class="fa-solid fa-list" style="margin-right:8px;color:#3b5bdb"></i>Parcelas para remessa</h5>
                <span class="rem-sel-info"><strong id="remSelCount">0</strong> parcela(s) na lista</span>
            </div>
            <div id="remTableWrap"></div>
        </div>

    </div>

</div>

<script>
(function () {
    'use strict';

    /* Captura SweetAlert2 v11 ANTES do vendors.bundle.js do Metronic sobrescrevê-lo */
    var Swal2 = window.Swal;

    /* selMap: { COB_CODIGO_PK: { val: float, row: object } } */
    var selMap = {};
    var ACTION = '<?= ACTION_URL ?>/remessa.php';

    /* ─────────────────────────────────────────────
       BUSCA POR PERÍODO
    ───────────────────────────────────────────── */
    window.remBuscar = function () {
        var dtIni = document.getElementById('remDtIni').value;
        var dtFim = document.getElementById('remDtFim').value;

        if (!dtIni || !dtFim) {
            Swal2.fire({ icon:'warning', title:'Atenção', text:'Informe as duas datas.', confirmButtonColor:'#3b5bdb' });
            return;
        }
        if (dtIni > dtFim) {
            Swal2.fire({ icon:'warning', title:'Atenção', text:'A data inicial não pode ser maior que a final.', confirmButtonColor:'#3b5bdb' });
            return;
        }

        var btn = document.getElementById('btnBuscar');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Buscando...';

        fetch(ACTION + '?acao=listar&dt_ini=' + dtIni + '&dt_fim=' + dtFim)
            .then(function (r) { return r.json(); })
            .then(function (res) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-magnifying-glass"></i> Buscar por período';

                if (!res.success) {
                    Swal2.fire({ icon:'error', title:'Erro', text: res.message || 'Erro ao buscar.', confirmButtonColor:'#3b5bdb' });
                    return;
                }
                if (!res.data || !res.data.length) {
                    Swal2.fire({ icon:'info', title:'Sem resultados', text:'Nenhuma parcela bancária em aberto encontrada para o período.', confirmButtonColor:'#3b5bdb' });
                    return;
                }

                /* substitui selMap com os resultados do período */
                selMap = {};
                res.data.forEach(function (r) {
                    selMap[r.COB_CODIGO_PK] = { val: parseFloat(r.COB_VALOR || 0), row: r };
                });
                renderTudo();
            })
            .catch(function () {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-magnifying-glass"></i> Buscar por período';
                Swal2.fire({ icon:'error', title:'Erro', text:'Falha na comunicação com o servidor.', confirmButtonColor:'#3b5bdb' });
            });
    };

    /* ─────────────────────────────────────────────
       BUSCA INDIVIDUAL
    ───────────────────────────────────────────── */
    window.remBuscarIndiv = function () {
        var q   = document.getElementById('remIndivQ').value.trim();
        var res = document.getElementById('remIndivResults');

        if (q.length < 2) {
            Swal2.fire({ icon:'warning', title:'Atenção', text:'Informe ao menos 2 caracteres.', confirmButtonColor:'#3b5bdb' });
            return;
        }

        var btn = document.getElementById('btnBuscarIndiv');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Buscando...';
        res.style.display = 'none';

        fetch(ACTION + '?acao=buscar_individual&q=' + encodeURIComponent(q))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-search"></i> Buscar';
                renderIndivResults(data.data || []);
            })
            .catch(function () {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-search"></i> Buscar';
                res.innerHTML = '<div class="rem-indiv-empty">Falha na comunicação.</div>';
                res.style.display = 'block';
            });
    };

    function renderIndivResults(rows) {
        var wrap = document.getElementById('remIndivResults');
        if (!rows.length) {
            wrap.innerHTML = '<div class="rem-indiv-empty"><i class="fa-solid fa-circle-xmark" style="margin-right:6px"></i>Nenhuma parcela bancária em aberto encontrada.</div>';
            wrap.style.display = 'block';
            return;
        }

        var html = '';
        rows.forEach(function (r) {
            var jaAdicionado = !!selMap[r.COB_CODIGO_PK];
            var btnHtml = jaAdicionado
                ? '<button class="rem-indiv-add" disabled><i class="fa-solid fa-check"></i> Adicionado</button>'
                : '<button class="rem-indiv-add" onclick="remAdicionarIndiv(' + JSON.stringify(r).replace(/"/g, '&quot;') + ')"><i class="fa-solid fa-plus"></i> Adicionar</button>';

            html += '<div class="rem-indiv-item" id="indiv-' + r.COB_CODIGO_PK + '">'
                + '<span class="rem-indiv-nome">' + esc(r.PES_NOME) + ' <span style="font-size:11px;color:#adb5bd;font-weight:400">#' + r.PES_CODIGO_FK + '</span></span>'
                + '<span class="rem-indiv-nn">NN: ' + esc(r.COB_NOSSO_NUMERO || '—') + '</span>'
                + '<span class="rem-indiv-dt">' + fmtData(r.COB_DATA_VENCIMENTO) + '</span>'
                + '<span class="rem-indiv-val">' + fmtBRL(r.COB_VALOR) + '</span>'
                + btnHtml
                + '</div>';
        });

        wrap.innerHTML = html;
        wrap.style.display = 'block';
    }

    window.remAdicionarIndiv = function (row) {
        var id = row.COB_CODIGO_PK;
        if (selMap[id]) return;

        selMap[id] = { val: parseFloat(row.COB_VALOR || 0), row: row };

        /* atualiza botão na lista de resultados individuais */
        var item = document.getElementById('indiv-' + id);
        if (item) {
            var btn = item.querySelector('.rem-indiv-add');
            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-check"></i> Adicionado'; }
        }

        renderTudo();
    };

    /* ─────────────────────────────────────────────
       RENDER TABELA + SUMÁRIO
    ───────────────────────────────────────────── */
    function renderTudo() {
        var ids  = Object.keys(selMap);
        var soma = 0;
        ids.forEach(function (id) { soma += selMap[id].val; });

        /* sumário */
        var chips = document.getElementById('remChips');
        chips.innerHTML = '<span class="rem-chip"><i class="fa-solid fa-receipt" style="margin-right:6px"></i>' + ids.length + ' parcela' + (ids.length !== 1 ? 's' : '') + '</span>'
            + '<span class="rem-chip rem-chip-green"><i class="fa-solid fa-dollar-sign" style="margin-right:6px"></i>' + fmtBRL(soma) + '</span>';

        document.getElementById('remSelCount').textContent = ids.length;
        document.getElementById('remResults').style.display = ids.length ? 'block' : 'none';

        /* tabela */
        var wrap = document.getElementById('remTableWrap');
        if (!ids.length) { wrap.innerHTML = ''; return; }

        var rows = ids.map(function (id) { return selMap[id].row; });
        rows.sort(function (a, b) {
            return (a.COB_DATA_VENCIMENTO || '').localeCompare(b.COB_DATA_VENCIMENTO || '');
        });

        var html = '<div style="overflow-x:auto"><table class="rem-table"><thead><tr>'
            + '<th class="tc">#</th>'
            + '<th>Associado</th>'
            + '<th>CPF / CNPJ</th>'
            + '<th>Nosso Número</th>'
            + '<th>Vencimento</th>'
            + '<th style="text-align:right">Valor</th>'
            + '<th>Status</th>'
            + '<th class="tc"></th>'
            + '</tr></thead><tbody>';

        rows.forEach(function (r, i) {
            var env   = r.COB_ENVIADO_BANCO === 'SIM';
            var badge = env
                ? '<span class="rem-badge rem-badge-env">Já enviado</span>'
                : '<span class="rem-badge rem-badge-pend">Pendente</span>';

            html += '<tr>'
                + '<td style="text-align:center;color:#868e96;font-size:12px">' + (i + 1) + '</td>'
                + '<td><strong style="color:#1a1d2e">' + esc(r.PES_NOME) + '</strong></td>'
                + '<td style="font-family:monospace;color:#495057">' + fmtDoc(r.PES_CPF_CNPJ) + '</td>'
                + '<td style="font-family:monospace;font-weight:700;color:#3b5bdb">' + esc(r.COB_NOSSO_NUMERO || '—') + '</td>'
                + '<td>' + fmtData(r.COB_DATA_VENCIMENTO) + '</td>'
                + '<td style="text-align:right;font-weight:700;color:#1a1d2e">' + fmtBRL(r.COB_VALOR) + '</td>'
                + '<td>' + badge + '</td>'
                + '<td style="text-align:center"><button class="rem-btn-rm" onclick="remRemover(' + r.COB_CODIGO_PK + ')" title="Remover"><i class="fa-solid fa-xmark"></i></button></td>'
                + '</tr>';
        });

        html += '</tbody></table></div>';
        wrap.innerHTML = html;
    }

    window.remRemover = function (id) {
        delete selMap[id];

        /* re-habilita botão na lista de busca individual, se visível */
        var item = document.getElementById('indiv-' + id);
        if (item) {
            var btn = item.querySelector('.rem-indiv-add');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-plus"></i> Adicionar';
                /* reata onclick */
                var row = selMap[id] ? selMap[id].row : null; /* já removido, usar data-row se necessário */
            }
        }

        renderTudo();
    };

    window.remLimpar = function () {
        if (!Object.keys(selMap).length) return;
        Swal2.fire({
            title: 'Limpar lista?',
            text: 'Todas as parcelas serão removidas da lista.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Limpar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#c92a2a',
        }).then(function (r) {
            if (!r.isConfirmed) return;
            selMap = {};
            /* reabilita todos os botões individuais */
            document.querySelectorAll('.rem-indiv-add').forEach(function (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-plus"></i> Adicionar';
            });
            renderTudo();
        });
    };

    /* ─────────────────────────────────────────────
       GERAR REMESSA
    ───────────────────────────────────────────── */
    window.remGerar = function () {
        var ids  = Object.keys(selMap);
        var soma = 0;
        ids.forEach(function (id) { soma += selMap[id].val; });

        if (!ids.length) {
            Swal2.fire({ icon:'warning', title:'Atenção', text:'A lista está vazia.', confirmButtonColor:'#3b5bdb' });
            return;
        }

        Swal2.fire({
            title: 'Gerar Remessa CNAB 240?',
            html: 'Serão incluídas <strong>' + ids.length + ' parcela' + (ids.length !== 1 ? 's' : '') + '</strong>'
                + ' — Total: <strong>' + fmtBRL(soma) + '</strong><br>'
                + '<small style="color:#868e96">As parcelas serão marcadas como enviadas.</small>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Gerar e Baixar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#2f9e44',
        }).then(function (r) {
            if (!r.isConfirmed) return;

            var form = document.createElement('form');
            form.method = 'POST';
            form.action = ACTION + '?acao=gerar';
            form.style.display = 'none';
            ids.forEach(function (id) {
                var inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
                form.appendChild(inp);
            });
            document.body.appendChild(form);

            Swal2.fire({
                title: 'Gerando arquivo...',
                html: 'Processando <strong>' + ids.length + '</strong> parcela' + (ids.length !== 1 ? 's' : '') + '.<br><small style="color:#868e96">O download iniciará em instantes.</small>',
                timer: 3500,
                timerProgressBar: true,
                showConfirmButton: false,
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: function () {
                    form.submit();
                    document.body.removeChild(form);
                }
            }).then(function () {
                selMap = {};
                document.getElementById('remIndivResults').style.display = 'none';
                document.getElementById('remIndivQ').value = '';
                renderTudo();
                Swal2.fire({
                    icon: 'success',
                    title: 'Remessa gerada!',
                    text: 'O arquivo foi gerado e o download iniciado. As parcelas foram marcadas como enviadas.',
                    confirmButtonColor: '#3b5bdb'
                });
            });
        });
    };

    /* ─────────────────────────────────────────────
       HELPERS
    ───────────────────────────────────────────── */
    function fmtBRL(v) {
        return 'R$ ' + parseFloat(v || 0).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
    function fmtData(d) {
        if (!d) return '—';
        var p = d.split('-');
        return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : d;
    }
    function fmtDoc(v) {
        if (!v) return '—';
        v = v.replace(/\D/g, '');
        if (v.length === 11) return v.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
        if (v.length === 14) return v.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
        return v;
    }
    function esc(s) {
        if (!s) return '—';
        return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

}());
</script>

<?php include __DIR__ . '/../../../inc/footer.php'; ?>
