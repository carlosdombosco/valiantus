<?php
// no arquivo que renderiza o modal (ex.: frm_listar_associado.php),
// garanta que o CSRF esteja carregado:
require_once PATH_INC . '/csrf.php';
$csrf = csrf_token();
?>


<style>
    #CadastrarCliente .modal-dialog {
        max-width: 1180px;
    }
    #CadastrarCliente .modal-content {
        border: none;
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(22,28,45,.18);
        background: #ffffff;
    }
    #CadastrarCliente .modal-header {
        background: linear-gradient(135deg,#3b5bdb 0%,#274cdb 100%);
        color: #fff;
        border-bottom: none;
        padding: 18px 22px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    #CadastrarCliente .modal-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 18px;
        font-weight: 700;
        margin: 0;
        color: #fff;
    }
    #CadastrarCliente .modal-title i {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(255,255,255,.14);
        font-size: 15px;
    }
    #CadastrarCliente .close,
    #CadastrarCliente .btn-close {
        border: none;
        outline: none;
        box-shadow: none !important;
        background: rgba(255,255,255,.14);
        color: #fff !important;
        width: 38px;
        height: 38px;
        border-radius: 12px;
        opacity: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: .18s ease;
    }
    #CadastrarCliente .close:hover,
    #CadastrarCliente .btn-close:hover {
        background: rgba(255,255,255,.22);
        transform: translateY(-1px);
    }
    #CadastrarCliente .modal-body {
        background: #f8fafc;
        padding: 22px;
    }
    #CadastrarCliente .modal-footer {
        border-top: 1px solid #e9ecef;
        background: #fff;
        padding: 16px 22px;
    }
    /* Blocos internos */
    #CadastrarCliente .cad-box {
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 16px;
        box-shadow: 0 4px 16px rgba(30,40,80,.05);
        padding: 18px;
        margin-bottom: 18px;
    }
    #CadastrarCliente .cad-box-title {
        display: flex;
        align-items: center;
        gap: 9px;
        font-size: 13px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #3b5bdb;
        margin-bottom: 14px;
    }
    #CadastrarCliente .cad-box-title i {
        width: 28px;
        height: 28px;
        border-radius: 9px;
        background: #e8edff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
    }
    /* Labels */
    #CadastrarCliente label {
        font-size: 12px;
        font-weight: 700;
        color: #495057;
        margin-bottom: 6px;
        display: inline-block;
    }
    /* Inputs */
    #CadastrarCliente .form-control,
    #CadastrarCliente .form-select,
    #CadastrarCliente select,
    #CadastrarCliente textarea {
        min-height: 40px;
        border: 1.5px solid #dbe2ea;
        border-radius: 11px;
        background: #fff;
        color: #1a1d2e;
        font-size: 13.5px;
        padding: 10px 12px;
        box-shadow: none !important;
        transition: border-color .15s ease, box-shadow .15s ease, background .15s ease;
    }
    #CadastrarCliente textarea {
        min-height: 100px;
        resize: vertical;
    }
    #CadastrarCliente .form-control:focus,
    #CadastrarCliente .form-select:focus,
    #CadastrarCliente select:focus,
    #CadastrarCliente textarea:focus {
        border-color: #3b5bdb;
        background: #fff;
        box-shadow: 0 0 0 4px rgba(59,91,219,.10) !important;
    }
    #CadastrarCliente .form-control::placeholder,
    #CadastrarCliente textarea::placeholder {
        color: #adb5bd;
    }
    #CadastrarCliente .row>[class*=”col-”] {
        margin-bottom: 10px;
    }
    /* Card da foto */
    #CadastrarCliente .cad-foto-card {
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 16px;
        box-shadow: 0 4px 16px rgba(30,40,80,.05);
        overflow: hidden;
    }
    #CadastrarCliente .cad-foto-head {
        padding: 14px 16px;
        border-bottom: 1px solid #edf2f7;
        background: linear-gradient(180deg,#fafcff 0%,#f4f7ff 100%);
    }
    #CadastrarCliente .cad-foto-head strong {
        font-size: 12px;
        letter-spacing: .05em;
        text-transform: uppercase;
        color: #3b5bdb;
    }
    #CadastrarCliente .cad-foto-body {
        padding: 18px;
        text-align: center;
    }
    #CadastrarCliente .cad-foto-preview {
        width: 190px;
        height: 190px;
        object-fit: cover;
        border-radius: 16px;
        border: 1px solid #e9ecef;
        box-shadow: 0 8px 20px rgba(30,40,80,.08);
        background: #fff;
        margin-bottom: 14px;
    }
    /* Botões */
    #CadastrarCliente .btn {
        border-radius: 11px;
        font-weight: 600;
        transition: all .15s ease;
    }
    #CadastrarCliente .btn-primary {
        background: #3b5bdb;
        border-color: #3b5bdb;
    }
    #CadastrarCliente .btn-primary:hover {
        background: #2f52d6;
        border-color: #2f52d6;
        transform: translateY(-1px);
        box-shadow: 0 8px 18px rgba(59,91,219,.20);
    }
    #CadastrarCliente .btn-secondary {
        border-color: #d0d7de;
        background: #fff;
        color: #495057;
    }
    #CadastrarCliente .btn-secondary:hover {
        background: #f8f9fa;
        border-color: #c5ced8;
    }
    #CadastrarCliente .btn-outline-primary,
    #CadastrarCliente .btn-outline-secondary {
        border-width: 1.5px;
        font-weight: 600;
    }
    /* Câmera */
    #CadastrarCliente #cameraWrap,
    #CadastrarCliente .camera-wrap {
        margin-top: 12px;
        border: 1px solid #e2e8f0 !important;
        border-radius: 14px !important;
        background: #f8fafc;
        padding: 12px !important;
    }
    #CadastrarCliente video {
        width: 100%;
        max-height: 220px;
        border-radius: 12px;
        background: #000;
    }
    /* Scrollbar elegante */
    #CadastrarCliente .modal-body::-webkit-scrollbar { width: 10px; }
    #CadastrarCliente .modal-body::-webkit-scrollbar-thumb { background: #cfd8ea; border-radius: 20px; }
    #CadastrarCliente .modal-body::-webkit-scrollbar-track { background: transparent; }
    /* Mobile */
    @media (max-width: 991px) {
        #CadastrarCliente .modal-dialog {
            max-width: calc(100% - 16px);
            margin: 8px auto;
        }
        #CadastrarCliente .modal-body { padding: 14px; }
        #CadastrarCliente .modal-footer { padding: 14px; }
    }
