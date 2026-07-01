<?php
if (!defined('PATH_INC')) require_once __DIR__ . '/../../inc/config.php';
require_once PATH_INC . '/db.php';
require_once PATH_INC . '/csrf.php';
$csrf_usu = csrf_token();

// Garante tabela de usuários
$pdo->exec("CREATE TABLE IF NOT EXISTS `tb_usuario` (
    `USU_CODIGO_PK` INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `USU_NOME`      VARCHAR(150)     NOT NULL DEFAULT '',
    `USU_EMAIL`     VARCHAR(150)     NOT NULL DEFAULT '',
    `USU_SENHA`     VARCHAR(255)     NOT NULL DEFAULT '',
    `USU_TIPO`      VARCHAR(20)      NOT NULL DEFAULT 'USUARIO',
    `USU_ATIVO`     TINYINT(1)       NOT NULL DEFAULT 1,
    `USU_CRIADO_EM` DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`USU_CODIGO_PK`),
    UNIQUE KEY `idx_usu_email` (`USU_EMAIL`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Garante coluna USU_ATIVO em instalações antigas
try { $pdo->exec("ALTER TABLE `tb_usuario` ADD COLUMN `USU_ATIVO` TINYINT(1) NOT NULL DEFAULT 1"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE `tb_usuario` ADD COLUMN `USU_CRIADO_EM` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP"); } catch (Throwable $e) {}

$usuarios = $pdo->query("SELECT USU_CODIGO_PK, USU_NOME, USU_EMAIL, USU_TIPO, USU_ATIVO, USU_CRIADO_EM FROM tb_usuario ORDER BY USU_NOME ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<link rel="stylesheet" href="../valiantus-tables.css">

<div class="vt-page">
    <div class="vt-card">

        <div class="vt-card-header">
            <div class="vt-card-title">
                <div class="vt-icon-wrap"><i class="fa-solid fa-user-gear"></i></div>
                <h3>Usuários do Sistema</h3>
            </div>
            <div class="vt-card-tools">
                <div class="vt-search-wrap">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="usuSearch" class="vt-search" placeholder="Buscar usuário...">
                </div>
                <button class="vt-btn-new" data-toggle="modal" data-target="#ModalNovoUsuario">
                    <i class="fa-solid fa-plus"></i> Novo Usuário
                </button>
            </div>
        </div>

        <div class="vt-card-body">
            <div class="vt-table-wrap">
                <table class="vt-table" id="tblUsuarios" width="100%">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Perfil</th>
                            <th>Situação</th>
                            <th>Cadastrado em</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usuarios)): ?>
                            <tr>
                                <td colspan="6" class="text-center" style="padding:40px;color:#868e96;">
                                    <i class="fa-solid fa-users" style="font-size:32px;margin-bottom:10px;display:block;opacity:.3;"></i>
                                    Nenhum usuário cadastrado.
                                </td>
                            </tr>
                        <?php else: foreach ($usuarios as $u): ?>
                            <tr data-id="<?= (int)$u['USU_CODIGO_PK'] ?>">
                                <td style="font-weight:600;"><?= htmlspecialchars($u['USU_NOME']) ?></td>
                                <td><?= htmlspecialchars($u['USU_EMAIL']) ?></td>
                                <td>
                                    <?php $tipo = strtoupper($u['USU_TIPO'] ?? 'USUARIO'); ?>
                                    <span class="vt-badge <?= $tipo === 'ADMIN' ? 'vt-badge--blue' : 'vt-badge--gray' ?>">
                                        <?= htmlspecialchars($u['USU_TIPO']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php $ativo = (bool)($u['USU_ATIVO'] ?? 1); ?>
                                    <span class="vt-badge <?= $ativo ? 'vt-badge--green' : 'vt-badge--gray' ?>">
                                        <?= $ativo ? 'ATIVO' : 'INATIVO' ?>
                                    </span>
                                </td>
                                <td><?= $u['USU_CRIADO_EM'] ? date('d/m/Y', strtotime($u['USU_CRIADO_EM'])) : '—' ?></td>
                                <td>
                                    <div class="vt-actions">
                                        <button class="vt-action-btn vt-action-btn--edit btn-editar-usu"
                                                title="Editar usuário"
                                                data-id="<?= (int)$u['USU_CODIGO_PK'] ?>">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <button class="vt-action-btn vt-action-btn--del btn-excluir-usu"
                                                title="Excluir usuário"
                                                data-id="<?= (int)$u['USU_CODIGO_PK'] ?>"
                                                data-nome="<?= htmlspecialchars($u['USU_NOME'], ENT_QUOTES) ?>">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Modal: Novo Usuário -->
<div class="modal fade vt-modal" id="ModalNovoUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form id="formNovoUsuario" class="modal-content" autocomplete="off">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-user-plus"></i> Novo Usuário</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Nome completo <span style="color:#c92a2a">*</span></label>
                    <input type="text" class="form-control" name="nome" required placeholder="Nome do usuário">
                </div>
                <div class="form-group">
                    <label>E-mail (login) <span style="color:#c92a2a">*</span></label>
                    <input type="email" class="form-control" name="email" required placeholder="email@exemplo.com.br" autocomplete="off">
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Senha <span style="color:#c92a2a">*</span></label>
                        <input type="password" class="form-control" name="senha" required placeholder="Mínimo 6 caracteres" minlength="6" autocomplete="new-password">
                    </div>
                    <div class="form-group col-md-6">
                        <label>Confirmar senha <span style="color:#c92a2a">*</span></label>
                        <input type="password" class="form-control" name="senha_confirmar" required placeholder="Repita a senha" minlength="6" autocomplete="new-password">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Perfil</label>
                        <select class="form-control" name="tipo">
                            <option value="USUARIO">Usuário</option>
                            <option value="ADMIN">Administrador</option>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Situação</label>
                        <select class="form-control" name="ativo">
                            <option value="1">Ativo</option>
                            <option value="0">Inativo</option>
                        </select>
                    </div>
                </div>
            </div>
            <input type="hidden" name="acao" value="cadastrar">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_usu, ENT_QUOTES, 'UTF-8') ?>">
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Editar Usuário -->
<div class="modal fade vt-modal" id="ModalEditarUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form id="formEditarUsuario" class="modal-content" autocomplete="off">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-user-pen"></i> Editar Usuário</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="edit_usu_id">
                <div class="form-group">
                    <label>Nome completo <span style="color:#c92a2a">*</span></label>
                    <input type="text" class="form-control" name="nome" id="edit_usu_nome" required>
                </div>
                <div class="form-group">
                    <label>E-mail (login) <span style="color:#c92a2a">*</span></label>
                    <input type="email" class="form-control" name="email" id="edit_usu_email" required autocomplete="off">
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Nova senha <small style="color:#868e96">(deixe em branco para manter)</small></label>
                        <input type="password" class="form-control" name="senha" placeholder="••••••" minlength="6" autocomplete="new-password">
                    </div>
                    <div class="form-group col-md-6">
                        <label>Confirmar nova senha</label>
                        <input type="password" class="form-control" name="senha_confirmar" placeholder="••••••" autocomplete="new-password">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Perfil</label>
                        <select class="form-control" name="tipo" id="edit_usu_tipo">
                            <option value="USUARIO">Usuário</option>
                            <option value="ADMIN">Administrador</option>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Situação</label>
                        <select class="form-control" name="ativo" id="edit_usu_ativo">
                            <option value="1">Ativo</option>
                            <option value="0">Inativo</option>
                        </select>
                    </div>
                </div>
            </div>
            <input type="hidden" name="acao" value="editar">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_usu, ENT_QUOTES, 'UTF-8') ?>">
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Salvar alterações</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const ACTION = '<?= ACTION_URL ?>/usuarios.php';

    /* ── DataTable ── */
    function initDT() {
        if (!window.jQuery || !$.fn.DataTable) return setTimeout(initDT, 60);
        const dt = $('#tblUsuarios').DataTable({
            autoWidth: false, scrollX: false, pageLength: 15,
            dom: 't<"dt-footer"<"dt-footer-info"i><"dt-footer-paging"p><"dt-footer-per"l>>',
            order: [[0, 'asc']],
            columnDefs: [{ targets: -1, orderable: false, searchable: false }],
            language: {
                sEmptyTable: 'Nenhum usuário cadastrado',
                sInfo: 'Mostrando <strong>_START_–_END_</strong> de <strong>_TOTAL_</strong> usuários',
                sInfoEmpty: 'Nenhum usuário encontrado',
                sInfoFiltered: '(filtrado de _MAX_)',
                sLengthMenu: 'Por página: _MENU_',
                sZeroRecords: 'Nenhum registro encontrado'
            }
        });
        const s = document.getElementById('usuSearch');
        if (s) s.addEventListener('input', function () { dt.search(this.value).draw(); });
    }
    document.addEventListener('DOMContentLoaded', initDT);

    /* ── Submit genérico ── */
    function bindForm(formId) {
        const form = document.getElementById(formId);
        if (!form) return;
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const fd    = new FormData(this);
            const senha = fd.get('senha');
            const conf  = fd.get('senha_confirmar');
            if (senha && senha !== conf) {
                Swal.fire({ icon: 'warning', title: 'Atenção', text: 'As senhas não conferem.' });
                return;
            }
            fetch(ACTION, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(j => {
                    if (j.success) {
                        Swal.fire({ icon: 'success', title: 'Salvo!', text: j.message, timer: 2000, showConfirmButton: false })
                            .then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Erro', text: j.message || 'Não foi possível salvar.' });
                    }
                })
                .catch(() => Swal.fire({ icon: 'error', title: 'Erro', text: 'Falha ao comunicar com o servidor.' }));
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindForm('formNovoUsuario');
        bindForm('formEditarUsuario');

        /* ── Editar ── */
        $(document).on('click', '.btn-editar-usu', function () {
            const id = $(this).data('id');
            fetch(ACTION + '?acao=obter&id=' + encodeURIComponent(id))
                .then(r => r.json())
                .then(j => {
                    if (!j.success || !j.data) throw new Error();
                    const d = j.data;
                    $('#edit_usu_id').val(d.USU_CODIGO_PK);
                    $('#edit_usu_nome').val(d.USU_NOME);
                    $('#edit_usu_email').val(d.USU_EMAIL);
                    $('#edit_usu_tipo').val(d.USU_TIPO);
                    $('#edit_usu_ativo').val(d.USU_ATIVO);
                    // limpa senhas
                    $('#formEditarUsuario input[name=senha]').val('');
                    $('#formEditarUsuario input[name=senha_confirmar]').val('');
                    $('#ModalEditarUsuario').modal('show');
                })
                .catch(() => Swal.fire({ icon: 'error', title: 'Erro', text: 'Não foi possível carregar os dados.' }));
        });

        /* ── Excluir ── */
        $(document).on('click', '.btn-excluir-usu', function () {
            const id   = $(this).data('id');
            const nome = $(this).data('nome');
            Swal.fire({
                icon: 'warning',
                title: 'Excluir usuário?',
                text: 'O usuário "' + nome + '" será removido. Esta ação não pode ser desfeita.',
                showCancelButton: true,
                confirmButtonText: 'Sim, excluir',
                confirmButtonColor: '#c92a2a',
                cancelButtonText: 'Cancelar'
            }).then(res => {
                if (!res.isConfirmed) return;
                const fd = new FormData();
                fd.set('acao', 'excluir');
                fd.set('id', id);
                fd.set('csrf', '<?= htmlspecialchars($csrf_usu, ENT_QUOTES, "UTF-8") ?>');
                fetch(ACTION, { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(j => {
                        if (j.success) {
                            Swal.fire({ icon: 'success', title: 'Excluído!', timer: 1500, showConfirmButton: false })
                                .then(() => location.reload());
                        } else {
                            Swal.fire({ icon: 'error', title: 'Erro', text: j.message || 'Não foi possível excluir.' });
                        }
                    })
                    .catch(() => Swal.fire({ icon: 'error', title: 'Erro', text: 'Falha ao comunicar com o servidor.' }));
            });
        });
    });
})();
</script>
