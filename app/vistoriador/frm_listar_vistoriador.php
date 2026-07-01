<?php
/* frm_listar_vistoriador.php */
if (!defined('PATH_INC')) require_once __DIR__ . '/../../inc/config.php';
require_once PATH_INC . '/db.php';
require_once PATH_INC . '/csrf.php';
$csrf_vis = csrf_token();

$vistoriadores = $pdo->query("SELECT * FROM tb_vistoriador ORDER BY VIS_NOME")->fetchAll(PDO::FETCH_OBJ);
$total    = count($vistoriadores);
$ativos   = count(array_filter($vistoriadores, fn($v) => ($v->VIS_STATUS ?? '') === 'ATIVO'));
$inativos = $total - $ativos;

$ufs = ['AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MG', 'MS', 'MT', 'PA', 'PB', 'PE', 'PI', 'PR', 'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO'];
function uf_options_vis(string $sel = ''): string
{
    global $ufs;
    $html = '<option value="">Selecione</option>';
    foreach ($ufs as $u) $html .= '<option value="' . $u . '"' . ($u === $sel ? ' selected' : '') . '>' . $u . '</option>';
    return $html;
}
function fmt_cpf_vis(string $cpf): string
{
    $cpf = preg_replace('/\D/', '', $cpf);
    if (strlen($cpf) === 11) return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    return $cpf;
}
function fmt_fone_vis(string $n): string
{
    $n = preg_replace('/\D/', '', $n);
    if (strlen($n) === 11) return '(' . substr($n, 0, 2) . ') ' . substr($n, 2, 5) . '-' . substr($n, 7, 4);
    if (strlen($n) === 10) return '(' . substr($n, 0, 2) . ') ' . substr($n, 2, 4) . '-' . substr($n, 6, 4);
    return $n;
}
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

    <!-- Cards de estatísticas -->
    <div class="vt-stats-row" style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap;">
        <div class="vt-stat-card" style="flex:1;min-width:160px;background:#fff;border-radius:10px;padding:18px 22px;box-shadow:0 1px 4px rgba(0,0,0,.08);display:flex;align-items:center;gap:14px;">
            <div style="width:42px;height:42px;border-radius:10px;background:#e7f5ff;display:flex;align-items:center;justify-content:center;font-size:20px;color:#1971c2;"><i class="fa-solid fa-clipboard-check"></i></div>
            <div>
                <div style="font-size:22px;font-weight:700;color:#212529;"><?= $total ?></div>
                <div style="font-size:12px;color:#868e96;">Total de Vistoriadores</div>
            </div>
        </div>
        <div class="vt-stat-card" style="flex:1;min-width:160px;background:#fff;border-radius:10px;padding:18px 22px;box-shadow:0 1px 4px rgba(0,0,0,.08);display:flex;align-items:center;gap:14px;">
            <div style="width:42px;height:42px;border-radius:10px;background:#ebfbee;display:flex;align-items:center;justify-content:center;font-size:20px;color:#2f9e44;"><i class="fa-solid fa-circle-check"></i></div>
            <div>
                <div style="font-size:22px;font-weight:700;color:#212529;"><?= $ativos ?></div>
                <div style="font-size:12px;color:#868e96;">Ativos</div>
            </div>
        </div>
        <div class="vt-stat-card" style="flex:1;min-width:160px;background:#fff;border-radius:10px;padding:18px 22px;box-shadow:0 1px 4px rgba(0,0,0,.08);display:flex;align-items:center;gap:14px;">
            <div style="width:42px;height:42px;border-radius:10px;background:#fff5f5;display:flex;align-items:center;justify-content:center;font-size:20px;color:#c92a2a;"><i class="fa-solid fa-circle-xmark"></i></div>
            <div>
                <div style="font-size:22px;font-weight:700;color:#212529;"><?= $inativos ?></div>
                <div style="font-size:12px;color:#868e96;">Inativos</div>
            </div>
        </div>
    </div>

    <div class="vt-card">
        <div class="vt-card-header">
            <div class="vt-card-title">
                <div class="vt-icon-wrap"><i class="fa-solid fa-clipboard-check"></i></div>
                <h3>Vistoriadores</h3>
            </div>
            <div class="vt-card-tools">
                <div class="vt-search-wrap">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="generalSearch" class="vt-search" placeholder="Buscar vistoriador...">
                </div>
                <a href="#" class="vt-btn-new" data-toggle="modal" data-target="#ModalCadastrarVistoriador">
                    <i class="fa-solid fa-plus"></i> Novo Vistoriador
                </a>
            </div>
        </div>

        <div class="vt-card-body">
            <div class="vt-table-wrap">
                <table class="vt-table" id="tbl_vistoriadores" width="100%">
                    <thead>
                        <tr>
                            <th style="width:60px;">Código</th>
                            <th style="width:48px;"></th>
                            <th>Nome</th>
                            <th style="width:140px;">CPF</th>
                            <th style="width:140px;">Telefone</th>
                            <th style="width:90px;">Status</th>
                            <th class="text-end" style="width:90px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vistoriadores as $v):
                            $statusClass = ($v->VIS_STATUS ?? 'INATIVO') === 'ATIVO' ? 'vt-badge--green' : 'vt-badge--red';
                            $fotoSrc     = !empty($v->VIS_FOTO) ? htmlspecialchars($v->VIS_FOTO) : '';
                        ?>
                            <tr>
                                <td>#<?= (int)$v->VIS_CODIGO_PK ?></td>
                                <td>
                                    <?php if ($fotoSrc): ?>
                                        <img src="<?= $fotoSrc ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:1px solid #dee2e6;">
                                    <?php else: ?>
                                        <span style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:50%;background:#e9ecef;color:#adb5bd;font-size:15px;"><i class="fa-solid fa-user"></i></span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-weight:600;"><?= htmlspecialchars($v->VIS_NOME ?? '') ?></td>
                                <td><?= htmlspecialchars(fmt_cpf_vis($v->VIS_CPF ?? '')) ?></td>
                                <td><?= htmlspecialchars(fmt_fone_vis($v->VIS_FONE_CELULAR ?? '')) ?></td>
                                <td>
                                    <span class="vt-badge <?= $statusClass ?>"><?= htmlspecialchars($v->VIS_STATUS ?? '') ?></span>
                                </td>
                                <td>
                                    <div class="vt-actions">
                                        <a href="#" class="vt-action-btn vt-action-btn--edit btn-editar-vistoriador"
                                            title="Editar"
                                            data-id="<?= (int)$v->VIS_CODIGO_PK ?>">
                                            <i class="fa-solid fa-pen"></i>
                                        </a>
                                        <a href="#" class="vt-action-btn vt-action-btn--del btn-excluir-vistoriador"
                                            title="Excluir"
                                            data-id="<?= (int)$v->VIS_CODIGO_PK ?>"
                                            data-nome="<?= htmlspecialchars($v->VIS_NOME ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
