<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Exemplo do padrão de injeção de transporte HTTP: em produção usa curl; no teste recebe um
 * transporte com request($method, $url, $headers, $body).
 */
class Sample_api
{
    private $transport;

    public function __construct($transport = null)
    {
        $this->transport = $transport;
    }

    public function schedule_invoice(array $payload)
    {
        $response = $this->request('POST', 'https://api-sandbox.asaas.com/v3/invoices', json_encode($payload));

        return ['status' => $response['status'], 'data' => json_decode($response['body'], true)];
    }

    private function request($method, $url, $body = null)
    {
        $headers = ['access_token' => get_option('sample_api_key'), 'Content-Type' => 'application/json'];

        if ($this->transport !== null) {
            $response = $this->transport->request($method, $url, $headers, $body);

            return ['status' => $response->status, 'body' => $response->body];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => array_map(static fn ($k, $v) => $k . ': ' . $v, array_keys($headers), $headers),
        ]);
        $raw    = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => $raw];
    }
}
