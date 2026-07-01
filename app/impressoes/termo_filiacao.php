<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/config.php';
require_once PATH_INC . '/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['SessUsuCodigo'])) {
    header('Location: ' . rtrim(BASE_URL, '/') . '/login.php'); exit;
}

$pesId = (int)($_GET['pes_id'] ?? 0);
$ctrId = (int)($_GET['ctr_id'] ?? 0);
if ($pesId <= 0 || $ctrId <= 0) { http_response_code(400); echo '<p>Parâmetros inválidos.</p>'; exit; }

/* ── Associado ── */
$stP = $pdo->prepare("SELECT * FROM tb_pessoa WHERE PES_CODIGO_PK = ? LIMIT 1");
$stP->execute([$pesId]);
$p = $stP->fetch(PDO::FETCH_ASSOC);
if (!$p) { http_response_code(404); echo '<p>Associado não encontrado.</p>'; exit; }

/* ── Contrato + veículo + cor ── */
$stC = $pdo->prepare("
    SELECT c.*,
           v.VEI_PLACA, v.VEI_MARCA, v.VEI_MODELO,
           v.VEI_ANO_FABRICACAO, v.VEI_ANO_MODELO,
           v.VEI_CHASSI, v.VEI_RENAVAM, v.VEI_CAMBIO, v.VEI_COMBUSTIVEL, v.VEI_TIPO,
           cr.COR_DESCRICAO AS VEI_COR,
           g.GRU_DESCRICAO,
           cb.COM_DESCRICAO
    FROM tb_contrato c
    LEFT JOIN tb_veiculo v  ON v.VEI_CODIGO_PK  = c.VEI_CODIGO_FK
    LEFT JOIN tb_cor     cr ON cr.COR_CODIGO_PK  = v.VEI_COD_COR_FK
    LEFT JOIN tb_grupo   g  ON g.GRU_CODIGO_PK   = c.GRU_CODIGO_FK
    LEFT JOIN tb_combo   cb ON cb.COM_CODIGO_PK   = c.COM_CODIGO_FK
    WHERE c.CTR_CODIGO_PK = ? AND c.PES_CODIGO_FK = ?
    LIMIT 1
");
$stC->execute([$ctrId, $pesId]);
$c = $stC->fetch(PDO::FETCH_ASSOC);
if (!$c) { http_response_code(404); echo '<p>Contrato não encontrado.</p>'; exit; }

/* ── Configurações ── */
$cfg = [];
try {
    $cfg = $pdo->query("SELECT * FROM tb_configuracoes WHERE CFG_CODIGO_PK = 1 LIMIT 1")
               ->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {}

$empresa   = $cfg['CFG_RAZAO_SOCIAL']      ?? 'VALIANTUS ASSOCIAÇÃO PROTEÇÃO VEICULAR';
$cnpj      = $cfg['CFG_CNPJ']             ?? '57.691.018/0001-02';
$cidade    = $cfg['CFG_CIDADE']            ?? 'Cachoeirinha';
$uf        = $cfg['CFG_UF']               ?? 'PE';
$fone      = $cfg['CFG_FONE']             ?? '(81) 99785-4377 / 98675-2122';
$logoPath  = $cfg['CFG_LOGO_PATH']        ?? '';
$assinPath = $cfg['CFG_ASSINATURA_PATH']  ?? '';
$nomeResp  = $cfg['CFG_NOME_RESPONSAVEL'] ?? '';
$cargoResp = $cfg['CFG_CARGO_RESPONSAVEL'] ?? '';
$textoTermo = trim($cfg['CFG_TEXTO_TERMO'] ?? '');

/* ── Helpers ── */
$h  = fn(?string $v) => htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
$dt = function(?string $v): string {
    if (!$v || $v === '0000-00-00') return '';
    try { return (new DateTime($v))->format('d/m/Y'); } catch (Exception $e) { return $v; }
};
$money = fn($v): string => ($v !== null && $v !== '' && (float)$v > 0)
    ? 'R$ ' . number_format((float)$v, 2, ',', '.') : '';
$or  = fn($v) => (string)($v ?? '');

/* ── Dados do associado ── */
$endNum    = trim(($p['PES_ENDERECO'] ?? '') . ($p['PES_NUMERO'] ? ', ' . $p['PES_NUMERO'] : ''));
$endAssoc  = $p['PES_ENDERECO'] ?? '';
$numAssoc  = $p['PES_NUMERO'] ?? '';

/* ── Texto do termo (usa DB ou texto padrão do PDF) ── */
$textoPadrao = 'O presente Termo de Filiação à ' . $empresa . ', CNPJ ' . $cnpj . ', estando devidamente assinado e aprovado pela Diretoria Executiva confere ao Filiado o direito aos benefícios, conforme a modalidade de benefícios adiante assinalada, conforme previsão no Estatuto Social e no Regimento da Entidade.

Outrossim, o Associado se COMPROMETE a efetuar o pagamento, em dia – até a data do vencimento – das mensalidades, bem como da Cota de Participação no rateio relativo aos prejuízos compartilhados referente a eventuais danos ocorridos com os bens dos associados no mês, sob pena de perda do direito aos benefícios, consoante normas estatutárias e regulamentares.

Por conseguinte, o/a Filiado/a declara expressamente ter conhecimento, porquanto recebeu cópia, do Regimento de Assistência e Benefícios aos Associados, e que detém pleno conhecimento dos seus direitos e deveres como associado/a, perante a Entidade.

A cobertura inicia-se no exato momento em que for realizada a vistoria do veículo.

O Associado encontra-se ciente que o não pagamento da mensalidade até a data de vencimento resultará na perda imediata da proteção e dos serviços de assistência da Associação e que para reativação far-se-á necessário o pagamento das parcelas inadimplidas e a realização de nova vistoria.

O pagamento da contribuição mensal do Associado se dará na data de vencimento indicada neste termo. O não recebimento do boleto não isenta o Associado de quitá-lo até a data do vencimento, tendo em vista está ciente desta obrigação desde a assinatura deste termo. Caso o Associado não receba o boleto de pagamento deve entrar em contato com a Associação.

O Associado que desejar não permanecer mas filiado deverá comparecer a Associação e assinar o termo de cancelamento.

O veículo cadastrado deve ser conduzido por pessoa habilitada de acordo com a categoria e normas do Código Nacional de Trânsito vigentes.

O Associado que prestar informações inexatas ou faltas, ou mesmo que omitir informações que possam influenciar na aceitação da Proposta de Adesão, será excluído do Quadro da Associação e perderá todos os benefícios Associativos, sem direito a restituição tendo em vista que deu causa ao cancelamento em menção.

O Associado resta ciente que deve comunicar IMEDIATAMANTE a Associação quando da alteração de titularidade do veículo, conforme Regimento Interno.';

$corpoTermo = $textoTermo ?: $textoPadrao;

$meses = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho',
          'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
$dataExtenso = date('d') . ' de ' . $meses[(int)date('n')] . ' de ' . date('Y');
$cidadeEstado = trim(($p['PES_CIDADE'] ?? '') ?: $cidade) . ' / ' . (($p['PES_UF'] ?? '') ?: $uf);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Termo de Filiação — #<?= $ctrId ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            color: #000;
            background: #e9ecef;
            padding: 20px;
        }

        .imp-page {
            max-width: 820px;
            margin: 0 auto;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 24px rgba(0,0,0,.12);
            overflow: hidden;
        }

        /* Toolbar */
        .imp-toolbar {
            display: flex; justify-content: space-between; align-items: center;
            gap: 10px; padding: 10px 20px;
            background: #f8f9fb; border-bottom: 1px solid #e9ecef;
        }
        .imp-toolbar-info { font-size: 12px; color: #868e96; font-weight: 600; }
        .imp-toolbar-btns { display: flex; gap: 8px; }
        .imp-btn {
            height: 36px; padding: 0 18px; border-radius: 8px; font-size: 12px;
            font-weight: 700; cursor: pointer; border: none;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .imp-btn-print { background: #3b5bdb; color: #fff; }
        .imp-btn-close { background: #e9ecef; color: #495057; }
        .imp-btn:hover { opacity: .88; }

        /* Páginas */
        .pagina { padding: 22px 28px; }
        .pagina + .pagina { border-top: 3px dashed #ccc; }

        /* Logo header (topo da página 1) */
        .doc-logo-header {
            display: flex; align-items: center; gap: 16px;
            border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 10px;
        }
        .doc-logo-header img { max-height: 60px; max-width: 130px; object-fit: contain; }
        .doc-logo-placeholder {
            width: 90px; height: 50px; border: 1px dashed #ccc;
            display: flex; align-items: center; justify-content: center;
            color: #adb5bd; font-size: 9px;
        }
        .doc-logo-info { flex: 1; }
        .doc-empresa-nome { font-size: 12px; font-weight: 800; text-transform: uppercase; }
        .doc-empresa-sub  { font-size: 9px; color: #333; margin-top: 2px; }
        .doc-page-label   { text-align: right; font-size: 9px; color: #555; }

        /* Título do documento */
        .doc-title {
            text-align: center;
            font-size: 12px; font-weight: 900; text-transform: uppercase;
            letter-spacing: .06em; background: #e0e0e0;
            padding: 5px 10px; border: 1px solid #bbb;
            margin-bottom: 8px;
        }

        /* Corpo de texto (cláusulas) */
        .doc-body {
            font-size: 9.5px; line-height: 1.6; color: #111;
            text-align: justify; margin-bottom: 10px;
            white-space: pre-wrap;
        }
        .doc-body p { text-indent: 20px; margin-bottom: 4px; }

        /* Seção (barra cinza) */
        .sec-bar {
            background: #d0d0d0; border: 1px solid #bbb;
            font-size: 10px; font-weight: 900; text-align: center;
            text-transform: uppercase; letter-spacing: .06em;
            padding: 4px 8px; margin-bottom: 0;
        }
        .sec-body {
            border: 1px solid #bbb; border-top: none;
            padding: 6px 10px; margin-bottom: 8px;
            font-size: 9.5px;
        }

        /* Tabela de campos */
        .campo-grid { width: 100%; border-collapse: collapse; }
        .campo-grid td {
            border: 1px solid #bbb;
            padding: 3px 6px;
            font-size: 9.5px;
            vertical-align: top;
        }
        .campo-grid .lbl { font-weight: 700; white-space: nowrap; }
        .campo-grid .val { min-width: 60px; }
        .campo-grid .full { border-top: none; padding-top: 0; }

        /* Linha simples de assinatura */
        .assin-area {
            display: flex; gap: 30px; margin-top: 12px; padding-top: 8px;
        }
        .assin-bloco { flex: 1; text-align: center; }
        .assin-line-img { max-height: 48px; max-width: 180px; object-fit: contain; margin-bottom: 3px; }
        .assin-line { border-top: 1px solid #000; margin: 0 8px 4px; padding-top: 2px; }
        .assin-label { font-size: 9px; font-weight: 700; text-transform: uppercase; }
        .assin-sub   { font-size: 8.5px; color: #333; }

        /* Rodapé de página */
        .pg-footer {
            text-align: right; font-size: 8px; color: #555;
            margin-top: 8px; padding-top: 4px;
            border-top: 1px solid #ddd;
        }

        /* Print */
        @media print {
            html, body { background: #fff !important; padding: 0 !important; }
            .imp-page    { box-shadow: none; border-radius: 0; max-width: 100%; }
            .imp-toolbar { display: none !important; }
            .pagina      { padding: 6mm 8mm; }
            .pagina + .pagina { border-top: none; page-break-before: always; }
            .assin-area  { page-break-inside: avoid; }
        }

        @page { size: A4; margin: 0; }
    </style>
</head>
<body>
<div class="imp-page">

    <!-- Toolbar -->
    <div class="imp-toolbar">
        <div class="imp-toolbar-info">
            <i class="fa-solid fa-file-signature" style="color:#3b5bdb;margin-right:6px;"></i>
            Termo de Filiação — CTR #<?= $ctrId ?> — <?= $h($p['PES_NOME'] ?? '') ?>
        </div>
        <div class="imp-toolbar-btns">
            <button class="imp-btn imp-btn-close" onclick="window.close()">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                Fechar
            </button>
            <button class="imp-btn imp-btn-print" onclick="window.print()">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Imprimir
            </button>
        </div>
    </div>

    <!-- ════════════════ PÁGINA 1 ════════════════ -->
    <div class="pagina">

        <!-- Logo header -->
        <div class="doc-logo-header">
            <div>
                <?php if ($logoPath): ?>
                    <img src="<?= $h($logoPath) ?>" alt="Logo">
                <?php else: ?>
                    <div class="doc-logo-placeholder">SEM LOGO</div>
                <?php endif; ?>
            </div>
            <div class="doc-logo-info">
                <div class="doc-empresa-nome"><?= $h($empresa) ?></div>
                <div class="doc-empresa-sub">CNPJ: <?= $h($cnpj) ?></div>
            </div>
            <div class="doc-page-label">Página 1/2 (v1)</div>
        </div>

        <!-- Título -->
        <div class="doc-title">Termo de Filiação</div>

        <!-- Corpo do termo -->
        <div class="doc-body"><?php
            foreach (explode("\n\n", $corpoTermo) as $para) {
                $para = trim($para);
                if ($para !== '') echo '<p>' . $h($para) . '</p>';
            }
        ?></div>

        <!-- ── DADOS DO/A ASSOCIADO/A ── -->
        <div class="sec-bar">Dados do/a Associado/a:</div>
        <div class="sec-body" style="padding:0;">
            <table class="campo-grid">
                <tr>
                    <td colspan="3"><span class="lbl">Associado/a:</span> <?= $h($p['PES_NOME'] ?? '') ?></td>
                    <td style="white-space:nowrap;"><span class="lbl">ID:</span> <?= $pesId ?></td>
                </tr>
                <tr>
                    <td><span class="lbl">CPF:</span> <?= $h($p['PES_CPF_CNPJ'] ?? '') ?></td>
                    <td><span class="lbl">RG:</span> <?= $h($p['PES_RG'] ?? '') ?></td>
                    <td colspan="2"><span class="lbl">Órg. Emissor:</span> <?= $h($p['PES_ORG_EXP'] ?? '') ?></td>
                </tr>
                <tr>
                    <td><span class="lbl">Data Nasc.:</span> <?= $h($dt($p['PES_DATA_NASCIMENTO'] ?? null)) ?></td>
                    <td><span class="lbl">Data Expedição:</span> <?= $h($dt($p['PES_VALIDADE'] ?? null)) ?></td>
                    <td colspan="2"><span class="lbl">Órgão Expeditor:</span> <?= $h($p['PES_ORG_EXP'] ?? '') ?></td>
                </tr>
                <tr>
                    <td><span class="lbl">Estado Civil:</span> <?= $h($p['PES_ESTADO_CIVIL'] ?? '') ?></td>
                    <td><span class="lbl">Sexo:</span> <?= $h($p['PES_SEXO'] ?? '') ?></td>
                    <td colspan="2"><span class="lbl">Profissão:</span> <?= $h($p['PES_PROFISSAO'] ?? '') ?></td>
                </tr>
                <tr>
                    <td colspan="3"><span class="lbl">Endereço:</span> <?= $h($p['PES_ENDERECO'] ?? '') ?></td>
                    <td><span class="lbl">Nº</span> <?= $h($p['PES_NUMERO'] ?? '') ?></td>
                </tr>
                <tr>
                    <td><span class="lbl">Bairro:</span> <?= $h($p['PES_BAIRRO'] ?? '') ?></td>
                    <td><span class="lbl">Cidade:</span> <?= $h($p['PES_CIDADE'] ?? '') ?></td>
                    <td colspan="2"><span class="lbl">Cep.:</span> <?= $h($p['PES_CEP'] ?? '') ?></td>
                </tr>
                <tr>
                    <td colspan="4"><span class="lbl">Email:</span> <?= $h($p['PES_EMAIL'] ?? '') ?></td>
                </tr>
                <tr>
                    <td><span class="lbl">Tel.:</span> <?= $h($p['PES_FONE_FIXO'] ?? '') ?></td>
                    <td><span class="lbl">Cel:</span> <?= $h($p['PES_FONE_CELULAR_1'] ?? '') ?></td>
                    <td colspan="2"><span class="lbl">WhatsApp:</span> <?= $h($p['PES_FONE_CELULAR_2'] ?? '') ?></td>
                </tr>
            </table>
        </div>

        <!-- ── CARACTERÍSTICAS DO VEÍCULO ── -->
        <div class="sec-bar">Características do Veículo</div>
        <div class="sec-body" style="padding:0;">
            <table class="campo-grid">
                <tr>
                    <td><span class="lbl">Marca:</span> <?= $h($c['VEI_MARCA'] ?? '') ?></td>
                    <td><span class="lbl">Modelo:</span> <?= $h($c['VEI_MODELO'] ?? '') ?></td>
                    <td><span class="lbl">Cor:</span> <?= $h($c['VEI_COR'] ?? '') ?></td>
                    <td><span class="lbl">Câmbio</span> <?= $h($c['VEI_CAMBIO'] ?? '') ?></td>
                </tr>
                <tr>
                    <td colspan="2"><span class="lbl">Chassi:</span> <?= $h($c['VEI_CHASSI'] ?? '') ?></td>
                    <td colspan="2"><span class="lbl">Placa:</span> <?= $h($c['VEI_PLACA'] ?? '') ?></td>
                </tr>
                <tr>
                    <td colspan="2"><span class="lbl">Cód. Renavam:</span> <?= $h($c['VEI_RENAVAM'] ?? '') ?></td>
                    <td colspan="2"><span class="lbl">Ano / Modelo:</span> <?= $h($c['VEI_ANO_FABRICACAO'] ?? '') ?> / <?= $h($c['VEI_ANO_MODELO'] ?? '') ?></td>
                </tr>
                <tr>
                    <td colspan="4"><span class="lbl">Combustível:</span> <?= $h($c['VEI_COMBUSTIVEL'] ?? '') ?></td>
                </tr>
                <tr>
                    <td colspan="2"><span class="lbl">CNH:</span> <?= $h($p['PES_NUM_CNH'] ?? '') ?></td>
                    <td colspan="2"><span class="lbl">Categoria:</span> <?= $h($p['PES_CATEGORIA_CNH'] ?? '') ?></td>
                </tr>
                <tr>
                    <td colspan="2"><span class="lbl">Veículo de leilão:</span> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
                    <td colspan="2"><span class="lbl">Veículo recuperado:</span> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
                </tr>
                <tr>
                    <td colspan="2"><span class="lbl">Veículo de pequena, média ou grande monta?</span> &nbsp;&nbsp;&nbsp;</td>
                    <td colspan="2"><span class="lbl">Especifique:</span></td>
                </tr>
                <tr>
                    <td colspan="2"><span class="lbl">Veículo com isenção de impostos e/ou taxas?</span> &nbsp;&nbsp;&nbsp;</td>
                    <td colspan="2"><span class="lbl">Especifique:</span></td>
                </tr>
                <tr>
                    <td colspan="4"><span class="lbl">Veículo de locação?</span> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
                </tr>
                <tr>
                    <td><span class="lbl">Táxi?</span> &nbsp;&nbsp;&nbsp;</td>
                    <td><span class="lbl">Placa vermelha?</span> &nbsp;&nbsp;&nbsp;</td>
                    <td colspan="2"><span class="lbl">Veículo com chassi remarcado?</span> &nbsp;&nbsp;&nbsp;</td>
                </tr>
                <tr>
                    <td colspan="2"><span class="lbl">Veículo com impedimento judicial?</span> &nbsp;&nbsp;&nbsp;</td>
                    <td colspan="2"><span class="lbl">Especifique:</span></td>
                </tr>
                <tr>
                    <td colspan="4" style="font-style:italic;font-size:9px;">
                        <strong>(Obs.: Consultar a política vigente da Associação sobre o uso de rastreadores)</strong>
                    </td>
                </tr>
            </table>
        </div>

        <div class="pg-footer">Página 1/2 (v1)</div>

    </div><!-- /pagina 1 -->

    <!-- ════════════════ PÁGINA 2 ════════════════ -->
    <div class="pagina">

        <!-- Logo header página 2 -->
        <div class="doc-logo-header">
            <div>
                <?php if ($logoPath): ?>
                    <img src="<?= $h($logoPath) ?>" alt="Logo">
                <?php else: ?>
                    <div class="doc-logo-placeholder">SEM LOGO</div>
                <?php endif; ?>
            </div>
            <div class="doc-logo-info">
                <div class="doc-empresa-nome"><?= $h($empresa) ?></div>
                <div class="doc-empresa-sub">CNPJ: <?= $h($cnpj) ?> — CTR #<?= $ctrId ?></div>
            </div>
            <div class="doc-page-label">Página 2/2 (v1)</div>
        </div>

        <!-- SERVIÇOS ADICIONAIS -->
        <div class="sec-bar">Serviços Adicionais</div>
        <div class="sec-body" style="min-height:28px;">
            <?= $h($c['COM_DESCRICAO'] ?? '') ?: 'SEM SERVIÇOS ADICIONAIS' ?>
        </div>

        <!-- OBSERVAÇÕES -->
        <div class="sec-bar">Observações</div>
        <div class="sec-body">
            <p style="margin-bottom:5px;">
                <strong>Data do vencimento da contribuição:</strong> 15
            </p>
            <p style="margin-bottom:5px;">
                <strong>Valor da Avaliação:</strong> <?= $h($money($c['CTR_VALOR_VEICULO'] ?? null)) ?>
            </p>
            <p style="margin-bottom:4px;"><strong>Cota de Participação:</strong></p>
            <p style="margin-left:10px;margin-bottom:2px;font-size:9px;">
                a) Quando carro: 4% do valor do bem no 1º sinistro, 6% nos demais sinistros ocorridos durante 12 meses a contar da data de Filiação, 8% quando o sinistro for roubo, furto, perda total ou incêndio por colisão.
            </p>
            <p style="margin-left:10px;margin-bottom:5px;font-size:9px;">
                b) Quando moto: 5% do valor do bem no 1º sinistro, 7% nos demais sinistros ocorridos durante 12 meses a contar da data de Filiação, 8% quando o sinistro for roubo, furto, perda total ou incêndio por colisão.
            </p>
            <p style="margin-bottom:5px;">
                <strong>Taxa de Filiação:</strong> <?= $h($money($c['CTR_VALOR_ADESAO'] ?? null)) ?>
            </p>
            <p style="margin-bottom:2px;"><strong>Observações:</strong></p>
            <div style="border-bottom:1px solid #aaa;min-height:16px;margin-bottom:4px;"></div>
            <div style="border-bottom:1px solid #aaa;min-height:16px;margin-bottom:4px;"></div>
            <div style="border-bottom:1px solid #aaa;min-height:16px;"></div>
        </div>

        <!-- DECLARAÇÃO -->
        <div class="sec-bar">Declaração</div>
        <div class="sec-body" style="font-size:9.5px;line-height:1.65;text-align:justify;">
            Declaro para todos os fins que recebi uma cópia do Regimento da <?= $h($empresa) ?>,
            CNPJ nº <?= $h($cnpj) ?>. De posse do sobredito documento, conferi, li e tomei conhecimento dos meus direitos e deveres
            perante a Entidade.<br>
            Ciente que, em caso de sinistro, devo comunicar a Associação IMEDIATAMENTE, através dos números:<?= $h($fone) ?>,
            informando o local, hora, situação do veículo, veículos envolvidos. Outrossim ciente que quando da lavratura do
            Boletim de Ocorrência deve constar estes números de telefone para contato.
        </div>

        <!-- LOCAL, DATA E ASSINATURAS -->
        <div class="sec-bar">Local, Data e Assinaturas</div>
        <div class="sec-body">
            <div style="display:flex;justify-content:space-between;margin-bottom:20px;">
                <div><strong>Cidade / Estado:</strong> <?= $h($cidadeEstado) ?></div>
                <div><strong>Data:</strong> <?= date('d/m/Y') ?></div>
            </div>

            <div style="display:flex;gap:30px;margin-top:6px;">
                <div style="flex:1;text-align:center;">
                    <div style="border-top:1px solid #000;padding-top:4px;margin-top:40px;"></div>
                    <div style="font-size:9px;font-weight:700;text-transform:uppercase;">Consultor / Vistoriador</div>
                </div>
                <div style="flex:1;text-align:center;">
                    <div style="border-top:1px solid #000;padding-top:4px;margin-top:40px;"></div>
                    <div style="font-size:9px;font-weight:700;text-transform:uppercase;">Assinatura do/a Associado/a</div>
                </div>
            </div>
        </div>

        <!-- APROVAÇÃO DA DIRETORIA -->
        <div class="sec-bar">Aprovação da Diretoria Executiva da <?= $h($empresa) ?></div>
        <div class="sec-body" style="text-align:center;padding:14px 10px 8px;">
            <?php if ($assinPath): ?>
                <img src="<?= $h($assinPath) ?>" alt="Assinatura" style="max-height:110px;max-width:380px;object-fit:contain;display:block;margin:0 auto 4px;">
            <?php else: ?>
                <div style="height:55px;"></div>
            <?php endif; ?>
            <div style="border-top:1px solid #000;width:60%;margin:0 auto 4px;"></div>
            <div style="font-size:9px;font-weight:700;"><?= $h($nomeResp ?: 'Responsável') ?></div>
            <div style="font-size:9px;color:#333;"><?= $h($cargoResp ?: 'Assinatura e Carimbo') ?></div>
        </div>

        <div class="pg-footer">Página 2/2 (v1)</div>

    </div><!-- /pagina 2 -->

</div><!-- /.imp-page -->
</body>
</html>