/* ── Helper: bloco de campos UF + Cidade ─────────────────── */
function uf_cidade_block_vis(string $pfx, string $ufSel = '', string $cidSel = ''): void
{
    global $ufs;
    echo '<div class="col-md-2 mb-2">
        <label class="form-label">UF</label>
        <select class="form-control form-control-sm vis-uf" id="' . $pfx . '_uf" name="uf">';
    echo '<option value="">Selecione</option>';
    foreach ($ufs as $u) echo '<option value="' . $u . '"' . ($u === $ufSel ? ' selected' : '') . '>' . $u . '</option>';
    echo '</select></div>
    <div class="col-md-4 mb-2">
        <label class="form-label">Cidade</label>
        <select class="form-control form-control-sm vis-cidade" id="' . $pfx . '_cidade" name="cidade">
            <option value="' . htmlspecialchars($cidSel) . '">' . (($cidSel !== '') ? htmlspecialchars($cidSel) : 'Selecione') . '</option>
        </select>
    </div>';
}

/* ── Macro: bloco de foto ────────────────────────────────── */
function foto_block_vis(string $sfx, string $fotoUrl = ''): void
{
    $svgRaw = '<svg xmlns="http://www.w3.org/2000/svg" width="140" height="140">'
            . '<rect width="140" height="140" fill="#e9ecef"/>'
            . '<circle cx="70" cy="52" r="28" fill="#adb5bd"/>'
            . '<ellipse cx="70" cy="130" rx="50" ry="40" fill="#adb5bd"/>'
            . '</svg>';
    $ph  = 'data:image/svg+xml;base64,' . base64_encode($svgRaw);
    $src = $fotoUrl ?: '';
    echo '
    <div class="text-center">
        <div style="margin:0 auto 12px;width:140px;height:140px;position:relative;">
            <img id="visFotoPreview' . $sfx . '" src="' . ($src ?: $ph) . '"
                style="width:140px;height:140px;border-radius:50%;object-fit:cover;border:2px solid #dee2e6;display:block;">
            <video id="visCameraStream' . $sfx . '" class="d-none"
                style="width:140px;height:140px;border-radius:50%;object-fit:cover;border:2px solid #dee2e6;"
                autoplay playsinline muted></video>
        </div>
        <div class="btn-group-vertical w-100" style="gap:4px;">
            <button type="button" class="btn btn-sm btn-outline-primary" id="btnVisFoto' . $sfx . '"><i class="fa fa-upload"></i> Enviar</button>
            <button type="button" class="btn btn-sm btn-outline-info" id="btnVisCam' . $sfx . '"><i class="fa fa-camera"></i> Câmera</button>
            <button type="button" class="btn btn-sm btn-success d-none" id="btnVisCap' . $sfx . '"><i class="fa fa-check"></i> Capturar</button>
            <button type="button" class="btn btn-sm btn-outline-secondary d-none mt-1" id="btnVisCamOff' . $sfx . '"><i class="fa fa-times"></i> Cancelar</button>
            <button type="button" class="btn btn-sm btn-outline-danger mt-1" id="btnVisRem' . $sfx . '"><i class="fa fa-trash"></i> Remover</button>
        </div>
        <input type="file" id="visFotoFile' . $sfx . '" name="foto" accept="image/*" class="d-none">
        <input type="hidden" name="foto_base64" id="visFotoB64' . $sfx . '">
    </div>';
}
?>

