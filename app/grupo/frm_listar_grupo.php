<?php
if (!defined('PATH_INC')) require_once __DIR__ . '/../../inc/config.php';
require_once PATH_INC . '/db.php';
require_once PATH_INC . '/csrf.php';
$csrf_grupo = csrf_token();

$grupos = $pdo->query("SELECT * FROM tb_grupo")->fetchAll(PDO::FETCH_OBJ);
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
                <div class="vt-icon-wrap"><i class="fa-solid fa-tags"></i></div>
                <h3>Tabela de Preço</h3>
            </div>
            <div class="vt-card-tools">
                <div class="vt-search-wrap">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="generalSearch" class="vt-search" placeholder="Buscar grupo...">
                </div>
                <a href="#" class="vt-btn-new" data-toggle="modal" data-target="#CadastrarGrupo">
                    <i class="fa-solid fa-plus"></i> Novo Grupo
                </a>
            </div>
        </div>

        <div class="vt-card-body">
            <div class="vt-table-wrap">
                <table class="vt-table" id="html_table" width="100%">
                    <thead>
                        <tr>
                            <th>Descrição</th>
                            <th>Mensalidade</th>
                            <th>Mínimo</th>
                            <th>Máximo</th>
                            <th>Adesão</th>
                            <th>Tipo Veículo</th>
                            <th>Situação</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($grupos)): ?>
                            <tr>
                                <td colspan="8" class="text-center" style="padding:40px;color:#868e96;">
                                    <i class="fa-solid fa-tags" style="font-size:32px;margin-bottom:10px;display:block;opacity:.3;"></i>
                                    Nenhum grupo encontrado.
                                </td>
                            </tr>
                            <?php else: foreach ($grupos as $g): ?>
                                <?php
                                $statusClass = strtoupper($g->GRU_STATUS ?? '') === 'ATIVO' ? 'vt-badge--green' : 'vt-badge--gray';
                                $brl = fn($v) => 'R$ ' . number_format((float)($v ?? 0), 2, ',', '.');
                                ?>
                                <tr data-id="<?= (int)$g->GRU_CODIGO_PK ?>">
                                    <td style="font-weight:600;"><?= htmlspecialchars($g->GRU_DESCRICAO) ?></td>
                                    <td><?= $brl($g->GRU_VALOR_MENSALIDADE) ?></td>
                                    <td><?= $brl($g->GRU_VALOR_MINIMO) ?></td>
                                    <td><?= $brl($g->GRU_VALOR_MAXIMO) ?></td>
                                    <td><?= $brl($g->GRU_VALOR_ADESAO) ?></td>
                                    <td>
                                        <span class="vt-badge vt-badge--blue"><?= htmlspecialchars($g->GRU_TIPO_VEICULO ?? '') ?></span>
                                    </td>
                                    <td>
                                        <span class="vt-badge <?= $statusClass ?>"><?= htmlspecialchars($g->GRU_STATUS) ?></span>
                                    </td>
                                    <td>
                                        <div class="vt-actions">
                                            <a href="#"
                                                class="vt-action-btn vt-action-btn--edit btn-editar-grupo"
                                                title="Editar grupo"
                                                data-id="<?= (int)$g->GRU_CODIGO_PK ?>">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>
                                            <a href="#"
                                                class="vt-action-btn vt-action-btn--del btn-excluir-grupo"
                                                title="Excluir grupo"
                                                data-id="<?= (int)$g->GRU_CODIGO_PK ?>">
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

