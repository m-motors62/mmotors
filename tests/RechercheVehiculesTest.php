<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RechercheVehiculesTest extends WebTestCase
{
    public function testLaSelectionResteVisibleMemeSansResultat(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/vehicules?marque=ZzTestMarqueAbsente&prix_max=1');

        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $crawler->filter('#filter-marque option[selected]')->count());
        $this->assertSame('ZzTestMarqueAbsente', trim($crawler->filter('#filter-marque option[selected]')->text()));
    }
}