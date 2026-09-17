<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class VehicleApiClient
{
    private const BASE_URL = 'https://vpic.nhtsa.dot.gov/api/vehicles';

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
    ) {
    }

    /**
     * @return string[]
     */
    public function getMakes(): array
    {
        return $this->cache->get('vehicle_api_makes', function (ItemInterface $item) {
            $item->expiresAfter(86400); // 24h

            $response = $this->httpClient->request('GET', self::BASE_URL.'/GetMakesForVehicleType/car', [
                'query' => ['format' => 'json'],
            ]);

            $data = $response->toArray();

            $makes = array_map(fn (array $result) => $result['MakeName'], $data['Results']);
            sort($makes);

            return array_values(array_unique($makes));
        });
    }

    /**
     * @return string[]
     */
    public function getModelsForMake(string $make): array
    {
        $cacheKey = 'vehicle_api_models_'.md5($make);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($make) {
            $item->expiresAfter(86400);

            $response = $this->httpClient->request('GET', self::BASE_URL.'/getmodelsformake/'.rawurlencode($make), [
                'query' => ['format' => 'json'],
            ]);

            $data = $response->toArray();

            $models = array_map(fn (array $result) => $result['Model_Name'], $data['Results']);
            sort($models);

            return array_values(array_unique($models));
        });
    }
}