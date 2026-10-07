<?php

namespace App\Tests;

use App\Service\AddressApiClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class AddressApiClientTest extends TestCase
{
    public function testTexteTropCourtNAppellePasLApi(): void
    {
        $appels = 0;
        $httpClient = new MockHttpClient(function () use (&$appels) {
            $appels++;

            return new MockResponse('{}');
        });

        $resultats = (new AddressApiClient($httpClient))->search('ab');

        $this->assertSame([], $resultats);
        $this->assertSame(0, $appels);
    }

    public function testAdresseRecomposeeSansCodePostalNiVille(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            $this->assertSame('GET', $method);
            $this->assertStringContainsString('geocodage/completion', $url);

            return new MockResponse(json_encode([
                'status' => 'OK',
                'results' => [[
                    'fulltext' => '10 Square Michelet, 62200 Boulogne-sur-Mer',
                    'zipcode' => '62200',
                    'city' => 'Boulogne-sur-Mer',
                    'street' => 'Square Michelet',
                ]],
            ]));
        });

        $resultats = (new AddressApiClient($httpClient))->search('10 square michelet');

        $this->assertCount(1, $resultats);
        $this->assertSame('10 Square Michelet', $resultats[0]['adresse']);
        $this->assertSame('62200', $resultats[0]['codePostal']);
        $this->assertSame('Boulogne-sur-Mer', $resultats[0]['ville']);
        $this->assertSame('10 Square Michelet, 62200 Boulogne-sur-Mer', $resultats[0]['label']);
    }

    public function testVilleAvecApostropheEtAccentsDansLaRegex(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'status' => 'OK',
            'results' => [[
                'fulltext' => '1 Rue Test, 94240 L\'Haÿ-les-Roses',
                'zipcode' => '94240',
                'city' => 'L\'Haÿ-les-Roses',
            ]],
        ])));

        $resultats = (new AddressApiClient($httpClient))->search('1 rue test');

        $this->assertSame('1 Rue Test', $resultats[0]['adresse']);
        $this->assertSame('L\'Haÿ-les-Roses', $resultats[0]['ville']);
    }

    public function testReponseSansResultat(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('{"status":"OK","results":[]}'));

        $this->assertSame([], (new AddressApiClient($httpClient))->search('adresse inconnue'));
    }
}