document.addEventListener('DOMContentLoaded', function () {

    function parseMoney(v) {
        if (v == null) return 0;
        v = String(v).replace(/\s|R\$/g, '').trim();
        var hasComma = v.indexOf(',') !== -1;
        var hasDot   = v.indexOf('.') !== -1;
        if (hasComma && hasDot) {
            v = v.replace(/\./g, '').replace(',', '.');
        } else if (hasComma) {
            v = v.replace(',', '.');
        }
        var n = parseFloat(v);
        return isNaN(n) ? 0 : n;
    }

    function formatBR(n) {
        return (parseFloat(n) || 0).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function maskBRL(e) {
        var el  = e.target;
        var raw = el.value.replace(/\D/g, '');
        if (!raw) { el.value = ''; return; }
        el.value = (parseInt(raw, 10) / 100).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function get(id) { return document.getElementById(id); }

    function atualizarTotal() {
        var mens  = parseMoney((get('mensalidade')     || {}).value);
        var combo = parseMoney((get('valorCombo')      || {}).value);
        var rast  = parseMoney((get('valorRastreador') || {}).value);
        var el = get('totalFinal');
        if (el) el.value = (mens + combo + rast).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }
    window.atualizarTotalVeiculo = atualizarTotal;

    // Grupo → preenche adesão/mensalidade e recalcula
    document.addEventListener('change', function (e) {
        if (!e.target || e.target.id !== 'grupo') return;
        var opt = e.target.options[e.target.selectedIndex];
        var elA = get('adesao');
        var elM = get('mensalidade');
        if (elA) elA.value = formatBR(parseMoney(opt ? (opt.getAttribute('data-adesao') || '') : ''));
        if (elM) elM.value = formatBR(parseMoney(opt ? (opt.getAttribute('data-mensalidade') || '') : ''));
        atualizarTotal();
    });

    // Rastreador → preenche valor e recalcula
    document.addEventListener('change', function (e) {
        if (!e.target || e.target.id !== 'rastreador') return;
        var opt = e.target.options[e.target.selectedIndex];
        var val = (opt && e.target.value) ? (parseFloat(opt.getAttribute('data-valor')) || 0) : 0;
        var el  = get('valorRastreador');
        if (el) el.value = val ? formatBR(val) : '';
        var lbl = get('labelTotalFinal');
        if (lbl) lbl.textContent = val ? 'R$ Mensalidade + Combo + Rastreador' : 'R$ Mensalidade + Combo';
        atualizarTotal();
    });

    // Máscara BRL ao digitar nos campos editáveis
    document.addEventListener('input', function (e) {
        var id = e.target && e.target.id;
        if (id === 'valorCobertura' || id === 'adesao' || id === 'mensalidade') {
            maskBRL(e);
        }
        if (id === 'mensalidade') {
            atualizarTotal();
        }
    });

    // Normaliza campos já preenchidos (modo edição) e calcula o total
    ['mensalidade', 'valorCombo', 'valorRastreador', 'adesao'].forEach(function (id) {
        var el = get(id);
        if (el && el.value) el.value = formatBR(parseMoney(el.value));
    });
    atualizarTotal();
});
