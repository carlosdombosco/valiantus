<?php
/**
 * modal_sin.php — Modal de cadastro/edição de sinistro (reutilizável)
 *
 * Variáveis esperadas do contexto de inclusão:
 *   $csrf      — token CSRF (string)
 *   $TIPOS_OCORRENCIA — array de tipos (opcional; se não definido, usa padrão)
 *   $UFS       — array de UFs  (opcional; se não definido, usa padrão)
 *
 * Modo ficha: defina window.SIN_FICHA_MODE = true antes de incluir (via JS inline).
 * Nesse modo, após salvar novo sinistro a página faz reload; em modo sinistros
 * a listagem é recarregada via carregarListagem() se a função existir.
 */
if (!isset($TIPOS_OCORRENCIA)) {
    $TIPOS_OCORRENCIA = ['ROUBO','FURTO','COLISÃO','INCÊNDIO','ALAGAMENTO','QUEBRA DE VIDRO',
                         'DANO ELÉTRICO','ACIDENTE NATURAL','TERCEIROS','OUTROS'];
}
if (!isset($UFS)) {
    $UFS = ['AC','AL','AM','AP','BA','CE','DF','ES','GO','MA','MG','MS','MT',
            'PA','PB','PE','PI','PR','RJ','RN','RO','RR','RS','SC','SE','SP','TO'];
}
if (!isset($csrf)) {
    require_once PATH_INC . '/csrf.php';
    $csrf = csrf_token();
}
?>

