<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ChurchRepository;
use App\Repository\ProfessionalRepository;

final class SearchService
{
    public function __construct(
        private readonly ProfessionalRepository $professionals,
        private readonly ChurchRepository $churches,
        private readonly GeoLocationService $geo
    ) {
    }

    public function search(
        ?string $cep,
        ?string $cidade,
        int $raioKm,
        string $tipo,
        ?int $habilidadeId,
        int $pagina,
        int $limite
    ): array {
        $raioKm = max(1, min(500, $raioKm));
        $pagina = max(1, $pagina);
        $limite = max(1, min(50, $limite));
        $offset = ($pagina - 1) * $limite;
        $tipo = in_array($tipo, ['profissionais', 'igrejas', 'ambos'], true) ? $tipo : 'ambos';

        $center = null;
        if ($cep !== null && trim($cep) !== '') {
            $center = $this->geo->resolveFromCepOrCity($cep, (string) $cidade);
        }
        if ($center === null && $cidade !== null && trim($cidade) !== '') {
            $center = $this->geo->resolveFromCity($cidade);
        }
        if ($center === null) {
            return [
                'items' => [],
                'meta' => [
                    'message' => 'Não foi possível localizar o centro da busca por CEP/cidade.',
                    'pagina' => $pagina,
                    'limite' => $limite,
                    'query' => compact('cep', 'cidade', 'raioKm', 'tipo', 'habilidadeId'),
                ],
            ];
        }

        $items = [];
        if ($tipo === 'ambos' || $tipo === 'profissionais') {
            $pros = $this->professionals->searchByRadius(
                (float) $center['latitude'],
                (float) $center['longitude'],
                $raioKm,
                $habilidadeId,
                $offset,
                $limite
            );
            foreach ($pros as $row) {
                $items[] = [
                    'tipo' => 'profissional',
                    'id' => (int) $row['id'],
                    'usuario_id' => (int) $row['usuario_id'],
                    'nome' => (string) $row['nome_publico'],
                    'cidade' => (string) $row['cidade'],
                    'verificado' => (int) $row['verificado'] === 1,
                    'distancia_km' => round((float) $row['distancia_km'], 2),
                    'habilidades' => array_map(
                        static fn (array $s): array => ['id' => (int) $s['id'], 'label' => (string) $s['label']],
                        $row['habilidades'] ?? []
                    ),
                    'url' => '/perfil/profissional/' . (int) $row['id'],
                ];
            }
        }
        if ($tipo === 'ambos' || $tipo === 'igrejas') {
            $igs = $this->churches->searchByRadius(
                (float) $center['latitude'],
                (float) $center['longitude'],
                $raioKm,
                $offset,
                $limite
            );
            foreach ($igs as $row) {
                $items[] = [
                    'tipo' => 'igreja',
                    'id' => (int) $row['id'],
                    'usuario_id' => (int) $row['usuario_id'],
                    'nome' => (string) $row['nome_igreja'],
                    'cidade' => (string) $row['cidade'],
                    'verificado' => (int) $row['verificado'] === 1,
                    'distancia_km' => round((float) $row['distancia_km'], 2),
                    'url' => '/perfil/igreja/' . (int) $row['id'],
                ];
            }
        }
        usort($items, static fn (array $a, array $b): int => ($a['distancia_km'] <=> $b['distancia_km']));
        $items = array_slice($items, 0, $limite);

        return [
            'items' => $items,
            'meta' => [
                'message' => 'Busca realizada com sucesso.',
                'centro' => $center,
                'pagina' => $pagina,
                'limite' => $limite,
                'query' => compact('cep', 'cidade', 'raioKm', 'tipo', 'habilidadeId'),
            ],
        ];
    }
}
