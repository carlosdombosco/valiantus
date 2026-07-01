<?php
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require __DIR__ . '/../inc/config.php';
require PATH_INC . '/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['SessUsuCodigo'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}

$acao = $_GET['acao'] ?? ($_POST['acao'] ?? '');

/* ── Tabelas de referência ──────────────────────────────────────────────── */

$MOVIMENTOS = [
    '02' => 'Entrada Confirmada',
    '03' => 'Entrada Rejeitada',
    '06' => 'Liquidação',
    '09' => 'Baixa Automática',
    '10' => 'Baixa Solicitada pelo Cedente',
    '14' => 'Vencimento Alterado',
    '17' => 'Liquidação após Baixa',
    '19' => 'Confirmação de Instrução de Protesto',
    '23' => 'Remessa a Cartório',
    '24' => 'Retirada de Cartório',
    '25' => 'Protestado e Baixado',
    '26' => 'Instrução Rejeitada',
    '28' => 'Débito de Tarifas/Custas',
    '32' => 'Instrução Cancelada',
    '60' => 'Entrada em Arquivo',
];

$MOTIVOS = [
    '00' => 'Sem ocorrência',
    '01' => 'CEP inválido',
    '02' => 'CEP sem praça de cobrança',
    '04' => 'Agência/Conta do Cedente inválida',
    '07' => 'Agência Cedente não prevista para o tipo de cobrança',
    '08' => 'Nosso Número inválido',
    '09' => 'Nosso Número duplicado',
    '10' => 'Carteira/Modalidade de Cobrança inválida',
    '13' => 'Nosso Número não correspondente ao banco',
    '16' => 'Data de vencimento inválida',
    '17' => 'Data de vencimento anterior à data de emissão',
    '18' => 'Vencimento fora do prazo permitido',
    '20' => 'Valor do Título inválido',
    '21' => 'Espécie do Título inválida',
    '24' => 'Data de emissão inválida',
    '26' => 'Código do Cedente inválido',
    '27' => 'CNPJ/CPF do Cedente inválido',
    '28' => 'CNPJ/CPF do Sacado inválido',
    '29' => 'Endereço do Sacado não informado',
    '30' => 'Nome do Sacado não informado',
    '37' => 'CEP irregular - Banco Correspondente',
    '38' => 'Prazo para protesto/negativação inválido',
    '44' => 'Agência Cedente não prevista para o tipo de cobrança',
    '46' => 'Tipo/Número do documento do Sacado incompleto',
    '48' => 'Percentual de multa inválido',
    '62' => 'Duplicidade de título',
    '63' => 'Entrada inválida para o tipo de desconto',
    '72' => 'Banco Correspondente não aceita o tipo de cobrança informado',
    '80' => 'Arquivo com erros de formatação',
    '85' => 'CEP do Sacado inválido',
    '97' => 'Instrução não aceita para o status atual do título',
    '98' => 'Instrução inválida',
    '99' => 'Outros erros',
];

$COD_LIQUIDACAO  = ['06', '17', '25'];
$COD_CONFIRMACAO = ['02', '60'];
$COD_REJEICAO    = ['03', '26'];

/* ── Helper: DDMMAAAA → YYYY-MM-DD ────────────────────────────────────── */
function cnabParaData(string $d): string
{
    $d = preg_replace('/\D/', '', $d);
    if (strlen($d) !== 8) return '';
    $dd = substr($d, 0, 2);
    $mm = substr($d, 2, 2);
    $aa = substr($d, 4, 4);
    if ($dd === '00' || $mm === '00' || $aa === '0000') return '';
    return "{$aa}-{$mm}-{$dd}";
}

