<?php

namespace App\Tests;

use App\Entity\Client;
use App\Entity\User;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

class UserCheckerTest extends TestCase
{
    public function testCompteActifEstAccepte(): void
    {
        $user = new User();
        $user->setIsActive(true);

        $this->expectNotToPerformAssertions();

        (new UserChecker())->checkPostAuth($user);
    }

    public function testCompteDesactiveEstRefuse(): void
    {
        $user = new User();
        $user->setIsActive(false);

        $this->expectException(CustomUserMessageAccountStatusException::class);

        (new UserChecker())->checkPostAuth($user);
    }

    public function testUnClientNEstPasConcerneParCeControle(): void
    {
        $this->expectNotToPerformAssertions();

        (new UserChecker())->checkPostAuth(new Client());
    }
}