<!-- ═══════════════════════════════════════════════════════════════════
     Modal: Cadastrar Vistoriador
═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade vt-modal" id="ModalCadastrarVistoriador" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl" style="max-width:1400px;width:95%">
        <form id="formCadastrarVistoriador" class="modal-content" enctype="multipart/form-data">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-clipboard-check"></i> Novo Vistoriador</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <!-- Formulário principal -->
                    <div class="col-md-9">
                        <div class="row">
                            <div class="col-md-9 mb-2">
                                <label class="form-label">Nome <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" name="nome" id="vis_nome" required>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="form-label">CPF <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm vis-cpf-input" id="vis_cpf" name="cpf"
                                    inputmode="numeric" pattern="\d*" maxlength="14" required>
                                <div class="invalid-feedback" id="vis_cpf_feedback">CPF já cadastrado.</div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Celular / WhatsApp</label>
                                <input type="text" class="form-control form-control-sm" name="celular" inputmode="numeric" pattern="\d*" maxlength="11">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Fone Fixo</label>
                                <input type="text" class="form-control form-control-sm" name="fone_fixo" inputmode="numeric" pattern="\d*" maxlength="10">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">E-mail</label>
                                <input type="email" class="form-control form-control-sm" name="email">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <label class="form-label">Sexo</label>
                                <select class="form-control form-control-sm" name="sexo">
                                    <option value="">Selecione...</option>
                                    <option>MASCULINO</option>
                                    <option>FEMININO</option>
                                    <option>OUTROS</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="form-label">Estado Civil</label>
                                <select class="form-control form-control-sm" name="estado_civil">
                                    <option value="">Selecione...</option>
                                    <option>SOLTEIRO</option>
                                    <option>CASADO</option>
                                    <option>OUTROS</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="form-label">Data Nascimento</label>
                                <input type="date" class="form-control form-control-sm" name="data_nascimento">
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="form-label">Status</label>
                                <select class="form-control form-control-sm" name="status">
                                    <option value="ATIVO">ATIVO</option>
                                    <option value="INATIVO">INATIVO</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-2">
                                <label class="form-label">RG</label>
                                <input type="text" class="form-control form-control-sm" name="rg">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Órgão Expedidor</label>
                                <input type="text" class="form-control form-control-sm" name="orgao">
                            </div>
                        </div>
                        <hr style="margin:8px 0;">
                        <div class="row">
                            <div class="col-md-2 mb-2">
                                <label class="form-label">CEP</label>
                                <input type="text" class="form-control form-control-sm vis-cep" id="vis_cep" name="cep" maxlength="9">
                            </div>
                            <div class="col-md-7 mb-2">
                                <label class="form-label">Endereço</label>
                                <input type="text" class="form-control form-control-sm" id="vis_end" name="endereco">
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="form-label">Número</label>
                                <input type="text" class="form-control form-control-sm" name="numero">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Bairro</label>
                                <input type="text" class="form-control form-control-sm" id="vis_bairro" name="bairro">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Complemento</label>
                                <input type="text" class="form-control form-control-sm" name="complemento">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Ponto de Referência</label>
                                <input type="text" class="form-control form-control-sm" name="referencia">
                            </div>
                        </div>
                        <div class="row">
                            <?php uf_cidade_block_vis('vis') ?>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-2">
                                <label class="form-label">Observação</label>
                                <textarea class="form-control form-control-sm" name="observacao" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <!-- Foto -->
                    <div class="col-md-3">
                        <?php foto_block_vis('Cad') ?>
                    </div>
                </div>
            </div>
            <input type="hidden" name="acao" value="cadastrar">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_vis, ENT_QUOTES, 'UTF-8') ?>">
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btnSalvarVisCad">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════
     Modal: Editar Vistoriador
