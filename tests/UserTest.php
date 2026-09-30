<?php

namespace App\Tests;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testAdminPeutGererNimporteQui(): void
    {
        $admin = $this->creerUserAvecId(1, ['ROLE_ADMIN']);
        $autreUser = $this->creerUserAvecId(2, ['ROLE_COMMERCIAL']);

        $this->assertTrue($autreUser->canBeManagedBy($admin));
    }

    public function testUtilisateurNePeutPasSeGererLuiMeme(): void
    {
        $admin = $this->creerUserAvecId(1, ['ROLE_ADMIN']);

        $this->assertFalse($admin->canBeManagedBy($admin));
    }

    public function testGestionnaireNePeutPasGererUnAdmin(): void
    {
        $gestionnaire = $this->creerUserAvecId(1, ['ROLE_GESTIONNAIRE']);
        $admin = $this->creerUserAvecId(2, ['ROLE_ADMIN']);

        $this->assertFalse($admin->canBeManagedBy($gestionnaire));
    }

    public function testGestionnairePeutGererUnCommercial(): void
    {
        $gestionnaire = $this->creerUserAvecId(1, ['ROLE_GESTIONNAIRE']);
        $commercial = $this->creerUserAvecId(2, ['ROLE_COMMERCIAL']);

        $this->assertTrue($commercial->canBeManagedBy($gestionnaire));
    }

    public function testCommercialNePeutGererPersonne(): void
    {
        $commercial = $this->creerUserAvecId(1, ['ROLE_COMMERCIAL']);
        $autreCommercial = $this->creerUserAvecId(2, ['ROLE_COMMERCIAL']);

        $this->assertFalse($autreCommercial->canBeManagedBy($commercial));
    }

    private function creerUserAvecId(int $id, array $roles): User
    {
        $user = new User();
        $user->setRoles($roles);

        $reflection = new \ReflectionClass($user);
        $property = $reflection->getProperty('id');
        $property->setValue($user, $id);

        return $user;
    }
}