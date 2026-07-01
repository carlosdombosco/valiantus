<?php
if (!defined('PATH_INC')) require_once __DIR__ . '/../../inc/config.php';
require_once PATH_INC . '/csrf.php';
$csrf = csrf_token();

/* ── Setup + carga de itens de vistoria ── */
$visItemsDb = [];
try {
    /* 1. Cria tabela se não existir */
    $pdo->exec("CREATE TABLE IF NOT EXISTS tb_itens_vistoria (
        ITV_CODIGO_PK INT          NOT NULL AUTO_INCREMENT,
        ITV_CHAVE     VARCHAR(80)  NOT NULL,
        ITV_DESCRICAO VARCHAR(120) NOT NULL,
        ITV_ATIVO     CHAR(1)     NOT NULL DEFAULT 'S',
        ITV_ORDEM     INT         NOT NULL DEFAULT 0,
        PRIMARY KEY (ITV_CODIGO_PK),
        UNIQUE KEY uq_chave (ITV_CHAVE)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    /* 2. Adiciona colunas novas caso a tabela já existia sem elas */
    $existingCols = array_map('strtolower',
        $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
                      WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME   = 'tb_itens_vistoria'")->fetchAll(PDO::FETCH_COLUMN)
    );
    if (!in_array('itv_ativo', $existingCols))
        $pdo->exec("ALTER TABLE tb_itens_vistoria ADD COLUMN ITV_ATIVO CHAR(1) NOT NULL DEFAULT 'S'");
    if (!in_array('itv_ordem', $existingCols))
        $pdo->exec("ALTER TABLE tb_itens_vistoria ADD COLUMN ITV_ORDEM INT NOT NULL DEFAULT 0");

    /* 3. Garante que linhas antigas (NULL) fiquem ativas */
    $pdo->exec("UPDATE tb_itens_vistoria SET ITV_ATIVO = 'S' WHERE ITV_ATIVO IS NULL OR ITV_ATIVO = ''");

    /* 4. Seed: insere os 30 itens padrão apenas se a tabela estiver vazia */
    if (!(int)$pdo->query("SELECT COUNT(*) FROM tb_itens_vistoria")->fetchColumn()) {
        $seedItems = [
            ['ar_condicionado',          'Ar condicionado',           1],
            ['banco_couro',              'Banco de couro',            2],
            ['direcao_hidraulica',       'Direção hidráulica',        3],
            ['vidro_eletrico_dianteiro', 'Vidro elétrico dianteiro',  4],
            ['vidro_eletrico_traseiro',  'Vidro elétrico traseiro',   5],
            ['trava_eletrica',           'Trava elétrica',            6],
            ['teto_solar',               'Teto solar',                7],
            ['alarme',                   'Alarme',                    8],
            ['rastreador',               'Rastreador',                9],
            ['central_multimidia',       'Central multimídia',        10],
            ['computador_bordo',         'Computador de bordo',       11],
            ['sensor_estacionamento',    'Sensor de estacionamento',  12],
            ['airbags',                  'Airbags',                   13],
            ['roda_liga_leve',           'Roda liga leve',            14],
            ['tracao_4x4',               'Tração 4x4',                15],
            ['cambio_automatico',        'Câmbio automático',         16],
            ['pelicula',                 'Película',                  17],
            ['desembacador',             'Desembaçador',              18],
            ['farol_milha',              'Farol de milha',            19],
            ['farol_neblina',            'Farol de neblina',          20],
            ['limpador_traseiro',        'Limpador traseiro',         21],
            ['antena_externa',           'Antena externa',            22],
            ['retrovisor_regulagem',     'Retrovisor c/ regulagem',   23],
            ['cinto_3p_dianteiro',       'Cinto 3pt dianteiro',       24],
            ['cinto_3p_traseiro',        'Cinto 3pt traseiro',        25],
            ['chave_codificada',         'Chave codificada',          26],
            ['calotas',                  'Calotas',                   27],
            ['cd_player',                'CD Player',                 28],
            ['santo_antonio',            'Santo antônio',             29],
            ['kit_gas',                  'Kit gás',                   30],
        ];
        $ins = $pdo->prepare("INSERT IGNORE INTO tb_itens_vistoria (ITV_CHAVE, ITV_DESCRICAO, ITV_ATIVO, ITV_ORDEM) VALUES (?,?,'S',?)");
        foreach ($seedItems as [$chave, $desc, $ordem]) $ins->execute([$chave, $desc, $ordem]);
    }

    /* 5. Carrega itens ativos para renderizar o modal */
    $visItemsDb = $pdo->query(
        "SELECT ITV_CODIGO_PK, ITV_CHAVE, ITV_DESCRICAO
           FROM tb_itens_vistoria
          WHERE ITV_ATIVO = 'S'
          ORDER BY ITV_ORDEM, ITV_DESCRICAO"
    )->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $_e) {
    error_log('[modal_vistoria] ' . $_e->getMessage() . ' in ' . $_e->getFile() . ':' . $_e->getLine());
    $visItemsDb = [];
}
?>
<style>
    #modalVeiculo .title-icon {
        width: 28px;
        height: 28px;
        background: #eef3ff;
        color: #3f51b5;
        font-size: 14px
    }

    #modalVeiculo .modal-header {
        border-bottom: 1px solid #eef1f5
    }

    /* ── Aba Vistoria ── */
    .vis-check-area { background: #f8faff; border: 1.5px solid #e2e8f0; border-radius: 10px; padding: 14px 16px 12px; }
    .vis-check-area-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
    .vis-check-area-title { font-size: 12.5px; font-weight: 700; color: #334155; }
    .vis-check-counter { font-size: 11.5px; color: #94a3b8; background: #e2e8f0; border-radius: 20px; padding: 1px 9px; font-weight: 600; }
    .vis-check-counter.has-items { background: #dbe4ff; color: #3b5bdb; }
    .vis-check-grid { display: flex; flex-wrap: wrap; gap: 7px; }
    .vis-item { position: relative; display: inline-flex; }
    .vis-item input[type=checkbox] { display: none; }
    .vis-item > label { display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px; border: 1.5px solid #dee2e6; border-radius: 20px; font-size: 12px; cursor: pointer; background: #fff; color: #6c757d; user-select: none; transition: border-color .15s, background .15s, color .15s; white-space: nowrap; }
    .vis-item > label .vis-check-icon { display: none; font-size: 9px; }
    .vis-item input:checked + label { border-color: #3b5bdb; background: #eef3ff; color: #3b5bdb; font-weight: 600; }
    .vis-item input:checked + label .vis-check-icon { display: inline; }
    .vis-item-del { display: none; position: absolute; top: -7px; right: -7px; width: 17px; height: 17px; background: #fa5252; color: #fff; border: none; border-radius: 50%; font-size: 11px; cursor: pointer; padding: 0; line-height: 1; font-weight: 700; z-index: 2; align-items: center; justify-content: center; }
    .vis-item:hover .vis-item-del { display: flex; }
    .vis-btn-add { display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px; border: 1.5px dashed #c5d0ff; border-radius: 20px; font-size: 12px; cursor: pointer; background: transparent; color: #3b5bdb; transition: .15s; margin-top: 8px; }
    .vis-btn-add:hover { background: #eef3ff; border-color: #3b5bdb; }
    .vis-add-row { display: none; align-items: center; gap: 6px; margin-top: 8px; }
    .vis-add-row.open { display: flex; }
    .vis-add-input { height: 30px; border: 1.5px solid #c5d0ff; border-radius: 20px; padding: 0 12px; font-size: 12px; flex: 1; outline: none; font-family: inherit; }
    .vis-add-input:focus { border-color: #3b5bdb; box-shadow: 0 0 0 3px rgba(59,91,219,.1); }
    .vis-add-confirm { height: 30px; padding: 0 14px; background: #3b5bdb; color: #fff; border: none; border-radius: 20px; font-size: 12px; font-weight: 600; cursor: pointer; }
    .vis-add-cancel  { height: 30px; padding: 0 12px; background: #f1f3f5; color: #6c757d; border: none; border-radius: 20px; font-size: 12px; cursor: pointer; }
</style>

<div class="modal fade" id="modalVeiculo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-custom-width">
        <div class="modal-content">
            <form id="formVeiculo" action="<?= ACTION_URL ?>/veiculos.php" method="post" enctype="multipart/form-data">
                <div class="modal-header align-items-center">
                    <h5 class="modal-title d-flex align-items-center">
                        <span class="title-icon rounded-circle d-inline-flex align-items-center justify-content-center mr-2">
                            <i class="fa fa-car"></i>
                        </span>
                        <span id="tituloModalVeiculo">Novo Veículo</span>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <!-- tabs -->
                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#dados" role="tab"><i class="fa fa-car"></i> Dados</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#vistoria" role="tab"><i class="fa fa-check"></i> Vistoria</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#imagens" role="tab"><i class="fa fa-image"></i> Imagens</a></li>
                    </ul>

                    <div class="tab-content">
                        <!-- DADOS -->
                        <div class="tab-pane fade show active" id="dados" role="tabpanel">
                            <div class="row">
                                <div class="col-md-2 mb-2">
                                    <label class="form-label">Placa</label>
                                    <input type="text" class="form-control form-control-sm" name="placa" id="placa" required maxlength="7" autocomplete="off" style="text-transform:uppercase">
                                </div>
                                <div class="col-md-5 mb-2">
                                    <label class="form-label">Chassi</label>
                                    <input type="text" class="form-control form-control-sm" id="chassi" name="chassi" required>
                                </div>
                                <div class="col-md-5 mb-2">
                                    <label class="form-label">Renavam</label>
                                    <input type="text" class="form-control form-control-sm" name="renavam" id="renavam" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-2 mb-2">
                                    <label class="form-label">Tipo</label>
                                    <select id="tipoVeiculo" class="form-select form-control form-control-sm" name="tipoVeiculo">
                                        <option value="">Selecione o tipo</option>
                                        <option value="CARRO">CARRO</option>
                                        <option value="MOTO">MOTO</option>
                                        <option value="CAMINHÃO">CAMINHÃO</option>
                                    </select>
                                </div>

                                <div class="col-md-3 mb-2">
                                    <label class="form-label">Marca</label>
                                    <select id="marcas" class="form-select form-control form-control-sm" name="marcas" disabled>
                                        <option value="">Selecione a marca</option>
                                    </select>
                                </div>

                                <div class="col-md-3 mb-2">
                                    <label class="form-label">Modelo</label>
                                    <select id="modelos" class="form-select form-control form-control-sm" name="modelos" disabled>
                                        <option value="">Selecione o modelo</option>
                                    </select>
                                </div>

                                <div class="col-md-3 mb-2">
                                    <label class="form-label">Ano</label>
                                    <select id="anos" class="form-select form-control form-control-sm" name="anos" disabled>
                                        <option value="">Selecione o ano</option>
                                    </select>
                                </div>

                                <div class="col-md-1 mb-2 d-flex align-items-end">
                                    <button type="button" id="consultar" class="btn btn-primary m-btn m-btn--icon m-btn--icon-only" disabled>
                                        <i class="fa fa-search"></i>
                                    </button>
                                </div>
                            </div>

                            <div id="resultado" class="row">
                                <div class="col-md-2 mb-2">
                                    <label class="form-label">Ano Fab.</label>
                                    <input type="text" class="form-control form-control-sm" id="ano" name="ano" readonly>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label class="form-label">Ano Modelo</label>
                                    <input type="text" class="form-control form-control-sm" id="anoModelo" name="anoModelo">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label class="form-label">Combustível</label>
                                    <input type="text" class="form-control form-control-sm" id="combustivel" name="combustivel" readonly>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="form-label">R$ Valor</label>
                                    <input type="text" class="form-control form-control-sm" id="valor" name="valor" readonly>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="form-label">R$ Valor Cobertura</label>
                                    <input type="text" class="form-control form-control-sm" id="valorCobertura" name="valorCobertura">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-2 mb-2">
                                    <label class="form-label">Cor</label>
                                    <select id="cor" class="form-select form-control form-control-sm" name="cor">
                                        <option value="">Selecione a cor</option>
                                        <?php if (!empty($cores ?? [])): foreach ($cores as $cor): ?>
                                                <option value="<?= (int)$cor->COR_CODIGO_PK ?>"><?= htmlspecialchars($cor->COR_DESCRICAO) ?></option>
                                        <?php endforeach;
                                        endif; ?>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label class="form-label">Código FIPE</label>
                                    <input type="text" class="form-control form-control-sm" id="codigoFipe" name="codigoFipe" readonly>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="form-label">Câmbio</label>
                                    <select class="form-select form-control form-control-sm" name="cambio">
                                        <option value="">Selecione o tipo</option>
                                        <option value="AUTOMÁTICO">AUTOMÁTICO</option>
                                        <option value="MANUAL">MANUAL</option>
                                        <option value="AUTOMATIZADO">AUTOMATIZADO</option>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label class="form-label">UF</label>
                                    <select class="form-select form-control form-control-sm" id="ufCarro" name="ufCarro" required>
                                        <option value="">Selecione</option>
                                        <?php foreach (['AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MG', 'MS', 'MT', 'PA', 'PB', 'PE', 'PI', 'PR', 'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO'] as $uf): ?>
                                            <option value="<?= $uf ?>"><?= $uf ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="form-label">Cidade</label>
                                    <select class="form-select form-control form-control-sm" id="cidadeCarro" name="cidadeCarro" required>
                                        <option value="">Selecione</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Grupo</label>
                                    <select id="grupo" class="form-select form-control form-control-sm" name="grupo">
                                        <option value="">Selecione</option>
                                        <?php if (!empty($grupos ?? [])): foreach ($grupos as $g): ?>
                                                <option value="<?= (int)$g->GRU_CODIGO_PK ?>"
                                                    data-adesao="<?= (float)$g->GRU_VALOR_ADESAO ?>"
                                                    data-mensalidade="<?= (float)$g->GRU_VALOR_MENSALIDADE ?>">
                                                    <?= htmlspecialchars($g->GRU_DESCRICAO) ?>
                                                </option>
                                        <?php endforeach;
                                        endif; ?>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label class="form-label">R$ Adesão</label>
                                    <input type="text" class="form-control form-control-sm" id="adesao" name="adesao">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label class="form-label">R$ Mensal.</label>
                                    <input type="text" class="form-control form-control-sm" id="mensalidade" name="mensalidade">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Combo</label>
                                    <select id="combo" class="form-select form-control form-control-sm" name="combo">
                                        <option value="">Selecione</option>
                                        <?php if (!empty($combos ?? [])): foreach ($combos as $c): ?>
                                                <option value="<?= (int)$c->COM_CODIGO_PK ?>" data-valor="<?= (float)$c->COM_VALOR ?>">
                                                    <?= htmlspecialchars($c->COM_DESCRICAO) ?>
                                                </option>
                                        <?php endforeach;
                                        endif; ?>
                                    </select>
                                </div>

                                <div class="col-md-4 mb-2">
                                    <label class="form-label">R$ Combo</label>
                                    <input type="text" class="form-control form-control-sm" id="valorCombo" name="valorCombo" readonly>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label" id="labelTotalFinal">R$ Mensalidade + Combo</label>
                                    <input type="text" id="totalFinal" name="totalFinal" class="form-control form-control-sm" readonly value="R$ 0,00">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Tipo de Boleto</label>
                                    <select id="tipoBoleto" class="form-select form-control form-control-sm" name="tipoBoleto">
                                        <option value="">Selecione</option>
                                        <option value="BANCARIO">Bancário</option>
                                        <option value="INTERNO">Interno</option>
                                    </select>
                                </div>

                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Rastreador</label>
                                    <select id="rastreador" class="form-select form-control form-control-sm" name="rastreador">
                                        <option value="">Selecione</option>
                                        <?php if (!empty($rastreadores ?? [])): foreach ($rastreadores as $r): ?>
                                                <option value="<?= (int)$r->RAS_CODIGO_PK ?>" data-valor="<?= (float)$r->RAS_VALOR_MENSALIDADE ?>">
                                                    <?= htmlspecialchars($r->RAS_CODIGO) ?>
                                                </option>
                                        <?php endforeach;
                                        endif; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">R$ Rastreador</label>
                                    <input type="text" class="form-control form-control-sm" id="valorRastreador" name="valorRastreador" readonly>
                                </div>

                                <div class="col-md-4 mb-2">
                                    <label class="form-label">CPF Proprietário</label>
                                    <input type="text" class="form-control form-control-sm" name="cpf_proprietario" id="cpf_proprietario">
                                </div>
                                <div class="col-md-12 mb-2">
                                    <label class="form-label">Nome Proprietário</label>
                                    <input type="text" class="form-control form-control-sm" name="nome_proprietario" id="nome_proprietario">
                                </div>
                            </div>

                            <!-- FIPE espelhos -->
                            <input type="hidden" name="marca" id="marca">
                            <input type="hidden" name="modelo" id="modelo">
                            <input type="hidden" name="mesReferencia" id="mesReferencia">
                            <input type="hidden" name="fipe_marca_cod" id="fipe_marca_cod">
                            <input type="hidden" name="fipe_modelo_cod" id="fipe_modelo_cod">
                        </div>

                        <!-- VISTORIA -->
                        <div class="tab-pane fade" id="vistoria" role="tabpanel">
                            <!-- Linha: vistoriador + código vidro + pneus -->
                            <div class="row mb-3">
                                <div class="col-md-5 mb-2">
                                    <label class="form-label">Vistoriador</label>
                                    <select class="form-select form-control form-control-sm" name="vistoriado_fk" id="vistoriado_fk">
                                        <option value="">Selecione o vistoriador</option>
                                        <?php foreach ($vistoriadores ?? [] as $vis): ?>
                                            <option value="<?= (int)$vis->VIS_CODIGO_PK ?>"><?= htmlspecialchars($vis->VIS_NOME) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Código do vidro</label>
                                    <input type="text" class="form-control form-control-sm" name="codigo_vidro" id="codigo_vidro">
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="form-label">Pneus</label>
                                    <select name="pneus" class="form-control form-control-sm">
                                        <option value="Novos">Novos</option>
                                        <option value="Bons">Bons</option>
                                        <option value="Ruins">Ruins</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Checklist de equipamentos -->
                            <div class="vis-check-area">
                                <div class="vis-check-area-header">
                                    <span class="vis-check-area-title"><i class="fa-solid fa-list-check mr-1" style="color:#3b5bdb;"></i>Equipamentos e acessórios</span>
                                    <span class="vis-check-counter" id="visCounter">0 selecionados</span>
                                </div>

                                <div class="vis-check-grid" id="visCheckGrid">
                                    <?php foreach ($visItemsDb as $item): ?>
                                    <div class="vis-item" data-id="<?= (int)$item['ITV_CODIGO_PK'] ?>" data-key="<?= htmlspecialchars($item['ITV_CHAVE'], ENT_QUOTES) ?>">
                                        <input type="checkbox" name="vis_checked[]" value="<?= (int)$item['ITV_CODIGO_PK'] ?>" id="vis_<?= htmlspecialchars($item['ITV_CHAVE'], ENT_QUOTES) ?>">
                                        <label for="vis_<?= htmlspecialchars($item['ITV_CHAVE'], ENT_QUOTES) ?>">
                                            <i class="fa-solid fa-check vis-check-icon"></i>
                                            <?= htmlspecialchars($item['ITV_DESCRICAO']) ?>
                                        </label>
                                        <button type="button" class="vis-item-del" title="Remover item">×</button>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php if (empty($visItemsDb)): ?>
                                    <span style="font-size:12px;color:#94a3b8;padding:4px;">Nenhum item cadastrado ainda.</span>
                                    <?php endif; ?>
                                </div>

                                <!-- Linha para adicionar item -->
                                <div class="vis-add-row" id="visAddRow">
                                    <input type="text" class="vis-add-input" id="visNewItemName" placeholder="Nome do novo item..." maxlength="60">
                                    <button type="button" class="vis-add-confirm" id="visConfirmAdd">Adicionar</button>
                                    <button type="button" class="vis-add-cancel"  id="visCancelAdd">Cancelar</button>
                                </div>

                                <div>
                                    <button type="button" class="vis-btn-add" id="visBtnAdd">
                                        <i class="fa-solid fa-plus" style="font-size:10px;"></i> Novo item
                                    </button>
                                </div>
                            </div>

                            <!-- Observação -->
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <label class="form-label">Observação</label>
                                    <textarea name="observacao_vistoria" id="observacao_vistoria" class="form-control form-control-sm" rows="3" placeholder="Anotações sobre o estado do veículo, arranhões, avarias, etc."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- IMAGENS -->
                        <div class="tab-pane fade" id="imagens" role="tabpanel">
                            <div class="form-group">
                                <label>Selecionar imagens da vistoria (máx. 500KB por imagem)</label>
                                <input type="file" id="inputImagens" name="imagens[]" class="form-control" multiple accept="image/*">
                            </div>
                            <div class="row" id="previewImagens"></div>
                        </div>
                    </div>

                    <!-- hidden -->
                    <input type="hidden" name="acao" id="acao" value="cadastrar">
                    <input type="hidden" name="codigo_veiculo" id="codigo_veiculo">
                    <input type="hidden" name="codigo_contrato" id="codigo_contrato">

                    <input type="hidden" name="codigo_associado" id="codigo_associado">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnSalvarVeiculo">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- deps do fluxo FIPE/Combo -->
<script src="<?= APP_URL ?>/associados/fipe.js?v=<?= filemtime(__DIR__ . '/fipe.js') ?>"></script>
<script src="<?= APP_URL ?>/associados/combo.js?v=<?= filemtime(__DIR__ . '/combo.js') ?>"></script>
<script>
(function () {
    var COMBO_URL = '<?= ACTION_URL ?>/combo.php';

    function parseMoney(v) {
        if (v == null) return 0;
        v = String(v).replace(/\s|R\$/g, '').trim();
        if (v.indexOf(',') !== -1 && v.indexOf('.') !== -1) v = v.replace(/\./g, '').replace(',', '.');
        else if (v.indexOf(',') !== -1) v = v.replace(',', '.');
        var n = parseFloat(v);
        return isNaN(n) ? 0 : n;
    }

    function formatBR(n) {
        return (parseFloat(n) || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Alias para código legado que chama window.recalcularTotal
    window.recalcularTotal = function () {
        if (typeof window.atualizarTotalVeiculo === 'function') window.atualizarTotalVeiculo();
    };

    // Combo → busca valor via AJAX e recalcula total
    document.addEventListener('change', function (e) {
        if (!e.target || e.target.id !== 'combo') return;
        var id = e.target.value;
        var elCombo = document.getElementById('valorCombo');
        if (!id) {
            if (elCombo) elCombo.value = '';
            window.recalcularTotal();
            return;
        }
        fetch(COMBO_URL + '?acao=obter&id=' + encodeURIComponent(id) + '&_=' + Date.now())
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (elCombo) elCombo.value = (j.success && j.combo) ? formatBR(parseMoney(j.combo.COM_VALOR)) : '';
                window.recalcularTotal();
            })
            .catch(function () { if (elCombo) elCombo.value = ''; window.recalcularTotal(); });
    });
})();
</script>

<script src="<?= APP_URL ?>/associados/carregar_cidades.js"></script>

<script>
    /* ===== Utils compartilháveis (expostos em window) ===== */
    (function() {
        // adiciona/seleciona valor em <select>, mesmo se não existir opção
        window.ensureSelectValue = function($sel, value) {
            if (!value && value !== 0) return;
            $sel.prop('disabled', false);
            const valStr = String(value);
            const $opt = $sel.find('option').filter(function() {
                return $(this).val() === valStr || $(this).text() === valStr;
            });
            if ($opt.length) $sel.val($opt.val());
            else $sel.append(new Option(valStr, valStr, true, true));
            $sel.trigger('change');
        };

        // ===== cidades por UF usando mapa local em window.cidadesPorEstado =====
        window.popularCidadesUFCarro = function(uf, cidadeSelecionada) {
            const $cidade = $('#cidadeCarro');
            const mapa = window.cidadesPorEstado || {};
            const ufKey = String(uf || '').toUpperCase();

            $cidade.empty().append(new Option('Selecione', '', true, false));

            if (!ufKey || !Object.prototype.hasOwnProperty.call(mapa, ufKey)) {
                // se o mapa não estiver carregado, apenas sai silenciosamente
                // (garanta que carregar_cidades.js foi incluído antes deste bloco)
                $cidade.trigger('change');
                return;
            }

            const lista = Array.isArray(mapa[ufKey]) ? mapa[ufKey] : [];
            for (const c of lista) $cidade.append(new Option(c, c, false, false));

            if (cidadeSelecionada && String(cidadeSelecionada).trim() !== '') {
                if (lista.includes(cidadeSelecionada)) $cidade.val(cidadeSelecionada);
                else $cidade.append(new Option(cidadeSelecionada, cidadeSelecionada, true, true));
            }

            $cidade.trigger('change'); // mantém eventuais dependentes em dia
        };

        // listeners
        $(document).on('change', '#ufCarro', function() {
            window.popularCidadesUFCarro($(this).val(), null);
        });
        $('#modalVeiculo').on('shown.bs.modal', function() {
            const ufAtual = $('#ufCarro').val();
            if (ufAtual) window.popularCidadesUFCarro(ufAtual, $('#cidadeCarro').val() || null);
        });
    })();
</script>

<script>
    /* ===== Upload + compress (500KB alvo) + preview (ACUMULANDO) ===== */
    (function() {
        const input = document.getElementById('inputImagens');
        const container = document.getElementById('previewImagens');
        if (!input || !container) return;

        // fila persistente de arquivos novos (acumula entre trocas)
        let fileQueue = new DataTransfer();
        // contador para IDs únicos dos checkboxes
        let uid = container.querySelectorAll('.col-md-3').length || 0;

        function loadImageFromFile(file) {
            return new Promise((res, rej) => {
                const img = new Image();
                img.onload = () => res(img);
                img.onerror = rej;
                img.src = URL.createObjectURL(file);
            });
        }

        function canvasToBlob(canvas, quality = 0.85, type = 'image/jpeg') {
            return new Promise((resolve) => canvas.toBlob(b => resolve(b), type, quality));
        }

        function sanitizeBase(base) {
            return base.replace(/[^a-zA-Z0-9_\-]/g, '_').slice(0, 80);
        }

        async function compressToTargetJPEG(file, targetBytes = 512 * 1024) {
            if (!/^image\/(png|jpeg|webp|jpg)$/i.test(file.type)) return file;
            const img = await loadImageFromFile(file);
            let w = img.naturalWidth || img.width,
                h = img.naturalHeight || img.height;

            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d', {
                alpha: false
            });
            canvas.width = w;
            canvas.height = h;
            ctx.drawImage(img, 0, 0, w, h);

            let q = 0.85,
                blob = await canvasToBlob(canvas, q),
                tries = 0;
            while (blob && blob.size > targetBytes && tries < 12) {
                if (q > 0.6) {
                    q -= 0.07;
                } else {
                    w = Math.max(320, Math.round(w * 0.85));
                    h = Math.max(240, Math.round(h * 0.85));
                    canvas.width = w;
                    canvas.height = h;
                    ctx.clearRect(0, 0, w, h);
                    ctx.drawImage(img, 0, 0, w, h);
                    q = 0.8;
                }
                blob = await canvasToBlob(canvas, q);
                tries++;
            }
            if (!blob) return file;

            const base = sanitizeBase((file.name || 'imagem').replace(/\.[^.]+$/, ''));
            return new File([blob], `${base}.jpg`, {
                type: 'image/jpeg',
                lastModified: Date.now()
            });
        }

        function alreadyQueued(f) {
            return [...fileQueue.files].some(x => x.name === f.name && x.size === f.size);
        }

        function appendCardForFile(file) {
            const url = URL.createObjectURL(file);
            const index = uid++;
            const col = document.createElement('div');
            col.className = 'col-md-3 mb-3';
            col.dataset.fileName = file.name; // marca o card com o nome do arquivo (p/ excluir)
            col.dataset.isNew = '1'; // indica que é arquivo novo (não do servidor)
            col.innerHTML = `
        <div class="card">
          <img src="${url}" class="card-img-top img-thumbnail" alt="Imagem" style="height:150px;object-fit:cover;">
          <div class="card-body p-2">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="is_chassi[]" value="${file.name}" id="chassi_${index}">
              <label class="form-check-label small" for="chassi_${index}">Imagem do Chassi</label>
            </div>
            <button type="button" class="btn btn-danger btn-sm btn-block mt-2 btn-excluir-img">Excluir</button>
          </div>
        </div>`;
            container.appendChild(col);
        }

        input.addEventListener('change', async (ev) => {
            const selected = Array.from(ev.target.files || []);
            if (!selected.length) return;

            for (const original of selected) {
                const compact = await compressToTargetJPEG(original, 512 * 1024);
                if (alreadyQueued(compact)) continue;
                fileQueue.items.add(compact);
                appendCardForFile(compact);
            }

            // 1) reseta o value pra permitir escolher o mesmo arquivo de novo
            ev.target.value = '';
            // 2) reanexa a fila acumulada ao input (vai pro submit)
            input.files = fileQueue.files;
        });


        container.addEventListener('click', function(e) {
            const btn = e.target.closest('.btn-excluir-img');
            if (!btn) return;

            const card = btn.closest('.col-md-3');
            const fname = card?.dataset.fileName;
            const isNew = card?.dataset.isNew === '1';

            if (isNew && fname) {
                // remove da fila (por nome)
                const newQueue = new DataTransfer();
                for (const f of fileQueue.files) {
                    if (f.name !== fname) newQueue.items.add(f);
                }
                fileQueue = newQueue;
                input.files = fileQueue.files;
            } else {
                // É imagem antiga do servidor — se você quiser sinalizar remoção no backend:
                // const id = card.dataset.imageId; // ex.: se você renderizar isso no HTML do servidor
                // if (id) {
                //   const hidden = document.createElement('input');
                //   hidden.type = 'hidden';
                //   hidden.name = 'excluir_imagem_existente[]';
                //   hidden.value = id;
                //   document.getElementById('formVeiculo').appendChild(hidden);
                // }
            }

            card.remove();
        });
    })();
</script>


<script>
    /* ===== Checagem de placa já existente ===== */
    (function() {
        function formatPhone(v) {
            const d = String(v || '').replace(/\D/g, '');
            if (d.length === 11) return `(${d.slice(0,2)}) ${d.slice(2,7)}-${d.slice(7)}`;
            if (d.length === 10) return `(${d.slice(0,2)}) ${d.slice(2,6)}-${d.slice(6)}`;
            return v || '—';
        }

        function escapeHtml(s) {
            return String(s || '').replace(/[&<>"'`=\/]/g, c => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
                '/': '&#x2F;',
                '`': '&#x60;',
                '=': '&#x3D;'
            } [c]));
        }
        $(document).on('blur', '#placa', function() {
            const $btnSave = $('#formVeiculo button[type="submit"]');
            const placa = (this.value || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
            if (!placa) return;
            this.value = placa;

            // >>> envia também o veiculo_id atual (se estiver em edição) para ignorar a própria placa
            const selfId = ($('#formVeiculo input[name="codigo_veiculo"]').val() || '').trim();
            const q = '<?= ACTION_URL ?>/veiculos.php?acao=verificar_placa&placa=' +
                encodeURIComponent(placa) +
                (selfId ? ('&veiculo_id=' + encodeURIComponent(selfId)) : '') +
                '&_=' + Date.now();

            fetch(q)
                .then(r => r.json())
                .then(j => {
                    if (j.success && j.existe) {
                        $btnSave.prop('disabled', true);
                        const a = j.associado || {};
                        const nome = a.nome ? escapeHtml(a.nome) : '—';
                        const fone = formatPhone(a.fone1);
                        Swal.fire({
                            icon: 'error',
                            title: 'Placa já cadastrada',
                            html: `<div class="text-left"><p><strong>Proprietário:</strong> ${nome}</p><p><strong>Telefone:</strong> ${fone}</p></div>`
                        });
                    } else {
                        $btnSave.prop('disabled', false);
                    }
                })
                .catch(() => {
                    $btnSave.prop('disabled', false);
                });
        });
    })();

    // garante que o mapa de cidades está disponível (preenchido por carregar_cidades.js)
    window.cidadesPorEstado = window.cidadesPorEstado || {};
</script>

<script>
/* ===== Vistoria: contador + adicionar/remover itens ===== */
(function () {
    var customUid = 0;

    function updateCounter() {
        var total   = document.querySelectorAll('#visCheckGrid .vis-item input[type=checkbox]').length;
        var checked = document.querySelectorAll('#visCheckGrid .vis-item input[type=checkbox]:checked').length;
        var el = document.getElementById('visCounter');
        if (!el) return;
        el.textContent = checked + ' de ' + total + ' selecionados';
        el.classList.toggle('has-items', checked > 0);
    }

    document.addEventListener('change', function (e) {
        if (e.target.closest('#visCheckGrid')) updateCounter();
    });

    /* ── Remover item ── */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.vis-item-del');
        if (!btn) return;
        e.preventDefault();
        var item = btn.closest('.vis-item');
        var id   = item ? item.dataset.id : null;
        if (!id) { item && item.remove(); updateCounter(); return; }
        var fd = new FormData();
        fd.set('acao', 'remover_item_vistoria');
        fd.set('id', id);
        fd.set('csrf', document.querySelector('input[name="csrf"]').value);
        fetch('<?= ACTION_URL ?>/veiculos.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (j.success) { item.remove(); updateCounter(); }
                else alert('Erro ao remover: ' + (j.message || ''));
            })
            .catch(function () { alert('Falha na comunicação.'); });
    });

    /* ── Adicionar item ── */
    var btnAdd     = document.getElementById('visBtnAdd');
    var addRow     = document.getElementById('visAddRow');
    var addInput   = document.getElementById('visNewItemName');
    var btnConfirm = document.getElementById('visConfirmAdd');
    var btnCancel  = document.getElementById('visCancelAdd');
    var grid       = document.getElementById('visCheckGrid');

    if (btnAdd) btnAdd.addEventListener('click', function () {
        addRow.classList.add('open');
        addInput.value = '';
        addInput.focus();
        btnAdd.style.display = 'none';
    });

    function closeAddRow() {
        addRow.classList.remove('open');
        btnAdd.style.display = '';
    }

    if (btnCancel) btnCancel.addEventListener('click', closeAddRow);

    function addCustomItem() {
        var name = (addInput.value || '').trim();
        if (!name) { addInput.focus(); return; }
        if (btnConfirm) { btnConfirm.disabled = true; btnConfirm.textContent = '…'; }
        var fd = new FormData();
        fd.set('acao', 'adicionar_item_vistoria');
        fd.set('nome', name);
        fd.set('csrf', document.querySelector('input[name="csrf"]').value);
        fetch('<?= ACTION_URL ?>/veiculos.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j.success) { alert('Erro: ' + (j.message || '')); return; }
                var uid = 'vis_new_' + j.id;
                var div = document.createElement('div');
                div.className = 'vis-item';
                div.dataset.id  = j.id;
                div.dataset.key = j.chave;
                div.innerHTML =
                    '<input type="checkbox" name="vis_checked[]" value="' + j.id + '" id="' + uid + '" checked>' +
                    '<label for="' + uid + '">' +
                    '<i class="fa-solid fa-check vis-check-icon"></i>' + j.nome.replace(/</g,'&lt;') +
                    '</label>' +
                    '<button type="button" class="vis-item-del" title="Remover">×</button>';
                grid.appendChild(div);
                closeAddRow();
                updateCounter();
            })
            .catch(function () { alert('Falha na comunicação.'); })
            .finally(function () {
                if (btnConfirm) { btnConfirm.disabled = false; btnConfirm.textContent = 'Adicionar'; }
            });
    }

    if (btnConfirm) btnConfirm.addEventListener('click', addCustomItem);
    if (addInput)   addInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); addCustomItem(); }
        if (e.key === 'Escape') closeAddRow();
    });

    /* inicializa contador quando a aba Vistoria fica visível */
    document.addEventListener('shown.bs.tab', function (e) {
        if (e.target && e.target.getAttribute('href') === '#vistoria') updateCounter();
    });
    /* e também ao abrir o modal */
    var modal = document.getElementById('modalVeiculo');
    if (modal) modal.addEventListener('shown.bs.modal', updateCounter);
})();
</script>