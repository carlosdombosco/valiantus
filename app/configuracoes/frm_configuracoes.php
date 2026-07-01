<?php
if (!defined('PATH_INC')) require_once __DIR__ . '/../../inc/config.php';
require_once PATH_INC . '/db.php';
require_once PATH_INC . '/csrf.php';
$csrf = csrf_token();

// Garante tabela e colunas
$pdo->exec("CREATE TABLE IF NOT EXISTS `tb_configuracoes` (
    `CFG_CODIGO_PK`         INT UNSIGNED  NOT NULL DEFAULT 1,
    `CFG_RAZAO_SOCIAL`      VARCHAR(200)  NOT NULL DEFAULT '',
    `CFG_ENDERECO`          VARCHAR(300)  NOT NULL DEFAULT '',
    `CFG_BAIRRO`            VARCHAR(100)  NOT NULL DEFAULT '',
    `CFG_CIDADE`            VARCHAR(100)  NOT NULL DEFAULT '',
    `CFG_UF`                CHAR(2)       NOT NULL DEFAULT '',
    `CFG_CEP`               VARCHAR(10)   NOT NULL DEFAULT '',
    `CFG_FONE`              VARCHAR(50)   NOT NULL DEFAULT '',
    `CFG_EMAIL`             VARCHAR(150)  NOT NULL DEFAULT '',
    `CFG_SITE`              VARCHAR(150)  NOT NULL DEFAULT '',
    `CFG_CNPJ`              VARCHAR(20)   NOT NULL DEFAULT '',
    `CFG_NOME_RESPONSAVEL`  VARCHAR(150)  NOT NULL DEFAULT '',
    `CFG_CARGO_RESPONSAVEL` VARCHAR(100)  NOT NULL DEFAULT '',
    `CFG_LOGO_PATH`         VARCHAR(500)  NOT NULL DEFAULT '',
    `CFG_ASSINATURA_PATH`   VARCHAR(500)  NOT NULL DEFAULT '',
    `CFG_TEXTO_ESTATUTO`    MEDIUMTEXT    NULL,
    `CFG_TEXTO_REGIMENTO`   MEDIUMTEXT    NULL,
    `CFG_TEXTO_TERMO`       MEDIUMTEXT    NULL,
    `CFG_UPDATED_AT`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`CFG_CODIGO_PK`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$pdo->exec("INSERT IGNORE INTO `tb_configuracoes` (CFG_CODIGO_PK) VALUES (1)");
foreach ([
    'CFG_TEXTO_ESTATUTO    MEDIUMTEXT    NULL',
    'CFG_TEXTO_REGIMENTO   MEDIUMTEXT    NULL',
    'CFG_TEXTO_TERMO       MEDIUMTEXT    NULL',
    "CFG_SICOOB_CLIENT_ID      VARCHAR(200) NOT NULL DEFAULT ''",
    "CFG_SICOOB_CLIENT_SECRET  VARCHAR(200) NOT NULL DEFAULT ''",
    "CFG_SICOOB_COOPERATIVA    VARCHAR(10)  NOT NULL DEFAULT ''",
    "CFG_SICOOB_CONTA          VARCHAR(20)  NOT NULL DEFAULT ''",
    "CFG_SICOOB_MODALIDADE     TINYINT      NOT NULL DEFAULT 1",
    "CFG_SICOOB_CARTEIRA       TINYINT      NOT NULL DEFAULT 1",
    "CFG_SICOOB_NUM_CONVENIO   VARCHAR(20)  NOT NULL DEFAULT ''",
    "CFG_SICOOB_CERT_PATH      VARCHAR(500) NOT NULL DEFAULT ''",
    "CFG_SICOOB_CERT_KEY       VARCHAR(500) NOT NULL DEFAULT ''",
    "CFG_SICOOB_AMBIENTE       VARCHAR(10)  NOT NULL DEFAULT 'producao'",
] as $def) {
    $col = strtok($def, ' ');
    try { $pdo->exec("ALTER TABLE `tb_configuracoes` ADD COLUMN $def"); } catch (Throwable $e) {}
}

$cfg = $pdo->query("SELECT * FROM tb_configuracoes WHERE CFG_CODIGO_PK = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
$v   = fn(string $k, string $default = '') => htmlspecialchars($cfg[$k] ?? $default, ENT_QUOTES, 'UTF-8');
$vt  = fn(string $k) => htmlspecialchars($cfg[$k] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');

$UFS = ['AC','AL','AM','AP','BA','CE','DF','ES','GO','MA','MG','MS','MT',
        'PA','PB','PE','PI','PR','RJ','RN','RO','RR','RS','SC','SE','SP','TO'];
?>
<link rel="stylesheet" href="../valiantus-tables.css">

<style>
/* ── Page ── */
.cfg-page { max-width: 960px; margin: 0 auto; }

/* ── Tabs ── */
.cfg-tabs {
    display: flex; gap: 4px; margin-bottom: 0;
    border-bottom: 2px solid #e9ecef;
    padding: 0 4px;
}
.cfg-tab {
    height: 44px; padding: 0 22px;
    border: none; background: transparent; cursor: pointer;
    font-size: 13px; font-weight: 700; color: #868e96;
    border-bottom: 3px solid transparent; margin-bottom: -2px;
    display: inline-flex; align-items: center; gap: 8px;
    transition: color .15s, border-color .15s;
    border-radius: 10px 10px 0 0;
}
.cfg-tab:hover { color: #3b5bdb; background: #f0f3ff; }
.cfg-tab.active { color: #3b5bdb; border-bottom-color: #3b5bdb; background: #fff; }

/* ── Card ── */
.cfg-card {
    background: #fff; border: 1px solid #e9ecef;
    border-top: none;
    border-radius: 0 0 16px 16px;
    box-shadow: 0 4px 20px rgba(30,40,80,.07);
    overflow: hidden;
}
.cfg-panel { display: none; }
.cfg-panel.active { display: block; }

/* ── Card header ── */
.cfg-card-header {
    padding: 18px 24px; border-bottom: 1px solid #e9ecef;
    display: flex; align-items: center; gap: 12px;
    background: linear-gradient(135deg,#3b5bdb 0%,#2f52d6 100%); color: #fff;
}
.cfg-card-header h3 { margin: 0; font-size: 16px; font-weight: 700; }
.cfg-card-header i {
    width: 36px; height: 36px; background: rgba(255,255,255,.15);
    border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 14px;
}
.cfg-card-header.green { background: linear-gradient(135deg,#2f9e44,#27873a); }
.cfg-card-header.dark  { background: linear-gradient(135deg,#1a1d2e,#2c3041); }

.cfg-card-body  { padding: 24px; }
.cfg-section    { margin-bottom: 22px; }
.cfg-section-title {
    font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .08em;
    color: #3b5bdb; margin-bottom: 14px;
    display: flex; align-items: center; gap: 8px;
    padding-bottom: 8px; border-bottom: 1px solid #e9ecef;
}
label.cfg-label { font-size: 12px; font-weight: 700; color: #495057; margin-bottom: 5px; display: block; }
.cfg-input {
    width: 100%; height: 40px; border: 1.5px solid #dbe2ea; border-radius: 9px;
    padding: 0 12px; font-size: 13.5px; color: #1a1d2e; background: #fff; transition: .15s;
}
.cfg-input:focus { border-color: #3b5bdb; box-shadow: 0 0 0 3px rgba(59,91,219,.1); outline: none; }

/* Textarea para documentos */
.cfg-textarea {
    width: 100%; border: 1.5px solid #dbe2ea; border-radius: 9px;
    padding: 12px 14px; font-size: 12.5px; font-family: inherit;
    color: #1a1d2e; background: #fff; resize: vertical; transition: .15s;
    min-height: 340px; line-height: 1.7;
}
.cfg-textarea:focus { border-color: #3b5bdb; box-shadow: 0 0 0 3px rgba(59,91,219,.1); outline: none; }
.cfg-textarea-hint {
    font-size: 11px; color: #868e96; margin-top: 5px;
    display: flex; align-items: center; gap: 6px;
}

/* Imagens */
.cfg-img-wrap {
    border: 2px dashed #dbe2ea; border-radius: 12px; padding: 20px;
    text-align: center; transition: .2s; background: #fafbfc; cursor: pointer;
}
.cfg-img-wrap:hover { border-color: #3b5bdb; background: #f0f3ff; }
.cfg-img-preview {
    max-width: 200px; max-height: 120px; object-fit: contain;
    border-radius: 8px; margin-bottom: 10px; display: none;
}
.cfg-img-preview.has-img { display: block; margin: 0 auto 10px; }
.cfg-img-label { font-size: 12px; color: #868e96; }

/* Footer */
.cfg-footer {
    padding: 16px 24px; border-top: 1px solid #e9ecef;
    display: flex; justify-content: flex-end; gap: 10px;
    background: #f8f9fb;
}
.cfg-btn-save {
    height: 42px; padding: 0 28px; border-radius: 11px;
    background: #3b5bdb; color: #fff; border: none;
    font-size: 14px; font-weight: 700; cursor: pointer; transition: .15s;
    display: inline-flex; align-items: center; gap: 8px;
}
.cfg-btn-save:hover { background: #2f52d6; transform: translateY(-1px); box-shadow: 0 6px 16px rgba(59,91,219,.25); }
.cfg-btn-preview {
    height: 42px; padding: 0 20px; border-radius: 11px;
    background: #e9ecef; color: #495057; border: none;
    font-size: 13px; font-weight: 700; cursor: pointer; transition: .15s;
    display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
}
.cfg-btn-preview:hover { background: #dee2e6; }
</style>

<div class="vt-page cfg-page">

    <!-- Tabs -->
    <div class="cfg-tabs">
        <button class="cfg-tab active" onclick="cfgTab('empresa', this)">
            <i class="fa-solid fa-building"></i> Empresa
        </button>
        <button class="cfg-tab" onclick="cfgTab('imagens', this)">
            <i class="fa-solid fa-image"></i> Logo e Assinatura
        </button>
        <button class="cfg-tab" onclick="cfgTab('documentos', this)">
            <i class="fa-solid fa-file-lines"></i> Documentos
        </button>
        <button class="cfg-tab" onclick="cfgTab('sicoob', this)">
            <i class="fa-solid fa-landmark"></i> Sicoob / Boleto
        </button>
    </div>

    <div class="cfg-card">

        <!-- ══════ TAB: Empresa ══════ -->
        <div class="cfg-panel active" id="cfgPanelEmpresa">
            <div class="cfg-card-header">
                <i class="fa-solid fa-building"></i>
                <h3>Dados da Empresa</h3>
            </div>
            <form id="formEmpresa" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <input type="hidden" name="acao" value="salvar">

                <div class="cfg-card-body">
                    <div class="cfg-section">
                        <div class="cfg-section-title"><i class="fa-solid fa-id-card"></i>Identificação</div>
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="cfg-label">Razão Social / Nome</label>
                                <input type="text" class="cfg-input" name="razao_social" value="<?= $v('CFG_RAZAO_SOCIAL') ?>" placeholder="Nome da associação...">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="cfg-label">CNPJ</label>
                                <input type="text" class="cfg-input" name="cnpj" value="<?= $v('CFG_CNPJ') ?>" placeholder="00.000.000/0000-00">
                            </div>
                        </div>
                    </div>

                    <div class="cfg-section">
                        <div class="cfg-section-title"><i class="fa-solid fa-location-dot"></i>Endereço</div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="cfg-label">Endereço</label>
                                <input type="text" class="cfg-input" name="endereco" value="<?= $v('CFG_ENDERECO') ?>" placeholder="Rua, número...">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="cfg-label">Bairro</label>
                                <input type="text" class="cfg-input" name="bairro" value="<?= $v('CFG_BAIRRO') ?>" placeholder="Bairro">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="cfg-label">CEP</label>
                                <input type="text" class="cfg-input" name="cep" value="<?= $v('CFG_CEP') ?>" placeholder="00000-000">
                            </div>
                            <div class="col-md-5 mb-3">
                                <label class="cfg-label">Cidade</label>
                                <input type="text" class="cfg-input" name="cidade" value="<?= $v('CFG_CIDADE') ?>" placeholder="Cidade">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="cfg-label">UF</label>
                                <select class="cfg-input" name="uf">
                                    <option value="">—</option>
                                    <?php foreach ($UFS as $uf): ?>
                                        <option value="<?= $uf ?>" <?= ($cfg['CFG_UF'] ?? '') === $uf ? 'selected' : '' ?>><?= $uf ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="cfg-label">Telefone</label>
                                <input type="text" class="cfg-input" name="fone" value="<?= $v('CFG_FONE') ?>" placeholder="(00) 0000-0000">
                            </div>
                            <div class="col-md-2 mb-0"></div>
                        </div>
                    </div>

                    <div class="cfg-section" style="margin-bottom:0;">
                        <div class="cfg-section-title"><i class="fa-solid fa-address-book"></i>Contato</div>
                        <div class="row">
                            <div class="col-md-5 mb-3">
                                <label class="cfg-label">E-mail</label>
                                <input type="email" class="cfg-input" name="email" value="<?= $v('CFG_EMAIL') ?>" placeholder="contato@empresa.com.br">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="cfg-label">Site</label>
                                <input type="text" class="cfg-input" name="site" value="<?= $v('CFG_SITE') ?>" placeholder="www.empresa.com.br">
                            </div>
                        </div>
                    </div>

                    <div class="cfg-section" style="margin-bottom:0;">
                        <div class="cfg-section-title"><i class="fa-solid fa-user-tie"></i>Responsável / Assinatura</div>
                        <div class="row">
                            <div class="col-md-7 mb-3">
                                <label class="cfg-label">Nome do Responsável</label>
                                <input type="text" class="cfg-input" name="nome_responsavel" value="<?= $v('CFG_NOME_RESPONSAVEL') ?>" placeholder="Nome completo...">
                            </div>
                            <div class="col-md-5 mb-0">
                                <label class="cfg-label">Cargo / Função</label>
                                <input type="text" class="cfg-input" name="cargo_responsavel" value="<?= $v('CFG_CARGO_RESPONSAVEL') ?>" placeholder="Ex: Diretor, Presidente...">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="cfg-footer">
                    <button type="submit" class="cfg-btn-save">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span class="cfg-btn-txt">Salvar</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- ══════ TAB: Imagens ══════ -->
        <div class="cfg-panel" id="cfgPanelImagens">
            <div class="cfg-card-header dark">
                <i class="fa-solid fa-image"></i>
                <h3>Logo e Assinatura</h3>
            </div>
            <form id="formImagens" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <input type="hidden" name="acao" value="salvar">

                <div class="cfg-card-body">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="cfg-label">Logo da Empresa</label>
                            <div class="cfg-img-wrap" onclick="document.getElementById('inputLogo').click()">
                                <img id="previewLogo"
                                     src="<?= $v('CFG_LOGO_PATH') ?>"
                                     class="cfg-img-preview <?= !empty($cfg['CFG_LOGO_PATH']) ? 'has-img' : '' ?>"
                                     alt="Logo">
                                <div class="cfg-img-label">
                                    <i class="fa-solid fa-cloud-arrow-up" style="font-size:22px;color:#adb5bd;display:block;margin-bottom:8px;"></i>
                                    Clique para selecionar (PNG, JPG, SVG)
                                </div>
                            </div>
                            <input type="file" id="inputLogo" name="logo" accept="image/*" style="display:none;">
                            <?php if (!empty($cfg['CFG_LOGO_PATH'])): ?>
                                <div style="margin-top:8px;font-size:11px;color:#868e96;">
                                    <i class="fa-solid fa-circle-check" style="color:#2f9e44;"></i>
                                    Logo cadastrada — envie uma nova para substituir
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="cfg-label">Imagem da Assinatura</label>
                            <div class="cfg-img-wrap" onclick="document.getElementById('inputAssin').click()">
                                <img id="previewAssin"
                                     src="<?= $v('CFG_ASSINATURA_PATH') ?>"
                                     class="cfg-img-preview <?= !empty($cfg['CFG_ASSINATURA_PATH']) ? 'has-img' : '' ?>"
                                     alt="Assinatura">
                                <div class="cfg-img-label">
                                    <i class="fa-solid fa-pen-fancy" style="font-size:22px;color:#adb5bd;display:block;margin-bottom:8px;"></i>
                                    Clique para selecionar (PNG, JPG)
                                </div>
                            </div>
                            <input type="file" id="inputAssin" name="assinatura" accept="image/*" style="display:none;">
                            <?php if (!empty($cfg['CFG_ASSINATURA_PATH'])): ?>
                                <div style="margin-top:8px;font-size:11px;color:#868e96;">
                                    <i class="fa-solid fa-circle-check" style="color:#2f9e44;"></i>
                                    Assinatura cadastrada — envie uma nova para substituir
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12">
                            <div style="background:#fff8e1;border:1px solid #ffe08a;border-radius:10px;padding:12px 16px;font-size:12px;color:#664d03;">
                                <i class="fa-solid fa-lightbulb" style="color:#f59f00;margin-right:6px;"></i>
                                <strong>Dica:</strong> Use fundo transparente (PNG) para melhor resultado nos documentos impressos.
                                Tamanho recomendado: logo até 400×200px, assinatura até 600×200px.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="cfg-footer">
                    <button type="submit" class="cfg-btn-save">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span class="cfg-btn-txt">Salvar Imagens</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- ══════ TAB: Documentos ══════ -->
        <div class="cfg-panel" id="cfgPanelDocumentos">
            <div class="cfg-card-header green">
                <i class="fa-solid fa-file-lines"></i>
                <h3>Textos dos Documentos</h3>
            </div>

            <!-- Sub-tabs dos documentos -->
            <div style="display:flex;gap:2px;padding:0 20px;border-bottom:1px solid #e9ecef;background:#f8f9fb;">
                <button class="cfg-doc-stab active" onclick="cfgDocTab('termo', this)"
                        style="height:40px;padding:0 18px;border:none;background:transparent;cursor:pointer;font-size:12px;font-weight:700;color:#868e96;border-bottom:2px solid transparent;margin-bottom:-1px;transition:.15s;">
                    <i class="fa-solid fa-file-signature"></i> Termo de Filiação
                </button>
                <button class="cfg-doc-stab" onclick="cfgDocTab('estatuto', this)"
                        style="height:40px;padding:0 18px;border:none;background:transparent;cursor:pointer;font-size:12px;font-weight:700;color:#868e96;border-bottom:2px solid transparent;margin-bottom:-1px;transition:.15s;">
                    <i class="fa-solid fa-scale-balanced"></i> Estatuto
                </button>
                <button class="cfg-doc-stab" onclick="cfgDocTab('regimento', this)"
                        style="height:40px;padding:0 18px;border:none;background:transparent;cursor:pointer;font-size:12px;font-weight:700;color:#868e96;border-bottom:2px solid transparent;margin-bottom:-1px;transition:.15s;">
                    <i class="fa-solid fa-book-open"></i> Regimento
                </button>
            </div>

            <form id="formDocumentos" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <input type="hidden" name="acao" value="salvar">

                <div class="cfg-card-body" style="padding-top:20px;">

                    <!-- Termo de Filiação -->
                    <div class="cfg-doc-panel active" id="docPanelTermo">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                            <label class="cfg-label" style="margin-bottom:0;">Cláusulas e Condições do Termo de Filiação</label>
                            <a href="<?= APP_URL ?>/impressoes/termo_filiacao.php?pes_id=0&ctr_id=0" target="_blank" class="cfg-btn-preview" style="font-size:12px;height:32px;padding:0 14px;">
                                <i class="fa-solid fa-eye"></i> Pré-visualizar
                            </a>
                        </div>
                        <textarea class="cfg-textarea" name="texto_termo" placeholder="Digite aqui as cláusulas e condições que aparecerão na página 2 do Termo de Filiação...&#10;&#10;Exemplo:&#10;CLÁUSULA 1ª — DA FILIAÇÃO&#10;O presente termo formaliza a adesão do associado às normas...&#10;&#10;CLÁUSULA 2ª — DAS OBRIGAÇÕES&#10;..."><?= $vt('CFG_TEXTO_TERMO') ?></textarea>
                        <div class="cfg-textarea-hint">
                            <i class="fa-solid fa-circle-info" style="color:#3b5bdb;"></i>
                            Este texto aparece na página 2 do Termo de Filiação. Use Enter para parágrafos e separação de cláusulas.
                        </div>
                    </div>

                    <!-- Estatuto -->
                    <div class="cfg-doc-panel" id="docPanelEstatuto" style="display:none;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                            <label class="cfg-label" style="margin-bottom:0;">Texto do Estatuto Social</label>
                            <a href="<?= APP_URL ?>/impressoes/estatuto.php" target="_blank" class="cfg-btn-preview" style="font-size:12px;height:32px;padding:0 14px;">
                                <i class="fa-solid fa-eye"></i> Pré-visualizar
                            </a>
                        </div>
                        <textarea class="cfg-textarea" name="texto_estatuto" placeholder="Digite ou cole aqui o texto completo do Estatuto Social..."><?= $vt('CFG_TEXTO_ESTATUTO') ?></textarea>
                        <div class="cfg-textarea-hint">
                            <i class="fa-solid fa-circle-info" style="color:#3b5bdb;"></i>
                            Este texto aparece no documento de Estatuto Social. Suporta parágrafos (Enter).
                        </div>
                    </div>

                    <!-- Regimento -->
                    <div class="cfg-doc-panel" id="docPanelRegimento" style="display:none;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                            <label class="cfg-label" style="margin-bottom:0;">Texto do Regimento Interno</label>
                            <a href="<?= APP_URL ?>/impressoes/regimento.php" target="_blank" class="cfg-btn-preview" style="font-size:12px;height:32px;padding:0 14px;">
                                <i class="fa-solid fa-eye"></i> Pré-visualizar
                            </a>
                        </div>
                        <textarea class="cfg-textarea" name="texto_regimento" placeholder="Digite ou cole aqui o texto completo do Regimento Interno..."><?= $vt('CFG_TEXTO_REGIMENTO') ?></textarea>
                        <div class="cfg-textarea-hint">
                            <i class="fa-solid fa-circle-info" style="color:#3b5bdb;"></i>
                            Este texto aparece no documento de Regimento Interno. Suporta parágrafos (Enter).
                        </div>
                    </div>

                </div>

                <div class="cfg-footer">
                    <button type="submit" class="cfg-btn-save">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span class="cfg-btn-txt">Salvar Documentos</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- ══════ TAB: Sicoob / Boleto ══════ -->
        <div class="cfg-panel" id="cfgPanelSicoob">
            <div class="cfg-card-header" style="background:linear-gradient(135deg,#1a5c1a,#237023);">
                <i class="fa-solid fa-landmark"></i>
                <h3>Sicoob — Cobrança Bancária</h3>
            </div>
            <form id="formSicoob">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <input type="hidden" name="acao" value="salvar">

                <div class="cfg-card-body">

                    <!-- Ambiente -->
                    <div class="cfg-section">
                        <div class="cfg-section-title"><i class="fa-solid fa-server"></i>Ambiente</div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="cfg-label">Ambiente</label>
                                <select class="cfg-input" name="sicoob_ambiente">
                                    <option value="producao"  <?= ($cfg['CFG_SICOOB_AMBIENTE'] ?? 'producao') === 'producao'  ? 'selected' : '' ?>>Produção</option>
                                    <option value="sandbox"   <?= ($cfg['CFG_SICOOB_AMBIENTE'] ?? '') === 'sandbox'           ? 'selected' : '' ?>>Sandbox (homologação)</option>
                                </select>
                            </div>
                            <div class="col-md-8 mb-3" style="display:flex;align-items:flex-end;">
                                <div id="sicoobStatusBadge" style="padding:8px 16px;border-radius:9px;font-size:12px;font-weight:700;background:#f1f3f5;color:#868e96;display:inline-flex;align-items:center;gap:8px;">
                                    <i class="fa-solid fa-circle-question"></i> Não testado
                                </div>
                                <button type="button" id="btnTestarSicoob"
                                        style="margin-left:10px;height:40px;padding:0 18px;border-radius:9px;border:1.5px solid #237023;background:#fff;color:#237023;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
                                    <i class="fa-solid fa-plug-circle-check"></i> Testar Conexão
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Credenciais OAuth2 -->
                    <div class="cfg-section">
                        <div class="cfg-section-title"><i class="fa-solid fa-key"></i>Credenciais OAuth2</div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="cfg-label">Client ID <span style="color:#c92a2a">*</span></label>
                                <input type="text" class="cfg-input" name="sicoob_client_id"
                                       value="<?= $v('CFG_SICOOB_CLIENT_ID') ?>"
                                       placeholder="Ex: minha-app-sicoob">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="cfg-label">Client Secret <span style="color:#c92a2a">*</span></label>
                                <div style="position:relative;">
                                    <input type="password" class="cfg-input" name="sicoob_client_secret" id="inputSecret"
                                           value="<?= $v('CFG_SICOOB_CLIENT_SECRET') ?>"
                                           placeholder="••••••••••••••••" style="padding-right:42px;">
                                    <button type="button" onclick="toggleSecret()" tabindex="-1"
                                            style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#868e96;font-size:14px;">
                                        <i class="fa-solid fa-eye" id="iconSecret"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Dados da Conta -->
                    <div class="cfg-section">
                        <div class="cfg-section-title"><i class="fa-solid fa-piggy-bank"></i>Dados da Conta</div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="cfg-label">Nº Conta <span style="color:#c92a2a">*</span></label>
                                <input type="text" class="cfg-input" name="sicoob_conta"
                                       value="<?= $v('CFG_SICOOB_CONTA') ?>"
                                       placeholder="Ex: 12345678">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="cfg-label">Nº Convênio</label>
                                <input type="text" class="cfg-input" name="sicoob_num_convenio"
                                       value="<?= $v('CFG_SICOOB_NUM_CONVENIO') ?>"
                                       placeholder="Ex: 123456">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="cfg-label">Modalidade</label>
                                <input type="number" class="cfg-input" name="sicoob_modalidade"
                                       value="<?= htmlspecialchars((string)($cfg['CFG_SICOOB_MODALIDADE'] ?? 1)) ?>"
                                       min="1" max="9">
                            </div>
                            <div class="col-md-1 mb-3">
                                <label class="cfg-label">Carteira</label>
                                <input type="number" class="cfg-input" name="sicoob_carteira"
                                       value="<?= htmlspecialchars((string)($cfg['CFG_SICOOB_CARTEIRA'] ?? 1)) ?>"
                                       min="1" max="9">
                            </div>
                        </div>
                    </div>

                    <!-- CNAB Remessa / Retorno -->
                    <div class="cfg-section">
                        <div class="cfg-section-title"><i class="fa-solid fa-file-arrow-up"></i>CNAB — Remessa e Retorno</div>

                        <div class="row">
                            <div class="col-md-2 mb-3">
                                <label class="cfg-label">Banco</label>
                                <input type="text" class="cfg-input" name="cnab_banco"
                                       value="<?= $v('CFG_CNAB_BANCO', '756') ?>"
                                       maxlength="10" placeholder="756">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="cfg-label">Agência <span style="color:#c92a2a">*</span></label>
                                <input type="text" class="cfg-input" name="cnab_agencia"
                                       value="<?= $v('CFG_CNAB_AGENCIA') ?>"
                                       maxlength="10" placeholder="Ex: 3182">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="cfg-label">Díg. Agência</label>
                                <input type="text" class="cfg-input" name="cnab_agencia_digito"
                                       value="<?= $v('CFG_CNAB_AGENCIA_DIGITO') ?>"
                                       maxlength="1" placeholder="0">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="cfg-label">Conta <span style="color:#c92a2a">*</span></label>
                                <input type="text" class="cfg-input" name="cnab_conta"
                                       value="<?= $v('CFG_CNAB_CONTA') ?>"
                                       maxlength="20" placeholder="Ex: 12345678">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="cfg-label">Díg. Conta</label>
                                <input type="text" class="cfg-input" name="cnab_conta_digito"
                                       value="<?= $v('CFG_CNAB_CONTA_DIGITO') ?>"
                                       maxlength="1" placeholder="0">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-2 mb-3">
                                <label class="cfg-label">DV Agência/Conta</label>
                                <input type="text" class="cfg-input" name="cnab_dv_agencia_conta"
                                       value="<?= $v('CFG_CNAB_DV_AGENCIA_CONTA') ?>"
                                       maxlength="1" placeholder="0">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="cfg-label">Código do Cedente <span style="color:#c92a2a">*</span></label>
                                <input type="text" class="cfg-input" name="cnab_codigo_cedente"
                                       value="<?= $v('CFG_CNAB_CODIGO_CEDENTE') ?>"
                                       maxlength="20" placeholder="Ex: 123456">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="cfg-label">CNPJ do Cedente <span style="color:#c92a2a">*</span></label>
                                <input type="text" class="cfg-input" name="cnab_cnpj"
                                       value="<?= htmlspecialchars(preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cfg['CFG_CNAB_CNPJ'] ?? '')) ?>"
                                       maxlength="18" placeholder="00.000.000/0000-00">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="cfg-label">Tipo de Carteira</label>
                                <select class="cfg-input" name="cnab_tipo_carteira">
                                    <?php foreach (['Registrada','Simples','Eletronica'] as $tc): ?>
                                        <option value="<?= $tc ?>" <?= ($cfg['CFG_CNAB_TIPO_CARTEIRA'] ?? 'Registrada') === $tc ? 'selected' : '' ?>>
                                            <?= $tc ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="cfg-label">Endereço do Cedente (impresso no boleto)</label>
                                <input type="text" class="cfg-input" name="cnab_endereco"
                                       value="<?= $v('CFG_CNAB_ENDERECO') ?>"
                                       maxlength="300" placeholder="Ex: Rua das Flores, 100, Centro, Cidade - UF, CEP 00000-000">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="cfg-label">Layout CNAB</label>
                                <select class="cfg-input" name="cnab_layout">
                                    <option value="CNAB240" <?= ($cfg['CFG_CNAB_LAYOUT'] ?? 'CNAB240') === 'CNAB240' ? 'selected' : '' ?>>CNAB 240</option>
                                    <option value="CNAB400" <?= ($cfg['CFG_CNAB_LAYOUT'] ?? '') === 'CNAB400' ? 'selected' : '' ?>>CNAB 400</option>
                                </select>
                            </div>
                            <div class="col-md-9 mb-3">
                                <label class="cfg-label">Pasta padrão para salvar remessa</label>
                                <input type="text" class="cfg-input" name="cnab_remessa_path"
                                       value="<?= $v('CFG_CNAB_REMESSA_PATH', 'C:\remessa') ?>"
                                       maxlength="500" placeholder="Ex: C:\remessa">
                            </div>
                        </div>
                    </div>

                    <!-- Certificado mTLS -->
                    <div class="cfg-section" style="margin-bottom:0;">
                        <div class="cfg-section-title"><i class="fa-solid fa-certificate"></i>Certificado mTLS (A1)</div>
                        <div style="background:#fff8e1;border:1px solid #ffe08a;border-radius:10px;padding:12px 16px;font-size:12px;color:#664d03;margin-bottom:16px;">
                            <i class="fa-solid fa-triangle-exclamation" style="color:#f59f00;margin-right:6px;"></i>
                            A API Sicoob exige certificado digital A1 vinculado ao seu <strong>client_id</strong>.
                            Informe os caminhos dos arquivos <code>.pem</code> no servidor.
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="cfg-label">Caminho do Certificado (.pem / .crt)</label>
                                <input type="text" class="cfg-input" name="sicoob_cert_path"
                                       value="<?= $v('CFG_SICOOB_CERT_PATH') ?>"
                                       placeholder="Ex: C:\certs\sicoob.crt">
                                <?php if (!empty($cfg['CFG_SICOOB_CERT_PATH'])): ?>
                                    <div style="margin-top:4px;font-size:11px;color:<?= file_exists($cfg['CFG_SICOOB_CERT_PATH']) ? '#2f9e44' : '#c92a2a' ?>;">
                                        <i class="fa-solid <?= file_exists($cfg['CFG_SICOOB_CERT_PATH']) ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                                        <?= file_exists($cfg['CFG_SICOOB_CERT_PATH']) ? 'Arquivo encontrado' : 'Arquivo NÃO encontrado no servidor' ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="cfg-label">Caminho da Chave Privada (.key / .pem)</label>
                                <input type="text" class="cfg-input" name="sicoob_cert_key"
                                       value="<?= $v('CFG_SICOOB_CERT_KEY') ?>"
                                       placeholder="Ex: C:\certs\sicoob.key">
                                <?php if (!empty($cfg['CFG_SICOOB_CERT_KEY'])): ?>
                                    <div style="margin-top:4px;font-size:11px;color:<?= file_exists($cfg['CFG_SICOOB_CERT_KEY']) ? '#2f9e44' : '#c92a2a' ?>;">
                                        <i class="fa-solid <?= file_exists($cfg['CFG_SICOOB_CERT_KEY']) ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                                        <?= file_exists($cfg['CFG_SICOOB_CERT_KEY']) ? 'Arquivo encontrado' : 'Arquivo NÃO encontrado no servidor' ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="cfg-footer">
                    <button type="submit" class="cfg-btn-save">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span class="cfg-btn-txt">Salvar Configurações Sicoob</span>
                    </button>
                </div>
            </form>
        </div>

    </div><!-- /.cfg-card -->

</div><!-- /.cfg-page -->

<script>
document.addEventListener('DOMContentLoaded', function () {
    var CFG_ACTION = '<?= ACTION_URL ?>/configuracoes.php';

    /* ── Tab principal ── */
    window.cfgTab = function (name, btn) {
        document.querySelectorAll('.cfg-panel').forEach(function (p) { p.classList.remove('active'); });
        document.querySelectorAll('.cfg-tab').forEach(function (b) { b.classList.remove('active'); });
        document.getElementById('cfgPanel' + name.charAt(0).toUpperCase() + name.slice(1)).classList.add('active');
        btn.classList.add('active');
    };

    /* ── Sub-tab documentos ── */
    window.cfgDocTab = function (name, btn) {
        document.querySelectorAll('.cfg-doc-panel').forEach(function (p) { p.style.display = 'none'; });
        document.querySelectorAll('.cfg-doc-stab').forEach(function (b) {
            b.style.color = '#868e96';
            b.style.borderBottomColor = 'transparent';
        });
        document.getElementById('docPanel' + name.charAt(0).toUpperCase() + name.slice(1)).style.display = 'block';
        btn.style.color = '#3b5bdb';
        btn.style.borderBottomColor = '#3b5bdb';
    };

    /* ── Preview de imagem ── */
    function setupPreview(inputId, previewId) {
        var input = document.getElementById(inputId);
        if (!input) return;
        input.addEventListener('change', function () {
            var file = this.files[0];
            if (!file) return;
            var reader = new FileReader();
            reader.onload = function (e) {
                var img = document.getElementById(previewId);
                img.src = e.target.result;
                img.classList.add('has-img');
            };
            reader.readAsDataURL(file);
        });
    }
    setupPreview('inputLogo',  'previewLogo');
    setupPreview('inputAssin', 'previewAssin');

    /* ── Submit genérico ── */
    function bindForm(formId) {
        var form = document.getElementById(formId);
        if (!form) return;
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = form.querySelector('.cfg-btn-save');
            var txt = form.querySelector('.cfg-btn-txt');
            btn.disabled = true;
            txt.textContent = 'Salvando...';

            fetch(CFG_ACTION, { method: 'POST', body: new FormData(form) })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (j.success) {
                        Swal.fire({ icon: 'success', title: 'Salvo!', text: j.message, timer: 2000, showConfirmButton: false });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Erro', text: j.message || 'Não foi possível salvar.' });
                    }
                })
                .catch(function () {
                    Swal.fire({ icon: 'error', title: 'Erro', text: 'Falha ao comunicar com o servidor.' });
                })
                .finally(function () {
                    btn.disabled = false;
                    txt.textContent = btn.dataset.label || 'Salvar';
                });
        });
    }
    bindForm('formEmpresa');
    bindForm('formImagens');
    bindForm('formDocumentos');
    bindForm('formSicoob');

    /* ── Toggle secret visibility ── */
    window.toggleSecret = function () {
        var inp  = document.getElementById('inputSecret');
        var icon = document.getElementById('iconSecret');
        if (inp.type === 'password') {
            inp.type = 'text';
            icon.className = 'fa-solid fa-eye-slash';
        } else {
            inp.type = 'password';
            icon.className = 'fa-solid fa-eye';
        }
    };

    /* ── Testar Conexão Sicoob ── */
    document.getElementById('btnTestarSicoob').addEventListener('click', function () {
        var badge = document.getElementById('sicoobStatusBadge');
        var btn   = this;
        btn.disabled = true;
        badge.style.background = '#e7f5ff';
        badge.style.color      = '#1971c2';
        badge.innerHTML        = '<i class="fa-solid fa-circle-notch fa-spin"></i> Testando…';

        fetch(CFG_ACTION + '?acao=testar_sicoob')
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (j.success) {
                    badge.style.background = '#ebfbee';
                    badge.style.color      = '#2f9e44';
                    badge.innerHTML        = '<i class="fa-solid fa-circle-check"></i> Conectado';
                } else {
                    badge.style.background = '#fff5f5';
                    badge.style.color      = '#c92a2a';
                    badge.innerHTML        = '<i class="fa-solid fa-circle-xmark"></i> ' + (j.message || 'Falha na conexão');
                }
            })
            .catch(function () {
                badge.style.background = '#fff5f5';
                badge.style.color      = '#c92a2a';
                badge.innerHTML        = '<i class="fa-solid fa-circle-xmark"></i> Erro de rede';
            })
            .finally(function () { btn.disabled = false; });
    });
});
</script>
