<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class VehicleApiClient
{
    private const CATALOG_URL = 'https://cdn.jsdelivr.net/gh/vehiclesdb/vehiclesdb@latest/dist/vehicles.json';

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
    ) {
    }

    private function getCatalog(): array
    {
        return $this->cache->get('vehicledb_catalog', function (ItemInterface $item) {
            $item->expiresAfter(30 * 86400); // 30 jours, cohérent avec la cadence mensuelle des données

            $response = $this->httpClient->request('GET', self::CATALOG_URL);

            return $response->toArray();
        });
    }

    /**
     * @return string[]
     */
    public function getMakes(): array
    {
        $catalog = $this->getCatalog();

        $makes = array_filter($catalog['makes'], fn (array $make) => in_array('car', $make['kinds'], true));
        $names = array_map(fn (array $make) => $make['name'], $makes);
        sort($names);

        return array_values($names);
    }

    /**
     * @return string[]
     */
    public function getModelsForMake(string $make): array
    {
        $catalog = $this->getCatalog();

        foreach ($catalog['makes'] as $makeEntry) {
            if (strtolower($makeEntry['name']) === strtolower($make)) {
                $models = array_filter($makeEntry['models'], fn (array $model) => $model['kind'] === 'car');
                $names = array_map(fn (array $model) => $model['name'], $models);
                sort($names);

                return array_values(array_unique($names));
            }
        }

        return [];
    }
}