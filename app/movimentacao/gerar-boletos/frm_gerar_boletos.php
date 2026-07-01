<?php
if (!defined('PATH_INC')) require_once __DIR__ . '/../../../inc/config.php';
require_once PATH_INC . '/db.php';
require_once PATH_INC . '/csrf.php';
$csrf = csrf_token();

// Preview: contratos ativos e quanto já existe para o mês vigente
$totalAtivos = 0;
try {
    $totalAtivos = (int)$pdo->query("SELECT COUNT(*) FROM tb_contrato WHERE CTR_STATUS = 'A'")->fetchColumn();
} catch (Throwable $e) {}
?>

<link rel="stylesheet" href="../../valiantus-tables.css">

<style>
.gb-page   { max-width: 680px; margin: 0 auto; }
.gb-card   {
    background: #fff; border: 1px solid #e9ecef;
    border-radius: 16px; box-shadow: 0 4px 20px rgba(30,40,80,.07); overflow: hidden;
}
.gb-header {
    padding: 20px 28px; display: flex; align-items: center; gap: 14px;
    background: linear-gradient(135deg,#2f9e44,#27873a); color: #fff;
}
.gb-header i  { font-size: 22px; }
.gb-header h3 { margin: 0; font-size: 17px; font-weight: 700; }
.gb-header p  { margin: 2px 0 0; font-size: 12px; opacity: .8; }
.gb-body   { padding: 32px 28px; }

.gb-label  {
    font-size: 12px; font-weight: 700; color: #495057;
    text-transform: uppercase; letter-spacing: .06em; margin-bottom: 8px; display: block;
}
.gb-input  {
    width: 100%; height: 48px; border: 2px solid #dbe2ea; border-radius: 10px;
    padding: 0 16px; font-size: 15px; color: #1a1d2e;
    transition: border-color .15s, box-shadow .15s;
}
.gb-input:focus { border-color: #2f9e44; box-shadow: 0 0 0 3px rgba(47,158,68,.12); outline: none; }

.gb-info {
    background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 10px;
    padding: 16px 18px; margin-top: 20px;
    display: flex; align-items: center; gap: 12px;
}
.gb-info-icon { font-size: 22px; color: #2f9e44; flex-shrink: 0; }
.gb-info-text { font-size: 13px; color: #495057; line-height: 1.5; }
.gb-info-text strong { color: #1a1d2e; }

.gb-preview {
    background: #ebfbee; border: 1px solid #b2f2bb; border-radius: 10px;
    padding: 16px 18px; margin-top: 16px; display: none;
}
.gb-preview-title {
    font-size: 12px; font-weight: 700; color: #2f9e44;
    text-transform: uppercase; letter-spacing: .06em; margin-bottom: 10px;
}
.gb-preview-row {
    display: flex; justify-content: space-between; align-items: center;
    font-size: 13px; color: #2c3041; padding: 4px 0;
    border-bottom: 1px solid rgba(47,158,68,.15);
}
.gb-preview-row:last-child { border-bottom: none; }
.gb-preview-row span:last-child { font-weight: 700; }

.gb-warn {
    background: #fff3bf; border: 1px solid #ffe08a; border-radius: 10px;
    padding: 12px 16px; margin-top: 12px;
    font-size: 12px; color: #664d03; display: none;
}

.gb-footer {
    padding: 20px 28px; border-top: 1px solid #e9ecef; background: #f8f9fb;
    display: flex; justify-content: flex-end; gap: 10px;
}
.gb-btn {
    height: 44px; padding: 0 28px; border-radius: 11px; border: none;
    font-size: 14px; font-weight: 700; cursor: pointer; transition: .15s;
    display: inline-flex; align-items: center; gap: 8px;
}
.gb-btn-preview { background: #e9ecef; color: #495057; }
.gb-btn-preview:hover { background: #dee2e6; }
.gb-btn-gerar   { background: #2f9e44; color: #fff; }
.gb-btn-gerar:hover { background: #27873a; transform: translateY(-1px); box-shadow: 0 6px 16px rgba(47,158,68,.3); }
.gb-btn-gerar:disabled { opacity: .55; cursor: not-allowed; transform: none; box-shadow: none; }
</style>

<div class="vt-page gb-page">
    <div class="gb-card">

        <div class="gb-header">
            <i class="fa-solid fa-file-invoice-dollar"></i>
            <div>
                <h3>Gerar Boletos</h3>
                <p>Gera cobranças para todos os contratos ativos com o vencimento informado</p>
            </div>
        </div>

        <div class="gb-body">

            <label class="gb-label" for="inputVencimento">
                <i class="fa-solid fa-calendar-day" style="margin-right:5px;color:#2f9e44;"></i>
                Data de Vencimento
            </label>
            <input type="date" id="inputVencimento" class="gb-input"
                   min="<?= date('Y-m-d') ?>"
                   value="<?= date('Y-m-') . date('t') /* último dia do mês */ ?>">

            <div class="gb-info">
                <i class="fa-solid fa-circle-info gb-info-icon"></i>
                <div class="gb-info-text">
                    Serão geradas cobranças para <strong><?= number_format($totalAtivos, 0, ',', '.') ?> contrato(s) ativo(s)</strong>
                    que ainda não possuem cobrança para a data selecionada.
                    Contratos com cobrança existente para esse vencimento serão ignorados.
                </div>
            </div>

            <div class="gb-preview" id="gbPreview">
                <div class="gb-preview-title"><i class="fa-solid fa-magnifying-glass"></i> Prévia</div>
                <div class="gb-preview-row">
                    <span>Contratos ativos</span>
                    <span id="pvTotal">—</span>
                </div>
                <div class="gb-preview-row">
                    <span>Já possuem cobrança nesta data</span>
                    <span id="pvExistentes">—</span>
                </div>
                <div class="gb-preview-row" style="color:#2f9e44;">
                    <span><strong>Serão gerados</strong></span>
                    <span id="pvNovos" style="font-size:16px;">—</span>
                </div>
            </div>

            <div class="gb-warn" id="gbWarn">
                <i class="fa-solid fa-triangle-exclamation" style="color:#f59f00;margin-right:6px;"></i>
                Todos os contratos ativos já possuem cobrança para esta data.
            </div>

        </div>

        <div class="gb-footer">
            <button class="gb-btn gb-btn-preview" id="btnPreview" onclick="fazerPrevia()">
                <i class="fa-solid fa-magnifying-glass"></i> Ver Prévia
            </button>
            <button class="gb-btn gb-btn-gerar" id="btnGerar" onclick="gerarBoletos()" disabled>
                <i class="fa-solid fa-bolt"></i> Gerar Boletos
            </button>
        </div>

    </div>
</div>

<script>
(function () {
    const ACTION   = '<?= ACTION_URL ?>/gerar_boletos.php';
    const CSRF     = '<?= htmlspecialchars($csrf, ENT_QUOTES) ?>';

    var previewData = null;

    window.fazerPrevia = function () {
        var dt = document.getElementById('inputVencimento').value;
        if (!dt) {
            Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Informe a data de vencimento.' });
            return;
        }

        var btn = document.getElementById('btnPreview');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Consultando…';

        fetch(ACTION + '?acao=preview&vencimento=' + encodeURIComponent(dt))
            .then(r => r.json())
            .then(j => {
                if (!j.success) { Swal.fire({ icon: 'error', title: 'Erro', text: j.message }); return; }
                previewData = j;
                document.getElementById('pvTotal').textContent      = j.total_ativos;
                document.getElementById('pvExistentes').textContent = j.existentes;
                document.getElementById('pvNovos').textContent      = j.novos;
                document.getElementById('gbPreview').style.display  = 'block';

                var warn  = document.getElementById('gbWarn');
                var btnG  = document.getElementById('btnGerar');
                if (j.novos > 0) {
                    warn.style.display = 'none';
                    btnG.disabled      = false;
                } else {
                    warn.style.display = 'block';
                    btnG.disabled      = true;
                }
            })
            .catch(() => Swal.fire({ icon: 'error', title: 'Erro', text: 'Falha ao comunicar com o servidor.' }))
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-magnifying-glass"></i> Ver Prévia';
            });
    };

    window.gerarBoletos = function () {
        var dt = document.getElementById('inputVencimento').value;
        if (!dt || !previewData || previewData.novos < 1) return;

        Swal.fire({
            title: 'Confirmar geração?',
            html: 'Serão gerados <strong>' + previewData.novos + ' boleto(s)</strong> '
                + 'com vencimento em <strong>' + formatarData(dt) + '</strong>.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sim, gerar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#2f9e44'
        }).then(res => {
            if (!res.isConfirmed) return;

            var btn = document.getElementById('btnGerar');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Gerando…';

            var fd = new FormData();
            fd.set('acao', 'gerar');
            fd.set('vencimento', dt);
            fd.set('csrf', CSRF);

            fetch(ACTION, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(j => {
                    if (j.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Gerado!',
                            html: '<strong>' + j.gerados + '</strong> cobrança(s) criadas com sucesso.',
                            confirmButtonColor: '#2f9e44'
                        }).then(() => {
                            previewData = null;
                            document.getElementById('gbPreview').style.display = 'none';
                            document.getElementById('gbWarn').style.display    = 'none';
                            btn.disabled = true;
                            fazerPrevia();
                        });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Erro', text: j.message || 'Não foi possível gerar.' });
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-bolt"></i> Gerar Boletos';
                    }
                })
                .catch(() => {
                    Swal.fire({ icon: 'error', title: 'Erro', text: 'Falha ao comunicar com o servidor.' });
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-bolt"></i> Gerar Boletos';
                });
        });
    };

    function formatarData(iso) {
        var p = iso.split('-');
        return p[2] + '/' + p[1] + '/' + p[0];
    }

    // Atualiza prévia automaticamente ao trocar a data
    document.getElementById('inputVencimento').addEventListener('change', function () {
        document.getElementById('gbPreview').style.display = 'none';
        document.getElementById('gbWarn').style.display    = 'none';
        document.getElementById('btnGerar').disabled       = true;
        previewData = null;
    });
})();
</script>
