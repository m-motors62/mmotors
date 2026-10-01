<?php

namespace App\Tests;

use App\Entity\Vehicule;
use PHPUnit\Framework\TestCase;

class VehiculeTest extends TestCase
{
    public function testSlugAvecTousLesChamps(): void
    {
        $vehicule = $this->creerVehiculeAvecId(16, 'Renault', 'Clio', 'Essence', 'Bleu');

        $this->assertSame('renault-clio-essence-bleu-16', $vehicule->getSlug());
    }

    public function testSlugSansMotorisationNiCouleur(): void
    {
        $vehicule = $this->creerVehiculeAvecId(20, 'Peugeot', '208', null, null);

        $this->assertSame('peugeot-208-20', $vehicule->getSlug());
    }

    public function testSlugAvecCaracteresSpeciaux(): void
    {
        $vehicule = $this->creerVehiculeAvecId(5, 'Škoda', 'Octavia', 'Électrique', null);

        $this->assertSame('skoda-octavia-electrique-5', $vehicule->getSlug());
    }

    public function testSlugSeTermineToujoursParLId(): void
    {
        $vehicule = $this->creerVehiculeAvecId(42, 'Dacia', 'Sandero', 'Essence', 'Rouge');

        $this->assertStringEndsWith('-42', $vehicule->getSlug());
    }

    private function creerVehiculeAvecId(int $id, string $marque, string $modele, ?string $motorisation, ?string $couleur): Vehicule
    {
        $vehicule = new Vehicule();
        $vehicule->setMarque($marque);
        $vehicule->setModele($modele);
        $vehicule->setMotorisation($motorisation);
        $vehicule->setCouleur($couleur);

        $reflection = new \ReflectionClass($vehicule);
        $property = $reflection->getProperty('id');
        $property->setValue($vehicule, $id);

        return $vehicule;
    }
}