<style>
/* ── Modal sinistro ── */
#modalSinistro .modal-dialog { max-width:1150px; }
#modalSinistro .modal-content { border:none; border-radius:18px; overflow:hidden; box-shadow:0 20px 60px rgba(22,28,45,.18); }
#modalSinistro .modal-header { background:linear-gradient(135deg,#c92a2a 0%,#a61e1e 100%); color:#fff; border-bottom:none; padding:18px 22px; }
#modalSinistro .modal-title { font-size:17px; font-weight:700; margin:0; display:flex; align-items:center; gap:10px; }
#modalSinistro .modal-title i { width:36px; height:36px; border-radius:10px; background:rgba(255,255,255,.15); display:inline-flex; align-items:center; justify-content:center; font-size:14px; }
#modalSinistro .close { color:#fff; opacity:1; background:rgba(255,255,255,.15); border:none; width:34px; height:34px; border-radius:10px; }
#modalSinistro .modal-body { background:#f8fafc; padding:20px; }
#modalSinistro .modal-footer { background:#fff; border-top:1px solid #e9ecef; padding:14px 20px; gap:8px; }
/* Abas */
#modalSinistro .nav-tabs { border-bottom:2px solid #e9ecef; margin-bottom:18px; }
#modalSinistro .nav-tabs .nav-link { border:none; color:#868e96; font-weight:600; font-size:13px; padding:10px 16px; border-radius:10px 10px 0 0; }
#modalSinistro .nav-tabs .nav-link.active { color:#c92a2a; background:#fff3f3; border-bottom:2px solid #c92a2a; }
#modalSinistro .nav-tabs .nav-link:hover:not(.active) { background:#f8f9fb; color:#495057; }
/* Blocos */
#modalSinistro .sin-section { background:#fff; border:1px solid #e9ecef; border-radius:14px; padding:16px; margin-bottom:14px; }
#modalSinistro .sin-section-title { font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.06em; color:#c92a2a; margin-bottom:12px; display:flex; align-items:center; gap:8px; }
#modalSinistro .sin-section-title i { width:26px; height:26px; border-radius:8px; background:#ffe3e3; display:inline-flex; align-items:center; justify-content:center; font-size:11px; }
#modalSinistro label { font-size:12px; font-weight:700; color:#495057; margin-bottom:5px; display:block; }
#modalSinistro .form-control, #modalSinistro select, #modalSinistro textarea { border:1.5px solid #dbe2ea; border-radius:9px; font-size:13px; padding:8px 11px; }
#modalSinistro .form-control:focus, #modalSinistro select:focus, #modalSinistro textarea:focus { border-color:#c92a2a; box-shadow:0 0 0 3px rgba(201,42,42,.1); outline:none; }
#modalSinistro textarea { resize:vertical; min-height:80px; }
#modalSinistro .row>[class*="col-"] { margin-bottom:10px; }
/* Seletor de veículo (ficha mode) */
#sinVeiculoSelect { border:1.5px solid #dbe2ea; border-radius:10px; padding:10px 12px; font-size:13.5px; width:100%; background:#fff; color:#1a1d2e; }
#sinVeiculoSelect:focus { border-color:#c92a2a; box-shadow:0 0 0 3px rgba(201,42,42,.1); outline:none; }
/* Upload */
.sin-upload-zone { border:2px dashed #dbe2ea; border-radius:12px; padding:24px; text-align:center; cursor:pointer; transition:.2s; background:#fafbfc; }
.sin-upload-zone:hover { border-color:#3b5bdb; background:#f0f3ff; }
.sin-upload-zone i { font-size:28px; color:#adb5bd; margin-bottom:8px; }
.sin-upload-zone p { color:#868e96; font-size:13px; margin:0; }
.sin-img-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(130px,1fr)); gap:10px; margin-top:12px; }
.sin-img-item { position:relative; border-radius:10px; overflow:hidden; aspect-ratio:4/3; background:#e9ecef; }
.sin-img-item img { width:100%; height:100%; object-fit:cover; }
.sin-img-del { position:absolute; top:4px; right:4px; background:rgba(201,42,42,.85); color:#fff; border:none; width:24px; height:24px; border-radius:50%; font-size:11px; cursor:pointer; display:flex; align-items:center; justify-content:center; }
.sin-img-del:hover { background:#c92a2a; }
/* WhatsApp modal */
#modalWhatsApp .modal-dialog { max-width:540px; }
</style>

<!-- ══════════ MODAL: Sinistro ══════════ -->
<div class="modal fade" id="modalSinistro" tabindex="-1" aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa-solid fa-car-burst"></i>
                    <span id="sinModalTitle">Novo Sinistro</span>
                </h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>

            <div class="modal-body">
                <!-- Campos ocultos -->
                <input type="hidden" id="sinId">
                <input type="hidden" id="sinVeiculoId">
                <input type="hidden" id="sinPessoaId">
                <input type="hidden" id="sinContratoId">
                <input type="hidden" id="sinFoneAssoc">

                <!-- Seletor de veículo (só aparece no modo ficha) -->
                <div id="sinVeiculoSelectorWrap" style="display:none;margin-bottom:14px;">
                    <div class="sin-section" style="background:#f0f3ff;border-color:#c5d0ff;margin-bottom:0;">
                        <div class="sin-section-title" style="color:#3b5bdb;">
                            <i class="fa-solid fa-car" style="background:#dbe4ff;"></i>
                            Selecione o Veículo
                        </div>
                        <select id="sinVeiculoSelect">
                            <option value="">— Selecione um veículo —</option>
                        </select>
                    </div>
                </div>

                <!-- Info do sinistro (readonly) -->
                <div class="sin-section" style="background:#fff8e1;border-color:#ffe082;">
                    <div class="row">
                        <div class="col-md-2"><label>Cód.</label>
                            <input type="text" class="form-control form-control-sm bg-light" id="sinCodDisplay" readonly></div>
                        <div class="col-md-4"><label>Data Lanç.</label>
                            <input type="text" class="form-control form-control-sm bg-light" id="sinDataLanc" readonly></div>
                        <div class="col-md-3"><label>Placa</label>
                            <input type="text" class="form-control form-control-sm bg-light" id="sinPlacaDisplay" readonly></div>
                        <div class="col-md-3"><label>Modelo</label>
                            <input type="text" class="form-control form-control-sm bg-light" id="sinModeloDisplay" readonly></div>
                    </div>
                    <div class="row" style="margin-top:0;">
                        <div class="col-md-2"><label>ID Assoc.</label>
                            <input type="text" class="form-control form-control-sm bg-light" id="sinIdAssocDisplay" readonly></div>
                        <div class="col-md-6"><label>Associado</label>
                            <input type="text" class="form-control form-control-sm bg-light" id="sinAssocDisplay" readonly></div>
                        <div class="col-md-4"><label>CPF / CNPJ</label>
                            <input type="text" class="form-control form-control-sm bg-light" id="sinCpfDisplay" readonly></div>
                    </div>
                </div>

                <!-- Abas -->
                <ul class="nav nav-tabs" id="sinTabs">
                    <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tabOcorrencia"><i class="fa-solid fa-file-alt mr-1"></i>Ocorrência</a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tabImgAntes"><i class="fa-solid fa-images mr-1"></i>Imagens Sinistro</a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tabImgDepois"><i class="fa-solid fa-image mr-1"></i>Imagens Pós Sinistro</a></li>
                </ul>

                <div class="tab-content">
                    <!-- ABA OCORRÊNCIA -->
                    <div class="tab-pane fade show active" id="tabOcorrencia">
                        <div class="sin-section">
                            <div class="sin-section-title"><i class="fa-solid fa-triangle-exclamation"></i>Dados da Ocorrência</div>
                            <div class="row">
                                <div class="col-md-4"><label>Tipo Ocorrência <span class="text-danger">*</span></label>
                                    <select class="form-control" id="sinTipo">
                                        <option value="">Selecione...</option>
                                        <?php foreach ($TIPOS_OCORRENCIA as $t): ?>
                                            <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                                        <?php endforeach; ?>
                                    </select></div>
                                <div class="col-md-3"><label>Data Ocorrência</label>
                                    <input type="date" class="form-control" id="sinDataOcorr"></div>
                                <div class="col-md-2"><label>Hora</label>
                                    <input type="time" class="form-control" id="sinHoraOcorr"></div>
                                <div class="col-md-3"><label>Valor FIPE</label>
                                    <input type="text" class="form-control" id="sinValorFipe" placeholder="R$ 0,00"></div>
                            </div>
                            <div class="row">
                                <div class="col-md-3"><label>Precisa Reboque</label>
                                    <select class="form-control" id="sinReboque"><option value="N">NÃO</option><option value="S">SIM</option></select></div>
                                <div class="col-md-3"><label>Houve Vítimas</label>
                                    <select class="form-control" id="sinVitimas"><option value="N">NÃO</option><option value="S">SIM</option></select></div>
                                <div class="col-md-2"><label>Nº Sinistros Ant.</label>
                                    <input type="number" min="0" class="form-control" id="sinNumAnt" value="0"></div>
                                <div class="col-md-2"><label>Franquia (%)</label>
                                    <input type="text" class="form-control" id="sinFranqPerc" placeholder="0,00"></div>
                                <div class="col-md-2"><label>Valor Franquia</label>
                                    <input type="text" class="form-control" id="sinValorFranq" placeholder="R$ 0,00"></div>
                            </div>
                        </div>

                        <div class="sin-section">
                            <div class="sin-section-title"><i class="fa-solid fa-gavel"></i>Boletim de Ocorrência</div>
                            <div class="row">
                                <div class="col-md-4"><label>Nº B.O</label>
                                    <input type="text" class="form-control form-control-sm" id="sinNumBO" placeholder="Ex: 19e0185000267"></div>
                                <div class="col-md-3"><label>Data B.O</label>
                                    <input type="date" class="form-control form-control-sm" id="sinDataBO"></div>
                                <div class="col-md-2"><label>Hora B.O</label>
                                    <input type="time" class="form-control form-control-sm" id="sinHoraBO"></div>
                                <div class="col-md-3"><label>Órgão Competência</label>
                                    <input type="text" class="form-control form-control-sm" id="sinOrgao" placeholder="Ex: SDS-PE"></div>
                            </div>
                        </div>

                        <div class="sin-section">
                            <div class="sin-section-title"><i class="fa-solid fa-id-card"></i>Condutor no Momento</div>
                            <div class="row">
                                <div class="col-md-4"><label>Nome Condutor</label>
                                    <input type="text" class="form-control" id="sinNomeCondutor"></div>
                                <div class="col-md-2"><label>Data Nasc.</label>
                                    <input type="date" class="form-control" id="sinDataNasc"></div>
                                <div class="col-md-2"><label>Sexo</label>
                                    <select class="form-control" id="sinSexo">
                                        <option value="">—</option>
                                        <option value="M">Masculino</option>
                                        <option value="F">Feminino</option>
                                        <option value="O">Outro</option>
                                    </select></div>
                                <div class="col-md-2"><label>CNH</label>
                                    <input type="text" class="form-control" id="sinCNH"></div>
                                <div class="col-md-2"><label>Validade CNH</label>
                                    <input type="date" class="form-control" id="sinValidCNH"></div>
                            </div>
                        </div>

                        <div class="sin-section">
                            <div class="sin-section-title"><i class="fa-solid fa-location-dot"></i>Local da Ocorrência</div>
                            <div class="row">
                                <div class="col-md-2"><label>UF</label>
                                    <select class="form-control" id="sinUF">
                                        <option value="">— UF —</option>
                                        <?php foreach ($UFS as $uf): ?>
                                            <option value="<?= $uf ?>"><?= $uf ?></option>
                                        <?php endforeach; ?>
                                    </select></div>
                                <div class="col-md-4"><label>Cidade</label>
                                    <select class="form-control" id="sinCidade" disabled>
                                        <option value="">Selecione a UF primeiro</option>
                                    </select></div>
                                <div class="col-md-3"><label>Bairro</label>
                                    <input type="text" class="form-control" id="sinBairro"></div>
                                <div class="col-md-3"><label>Ponto de Referência</label>
                                    <input type="text" class="form-control" id="sinPontoRef"></div>
                            </div>
                        </div>

                        <div class="sin-section">
                            <div class="sin-section-title"><i class="fa-solid fa-align-left"></i>Narrativa</div>
                            <div class="row">
                                <div class="col-md-12"><label>Detalhe do Sinistro</label>
                                    <textarea class="form-control form-control-sm" id="sinDetalhe" rows="4"
                                              placeholder="Descreva o sinistro com detalhes..."></textarea></div>
                                <div class="col-md-12"><label>Danos ao Veículo</label>
                                    <textarea class="form-control form-control-sm" id="sinDanos" rows="3"
                                              placeholder="Descreva os danos ao veículo..."></textarea></div>
                            </div>
                        </div>
                    </div><!-- /tabOcorrencia -->

                    <!-- ABA IMAGENS ANTES -->
                    <div class="tab-pane fade" id="tabImgAntes">
                        <div class="sin-section">
                            <div class="sin-section-title"><i class="fa-solid fa-images"></i>Fotos do Sinistro (antes/durante)</div>
                            <label id="uploadZoneAntes" class="sin-upload-zone" for="inputImgAntes" style="display:block;">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <p>Clique ou arraste as fotos aqui<br><small style="color:#adb5bd;">JPG, PNG, WEBP — máx. 10MB cada</small></p>
                            </label>
                            <input type="file" id="inputImgAntes" accept="image/*" multiple style="display:none;">
                            <div id="gridAntes" class="sin-img-grid"></div>
                        </div>
                        <div id="alertSaveFirstAntes" class="alert alert-warning mt-2" style="display:none;font-size:13px;">
                            <i class="fa-solid fa-circle-info mr-1"></i>Salve o sinistro primeiro para adicionar imagens.
                        </div>
                    </div>

                    <!-- ABA IMAGENS DEPOIS -->
                    <div class="tab-pane fade" id="tabImgDepois">
                        <div class="sin-section">
                            <div class="sin-section-title"><i class="fa-solid fa-image"></i>Fotos Pós Sinistro (depois/reparo)</div>
                            <label id="uploadZoneDepois" class="sin-upload-zone" for="inputImgDepois" style="display:block;">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <p>Clique ou arraste as fotos aqui<br><small style="color:#adb5bd;">JPG, PNG, WEBP — máx. 10MB cada</small></p>
                            </label>
                            <input type="file" id="inputImgDepois" accept="image/*" multiple style="display:none;">
                            <div id="gridDepois" class="sin-img-grid"></div>
                        </div>
                        <div id="alertSaveFirstDepois" class="alert alert-warning mt-2" style="display:none;font-size:13px;">
                            <i class="fa-solid fa-circle-info mr-1"></i>Salve o sinistro primeiro para adicionar imagens.
                        </div>
                    </div>
                </div><!-- /tab-content -->
            </div><!-- /modal-body -->

            <div class="modal-footer" style="flex-wrap:wrap;">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fa-solid fa-xmark mr-1"></i>Fechar
                </button>
                <button type="button" id="sinBtnWhatsApp" class="btn" disabled
                        style="background:#25d366;color:#fff;border:none;">
                    <i class="fa-brands fa-whatsapp mr-1"></i><span>Enviar WhatsApp</span>
                </button>
                <button type="button" id="sinBtnSalvar" class="btn btn-danger">
                    <i class="fa-solid fa-floppy-disk mr-1"></i>
                    <span id="sinBtnSalvarTxt">Salvar Sinistro</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════ MODAL: WhatsApp ══════════ -->
<div class="modal fade" id="modalWhatsApp" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:14px;overflow:hidden;">
            <div class="modal-header" style="background:#25d366;color:#fff;border-bottom:none;">
                <h5 class="modal-title" style="font-weight:700;">
                    <i class="fa-brands fa-whatsapp mr-2"></i>Enviar mensagem WhatsApp
                </h5>
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:1;"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group mb-2">
                    <label style="font-size:12px;font-weight:700;">Telefone do associado</label>
                    <input type="text" class="form-control form-control-sm" id="waFone" placeholder="5581999999999">
                    <small class="text-muted">Formato: 55 + DDD + número (sem espaços)</small>
                </div>
                <div class="form-group mb-0">
                    <label style="font-size:12px;font-weight:700;">Mensagem (editável)</label>
                    <textarea class="form-control" id="waMensagem" rows="7" style="font-size:13px;border-radius:9px;"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" id="waBtnEnviar" class="btn" style="background:#25d366;color:#fff;border:none;font-weight:700;">
                    <i class="fa-brands fa-whatsapp mr-1"></i>Abrir WhatsApp
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?= APP_URL ?>/associados/carregar_cidades.js"></script>

<!-- ══════════ JS COMPARTILHADO ══════════ -->
<script>
(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {

    var SIN_ACTION = '<?= ACTION_URL ?>/sinistros.php';
    var CSRF       = '<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>';

    var vSwal = Swal.mixin({
        showClass: { popup: 'vt-swal-in' },
        hideClass: { popup: 'vt-swal-out' },
        customClass: { popup: 'vt-swal-popup' }
    });

    /* ── Seletor de veículo (modo ficha) ── */
    document.getElementById('sinVeiculoSelect').addEventListener('change', function () {
        var idx = this.selectedIndex;
        if (idx <= 0) return;
        var opt = this.options[idx];
        preencherComVeiculo({
            veiId  : opt.dataset.veiId   || '',
            ctrId  : opt.dataset.ctrId   || '',
            pesId  : opt.dataset.pesId   || '',
            placa  : opt.dataset.placa   || '',
            modelo : opt.dataset.modelo  || '',
            assoc  : opt.dataset.assoc   || '',
            cpf    : opt.dataset.cpf     || '',
            fone   : opt.dataset.fone    || '',
            fipe   : opt.dataset.fipe    || ''
        });
    });

    function preencherComVeiculo(v) {
        $('#sinVeiculoId').val(v.veiId);
        $('#sinPessoaId').val(v.pesId);
        $('#sinContratoId').val(v.ctrId);
        $('#sinFoneAssoc').val(v.fone);
        $('#sinPlacaDisplay').val(v.placa);
        $('#sinModeloDisplay').val(v.modelo);
        $('#sinIdAssocDisplay').val(v.pesId ? '#' + v.pesId : '');
        $('#sinAssocDisplay').val(v.assoc);
        $('#sinCpfDisplay').val(v.cpf);
        var fipe = parseFloat(v.fipe || '0');
        $('#sinValorFipe').val(fipe ? 'R$ ' + fipe.toFixed(2).replace('.', ',') : '');
        $('#sinNomeCondutor').val(v.assoc);
    }

    /* ── API pública para abrir o modal externamente ── */
    window.sinAbrirNovo = function (dados) {
        resetModal();

        if (dados) {
            // Modo com veículo único: preenche direto
            if (dados.veiId) {
                preencherComVeiculo(dados);
                $('#sinVeiculoSelectorWrap').hide();
            } else if (dados.veiculos && dados.veiculos.length) {
                // Múltiplos veículos: popula o seletor
                var $sel = $('#sinVeiculoSelect').empty().append('<option value="">— Selecione um veículo —</option>');
                dados.veiculos.forEach(function (v) {
                    var $opt = $('<option>').val(v.veiId).text(v.placa + ' — ' + v.modelo);
                    $opt.attr({
                        'data-vei-id' : v.veiId,
                        'data-ctr-id' : v.ctrId  || '',
                        'data-pes-id' : v.pesId,
                        'data-placa'  : v.placa,
                        'data-modelo' : v.modelo,
                        'data-assoc'  : v.assoc,
                        'data-cpf'    : v.cpf,
                        'data-fone'   : v.fone   || '',
                        'data-fipe'   : v.fipe   || ''
                    });
                    $sel.append($opt);
                });
                // Preenche associado (sem veículo ainda)
                if (dados.pesId) {
                    $('#sinPessoaId').val(dados.pesId);
                    $('#sinIdAssocDisplay').val('#' + dados.pesId);
                    $('#sinAssocDisplay').val(dados.assoc || '');
                    $('#sinCpfDisplay').val(dados.cpf || '');
                    $('#sinFoneAssoc').val(dados.fone || '');
                    $('#sinNomeCondutor').val(dados.assoc || '');
                }
                $('#sinVeiculoSelectorWrap').show();
            }
        }

        $('#sinDataLanc').val(new Date().toLocaleString('pt-BR'));
        $('#sinCodDisplay').val('(novo)');
        toggleUploadZones(false);
        $('#modalSinistro').modal('show');
    };

    /* ── Abrir a partir de um card .sin-veiculo-card ── */
    window.sinAbrirNovoPorCard = function ($card) {
        resetModal();
        preencherComVeiculo({
            veiId  : $card.data('vei-id')  || '',
            ctrId  : $card.data('ctr-id')  || '',
            pesId  : $card.data('pes-id')  || '',
            placa  : $card.data('placa')   || '',
            modelo : $card.data('modelo')  || '',
            assoc  : $card.data('assoc')   || '',
            cpf    : $card.data('cpf')     || '',
            fone   : $card.data('fone')    || '',
            fipe   : $card.data('fipe')    || ''
        });
        $('#sinVeiculoSelectorWrap').hide();
        $('#sinDataLanc').val(new Date().toLocaleString('pt-BR'));
        $('#sinCodDisplay').val('(novo)');
        toggleUploadZones(false);
        $('#modalSinistro').modal('show');
    };

    /* ── Abrir modal em modo edição ── */
    window.sinAbrirEditar = function (sinId) {
        fetch(SIN_ACTION + '?acao=obter&id=' + encodeURIComponent(sinId))
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j.success) { vSwal.fire('Erro', j.message || 'Não foi possível carregar.', 'error'); return; }
                preencherModal(j.data);
                $('#sinVeiculoSelectorWrap').hide();
                $('#modalSinistro').modal('show');
            })
            .catch(function () { vSwal.fire('Erro', 'Falha ao comunicar com o servidor.', 'error'); });
    };

    /* Delegação para botões .btn-novo-sin e .btn-editar-sin */
    $(document).on('click', '.btn-novo-sin', function (e) {
        e.stopPropagation();
        window.sinAbrirNovoPorCard($(this).closest('.sin-veiculo-card'));
    });
    $(document).on('click', '.btn-editar-sin', function (e) {
        e.stopPropagation();
        window.sinAbrirEditar($(this).data('sin-id'));
    });

    /* ── resetModal ── */
    function resetModal() {
        $('#sinId, #sinVeiculoId, #sinPessoaId, #sinContratoId, #sinFoneAssoc').val('');
        $('#sinCodDisplay, #sinDataLanc, #sinPlacaDisplay, #sinModeloDisplay').val('');
        $('#sinIdAssocDisplay, #sinAssocDisplay, #sinCpfDisplay').val('');
        $('#sinTipo').val('');
        $('#sinDataOcorr, #sinHoraOcorr').val('');
        $('#sinReboque, #sinVitimas').val('N');
        $('#sinNumBO, #sinDataBO, #sinHoraBO, #sinOrgao').val('');
        $('#sinValorFipe, #sinNumAnt').val('0');
        $('#sinFranqPerc, #sinValorFranq').val('');
        $('#sinNomeCondutor, #sinDataNasc').val('');
        $('#sinSexo').val('');
        $('#sinCNH, #sinValidCNH').val('');
        $('#sinBairro, #sinPontoRef').val('');
        $('#sinUF').val('');
        $('#sinCidade').prop('disabled', true).empty().append('<option value="">Selecione a UF primeiro</option>');
        $('#sinDetalhe, #sinDanos').val('');
        $('#gridAntes, #gridDepois').empty();
        $('#sinBtnWhatsApp').prop('disabled', true);
        $('#sinModalTitle').text('Novo Sinistro');
        $('#sinBtnSalvarTxt').text('Salvar Sinistro');
        $('#sinVeiculoSelect').prop('selectedIndex', 0);
        $('#sinVeiculoSelectorWrap').hide();
        $('#sinTabs a[href="#tabOcorrencia"]').tab('show');
    }

    /* ── preencherModal (edição) ── */
    function preencherModal(d) {
        resetModal();
        $('#sinId').val(d.SIN_CODIGO_PK);
        $('#sinVeiculoId').val(d.VEI_CODIGO_FK);
        $('#sinPessoaId').val(d.PES_CODIGO_FK);
        $('#sinContratoId').val(d.CTR_CODIGO_FK || '');
        $('#sinFoneAssoc').val(d.PES_FONE_CELULAR_1 || '');
        $('#sinCodDisplay').val('#' + d.SIN_CODIGO_PK);
        $('#sinDataLanc').val(d.SIN_DATA_LANCAMENTO || '');
        $('#sinPlacaDisplay').val(d.VEI_PLACA || '');
        $('#sinModeloDisplay').val((d.VEI_MARCA || '') + ' ' + (d.VEI_MODELO || ''));
        $('#sinIdAssocDisplay').val('#' + d.PES_CODIGO_FK);
        $('#sinAssocDisplay').val(d.PES_NOME || '');
        $('#sinCpfDisplay').val(d.PES_CPF_CNPJ || '');
        $('#sinTipo').val(d.SIN_TIPO_OCORRENCIA || '');
        $('#sinDataOcorr').val(d.SIN_DATA_OCORRENCIA || '');
        $('#sinHoraOcorr').val(d.SIN_HORA_OCORRENCIA || '');
        $('#sinReboque').val(d.SIN_PRECISA_REBOQUE || 'N');
        $('#sinVitimas').val(d.SIN_HOUVE_VITIMAS || 'N');
        $('#sinNumBO').val(d.SIN_NUM_BO || '');
        $('#sinDataBO').val(d.SIN_DATA_BO || '');
        $('#sinHoraBO').val(d.SIN_HORA_BO || '');
        $('#sinOrgao').val(d.SIN_ORGAO_COMPETENCIA || '');
        var fipe = parseFloat(d.SIN_VALOR_FIPE || '0');
        $('#sinValorFipe').val(fipe ? 'R$ ' + fipe.toFixed(2).replace('.', ',') : '');
        $('#sinNumAnt').val(d.SIN_NUM_SINISTROS_ANT || 0);
        $('#sinFranqPerc').val(d.SIN_FRANQUIA_PERC || '');
        var franq = parseFloat(d.SIN_VALOR_FRANQUIA || '0');
        $('#sinValorFranq').val(franq ? 'R$ ' + franq.toFixed(2).replace('.', ',') : '');
        $('#sinNomeCondutor').val(d.SIN_NOME_CONDUTOR || '');
        $('#sinDataNasc').val(d.SIN_DATA_NASC_CONDUTOR || '');
        $('#sinSexo').val(d.SIN_SEXO_CONDUTOR || '');
        $('#sinCNH').val(d.SIN_CNH_CONDUTOR || '');
        $('#sinValidCNH').val(d.SIN_VALIDADE_CNH || '');
        $('#sinBairro').val(d.SIN_BAIRRO_OCORRENCIA || '');
        $('#sinPontoRef').val(d.SIN_PONTO_REFERENCIA || '');
        $('#sinUF').val(d.SIN_UF_OCORRENCIA || '');
        carregarCidadesSin(d.SIN_UF_OCORRENCIA || '', d.SIN_CIDADE_OCORRENCIA || '');
        $('#sinDetalhe').val(d.SIN_DETALHE || '');
        $('#sinDanos').val(d.SIN_DANOS_VEICULO || '');
        toggleUploadZones(true);
        renderImagens(d.imagens || []);
        $('#sinBtnWhatsApp').prop('disabled', false);
        $('#sinModalTitle').text('Sinistro #' + d.SIN_CODIGO_PK);
        $('#sinBtnSalvarTxt').text('Atualizar Sinistro');
    }

    /* ── Salvar ── */
    $('#sinBtnSalvar').on('click', function () {
        var tipo      = $('#sinTipo').val();
        var veiculoId = $('#sinVeiculoId').val();
        var pessoaId  = $('#sinPessoaId').val();

        if (!tipo) {
            vSwal.fire('Atenção', 'Selecione o tipo de ocorrência.', 'warning');
            $('#sinTabs a[href="#tabOcorrencia"]').tab('show');
            $('#sinTipo').focus();
            return;
        }
        if (!veiculoId) {
            vSwal.fire('Atenção', 'Selecione o veículo antes de salvar.', 'warning');
            return;
        }

        var btn  = this;
        btn.disabled = true;
        var orig = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i>Salvando...';

        var sinId = $('#sinId').val();
        var isNovo = !sinId;

        var fd = new FormData();
        fd.append('acao',               isNovo ? 'cadastrar' : 'atualizar');
        fd.append('csrf',               CSRF);
        if (!isNovo) fd.append('sin_id', sinId);
        fd.append('veiculo_id',         veiculoId);
        fd.append('pessoa_id',          pessoaId);
        fd.append('contrato_id',        $('#sinContratoId').val());
        fd.append('tipo_ocorrencia',    tipo);
        fd.append('data_ocorrencia',    $('#sinDataOcorr').val());
        fd.append('hora_ocorrencia',    $('#sinHoraOcorr').val());
        fd.append('precisa_reboque',    $('#sinReboque').val());
        fd.append('houve_vitimas',      $('#sinVitimas').val());
        fd.append('num_bo',             $('#sinNumBO').val());
        fd.append('data_bo',            $('#sinDataBO').val());
        fd.append('hora_bo',            $('#sinHoraBO').val());
        fd.append('orgao_competencia',  $('#sinOrgao').val());
        fd.append('valor_fipe',         $('#sinValorFipe').val());
        fd.append('num_sinistros_ant',  $('#sinNumAnt').val());
        fd.append('franquia_perc',      $('#sinFranqPerc').val());
        fd.append('valor_franquia',     $('#sinValorFranq').val());
        fd.append('nome_condutor',      $('#sinNomeCondutor').val());
        fd.append('data_nasc_condutor', $('#sinDataNasc').val());
        fd.append('sexo_condutor',      $('#sinSexo').val());
        fd.append('cnh_condutor',       $('#sinCNH').val());
        fd.append('validade_cnh',       $('#sinValidCNH').val());
        fd.append('bairro_ocorrencia',  $('#sinBairro').val());
        fd.append('ponto_referencia',   $('#sinPontoRef').val());
        fd.append('cidade_ocorrencia',  $('#sinCidade').val() || '');
        fd.append('uf_ocorrencia',      $('#sinUF').val());
        fd.append('detalhe',            $('#sinDetalhe').val());
        fd.append('danos_veiculo',      $('#sinDanos').val());

        fetch(SIN_ACTION, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (j.success) {
                    if (isNovo) {
                        var novoId = j.sin_id;
                        $('#sinId').val(novoId);
                        $('#sinCodDisplay').val('#' + novoId);
                        toggleUploadZones(true);
                        $('#sinBtnWhatsApp').prop('disabled', false);
                        $('#sinBtnSalvarTxt').text('Atualizar Sinistro');
                        $('#sinModalTitle').text('Sinistro #' + novoId);
                        $('#sinTabs a[href="#tabImgAntes"]').tab('show');
                        // Notifica a listagem pai (se existir)
                        if (typeof window.sinOnSalvo === 'function') window.sinOnSalvo(novoId, true);
                        setTimeout(function () {
                            vSwal.fire({
                                icon: 'success', title: 'Sinistro salvo!',
                                text: 'Adicione as fotos nas abas acima ou feche o modal.',
                                toast: true, position: 'bottom-end',
                                timer: 3500, timerProgressBar: true,
                                showConfirmButton: false, returnFocus: false
                            });
                        }, 150);
                    } else {
                        var msg = j.message;
                        $('#modalSinistro').modal('hide');
                        if (typeof window.sinOnSalvo === 'function') window.sinOnSalvo(sinId, false);
                        $('#modalSinistro').one('hidden.bs.modal', function () {
                            vSwal.fire({ icon: 'success', title: 'Sinistro atualizado!', text: msg, confirmButtonColor: '#3b5bdb' });
                        });
                    }
                } else {
                    vSwal.fire({ icon: 'error', title: 'Erro', text: j.message || 'Não foi possível salvar.' });
                }
            })
            .catch(function () {
                vSwal.fire({ icon: 'error', title: 'Erro', text: 'Falha ao comunicar com o servidor.' });
            })
            .finally(function () {
                btn.disabled = false;
                btn.innerHTML = orig;
            });
    });

    /* ── Upload de imagens ── */
    function toggleUploadZones(enabled) {
        var opacity = enabled ? '1' : '.45';
        var cursor  = enabled ? 'pointer' : 'not-allowed';
        var pEvents = enabled ? '' : 'none';
        ['#uploadZoneAntes', '#uploadZoneDepois'].forEach(function (sel) {
            $(sel).css({ opacity: opacity, cursor: cursor, pointerEvents: pEvents });
        });
        $('#inputImgAntes, #inputImgDepois').prop('disabled', !enabled);
        $('#alertSaveFirstAntes, #alertSaveFirstDepois').toggle(!enabled);
    }
    toggleUploadZones(false);

    function setupUploadZone(zoneId, inputId, tipo) {
        var $zone  = $(zoneId);
        var $input = $(inputId);
        $zone.on('dragover dragenter', function (e) {
            e.preventDefault();
            if (!$input.prop('disabled')) $(this).addClass('active');
        }).on('dragleave drop', function (e) {
            e.preventDefault();
            $(this).removeClass('active');
            if (e.type === 'drop' && !$input.prop('disabled')) {
                uploadFiles(e.originalEvent.dataTransfer.files, tipo);
            }
        });
        $input.on('change', function () {
            uploadFiles(this.files, tipo);
            this.value = '';
        });
    }
    setupUploadZone('#uploadZoneAntes',  '#inputImgAntes',  'ANTES');
    setupUploadZone('#uploadZoneDepois', '#inputImgDepois', 'DEPOIS');

    function uploadFiles(files, tipo) {
        var sinId = $('#sinId').val();
        if (!sinId) { vSwal.fire('Atenção', 'Salve o sinistro primeiro.', 'warning'); return; }
        Array.from(files).forEach(function (file) {
            var $placeholder = addImgPlaceholder(tipo, file);
            var fd = new FormData();
            fd.append('acao',   'upload_imagem');
            fd.append('csrf',   CSRF);
            fd.append('sin_id', sinId);
            fd.append('tipo',   tipo);
            fd.append('imagem', file);
            fetch(SIN_ACTION, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (j.success) $placeholder.replaceWith(buildImgCard(j.id, j.url, tipo));
                    else { $placeholder.remove(); vSwal.fire('Erro', j.message || 'Falha no upload.', 'error'); }
                })
                .catch(function () { $placeholder.remove(); });
        });
    }

    function addImgPlaceholder(tipo, file) {
        var $grid = tipo === 'ANTES' ? $('#gridAntes') : $('#gridDepois');
        var url   = URL.createObjectURL(file);
        var $el   = $('<div class="sin-img-item" style="opacity:.5;">').html(
            '<img src="' + url + '"><div class="sin-img-del" style="background:#adb5bd;"><i class="fa-solid fa-spinner fa-spin"></i></div>'
        );
        $grid.append($el);
        return $el;
    }

    function buildImgCard(imgId, url, tipo) {
        var $el  = $('<div class="sin-img-item">');
        var $img = $('<img loading="lazy">').attr('src', url);
        var btn  = document.createElement('button');
        btn.type      = 'button';
        btn.className = 'sin-img-del';
        btn.title     = 'Excluir';
        btn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
        btn.addEventListener('click', function (e) {
            e.stopPropagation(); e.preventDefault();
            if (btn.disabled) return;
            btn.disabled  = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
            var fd = new FormData();
            fd.append('acao',   'excluir_imagem');
            fd.append('csrf',   CSRF);
            fd.append('img_id', imgId);
            fetch(SIN_ACTION, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (j.success) $el.fadeOut(200, function () { $el.remove(); });
                    else { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-xmark"></i>'; }
                })
                .catch(function () { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-xmark"></i>'; });
        });
        $el.append($img).append(btn);
        return $el;
    }

    function renderImagens(imagens) {
        $('#gridAntes, #gridDepois').empty();
        imagens.forEach(function (img) {
            var grid = img.SIM_TIPO === 'ANTES' ? '#gridAntes' : '#gridDepois';
            $(grid).append(buildImgCard(img.SIM_CODIGO_PK, img.SIM_CAMINHO, img.SIM_TIPO));
        });
    }

    /* ── WhatsApp ── */
    $('#sinBtnWhatsApp').on('click', function () {
        var sinId  = $('#sinId').val();
        var placa  = $('#sinPlacaDisplay').val();
        var assoc  = $('#sinAssocDisplay').val();
        var tipo   = $('#sinTipo').val();
        var data   = $('#sinDataOcorr').val();
        var fone   = $('#sinFoneAssoc').val().replace(/\D/g, '');
        var waFone = fone.startsWith('55') ? fone : '55' + fone;
        var msg    = 'Olá ' + assoc + ', informamos que o sinistro do seu veículo de placa *' + placa + '* foi registrado em nosso sistema.\n\n' +
                     '*Código:* #' + sinId + '\n*Tipo:* ' + (tipo || '—') + '\n*Data:* ' + (data || '—') + '\n\nEm caso de dúvidas, entre em contato conosco. 🚗';
        $('#waFone').val(waFone);
        $('#waMensagem').val(msg);
        $('#modalWhatsApp').modal('show');
    });

    $('#waBtnEnviar').on('click', function () {
        var fone = $('#waFone').val().replace(/\D/g, '');
        var msg  = $('#waMensagem').val().trim();
        if (!fone) { vSwal.fire('Atenção', 'Informe o telefone.', 'warning'); return; }
        if (!msg)  { vSwal.fire('Atenção', 'A mensagem não pode estar vazia.', 'warning'); return; }
        window.open('https://wa.me/' + fone + '?text=' + encodeURIComponent(msg), '_blank');
        var sinId = $('#sinId').val();
        if (sinId) {
            var fd = new FormData();
            fd.append('acao',   'marcar_whatsapp');
            fd.append('csrf',   CSRF);
            fd.append('sin_id', sinId);
            fetch(SIN_ACTION, { method: 'POST', body: fd });
        }
        $('#modalWhatsApp').modal('hide');
    });

    /* ── UF → Cidades (local) ── */
    function carregarCidadesSin(uf, cidadeSelecionada) {
        var $sel = $('#sinCidade');
        if (!uf) {
            $sel.prop('disabled', true).empty().append('<option value="">Selecione a UF primeiro</option>');
            return;
        }
        var mapa = window.cidadesPorEstado || {};
        var lista = mapa[uf] || [];
        $sel.empty().append('<option value="">Selecione...</option>');
        lista.forEach(function (c) {
            $sel.append(new Option(c, c, false, c === cidadeSelecionada));
        });
        $sel.prop('disabled', lista.length === 0);
        if (cidadeSelecionada) $sel.val(cidadeSelecionada);
    }

    document.getElementById('sinUF').addEventListener('change', function () { carregarCidadesSin(this.value, ''); });

    /* ── Reset ao fechar ── */
    $('#modalSinistro').on('hidden.bs.modal', function () {
        resetModal();
        toggleUploadZones(false);
    });

    }); // DOMContentLoaded
})();
</script>
