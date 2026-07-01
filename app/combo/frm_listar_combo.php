<?php
/* frm_listar_combo.php */
if (!defined('PATH_INC')) require_once __DIR__ . '/../../inc/config.php';
require_once PATH_INC . '/db.php';
require_once PATH_INC . '/csrf.php';
$csrf_combo = csrf_token();

$combos = $pdo->query("SELECT * FROM tb_combo")->fetchAll(PDO::FETCH_OBJ);
?>

<link rel="stylesheet" href="../valiantus-tables.css">

<style>
    .dataTables_wrapper .dataTables_filter { display: none !important; }
    .dataTables_wrapper .dataTables_info {
        font-size: 12.5px; color: #64748b;
        padding: 0 !important; float: none !important; clear: none !important;
    }
    .dataTables_wrapper .dataTables_length { float: none !important; padding: 0 !important; }
    .dataTables_wrapper .dataTables_length label {
        display: flex !important; align-items: center;
        gap: 7px; font-size: 12.5px; color: #64748b; margin: 0; white-space: nowrap;
    }
    .dataTables_wrapper .dataTables_length select {
        border: 1.5px solid #e2e8f0 !important; border-radius: 8px !important;
        font-size: 12.5px !important; padding: 3px 8px !important; color: #334155;
    }
    .dt-footer {
        display: flex; align-items: center; gap: 12px;
        padding: 11px 20px; border-top: 1px solid #e2e8f0;
        background: #fff; border-radius: 0 0 14px 14px;
    }
    .dt-footer-info { flex: 1; }
    .dt-footer-paging { flex: 0 0 auto; }
    .dt-footer-paging .dataTables_paginate { display: none !important; }
    .dt-footer-per { flex: 1; display: flex; justify-content: flex-end; }
    .asc-pag-controls { display: flex; align-items: center; gap: 4px; }
    .asc-pag-controls button {
        background: #fff; border: 1px solid #e2e8f0; color: #334155;
        min-width: 32px; height: 32px; padding: 0 8px; border-radius: 7px;
        font-size: 12px; font-family: inherit; cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center;
        transition: background .15s, border-color .15s, color .15s;
    }
    .asc-pag-controls button:hover:not(:disabled) { background: #edf2ff; border-color: #3b5bdb; color: #3b5bdb; }
    .asc-pag-controls button:disabled { opacity: .38; cursor: not-allowed; }
    .asc-pag-controls button.active { background: #3b5bdb; border-color: #3b5bdb; color: #fff; }
    .asc-pag-controls .pg-ellipsis { font-size: 12px; color: #94a3b8; padding: 0 4px; user-select: none; }
</style>

<div class="vt-page">
    <div class="vt-card">

        <div class="vt-card-header">
            <div class="vt-card-title">
                <div class="vt-icon-wrap"><i class="fa-solid fa-layer-group"></i></div>
                <h3>Combos</h3>
            </div>
            <div class="vt-card-tools">
                <div class="vt-search-wrap">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="generalSearch" class="vt-search" placeholder="Buscar combo...">
                </div>
                <a href="#" class="vt-btn-new" data-toggle="modal" data-target="#CadastrarCombo">
                    <i class="fa-solid fa-plus"></i> Novo Combo
                </a>
            </div>
        </div>

        <div class="vt-card-body">
            <div class="vt-table-wrap">
                <table class="vt-table" id="html_table" width="100%">
                    <thead>
                        <tr>
                            <th style="width:70px;">Código</th>
                            <th>Descrição</th>
                            <th style="width:140px;">Valor</th>
                            <th class="text-end" style="width:100px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($combos)): ?>
                            <tr>
                                <td colspan="4" class="text-center" style="padding:40px;color:#868e96;">
                                    <i class="fa-solid fa-layer-group" style="font-size:32px;margin-bottom:10px;display:block;opacity:.3;"></i>
                                    Nenhum combo encontrado.
                                </td>
                            </tr>
                            <?php else: foreach ($combos as $c): ?>
                                <tr data-id="<?= (int)$c->COM_CODIGO_PK ?>">
                                    <td>#<?= (int)$c->COM_CODIGO_PK ?></td>
                                    <td style="font-weight:600;"><?= htmlspecialchars($c->COM_DESCRICAO) ?></td>
                                    <td>
                                        <span class="vt-badge vt-badge--green">
                                            R$ <?= number_format((float)$c->COM_VALOR, 2, ',', '.') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="vt-actions">
                                            <a href="#"
                                                class="vt-action-btn vt-action-btn--edit btn-editar-combo"
                                                title="Editar"
                                                data-id="<?= (int)$c->COM_CODIGO_PK ?>"
                                                data-descricao="<?= htmlspecialchars($c->COM_DESCRICAO, ENT_QUOTES, 'UTF-8') ?>"
                                                data-valor="<?= htmlspecialchars(number_format((float)$c->COM_VALOR, 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?>">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>
                                            <a href="#"
                                                class="vt-action-btn vt-action-btn--del btn-excluir-combo"
                                                title="Excluir"
                                                data-id="<?= (int)$c->COM_CODIGO_PK ?>">
                                                <i class="fa-solid fa-trash"></i>
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
</div>

<!-- Modal: Cadastrar Combo -->
<div class="modal fade vt-modal" id="CadastrarCombo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form id="formCadastrarCombo" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-layer-group"></i> Novo Combo</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Descrição</label>
                    <input type="text" class="form-control" name="descricao" id="cmb_descricao" required>
                </div>
                <div class="form-group">
                    <label>Valor (R$)</label>
                    <input type="text" class="form-control" name="valor" id="cmb_valor" placeholder="0,00" required>
                </div>
            </div>
            <input type="hidden" name="acao" value="cadastrar">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_combo, ENT_QUOTES, 'UTF-8') ?>">
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Editar Combo -->
<div class="modal fade vt-modal" id="EditarCombo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form id="formEditarCombo" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-pen-to-square"></i> Editar Combo</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="cmb_id">
                <div class="form-group">
                    <label>Descrição</label>
                    <input type="text" class="form-control" name="descricao" id="cmb_descricao_edit" required>
                </div>
                <div class="form-group">
                    <label>Valor (R$)</label>
                    <input type="text" class="form-control" name="valor" id="cmb_valor_edit" placeholder="0,00" required>
                </div>
            </div>
            <input type="hidden" name="acao" value="editar">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_combo, ENT_QUOTES, 'UTF-8') ?>">
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar alterações</button>
            </div>
        </form>
    </div>
</div>

<script>
    (function() {
        const ACTION = '<?= ACTION_URL ?>/combo.php';

        /* DataTable */
        function initDT() {
            if (!window.jQuery || !$.fn.DataTable) return setTimeout(initDT, 60);
            const dt = $('#html_table').DataTable({
                autoWidth: false,
                scrollX: false,
                pageLength: 10,
                dom: 't<"dt-footer"<"dt-footer-info"i><"dt-footer-paging"p><"dt-footer-per"l>>',
                order: [[0, 'desc']],
                columnDefs: [{targets: -1, orderable: false, searchable: false}],
                drawCallback: function() {
                    const api  = this.api();
                    const info = api.page.info();
                    const cur  = info.page + 1;
                    const tot  = info.pages;
                    const wrap = document.querySelector('.dt-footer-paging');
                    if (!wrap) return;
                    const btn = (icon, page, disabled, active) =>
                        `<button onclick="window._dtCombo&&window._dtCombo.page(${page - 1}).draw(false)"
                            ${disabled ? 'disabled' : ''} class="${active ? 'active' : ''}">${icon}</button>`;
                    const parts = [];
                    parts.push(btn('<i class="fa-solid fa-angles-left"></i>',  1,       cur === 1,   false));
                    parts.push(btn('<i class="fa-solid fa-angle-left"></i>',   cur - 1, cur === 1,   false));
                    let from = Math.max(1, cur - 2), to = Math.min(tot, from + 4);
                    from = Math.max(1, to - 4);
                    if (from > 1) parts.push('<span class="pg-ellipsis">…</span>');
                    for (let i = from; i <= to; i++) parts.push(btn(i, i, false, i === cur));
                    if (to < tot) parts.push('<span class="pg-ellipsis">…</span>');
                    parts.push(btn('<i class="fa-solid fa-angle-right"></i>',  cur + 1, cur === tot, false));
                    parts.push(btn('<i class="fa-solid fa-angles-right"></i>', tot,     cur === tot, false));
                    const html = `<div class="asc-pag-controls">${parts.join('')}</div>`;
                    const ex = wrap.querySelector('.asc-pag-controls');
                    if (ex) ex.outerHTML = html; else wrap.insertAdjacentHTML('afterbegin', html);
                },
                language: {
                    sEmptyTable: "Nenhum registro encontrado",
                    sInfo: "Mostrando <strong>_START_–_END_</strong> de <strong>_TOTAL_</strong> combos",
                    sInfoEmpty: "Nenhum combo encontrado",
                    sInfoFiltered: "(filtrado de _MAX_)",
                    sLengthMenu: "Por página: _MENU_",
                    sZeroRecords: "Nenhum registro encontrado"
                }
            });
            window._dtCombo = dt;
            const s = document.getElementById('generalSearch');
            if (s) {
                let t;
                s.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => dt.search(s.value).draw(), 250); });
            }
        }
        document.addEventListener('DOMContentLoaded', initDT);

        /* BRL simples */
        function formatBRL(el) {
            let v = (el.value || '').replace(/\D/g, '');
            if (!v) {
                el.value = '';
                return;
            }
            if (v.length < 3) v = v.padStart(3, '0');
            el.value = v.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + v.slice(-2);
        }
        document.addEventListener('input', e => {
            if (['cmb_valor', 'cmb_valor_edit'].includes(e.target?.id)) e.target.value = e.target.value.replace(/[^\d.,]/g, '');
        });
        document.addEventListener('blur', e => {
            if (['cmb_valor', 'cmb_valor_edit'].includes(e.target?.id)) formatBRL(e.target);
        }, true);

        document.addEventListener('DOMContentLoaded', function() {
            /* Editar — abrir modal */
            $(document).on('click', '.btn-editar-combo', function(e) {
                e.preventDefault();
                const $a = $(this).closest('[data-id]');
                $('#cmb_id').val($a.attr('data-id') || '');
                $('#cmb_descricao_edit').val($a.attr('data-descricao') || '');
                $('#cmb_valor_edit').val($a.attr('data-valor') || '');
                $('#EditarCombo').modal('show');
            });

            /* Submit genérico */
            function postForm(formId, label) {
                $(formId).on('submit', function(ev) {
                    ev.preventDefault();
                    const fd = new FormData(this);
                    Swal.fire({ icon: 'question', title: label, showCancelButton: true, confirmButtonText: 'Salvar', cancelButtonText: 'Cancelar' })
                        .then(res => {
                            if (!res.isConfirmed) return;
                            fetch(ACTION, { method: 'POST', body: fd })
                                .then(r => r.json())
                                .then(json => {
                                    if (json.success) Swal.fire({ icon: 'success', title: 'Salvo!', text: json.message || '' }).then(() => location.reload());
                                    else Swal.fire({ icon: 'error', title: 'Erro', text: json.message || 'Não foi possível salvar.' });
                                })
                                .catch(() => Swal.fire({ icon: 'error', title: 'Erro', text: 'Falha ao comunicar com o servidor.' }));
                        });
                });
            }
            postForm('#formCadastrarCombo', 'Confirmar cadastro?');
            postForm('#formEditarCombo', 'Confirmar alterações?');

            /* Excluir */
            $(document).on('click', '.btn-excluir-combo', function(ev) {
                ev.preventDefault();
                const id = $(this).data('id');
                if (!id) return;
                Swal.fire({ icon: 'warning', title: 'Excluir combo?', text: 'Esta ação não poderá ser desfeita.', showCancelButton: true, confirmButtonText: 'Sim, excluir', confirmButtonColor: '#c92a2a' })
                    .then(res => {
                        if (!res.isConfirmed) return;
                        const fd = new FormData();
                        fd.set('acao', 'excluir');
                        fd.set('id', id);
                        fd.set('csrf', '<?= htmlspecialchars($csrf_combo, ENT_QUOTES, "UTF-8") ?>');
                        fetch(ACTION, { method: 'POST', body: fd })
                            .then(r => r.json())
                            .then(json => {
                                if (json.success) Swal.fire({ icon: 'success', title: 'Excluído!', text: json.message || '' }).then(() => location.reload());
                                else Swal.fire({ icon: 'error', title: 'Erro', text: json.message || 'Não foi possível excluir.' });
                            })
                            .catch(() => Swal.fire({ icon: 'error', title: 'Erro', text: 'Falha ao comunicar com o servidor.' }));
                    });
            });
        });
    })();
</script>