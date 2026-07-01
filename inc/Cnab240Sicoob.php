<?php
declare(strict_types=1);

/**
 * Cnab240Sicoob — Gerador de arquivo de remessa CNAB 240 para Sicoob
 *
 * Baseado no manual CNAB 240 Sicoob e arquivo de referência cb150601.rem
 * Cada registro tem exatamente 240 caracteres + CRLF
 */
class Cnab240Sicoob
{
    private static function f(string $v, int $n, string $pad = '0', int $dir = STR_PAD_LEFT): string
    {
        $v = substr($v, 0, $n);
        return str_pad($v, $n, $pad, $dir);
    }

    private static function fAlpha(string $v, int $n): string
    {
        return self::f(strtoupper($v), $n, ' ', STR_PAD_RIGHT);
    }

    private static function fNum(string|int $v, int $n): string
    {
        return self::f((string)(int)$v, $n, '0', STR_PAD_LEFT);
    }

    /** Módulo 11 pesos 2-9 da direita para esquerda */
    public static function dvNossoNumero(string $num): string
    {
        $sum = 0; $f = 2;
        for ($i = strlen($num) - 1; $i >= 0; $i--) {
            $sum += (int)$num[$i] * $f;
            $f   = ($f === 9) ? 2 : $f + 1;
        }
        $r = $sum % 11;
        return (string)(($r === 0 || $r === 1) ? 0 : 11 - $r);
    }

    /* ──────────────────────────────────────────────────────────────────── */

    /** Gera o conteúdo completo do arquivo de remessa (string, linhas CRLF) */
    public static function gerarRemessa(array $cfg, array $boletos): string
    {
        $banco  = self::f($cfg['CFG_CNAB_BANCO'] ?? '756', 3);
        $nBol   = count($boletos);
        // 4 segmentos por boleto (P+Q+R+S) + header arquivo + header lote + trailer lote + trailer arquivo
        $nReg   = $nBol * 4 + 4;
        $hoje   = date('dmY');      // DDMMAAAA
        $hora   = date('His');      // HHMMSS
        $nsa    = self::fNum($cfg['CFG_CNAB_SEQUENCIAL_ARQUIVO'] ?? 1, 6);
        $conv   = self::fNum($cfg['CFG_SICOOB_NUM_CONVENIO'] ?? 0, 4);  // Ex: 7799

        $lines = [];
        $lines[] = self::headerArquivo($cfg, $banco, $hoje, $hora, $nsa, $conv);
        $lines[] = self::headerLote($cfg, $banco, $hoje, $nsa, $conv);

        $seqLote = 0;
        foreach ($boletos as $bol) {
            $seqLote++;
            $lines[] = self::segmentoP($cfg, $bol, $banco, ++$seqLote - 1, $hoje);
            $lines[] = self::segmentoQ($cfg, $bol, $banco, ++$seqLote - 1);
            $lines[] = self::segmentoR($banco, ++$seqLote - 1);
            $lines[] = self::segmentoS($banco, ++$seqLote - 1);
        }

        // seq nos segmentos começa em 1 e avança por linha
        // recalcular corretamente
        $lines = [];
        $lines[] = self::headerArquivo($cfg, $banco, $hoje, $hora, $nsa, $conv);
        $lines[] = self::headerLote($cfg, $banco, $hoje, $nsa, $conv);

        $seq = 0;
        foreach ($boletos as $bol) {
            $lines[] = self::segmentoP($cfg, $bol, $banco, ++$seq, $hoje);
            $lines[] = self::segmentoQ($cfg, $bol, $banco, ++$seq);
            $lines[] = self::segmentoR($banco, ++$seq);
            $lines[] = self::segmentoS($banco, ++$seq);
        }

        $lines[] = self::trailerLote($banco, $seq, $nBol);
        $lines[] = self::trailerArquivo($banco, $nBol * 4 + 4);

        return implode("\r\n", $lines) . "\r\n";
    }

    /* ── Segmentos ──────────────────────────────────────────────────────── */