</style>


<!-- Modal de Cadastro -->
<div class="modal fade" id="CadastrarCliente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-custom-width" role="document">
        <form id="formCadastroPessoa">
            <div class="modal-content">
                <div class="modal-header align-items-center">
                    <h5 class="modal-title d-flex align-items-center">
                        <span class="title-icon rounded-circle d-inline-flex align-items-center justify-content-center mr-2">
                            <i class="fa fa-user" id="modalAssocIcon"></i>
                        </span>
                        <span id="modalAssocTitulo">Novo Associado</span>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <div class="row">
                        <!-- ===== Coluna esquerda: todo o formulário original ===== -->
                        <div class="col-md-9">
                            <div class="row">
                                <div class="col-md-2 mb-2">
                                    <label class="form-label">Tipo</label>
                                    <select class="form-select form-control form-control-sm" name="tipo">
                                        <option value="FÍSICA">FÍSICA</option>
                                        <option value="JURÍDICA">JURÍDICA</option>
                                    </select>
                                </div>
                                <div class="col-md-7 mb-2">
                                    <label for="nome" class="form-label">Nome</label>
                                    <input type="text" class="form-control form-control-sm" name="nome" required>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="form-label">CPF / CNPJ</label>
                                    <input type="text" class="form-control form-control-sm" id="cpf" name="cpf" inputmode="numeric" pattern="\d*" maxlength="14" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Celular / WhatsApp</label>
                                    <input type="text" class="form-control form-control-sm" name="celular1" id="celular1" inputmode="numeric" pattern="\d*" maxlength="11" required>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Celular 2</label>
                                    <input type="text" class="form-control form-control-sm" name="celular2" id="celular2" inputmode="numeric" pattern="\d*" maxlength="11">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Fone Fixo</label>
                                    <input type="text" class="form-control form-control-sm" name="telefone" id="telefone">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Data Nascimento</label>
                                    <input type="date" class="form-control form-control-sm" name="data_nascimento">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">RG</label>
                                    <input type="text" class="form-control form-control-sm" name="rg">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Órgão Expeditor</label>
                                    <input type="text" class="form-control form-control-sm" name="orgao">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">N. CNH</label>
                                    <input type="text" class="form-control form-control-sm" name="cnh">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Categoria</label>
                                    <input type="text" class="form-control form-control-sm" name="categoria">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Validade</label>
                                    <input type="date" class="form-control form-control-sm" name="validade">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-2 mb-2">
                                    <label class="form-label">Estado Civil</label>
                                    <select class="form-select form-control form-control-sm" name="estado_civil">
                                        <option value="">Selecione...</option>
                                        <option value="SOLTEIRO">SOLTEIRO</option>
                                        <option value="CASADO">CASADO</option>
                                        <option value="OUTROS">OUTROS</option>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label class="form-label">Sexo</label>
                                    <select class="form-select form-control form-control-sm" name="sexo">
                                        <option value="">Selecione...</option>
                                        <option value="MASCULINO">MASCULINO</option>
                                        <option value="FEMININO">FEMININO</option>
                                        <option value="OUTROS">OUTROS</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control form-control-sm" name="email" id="email">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Profissão</label>
                                    <input type="text" class="form-control form-control-sm" name="profissao">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-2 mb-2">
                                    <label class="form-label">CEP</label>
                                    <input type="text" class="form-control form-control-sm" name="cep" id="cep">
                                </div>
                                <div class="col-md-8 mb-2">
                                    <label class="form-label">Endereço</label>
                                    <input type="text" class="form-control form-control-sm" name="endereco" id="endereco">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label class="form-label">Número</label>
                                    <input type="text" class="form-control form-control-sm" name="numero">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Complemento</label>
                                    <input type="text" class="form-control form-control-sm" name="complemento">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Bairro</label>
                                    <input type="text" class="form-control form-control-sm" name="bairro" id="bairro">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Ponto de Referência</label>
                                    <input type="text" class="form-control form-control-sm" name="referencia">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label class="form-label">UF</label>
                                    <select class="form-select form-control form-control-sm" id="uf" name="uf" required>
                                        <option value="">Selecione</option>
                                        <option value="AC">AC</option>
                                        <option value="AL">AL</option>
                                        <option value="AM">AM</option>
                                        <option value="AP">AP</option>
                                        <option value="BA">BA</option>
                                        <option value="CE">CE</option>
                                        <option value="DF">DF</option>
                                        <option value="ES">ES</option>
                                        <option value="GO">GO</option>
                                        <option value="MA">MA</option>
                                        <option value="MG">MG</option>
                                        <option value="MS">MS</option>
                                        <option value="MT">MT</option>
                                        <option value="PA">PA</option>
                                        <option value="PB">PB</option>
                                        <option value="PE">PE</option>
                                        <option value="PI">PI</option>
                                        <option value="PR">PR</option>
                                        <option value="RJ">RJ</option>
                                        <option value="RN">RN</option>
                                        <option value="RO">RO</option>
                                        <option value="RR">RR</option>
                                        <option value="RS">RS</option>
                                        <option value="SC">SC</option>
                                        <option value="SE">SE</option>
                                        <option value="SP">SP</option>
                                        <option value="TO">TO</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label for="cidade" class="form-label">Cidade</label>
                                    <select class="form-select form-control form-control-sm" id="cidade" name="cidade" required>
                                        <option value="">Selecione</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12 mb-2">
                                    <label class="form-label">Observação</label>
                                    <textarea class="form-control" name="observacao" id="observacao" rows="5"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- ===== Coluna direita: Foto do associado ===== -->
                        <div class="col-md-3">
                            <div class="card text-center shadow-sm">
                                <div class="card-body">
                                    <div class="avatar-wrapper mx-auto mb-3" style="width:160px;height:160px;position:relative;">
                                        <!-- Preview (padrão) -->
                                        <img id="fotoPreview"
                                            src="https://cdn.jsdelivr.net/gh/encharm/Font-Awesome-SVG-PNG/black/png/64/user.png"
                                            alt="Foto do associado"
                                            class="rounded-circle border"
                                            style="width:160px;height:160px;object-fit:cover;display:block;">
                                        <!-- Vídeo (câmera) -->
                                        <video id="cameraStream"
                                            class="rounded-circle border d-none"
                                            autoplay playsinline muted
                                            style="width:160px;height:160px;object-fit:cover;"></video>
                                    </div>

                                    <div class="btn-group-vertical w-100">
                                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnEscolherFoto">
                                            <i class="fa fa-upload"></i> Enviar foto
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-info" id="btnAbrirCamera">
                                            <i class="fa fa-camera"></i> Tirar foto
                                        </button>
                                        <button type="button" class="btn btn-sm btn-success d-none" id="btnCapturar">
                                            <i class="fa fa-check"></i> Capturar
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="btnCancelarCamera">
                                            <i class="fa fa-times"></i> Cancelar
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger mt-1" id="btnRemoverFoto">
                                            <i class="fa fa-trash"></i> Remover
                                        </button>
                                    </div>

                                    <!-- Inputs de suporte -->
                                    <input type="file" id="fotoUpload" name="foto" accept="image/*" capture="user" class="d-none">
                                    <input type="hidden" name="foto_base64" id="fotoBase64">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Campos ocultos -->
                <input type="hidden" name="id"   id="pessoaId"  value="">
                <input type="hidden" name="acao" id="pessoaAcao" value="cadastrar">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                    <button type="submit" id="btnSalvar" class="btn btn-primary">Salvar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Abrir seletor de arquivo
        document.getElementById('btnEscolherFoto').addEventListener('click', () => {
            document.getElementById('fotoUpload').click();
        });

        // Preview quando escolher arquivo
        document.getElementById('fotoUpload').addEventListener('change', function(e) {
            const file = this.files && this.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function(ev) {
                const preview = document.getElementById('fotoPreview');
                preview.src = ev.target.result;
                preview.classList.remove('d-none');
                document.getElementById('cameraStream').classList.add('d-none');
                // limpa base64 se veio por arquivo
                document.getElementById('fotoBase64').value = '';
            };
            reader.readAsDataURL(file);
        });

        // Abrir câmera
        let mediaStream = null;
        async function openCamera() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                Swal && Swal.fire('Ops', 'Câmera não suportada neste dispositivo.', 'info');
                return;
            }
            try {
                mediaStream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: 'user'
                    },
                    audio: false
                });
                const video = document.getElementById('cameraStream');
                video.srcObject = mediaStream;
                video.classList.remove('d-none');

                document.getElementById('fotoPreview').classList.add('d-none');
                document.getElementById('btnCapturar').classList.remove('d-none');
                document.getElementById('btnCancelarCamera').classList.remove('d-none');
            } catch (err) {
                Swal && Swal.fire('Erro', 'Não foi possível acessar a câmera.', 'error');
            }
        }

        function closeCamera() {
            const video = document.getElementById('cameraStream');
            if (mediaStream) {
                mediaStream.getTracks().forEach(t => t.stop());
                mediaStream = null;
            }
            video.srcObject = null;
            video.classList.add('d-none');
            document.getElementById('btnCapturar').classList.add('d-none');
            document.getElementById('btnCancelarCamera').classList.add('d-none');
            document.getElementById('fotoPreview').classList.remove('d-none');
        }

        document.getElementById('btnAbrirCamera').addEventListener('click', openCamera);
        document.getElementById('btnCancelarCamera').addEventListener('click', closeCamera);

        // Capturar foto da câmera → base64
        document.getElementById('btnCapturar').addEventListener('click', function() {
            const video = document.getElementById('cameraStream');
            if (!video || video.classList.contains('d-none')) return;

            const canvas = document.createElement('canvas');
            // recorte quadrado suave (avatar)
            const size = Math.min(video.videoWidth || 640, video.videoHeight || 640);
            canvas.width = size;
            canvas.height = size;
            const ctx = canvas.getContext('2d');
            const sx = ((video.videoWidth || size) - size) / 2;
            const sy = ((video.videoHeight || size) - size) / 2;
            ctx.drawImage(video, sx, sy, size, size, 0, 0, size, size);

            // JPEG ~0.85 (ótimo balanço p/ avatar)
            const dataURL = canvas.toDataURL('image/jpeg', 0.85);
            document.getElementById('fotoPreview').src = dataURL;
            document.getElementById('fotoBase64').value = dataURL;
            // limpa arquivo se havia
            document.getElementById('fotoUpload').value = '';
            closeCamera();
        });

        // Remover foto (volta para placeholder e limpa inputs)
        document.getElementById('btnRemoverFoto').addEventListener('click', function() {
            document.getElementById('fotoPreview').src =
                'https://cdn.jsdelivr.net/gh/encharm/Font-Awesome-SVG-PNG/black/png/64/user.png';
            document.getElementById('fotoUpload').value = '';
            document.getElementById('fotoBase64').value = '';
            closeCamera();
        });

        // Ao fechar: desliga câmera e reseta para modo cadastro
        $('#CadastrarCliente').on('hidden.bs.modal', function () {
            closeCamera();
            const PH = 'https://cdn.jsdelivr.net/gh/encharm/Font-Awesome-SVG-PNG/black/png/64/user.png';
            document.getElementById('formCadastroPessoa').reset();
            document.getElementById('pessoaId').value      = '';
            document.getElementById('pessoaAcao').value    = 'cadastrar';
            document.getElementById('modalAssocTitulo').textContent = 'Novo Associado';
            document.getElementById('modalAssocIcon').className     = 'fa fa-user';
            document.getElementById('fotoPreview').src  = PH;
            document.getElementById('fotoUpload').value = '';
            document.getElementById('fotoBase64').value = '';
            document.getElementById('btnSalvar').disabled = false;
            document.getElementById('btnSalvar').textContent = 'Salvar';
        });
    });
