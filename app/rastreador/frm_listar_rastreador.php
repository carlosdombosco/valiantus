<?php
/* frm_listar_rastreador.php */
if (!defined('PATH_INC')) require_once __DIR__ . '/../../inc/config.php';
require_once PATH_INC . '/db.php';
require_once PATH_INC . '/csrf.php';
$csrf_ras = csrf_token();

$rastreadores = $pdo->query("SELECT * FROM tb_rastreador")->fetchAll(PDO::FETCH_OBJ);
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
                <div class="vt-icon-wrap"><i class="fa-solid fa-satellite-dish"></i></div>
                <h3>Rastreadores</h3>
            </div>
            <div class="vt-card-tools">
                <div class="vt-search-wrap">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="generalSearch" class="vt-search" placeholder="Buscar rastreador...">
                </div>
                <a href="#" class="vt-btn-new" data-toggle="modal" data-target="#CadastrarRastreador">
                    <i class="fa-solid fa-plus"></i> Novo Rastreador
                </a>
            </div>
        </div>

        <div class="vt-card-body">
            <div class="vt-table-wrap">
                <table class="vt-table" id="html_table" width="100%">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Modelo</th>
                            <th>Operadora</th>
                            <th>Nº Chip</th>
                            <th>Instalação</th>
                            <th>Situação</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rastreadores)): ?>
                            <tr>
                                <td colspan="7" class="text-center" style="padding:40px;color:#868e96;">
                                    <i class="fa-solid fa-satellite-dish" style="font-size:32px;margin-bottom:10px;display:block;opacity:.3;"></i>
                                    Nenhum rastreador encontrado.
                                </td>
                            </tr>
                            <?php else: foreach ($rastreadores as $r): ?>
                                <?php
                                $sc = match (strtoupper($r->RAS_STATUS ?? '')) {
                                    'ATIVO'      => 'vt-badge--green',
                                    'MANUTENÇÃO' => 'vt-badge--amber',
                                    default      => 'vt-badge--gray'
                                };
                                $brl = fn($v) => $v ? 'R$ ' . number_format((float)$v, 2, ',', '.') : '—';
                                ?>
                                <tr data-id="<?= (int)$r->RAS_CODIGO_PK ?>">
                                    <td ><?= htmlspecialchars($r->RAS_CODIGO) ?></td>
                                    <td style="font-weight:600;"><?= htmlspecialchars($r->RAS_MODELO) ?></td>
                                    <td><?= htmlspecialchars($r->RAS_OPERADORA) ?></td>
                                    <td ><?= htmlspecialchars($r->RAS_NUM_CHIP) ?></td>
                                    <td><?= $brl($r->RAS_VALOR_INSTALACAO) ?></td>
                                    <td><span class="vt-badge <?= $sc ?>"><?= htmlspecialchars($r->RAS_STATUS) ?></span></td>
                                    <td>
                                        <div class="vt-actions">
                                            <a href="#" class="vt-action-btn vt-action-btn--edit btn-editar-rastreador"
                                                title="Editar" data-id="<?= (int)$r->RAS_CODIGO_PK ?>">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>
                                            <a href="#" class="vt-action-btn vt-action-btn--del btn-excluir-rastreador"
                                                title="Excluir" data-id="<?= (int)$r->RAS_CODIGO_PK ?>">
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

