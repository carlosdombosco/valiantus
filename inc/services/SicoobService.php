<?php
declare(strict_types=1);

/**
 * SicoobService — Integração com a API Aberta Sicoob (Cobrança Bancária v3)
 *
 * Documentação oficial: https://developers.sicoob.com.br/
 * Autenticação: OAuth 2.0 client_credentials + mTLS (certificado A1/A3)
 *
 * Configuração necessária em inc/config.php:
 *   SICOOB_CLIENT_ID, SICOOB_CLIENT_SECRET, SICOOB_COOPERATIVA,
 *   SICOOB_CONTA, SICOOB_MODALIDADE, SICOOB_CERT_PATH, SICOOB_CERT_KEY
 */
class SicoobService
{
    private const BASE_URL   = 'https://api.sicoob.com.br/cobranca-bancaria/v3';
    private const TOKEN_URL  = 'https://auth.sicoob.com.br/auth/realms/cooperado/protocol/openid-connect/token';
    private const SCOPE      = 'cobranca_bancaria-leitura cobranca_bancaria-alteracao';

    private string $clientId;
    private string $clientSecret;
    private int    $cooperativa;
    private int    $conta;
    private int    $modalidade;
    private string $certPath;
    private string $certKeyPath;

    private ?string $accessToken  = null;
    private int     $tokenExpires = 0;

    /**
     * @param array|null $cfg  Row from tb_configuracoes. When null, falls back to PHP constants.
     */
    public function __construct(?array $cfg = null)
    {
        if ($cfg !== null) {
            $this->clientId    = $cfg['CFG_SICOOB_CLIENT_ID']     ?? '';
            $this->clientSecret= $cfg['CFG_SICOOB_CLIENT_SECRET'] ?? '';
            $this->cooperativa = (int)($cfg['CFG_SICOOB_COOPERATIVA'] ?? 0);
            $this->conta       = (int)($cfg['CFG_SICOOB_CONTA']       ?? 0);
            $this->modalidade  = (int)($cfg['CFG_SICOOB_MODALIDADE']  ?? 1);
            $this->certPath    = $cfg['CFG_SICOOB_CERT_PATH'] ?? '';
            $this->certKeyPath = $cfg['CFG_SICOOB_CERT_KEY']  ?? '';
        } else {
            $this->clientId    = defined('SICOOB_CLIENT_ID')     ? SICOOB_CLIENT_ID     : '';
            $this->clientSecret= defined('SICOOB_CLIENT_SECRET') ? SICOOB_CLIENT_SECRET : '';
            $this->cooperativa = defined('SICOOB_COOPERATIVA')   ? (int)SICOOB_COOPERATIVA : 0;
            $this->conta       = defined('SICOOB_CONTA')         ? (int)SICOOB_CONTA       : 0;
            $this->modalidade  = defined('SICOOB_MODALIDADE')    ? (int)SICOOB_MODALIDADE  : 1;
            $this->certPath    = defined('SICOOB_CERT_PATH')     ? SICOOB_CERT_PATH     : '';
            $this->certKeyPath = defined('SICOOB_CERT_KEY')      ? SICOOB_CERT_KEY      : '';
        }
    }

    /* ── Autenticação OAuth2 ─────────────────────────────────────────── */

    /**
     * Obtém (ou renova) o access token via client_credentials.
     * @throws RuntimeException
     */
    public function autenticar(): string
    {
        if ($this->accessToken && time() < $this->tokenExpires - 30) {
            return $this->accessToken;
        }

        $ch = curl_init(self::TOKEN_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type'    => 'client_credentials',
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope'         => self::SCOPE,
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $this->aplicarCertificado($ch);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) throw new RuntimeException('Sicoob auth cURL error: ' . $curlErr);
        if ($httpCode !== 200) throw new RuntimeException('Sicoob auth HTTP ' . $httpCode . ': ' . $response);

        $data = json_decode($response, true);
        if (empty($data['access_token'])) {
            throw new RuntimeException('Sicoob: token não retornado. ' . $response);
        }

        $this->accessToken  = $data['access_token'];
        $this->tokenExpires = time() + (int)($data['expires_in'] ?? 300);

        return $this->accessToken;
    }

    /* ── Emissão de Boleto ───────────────────────────────────────────── */

