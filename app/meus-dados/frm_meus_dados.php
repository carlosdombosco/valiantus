<?php
if (!defined('PATH_INC')) require_once __DIR__ . '/../../inc/config.php';
require_once PATH_INC . '/db.php';
require_once PATH_INC . '/csrf.php';
$csrf = csrf_token();

// Garante coluna USU_FOTO
$colFotoExiste = $pdo->query("
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tb_usuario' AND COLUMN_NAME = 'USU_FOTO'
")->fetchColumn();
if (!$colFotoExiste) {
    $pdo->exec("ALTER TABLE `tb_usuario` ADD COLUMN `USU_FOTO` VARCHAR(500) NOT NULL DEFAULT '' AFTER `USU_TIPO`");
}

$usuCodigo = (int)$_SESSION['SessUsuCodigo'];
$stmt = $pdo->prepare("SELECT USU_CODIGO_PK, USU_NOME, USU_EMAIL, USU_TIPO, USU_FOTO FROM tb_usuario WHERE USU_CODIGO_PK = ?");
$stmt->execute([$usuCodigo]);
$usu = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$usuNome  = htmlspecialchars($usu['USU_NOME']  ?? '', ENT_QUOTES, 'UTF-8');
$usuEmail = htmlspecialchars($usu['USU_EMAIL'] ?? '', ENT_QUOTES, 'UTF-8');
$usuTipo  = htmlspecialchars($usu['USU_TIPO']  ?? '', ENT_QUOTES, 'UTF-8');
$usuFoto  = htmlspecialchars($usu['USU_FOTO']  ?? '', ENT_QUOTES, 'UTF-8');
$iniciais = strtoupper(implode('', array_map(
    fn($p) => $p[0],
    array_slice(explode(' ', trim($usu['USU_NOME'] ?: 'US')), 0, 2)
)));
?>

<style>
.md-page        { }
.md-profile-card {
    background: linear-gradient(135deg, #3b5bdb 0%, #2f4abf 100%);
    border-radius: 20px;
    padding: 32px 28px;
    display: flex;
    align-items: center;
    gap: 24px;
    margin-bottom: 24px;
    box-shadow: 0 8px 32px rgba(59,91,219,.28);
    position: relative;
    overflow: hidden;
}
.md-profile-card::before {
    content: '';
    position: absolute;
    width: 300px; height: 300px;
    border-radius: 50%;
    border: 1px solid rgba(255,255,255,.07);
    top: -120px; right: -80px;
}
.md-avatar-wrap { position: relative; flex-shrink: 0; }
.md-avatar-img  {
    width: 96px; height: 96px; border-radius: 50%;
    object-fit: cover;
    border: 3px solid rgba(255,255,255,.4);
    display: block;
}
.md-avatar-initials {
    width: 96px; height: 96px; border-radius: 50%;
    background: rgba(255,255,255,.18);
    border: 3px solid rgba(255,255,255,.35);
    display: flex; align-items: center; justify-content: center;
    font-size: 32px; font-weight: 800; color: #fff; letter-spacing: .02em;
}
.md-avatar-btn {
    position: absolute; bottom: 2px; right: 2px;
    width: 28px; height: 28px; border-radius: 50%;
    background: #fff; border: none; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 2px 8px rgba(0,0,0,.2);
    color: #3b5bdb; font-size: 12px;
    transition: .15s;
}
.md-avatar-btn:hover { transform: scale(1.1); }
.md-profile-info { flex: 1; z-index: 1; }
.md-profile-info h2 { color: #fff; font-size: 22px; font-weight: 800; margin: 0 0 4px; }
.md-profile-info .md-email { color: rgba(255,255,255,.75); font-size: 13.5px; margin: 0 0 10px; }
.md-badge {
    display: inline-flex; align-items: center; gap: 5px;
    background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.2);
    border-radius: 99px; padding: 5px 14px;
    font-size: 12px; font-weight: 600; color: rgba(255,255,255,.9);
}

/* Tabs */
.md-tabs { display: flex; gap: 4px; margin-bottom: 16px; border-bottom: 2px solid #e9ecef; }
.md-tab  {
    padding: 10px 20px; border: none; background: transparent;
    font-size: 13.5px; font-weight: 600; color: #868e96;
    cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px;
    transition: .15s; border-radius: 8px 8px 0 0; display: flex; align-items: center; gap: 7px;
}
.md-tab:hover { color: #3b5bdb; background: #f0f3ff; }
.md-tab.active { color: #3b5bdb; border-bottom-color: #3b5bdb; background: #f0f3ff; }

.md-panel { display: none; }
.md-panel.active { display: block; }

/* Card */
.md-card {
    background: #fff; border: 1px solid #e9ecef;
    border-radius: 16px; box-shadow: 0 4px 20px rgba(30,40,80,.07);
    overflow: hidden; margin-bottom: 20px;
}
.md-card-header {
    padding: 16px 22px; border-bottom: 1px solid #e9ecef;
    display: flex; align-items: center; gap: 10px;
    background: #fafbfc;
}
.md-card-header i { color: #3b5bdb; font-size: 15px; }
.md-card-header h3 { margin: 0; font-size: 15px; font-weight: 700; color: #1a1d2e; }
.md-card-body { padding: 24px; }
.md-card-footer {
    padding: 14px 22px; border-top: 1px solid #e9ecef;
    display: flex; justify-content: flex-end; gap: 10px; background: #fafbfc;
}

/* Form */
.md-label { font-size: 12px; font-weight: 700; color: #495057; margin-bottom: 5px; display: block; }
.md-input {
    width: 100%; height: 42px; border: 1.5px solid #dbe2ea;
    border-radius: 9px; padding: 0 12px; font-size: 13.5px;
    color: #1a1d2e; background: #fff; transition: .15s;
    font-family: inherit;
}
.md-input:focus { border-color: #3b5bdb; box-shadow: 0 0 0 3px rgba(59,91,219,.1); outline: none; }
.md-input-icon-wrap { position: relative; }
.md-input-icon-wrap .md-input { padding-right: 42px; }
.md-input-icon-wrap .md-eye-btn {
    position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
    background: none; border: none; cursor: pointer; color: #868e96; font-size: 14px; padding: 0;
}
.md-input-icon-wrap .md-eye-btn:hover { color: #3b5bdb; }

.md-hint { font-size: 11.5px; color: #868e96; margin-top: 5px; }
.md-strength { display: flex; gap: 4px; margin-top: 6px; }
.md-strength-bar { flex: 1; height: 4px; border-radius: 3px; background: #e9ecef; transition: .3s; }
.md-strength-label { font-size: 11px; color: #868e96; margin-top: 4px; }

/* Botões */
.md-btn-save {
    height: 40px; padding: 0 24px; border-radius: 10px;
    background: #3b5bdb; color: #fff; border: none;
    font-size: 13.5px; font-weight: 700; cursor: pointer; transition: .15s;
    display: inline-flex; align-items: center; gap: 7px;
}
.md-btn-save:hover { background: #2f4abf; transform: translateY(-1px); box-shadow: 0 6px 16px rgba(59,91,219,.25); }
.md-btn-save:disabled { opacity: .65; transform: none; }

.md-btn-danger {
    height: 40px; padding: 0 24px; border-radius: 10px;
    background: #e03131; color: #fff; border: none;
    font-size: 13.5px; font-weight: 700; cursor: pointer; transition: .15s;
    display: inline-flex; align-items: center; gap: 7px;
}
.md-btn-danger:hover { background: #c92a2a; transform: translateY(-1px); }
.md-btn-danger:disabled { opacity: .65; transform: none; }

/* Email atual readonly */
.md-email-atual {
    display: flex; align-items: center; gap: 10px;
    background: #f8f9fa; border: 1.5px solid #e9ecef;
    border-radius: 9px; padding: 0 14px; height: 42px;
    font-size: 13.5px; color: #495057;
}
.md-email-atual i { color: #adb5bd; }

/* Alert inline */
.md-alert {
    border-radius: 9px; padding: 10px 14px; font-size: 13px;
    margin-bottom: 14px; display: none; align-items: center; gap: 8px;
}
.md-alert.show { display: flex; }
.md-alert.success { background: #ebfbee; border: 1px solid #b2f2bb; color: #2b8a3e; }
.md-alert.error   { background: #fff5f5; border: 1px solid #ffc9c9; color: #c92a2a; }
</style>

<div class="vt-page md-page">

    <!-- Cartão de perfil -->
    <div class="md-profile-card">
        <div class="md-avatar-wrap" id="mdAvatarWrap">
            <?php if ($usuFoto): ?>
                <img src="<?= $usuFoto ?>" class="md-avatar-img" id="mdAvatarImg" alt="Foto">
            <?php else: ?>
                <div class="md-avatar-initials" id="mdAvatarInitials"><?= $iniciais ?></div>
            <?php endif; ?>
            <button class="md-avatar-btn" title="Alterar foto" onclick="document.getElementById('inputFoto').click()">
                <i class="fa-solid fa-camera"></i>
            </button>
            <input type="file" id="inputFoto" accept="image/*" style="display:none;">
        </div>
        <div class="md-profile-info">
            <h2 id="mdNomeDisplay"><?= $usuNome ?: 'Usuário' ?></h2>
            <p class="md-email"><i class="fa-regular fa-envelope" style="margin-right:5px;opacity:.7;"></i><?= $usuEmail ?></p>
            <?php if ($usuTipo): ?>
                <span class="md-badge"><i class="fa-solid fa-shield-halved"></i><?= $usuTipo ?></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tabs -->
    <div class="md-tabs">
        <button class="md-tab active" onclick="mdSwitchTab('dados',this)">
            <i class="fa-regular fa-user"></i> Dados Pessoais
        </button>
        <button class="md-tab" onclick="mdSwitchTab('email',this)">
            <i class="fa-regular fa-envelope"></i> Alterar E-mail
        </button>
        <button class="md-tab" onclick="mdSwitchTab('senha',this)">
            <i class="fa-solid fa-lock"></i> Alterar Senha
        </button>
    </div>

    <!-- ── Painel: Dados Pessoais ── -->
    <div class="md-panel active" id="mdPanelDados">
        <div class="md-card">
            <div class="md-card-header">
                <i class="fa-regular fa-address-card"></i>
                <h3>Dados Pessoais</h3>
            </div>
            <form id="formDados" enctype="multipart/form-data">
                <input type="hidden" name="csrf"  value="<?= $csrf ?>">
                <input type="hidden" name="acao"  value="atualizar_dados">
                <input type="hidden" id="fotoDataInput" name="foto_data" value="">
                <div class="md-card-body">
                    <div class="md-alert" id="alertDados"></div>
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="md-label">Nome completo</label>
                            <input type="text" class="md-input" name="nome" id="inputNome"
                                   value="<?= $usuNome ?>" placeholder="Seu nome completo" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="md-label">Tipo de acesso</label>
                            <input type="text" class="md-input" value="<?= $usuTipo ?>" disabled style="background:#f8f9fa;color:#868e96;">
                        </div>
                        <div class="col-12 mb-2">
                            <label class="md-label">Foto de perfil</label>
                            <div style="display:flex;align-items:center;gap:16px;">
                                <div id="fotoPreviewWrap" style="flex-shrink:0;">
                                    <?php if ($usuFoto): ?>
                                        <img src="<?= $usuFoto ?>" id="fotoPreviewImg"
                                             style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:2px solid #e9ecef;" alt="Foto">
                                    <?php else: ?>
                                        <div id="fotoPreviewInitials"
                                             style="width:72px;height:72px;border-radius:50%;background:#3b5bdb;color:#fff;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:800;">
                                            <?= $iniciais ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <button type="button" class="md-btn-save" style="background:#f0f3ff;color:#3b5bdb;border:1.5px solid #c5d0fc;"
                                            onclick="document.getElementById('inputFoto').click()">
                                        <i class="fa-solid fa-upload"></i> Escolher foto
                                    </button>
                                    <p class="md-hint" style="margin-top:6px;">JPG, PNG ou WEBP. Máximo 5 MB.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="md-card-footer">
                    <button type="submit" class="md-btn-save" id="btnSalvarDados">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span id="txtBtnDados">Salvar dados</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Painel: Alterar E-mail ── -->
    <div class="md-panel" id="mdPanelEmail">
        <div class="md-card">
            <div class="md-card-header">
                <i class="fa-regular fa-envelope"></i>
                <h3>Alterar E-mail</h3>
            </div>
            <form id="formEmail">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <input type="hidden" name="acao" value="alterar_email">
                <div class="md-card-body">
                    <div class="md-alert" id="alertEmail"></div>
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="md-label">E-mail atual</label>
                            <div class="md-email-atual">
                                <i class="fa-solid fa-envelope"></i>
                                <span id="emailAtualDisplay"><?= $usuEmail ?></span>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="md-label">Novo e-mail</label>
                            <input type="email" class="md-input" name="novo_email" placeholder="novo@email.com" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="md-label">Confirmar novo e-mail</label>
                            <input type="email" class="md-input" name="confirma_email" placeholder="Repita o novo e-mail" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="md-label">Sua senha atual <span style="color:#c92a2a">*</span></label>
                            <div class="md-input-icon-wrap">
                                <input type="password" class="md-input" name="senha_atual" placeholder="••••••••" required>
                                <button type="button" class="md-eye-btn" onclick="mdTogglePw(this)">
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </div>
                            <p class="md-hint">Por segurança, confirme sua senha para alterar o e-mail.</p>
                        </div>
                    </div>
                </div>
                <div class="md-card-footer">
                    <button type="submit" class="md-btn-save" id="btnSalvarEmail">
                        <i class="fa-solid fa-envelope-circle-check"></i>
                        <span id="txtBtnEmail">Alterar e-mail</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Painel: Alterar Senha ── -->
    <div class="md-panel" id="mdPanelSenha">
        <div class="md-card">
            <div class="md-card-header">
                <i class="fa-solid fa-lock"></i>
                <h3>Alterar Senha</h3>
            </div>
            <form id="formSenha">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <input type="hidden" name="acao" value="alterar_senha">
                <div class="md-card-body">
                    <div class="md-alert" id="alertSenha"></div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="md-label">Senha atual <span style="color:#c92a2a">*</span></label>
                            <div class="md-input-icon-wrap">
                                <input type="password" class="md-input" name="senha_atual" id="inputSenhaAtual" placeholder="••••••••" required>
                                <button type="button" class="md-eye-btn" onclick="mdTogglePw(this)">
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-12"></div>
                        <div class="col-md-6 mb-3">
                            <label class="md-label">Nova senha <span style="color:#c92a2a">*</span></label>
                            <div class="md-input-icon-wrap">
                                <input type="password" class="md-input" name="nova_senha" id="inputNovaSenha"
                                       placeholder="Mínimo 6 caracteres" required oninput="mdCheckStrength(this.value)">
                                <button type="button" class="md-eye-btn" onclick="mdTogglePw(this)">
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </div>
                            <div class="md-strength" id="strengthBars">
                                <div class="md-strength-bar" id="sb1"></div>
                                <div class="md-strength-bar" id="sb2"></div>
                                <div class="md-strength-bar" id="sb3"></div>
                                <div class="md-strength-bar" id="sb4"></div>
                            </div>
                            <div class="md-strength-label" id="strengthLabel"></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="md-label">Confirmar nova senha <span style="color:#c92a2a">*</span></label>
                            <div class="md-input-icon-wrap">
                                <input type="password" class="md-input" name="confirma_senha" id="inputConfirmaSenha"
                                       placeholder="Repita a nova senha" required>
                                <button type="button" class="md-eye-btn" onclick="mdTogglePw(this)">
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="md-card-footer">
                    <button type="submit" class="md-btn-danger" id="btnSalvarSenha">
                        <i class="fa-solid fa-key"></i>
                        <span id="txtBtnSenha">Alterar senha</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div><!-- /md-page -->

<script>
(function () {
    var MD_ACTION = '<?= ACTION_URL ?>/meus_dados.php';

    /* ── Tabs ── */
    window.mdSwitchTab = function (id, btn) {
        document.querySelectorAll('.md-panel').forEach(function (p) { p.classList.remove('active'); });
        document.querySelectorAll('.md-tab').forEach(function (b)  { b.classList.remove('active'); });
        document.getElementById('mdPanel' + id.charAt(0).toUpperCase() + id.slice(1)).classList.add('active');
        btn.classList.add('active');
    };

    /* ── Toggle senha ── */
    window.mdTogglePw = function (btn) {
        var inp = btn.parentElement.querySelector('input');
        var showing = inp.type === 'text';
        inp.type = showing ? 'password' : 'text';
        btn.querySelector('i').className = showing ? 'fa-regular fa-eye' : 'fa-regular fa-eye-slash';
    };

    /* ── Força da senha ── */
    window.mdCheckStrength = function (v) {
        var score = 0;
        if (v.length >= 6)  score++;
        if (v.length >= 10) score++;
        if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
        if (/[0-9]/.test(v) && /[^A-Za-z0-9]/.test(v)) score++;

        var colors = ['#fa5252','#fd7e14','#fcc419','#40c057'];
        var labels = ['Muito fraca','Fraca','Média','Forte'];
        for (var i = 1; i <= 4; i++) {
            var bar = document.getElementById('sb' + i);
            bar.style.background = i <= score ? colors[score - 1] : '#e9ecef';
        }
        document.getElementById('strengthLabel').textContent = v.length ? labels[score - 1] || '' : '';
        document.getElementById('strengthLabel').style.color = score > 0 ? colors[score - 1] : '#868e96';
    };

    /* ── Alerta inline ── */
    function showAlert(id, type, msg) {
        var el = document.getElementById(id);
        el.className = 'md-alert show ' + type;
        el.innerHTML = '<i class="fa-solid ' + (type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation') + '"></i> ' + msg;
        setTimeout(function () { el.classList.remove('show'); }, 5000);
    }

    /* ── Preview foto ── */
    var inputFoto = document.getElementById('inputFoto');
    inputFoto.addEventListener('change', function () {
        var file = this.files[0];
        if (!file) return;
        if (file.size > 5 * 1024 * 1024) {
            showAlert('alertDados', 'error', 'A imagem não pode exceder 5 MB.');
            this.value = '';
            return;
        }
        var reader = new FileReader();
        reader.onload = function (e) {
            var src = e.target.result;
            // Cartão de perfil
            var wrapCard = document.getElementById('mdAvatarWrap');
            var imgCard = document.getElementById('mdAvatarImg');
            var initCard = document.getElementById('mdAvatarInitials');
            if (!imgCard) {
                imgCard = document.createElement('img');
                imgCard.id = 'mdAvatarImg';
                imgCard.className = 'md-avatar-img';
                imgCard.alt = 'Foto';
                wrapCard.insertBefore(imgCard, wrapCard.firstChild);
            }
            if (initCard) initCard.style.display = 'none';
            imgCard.src = src;
            imgCard.style.display = 'block';

            // Preview no form
            var previewWrap = document.getElementById('fotoPreviewWrap');
            var initPrev = document.getElementById('fotoPreviewInitials');
            var imgPrev  = document.getElementById('fotoPreviewImg');
            if (!imgPrev) {
                imgPrev = document.createElement('img');
                imgPrev.id = 'fotoPreviewImg';
                imgPrev.style.cssText = 'width:72px;height:72px;border-radius:50%;object-fit:cover;border:2px solid #e9ecef;';
                imgPrev.alt = 'Foto';
                previewWrap.appendChild(imgPrev);
            }
            if (initPrev) initPrev.style.display = 'none';
            imgPrev.src = src;
            imgPrev.style.display = 'block';

            // Passa base64 para o campo oculto
            document.getElementById('fotoDataInput').value = src;
        };
        reader.readAsDataURL(file);
    });

    /* ── POST genérico ── */
    function postForm(formId, btnId, txtId, alertId, onSuccess) {
        document.getElementById(formId).addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = document.getElementById(btnId);
            var txt = document.getElementById(txtId);
            var original = txt.textContent;
            btn.disabled = true;
            txt.textContent = 'Aguarde...';

            fetch(MD_ACTION, { method: 'POST', body: new FormData(this) })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (j.success) {
                        showAlert(alertId, 'success', j.message);
                        if (onSuccess) onSuccess(j);
                    } else {
                        showAlert(alertId, 'error', j.message || 'Erro ao processar.');
                    }
                })
                .catch(function () {
                    showAlert(alertId, 'error', 'Falha ao comunicar com o servidor.');
                })
                .finally(function () {
                    btn.disabled = false;
                    txt.textContent = original;
                });
        });
    }

    /* Dados pessoais */
    postForm('formDados', 'btnSalvarDados', 'txtBtnDados', 'alertDados', function (j) {
        if (j.data && j.data.USU_NOME) {
            document.getElementById('mdNomeDisplay').textContent = j.data.USU_NOME;
        }
    });

    /* Alterar e-mail */
    postForm('formEmail', 'btnSalvarEmail', 'txtBtnEmail', 'alertEmail', function (j) {
        if (j.novo_email) {
            document.getElementById('emailAtualDisplay').textContent = j.novo_email;
        }
        document.getElementById('formEmail').reset();
    });

    /* Alterar senha */
    postForm('formSenha', 'btnSalvarSenha', 'txtBtnSenha', 'alertSenha', function () {
        document.getElementById('formSenha').reset();
        document.querySelectorAll('#mdPanelSenha .md-strength-bar').forEach(function (b) {
            b.style.background = '#e9ecef';
        });
        document.getElementById('strengthLabel').textContent = '';
    });

})();
</script>