═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade vt-modal" id="ModalEditarVistoriador" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl" style="max-width:1400px;width:95%">
        <form id="formEditarVistoriador" class="modal-content" enctype="multipart/form-data">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-pen-to-square"></i> Editar Vistoriador</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="vis_edit_id">
                <div class="row">
                    <div class="col-md-9">
                        <div class="row">
                            <div class="col-md-9 mb-2">
                                <label class="form-label">Nome <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" name="nome" id="vis_edit_nome" required>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="form-label">CPF <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm vis-cpf-input" id="vis_edit_cpf" name="cpf"
                                    inputmode="numeric" pattern="\d*" maxlength="14" required>
                                <div class="invalid-feedback" id="vis_edit_cpf_feedback">CPF já cadastrado.</div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Celular / WhatsApp</label>
                                <input type="text" class="form-control form-control-sm" name="celular" id="vis_edit_celular" inputmode="numeric" pattern="\d*" maxlength="11">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Fone Fixo</label>
                                <input type="text" class="form-control form-control-sm" name="fone_fixo" id="vis_edit_fone" inputmode="numeric" pattern="\d*" maxlength="10">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">E-mail</label>
                                <input type="email" class="form-control form-control-sm" name="email" id="vis_edit_email">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <label class="form-label">Sexo</label>
                                <select class="form-control form-control-sm" name="sexo" id="vis_edit_sexo">
                                    <option value="">Selecione...</option>
                                    <option>MASCULINO</option>
                                    <option>FEMININO</option>
                                    <option>OUTROS</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="form-label">Estado Civil</label>
                                <select class="form-control form-control-sm" name="estado_civil" id="vis_edit_ecivil">
                                    <option value="">Selecione...</option>
                                    <option>SOLTEIRO</option>
                                    <option>CASADO</option>
                                    <option>OUTROS</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="form-label">Data Nascimento</label>
                                <input type="date" class="form-control form-control-sm" name="data_nascimento" id="vis_edit_nasc">
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="form-label">Status</label>
                                <select class="form-control form-control-sm" name="status" id="vis_edit_status">
                                    <option value="ATIVO">ATIVO</option>
                                    <option value="INATIVO">INATIVO</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-2">
                                <label class="form-label">RG</label>
                                <input type="text" class="form-control form-control-sm" name="rg" id="vis_edit_rg">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Órgão Expedidor</label>
                                <input type="text" class="form-control form-control-sm" name="orgao" id="vis_edit_orgao">
                            </div>
                        </div>
                        <hr style="margin:8px 0;">
                        <div class="row">
                            <div class="col-md-2 mb-2">
                                <label class="form-label">CEP</label>
                                <input type="text" class="form-control form-control-sm vis-cep" id="vis_edit_cep" name="cep" maxlength="9">
                            </div>
                            <div class="col-md-7 mb-2">
                                <label class="form-label">Endereço</label>
                                <input type="text" class="form-control form-control-sm" id="vis_edit_end" name="endereco">
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="form-label">Número</label>
                                <input type="text" class="form-control form-control-sm" id="vis_edit_num" name="numero">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Bairro</label>
                                <input type="text" class="form-control form-control-sm" id="vis_edit_bairro" name="bairro">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Complemento</label>
                                <input type="text" class="form-control form-control-sm" id="vis_edit_comp" name="complemento">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Ponto de Referência</label>
                                <input type="text" class="form-control form-control-sm" id="vis_edit_ref" name="referencia">
                            </div>
                        </div>
                        <div class="row">
                            <?php uf_cidade_block_vis('vis_edit') ?>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-2">
                                <label class="form-label">Observação</label>
                                <textarea class="form-control form-control-sm" name="observacao" id="vis_edit_obs" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <?php foto_block_vis('Edit') ?>
                    </div>
                </div>
            </div>
            <input type="hidden" name="acao" value="editar">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_vis, ENT_QUOTES, 'UTF-8') ?>">
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btnSalvarVisEdit">Salvar alterações</button>
            </div>
        </form>
    </div>
