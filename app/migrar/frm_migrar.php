<?php
if (!defined('PATH_INC')) require_once __DIR__ . '/../../inc/config.php';

// Somente admin
if (($_SESSION['SessUsuTipo'] ?? '') !== 'admin') {
    // Comentar em produção se todos os usuários puderem usar
}

$FDB_OPTIONS = [
    'C:\CICLO\CACHOEIRINHA.FDB' => 'CACHOEIRINHA (principal – atualizado hoje)',
    'C:\CICLO\CARUARU.FDB'      => 'CARUARU',
    'C:\CICLO\CICLO123.FDB'     => 'CICLO123 (base de teste)',
    'C:\wamp64\www\valiantus\CICLO.FDB' => 'CICLO.FDB (cópia local)',
];

$ETAPAS = [
    ['id'=>'tb_cor',              'label'=>'Cores de Veículo',           'fb'=>'TB_VEICULO_COR',     'icon'=>'fa-palette'],
    ['id'=>'tb_grupo',            'label'=>'Grupos / Tabela de Preço',   'fb'=>'TB_GRUPO',           'icon'=>'fa-layer-group'],
    ['id'=>'tb_combo',            'label'=>'Combos e Serviços',          'fb'=>'TB_COMBO',           'icon'=>'fa-box-open'],
    ['id'=>'tb_pessoa',           'label'=>'Associados (Pessoas)',        'fb'=>'TB_PESSOA',          'icon'=>'fa-users'],
    ['id'=>'tb_veiculo',          'label'=>'Veículos',                   'fb'=>'TB_VEICULO',         'icon'=>'fa-car'],
    ['id'=>'tb_contrato',         'label'=>'Contratos / Vínculos',       'fb'=>'TB_VEICULO_CONTRATO','icon'=>'fa-file-contract'],
    ['id'=>'corrigir_vinculos',   'label'=>'Corrigir Vínculos Veículo↔Associado', 'fb'=>null, 'icon'=>'fa-link', 'noCount'=>true],
    ['id'=>'tb_vistoria',         'label'=>'Vistorias',                  'fb'=>'TB_VISTORIA',        'icon'=>'fa-clipboard-check'],
    ['id'=>'tb_cobranca',         'label'=>'Cobranças / Boletos (hist)', 'fb'=>'TB_CONTAS',          'icon'=>'fa-file-invoice-dollar'],
    ['id'=>'corrigir_vinculos_cobranca', 'label'=>'Vincular Cobranças → Associados', 'fb'=>null, 'icon'=>'fa-link', 'noCount'=>true],
    ['id'=>'tb_sinistro',         'label'=>'Sinistros',                  'fb'=>'TB_SINISTRO',        'icon'=>'fa-car-burst'],
    ['id'=>'tb_imagens',          'label'=>'Imagens de Veículo',         'fb'=>'TB_IMAGEM_VEICULO',  'icon'=>'fa-images'],
    ['id'=>'tb_sinistro_imagem',  'label'=>'Imagens de Sinistro',        'fb'=>'TB_IMAGEM_SINISTRO', 'icon'=>'fa-image'],
    ['id'=>'tb_historico_vistoria','label'=>'Histórico',                 'fb'=>'TB_HISTORICO',       'icon'=>'fa-clock-rotate-left'],
];
?>
<style>
.mig-page   { max-width:960px; margin:0 auto; }
.mig-card   { background:#fff; border:1px solid #e9ecef; border-radius:16px; box-shadow:0 4px 20px rgba(30,40,80,.07); overflow:hidden; margin-bottom:22px; }
.mig-head   { padding:18px 24px; background:linear-gradient(135deg,#1a1d2e,#2f3450); color:#fff; display:flex; align-items:center; gap:12px; }
.mig-head h3 { margin:0; font-size:16px; font-weight:700; }
.mig-head i  { width:36px; height:36px; border-radius:10px; background:rgba(255,255,255,.1); display:flex; align-items:center; justify-content:center; font-size:15px; }
.mig-body   { padding:22px; }
.mig-label  { font-size:12px; font-weight:700; color:#495057; display:block; margin-bottom:6px; }
.mig-select { width:100%; height:42px; border:1.5px solid #dbe2ea; border-radius:11px; padding:0 14px; font-size:13.5px; }
.mig-select:focus { border-color:#3b5bdb; box-shadow:0 0 0 3px rgba(59,91,219,.1); outline:none; }
.mig-input  { width:100%; height:42px; border:1.5px solid #dbe2ea; border-radius:11px; padding:0 14px; font-size:13px; }
.mig-input:focus { border-color:#3b5bdb; box-shadow:0 0 0 3px rgba(59,91,219,.1); outline:none; }

.mig-btn-primary { height:42px; padding:0 24px; border-radius:11px; background:#3b5bdb; color:#fff; border:none; font-size:14px; font-weight:700; cursor:pointer; transition:.15s; }
.mig-btn-primary:hover { background:#2f52d6; transform:translateY(-1px); }
.mig-btn-sm { height:34px; padding:0 16px; border-radius:9px; font-size:12px; font-weight:700; cursor:pointer; border:none; transition:.15s; }
.mig-btn-migrar { background:#3b5bdb; color:#fff; }
.mig-btn-migrar:hover { background:#2f52d6; }
.mig-btn-clear  { background:#ffe3e3; color:#c92a2a; }
.mig-btn-clear:hover  { background:#ffc9c9; }
.mig-btn-migrar:disabled { background:#adb5bd; cursor:not-allowed; }

/* Table */
.mig-table { width:100%; border-collapse:collapse; font-size:13px; }
.mig-table th { padding:10px 14px; text-align:left; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.06em; color:#868e96; border-bottom:2px solid #e9ecef; }
.mig-table td { padding:10px 14px; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
.mig-table tbody tr:last-child td { border-bottom:none; }
.mig-table tbody tr:hover td { background:#f8f9fb; }

.mig-icon-col { width:36px; height:36px; border-radius:10px; background:#e8edff; color:#3b5bdb; display:inline-flex; align-items:center; justify-content:center; font-size:13px; }
.mig-badge-ok   { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; background:#d3f9d8; color:#2f9e44; }
.mig-badge-zero { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; background:#fff3cd; color:#856404; }
.mig-badge-err  { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; background:#ffe3e3; color:#c92a2a; }

/* Progress */
.mig-progress-wrap { margin-top:6px; display:none; }
.mig-progress { height:6px; border-radius:3px; background:#e9ecef; overflow:hidden; }
.mig-progress-bar { height:100%; background:#3b5bdb; border-radius:3px; transition:width .3s; }
.mig-status-txt { font-size:11px; color:#868e96; margin-top:3px; }
.mig-log   { font-size:11px; background:#1a1d2e; color:#a5d6a7; border-radius:8px; padding:10px 14px; max-height:120px; overflow-y:auto; margin-top:6px; display:none; font-family:monospace; }

.mig-conn-status { padding:10px 14px; border-radius:10px; font-size:13px; font-weight:600; margin-bottom:14px; }
.mig-conn-ok  { background:#d3f9d8; color:#2f9e44; }
.mig-conn-err { background:#ffe3e3; color:#c92a2a; }

.mig-img-path { background:#f8f9fb; border:1px solid #e9ecef; border-radius:9px; padding:6px 12px; font-size:12px; font-family:monospace; color:#495057; }
.mig-icon-fix { background:#fff3cd; color:#856404; }
.mig-btn-reset { background:#fff3cd; color:#7d4e00; border:1.5px solid #f0c040; }
.mig-btn-reset:hover { background:#ffe8a1; }
</style>

<div class="vt-page mig-page">

    <!-- ── Card: Configuração ── -->
    <div class="mig-card">
        <div class="mig-head">
            <i class="fa-solid fa-database"></i>
            <h3>Migração de Dados — Sistema Ciclo (Firebird → MySQL)</h3>
        </div>
        <div class="mig-body">

            <div id="fbConnStatus" class="mig-conn-status" style="display:none;"></div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="mig-label">Arquivo FDB (banco Firebird)</label>
                    <select id="migFdb" class="mig-select">
                        <?php foreach ($FDB_OPTIONS as $path => $label): ?>
                            <option value="<?= htmlspecialchars($path) ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                        <option value="__custom__">Outro caminho…</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3" id="fdbCustomWrap" style="display:none;">
                    <label class="mig-label">Caminho completo do .FDB</label>
                    <input type="text" id="migFdbCustom" class="mig-input" placeholder="C:\caminho\banco.fdb">
                </div>
            </div>

            <div class="row">
                <div class="col-md-8 mb-3">
                    <label class="mig-label">
                        Pasta raiz das imagens
                        <span style="color:#868e96;font-weight:400;"> (substitui o Z:\ do Delphi — deixe vazio se Z:\ estiver acessível)</span>
                    </label>
                    <input type="text" id="migImgBase" class="mig-input" placeholder="Ex: Z:\  ou  C:\CICLO\imagens" value="Z:\">
                </div>
                <div class="col-md-4 mb-3 d-flex align-items-end">
                    <button type="button" class="mig-btn-primary" id="migBtnTestar" style="width:100%;">
                        <i class="fa-solid fa-plug mr-1"></i> Testar Conexão
                    </button>
                </div>
            </div>

            <div class="alert alert-info" style="font-size:13px;border-radius:10px;margin-bottom:8px;">
                <i class="fa-solid fa-circle-info mr-1"></i>
                <strong>Ordem de importação:</strong> cores → grupos → combos → pessoas → veículos → contratos → vistorias → cobranças → sinistros → imagens → histórico. Respeitar esta ordem evita erros de FK.
            </div>
            <div class="alert alert-warning" style="font-size:12px;border-radius:10px;padding:8px 14px;">
                <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                <strong>Imagens:</strong> O drive Z:\ possui apenas parte das fotos. Para migrar todas as imagens (~78k), extraia
                <code>C:\CICLO\IMAGENS 2026-02-07 22;24;00 (Full).zip</code> para uma pasta e informe o caminho acima.
            </div>

        </div>
    </div>

    <!-- ── Card: Etapas ── -->
    <div class="mig-card">
        <div class="mig-head">
            <i class="fa-solid fa-list-check"></i>
            <h3>Etapas da Migração</h3>
        </div>
        <div class="mig-body" style="padding:0;">
            <table class="mig-table" id="migTable">
                <thead>
                    <tr>
                        <th style="width:36px;"></th>
                        <th>Tabela Destino (MySQL)</th>
                        <th>Origem (Firebird)</th>
                        <th style="text-align:center;">Registros FB</th>
                        <th style="text-align:center;">Registros MySQL</th>
                        <th style="text-align:center;">Status</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ETAPAS as $e): ?>
                    <?php $noCount = !empty($e['noCount']); ?>
                    <tr id="row-<?= $e['id'] ?>">
                        <td><span class="mig-icon-col<?= $noCount ? ' mig-icon-fix' : '' ?>"><i class="fa-solid <?= $e['icon'] ?>"></i></span></td>
                        <td><strong><?= $e['id'] ?></strong><br><span style="font-size:11px;color:#868e96;"><?= $e['label'] ?></span></td>
                        <td style="font-family:monospace;font-size:12px;color:#3b5bdb;"><?= $e['fb'] ?? '<span style="color:#868e96;">—</span>' ?></td>
                        <td style="text-align:center;" id="cnt-fb-<?= $e['id'] ?>"><?= $noCount ? '<span style="color:#868e96;">—</span>' : '—' ?></td>
                        <td style="text-align:center;" id="cnt-my-<?= $e['id'] ?>"><?= $noCount ? '<span style="color:#868e96;">—</span>' : '—' ?></td>
                        <td style="text-align:center;" id="status-<?= $e['id'] ?>"><span class="mig-badge-zero">aguardando</span></td>
                        <td>
                            <div>
                                <button class="mig-btn-sm mig-btn-migrar" id="btn-<?= $e['id'] ?>"
                                        data-table="<?= $e['id'] ?>" data-nocount="<?= $noCount ? '1' : '0' ?>" disabled>
                                    <i class="fa-solid fa-play mr-1"></i><?= $noCount ? 'Executar' : 'Migrar' ?>
                                </button>
                                <?php if (!$noCount): ?>
                                <button class="mig-btn-sm mig-btn-clear ml-1" id="btnclr-<?= $e['id'] ?>"
                                        data-table="<?= $e['id'] ?>" disabled title="Limpar dados desta tabela">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="mig-progress-wrap" id="prog-wrap-<?= $e['id'] ?>">
                                <div class="mig-progress"><div class="mig-progress-bar" id="prog-<?= $e['id'] ?>" style="width:0%"></div></div>
                                <div class="mig-status-txt" id="prog-txt-<?= $e['id'] ?>"></div>
                            </div>
                            <div class="mig-log" id="log-<?= $e['id'] ?>"></div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="padding:14px 22px; border-top:1px solid #e9ecef; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
            <button type="button" id="migBtnTodos" class="mig-btn-primary" disabled>
                <i class="fa-solid fa-bolt mr-1"></i> Migrar Todos (em sequência)
            </button>
            <button type="button" id="migBtnStatus" class="mig-btn-sm" style="background:#f0f3ff;color:#3b5bdb;border:1.5px solid #c5d0ff;" disabled>
                <i class="fa-solid fa-rotate-right mr-1"></i> Atualizar Contagens
            </button>
            <span style="flex:1;"></span>
            <button type="button" id="migBtnReset" class="mig-btn-sm mig-btn-reset" title="Apaga todos os dados migrados e preserva usuários, configurações e FIPE">
                <i class="fa-solid fa-trash-can mr-1"></i> Limpar Tudo (Reset)
            </button>
        </div>
    </div>

</div><!-- /mig-page -->

<script>
document.addEventListener('DOMContentLoaded', function () {

    const ACT = '<?= ACTION_URL ?>/migrar.php';

    const ETAPAS     = <?= json_encode(array_column($ETAPAS, 'id')) ?>;
    const NO_COUNT   = <?= json_encode(array_keys(array_filter(array_column($ETAPAS, 'noCount', 'id')))) ?>;

    function getFdb() {
        const sel = document.getElementById('migFdb').value;
        if (sel === '__custom__') return document.getElementById('migFdbCustom').value.trim();
        return sel;
    }
    function getImgBase() { return document.getElementById('migImgBase').value.trim(); }

    // Custom FDB toggle
    document.getElementById('migFdb').addEventListener('change', function () {
        document.getElementById('fdbCustomWrap').style.display = this.value === '__custom__' ? '' : 'none';
    });
    // (re-teste de conexão ao trocar FDB está no listener abaixo, junto com o botão Testar)

    /* ── Testar conexão ── */
    function testarConexao(silent) {
        const btn = document.getElementById('migBtnTestar');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i>Conectando…';

        const fd = new FormData();
        fd.append('acao', 'testar_conexao');
        fd.append('fdb',  getFdb());

        fetch(ACT, { method:'POST', body:fd })
            .then(r => r.json())
            .then(function (j) {
                const el = document.getElementById('fbConnStatus');
                el.style.display = '';
                if (j.success) {
                    el.className = 'mig-conn-status mig-conn-ok';
                    el.innerHTML = '<i class="fa-solid fa-circle-check mr-1"></i>' + j.message;
                    habilitarBotoes();
                    carregarStatus();
                } else {
                    el.className = 'mig-conn-status mig-conn-err';
                    el.innerHTML = '<i class="fa-solid fa-circle-xmark mr-1"></i>' + j.message;
                    if (!silent) desabilitarBotoes();
                }
            })
            .catch(function () {
                const el = document.getElementById('fbConnStatus');
                el.style.display = '';
                el.className = 'mig-conn-status mig-conn-err';
                el.innerHTML = 'Falha ao comunicar com o servidor.';
            })
            .finally(function () {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-plug mr-1"></i> Testar Conexão';
            });
    }

    function habilitarBotoes() {
        ETAPAS.forEach(function(id) {
            document.getElementById('btn-' + id).disabled = false;
            var clrBtn = document.getElementById('btnclr-' + id);
            if (clrBtn) clrBtn.disabled = false;
        });
        document.getElementById('migBtnTodos').disabled  = false;
        document.getElementById('migBtnStatus').disabled = false;
        document.getElementById('migBtnReset').disabled  = false;
    }

    function desabilitarBotoes() {
        ETAPAS.forEach(function(id) {
            document.getElementById('btn-' + id).disabled = true;
            var clrBtn = document.getElementById('btnclr-' + id);
            if (clrBtn) clrBtn.disabled = true;
        });
        document.getElementById('migBtnTodos').disabled  = true;
        document.getElementById('migBtnStatus').disabled = true;
    }

    document.getElementById('migBtnTestar').addEventListener('click', function () { testarConexao(false); });
    document.getElementById('migFdb').addEventListener('change', function () { testarConexao(false); });

    // Auto-testa ao carregar
    testarConexao(true);

    /* ── Contagens ── */
    function carregarStatus() {
        const fd = new FormData();
        fd.append('acao', 'status');
        fd.append('fdb',  getFdb());

        fetch(ACT, { method:'POST', body:fd })
            .then(r => r.json())
            .then(function (data) {
                ETAPAS.forEach(function (id) {
                    const s = data[id] || {};
                    document.getElementById('cnt-fb-' + id).textContent = s.fb  != null ? s.fb.toLocaleString('pt-BR')  : '—';
                    document.getElementById('cnt-my-' + id).textContent = s.my  != null ? s.my.toLocaleString('pt-BR')  : '—';
                    const badge = document.getElementById('status-' + id);
                    if (s.my > 0 && s.my >= s.fb) {
                        badge.innerHTML = '<span class="mig-badge-ok">completo</span>';
                    } else if (s.my > 0) {
                        badge.innerHTML = '<span class="mig-badge-zero">parcial (' + s.my + '/' + s.fb + ')</span>';
                    } else {
                        badge.innerHTML = '<span class="mig-badge-zero">pendente</span>';
                    }
                });
            });
    }

    document.getElementById('migBtnStatus').addEventListener('click', carregarStatus);

    /* ── Migrar tabela única ── */
    function log(id, msg) {
        const el = document.getElementById('log-' + id);
        el.style.display = 'block';
        el.textContent += msg + '\n';
        el.scrollTop = el.scrollHeight;
    }

    function setProgress(id, pct, txt) {
        document.getElementById('prog-wrap-' + id).style.display = 'block';
        document.getElementById('prog-' + id).style.width = pct + '%';
        document.getElementById('prog-txt-' + id).textContent = txt;
    }

    async function migrarTabela(id) {
        const btn = document.getElementById('btn-' + id);
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i>';
        document.getElementById('log-' + id).textContent = '';

        let offset = 0;
        const limit = 500;
        let done    = false;
        let total   = 0;
        let imported = 0;
        let erros   = 0;

        while (!done) {
            const fd = new FormData();
            fd.append('acao',     'migrar_' + id);
            fd.append('fdb',      getFdb());
            fd.append('img_base', getImgBase());
            fd.append('offset',   offset);
            fd.append('limit',    limit);

            let j;
            try {
                const r = await fetch(ACT, { method:'POST', body:fd });
                j = await r.json();
            } catch(e) {
                log(id, '✗ Erro de comunicação: ' + e.message);
                break;
            }

            if (!j.success) {
                log(id, '✗ ' + (j.message || 'Erro desconhecido'));
                document.getElementById('status-' + id).innerHTML = '<span class="mig-badge-err">erro</span>';
                break;
            }

            imported += (j.imported || 0);
            erros    += (j.errors   || 0);
            total     = j.total  || total;
            done      = j.done   !== false;
            offset    = j.next_offset || (offset + limit);

            if (j.log) j.log.forEach(function(l) { log(id, l); });

            const pct = total > 0 ? Math.round((imported / total) * 100) : 100;
            setProgress(id, pct, imported.toLocaleString('pt-BR') + ' / ' + (total||'?').toLocaleString('pt-BR') + ' importados' + (erros ? ' · ' + erros + ' erros' : ''));

            if (done) {
                const statusEl = document.getElementById('status-' + id);
                if (erros === 0) {
                    statusEl.innerHTML = '<span class="mig-badge-ok">✓ ' + imported.toLocaleString('pt-BR') + '</span>';
                } else {
                    statusEl.innerHTML = '<span class="mig-badge-zero">' + imported.toLocaleString('pt-BR') + ' ok · ' + erros + ' erros</span>';
                }
                log(id, '✓ Concluído: ' + imported.toLocaleString('pt-BR') + ' registros.');
                carregarStatus();
            }
        }

        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-rotate-right mr-1"></i>Reimportar';
    }

    // Bind botões individuais
    ETAPAS.forEach(function (id) {
        document.getElementById('btn-' + id).addEventListener('click', function () {
            migrarTabela(id);
        });
        var clrBtn = document.getElementById('btnclr-' + id);
        if (clrBtn) {
            clrBtn.addEventListener('click', function () {
                if (!window.confirm('Limpar TODOS os registros de ' + id + '?')) return;
                var btn = document.getElementById('btnclr-' + id);
                btn.disabled = true;
                var fd = new FormData();
                fd.append('acao', 'limpar');
                fd.append('tabela', id);
                fetch(ACT, { method:'POST', body:fd })
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        btn.disabled = false;
                        if (j.success) {
                            document.getElementById('status-' + id).innerHTML = '<span class="mig-badge-zero">limpo</span>';
                            carregarStatus();
                        } else {
                            window.alert('Erro: ' + j.message);
                        }
                    })
                    .catch(function (e) {
                        btn.disabled = false;
                        window.alert('Erro de comunicação: ' + e.message);
                    });
            });
        }
    });

    // Migrar todos
    var _migrandoTodos = false;
    document.getElementById('migBtnTodos').addEventListener('click', function () {
        if (_migrandoTodos) return;

        Swal.fire({
            title: 'Migrar todas as etapas?',
            text: 'As etapas serão executadas em sequência. Pode levar vários minutos.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3b5bdb',
            confirmButtonText: 'Sim, migrar tudo',
            cancelButtonText: 'Cancelar'
        }).then(function (res) {
            if (!res.isConfirmed) return;

            _migrandoTodos = true;
            var btnTodos = document.getElementById('migBtnTodos');
            btnTodos.disabled = true;
            btnTodos.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Migrando…';

            var etapaIdx = 0;

            function proxima() {
                if (etapaIdx >= ETAPAS.length) {
                    _migrandoTodos = false;
                    btnTodos.disabled = false;
                    btnTodos.innerHTML = '<i class="fa-solid fa-bolt mr-1"></i> Migrar Todos (em sequência)';
                    Swal.fire({ title: 'Concluído!', text: 'Todas as etapas foram processadas.', icon: 'success', timer: 3000, showConfirmButton: false });
                    return;
                }
                var id = ETAPAS[etapaIdx++];
                migrarTabela(id).then(proxima).catch(function (e) {
                    console.error('Erro em ' + id + ':', e);
                    proxima();
                });
            }

            proxima();
        });
    });

    // Reset completo
    document.getElementById('migBtnReset').addEventListener('click', function () {
        if (!window.confirm('ATENÇÃO: Apagar TODOS os dados migrados?\n\nPessoas, veículos, contratos, imagens etc. serão removidos.\nUsuários e configurações serão preservados.')) return;

        var btn = document.getElementById('migBtnReset');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Limpando…';

        var fd = new FormData();
        fd.append('acao', 'resetar_tudo');

        fetch(ACT, { method:'POST', body:fd })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-trash-can mr-1"></i> Limpar Tudo (Reset)';
                if (j.success) {
                    carregarStatus();
                    ETAPAS.forEach(function (id) {
                        document.getElementById('status-' + id).innerHTML = '<span class="mig-badge-zero">aguardando</span>';
                        var pw = document.getElementById('prog-wrap-' + id);
                        if (pw) pw.style.display = 'none';
                        var lg = document.getElementById('log-' + id);
                        if (lg) { lg.textContent = ''; lg.style.display = 'none'; }
                    });
                    window.alert('✓ ' + j.message);
                } else {
                    window.alert('Erro: ' + j.message);
                }
            })
            .catch(function (e) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-trash-can mr-1"></i> Limpar Tudo (Reset)';
                window.alert('Erro de comunicação: ' + e.message);
            });
    });
});
</script>