    private static function headerArquivo(
        array  $cfg,
        string $banco,
        string $hoje,
        string $hora,
        string $nsa,
        string $conv
    ): string {
        $cnpj  = self::fNum(preg_replace('/\D/', '', $cfg['CFG_CNAB_CNPJ'] ?? $cfg['CFG_CNPJ'] ?? ''), 14);
        $ag5   = str_pad(preg_replace('/\D/', '', $cfg['CFG_CNAB_AGENCIA'] ?? ''), 5, '0', STR_PAD_LEFT);
        $digAg = substr($cfg['CFG_CNAB_AGENCIA_DIGITO'] ?? '0', 0, 1);
        $ct7   = str_pad(preg_replace('/\D/', '', $cfg['CFG_CNAB_CONTA'] ?? ''), 7, '0', STR_PAD_LEFT);
        $digCt = substr($cfg['CFG_CNAB_CONTA_DIGITO'] ?? '0', 0, 1);
        $dvAg  = substr($cfg['CFG_CNAB_DV_AGENCIA_CONTA'] ?? '0', 0, 1);
        $nome  = self::fAlpha($cfg['CFG_RAZAO_SOCIAL'] ?? 'EMPRESA', 30);

        // Campo código/convenio (pos 67-70 = 4 chars)
        $cod4  = self::f($conv, 4, '0', STR_PAD_LEFT);

        $r  = $banco . '0000' . '0';              // 0-7: banco+lote0+tipo0
        $r .= str_repeat(' ', 9);                 // 8-16: brancos
        $r .= '2';                                // 17: tipo inscrição (CNPJ)
        $r .= $cnpj;                              // 18-31: CNPJ (14)
        $r .= str_repeat(' ', 20);               // 32-51: brancos
        $r .= $ag5;                               // 52-56: agência (5)
        $r .= $digAg;                             // 57: dígito agência
        $r .= $ct7;                               // 58-64: conta (7)
        $r .= $digCt;                             // 65: dígito conta
        $r .= $dvAg;                              // 66: DV ag/ct
        $r .= $cod4;                              // 67-70: código/convênio (4)
        $r .= '0';                                // 71: zero
        $r .= $nome;                              // 72-101: nome empresa (30)
        $r .= self::fAlpha('SICOOB', 30);         // 102-131: nome banco (30)
        $r .= str_repeat(' ', 10);               // 132-141: uso exclusivo
        $r .= $hoje;                              // 142-149: data gravação DDMMAAAA
        $r .= $hora;                              // 150-155: hora gravação HHMMSS
        $r .= $nsa;                               // 156-161: NSA (6)
        $r .= '0040';                             // 162-165: versão layout
        $r .= '01600';                            // 166-170: densidade
        $r .= str_repeat(' ', 69);               // 171-239: brancos

        return substr(str_pad($r, 240, ' '), 0, 240);
    }

    private static function headerLote(
        array  $cfg,
        string $banco,
        string $hoje,
        string $nsa,
        string $conv
    ): string {
        $cnpj  = self::fNum(preg_replace('/\D/', '', $cfg['CFG_CNAB_CNPJ'] ?? $cfg['CFG_CNPJ'] ?? ''), 14);
        $ag5   = str_pad(preg_replace('/\D/', '', $cfg['CFG_CNAB_AGENCIA'] ?? ''), 5, '0', STR_PAD_LEFT);
        $digAg = substr($cfg['CFG_CNAB_AGENCIA_DIGITO'] ?? '0', 0, 1);
        $ct7   = str_pad(preg_replace('/\D/', '', $cfg['CFG_CNAB_CONTA'] ?? ''), 7, '0', STR_PAD_LEFT);
        $digCt = substr($cfg['CFG_CNAB_CONTA_DIGITO'] ?? '0', 0, 1);
        $dvAg  = substr($cfg['CFG_CNAB_DV_AGENCIA_CONTA'] ?? '0', 0, 1);
        $nome  = self::fAlpha($cfg['CFG_RAZAO_SOCIAL'] ?? 'EMPRESA', 30);
        $cod4  = self::f($conv, 4, '0', STR_PAD_LEFT);
        $end   = self::fAlpha($cfg['CFG_ENDERECO'] ?? '', 40);

        $r  = $banco . '0001' . '1';             // 0-7: banco+lote1+tipo1
        $r .= 'R01  ';                            // 8-12: operação + serviço + brancos
        $r .= '040 ';                             // 13-16: forma lançamento + branco
        $r .= '2';                                // 17: versão layout lote
        $r .= '0';                                // 18: branco
        $r .= '2';                                // 19: tipo inscrição (CNPJ)
        $r .= $cnpj;                              // 20-33: CNPJ
        $r .= str_repeat(' ', 20);               // 34-53: brancos
        $r .= $ag5;                               // 54-58: agência (5)
        $r .= $digAg;                             // 59: dígito agência
        $r .= $ct7;                               // 60-66: conta (7)
        $r .= $digCt;                             // 67: dígito conta
        $r .= $dvAg;                              // 68: DV ag/ct
        $r .= $cod4 . ' ';                        // 69-73: código+espaço (5)
        $r .= $nome;                              // 74-103: nome empresa (30)
        $r .= $end;                               // 104-143: endereço (40)
        $r .= str_repeat(' ', 40);               // 144-183: cidade/UF/CEP/telefone
        $r .= $nsa;                               // 184-189: NSA (6)
        $r .= '  ';                               // 190-191: brancos
        $r .= $hoje;                              // 192-199: data gravação
        $r .= '00000000';                         // 200-207: hora/zeros
        $r .= str_repeat(' ', 32);               // 208-239: brancos

        return substr(str_pad($r, 240, ' '), 0, 240);
    }