<!-- Modal: Cadastrar Grupo -->
<div class="modal fade vt-modal" id="CadastrarGrupo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form id="formCadastrarGrupo" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-plus-circle"></i> Novo Grupo</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group col-md-8">
                        <label>Descrição</label>
                        <input type="text" class="form-control" name="descricao" id="gru_descricao" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Tipo de Veículo</label>
                        <select name="tipo" id="gru_tipo" class="form-control" required>
                            <option value="">Selecione...</option>
                            <option>CARRO</option>
                            <option>MOTO</option>
                            <option>VAN</option>
                            <option>ÔNIBUS</option>
                            <option>CAMINHÃO</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-3"><label>Mensalidade (R$)</label><input type="text" class="form-control brl" name="mensalidade" id="gru_mensalidade" placeholder="0,00"></div>
                    <div class="form-group col-md-3"><label>Valor Mínimo (R$)</label><input type="text" class="form-control brl" name="minimo" id="gru_minimo" placeholder="0,00"></div>
                    <div class="form-group col-md-3"><label>Valor Máximo (R$)</label><input type="text" class="form-control brl" name="maximo" id="gru_maximo" placeholder="0,00"></div>
                    <div class="form-group col-md-3"><label>Terceiro (R$)</label><input type="text" class="form-control brl" name="terceiro" id="gru_terceiro" placeholder="0,00"></div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-3"><label>Reserva (R$)</label><input type="text" class="form-control brl" name="reserva" id="gru_reserva" placeholder="0,00"></div>
                    <div class="form-group col-md-3"><label>Adesão (R$)</label><input type="text" class="form-control brl" name="adesao" id="gru_adesao" placeholder="0,00"></div>
                    <div class="form-group col-md-3"><label>Renovação (R$)</label><input type="text" class="form-control brl" name="renovacao" id="gru_renovacao" placeholder="0,00"></div>
                    <div class="form-group col-md-3"><label>Limite Cadastro (R$)</label><input type="text" class="form-control brl" name="limite" id="gru_limite" placeholder="0,00"></div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-3"><label>Taxa Regularização (R$)</label><input type="text" class="form-control brl" name="regularizacao" id="gru_regularizacao" placeholder="0,00"></div>
                    <div class="form-group col-md-3"><label>Sequência</label><input type="number" class="form-control" name="sequencia" id="gru_sequencia" min="0" step="1"></div>
                    <div class="form-group col-md-3">
                        <label>Status</label>
                        <select name="status" id="gru_status" class="form-control">
                            <option value="ATIVO" selected>ATIVO</option>
                            <option value="INATIVO">INATIVO</option>
                        </select>
                    </div>
                </div>
            </div>
            <input type="hidden" name="acao" value="cadastrar">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_grupo, ENT_QUOTES, 'UTF-8') ?>">
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Editar Grupo -->
<div class="modal fade vt-modal" id="EditarGrupo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form id="formEditarGrupo" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-pen-to-square"></i> Editar Grupo</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="gru_id">
                <div class="form-row">
                    <div class="form-group col-md-8">
                        <label>Descrição</label>
                        <input type="text" class="form-control" name="descricao" id="gru_descricao_edit" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Tipo de Veículo</label>
                        <select name="tipo" id="gru_tipo_edit" class="form-control" required>
                            <option value="">Selecione...</option>
                            <option>CARRO</option>
                            <option>MOTO</option>
                            <option>VAN</option>
                            <option>ÔNIBUS</option>
                            <option>CAMINHÃO</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-3"><label>Mensalidade (R$)</label><input type="text" class="form-control brl" name="mensalidade" id="gru_mensalidade_edit"></div>
                    <div class="form-group col-md-3"><label>Valor Mínimo (R$)</label><input type="text" class="form-control brl" name="minimo" id="gru_minimo_edit"></div>
                    <div class="form-group col-md-3"><label>Valor Máximo (R$)</label><input type="text" class="form-control brl" name="maximo" id="gru_maximo_edit"></div>
                    <div class="form-group col-md-3"><label>Terceiro (R$)</label><input type="text" class="form-control brl" name="terceiro" id="gru_terceiro_edit"></div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-3"><label>Reserva (R$)</label><input type="text" class="form-control brl" name="reserva" id="gru_reserva_edit"></div>
                    <div class="form-group col-md-3"><label>Adesão (R$)</label><input type="text" class="form-control brl" name="adesao" id="gru_adesao_edit"></div>
                    <div class="form-group col-md-3"><label>Renovação (R$)</label><input type="text" class="form-control brl" name="renovacao" id="gru_renovacao_edit"></div>
                    <div class="form-group col-md-3"><label>Limite Cadastro (R$)</label><input type="text" class="form-control brl" name="limite" id="gru_limite_edit"></div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-3"><label>Taxa Regularização (R$)</label><input type="text" class="form-control brl" name="regularizacao" id="gru_regularizacao_edit"></div>
                    <div class="form-group col-md-3"><label>Sequência</label><input type="number" class="form-control" name="sequencia" id="gru_sequencia_edit" min="0" step="1"></div>
                    <div class="form-group col-md-3">
                        <label>Status</label>
                        <select name="status" id="gru_status_edit" class="form-control">
                            <option value="ATIVO">ATIVO</option>
                            <option value="INATIVO">INATIVO</option>
                        </select>
                    </div>
                </div>
            </div>
            <input type="hidden" name="acao" value="editar">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_grupo, ENT_QUOTES, 'UTF-8') ?>">
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar alterações</button>
            </div>
        </form>
    </div>
