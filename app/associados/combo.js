$(document).ready(function () {
    window.atualizarTotalVeiculo = function() { atualizarTotal(); };

    function parseMoney(v) {
        if (v == null) return 0;
        v = String(v).replace(/\s|R\$/g, '').trim();
        const hasComma = v.indexOf(',') !== -1;
        const hasDot   = v.indexOf('.') !== -1;
        if (hasComma && hasDot) {
            v = v.replace(/\./g, '').replace(',', '.');
        } else if (hasComma) {
            v = v.replace(',', '.');
        }
        const n = parseFloat(v);
        return isNaN(n) ? 0 : n;
    }

    function formatBR(n) {
        return (parseFloat(n) || 0).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function atualizarTotal() {
        const mensalidade    = parseMoney($('#mensalidade').val());
        const valorCombo     = parseMoney($('#valorCombo').val());
        const valorRastreador = parseMoney($('#valorRastreador').val());
        const total = mensalidade + valorCombo + valorRastreador;
        $('#totalFinal').val(
            total.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
        );
    }

    // Grupo → preenche adesão/mensalidade e recalcula
    $('#grupo').on('change', function () {
        const $opt = $(this).find(':selected');
        $('#adesao').val(formatBR(parseMoney($opt.data('adesao') || '')));
        $('#mensalidade').val(formatBR(parseMoney($opt.data('mensalidade') || '')));
        atualizarTotal();
    });

    // Combo → tratado via delegação em modal_cad_veiculo.php
    $('#combo').on('change', function () {
        if (typeof window.onComboChange === 'function') {
            window.onComboChange($(this).val());
        } else {
            const valorStr = $(this).find(':selected').data('valor') || '';
            $('#valorCombo').val(formatBR(parseMoney(valorStr)));
            atualizarTotal();
        }
    });

    // Rastreador → preenche valor e recalcula
    $('#rastreador').on('change', function () {
        const valorStr = $(this).find(':selected').data('valor') || '';
        $('#valorRastreador').val(formatBR(parseMoney(valorStr)));
        atualizarTotal();
    });

    // Mensalidade editada manualmente → recalcula
    $('#mensalidade').on('input change', function () {
        atualizarTotal();
    });

    // Normaliza campos já preenchidos (modo edição) e calcula
    (function normalizarExistentes() {
        ['#mensalidade', '#valorCombo', '#valorRastreador', '#adesao'].forEach(function (sel) {
            const $el = $(sel);
            if ($el.length && $el.val()) {
                $el.val(formatBR(parseMoney($el.val())));
            }
        });
        atualizarTotal();
    })();
});
