<?php

namespace App\Tests;

use App\Entity\Client;
use App\Entity\User;
use App\Security\ClientChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

class ClientCheckerTest extends TestCase
{
    public function testClientNonVerifieEstRefuse(): void
    {
        $client = new Client();
        $client->setIsVerified(false);

        $this->expectException(CustomUserMessageAccountStatusException::class);

        (new ClientChecker())->checkPreAuth($client);
    }

    public function testClientVerifieEstAccepte(): void
    {
        $client = new Client();
        $client->setIsVerified(true);

        $this->expectNotToPerformAssertions();

        (new ClientChecker())->checkPreAuth($client);
    }

    public function testUnUtilisateurDuBackOfficeNEstPasConcerneParLaVerification(): void
    {
        $this->expectNotToPerformAssertions();

        (new ClientChecker())->checkPreAuth(new User());
    }
}