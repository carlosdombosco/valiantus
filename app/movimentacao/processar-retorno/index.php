<?php
$_SESSION['sessionTitulo'] = 'Processar Retorno — Valiantus';
include __DIR__ . '/../../../inc/header.php';
?>

<style>
.ret-page-header { display:flex; align-items:center; gap:14px; margin-bottom:24px; }
.ret-page-icon   { width:48px; height:48px; border-radius:14px; background:linear-gradient(135deg,#2f9e44,#40c057); color:#fff; display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0; box-shadow:0 6px 16px rgba(47,158,68,.3); }
.ret-page-title  { font-size:22px; font-weight:800; color:#1a1d2e; margin:0; line-height:1.2; }
.ret-page-sub    { font-size:13px; color:#868e96; margin:2px 0 0; }

.ret-card        { background:#fff; border-radius:16px; border:1px solid #e9ecef; box-shadow:0 4px 20px rgba(30,40,80,.07); padding:24px 28px; margin-bottom:22px; }

.ret-file-label  { display:inline-flex; align-items:center; gap:10px; height:42px; padding:0 18px; border:1.5px dashed #adb5bd; border-radius:11px; font-size:14px; color:#495057; cursor:pointer; transition:.15s; background:#f8f9fa; min-width:280px; }
.ret-file-label:hover { border-color:#2f9e44; color:#2f9e44; background:#f4fdf5; }

.ret-btn         { height:42px; padding:0 22px; border-radius:11px; border:none; font-weight:700; font-size:14px; cursor:pointer; transition:.15s; white-space:nowrap; display:inline-flex; align-items:center; gap:8px; }
.ret-btn-proc    { background:#3b5bdb; color:#fff; }
.ret-btn-proc:hover    { background:#2f52d6; transform:translateY(-1px); box-shadow:0 6px 16px rgba(59,91,219,.25); }
.ret-btn-proc:disabled { background:#adb5bd; cursor:not-allowed; transform:none; box-shadow:none; }
.ret-btn-aplic   { background:#2f9e44; color:#fff; }
.ret-btn-aplic:hover    { background:#258837; transform:translateY(-1px); box-shadow:0 6px 16px rgba(47,158,68,.25); }
.ret-btn-aplic:disabled { background:#adb5bd; cursor:not-allowed; transform:none; box-shadow:none; }

.ret-chip        { display:inline-flex; align-items:center; gap:8px; padding:8px 16px; border-radius:999px; font-size:13px; font-weight:700; }
.ret-chip-liq    { background:#d3f9d8; color:#1c7430; }
.ret-chip-conf   { background:#d0ebff; color:#1864ab; }
.ret-chip-rej    { background:#ffe3e3; color:#c92a2a; }
.ret-chip-out    { background:#f1f3f5; color:#495057; }

.ret-summary-bar { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:20px; }
.ret-summary-bar .ret-chips { display:flex; gap:10px; flex-wrap:wrap; flex:1; }
.ret-summary-bar .ret-bar-right { margin-left:auto; }

.ret-table-wrap  { background:#fff; border-radius:16px; border:1px solid #e9ecef; box-shadow:0 4px 20px rgba(30,40,80,.07); overflow:hidden; }
.ret-table       { width:100%; border-collapse:collapse; font-size:13.5px; }
.ret-table thead th { background:#f8f9fa; font-size:11px; font-weight:700; color:#868e96; text-transform:uppercase; letter-spacing:.06em; padding:10px 14px; border-bottom:1px solid #e9ecef; white-space:nowrap; }
.ret-table tbody td { padding:10px 14px; border-bottom:1px solid #f1f3f5; color:#1a1d2e; vertical-align:middle; }
.ret-table tbody tr:last-child td { border-bottom:none; }
.ret-table tbody tr.liq  { background:#f4fdf5; }
.ret-table tbody tr.conf { background:#f0f7ff; }
.ret-table tbody tr.rej  { background:#fff5f5; }

.ret-badge       { display:inline-block; padding:3px 10px; border-radius:999px; font-size:11.5px; font-weight:700; }
.ret-badge-liq   { background:#d3f9d8; color:#1c7430; }
.ret-badge-conf  { background:#d0ebff; color:#1864ab; }
.ret-badge-rej   { background:#ffe3e3; color:#c92a2a; cursor:pointer; }
.ret-badge-out   { background:#f1f3f5; color:#495057; }

#divTotais { display:none; }
#divGrid   { display:none; }
</style>

<div class="container-fluid" style="max-width:1300px;padding:28px 24px 40px;">

    <!-- cabeçalho -->
    <div class="ret-page-header">
        <div class="ret-page-icon"><i class="fa-solid fa-file-import"></i></div>
        <div>
            <h1 class="ret-page-title">Processar Retorno CNAB 240</h1>
            <p class="ret-page-sub">Sicoob — Leitura e baixa automática de arquivo de retorno bancário</p>
        </div>
    </div>

    <!-- card de seleção de arquivo -->
    <div class="ret-card">
        <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
            <div style="display:flex;flex-direction:column;gap:6px;">
                <label style="font-size:11.5px;font-weight:700;color:#495057;text-transform:uppercase;letter-spacing:.04em;">
                    <i class="fa-solid fa-file-code" style="margin-right:4px;color:#2f9e44;"></i>Arquivo de retorno (.ret)
                </label>
                <label class="ret-file-label" id="lblArquivo">
                    <i class="fa-solid fa-folder-open" style="color:#2f9e44;"></i>
                    <span id="nomeArquivo">Selecionar arquivo .ret...</span>
                </label>
                <input type="file" id="inputArquivo" accept=".ret" style="display:none;">
            </div>
            <button class="ret-btn ret-btn-proc" id="btnProcessar" onclick="retProcessar()">
                <i class="fa-solid fa-folder-open"></i> Carregar arquivo
            </button>
        </div>
    </div>

    <!-- barra de totais -->
    <div id="divTotais">
        <div class="ret-summary-bar">
            <div class="ret-chips">
                <span class="ret-chip ret-chip-liq"><i class="fa-solid fa-circle-check"></i><span id="lblLiq">0 liquidações</span> — <span id="lblLiqVal">R$ 0,00</span></span>
                <span class="ret-chip ret-chip-conf"><i class="fa-solid fa-file-import"></i><span id="lblConf">0 entradas confirmadas</span></span>
                <span class="ret-chip ret-chip-rej"><i class="fa-solid fa-circle-xmark"></i><span id="lblRej">0 rejeitados</span></span>
                <span class="ret-chip ret-chip-out"><i class="fa-solid fa-circle-info"></i><span id="lblOut">0 outros</span></span>
            </div>
            <div class="ret-bar-right">
                <button class="ret-btn ret-btn-aplic" id="btnAplicar" onclick="retAplicar()">
                    <i class="fa-solid fa-check-double"></i> Processar Retorno
                </button>
            </div>
        </div>
    </div>

    <!-- grid de resultado -->
    <div id="divGrid" class="ret-table-wrap">
        <div style="overflow-x:auto;">
            <table class="ret-table">
                <thead>
                    <tr>
                        <th>Nosso Nº</th>
                        <th>Associado</th>
                        <th>Vencimento</th>
                        <th style="text-align:right;">Valor Cobrado</th>
                        <th style="text-align:right;">Valor Pago</th>
                        <th style="text-align:right;">Juros/Multa</th>
                        <th style="text-align:center;">Data Crédito</th>
                        <th style="text-align:center;">Ocorrência</th>
                        <th style="text-align:center;">Status</th>
                    </tr>
                </thead>
                <tbody id="tbodyRetorno">
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
(function () {
    var Swal2  = window.Swal; /* captura v11 ANTES do vendors.bundle.js (footer) sobrescrever */
    var ACTION = '<?= ACTION_URL ?>/retorno.php';
    var dadosRet = null;

    /* ── seleção de arquivo ─────────────────────────────────────────── */
    document.getElementById('lblArquivo').addEventListener('click', function () {
        document.getElementById('inputArquivo').click();
    });
    document.getElementById('inputArquivo').addEventListener('change', function () {
        var f = this.files[0];
        document.getElementById('nomeArquivo').textContent = f ? f.name : 'Selecionar arquivo .ret...';
    });

    /* ── helpers ────────────────────────────────────────────────────── */
    function fmtData(iso) {
        if (!iso) return '—';
        var p = iso.split('-');
        return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : iso;
    }

    function fmtVal(v) {
        v = parseFloat(v) || 0;
        return 'R$ ' + v.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function badgeHtml(reg) {
        if (reg.tipo === 'liquidacao') {
            var extra = reg.ja_liquidado ? ' (já baixado)' : '';
            return '<span class="ret-badge ret-badge-liq">Liquidação' + extra + '</span>';
        }
        if (reg.tipo === 'confirmacao') {
            return '<span class="ret-badge ret-badge-conf">Entrada Confirmada</span>';
        }
        if (reg.tipo === 'rejeicao') {
            var motivos = (reg.motivos && reg.motivos.length) ? reg.motivos.join('&#10;') : reg.descricao_mov;
            return '<span class="ret-badge ret-badge-rej" onclick="mostrarMotivos(this)" data-motivos="'
                + motivos.replace(/"/g, '&quot;') + '">Rejeitado ⓘ</span>';
        }
        return '<span class="ret-badge ret-badge-out">' + (reg.descricao_mov || reg.codigo_mov || '—') + '</span>';
    }

    window.mostrarMotivos = function (el) {
        var txt = el.getAttribute('data-motivos') || 'Sem detalhes';
        Swal2.fire({
            title: 'Motivos de Rejeição',
            html: '<pre style="text-align:left;font-size:13px;white-space:pre-wrap;margin:0;">' + txt + '</pre>',
            icon: 'error',
            confirmButtonText: 'Fechar',
        });
    };

    /* ── processar arquivo ──────────────────────────────────────────── */
    window.retProcessar = function () {
        var input = document.getElementById('inputArquivo');
        if (!input.files || !input.files[0]) {
            Swal2.fire({ icon: 'warning', title: 'Selecione um arquivo .ret', timer: 2000, showConfirmButton: false });
            return;
        }

        var fd = new FormData();
        fd.append('arquivo', input.files[0]);

        Swal2.fire({
            title: 'Processando arquivo...',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: function () { Swal2.showLoading(); }
        });

        fetch(ACTION + '?acao=processar', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                Swal2.close();
                if (!json.success) {
                    Swal2.fire({ icon: 'error', title: 'Erro', text: json.message });
                    return;
                }
                dadosRet = json;
                renderGrid(json);
            })
            .catch(function (e) {
                Swal2.close();
                Swal2.fire({ icon: 'error', title: 'Erro de comunicação', text: e.message });
            });
    };

    /* ── renderizar grid ────────────────────────────────────────────── */
    function renderGrid(json) {
        var regs = json.registros || [];
        var tot  = json.totais    || {};
        var liq  = tot.liquidacoes  || { qtd: 0, valor: 0 };
        var conf = tot.confirmacoes || { qtd: 0 };
        var rej  = tot.rejeicoes   || { qtd: 0 };
        var outQtd = regs.length - (liq.qtd || 0) - (conf.qtd || 0) - (rej.qtd || 0);
        if (outQtd < 0) outQtd = 0;

        document.getElementById('lblLiq').textContent    = (liq.qtd || 0) + ' liquidaç' + (liq.qtd === 1 ? 'ão' : 'ões');
        document.getElementById('lblLiqVal').textContent = fmtVal(liq.valor || 0);
        document.getElementById('lblConf').textContent   = (conf.qtd || 0) + ' entrada' + (conf.qtd === 1 ? '' : 's') + ' confirmada' + (conf.qtd === 1 ? '' : 's');
        document.getElementById('lblRej').textContent    = (rej.qtd || 0) + ' rejeitado' + (rej.qtd === 1 ? '' : 's');
        document.getElementById('lblOut').textContent    = outQtd + ' outro' + (outQtd === 1 ? '' : 's');

        var html = '';
        regs.forEach(function (r) {
            var cls = '';
            if (r.tipo === 'liquidacao')  cls = 'liq';
            if (r.tipo === 'confirmacao') cls = 'conf';
            if (r.tipo === 'rejeicao')   cls = 'rej';

            html += '<tr class="' + cls + '">'
                + '<td>' + (r.nosso_numero || '—') + '</td>'
                + '<td>' + (r.pes_nome || '<span style="color:#adb5bd;font-style:italic;">não encontrado</span>') + '</td>'
                + '<td>' + fmtData(r.vencimento) + '</td>'
                + '<td style="text-align:right;">' + (r.valor_cobrado ? fmtVal(r.valor_cobrado) : '—') + '</td>'
                + '<td style="text-align:right;">' + (r.valor_pago    ? fmtVal(r.valor_pago)    : '—') + '</td>'
                + '<td style="text-align:right;">' + (r.juros_multa ? fmtVal(r.juros_multa) : '—') + '</td>'
                + '<td style="text-align:center;">' + fmtData(r.data_credito) + '</td>'
                + '<td style="text-align:center;">' + (r.codigo_mov || '—') + '</td>'
                + '<td style="text-align:center;">' + badgeHtml(r) + '</td>'
                + '</tr>';
        });

        document.getElementById('tbodyRetorno').innerHTML = html
            || '<tr><td colspan="9" style="text-align:center;padding:30px;color:#adb5bd;">Nenhum registro encontrado</td></tr>';

        document.getElementById('divTotais').style.display = 'block';
        document.getElementById('divGrid').style.display   = 'block';

        /* reabilita botão aplicar (caso tenha sido usado antes) */
        var btn = document.getElementById('btnAplicar');
        btn.disabled = false;
        btn.style.opacity = '';
        btn.style.cursor  = '';
    }

    /* ── aplicar retorno no banco ───────────────────────────────────── */
    window.retAplicar = function () {
        if (!dadosRet || !dadosRet.registros || dadosRet.registros.length === 0) {
            Swal2.fire({ icon: 'warning', title: 'Nada a aplicar', text: 'Processe um arquivo primeiro.' });
            return;
        }

        var liq = (dadosRet.totais && dadosRet.totais.liquidacoes) ? dadosRet.totais.liquidacoes : {};
        var qtd = liq.qtd || 0;

        if (qtd === 0) {
            Swal2.fire({ icon: 'info', title: 'Sem liquidações', text: 'Não há registros de liquidação neste arquivo para baixar.' });
            return;
        }

        Swal2.fire({
            icon: 'question',
            title: 'Confirmar aplicação',
            html: 'Serão baixadas <strong>' + qtd + ' parcela(s)</strong> no valor de <strong>' + fmtVal(liq.valor || 0) + '</strong>.<br><br>Deseja continuar?',
            showCancelButton: true,
            confirmButtonColor: '#2f9e44',
            cancelButtonColor: '#868e96',
            confirmButtonText: 'Sim, aplicar',
            cancelButtonText: 'Cancelar',
        }).then(function (res) {
            if (!res.isConfirmed) return;

            Swal2.fire({
                title: 'Aplicando retorno...',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: function () { Swal2.showLoading(); }
            });

            fetch(ACTION + '?acao=aplicar', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ registros: dadosRet.registros }),
            })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                Swal2.close();
                if (!json.success) {
                    Swal2.fire({ icon: 'error', title: 'Erro', text: json.message });
                    return;
                }
                Swal2.fire({
                    icon: 'success',
                    title: 'Retorno aplicado!',
                    html: json.message,
                    confirmButtonText: 'OK',
                }).then(function () {
                    var btn = document.getElementById('btnAplicar');
                    btn.disabled = true;
                    btn.style.opacity = '0.5';
                    btn.style.cursor  = 'not-allowed';
                    dadosRet = null;
                });
            })
            .catch(function (e) {
                Swal2.close();
                Swal2.fire({ icon: 'error', title: 'Erro de comunicação', text: e.message });
            });
        });
    };

}());
</script>

<?php include __DIR__ . '/../../../inc/footer.php'; ?>
