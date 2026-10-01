<?php

namespace App\Tests;

use App\Entity\Client;
use App\Entity\Contact;
use App\Entity\Dossier;
use App\Entity\Vehicule;
use App\Repository\DossierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DossierRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private DossierRepository $dossierRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->dossierRepository = $this->entityManager->getRepository(Dossier::class);
    }

    public function testClientSansDossierNaPasDeDossierActif(): void
    {
        $client = $this->creerClient('test-sans-dossier@test.com');
        $vehicule = $this->creerVehicule('AA-000-TT');

        $this->assertFalse($this->dossierRepository->hasActiveDossierFor($client, $vehicule));

        $this->nettoyer($client, $vehicule);
    }

    public function testClientAvecDossierEnCoursADossierActif(): void
    {
        $client = $this->creerClient('test-avec-dossier@test.com');
        $vehicule = $this->creerVehicule('AA-001-TT');

        $dossier = new Dossier();
        $dossier->setType('location');
        $dossier->setStatut('en_cours');
        $dossier->setDateCreation(new \DateTimeImmutable());
        $dossier->setClient($client);
        $dossier->setVehicule($vehicule);
        $this->entityManager->persist($dossier);
        $this->entityManager->flush();

        $this->assertTrue($this->dossierRepository->hasActiveDossierFor($client, $vehicule));

        $this->entityManager->remove($dossier);
        $this->nettoyer($client, $vehicule);
    }

    public function testDossierRefuseNEstPasConsidereActif(): void
    {
        $client = $this->creerClient('test-dossier-refuse@test.com');
        $vehicule = $this->creerVehicule('AA-002-TT');

        $dossier = new Dossier();
        $dossier->setType('achat');
        $dossier->setStatut('refuse');
        $dossier->setDateCreation(new \DateTimeImmutable());
        $dossier->setClient($client);
        $dossier->setVehicule($vehicule);
        $this->entityManager->persist($dossier);
        $this->entityManager->flush();

        $this->assertFalse($this->dossierRepository->hasActiveDossierFor($client, $vehicule));

        $this->entityManager->remove($dossier);
        $this->nettoyer($client, $vehicule);
    }

    private function creerClient(string $email): Client
    {
        $contact = new Contact();
        $contact->setNom('Test');
        $contact->setPrenom('Phpunit');
        $contact->setEmail($email);
        $this->entityManager->persist($contact);

        $client = new Client();
        $client->setContact($contact);
        $client->setPassword('test');
        $client->setRoles([]);
        $this->entityManager->persist($client);
        $this->entityManager->flush();

        return $client;
    }

    private function creerVehicule(string $immatriculation): Vehicule
    {
        $vehicule = new Vehicule();
        $vehicule->setMarque('Test');
        $vehicule->setModele('Test');
        $vehicule->setKilometrage(0);
        $vehicule->setPrix(100);
        $vehicule->setStatut('location');
        $vehicule->setAssurance(false);
        $vehicule->setAssistance(false);
        $vehicule->setEntretien(false);
        $vehicule->setControleTechnique(false);
        $vehicule->setImmatriculation($immatriculation);
        $this->entityManager->persist($vehicule);
        $this->entityManager->flush();

        return $vehicule;
    }

    private function nettoyer(Client $client, Vehicule $vehicule): void
    {
        $contact = $client->getContact();
        $this->entityManager->remove($client);
        $this->entityManager->remove($contact);
        $this->entityManager->remove($vehicule);
        $this->entityManager->flush();
    }
}