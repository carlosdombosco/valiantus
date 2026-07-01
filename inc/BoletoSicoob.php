<?php
declare(strict_types=1);

/**
 * BoletoSicoob — Cálculo de código de barras, linha digitável e SVG ITF-25
 * para Sicoob CNAB 240, modalidade Simples / Registrada / Eletrônica.
 *
 * Referência: Manual CNAB 240 Sicoob + implementação ACBr (ACBrBancoSicoob.pas)
 */
class BoletoSicoob
{
    // ITF-25 encoding: 0=narrow, 1=wide
    private const ITF = [
        '0'=>[0,0,1,1,0],'1'=>[1,0,0,0,1],'2'=>[0,1,0,0,1],'3'=>[1,1,0,0,0],
        '4'=>[0,0,1,0,1],'5'=>[1,0,1,0,0],'6'=>[0,1,1,0,0],'7'=>[0,0,0,1,1],
        '8'=>[1,0,0,1,0],'9'=>[0,1,0,1,0],
    ];

    /* ── Campo livre (25 dígitos) ───────────────────────────────────────── */

    public static function campoLivre(array $cfg, string $nossoNumero): string
    {
        // Garante tamanho EXATO: pad esquerda com zeros e pega os últimos N dígitos
        $exact = function (string $v, int $n): string {
            return substr(str_pad(preg_replace('/\D/', '', $v), $n, '0', STR_PAD_LEFT), -$n);
        };

        $modalidade = (string)((int)($cfg['CFG_SICOOB_MODALIDADE'] ?? 1) % 10); // 1 dígito
        // Agência: 4 dígitos + dígito verificador da agência = 5 chars (ex: 4293+0 = "42930")
        $agencia    = $exact($cfg['CFG_CNAB_AGENCIA'] ?? '', 4)
                    . (substr($cfg['CFG_CNAB_AGENCIA_DIGITO'] ?? '0', 0, 1) ?: '0');
        // Posição D = DV agência/conta (campo separado na config)
        $agDig      = substr($cfg['CFG_CNAB_DV_AGENCIA_CONTA'] ?? '0', 0, 1) ?: '0'; // 1 dígito
        $cedente    = $exact($cfg['CFG_CNAB_CODIGO_CEDENTE'] ?? '', 7);          // 7 dígitos
        $nn7        = $exact($nossoNumero, 7);                                    // últimos 7 dígitos
        $dvNn       = self::dvNossoNumero($nn7);                                  // 1 dígito
        // M(1)+AG4(4)+DIG_AG(1)+DV_AG_CT(1)+CED(7)+NN7(7)+DV_NN(1)+'00'(2)+CART(1) = 25
        return $modalidade . $agencia . $agDig . $cedente . $nn7 . $dvNn . '001';
    }

    /* ── Código de barras (44 dígitos) ─────────────────────────────────── */

    public static function codigoBarras(array $cfg, string $nossoNumero, float $valor, string $vencimento): string
    {
        $banco     = str_pad($cfg['CFG_CNAB_BANCO'] ?? '756', 3, '0', STR_PAD_LEFT);
        $moeda     = '9';
        $fator     = self::fatorVencimento($vencimento);
        $vlr       = str_pad((string)(int)round($valor * 100), 10, '0', STR_PAD_LEFT);
        $cl        = self::campoLivre($cfg, $nossoNumero);

        // 43 dígitos sem DV: BBBM FFFF VVVVVVVVVV CL(25)
        $sem = $banco . $moeda . $fator . $vlr . $cl;
        $dv  = self::dvGeral($sem);

        // DV vai na posição 5 (índice 4): BBBM DV FFFF VVVVVVVVVV CL
        return substr($sem, 0, 4) . $dv . substr($sem, 4);
    }

    /* ── Linha digitável (campos + pontos + espaços) ───────────────────── */

