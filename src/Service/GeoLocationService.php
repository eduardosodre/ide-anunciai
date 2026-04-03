<?php

declare(strict_types=1);

namespace App\Service;

final class GeoLocationService
{
    public function __construct(private readonly ViaCepClient $viaCep)
    {
    }

    /**
     * Geocodifica por nome de município e, se informado, UF (evita cidades homônimas).
     *
     * @return array{latitude: float, longitude: float, label: string, uf: ?string}|null
     */
    public function resolveFromCity(string $city, ?string $uf = null): ?array
    {
        return $this->resolveFromLocalityUf(trim($city), $this->normalizeUf($uf));
    }

    /**
     * CEP: ViaCEP define localidade e UF; geocodificação usa "Localidade, UF, Brasil".
     * Se o CEP falhar, usa cidade e UF informados no formulário.
     *
     * @return array{latitude: float, longitude: float, label: string, uf: ?string}|null
     */
    public function resolveFromCepOrCity(string $cep, string $fallbackCity, ?string $fallbackUf = null): ?array
    {
        $fromCep = $this->viaCep->consultar($cep);
        if ($fromCep !== null) {
            $loc = trim((string) ($fromCep['localidade'] ?? ''));
            $uf = $this->normalizeUf($fromCep['uf'] ?? null);
            if ($loc !== '') {
                return $this->resolveFromLocalityUf($loc, $uf);
            }
        }

        return $this->resolveFromLocalityUf(trim($fallbackCity), $this->normalizeUf($fallbackUf));
    }

    /**
     * @return array{latitude: float, longitude: float, label: string, uf: ?string}|null
     */
    public function resolveFromLocalityUf(string $localidade, ?string $uf): ?array
    {
        $localidade = trim($localidade);
        if ($localidade === '') {
            return null;
        }

        $query = $uf !== null
            ? $localidade . ', ' . $uf . ', Brasil'
            : $localidade . ', Brasil';

        $url = 'https://nominatim.openstreetmap.org/search?q=' . rawurlencode($query) . '&format=json&limit=1';
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

        $label = $uf !== null ? $localidade . ', ' . $uf : $localidade;

        return [
            'latitude' => $lat,
            'longitude' => $lng,
            'label' => $label,
            'uf' => $uf,
        ];
    }

    private function normalizeUf(?string $uf): ?string
    {
        if ($uf === null) {
            return null;
        }
        $u = strtoupper(trim($uf));
        if (strlen($u) !== 2 || !ctype_alpha($u)) {
            return null;
        }

        return $u;
    }
}