/* ── POST: processar arquivo de retorno ────────────────────────────────── */
if ($acao === 'processar' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    if (empty($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Nenhum arquivo enviado ou erro no upload.']);
        exit;
    }

    $ext = strtolower(pathinfo($_FILES['arquivo']['name'], PATHINFO_EXTENSION));
    if ($ext !== 'ret') {
        echo json_encode(['success' => false, 'message' => 'Apenas arquivos .ret são aceitos.']);
        exit;
    }

    $conteudo = file_get_contents($_FILES['arquivo']['tmp_name']);
    if ($conteudo === false || strlen($conteudo) < 240) {
        echo json_encode(['success' => false, 'message' => 'Arquivo inválido ou vazio.']);
        exit;
    }

    /* converte de ISO-8859-1 para UTF-8 se necessário */
    if (!mb_check_encoding($conteudo, 'UTF-8')) {
        $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'ISO-8859-1');
    }

    $linhas = preg_split('/\r\n|\r|\n/', $conteudo);

    $registros   = [];
    $segT        = null;
    $dataArquivo = '';

    foreach ($linhas as $linha) {
        /* Remove apenas \r residual — NÃO usar rtrim pois remove espaços de padding CNAB */
        $linha = rtrim($linha, "\r");
        if (strlen($linha) < 18) continue;

        $tipoReg  = $linha[7];
        $segmento = $linha[13] ?? '';

        /* data de geração no header do arquivo */
        if ($tipoReg === '0' && $dataArquivo === '') {
            $dataArquivo = cnabParaData(substr($linha, 142, 8));
        }

        if ($tipoReg !== '3') continue;

        if ($segmento === 'T') {
            /* salva T anterior se não veio um U correspondente */
            if ($segT !== null) {
                $registros[] = $segT;
            }

            $codigoMov  = trim(substr($linha, 15, 2));
            $nossoRaw   = substr($linha, 40, 6);   // 6 dígitos, zero-padded
            $vencRaw    = substr($linha, 73, 8);   // DDMMAAAA
            $valorCent  = (int) substr($linha, 81, 15);

            /* Códigos de rejeição: 5 pares de 2 dígitos na pos 213-222 */
            $ocorrRaw   = substr($linha, 213, 10);
            $motivosParsed = [];
            for ($i = 0; $i < 10; $i += 2) {
                $cod = substr($ocorrRaw, $i, 2);
                if ($cod !== '00' && $cod !== '  ' && trim($cod) !== '') {
                    $motivosParsed[] = $cod . ' – ' . ($MOTIVOS[$cod] ?? 'Código ' . $cod);
                }
            }

            /* nome do sacado embutido no segmento T a partir de ~pos 100-108 fica
               um bloco de uso banco; o nome do sacado está em pos ~134-173 (40 chars).
               Extração simplificada: procura o primeiro bloco de letras maiúsculas longas */
            $nomeLinha = '';
            if (strlen($linha) >= 174) {
                $nomeLinha = trim(substr($linha, 134, 40));
            }

            $segT = [
                'nosso_numero'    => ltrim($nossoRaw, '0') ?: '0',
                'nosso_numero_raw'=> $nossoRaw,
                'codigo_mov'      => $codigoMov,
                'descricao_mov'   => $MOVIMENTOS[$codigoMov] ?? "Ocorrência {$codigoMov}",
                'vencimento'      => cnabParaData($vencRaw),
                'valor_cobrado'   => $valorCent / 100,
                'valor_pago'      => 0.0,
                'juros_multa'     => 0.0,
                'desconto'        => 0.0,
                'data_ocorrencia' => '',
                'data_credito'    => '',
                'motivos'         => $motivosParsed,
                /* campos preenchidos após busca no BD */
                'cob_pk'          => null,
                'pes_nome'        => $nomeLinha, /* fallback: nome do arquivo */
                'ja_liquidado'    => false,
            ];

        } elseif ($segmento === 'U' && $segT !== null) {

            $segT['juros_multa']     = ((int) substr($linha, 17, 15)) / 100;
            $segT['desconto']        = ((int) substr($linha, 32, 15)) / 100;
            $segT['valor_pago']      = ((int) substr($linha, 77, 15)) / 100;
            $segT['data_ocorrencia'] = cnabParaData(substr($linha, 137, 8));
            $dtCred                  = cnabParaData(substr($linha, 145, 8));
            /* usa data de ocorrência como fallback quando crédito não está preenchido */
            $segT['data_credito']    = $dtCred ?: $segT['data_ocorrencia'];

            $registros[] = $segT;
            $segT = null;
        }
    }

    /* último T sem U correspondente */
    if ($segT !== null) {
        $registros[] = $segT;
    }

    if (empty($registros)) {
        echo json_encode(['success' => false, 'message' => 'Nenhum registro de detalhe encontrado no arquivo.']);
        exit;
    }

    /* ── busca no banco por nosso número ─────────────────────────────── */
    try {
        $stmtBusca = $pdo->prepare("
            SELECT c.COB_CODIGO_PK, c.COB_PAGO, c.COB_DATA_VENCIMENTO,
                   c.COB_VALOR, c.COB_NOSSO_NUMERO,
                   p.PES_NOME
            FROM tb_cobranca c
            LEFT JOIN tb_pessoa p ON p.PES_CODIGO_PK = c.PES_CODIGO_FK
            WHERE c.COB_CODIGO_PK = ?
            LIMIT 1
        ");

        $totais = [
            'liquidacoes'  => ['qtd' => 0, 'valor' => 0.0],
            'confirmacoes' => ['qtd' => 0],
            'rejeicoes'    => ['qtd' => 0],
            'nao_encontrado' => ['qtd' => 0],
        ];

        foreach ($registros as &$reg) {
            $stmtBusca->execute([(int)$reg['nosso_numero']]);
            $row = $stmtBusca->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $reg['cob_pk']       = (int)$row['COB_CODIGO_PK'];
                $reg['pes_nome']     = $row['PES_NOME'] ?? '';
                $reg['ja_liquidado'] = ($row['COB_PAGO'] === 'SIM');
            } else {
                $totais['nao_encontrado']['qtd']++;
            }

            if (in_array($reg['codigo_mov'], $COD_LIQUIDACAO, true)) {
                $totais['liquidacoes']['qtd']++;
                $totais['liquidacoes']['valor'] += $reg['valor_pago'] ?: $reg['valor_cobrado'];
                $reg['tipo'] = 'liquidacao';
            } elseif (in_array($reg['codigo_mov'], $COD_CONFIRMACAO, true)) {
                $totais['confirmacoes']['qtd']++;
                $reg['tipo'] = 'confirmacao';
            } elseif (in_array($reg['codigo_mov'], $COD_REJEICAO, true)) {
                $totais['rejeicoes']['qtd']++;
                $reg['tipo'] = 'rejeicao';
            } else {
                $reg['tipo'] = 'outros';
            }
        }
        unset($reg);

        echo json_encode([
            'success'      => true,
            'arquivo'      => $_FILES['arquivo']['name'],
            'data_arquivo' => $dataArquivo,
            'registros'    => $registros,
            'totais'       => $totais,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao consultar banco de dados: ' . $e->getMessage()]);
    }
    exit;
}

/* ── POST: aplicar retorno no banco de dados ───────────────────────────── */
if ($acao === 'aplicar' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $input     = json_decode(file_get_contents('php://input'), true);
    $registros = $input['registros'] ?? [];

    if (empty($registros)) {
        echo json_encode(['success' => false, 'message' => 'Nenhum registro para aplicar.']);
        exit;
    }

    try {
        $hoje = date('Y-m-d');

        $stmtLiq = $pdo->prepare("
            UPDATE tb_cobranca
               SET COB_PAGO             = 'SIM',
                   COB_DATA_QUITACAO    = ?,
                   COB_VALOR_QUITADO    = ?,
                   COB_JUROS            = ?,
                   COB_DESCONTO         = ?,
                   COB_OCORRENCIA       = ?,
                   COB_STATUS_BOLETO    = 'LIQUIDADO',
                   COB_DATA_PROCESSAMENTO = ?
             WHERE COB_CODIGO_PK = ?
               AND COB_PAGO = 'NÃO'
        ");

        $stmtOcorr = $pdo->prepare("
            UPDATE tb_cobranca
               SET COB_OCORRENCIA       = ?,
                   COB_STATUS_BOLETO    = ?,
                   COB_DATA_PROCESSAMENTO = ?
             WHERE COB_CODIGO_PK = ?
        ");

        $aplicados   = 0;
        $ignorados   = 0;

        foreach ($registros as $reg) {
            $pk      = (int)($reg['cob_pk']       ?? 0);
            $codigo  = (string)($reg['codigo_mov'] ?? '');
            $valPago = (float)($reg['valor_pago']  ?? 0);
            $juros   = (float)($reg['juros_multa'] ?? 0);
            $desc    = (float)($reg['desconto']    ?? 0);
            $dtQuit  = (string)($reg['data_credito'] ?? $hoje);

            if ($pk <= 0) { $ignorados++; continue; }

            if (in_array($codigo, $COD_LIQUIDACAO, true)) {
                $stmtLiq->execute([
                    $dtQuit ?: $hoje,
                    $valPago,
                    $juros,
                    $desc,
                    $codigo,
                    $hoje,
                    $pk,
                ]);
                $aplicados += $stmtLiq->rowCount();
            } else {
                /* para outros códigos, apenas atualiza ocorrência e status */
                $status = $MOVIMENTOS[$codigo] ?? "Ocorrência {$codigo}";
                $stmtOcorr->execute([$codigo, $status, $hoje, $pk]);
            }
        }

        echo json_encode([
            'success'   => true,
            'aplicados' => $aplicados,
            'ignorados' => $ignorados,
            'message'   => "{$aplicados} parcela(s) liquidada(s) com sucesso.",
        ], JSON_UNESCAPED_UNICODE);

    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao aplicar retorno: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => true, 'message' => 'Ação inválida.']);