<!-- Modal: Cadastrar Rastreador -->
<div class="modal fade vt-modal" id="CadastrarRastreador" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form id="formCadastrarRas" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-satellite-dish"></i> Novo Rastreador</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group col-md-4"><label>Código/Etiqueta</label><input type="text" class="form-control" name="codigo" autocomplete="off"></div>
                    <div class="form-group col-md-4"><label>Modelo</label><input type="text" class="form-control" name="modelo" autocomplete="off"></div>
                    <div class="form-group col-md-4">
                        <label>Operadora</label>
                        <select class="form-control" name="operadora">
                            <option value="">Selecione</option>
                            <option>VIVO</option>
                            <option>CLARO</option>
                            <option>TIM</option>
                            <option>OI</option>
                            <option>OUTRA</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4"><label>Nº Chip</label><input type="text" class="form-control" name="num_chip" id="ras_num_chip" placeholder="apenas números"></div>
                    <div class="form-group col-md-4"><label>Usuário</label><input type="text" class="form-control" name="usuario" autocomplete="off"></div>
                    <div class="form-group col-md-4">
                        <label>Senha</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="senha" id="ras_senha">
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary toggle-pass" type="button" data-target="#ras_senha"><i class="fa-regular fa-eye"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4"><label>Valor Equipamento (R$)</label><input type="text" class="form-control brl" name="valor_equip" id="ras_val_equip" placeholder="0,00"></div>
                    <div class="form-group col-md-4"><label>Valor Instalação (R$)</label><input type="text" class="form-control brl" name="valor_inst" id="ras_val_inst" placeholder="0,00"></div>
                    <div class="form-group col-md-4"><label>Data última recarga</label><input type="date" class="form-control" name="data_ultima_recarga"></div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-8"><label>Observação</label><textarea class="form-control" rows="3" name="observacao"></textarea></div>
                    <div class="form-group col-md-4">
                        <label>Status</label>
                        <select class="form-control" name="status">
                            <option>ATIVO</option>
                            <option>INATIVO</option>
                            <option>MANUTENÇÃO</option>
                        </select>
                    </div>
                </div>
            </div>
            <input type="hidden" name="acao" value="cadastrar">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_ras, ENT_QUOTES, 'UTF-8') ?>">
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Editar Rastreador -->
<div class="modal fade vt-modal" id="EditarRastreador" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form id="formEditarRas" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-pen-to-square"></i> Editar Rastreador</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="ras_id">
                <div class="form-row">
                    <div class="form-group col-md-4"><label>Código/Etiqueta</label><input type="text" class="form-control" name="codigo" id="ras_codigo_edit"></div>
                    <div class="form-group col-md-4"><label>Modelo</label><input type="text" class="form-control" name="modelo" id="ras_modelo_edit"></div>
                    <div class="form-group col-md-4">
                        <label>Operadora</label>
                        <select class="form-control" name="operadora" id="ras_operadora_edit">
                            <option value="">Selecione</option>
                            <option>VIVO</option>
                            <option>CLARO</option>
                            <option>TIM</option>
                            <option>OI</option>
                            <option>OUTRA</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4"><label>Nº Chip</label><input type="text" class="form-control" name="num_chip" id="ras_num_chip_edit"></div>
                    <div class="form-group col-md-4"><label>Usuário</label><input type="text" class="form-control" name="usuario" id="ras_usuario_edit"></div>
                    <div class="form-group col-md-4">
                        <label>Senha</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="senha" id="ras_senha_edit">
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary toggle-pass" type="button" data-target="#ras_senha_edit"><i class="fa-regular fa-eye"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4"><label>Valor Equipamento (R$)</label><input type="text" class="form-control brl" name="valor_equip" id="ras_val_equip_edit"></div>
                    <div class="form-group col-md-4"><label>Valor Instalação (R$)</label><input type="text" class="form-control brl" name="valor_inst" id="ras_val_inst_edit"></div>
                    <div class="form-group col-md-4"><label>Data última recarga</label><input type="date" class="form-control" name="data_ultima_recarga" id="ras_recarga_edit"></div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-8"><label>Observação</label><textarea class="form-control" rows="3" name="observacao" id="ras_obs_edit"></textarea></div>
                    <div class="form-group col-md-4">
                        <label>Status</label>
                        <select class="form-control" name="status" id="ras_status_edit">
                            <option>ATIVO</option>
                            <option>INATIVO</option>
                            <option>MANUTENÇÃO</option>
                        </select>
                    </div>
                </div>
            </div>
            <input type="hidden" name="acao" value="editar">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_ras, ENT_QUOTES, 'UTF-8') ?>">
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar alterações</button>
            </div>
        </form>
    </div>
</div>