    private static function segmentoP(
        array  $cfg,
        array  $bol,
        string $banco,
        int    $seq,
        string $hoje
    ): string {
        $ag5   = str_pad(preg_replace('/\D/', '', $cfg['CFG_CNAB_AGENCIA'] ?? ''), 5, '0', STR_PAD_LEFT);
        $digAg = substr($cfg['CFG_CNAB_AGENCIA_DIGITO'] ?? '0', 0, 1);
        $ct7   = str_pad(preg_replace('/\D/', '', $cfg['CFG_CNAB_CONTA'] ?? ''), 7, '0', STR_PAD_LEFT);
        $digCt = substr($cfg['CFG_CNAB_CONTA_DIGITO'] ?? '0', 0, 1);
        $dvAg  = substr($cfg['CFG_CNAB_DV_AGENCIA_CONTA'] ?? '0', 0, 1);
        $conv  = self::fNum($cfg['CFG_SICOOB_NUM_CONVENIO'] ?? 0, 4);

        // Nosso número: 6 dígitos + DV — sempre usa o PK como fonte
        $nn6  = self::fNum(preg_replace('/\D/', '', (string)($bol['COB_CODIGO_PK'] ?? '')), 6);
        $dvNn = self::dvNossoNumero($nn6);

        // Vencimento: DDMMAAAA
        $venc = (string)($bol['COB_DATA_VENCIMENTO'] ?? date('Y-m-d'));
        $dv   = date('dmY', strtotime($venc));

        // Valor em centavos padded 15
        $val  = self::fNum((int)round((float)($bol['COB_VALOR'] ?? 0) * 100), 15);

        // Código do instrumento (fixo Sicoob) — posições 47-61
        $codigoFixo = '01014' . '     ' . '10 22'; // 15 chars

        $r  = $banco . '0001' . '3';             // 0-7: banco+lote+tipo3
        $r .= self::fNum($seq, 5);               // 8-12: seq lote (5)
        $r .= 'P';                               // 13: segmento
        $r .= ' ';                               // 14: branco
        $r .= '01';                              // 15-16: código movimento
        $r .= $ag5;                              // 17-21: agência (5)
        $r .= $digAg;                            // 22: dígito agência
        $r .= $ct7;                              // 23-29: conta (7)
        $r .= $digCt;                            // 30: dígito conta
        $r .= $dvAg;                             // 31: DV ag/ct
        $r .= $conv;                             // 32-35: código convênio (4)
        $r .= ' ';                               // 36: espaço
        $r .= '000';                             // 37-39: prefixo fixo
        $r .= $nn6;                              // 40-45: nosso número (6)
        $r .= $dvNn;                             // 46: DV nosso número (1)
        $r .= $codigoFixo;                       // 47-61: código fixo (15)
        $r .= $nn6;                              // 62-67: nosso número (6) repetido
        $r .= str_repeat(' ', 9);               // 68-76: brancos (9)
        $r .= $dv;                               // 77-84: vencimento DDMMAAAA (8)
        $r .= $val;                              // 85-99: valor centavos (15)
        $r .= '000';                             // 100-102: banco cobrador
        $r .= '00000';                           // 103-107: agência cobradora
        $r .= 'N';                               // 108: aceite
        $r .= $hoje;                             // 109-116: data emissão DDMMAAAA (8)
        $r .= '0';                               // 117: tipo juros
        $r .= '00000000';                        // 118-125: data juros
        $r .= str_repeat('0', 15);              // 126-140: valor juros
        $r .= '0';                               // 141: tipo desconto
        $r .= '00000000';                        // 142-149: data desconto
        $r .= str_repeat('0', 15);              // 150-164: valor desconto
        $r .= str_repeat('0', 15);              // 165-179: IOF
        $r .= str_repeat('0', 15);              // 180-194: abatimento
        $r .= str_repeat(' ', 15);              // 195-209: ident. empresa
        $r .= '1';                               // 210: código protesto
        $r .= '00';                              // 211-212: prazo protesto
        $r .= '0';                               // 213: código baixa
        $r .= '000';                             // 214-216: prazo baixa
        $r .= '009';                             // 217-219: moeda (009=real)
        $r .= str_repeat('0', 10);              // 220-229: número contrato
        $r .= ' ';                               // 230: uso livre
        $r .= str_repeat(' ', 9);               // 231-239: brancos

        return substr(str_pad($r, 240, ' '), 0, 240);
    }

