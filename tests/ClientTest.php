<?php

namespace App\Tests;

use App\Entity\Client;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    public function testRoleClientToujoursPresent(): void
    {
        $client = new Client();
        $client->setRoles([]);

        $this->assertContains('ROLE_CLIENT', $client->getRoles());
    }

    public function testRoleClientNonDuplique(): void
    {
        $client = new Client();
        $client->setRoles(['ROLE_CLIENT']);

        $roles = $client->getRoles();
        $occurrences = array_count_values($roles)['ROLE_CLIENT'];

        $this->assertSame(1, $occurrences);
    }

    public function testGetUserIdentifierUtiliseEmailDuContact(): void
    {
        $contact = new \App\Entity\Contact();
        $contact->setNom('Dupont');
        $contact->setPrenom('Jean');
        $contact->setEmail('jean.dupont@example.com');

        $client = new Client();
        $client->setContact($contact);

        $this->assertSame('jean.dupont@example.com', $client->getUserIdentifier());
    }
}