<?php

declare(strict_types=1);

namespace App\Service;

final class ViaCepClient
{
    public function consultar(string $cep): ?array
    {
        $digits = preg_replace('/\D/u', '', $cep) ?? '';
        if (strlen($digits) !== 8) {
            return null;
        }

        $url = 'https://viacep.com.br/ws/' . $digits . '/json/';
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 6,
                'header' => "Accept: application/json\r\n",
            ],
        ]);

        $raw = @file_get_contents($url, false, $ctx);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }
        if (isset($data['erro']) && $data['erro'] === true) {
            return null;
        }

        $localidade = isset($data['localidade']) ? trim((string) $data['localidade']) : '';
        $uf = isset($data['uf']) ? strtoupper(trim((string) $data['uf'])) : '';

        if ($localidade === '') {
            return null;
        }

        return [
            'localidade' => $localidade,
            'uf' => $uf,
            'cidade' => $localidade . ($uf !== '' ? ' - ' . $uf : ''),
            'logradouro' => isset($data['logradouro']) ? (string) $data['logradouro'] : '',
            'bairro' => isset($data['bairro']) ? (string) $data['bairro'] : '',
        ];
    }
}
