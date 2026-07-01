<?php
include __DIR__ . '/../../inc/header.php';
require PATH_INC . '/db.php';
try {
    $cfg = $pdo->query("SELECT * FROM tb_configuracoes WHERE CFG_CODIGO_PK = 1")->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { $cfg = []; }
$cfgJs = json_encode([
    'razaoSocial' => $cfg['CFG_RAZAO_SOCIAL'] ?? '',
    'cnpj'        => $cfg['CFG_CNPJ'] ?? '',
    'endereco'    => trim(($cfg['CFG_ENDERECO'] ?? '') . ($cfg['CFG_BAIRRO'] ?? '' ? ', ' . $cfg['CFG_BAIRRO'] : ''), ', '),
    'cidade'      => trim(($cfg['CFG_CIDADE'] ?? '') . ($cfg['CFG_UF'] ?? '' ? ' - ' . $cfg['CFG_UF'] : '')),
    'fone'        => $cfg['CFG_FONE'] ?? '',
    'logoUrl'     => $cfg['CFG_LOGO_PATH'] ?? '',
], JSON_UNESCAPED_UNICODE);
?>
<style>
.bp-wrap {
    max-width: 680px;
    margin: 0 auto;
    padding: 32px 16px 48px;
}
.bp-card {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 18px;
    box-shadow: 0 4px 24px rgba(30,40,80,.08);
    overflow: hidden;
}
.bp-card-header {
    background: linear-gradient(135deg, #1a1d2e 0%, #2d3250 100%);
    color: #fff;
    padding: 20px 26px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.bp-card-header i { font-size: 20px; opacity: .85; }
.bp-card-header h4 { margin: 0; font-size: 17px; font-weight: 700; }
.bp-card-body { padding: 28px 26px 24px; }

.bp-search-row {
    display: flex;
    gap: 10px;
    margin-bottom: 28px;
}
.bp-search-row input {
    flex: 1;
    height: 44px;
    border: 2px solid #dbe2ea;
    border-radius: 11px;
    font-size: 15px;
    font-weight: 600;
    padding: 0 14px;
    letter-spacing: .04em;
    transition: border-color .15s;
}
.bp-search-row input:focus { border-color: #3b5bdb; outline: none; box-shadow: 0 0 0 3px rgba(59,91,219,.1); }
.bp-btn-buscar {
    height: 44px;
    padding: 0 22px;
    background: #3b5bdb;
    color: #fff;
    border: none;
    border-radius: 11px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: .15s;
    white-space: nowrap;
}
.bp-btn-buscar:hover { background: #2f52d6; }
.bp-btn-buscar:disabled { opacity: .6; cursor: not-allowed; }

.bp-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px 20px;
}
.bp-grid .bp-full { grid-column: 1 / -1; }
.bp-field label {
    display: block;
    font-size: 11.5px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: .06em;
    margin-bottom: 5px;
}
.bp-field input {
    width: 100%;
    height: 40px;
    border: 1.5px solid #dbe2ea;
    border-radius: 9px;
    font-size: 13.5px;
    padding: 0 12px;
    background: #f8fafc;
    color: #1a1d2e;
    box-sizing: border-box;
    transition: border-color .15s;
}
.bp-field input[readonly] { background: #f1f5f9; color: #64748b; cursor: default; }
.bp-field input:focus { border-color: #3b5bdb; outline: none; box-shadow: 0 0 0 3px rgba(59,91,219,.1); background: #fff; }

/* Campos editáveis — branco, sempre clicáveis, !important vence qualquer bundle externo */
#bpDesconto, #bpJuros, #bpMulta, #bpValorRecebido {
    background: #fff !important;
    color: #1a1d2e !important;
    border-color: #93c5fd !important;
    cursor: text !important;
    pointer-events: auto !important;
}
#bpDesconto:focus, #bpJuros:focus, #bpMulta:focus, #bpValorRecebido:focus {
    border-color: #3b5bdb !important;
    box-shadow: 0 0 0 3px rgba(59,91,219,.15) !important;
}

/* Parcela paga/cancelada: bloqueia edição via CSS (sem tocar no atributo disabled) */
#bpCampos.bp-bloqueado #bpDesconto,
#bpCampos.bp-bloqueado #bpJuros,
#bpCampos.bp-bloqueado #bpMulta,
#bpCampos.bp-bloqueado #bpValorRecebido {
    background: #f1f5f9 !important;
    color: #94a3b8 !important;
    border-color: #dbe2ea !important;
    cursor: not-allowed !important;
    pointer-events: none !important;
}

.bp-field input.bp-valor-pagar { background: #edf2ff !important; color: #3b5bdb !important; font-weight: 700; border-color: #bac8ff !important; }
.bp-field input.bp-troco { background: #f0fdf4 !important; color: #16a34a !important; font-weight: 700; border-color: #bbf7d0 !important; }
.bp-field input.bp-troco.negativo { background: #fff1f2 !important; color: #dc2626 !important; border-color: #fecaca !important; }

.bp-divider { border: none; border-top: 1.5px dashed #e2e8f0; margin: 18px 0 14px; }

.bp-btn-baixar {
    display: block;
    width: 100%;
    height: 48px;
    margin-top: 24px;
    background: linear-gradient(135deg, #2f9e44, #2b8a3e);
    color: #fff;
    border: none;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    transition: .15s;
}
.bp-btn-baixar:hover { opacity: .92; transform: translateY(-1px); box-shadow: 0 6px 18px rgba(47,158,68,.3); }
.bp-btn-baixar:disabled,
.bp-btn-baixar.bp-inativo { opacity: .45; cursor: not-allowed; transform: none; box-shadow: none; pointer-events: none; }

.bp-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    margin-bottom: 18px;
}
.bp-status.pago     { background: #d3f9d8; color: #2f9e44; }
.bp-status.aberto   { background: #fff3cd; color: #856404; }
.bp-status.vencido  { background: #ffe3e3; color: #c92a2a; }
.bp-status.cancelado{ background: #e9ecef; color: #6c757d; }
</style>

<div class="bp-wrap">
    <div class="bp-card">
        <div class="bp-card-header">
            <i class="fa-solid fa-hand-holding-dollar"></i>
            <h4>Baixa Manual de Parcela</h4>
        </div>
        <div class="bp-card-body">

            <!-- Busca -->
            <div class="bp-search-row">
                <input type="text" id="bpNossoNum" placeholder="Nº da parcela (# ou Nosso Nº)"
                       inputmode="numeric" autocomplete="off">
                <button type="button" class="bp-btn-buscar" id="bpBtnBuscar">
                    <i class="fa-solid fa-magnifying-glass mr-1"></i>Pesquisar
                </button>
            </div>

            <!-- Status badge -->
            <div id="bpStatusWrap" style="display:none;"></div>

            <!-- Campos — sempre visíveis e no DOM, nunca ocultos -->
            <div id="bpCampos">
                <div class="bp-grid">
                    <div class="bp-field">
                        <label># (Código)</label>
                        <input type="text" id="bpNumParcela" readonly tabindex="-1">
                    </div>
                    <div class="bp-field">
                        <label>Nosso Nº</label>
                        <input type="text" id="bpNossoNumero" readonly tabindex="-1">
                    </div>
                    <div class="bp-field">
                        <label>Vencimento</label>
                        <input type="text" id="bpVencimento" readonly tabindex="-1">
                    </div>
                    <div class="bp-field">
                        <label>Placa</label>
                        <input type="text" id="bpPlaca" readonly tabindex="-1">
                    </div>
                    <div class="bp-field">
                        <label>Nome do Associado</label>
                        <input type="text" id="bpNomeAssoc" readonly tabindex="-1">
                    </div>
                    <div class="bp-field">
                        <label>Valor da Parcela</label>
                        <input type="text" id="bpValorParcela" readonly tabindex="-1">
                    </div>

                    <hr class="bp-divider bp-full">

                    <div class="bp-field">
                        <label>Desconto (R$)</label>
                        <input type="text" id="bpDesconto" inputmode="decimal" autocomplete="off">
                    </div>
                    <div class="bp-field">
                        <label>Juros (R$)</label>
                        <input type="text" id="bpJuros" inputmode="decimal" autocomplete="off">
                    </div>
                    <div class="bp-field">
                        <label>Multa (R$)</label>
                        <input type="text" id="bpMulta" inputmode="decimal" autocomplete="off">
                    </div>
                    <div class="bp-field">
                        <label>Valor a Pagar (R$)</label>
                        <input type="text" id="bpValorPagar" class="bp-valor-pagar" readonly tabindex="-1">
                    </div>
                    <div class="bp-field">
                        <label>Valor Recebido (R$)</label>
                        <input type="text" id="bpValorRecebido" inputmode="decimal" autocomplete="off">
                    </div>
                    <div class="bp-field">
                        <label>Troco (R$)</label>
                        <input type="text" id="bpTroco" class="bp-troco" readonly>
                    </div>
                </div>

                <button type="button" class="bp-btn-baixar bp-inativo" id="bpBtnBaixar"
                        onclick="if(window._bpBaixar)window._bpBaixar()">
                    <i class="fa-solid fa-circle-check mr-2"></i>Baixar Parcela
                </button>
            </div>

        </div>
    </div>
</div>

<?php include __DIR__ . '/../../inc/footer.php'; ?>
<!-- Recarrega SweetAlert2 após vendors.bundle.js para garantir versão correta -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
(function () {
    'use strict';

    /* ── Imuniza os campos editáveis contra qualquer script externo (Metronic/jQuery)
          que tente setar disabled ou readOnly.
          Object.defineProperty no próprio elemento sobrepõe o prototype do navegador.
          O override de setAttribute bloqueia a via de atributo HTML.        ── */
    var IDS_EDITAVEIS = ['bpDesconto','bpJuros','bpMulta','bpValorRecebido'];

    function imunizar() {
        IDS_EDITAVEIS.forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;

            el.removeAttribute('disabled');
            el.removeAttribute('readonly');

            try {
                Object.defineProperty(el, 'disabled', {
                    get: function () { return false; },
                    set: function () { /* bloqueado intencionalmente */ },
                    configurable: true
                });
                Object.defineProperty(el, 'readOnly', {
                    get: function () { return false; },
                    set: function () { /* bloqueado intencionalmente */ },
                    configurable: true
                });
            } catch (e) { /* navegadores antigos ignoram */ }

            /* Bloqueia também a via setAttribute */
            var _origSetAttr = el.setAttribute.bind(el);
            el.setAttribute = function (nome, valor) {
                if (nome === 'disabled' || nome === 'readonly') return;
                _origSetAttr(nome, valor);
            };
        });
    }

    /* Roda imediatamente (scripts já executaram, DOM existe) */
    imunizar();

    /* Roda de novo no DOMContentLoaded por precaução */
    document.addEventListener('DOMContentLoaded', function () {
        imunizar();
        iniciarApp();
    });

    function iniciarApp() {

        var ACTION = '<?= ACTION_URL ?>/cobrancas.php';
        var cobId         = 0;
        var valorBase     = 0;
        var baixarAtivo   = false;

        var els = {
            nossoNum     : document.getElementById('bpNossoNum'),
            btnBuscar    : document.getElementById('bpBtnBuscar'),
            statusWrap   : document.getElementById('bpStatusWrap'),
            campos       : document.getElementById('bpCampos'),
            numParcela   : document.getElementById('bpNumParcela'),
            nossoNumero  : document.getElementById('bpNossoNumero'),
            vencimento   : document.getElementById('bpVencimento'),
            placa        : document.getElementById('bpPlaca'),
            valorParcela : document.getElementById('bpValorParcela'),
            nomeAssoc    : document.getElementById('bpNomeAssoc'),
            desconto     : document.getElementById('bpDesconto'),
            juros        : document.getElementById('bpJuros'),
            multa        : document.getElementById('bpMulta'),
            valorPagar   : document.getElementById('bpValorPagar'),
            valorRecebido: document.getElementById('bpValorRecebido'),
            troco        : document.getElementById('bpTroco'),
            btnBaixar    : document.getElementById('bpBtnBaixar'),
        };

        /* ── helpers ── */
        function parseMoeda(v) {
            return parseFloat((v || '0').replace(/\./g, '').replace(',', '.')) || 0;
        }
        function fmtMoeda(n) {
            return n.toFixed(2).replace('.', ',');
        }
        function fmtData(iso) {
            if (!iso) return '';
            var p = iso.split('-');
            return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : iso;
        }

        function recalcular() {
            var pagar = Math.max(0, valorBase
                + parseMoeda(els.juros.value)
                + parseMoeda(els.multa.value)
                - parseMoeda(els.desconto.value));
            els.valorPagar.value = fmtMoeda(pagar);
            recalcularTroco();
        }
        function recalcularTroco() {
            var troco = parseMoeda(els.valorRecebido.value) - parseMoeda(els.valorPagar.value);
            els.troco.value = fmtMoeda(troco);
            els.troco.classList.toggle('negativo', troco < 0);
        }

        function popularCampos(d) {
            cobId     = parseInt(d.COB_CODIGO_PK);
            valorBase = parseFloat(d.COB_VALOR || '0');
            els.numParcela.value   = d.COB_CODIGO_PK  || '';
            els.nossoNumero.value  = d.COB_NOSSO_NUMERO || '';
            els.vencimento.value   = fmtData(d.COB_DATA_VENCIMENTO || '');
            els.placa.value        = d.VEI_PLACA || d.COB_PLACAS || '';
            els.nomeAssoc.value    = d.PES_NOME  || '';
            els.valorParcela.value = fmtMoeda(valorBase);
            els.desconto.value     = fmtMoeda(parseFloat(d.COB_DESCONTO || '0'));
            els.juros.value        = fmtMoeda(parseFloat(d.COB_JUROS    || '0'));
            els.multa.value        = fmtMoeda(parseFloat(d.COB_MULTA    || '0'));
            recalcular();
            els.valorRecebido.value = els.valorPagar.value;
            recalcularTroco();
        }

        function limparCampos() {
            ['numParcela','nossoNumero','vencimento','placa','valorParcela','nomeAssoc',
             'desconto','juros','multa','valorPagar','valorRecebido','troco']
                .forEach(function (k) { els[k].value = ''; });
            els.campos.classList.remove('bp-bloqueado');
            els.btnBaixar.classList.add('bp-inativo');
            baixarAtivo = false;
            cobId = 0; valorBase = 0;
        }

        function mostrarStatus(d) {
            var pago      = (d.COB_PAGO === 'SIM');
            var cancelado = (d.COB_BOLETO_CANCELADO === 'SIM');
            var vencido   = !pago && !cancelado && new Date(d.COB_DATA_VENCIMENTO) < new Date();

            var cls, icon, txt;
            if (cancelado)    { cls='cancelado'; icon='fa-ban';                  txt='Cancelada'; }
            else if (pago)    { cls='pago';      icon='fa-circle-check';         txt='Paga em '+fmtData(d.COB_DATA_QUITACAO||''); }
            else if (vencido) { cls='vencido';   icon='fa-triangle-exclamation'; txt='Vencida'; }
            else              { cls='aberto';    icon='fa-clock';                txt='Em aberto'; }

            els.statusWrap.innerHTML =
                '<span class="bp-status '+cls+'"><i class="fa-solid '+icon+'"></i> '+txt+'</span>';
            els.statusWrap.style.display = 'block';

            var bloqueado = pago || cancelado;
            baixarAtivo   = !bloqueado;
            els.campos.classList.toggle('bp-bloqueado', bloqueado);
            els.btnBaixar.classList.toggle('bp-inativo', bloqueado);

            if (pago) {
                Swal.fire({
                    icon: 'info',
                    title: 'Parcela já paga',
                    html: 'Paga em <b>' + fmtData(d.COB_DATA_QUITACAO || '') + '</b>.<br>Deseja imprimir a 2ª via do recibo?',
                    showCancelButton: true,
                    confirmButtonText: '<i class="fa-solid fa-print"></i> Imprimir 2ª via',
                    cancelButtonText: 'Não',
                    confirmButtonColor: '#3b5bdb',
                    cancelButtonColor: '#6c757d',
                    returnFocus: false,
                }).then(function (res) {
                    if (res.isConfirmed) {
                        imprimirRecibo({
                            numParcela:    els.numParcela.value,
                            nossoNumero:   els.nossoNumero.value,
                            nomeAssoc:     els.nomeAssoc.value,
                            placa:         els.placa.value,
                            vencimento:    els.vencimento.value,
                            valorParcela:  els.valorParcela.value,
                            desconto:      els.desconto.value,
                            juros:         els.juros.value,
                            multa:         els.multa.value,
                            valorPagar:    els.valorPagar.value,
                            valorRecebido: els.valorPagar.value,
                            troco:         '0,00',
                            dataHora:      (function() {
                                var q = d.COB_DATA_QUITACAO || '';
                                var partes = q.split(/[ T]/);
                                var dt = fmtData(partes[0] || '') || new Date().toLocaleDateString('pt-BR');
                                var hr = (partes[1] || '').substring(0, 5);
                                return hr ? dt + ' ' + hr : dt;
                            })(),
                            operador:      '<?= addslashes($sessNome) ?>',
                        });
                    }
                    setTimeout(function () { els.nossoNum.focus(); }, 0);
                });
            } else if (!bloqueado) {
                els.desconto.focus();
                els.desconto.select();
            }
        }

        function pesquisar() {
            var n = els.nossoNum.value.trim();
            if (!n) { els.nossoNum.focus(); return; }

            els.btnBuscar.disabled = true;
            els.btnBuscar.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i>Buscando...';
            els.statusWrap.style.display = 'none';
            limparCampos();

            fetch(ACTION + '?acao=buscar_nosso_numero&nosso_numero=' + encodeURIComponent(n))
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (!j.success) {
                        Swal.fire({ icon:'warning', title:'Não encontrado', text:j.message, confirmButtonColor:'#3b5bdb' });
                        return;
                    }
                    popularCampos(j.data);
                    mostrarStatus(j.data);
                })
                .catch(function () {
                    Swal.fire({ icon:'error', title:'Erro', text:'Falha ao comunicar com o servidor.', confirmButtonColor:'#3b5bdb' });
                })
                .finally(function () {
                    els.btnBuscar.disabled = false;
                    els.btnBuscar.innerHTML = '<i class="fa-solid fa-magnifying-glass mr-1"></i>Pesquisar';
                });
        }

        function baixar() {
            if (!cobId || !baixarAtivo) return;
            var pagar = parseMoeda(els.valorPagar.value);
            if (pagar <= 0) {
                Swal.fire({ icon:'warning', title:'Atenção', text:'Valor a pagar inválido.', confirmButtonColor:'#3b5bdb' });
                return;
            }

            Swal.fire({
                icon: 'question',
                title: 'Confirma baixa da parcela?',
                html: '<b>#' + els.numParcela.value + '</b> — ' + els.nomeAssoc.value +
                      '<br>Valor a pagar: <b>R$ ' + els.valorPagar.value + '</b>',
                showCancelButton: true,
                confirmButtonText: 'Sim, baixar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#2f9e44',
                cancelButtonColor: '#6c757d',
                reverseButtons: true,
            }).then(function (res) {
                if (!res.isConfirmed) return;

                els.btnBaixar.classList.add('bp-inativo');
                els.btnBaixar.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>Baixando...';

                var fd = new FormData();
                fd.append('acao',           'baixar_manual');
                fd.append('cob_id',         cobId);
                fd.append('desconto',       els.desconto.value);
                fd.append('juros',          els.juros.value);
                fd.append('multa',          els.multa.value);
                fd.append('valor_pagar',    els.valorPagar.value);
                fd.append('valor_recebido', els.valorRecebido.value);

                fetch(ACTION, { method:'POST', body:fd })
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        if (j.success) {
                            var dadosRecibo = {
                                numParcela:    els.numParcela.value,
                                nossoNumero:   els.nossoNumero.value,
                                nomeAssoc:     els.nomeAssoc.value,
                                placa:         els.placa.value,
                                vencimento:    els.vencimento.value,
                                valorParcela:  els.valorParcela.value,
                                desconto:      els.desconto.value,
                                juros:         els.juros.value,
                                multa:         els.multa.value,
                                valorPagar:    els.valorPagar.value,
                                valorRecebido: els.valorRecebido.value,
                                troco:         els.troco.value,
                                dataHora:      (function() { var n = new Date(); return n.toLocaleDateString('pt-BR') + ' ' + n.toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'}); })(),
                                operador:      '<?= addslashes($sessNome) ?>',
                            };
                            els.btnBaixar.innerHTML = '<i class="fa-solid fa-circle-check mr-2"></i>Baixar Parcela';
                            els.nossoNum.value = '';
                            els.statusWrap.style.display = 'none';
                            limparCampos();
                            Swal.fire({
                                icon: 'success',
                                title: 'Parcela baixada!',
                                text: j.message,
                                confirmButtonColor: '#2f9e44',
                                returnFocus: false,
                            }).then(function () {
                                return Swal.fire({
                                    icon: 'question',
                                    title: 'Deseja imprimir o recibo?',
                                    text: '2 vias — cliente e estabelecimento',
                                    showCancelButton: true,
                                    confirmButtonText: '<i class="fa-solid fa-print"></i> Imprimir',
                                    cancelButtonText: 'Não, obrigado',
                                    confirmButtonColor: '#3b5bdb',
                                    cancelButtonColor: '#6c757d',
                                    returnFocus: false,
                                });
                            }).then(function (res) {
                                if (res.isConfirmed) imprimirRecibo(dadosRecibo);
                                setTimeout(function () { els.nossoNum.focus(); }, 0);
                            });
                        } else {
                            Swal.fire({ icon:'error', title:'Erro', text:j.message, confirmButtonColor:'#3b5bdb' });
                            els.btnBaixar.classList.remove('bp-inativo');
                            els.btnBaixar.innerHTML = '<i class="fa-solid fa-circle-check mr-2"></i>Baixar Parcela';
                        }
                    })
                    .catch(function () {
                        Swal.fire({ icon:'error', title:'Erro', text:'Falha ao comunicar com o servidor.', confirmButtonColor:'#3b5bdb' });
                        els.btnBaixar.classList.remove('bp-inativo');
                        els.btnBaixar.innerHTML = '<i class="fa-solid fa-circle-check mr-2"></i>Baixar Parcela';
                    });
            });
        }
        window._bpBaixar = baixar;

        /* ── Tab e Enter navegam na ordem: Desconto → Juros → Multa → V.Recebido → Troco → [baixar] ── */
        var NAV = ['bpDesconto','bpJuros','bpMulta','bpValorRecebido','bpTroco'];
        function avancarCampo(e) {
            if (e.key !== 'Enter' && e.key !== 'Tab') return;
            e.preventDefault();
            if (e.target.id === 'bpTroco') { if (baixarAtivo) baixar(); return; }
            var idx = NAV.indexOf(e.target.id);
            if (idx >= 0 && idx < NAV.length - 1) {
                var prox = document.getElementById(NAV[idx + 1]);
                prox.focus();
                prox.select();
            }
        }

        /* ── eventos ── */
        els.nossoNum.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); pesquisar(); }
        });
        els.btnBuscar.addEventListener('click', pesquisar);
        ['desconto','juros','multa'].forEach(function (k) {
            els[k].addEventListener('change', recalcular);
            els[k].addEventListener('keydown', avancarCampo);
        });
        els.valorRecebido.addEventListener('input', recalcularTroco);
        els.valorRecebido.addEventListener('keydown', avancarCampo);
        els.troco.addEventListener('keydown', avancarCampo);
        els.btnBaixar.addEventListener('click', baixar);

        els.nossoNum.focus();
    }

    /* ── Impressão térmica ── */
    var CFG = <?= $cfgJs ?>;

    function parseMoedaGlobal(v) {
        return parseFloat((v || '0').replace(/\./g, '').replace(',', '.')) || 0;
    }

    function imprimirRecibo(d) {
        var via = gerarVia(d);
        var corte = '<div class="corte">✂ — — — — — RECORTAR — — — — — ✂</div>';
        var html = '<!DOCTYPE html><html lang="pt-BR"><head>'
            + '<meta charset="utf-8"><title>Recibo</title>'
            + '<style>'
            + '*{box-sizing:border-box;margin:0;padding:0;font-weight:bold}'
            + 'body{font-family:"Courier New",monospace;font-size:9pt;width:100%;padding:2mm 7mm 2mm 10mm;margin:0;color:#000}'
            + '.via{width:100%;padding:2mm 0}'
            + '.empresa{text-align:center;font-weight:bold;font-size:10pt;margin-bottom:1mm}'
            + '.sub{text-align:center;font-size:7.5pt;margin-bottom:3mm;line-height:1.5}'
            + '.titulo{text-align:center;font-weight:bold;font-size:9pt;border-top:1px solid #000;border-bottom:1px solid #000;padding:1.5mm 0;margin:2mm 0;letter-spacing:1px}'
            + '.ln{display:flex;justify-content:space-between;margin-bottom:1.5mm;font-size:8.5pt}'
            + '.ln .lbl{color:#555}'
            + '.ln .val{font-weight:bold;text-align:right;max-width:60%;word-break:break-word}'
            + '.sep{border-top:1px dashed #ccc;margin:2mm 0}'
            + '.total{display:flex;justify-content:space-between;font-size:10pt;font-weight:bold;margin:2mm 0}'
            + '.assinatura{border-top:1px solid #000;margin-top:7mm;padding-top:1mm;text-align:center;font-size:7.5pt;color:#555}'
            + '.label-via{text-align:center;font-size:7pt;font-style:italic;margin-top:2mm;color:#555}'
            + '.corte{border-top:1px dashed #000;border-bottom:1px dashed #000;text-align:center;font-size:7.5pt;padding:1.5mm 0;margin:2mm 0}'
            + '.logo-wrap{text-align:center;margin-bottom:2mm}'
            + '.logo{max-width:60%;max-height:10mm;object-fit:contain}'
            + '@page{margin:0}'
            + '</style></head><body>'
            + via.replace('{{LABEL}}', '1ª VIA — CLIENTE')
            + corte
            + via.replace('{{LABEL}}', '2ª VIA — ESTABELECIMENTO')
            + '</body></html>';

        var iframe = document.createElement('iframe');
        /* 302px ≈ 80mm a 96dpi — viewport correto para o layout não escalar na impressão */
        iframe.style.cssText = 'position:fixed;top:-9999px;left:-9999px;width:302px;height:1px;border:none;visibility:hidden;';
        document.body.appendChild(iframe);

        iframe.contentDocument.open();
        iframe.contentDocument.write(html);
        iframe.contentDocument.close();

        iframe.contentWindow.addEventListener('load', function () {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
            iframe.contentWindow.addEventListener('afterprint', function () {
                document.body.removeChild(iframe);
            });
        });
    }

    function gerarVia(d) {
        function ln(lbl, val) {
            return '<div class="ln"><span class="lbl">' + lbl + '</span><span class="val">' + val + '</span></div>';
        }

        var cabecalho = CFG.logoUrl
            ? '<div class="logo-wrap"><img src="' + CFG.logoUrl + '" class="logo"></div>'
            : '<div class="empresa">' + (CFG.razaoSocial || 'Valiantus') + '</div>';
        var sub = [
            CFG.cnpj     ? 'CNPJ: ' + CFG.cnpj : '',
            CFG.endereco || '',
            CFG.cidade   || '',
            CFG.fone     ? 'Tel: ' + CFG.fone : '',
        ].filter(Boolean).join('<br>');

        var linhas = '';
        linhas += ln('Parcela #',  d.numParcela);
        linhas += ln('Nosso Nº', d.nossoNumero);
        linhas += ln('Vencimento', d.vencimento);
        linhas += ln('Associado',  d.nomeAssoc);
        linhas += ln('Placa',      d.placa);
        linhas += '<div class="sep"></div>';
        linhas += ln('Valor da Parcela', 'R$ ' + d.valorParcela);
        if (parseMoedaGlobal(d.desconto) > 0) linhas += ln('Desconto', '- R$ ' + d.desconto);
        if (parseMoedaGlobal(d.juros)    > 0) linhas += ln('Juros',    '+ R$ ' + d.juros);
        if (parseMoedaGlobal(d.multa)    > 0) linhas += ln('Multa',    '+ R$ ' + d.multa);
        linhas += '<div class="sep"></div>';
        linhas += '<div class="total"><span>VALOR PAGO</span><span>R$ ' + d.valorPagar + '</span></div>';
        if (parseMoedaGlobal(d.valorRecebido) > parseMoedaGlobal(d.valorPagar)) {
            linhas += ln('Recebido', 'R$ ' + d.valorRecebido);
            linhas += ln('Troco',    'R$ ' + d.troco);
        }
        linhas += '<div class="sep"></div>';
        linhas += ln('Data/Hora', d.dataHora);
        linhas += ln('Operador',  d.operador);

        return '<div class="via">'
            + cabecalho
            + (sub ? '<div class="sub">' + sub + '</div>' : '')
            + '<div class="titulo">RECIBO DE PAGAMENTO</div>'
            + linhas
            + '<div class="assinatura">_________________________<br>Assinatura do Associado</div>'
            + '<div class="label-via">{{LABEL}}</div>'
            + '</div>';
    }

})();
</script>
