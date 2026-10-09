<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityTest extends WebTestCase
{
    public function testAccesRefuseSansAuthentificationSurEspaceAdmin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/vehicules?key=9184710');

        $this->assertResponseRedirects();
    }

    public function testAccesRefuseSansAuthentificationSurEspaceClient(): void
    {
        $client = static::createClient();
        $client->request('GET', '/espace-client');

        $this->assertResponseRedirects();
    }

    public function testPageDeConnexionAdminAccessibleAvecLaCle(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/login?key=9184710');

        $this->assertResponseIsSuccessful();
    }

    public function testPageDeConnexionAdminInaccessibleSansLaCle(): void
    {
        $client = static::createClient();
        $client->getCookieJar()->clear();
        $client->request('GET', '/admin/login');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testPageDeConnexionClientAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/connexion');

        $this->assertResponseIsSuccessful();
    }

    public function testRecherchePubliqueAccessibleSansConnexion(): void
    {
        $client = static::createClient();
        $client->request('GET', '/vehicules');

        $this->assertResponseIsSuccessful();
    }

    public function testLeBoutonDeReinitialisationNApparaitQueSiUnFiltreEstActif(): void
    {
        $client = static::createClient();

        $client->request('GET', '/vehicules');
        $this->assertSelectorNotExists('#reset-filtres');

        $client->request('GET', '/vehicules?mode=location');
        $this->assertSelectorExists('#reset-filtres');
    }
}