</div>

<script>
    (function() {
        const ACTION = '<?= ACTION_URL ?>/grupo.php';

        /* ── DataTable ── */
        function initDT() {
            if (!window.jQuery || !$.fn.DataTable) return setTimeout(initDT, 60);
            const dt = $('#html_table').DataTable({
                autoWidth: false,
                scrollX: false,
                pageLength: 10,
                dom: 't<"dt-footer"<"dt-footer-info"i><"dt-footer-paging"p><"dt-footer-per"l>>',
                order: [[0, 'asc']],
                columnDefs: [{targets: -1, orderable: false, searchable: false}],
                drawCallback: function() {
                    const api  = this.api();
                    const info = api.page.info();
                    const cur  = info.page + 1;
                    const tot  = info.pages;
                    const wrap = document.querySelector('.dt-footer-paging');
                    if (!wrap) return;
                    const btn = (icon, page, disabled, active) =>
                        `<button onclick="window._dtGrupo&&window._dtGrupo.page(${page - 1}).draw(false)"
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
                    sInfo: "Mostrando <strong>_START_–_END_</strong> de <strong>_TOTAL_</strong> grupos",
                    sInfoEmpty: "Nenhum grupo encontrado",
                    sInfoFiltered: "(filtrado de _MAX_)",
                    sLengthMenu: "Por página: _MENU_",
                    sZeroRecords: "Nenhum registro encontrado"
                }
            });
            window._dtGrupo = dt;
            const s = document.getElementById('generalSearch');
            if (s) {
                let t;
                s.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => dt.search(s.value).draw(), 250); });
            }
        }
        document.addEventListener('DOMContentLoaded', initDT);

        /* ── Máscara BRL ── */
        function toBRL(el) {
            let v = (el.value || '').replace(/\D/g, '');
            if (!v) {
                el.value = '';
                return;
            }
            if (v.length < 3) v = v.padStart(3, '0');
            el.value = v.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + v.slice(-2);
        }
        document.addEventListener('input', e => {
            if (e.target?.classList.contains('brl')) e.target.value = e.target.value.replace(/[^\d,.]/g, '');
        });
        document.addEventListener('blur', e => {
            if (e.target?.classList.contains('brl')) toBRL(e.target);
        }, true);

        /* ── Preencher editar ── */
        function fillEditar(d) {
            const brl = x => x === null || x === '' || isNaN(x) ? '' : Number(x).toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            $('#gru_id').val(d.GRU_CODIGO_PK || '');
            $('#gru_descricao_edit').val(d.GRU_DESCRICAO || '');
            $('#gru_tipo_edit').val(d.GRU_TIPO_VEICULO || '');
            $('#gru_mensalidade_edit').val(brl(d.GRU_VALOR_MENSALIDADE));
            $('#gru_minimo_edit').val(brl(d.GRU_VALOR_MINIMO));
            $('#gru_maximo_edit').val(brl(d.GRU_VALOR_MAXIMO));
            $('#gru_terceiro_edit').val(brl(d.GRU_VALOR_TERCEIRO));
            $('#gru_reserva_edit').val(brl(d.GRU_VALOR_RESERVA));
            $('#gru_adesao_edit').val(brl(d.GRU_VALOR_ADESAO));
            $('#gru_renovacao_edit').val(brl(d.GRU_VALOR_RENOVACAO));
            $('#gru_limite_edit').val(brl(d.GRU_LIMITE_CADASTRO));
            $('#gru_regularizacao_edit').val(brl(d.GRU_TAXA_REGULARIZACAO));
            $('#gru_sequencia_edit').val(d.GRU_SEQUENCIA || '');
            $('#gru_status_edit').val(d.GRU_STATUS || 'ATIVO');
        }

        document.addEventListener('DOMContentLoaded', function() {
            $(document).on('click', '.btn-editar-grupo', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                if (!id) return;
                fetch(`${ACTION}?acao=obter&id=${encodeURIComponent(id)}`)
                    .then(r => r.json())
                    .then(json => {
                        if (!json?.success || !json.data) throw new Error();
                        fillEditar(json.data);
                        $('#EditarGrupo').modal('show');
                    })
                    .catch(() => Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: 'Não foi possível carregar os dados do grupo.'
                    }));
            });

            function handleSubmit(formId, confirmMsg) {
                $(formId).on('submit', function(ev) {
                    ev.preventDefault();
                    const fd = new FormData(this);
                    Swal.fire({
                            icon: 'question',
                            title: confirmMsg,
                            showCancelButton: true,
                            confirmButtonText: 'Salvar',
                            cancelButtonText: 'Cancelar'
                        })
                        .then(res => {
                            if (!res.isConfirmed) return;
                            fetch(ACTION, {
                                method: 'POST',
                                body: fd
                            }).then(r => r.json()).then(json => {
                                if (json.success) Swal.fire({
                                    icon: 'success',
                                    title: 'Salvo!',
                                    text: json.message || ''
                                }).then(() => location.reload());
                                else Swal.fire({
                                    icon: 'error',
                                    title: 'Erro',
                                    text: json.message || 'Não foi possível salvar.'
                                });
                            }).catch(() => Swal.fire({
                                icon: 'error',
                                title: 'Erro',
                                text: 'Falha ao comunicar com o servidor.'
                            }));
                        });
                });
            }
            handleSubmit('#formCadastrarGrupo', 'Confirmar cadastro?');
            handleSubmit('#formEditarGrupo', 'Confirmar alterações?');

            $(document).on('click', '.btn-excluir-grupo', function(ev) {
                ev.preventDefault();
                const id = $(this).data('id');
                if (!id) return;
                Swal.fire({
                        icon: 'warning',
                        title: 'Excluir grupo?',
                        text: 'Esta ação não poderá ser desfeita.',
                        showCancelButton: true,
                        confirmButtonText: 'Sim, excluir',
                        confirmButtonColor: '#c92a2a'
                    })
                    .then(res => {
                        if (!res.isConfirmed) return;
                        const fd = new FormData();
                        fd.set('acao', 'excluir');
                        fd.set('id', id);
                        fd.set('csrf', '<?= htmlspecialchars($csrf_grupo, ENT_QUOTES, "UTF-8") ?>');
                        fetch(ACTION, {
                            method: 'POST',
                            body: fd
                        }).then(r => r.json()).then(json => {
                            if (json.success) Swal.fire({
                                icon: 'success',
                                title: 'Excluído!',
                                text: json.message || ''
                            }).then(() => location.reload());
                            else Swal.fire({
                                icon: 'error',
                                title: 'Erro',
                                text: json.message || 'Não foi possível excluir.'
                            });
                        }).catch(() => Swal.fire({
                            icon: 'error',
                            title: 'Erro',
                            text: 'Falha ao comunicar com o servidor.'
                        }));
                    });
            });
        });
    })();
</script>