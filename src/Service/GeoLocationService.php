<?php

declare(strict_types=1);

namespace App\Service;

final class GeoLocationService
{
    public function __construct(private readonly ViaCepClient $viaCep)
    {
    }

    public function resolveFromCity(string $city): ?array
    {
        $city = trim($city);
        if ($city === '') {
            return null;
        }

        $query = rawurlencode($city . ', Brasil');
        $url = 'https://nominatim.openstreetmap.org/search?q=' . $query . '&format=json&limit=1';
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 8,
                'header' => "Accept: application/json\r\nUser-Agent: ide-anunciai/1.0\r\n",
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data[0]) || !is_array($data[0])) {
            return null;
        }
        $lat = isset($data[0]['lat']) ? (float) $data[0]['lat'] : 0.0;
        $lng = isset($data[0]['lon']) ? (float) $data[0]['lon'] : 0.0;
        if ($lat === 0.0 && $lng === 0.0) {
            return null;
        }

        return ['latitude' => $lat, 'longitude' => $lng];
    }

    public function resolveFromCepOrCity(string $cep, string $fallbackCity): ?array
    {
        $resolvedCity = $fallbackCity;
        $fromCep = $this->viaCep->consultar($cep);
        if ($fromCep !== null && isset($fromCep['cidade']) && trim((string) $fromCep['cidade']) !== '') {
            $resolvedCity = (string) $fromCep['cidade'];
        }

        return $this->resolveFromCity($resolvedCity);
    }
}
