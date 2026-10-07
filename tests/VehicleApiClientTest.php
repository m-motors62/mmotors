<?php

namespace App\Tests;

use App\Service\VehicleApiClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class VehicleApiClientTest extends TestCase
{
    private function catalogue(): string
    {
        return json_encode(['makes' => [
            ['name' => 'Renault', 'kinds' => ['car', 'van'], 'models' => [
                ['name' => 'Clio', 'kind' => 'car'],
                ['name' => 'Captur', 'kind' => 'car'],
                ['name' => 'Clio', 'kind' => 'car'],
                ['name' => 'Master', 'kind' => 'van'],
            ]],
            ['name' => 'Dacia', 'kinds' => ['car'], 'models' => [
                ['name' => 'Sandero', 'kind' => 'car'],
            ]],
            ['name' => 'Yamaha', 'kinds' => ['moped'], 'models' => [
                ['name' => 'Aerox', 'kind' => 'moped'],
            ]],
        ]]);
    }

    public function testSeulesLesMarquesAutomobilesSontRenvoyeesEtTriees(): void
    {
        $client = new VehicleApiClient(new MockHttpClient(new MockResponse($this->catalogue())), new ArrayAdapter());

        $this->assertSame(['Dacia', 'Renault'], $client->getMakes());
    }

    public function testModelesFiltresSurLesVoituresSansDoublonEtTries(): void
    {
        $client = new VehicleApiClient(new MockHttpClient(new MockResponse($this->catalogue())), new ArrayAdapter());

        $this->assertSame(['Captur', 'Clio'], $client->getModelsForMake('Renault'));
    }

    public function testRechercheDeMarqueInsensibleALaCasse(): void
    {
        $client = new VehicleApiClient(new MockHttpClient(new MockResponse($this->catalogue())), new ArrayAdapter());

        $this->assertSame(['Sandero'], $client->getModelsForMake('dAcIa'));
    }

    public function testMarqueInconnueRenvoieUneListeVide(): void
    {
        $client = new VehicleApiClient(new MockHttpClient(new MockResponse($this->catalogue())), new ArrayAdapter());

        $this->assertSame([], $client->getModelsForMake('Marque inexistante'));
    }

    public function testLeCatalogueNEstTelechargeQuUneFoisGraceAuCache(): void
    {
        $appels = 0;
        $httpClient = new MockHttpClient(function () use (&$appels) {
            $appels++;

            return new MockResponse($this->catalogue());
        });
        $client = new VehicleApiClient($httpClient, new ArrayAdapter());

        $client->getMakes();
        $client->getModelsForMake('Dacia');
        $client->getMakes();

        $this->assertSame(1, $appels);
    }
}