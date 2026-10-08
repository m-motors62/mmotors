<?php

namespace App\Tests;

use App\Entity\Client;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserEquatableTest extends TestCase
{
    public function testMemeUtilisateurActifEstEgal(): void
    {
        $session = $this->user('a@test.com', 'hash', ['ROLE_COMMERCIAL']);
        $recharge = $this->user('a@test.com', 'hash', ['ROLE_COMMERCIAL']);

        $this->assertTrue($session->isEqualTo($recharge));
    }

    public function testCompteDesactiveEnBaseEstDeconnecte(): void
    {
        $session = $this->user('a@test.com', 'hash', ['ROLE_COMMERCIAL']);
        $recharge = $this->user('a@test.com', 'hash', ['ROLE_COMMERCIAL'], false);

        $this->assertFalse($session->isEqualTo($recharge));
    }

    public function testChangementDeMotDePasseDeconnecte(): void
    {
        $session = $this->user('a@test.com', 'ancien', ['ROLE_COMMERCIAL']);
        $recharge = $this->user('a@test.com', 'nouveau', ['ROLE_COMMERCIAL']);

        $this->assertFalse($session->isEqualTo($recharge));
    }

    public function testEmpreinteCrc32cEnSessionResteValide(): void
    {
        $session = $this->user('a@test.com', hash('crc32c', 'hash-complet'), ['ROLE_COMMERCIAL']);
        $recharge = $this->user('a@test.com', 'hash-complet', ['ROLE_COMMERCIAL']);

        $this->assertTrue($session->isEqualTo($recharge));
    }

    public function testChangementDeRoleDeconnecte(): void
    {
        $session = $this->user('a@test.com', 'hash', ['ROLE_ADMIN']);
        $recharge = $this->user('a@test.com', 'hash', ['ROLE_COMMERCIAL']);

        $this->assertFalse($session->isEqualTo($recharge));
    }

    public function testUnAutreTypeDUtilisateurNEstPasEgal(): void
    {
        $this->assertFalse($this->user('a@test.com', 'hash', ['ROLE_COMMERCIAL'])->isEqualTo(new Client()));
    }

    private function user(string $email, string $motDePasse, array $roles, bool $actif = true): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword($motDePasse);
        $user->setRoles($roles);
        $user->setIsActive($actif);

        return $user;
    }
}