    /**
     * Emite um boleto via API Sicoob.
     *
     * @param array $dados Dados do boleto:
     *   - valor          (float)  Valor do boleto
     *   - vencimento     (string) Data de vencimento Y-m-d
     *   - nome_pagador   (string) Nome do associado
     *   - cpf_pagador    (string) CPF/CNPJ do associado (somente dígitos)
     *   - nosso_numero   (string) Nosso número (gerado internamente)
     *   - descricao      (string) Descrição/mensagem
     * @return array Dados retornados pela API (nosso_numero, linha_digitavel, cod_barras, etc.)
     * @throws RuntimeException
     */
    public function emitirBoleto(array $dados): array
    {
        $token = $this->autenticar();

        $payload = [
            'numeroContrato'   => $this->conta,
            'modalidade'       => $this->modalidade,
            'numeroCooperativa'=> $this->cooperativa,
            'dataVencimento'   => $dados['vencimento'],
            'valor'            => round((float)$dados['valor'], 2),
            'seuNumero'        => $dados['nosso_numero'] ?? '',
            'mensagem'         => ['linha1' => $dados['descricao'] ?? 'Mensalidade Associação Veicular'],
            'pagador'          => [
                'nome'          => $dados['nome_pagador'],
                'cpfCnpj'       => preg_replace('/\D/', '', $dados['cpf_pagador']),
                'tipoPessoa'    => strlen(preg_replace('/\D/', '', $dados['cpf_pagador'])) === 11 ? 'FISICA' : 'JURIDICA',
            ],
        ];

        $response = $this->request('POST', '/cobranças', $payload, $token);
        return $response;
    }

    /**
     * Consulta situação de um boleto pelo nosso número.
     * @throws RuntimeException
     */
    public function consultarBoleto(string $nossoNumero): array
    {
        $token = $this->autenticar();
        return $this->request('GET', '/cobranças/' . $this->modalidade . '/' . $nossoNumero, null, $token);
    }

    /**
     * Cancela um boleto pelo nosso número.
     * @throws RuntimeException
     */
    public function cancelarBoleto(string $nossoNumero, string $motivo = 'A pedido do cliente'): array
    {
        $token = $this->autenticar();
        return $this->request('DELETE', '/cobranças/' . $this->modalidade . '/' . $nossoNumero, ['motivo' => $motivo], $token);
    }

    /**
     * Lista boletos por período.
     * @throws RuntimeException
     */
    public function listarBoletos(string $dataInicio, string $dataFim): array
    {
        $token = $this->autenticar();
        $qs    = http_build_query([
            'numeroCooperativa' => $this->cooperativa,
            'numeroConta'       => $this->conta,
            'modalidade'        => $this->modalidade,
            'dataInicio'        => $dataInicio,
            'dataFim'           => $dataFim,
        ]);
        return $this->request('GET', '/cobranças?' . $qs, null, $token);
    }

    /* ── Helpers privados ────────────────────────────────────────────── */

    private function request(string $method, string $path, ?array $body, string $token): array
    {
        $ch = curl_init(self::BASE_URL . $path);
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
        ]);

        if ($body !== null && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }

        $this->aplicarCertificado($ch);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) throw new RuntimeException('Sicoob cURL error: ' . $curlErr);

        $data = json_decode($response ?: '{}', true) ?? [];

        if ($httpCode >= 400) {
            $msg = $data['mensagem'] ?? ($data['message'] ?? 'Erro HTTP ' . $httpCode);
            throw new RuntimeException('Sicoob API ' . $httpCode . ': ' . $msg);
        }

        return $data;
    }

    private function aplicarCertificado(\CurlHandle $ch): void
    {
        if ($this->certPath && file_exists($this->certPath)) {
            curl_setopt($ch, CURLOPT_SSLCERT, $this->certPath);
        }
        if ($this->certKeyPath && file_exists($this->certKeyPath)) {
            curl_setopt($ch, CURLOPT_SSLKEY, $this->certKeyPath);
        }
        // Em produção: CURLOPT_SSL_VERIFYPEER deve ser true
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !defined('SICOOB_DEV') || !SICOOB_DEV);
    }

    /* ── Verificação de configuração ─────────────────────────────────── */

    public function configurado(): bool
    {
        return $this->clientId !== '' && $this->cooperativa > 0 && $this->conta > 0;
    }

    public function statusConfig(): array
    {
        return [
            'client_id'    => $this->clientId   !== '' ? '✓ configurado' : '✗ ausente',
            'cooperativa'  => $this->cooperativa > 0   ? '✓ ' . $this->cooperativa : '✗ ausente',
            'conta'        => $this->conta       > 0   ? '✓ ' . $this->conta       : '✗ ausente',
            'modalidade'   => $this->modalidade,
            'certificado'  => $this->certPath && file_exists($this->certPath) ? '✓ encontrado' : '✗ não encontrado',
        ];
    }
}