    private static function segmentoQ(
        array  $cfg,
        array  $bol,
        string $banco,
        int    $seq
    ): string {
        $cpfCnpj  = preg_replace('/\D/', '', (string)($bol['PES_CPF_CNPJ'] ?? ''));
        $tipoInsc = (strlen($cpfCnpj) === 14 || strtoupper($bol['PES_TIPO'] ?? '') === 'JURÍDICA') ? '2' : '1';
        $insc14   = self::fNum($cpfCnpj, 14);

        $nome     = self::fAlpha($bol['PES_NOME'] ?? '', 40);
        $end      = self::fAlpha(trim(($bol['PES_ENDERECO'] ?? '') . ' ' . ($bol['PES_NUMERO'] ?? '')), 40);
        $bairro   = self::fAlpha($bol['PES_BAIRRO'] ?? '', 16);  // Sicoob usa 16 chars
        $cep      = self::fNum(preg_replace('/\D/', '', $bol['PES_CEP'] ?? ''), 8);
        $cidade   = self::fAlpha($bol['PES_CIDADE'] ?? '', 15);
        $uf       = self::fAlpha($bol['PES_UF'] ?? '', 2);

        $r  = $banco . '0001' . '3';             // 0-7
        $r .= self::fNum($seq, 5);               // 8-12: seq
        $r .= 'Q';                               // 13
        $r .= ' ';                               // 14
        $r .= '01';                              // 15-16: movimento
        $r .= $tipoInsc;                         // 17: tipo inscrição sacado
        $r .= $insc14;                           // 18-31: CPF/CNPJ (14)
        $r .= $nome;                             // 32-71: nome (40)
        $r .= $end;                              // 72-111: endereço (40)
        $r .= $bairro;                           // 112-127: bairro (16)
        $r .= $cep;                              // 128-135: CEP (8)
        $r .= $cidade;                           // 136-150: cidade (15)
        $r .= $uf;                               // 151-152: UF (2)
        // Sacador/avalista (1=branco inscrição, 14=zeros, 40=espaços)
        $r .= '0';                               // 153: tipo inscrição sacador
        $r .= str_repeat('0', 14);              // 154-167: CPF/CNPJ sacador
        $r .= str_repeat(' ', 40);              // 168-207: nome sacador
        // Banco correspondente
        $r .= '000';                             // 208-210: banco correspondente
        $r .= str_repeat('0', 20);              // 211-230: nosso número banco corrente
        $r .= str_repeat(' ', 9);               // 231-239: brancos

        return substr(str_pad($r, 240, ' '), 0, 240);
    }