</script>

<style>
    /* Só para deixar o círculo do ícone do título bonitinho */
    .title-icon {
        width: 28px;
        height: 28px;
        background: #f3f6f9;
        color: #3f4254;
    }
</style>


<script>
    document.addEventListener('DOMContentLoaded', function () {
        function getMapaCidades() {
            if (typeof cidadesPorEstado !== 'undefined') return cidadesPorEstado;
            if (typeof window !== 'undefined' && window.cidadesPorEstado) return window.cidadesPorEstado;
            return {};
        }

        function popularCidadesUF(uf, cidadeSelecionada) {
            const $cidade = $('#cidade');
            $cidade.empty().append(new Option('Selecione', '', true, false));

            if (typeof preencherCidades === 'function') {
                preencherCidades(uf, $cidade, cidadeSelecionada || '');
                return;
            }

            const mapa = getMapaCidades();
            const lista = (mapa && mapa[uf]) ? mapa[uf] : [];

            for (const c of lista) {
                $cidade.append(new Option(c, c, false, false));
            }

            if (cidadeSelecionada && cidadeSelecionada.trim() !== '') {
                const existe = lista.includes(cidadeSelecionada);
                if (!existe) {
                    $cidade.append(new Option(cidadeSelecionada, cidadeSelecionada, true, true));
                } else {
                    $cidade.val(cidadeSelecionada);
                }
            }
        }

        function onlyDigits(el) {
            el.value = el.value.replace(/\D/g, '');
        }

        const cepEl = document.getElementById('cep');
        if (cepEl) {
            cepEl.addEventListener('input', function() {
                onlyDigits(cepEl);
            });

            $('#cep').on('blur', function() {
                const cep = this.value.replace(/\D/g, '');
                if (cep.length !== 8) return;

                if (typeof preencherEnderecoPorCep === 'function') {
                    preencherEnderecoPorCep(cep, {
                        cepEl: '#cep',
                        endereco: '#endereco',
                        bairro: '#bairro',
                        uf: '#uf',
                        cidade: '#cidade'
                    });
                    return;
                }

                $.getJSON(`https://viacep.com.br/ws/${cep}/json/`, function(data) {
                    if (data && !data.erro) {
                        $('#endereco').val(data.logradouro || '');
                        $('#bairro').val(data.bairro || '');

                        const uf = (data.uf || '').toUpperCase();
                        const cidade = data.localidade || '';

                        if (uf) {
                            $('#uf').val(uf);
                            popularCidadesUF(uf, cidade);
                        } else {
                            $('#cidade').empty().append(new Option('Selecione', '', true, false));
                        }
                    } else {
                        $('#endereco, #bairro').val('');
                        $('#cidade').empty().append(new Option('Selecione', '', true, false));
                    }
                });
            });
        }

        $('#uf').on('change', function() {
            popularCidadesUF($(this).val(), null);
        });

        $('#CadastrarCliente').on('shown.bs.modal', function() {
            const ufAtual = $('#uf').val();
            if (ufAtual) {
                popularCidadesUF(ufAtual, $('#cidade').val() || null);
            }
        });

        const emailField = document.getElementById('email');
        if (emailField) {
            emailField.addEventListener('input', () => {
                emailField.value = emailField.value.toLowerCase();
            });
        }

        // Validação rápida de CPF (mantive sua função)
        function validarCPF(cpf) {
            cpf = cpf.replace(/[^\d]+/g, '');
            if (cpf.length !== 11 || /^(\d)\1+$/.test(cpf)) return false;
            let soma = 0,
                resto;
            for (let i = 1; i <= 9; i++) soma += parseInt(cpf.substring(i - 1, i)) * (11 - i);
            resto = (soma * 10) % 11;
            if (resto === 10 || resto === 11) resto = 0;
            if (resto !== parseInt(cpf.substring(9, 10))) return false;
            soma = 0;
            for (let i = 1; i <= 10; i++) soma += parseInt(cpf.substring(i - 1, i)) * (12 - i);
            resto = (soma * 10) % 11;
            if (resto === 10 || resto === 11) resto = 0;
            return resto === parseInt(cpf.substring(10, 11));
        }
        $('#cpf').on('blur', function() {
            const bruto = $(this).val() || '';
            const cpf = bruto.replace(/\D/g, '');

            if (!cpf) return;

            if (!validarCPF(cpf)) {
                Swal.fire('CPF inválido', 'O CPF digitado não é válido.', 'warning');
                return;
            }
            const csrf   = document.querySelector('input[name="csrf"]').value;
            const idAtual = (document.getElementById('pessoaId')?.value || '').trim();
            const body = new URLSearchParams({ acao: 'verificar_cpf', cpf, csrf });
            if (idAtual) body.set('id', idAtual);

            fetch('<?= ACTION_URL ?>/pessoas.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                    body
                })
                .then(r => r.json())
                .then(json => {
                    if (json.success && json.existe) {
                        $('#btnSalvar').prop('disabled', true);
                        Swal.fire('CPF já cadastrado', 'Já existe um associado com esse CPF.', 'error');
                    } else {
                        $('#btnSalvar').prop('disabled', false);
                    }
                })
                .catch(() => { $('#btnSalvar').prop('disabled', false); });
        });

        // Submit (mantive sua chamada ao ACTION_URL/pessoas.php)
        document.getElementById('formCadastroPessoa').addEventListener('submit', function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            fd.set('cpf', (fd.get('cpf') || '').replace(/\D/g, ''));
            fd.set('celular1', (fd.get('celular1') || '').replace(/\D/g, ''));
            fd.set('celular2', (fd.get('celular2') || '').replace(/\D/g, ''));
            fd.set('telefone', (fd.get('telefone') || '').replace(/\D/g, ''));

            fetch('<?= ACTION_URL ?>/pessoas.php', {
                    method: 'POST',
                    body: fd
                })
                .then(r => {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(json => {
                    if (json.success) {
                        const isEdit = !!document.getElementById('pessoaId').value;
                        Swal.fire({
                            icon: 'success',
                            title: isEdit ? 'Associado atualizado!' : 'Associado cadastrado!',
                            text:  isEdit ? 'Dados atualizados com sucesso.' : 'Cadastro efetuado com sucesso.'
                        }).then(() => location.reload());
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro',
                            text: json.message || 'Não foi possível salvar.'
                        });
                    }
                })
                .catch(() => Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: 'Falha ao comunicar com o servidor.'
                }));
        });
    });
</script>



<script>
    document.addEventListener('DOMContentLoaded', function () {
        function onlyDigitsInput(selector, maxLen) {
            const el = document.querySelector(selector);
            if (!el) return;

            // dicas para mobile
            el.setAttribute('inputmode', 'numeric');
            el.setAttribute('pattern', '\\d*');
            if (maxLen) el.setAttribute('maxlength', String(maxLen));

            // limpa qualquer coisa que não seja dígito (inclusive colar)
            el.addEventListener('input', function(e) {
                const digits = e.target.value.replace(/\D+/g, '');
                e.target.value = maxLen ? digits.slice(0, maxLen) : digits;
            });
        }

        // CPF (11) e Celular (11)
        onlyDigitsInput('#cpf', 14);
        onlyDigitsInput('#celular1', 11);
        onlyDigitsInput('#celular2', 11);
    });
</script>