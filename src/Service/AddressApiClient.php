<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class AddressApiClient
{
    private const BASE_URL = 'https://data.geopf.fr/geocodage/completion/';

    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    /**
     * @return array<int, array{label: string, adresse: string, codePostal: string, ville: string}>
     */
    public function search(string $text): array
    {
        if (mb_strlen($text) < 3) {
            return [];
        }

        $response = $this->httpClient->request('GET', self::BASE_URL, [
            'query' => [
                'text' => $text,
                'type' => 'StreetAddress',
                'maximumResponses' => 5,
            ],
        ]);

        $data = $response->toArray();

        $results = [];
        foreach ($data['results'] ?? [] as $result) {
            $fulltext = $result['fulltext'] ?? '';
            $zipcode = $result['zipcode'] ?? '';
            $city = $result['city'] ?? '';

            // fulltext est du type "10 Square Michelet, 62200 Boulogne-sur-Mer"
            // on retire la partie ", CODE_POSTAL VILLE" pour ne garder que l'adresse
            $adresseComplete = preg_replace('/,\s*' . preg_quote($zipcode, '/') . '\s+' . preg_quote($city, '/') . '$/u', '', $fulltext);

            $results[] = [
                'label' => $fulltext,
                'adresse' => trim($adresseComplete),
                'codePostal' => $zipcode,
                'ville' => $city,
            ];
        }

        return $results;
    }
}