    private static function segmentoR(string $banco, int $seq): string
    {
        $r  = $banco . '0001' . '3';
        $r .= self::fNum($seq, 5);
        $r .= 'R';
        $r .= ' ';
        $r .= '01';
        // Tipo multa 0=sem, data multa, valor multa
        $r .= '0' . '00000000' . str_repeat('0', 15);  // 17-40: multa
        // Tipo desconto 2, data, valor
        $r .= '0' . '00000000' . str_repeat('0', 15);  // 41-64: desconto2
        // Tipo desconto 3
        $r .= '0' . '00000000' . str_repeat('0', 15);  // 65-88: desconto3
        // Código para baixa / devolução
        $r .= str_repeat('0', 30);                     // 89-118
        // Código do benefício
        $r .= str_repeat(' ', 10);                     // 119-128
        // Instrução 1 e 2
        $r .= '00';                                    // 129-130: instrução 1
        $r .= self::fAlpha('NAO ACEITAR APOS O VENCIMENTO', 30); // 131-160
        $r .= '00';                                    // 161-162: instrução 2
        $r .= str_repeat(' ', 30);                     // 163-192
        // Débito automático / outros
        $r .= str_repeat('0', 40);                     // 193-232
        $r .= str_repeat(' ', 7);                      // 233-239

        return substr(str_pad($r, 240, ' '), 0, 240);
    }

    private static function segmentoS(string $banco, int $seq): string
    {
        $r  = $banco . '0001' . '3';
        $r .= self::fNum($seq, 5);
        $r .= 'S';
        $r .= ' ';
        $r .= '01';
        $r .= '3';  // 17: tipo conteúdo (3=mensagem)
        $r .= self::fAlpha('NAO ACEITAR APOS O VENCIMENTO', 40); // 18-57
        $r .= str_repeat(' ', 182);                              // 58-239: brancos

        return substr(str_pad($r, 240, ' '), 0, 240);
    }

    private static function trailerLote(string $banco, int $seqUltimo, int $nBoletos): string
    {
        // Quantidade de registros no lote = nBoletos*4 + header + trailer = nBoletos*4 + 2
        $qReg = $nBoletos * 4 + 2;
        // Total de títulos = nBoletos
        $qTit = $nBoletos;

        $r  = $banco . '0001' . '5';             // 0-7
        $r .= str_repeat(' ', 9);               // 8-16: brancos
        $r .= self::fNum($qReg, 6);             // 17-22: qtd registros lote
        $r .= self::fNum($qTit, 6);             // 23-28: qtd títulos
        $r .= str_repeat('0', 17);              // 29-45: valor total cobrado
        $r .= str_repeat('0', 15);              // 46-60: qtd títulos em carteira
        $r .= str_repeat('0', 15);              // 61-75: valor em carteira
        $r .= str_repeat('0', 15);              // 76-90: qtd títulos efetivados
        $r .= str_repeat('0', 15);              // 91-105: valor efetivado
        $r .= str_repeat(' ', 8);               // 106-113: nro aviso débito
        $r .= str_repeat(' ', 126);             // 114-239: brancos

        return substr(str_pad($r, 240, ' '), 0, 240);
    }

    private static function trailerArquivo(string $banco, int $totalReg): string
    {
        $r  = $banco . '9999' . '9';             // 0-7
        $r .= str_repeat(' ', 9);               // 8-16: brancos
        $r .= self::fNum(1, 6);                 // 17-22: qtd lotes (1)
        $r .= self::fNum($totalReg, 6);         // 23-28: qtd registros total
        $r .= str_repeat('0', 6);               // 29-34: qtd contas
        $r .= str_repeat(' ', 205);             // 35-239: brancos

        return substr(str_pad($r, 240, ' '), 0, 240);
    }

    /* ── Utilitários públicos ───────────────────────────────────────────── */

    /** Gera o nome do arquivo: cb{DDMM}{NN}.rem */
    public static function nomeArquivo(string $remessaPath, int $seq): string
    {
        $ddmm = date('dm');
        $nn   = str_pad((string)$seq, 2, '0', STR_PAD_LEFT);
        return rtrim($remessaPath, '\\/') . DIRECTORY_SEPARATOR . "cb{$ddmm}{$nn}.rem";
    }

    /** Escreve o arquivo de remessa e retorna o caminho */
    public static function escreverArquivo(string $caminho, string $conteudo): bool
    {
        $dir = dirname($caminho);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return file_put_contents($caminho, $conteudo) !== false;
    }
}