    public static function linhaDigitavel(string $cod44): string
    {
        // Posições (0-indexed): 0-2=banco, 3=moeda, 4=DV, 5-8=fator, 9-18=valor, 19-43=campo livre
        $cl = substr($cod44, 19, 25);

        // Campo 1: BBBM + CL[0:5] → 9 dígitos + DV mod10  (formato XXXXX.XXXXX)
        $r1 = substr($cod44, 0, 4) . substr($cl, 0, 5);
        $c1 = substr($r1, 0, 5) . '.' . substr($r1, 5) . self::mod10($r1);

        // Campo 2: CL[5:15] → 10 dígitos + DV mod10  (formato XXXXX.XXXXXX)
        $r2 = substr($cl, 5, 10);
        $c2 = substr($r2, 0, 5) . '.' . substr($r2, 5) . self::mod10($r2);

        // Campo 3: CL[15:25] → 10 dígitos + DV mod10  (formato XXXXX.XXXXXX)
        $r3 = substr($cl, 15, 10);
        $c3 = substr($r3, 0, 5) . '.' . substr($r3, 5) . self::mod10($r3);

        // Campo 4: DV geral (posição 4 do código de barras)
        $c4 = $cod44[4];

        // Campo 5: fator(4) + valor(10) — sem ponto
        $c5 = substr($cod44, 5, 14);

        return "$c1 $c2 $c3 $c4 $c5";
    }

    /* ── Nosso número formatado para exibição ───────────────────────────── */

    public static function nossoNumeroFormatado(array $cfg, string $nossoNumero): string
    {
        $modal = str_pad((string)(int)($cfg['CFG_SICOOB_MODALIDADE'] ?? 1), 2, '0', STR_PAD_LEFT);
        $nn    = substr(str_pad(preg_replace('/\D/', '', $nossoNumero), 7, '0', STR_PAD_LEFT), -7);
        $dv    = self::dvNossoNumero($nn);
        return "$modal/$nn-$dv";
    }

    /* ── SVG do código de barras (ITF-25) ───────────────────────────────── */

    public static function barcodeSvg(string $digits): string
    {
        $n = 2; $w = 6; $h = 50; $qz = 20;
        if (strlen($digits) % 2 !== 0) $digits = '0' . $digits;

        $els = [];
        $els[] = ['B', $n]; $els[] = ['S', $n]; $els[] = ['B', $n]; $els[] = ['S', $n]; // start

        for ($i = 0; $i < strlen($digits); $i += 2) {
            $e1 = self::ITF[$digits[$i]];
            $e2 = self::ITF[$digits[$i + 1]];
            for ($j = 0; $j < 5; $j++) {
                $els[] = ['B', $e1[$j] ? $w : $n];
                $els[] = ['S', $e2[$j] ? $w : $n];
            }
        }
        $els[] = ['B', $w]; $els[] = ['S', $n]; $els[] = ['B', $n]; // stop

        $tw = $qz * 2;
        foreach ($els as $e) $tw += $e[1];

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="100%" viewBox="0 0 ' . $tw . ' ' . $h . '" preserveAspectRatio="none">';
        $x = $qz;
        foreach ($els as $e) {
            if ($e[0] === 'B') $svg .= '<rect x="' . $x . '" y="0" width="' . $e[1] . '" height="' . $h . '" fill="#000"/>';
            $x += $e[1];
        }
        return $svg . '</svg>';
    }

    /* ── Fator de vencimento (dias desde 07/10/1997) ────────────────────── */

    public static function fatorVencimento(string $dataYmd): string
    {
        try {
            $base = new DateTime('1997-10-07');
            $venc = new DateTime(substr($dataYmd, 0, 10));
            if ($venc <= $base) return '0000';
            $days = (int)$base->diff($venc)->days;
            // Contagem revolving FEBRABAN: após 9999 (≈ 2025-02-22) subtrai 9000
            if ($days > 9999) $days -= 9000;
            return str_pad((string)$days, 4, '0', STR_PAD_LEFT);
        } catch (Throwable $e) {
            return '0000';
        }
    }

    /* ── DV nosso número: módulo 11, fatores 2-9 (padrão Sicoob/ACBr) ──── */

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

    /* ── DV geral do código de barras: módulo 11, fatores 2-9 ──────────── */

    private static function dvGeral(string $num43): int
    {
        $sum = 0; $f = 2;
        for ($i = strlen($num43) - 1; $i >= 0; $i--) {
            $sum += (int)$num43[$i] * $f;
            $f   = ($f === 9) ? 2 : $f + 1;
        }
        $r = $sum % 11;
        return ($r === 0 || $r === 1) ? 1 : 11 - $r;
    }

    /* ── Módulo 10 (campos 1, 2, 3 da linha digitável) ─────────────────── */

    private static function mod10(string $num): int
    {
        $sum = 0; $f = 2;
        for ($i = strlen($num) - 1; $i >= 0; $i--) {
            $p = (int)$num[$i] * $f;
            $sum += $p > 9 ? $p - 9 : $p;
            $f = ($f === 2) ? 1 : 2;
        }
        return (10 - ($sum % 10)) % 10;
    }
}