</div>

<script>
    (function() {
        const ACTION = '<?= ACTION_URL ?>/vistoriadores.php';
        const PH_IMG = '<?= "data:image/svg+xml;base64," . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="140" height="140"><rect width="140" height="140" fill="#e9ecef"/><circle cx="70" cy="52" r="28" fill="#adb5bd"/><ellipse cx="70" cy="130" rx="50" ry="40" fill="#adb5bd"/></svg>') ?>';

        /* ── DataTable ── */
        function initDT() {
            if (!window.jQuery || !$.fn.DataTable) return setTimeout(initDT, 60);
            const dt = $('#tbl_vistoriadores').DataTable({
                autoWidth: false,
                scrollX: false,
                pageLength: 10,
                dom: 't<"dt-footer"<"dt-footer-info"i><"dt-footer-paging"p><"dt-footer-per"l>>',
                order: [[0, 'desc']],
                columnDefs: [
                    { targets: 1,  orderable: false, searchable: false },
                    { targets: -1, orderable: false, searchable: false }
                ],
                drawCallback: function() {
                    const api  = this.api();
                    const info = api.page.info();
                    const cur  = info.page + 1;
                    const tot  = info.pages;
                    const wrap = document.querySelector('.dt-footer-paging');
                    if (!wrap) return;
                    const btn = (icon, page, disabled, active) =>
                        `<button onclick="window._dtVis&&window._dtVis.page(${page - 1}).draw(false)"
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
                    sInfo: "Mostrando <strong>_START_–_END_</strong> de <strong>_TOTAL_</strong> vistoriadores",
                    sInfoEmpty: "Nenhum vistoriador encontrado",
                    sInfoFiltered: "(filtrado de _MAX_)",
                    sLengthMenu: "Por página: _MENU_",
                    sZeroRecords: "Nenhum registro encontrado"
                }
            });
            window._dtVis = dt;
            const s = document.getElementById('generalSearch');
            if (s) {
                let t;
                s.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => dt.search(s.value).draw(), 250); });
            }
        }
        document.addEventListener('DOMContentLoaded', initDT);

        /* ── CEP helpers ── */
        function mascaraCep(el) {
            el.addEventListener('input', function() {
                let v = this.value.replace(/\D/g, '').slice(0, 8);
                this.value = v.length > 5 ? v.slice(0, 5) + '-' + v.slice(5) : v;
            });
        }

        function preencherCidades(uf, selectEl) {
            const cidades = (window.cidadesPorEstado || {})[uf] || [];
            selectEl.innerHTML = '<option value="">Selecione</option>';
            cidades.forEach(c => {
                const o = document.createElement('option');
                o.value = c;
                o.textContent = c;
                selectEl.appendChild(o);
            });
        }

        function buscarCep(cepRaw, ids) {
            const cep = cepRaw.replace(/\D/g, '');
            if (cep.length !== 8) return;
            fetch(`https://viacep.com.br/ws/${cep}/json/`)
                .then(r => r.json())
                .then(d => {
                    if (d.erro) return;
                    const set = (id, v) => {
                        const el = document.getElementById(id);
                        if (el) el.value = v || '';
                    };
                    set(ids.end, d.logradouro);
                    set(ids.bairro, d.bairro);
                    const ufEl = document.getElementById(ids.uf);
                    if (ufEl && d.uf) {
                        ufEl.value = d.uf;
                        const cidEl = document.getElementById(ids.cidade);
                        if (cidEl) {
                            preencherCidades(d.uf, cidEl);
                            setTimeout(() => cidEl.value = d.localidade || '', 50);
                        }
                    }
                }).catch(() => {});
        }

        /* ── Validação CPF ── */
        function validarCpf(cpf) {
            cpf = cpf.replace(/\D/g, '');
            if (cpf.length !== 11 || /^(\d)\1+$/.test(cpf)) return false;
            let s = 0;
            for (let i = 0; i < 9; i++) s += +cpf[i] * (10 - i);
            let r = (s * 10) % 11;
            if (r === 10 || r === 11) r = 0;
            if (r !== +cpf[9]) return false;
            s = 0;
            for (let i = 0; i < 10; i++) s += +cpf[i] * (11 - i);
            r = (s * 10) % 11;
            if (r === 10 || r === 11) r = 0;
            return r === +cpf[10];
        }

        function verificarCpfServidor(inputEl, feedbackEl, btnEl, excludeId) {
            const cpf = inputEl.value.replace(/\D/g, '');
            if (!validarCpf(cpf)) {
                inputEl.classList.add('is-invalid');
                feedbackEl.textContent = 'CPF inválido.';
                btnEl.disabled = true;
                return;
            }
            const fd = new FormData();
            fd.append('acao', 'verificar_cpf');
            fd.append('csrf', '<?= htmlspecialchars($csrf_vis, ENT_QUOTES, "UTF-8") ?>');
            fd.append('cpf', cpf);
            if (excludeId) fd.append('id', excludeId);
            fetch(ACTION, {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(j => {
                    if (j.existe) {
                        inputEl.classList.add('is-invalid');
                        feedbackEl.textContent = 'CPF já cadastrado.';
                        btnEl.disabled = true;
                    } else {
                        inputEl.classList.remove('is-invalid');
                        btnEl.disabled = false;
                    }
                }).catch(() => {});
        }

        /* ── Foto helpers ── */
        function setupFoto(sfx) {
            const preview = document.getElementById('visFotoPreview' + sfx);
            const video = document.getElementById('visCameraStream' + sfx);
            const fileInp = document.getElementById('visFotoFile' + sfx);
            const b64Inp = document.getElementById('visFotoB64' + sfx);
            const btnFoto = document.getElementById('btnVisFoto' + sfx);
            const btnCam = document.getElementById('btnVisCam' + sfx);
            const btnCap = document.getElementById('btnVisCap' + sfx);
            const btnCamOff = document.getElementById('btnVisCamOff' + sfx);
            const btnRem = document.getElementById('btnVisRem' + sfx);
            if (!preview) return;
            let stream = null;

            btnFoto.addEventListener('click', () => fileInp.click());
            fileInp.addEventListener('change', function() {
                if (!this.files[0]) return;
                const reader = new FileReader();
                reader.onload = e => {
                    preview.src = e.target.result;
                    b64Inp.value = '';
                };
                reader.readAsDataURL(this.files[0]);
            });
            btnRem.addEventListener('click', () => {
                preview.src = PH_IMG;
                fileInp.value = '';
                b64Inp.value = '';
            });
            btnCam.addEventListener('click', () => {
                navigator.mediaDevices.getUserMedia({
                        video: true
                    })
                    .then(s => {
                        stream = s;
                        video.srcObject = s;
                        preview.classList.add('d-none');
                        video.classList.remove('d-none');
                        btnCap.classList.remove('d-none');
                        btnCamOff.classList.remove('d-none');
                        btnFoto.classList.add('d-none');
                        btnCam.classList.add('d-none');
                        btnRem.classList.add('d-none');
                    }).catch(() => Swal.fire({
                        icon: 'error',
                        title: 'Câmera indisponível',
                        text: 'Permita o acesso à câmera.'
                    }));
            });

            function pararCam() {
                if (stream) {
                    stream.getTracks().forEach(t => t.stop());
                    stream = null;
                }
                video.classList.add('d-none');
                preview.classList.remove('d-none');
                btnCap.classList.add('d-none');
                btnCamOff.classList.add('d-none');
                btnFoto.classList.remove('d-none');
                btnCam.classList.remove('d-none');
                btnRem.classList.remove('d-none');
            }
            btnCamOff.addEventListener('click', pararCam);
            btnCap.addEventListener('click', () => {
                const canvas = document.createElement('canvas');
                const side = Math.min(video.videoWidth, video.videoHeight);
                canvas.width = canvas.height = side;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(video, (video.videoWidth - side) / 2, (video.videoHeight - side) / 2, side, side, 0, 0, side, side);
                const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
                preview.src = dataUrl;
                b64Inp.value = dataUrl;
                fileInp.value = '';
                pararCam();
            });
        }

        document.addEventListener('DOMContentLoaded', function() {

            /* CEP masks */
            document.querySelectorAll('.vis-cep').forEach(mascaraCep);

            /* CEP blur → autocomplete */
            document.getElementById('vis_cep').addEventListener('blur', function() {
                buscarCep(this.value, {
                    end: 'vis_end',
                    bairro: 'vis_bairro',
                    uf: 'vis_uf',
                    cidade: 'vis_cidade'
                });
            });
            document.getElementById('vis_edit_cep').addEventListener('blur', function() {
                buscarCep(this.value, {
                    end: 'vis_edit_end',
                    bairro: 'vis_edit_bairro',
                    uf: 'vis_edit_uf',
                    cidade: 'vis_edit_cidade'
                });
            });

            /* UF → cidade */
            document.getElementById('vis_uf').addEventListener('change', function() {
                preencherCidades(this.value, document.getElementById('vis_cidade'));
            });
            document.getElementById('vis_edit_uf').addEventListener('change', function() {
                preencherCidades(this.value, document.getElementById('vis_edit_cidade'));
            });

            /* CPF validation */
            const cpfCad = document.getElementById('vis_cpf');
            const cpfFbCad = document.getElementById('vis_cpf_feedback');
            const btnSalvCad = document.getElementById('btnSalvarVisCad');
            cpfCad.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '');
                this.classList.remove('is-invalid');
                btnSalvCad.disabled = false;
            });
            cpfCad.addEventListener('blur', function() {
                if (this.value.length > 0) verificarCpfServidor(this, cpfFbCad, btnSalvCad, null);
            });

            const cpfEdit = document.getElementById('vis_edit_cpf');
            const cpfFbEdit = document.getElementById('vis_edit_cpf_feedback');
            const btnSalvEdt = document.getElementById('btnSalvarVisEdit');
            cpfEdit.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '');
                this.classList.remove('is-invalid');
                btnSalvEdt.disabled = false;
            });
            cpfEdit.addEventListener('blur', function() {
                const id = document.getElementById('vis_edit_id').value;
                if (this.value.length > 0) verificarCpfServidor(this, cpfFbEdit, btnSalvEdt, id);
            });

            /* Foto */
            setupFoto('Cad');
            setupFoto('Edit');

            /* Limpar modal de cadastro ao fechar */
            $('#ModalCadastrarVistoriador').on('hidden.bs.modal', function() {
                document.getElementById('formCadastrarVistoriador').reset();
                const p = document.getElementById('visFotoPreviewCad');
                if (p) p.src = PH_IMG;
                document.getElementById('visFotoB64Cad').value = '';
                cpfCad.classList.remove('is-invalid');
                btnSalvCad.disabled = false;
                document.getElementById('vis_cidade').innerHTML = '<option value="">Selecione</option>';
            });

            /* Editar: clicar no botão */
            $(document).on('click', '.btn-editar-vistoriador', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                fetch(`${ACTION}?acao=obter&id=${id}`)
                    .then(r => r.json())
                    .then(j => {
                        if (!j.success) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erro',
                                text: j.message
                            });
                            return;
                        }
                        const d = j.data;
                        const set = (id, v) => {
                            const el = document.getElementById(id);
                            if (el) el.value = v || '';
                        };

                        set('vis_edit_id', d.VIS_CODIGO_PK);
                        set('vis_edit_nome', d.VIS_NOME);
                        set('vis_edit_cpf', d.VIS_CPF);
                        set('vis_edit_celular', d.VIS_FONE_CELULAR);
                        set('vis_edit_fone', d.VIS_FONE_FIXO);
                        set('vis_edit_email', d.VIS_EMAIL);
                        set('vis_edit_sexo', d.VIS_SEXO);
                        set('vis_edit_ecivil', d.VIS_ESTADO_CIVIL);
                        set('vis_edit_nasc', d.VIS_DATA_NASCIMENTO);
                        set('vis_edit_rg', d.VIS_RG);
                        set('vis_edit_orgao', d.VIS_ORG_EXP);
                        set('vis_edit_status', d.VIS_STATUS || 'ATIVO');
                        /* CEP com máscara */
                        const cepDigits = (d.VIS_CEP || '').replace(/\D/g, '');
                        document.getElementById('vis_edit_cep').value = cepDigits.length === 8 ?
                            cepDigits.slice(0, 5) + '-' + cepDigits.slice(5) : cepDigits;
                        set('vis_edit_end', d.VIS_ENDERECO);
                        set('vis_edit_num', d.VIS_NUMERO);
                        set('vis_edit_bairro', d.VIS_BAIRRO);
                        set('vis_edit_comp', d.VIS_COMPLEMENTO);
                        set('vis_edit_ref', d.VIS_PONTO_REFERENCIA);
                        set('vis_edit_obs', d.VIS_OBSERVACAO);

                        /* UF + cidades */
                        const ufEl = document.getElementById('vis_edit_uf');
                        const cidEl = document.getElementById('vis_edit_cidade');
                        ufEl.value = d.VIS_UF || '';
                        if (d.VIS_UF) {
                            preencherCidades(d.VIS_UF, cidEl);
                            setTimeout(() => cidEl.value = d.VIS_CIDADE || '', 50);
                        }

                        /* Foto */
                        const prev = document.getElementById('visFotoPreviewEdit');
                        if (prev) prev.src = d.VIS_FOTO || PH_IMG;
                        document.getElementById('visFotoB64Edit').value = '';
                        document.getElementById('visFotoFileEdit').value = '';

                        cpfEdit.classList.remove('is-invalid');
                        btnSalvEdt.disabled = false;
                        $('#ModalEditarVistoriador').modal('show');
                    })
                    .catch(() => Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: 'Falha ao carregar dados.'
                    }));
            });

            /* Submit genérico — sem confirmação, sucesso após salvar */
            function postForm(formId) {
                $(formId).on('submit', function(ev) {
                    ev.preventDefault();
                    const form = this;
                    const fd   = new FormData(form);
                    const btn  = form.querySelector('[type="submit"]');
                    const orig = btn ? btn.textContent : '';
                    if (btn) { btn.disabled = true; btn.textContent = 'Salvando...'; }
                    fetch(ACTION, { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(j => {
                            if (j.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Registro salvo com sucesso',
                                    confirmButtonText: 'OK'
                                }).then(() => location.reload());
                            } else {
                                Swal.fire({ icon: 'error', title: 'Erro', text: j.message || 'Não foi possível salvar.' });
                                if (btn) { btn.disabled = false; btn.textContent = orig; }
                            }
                        })
                        .catch(() => {
                            Swal.fire({ icon: 'error', title: 'Erro', text: 'Falha ao comunicar com o servidor.' });
                            if (btn) { btn.disabled = false; btn.textContent = orig; }
                        });
                });
            }
            postForm('#formCadastrarVistoriador');
            postForm('#formEditarVistoriador');

            /* Excluir */
            $(document).on('click', '.btn-excluir-vistoriador', function(ev) {
                ev.preventDefault();
                const id = $(this).data('id');
                const nome = $(this).data('nome') || 'este vistoriador';
                Swal.fire({
                    icon: 'warning',
                    title: 'Excluir vistoriador?',
                    text: `${nome} será removido e não poderá ser recuperado.`,
                    showCancelButton: true,
                    confirmButtonText: 'Sim, excluir',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#c92a2a'
                }).then(res => {
                    if (!res.isConfirmed) return;
                    const fd = new FormData();
                    fd.set('acao', 'excluir');
                    fd.set('id', id);
                    fd.set('csrf', '<?= htmlspecialchars($csrf_vis, ENT_QUOTES, "UTF-8") ?>');
                    fetch(ACTION, {
                            method: 'POST',
                            body: fd
                        })
                        .then(r => r.json())
                        .then(j => {
                            if (j.success) Swal.fire({
                                icon: 'success',
                                title: 'Excluído!',
                                text: j.message
                            }).then(() => location.reload());
                            else Swal.fire({
                                icon: 'error',
                                title: 'Erro',
                                text: j.message
                            });
                        })
                        .catch(() => Swal.fire({
                            icon: 'error',
                            title: 'Erro',
                            text: 'Falha ao comunicar com o servidor.'
                        }));
                });
            });

        }); /* DOMContentLoaded */
    })();
</script>
<script src="<?= APP_URL ?>/associados/carregar_cidades.js"></script>