<script>
    (function() {
        const ACTION = '<?= ACTION_URL ?>/rastreador.php';

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
                        `<button onclick="window._dtRas&&window._dtRas.page(${page - 1}).draw(false)"
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
                    sInfo: "Mostrando <strong>_START_–_END_</strong> de <strong>_TOTAL_</strong> rastreadores",
                    sInfoEmpty: "Nenhum rastreador encontrado",
                    sInfoFiltered: "(filtrado de _MAX_)",
                    sLengthMenu: "Por página: _MENU_",
                    sZeroRecords: "Nenhum registro encontrado"
                }
            });
            window._dtRas = dt;
            const s = document.getElementById('generalSearch');
            if (s) {
                let t;
                s.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => dt.search(s.value).draw(), 250); });
            }
        }
        document.addEventListener('DOMContentLoaded', initDT);

        /* só dígitos no chip */
        document.addEventListener('input', e => {
            if (['ras_num_chip', 'ras_num_chip_edit'].includes(e.target?.id)) e.target.value = e.target.value.replace(/\D/g, '');
        });

        /* BRL */
        function toBRL(el) {
            let v = (el.value || '').replace(/\D/g, '');
            if (!v) { el.value = ''; return; }
            if (v.length < 3) v = v.padStart(3, '0');
            el.value = v.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + v.slice(-2);
        }
        document.addEventListener('blur', e => {
            if (e.target?.classList.contains('brl')) toBRL(e.target);
        }, true);

        function fillEditar(d) {
            const brl = x => (!x && x !== 0) ? '' : Number(x).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            $('#ras_id').val(d.RAS_CODIGO_PK || '');
            $('#ras_codigo_edit').val(d.RAS_CODIGO || '');
            $('#ras_modelo_edit').val(d.RAS_MODELO || '');
            $('#ras_operadora_edit').val(d.RAS_OPERADORA || '');
            $('#ras_num_chip_edit').val(d.RAS_NUM_CHIP || '');
            $('#ras_usuario_edit').val(d.RAS_USUARIO || '');
            $('#ras_senha_edit').val(d.RAS_SENHA || '');
            $('#ras_val_equip_edit').val(brl(d.RAS_VALOR_EQUIPAMENTO));
            $('#ras_val_inst_edit').val(brl(d.RAS_VALOR_INSTALACAO));
            $('#ras_recarga_edit').val(d.RAS_DATA_ULTIMA_RECARGA || '');
            $('#ras_obs_edit').val(d.RAS_OBSERVACAO || '');
            $('#ras_status_edit').val(d.RAS_STATUS || 'ATIVO');
        }

        document.addEventListener('DOMContentLoaded', function() {
            /* toggle senha */
            $(document).on('click', '.toggle-pass', function() {
                const $inp = $($(this).data('target'));
                const t = $inp.attr('type') === 'password' ? 'text' : 'password';
                $inp.attr('type', t);
                $(this).find('i').toggleClass('fa-eye fa-eye-slash');
            });

            $(document).on('click', '.btn-editar-rastreador', function(ev) {
                ev.preventDefault();
                const id = $(this).closest('[data-id]').data('id');
                if (!id) return;
                fetch(`${ACTION}?acao=obter&id=${encodeURIComponent(id)}`)
                    .then(async r => {
                        const j = await r.json().catch(() => null);
                        if (!j?.success || !j.data) throw new Error();
                        return j.data;
                    })
                    .then(data => {
                        fillEditar(data);
                        $('#EditarRastreador').modal('show');
                    })
                    .catch(() => Swal.fire({ icon: 'error', title: 'Erro', text: 'Não foi possível carregar os dados do rastreador.' }));
            });

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
            postForm('#formCadastrarRas', 'Confirmar cadastro?');
            postForm('#formEditarRas', 'Confirmar alterações?');

            $(document).on('click', '.btn-excluir-rastreador', function(ev) {
                ev.preventDefault();
                const id = $(this).data('id');
                if (!id) return;
                Swal.fire({ icon: 'warning', title: 'Excluir rastreador?', text: 'Esta ação não poderá ser desfeita.', showCancelButton: true, confirmButtonText: 'Sim, excluir', confirmButtonColor: '#c92a2a' })
                    .then(res => {
                        if (!res.isConfirmed) return;
                        const fd = new FormData();
                        fd.set('acao', 'excluir');
                        fd.set('id', id);
                        fd.set('csrf', '<?= htmlspecialchars($csrf_ras, ENT_QUOTES, "UTF-8") ?>');
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