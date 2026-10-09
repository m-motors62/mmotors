<?php

namespace App\Tests;

use App\Entity\Client;
use App\Entity\Contact;
use App\Entity\Dossier;
use App\Entity\Vehicule;
use App\Repository\VehiculeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class VehiculeRepositoryTest extends KernelTestCase
{
    private const EMAIL_CLIENT = 'test-vehicule-repo@test.com';

    private EntityManagerInterface $entityManager;
    private VehiculeRepository $repository;
    private ?Client $client = null;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository = $this->entityManager->getRepository(Vehicule::class);
        $this->nettoyer();
    }

    protected function tearDown(): void
    {
        $this->nettoyer();
        parent::tearDown();
    }

    public function testRechercheParModeNeRenvoieQueLeModeDemande(): void
    {
        $this->creerVehicule('ZzTestAlpha', 'Clio', 'Essence', 'location', 200, 10000, 'ZT-001-AA');
        $this->creerVehicule('ZzTestAlpha', 'Golf', 'Diesel', 'vente', 15000, 40000, 'ZT-002-AA');

        $resultats = $this->repository->search(mode: 'location', marque: 'ZzTestAlpha');

        $this->assertSame(['Clio'], $this->modeles($resultats));
    }

    public function testRechercheParPrixMaximumEtKilometrageMaximum(): void
    {
        $this->creerVehicule('ZzTestAlpha', 'Clio', 'Essence', 'location', 200, 10000, 'ZT-001-AA');
        $this->creerVehicule('ZzTestAlpha', 'Golf', 'Diesel', 'vente', 15000, 40000, 'ZT-002-AA');

        $this->assertSame(['Clio'], $this->modeles($this->repository->search(marque: 'ZzTestAlpha', prixMax: 1000)));
        $this->assertSame(['Clio'], $this->modeles($this->repository->search(marque: 'ZzTestAlpha', kilometrageMax: 20000)));
        $this->assertCount(2, $this->repository->search(marque: 'ZzTestAlpha', kilometrageMax: 50000));
    }

    public function testRechercheInsensibleALaCasse(): void
    {
        $this->creerVehicule('ZzTestAlpha', 'Clio', 'Essence', 'location', 200, 10000, 'ZT-001-AA');
        $this->creerVehicule('ZzTestAlpha', 'Golf', 'Diesel', 'vente', 15000, 40000, 'ZT-002-AA');

        $resultats = $this->repository->search(marque: 'zztestalpha', motorisation: 'diesel');

        $this->assertSame(['Golf'], $this->modeles($resultats));
    }

    public function testVehiculeArchiveExcluDeLaRecherchePublique(): void
    {
        $this->creerVehicule('ZzTestBeta', 'Polo', 'Essence', 'vente', 9000, 30000, 'ZT-003-AA', true);

        $this->assertSame([], $this->repository->search(marque: 'ZzTestBeta'));
    }

    public function testVehiculeAvecDossierValideEstExclu(): void
    {
        $vehicule = $this->creerVehicule('ZzTestGamma', 'Sandero', 'Essence', 'location', 180, 8000, 'ZT-004-AA');
        $this->creerDossier($vehicule, 'valide');

        $this->assertSame([], $this->repository->search(marque: 'ZzTestGamma'));
    }

    public function testVehiculeAvecDossierEnCoursResteVisible(): void
    {
        $vehicule = $this->creerVehicule('ZzTestGamma', 'Sandero', 'Essence', 'location', 180, 8000, 'ZT-004-AA');
        $this->creerDossier($vehicule, 'en_cours');

        $this->assertCount(1, $this->repository->search(marque: 'ZzTestGamma'));
    }

    public function testListeDesMarquesDependDesAutresFiltres(): void
    {
        $this->creerJeuDeDonneesFiltres();

        $diesel = $this->repository->getMarquesDisponibles(motorisation: 'Diesel');
        $this->assertContains('ZzTestAlpha', $diesel);
        $this->assertContains('ZzTestDelta', $diesel);

        $essence = $this->repository->getMarquesDisponibles(motorisation: 'Essence');
        $this->assertContains('ZzTestAlpha', $essence);
        $this->assertNotContains('ZzTestDelta', $essence);
    }

    public function testListeDesModelesFiltreeParMarqueMotorisationEtMode(): void
    {
        $this->creerJeuDeDonneesFiltres();

        $this->assertSame(['Clio', 'Golf'], $this->repository->getModelesDisponibles('ZzTestAlpha'));
        $this->assertSame(['Golf'], $this->repository->getModelesDisponibles('ZzTestAlpha', 'Diesel'));
        $this->assertSame(['Clio'], $this->repository->getModelesDisponibles('ZzTestAlpha', null, 'location'));
    }

    public function testListesDesModesEtDesMotorisations(): void
    {
        $this->creerJeuDeDonneesFiltres();

        $this->assertSame(['location', 'vente'], $this->repository->getModesDisponibles('ZzTestAlpha'));
        $this->assertSame(['vente'], $this->repository->getModesDisponibles('ZzTestAlpha', null, 'Diesel'));
        $this->assertSame(['Diesel', 'Essence'], $this->repository->getMotorisationsDisponibles('ZzTestAlpha'));
        $this->assertSame(['Essence'], $this->repository->getMotorisationsDisponibles('ZzTestAlpha', 'Clio'));
    }

    public function testIdentifiantsDesVehiculesAvecDossierActif(): void
    {
        $actif = $this->creerVehicule('ZzTestGamma', 'Sandero', 'Essence', 'location', 180, 8000, 'ZT-004-AA');
        $refuse = $this->creerVehicule('ZzTestGamma', 'Duster', 'Diesel', 'location', 220, 12000, 'ZT-005-AA');
        $this->creerDossier($actif, 'en_cours');
        $this->creerDossier($refuse, 'refuse');

        $ids = $this->repository->findVehiculeIdsWithActiveDossier();

        $this->assertContains($actif->getId(), $ids);
        $this->assertNotContains($refuse->getId(), $ids);
    }

    public function testListeAdminExclutLesArchivesSaufSiDemande(): void
    {
        $actif = $this->creerVehicule('ZzTestEpsilon', 'Zoe', 'Electrique', 'location', 250, 5000, 'ZT-006-AA');
        $archive = $this->creerVehicule('ZzTestEpsilon', 'Twingo', 'Essence', 'location', 150, 90000, 'ZT-007-AA', true);

        $sansArchives = $this->ids($this->repository->findAllFiltered());
        $avecArchives = $this->ids($this->repository->findAllFiltered(true));

        $this->assertContains($actif->getId(), $sansArchives);
        $this->assertNotContains($archive->getId(), $sansArchives);
        $this->assertContains($archive->getId(), $avecArchives);
    }

    public function testLesListesTiennentCompteDuPrixEtDuKilometrage(): void
    {
        $this->creerJeuDeDonneesFiltres();

        $marques = $this->repository->getMarquesDisponibles(prixMax: 1000);
        $this->assertContains('ZzTestAlpha', $marques);
        $this->assertNotContains('ZzTestDelta', $marques);

        $this->assertSame(['Clio'], $this->repository->getModelesDisponibles('ZzTestAlpha', kilometrageMax: 20000));
        $this->assertSame(['location'], $this->repository->getModesDisponibles('ZzTestAlpha', prixMax: 1000));
        $this->assertSame(['Essence'], $this->repository->getMotorisationsDisponibles('ZzTestAlpha', prixMax: 1000));
    }

    private function creerJeuDeDonneesFiltres(): void
    {
        $this->creerVehicule('ZzTestAlpha', 'Clio', 'Essence', 'location', 200, 10000, 'ZT-101-AA');
        $this->creerVehicule('ZzTestAlpha', 'Golf', 'Diesel', 'vente', 15000, 40000, 'ZT-102-AA');
        $this->creerVehicule('ZzTestDelta', 'Polo', 'Diesel', 'vente', 12000, 35000, 'ZT-103-AA');
    }

    private function creerVehicule(string $marque, string $modele, ?string $motorisation, string $statut, float $prix, int $km, string $immatriculation, bool $archive = false): Vehicule
    {
        $vehicule = new Vehicule();
        $vehicule->setMarque($marque);
        $vehicule->setModele($modele);
        $vehicule->setMotorisation($motorisation);
        $vehicule->setKilometrage($km);
        $vehicule->setPrix($prix);
        $vehicule->setStatut($statut);
        $vehicule->setAssurance(false);
        $vehicule->setAssistance(false);
        $vehicule->setEntretien(false);
        $vehicule->setControleTechnique(false);
        $vehicule->setImmatriculation($immatriculation);
        $vehicule->setIsArchived($archive);
        $this->entityManager->persist($vehicule);
        $this->entityManager->flush();

        return $vehicule;
    }

    private function creerClient(): Client
    {
        if ($this->client) {
            return $this->client;
        }

        $contact = new Contact();
        $contact->setNom('Test');
        $contact->setPrenom('VehiculeRepo');
        $contact->setEmail(self::EMAIL_CLIENT);
        $this->entityManager->persist($contact);

        $client = new Client();
        $client->setContact($contact);
        $client->setPassword('test');
        $client->setRoles([]);
        $this->entityManager->persist($client);
        $this->entityManager->flush();

        return $this->client = $client;
    }

    private function creerDossier(Vehicule $vehicule, string $statut): Dossier
    {
        $dossier = new Dossier();
        $dossier->setType('location');
        $dossier->setStatut($statut);
        $dossier->setDateCreation(new \DateTimeImmutable());
        $dossier->setClient($this->creerClient());
        $dossier->setVehicule($vehicule);
        $this->entityManager->persist($dossier);
        $this->entityManager->flush();

        return $dossier;
    }

    /**
     * @param Vehicule[] $vehicules
     * @return string[]
     */
    private function modeles(array $vehicules): array
    {
        $noms = array_map(fn (Vehicule $v) => $v->getModele(), $vehicules);
        sort($noms);

        return $noms;
    }

    /**
     * @param Vehicule[] $vehicules
     * @return int[]
     */
    private function ids(array $vehicules): array
    {
        return array_map(fn (Vehicule $v) => $v->getId(), $vehicules);
    }

    private function nettoyer(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement("DELETE FROM dossier WHERE vehicule_id IN (SELECT id FROM vehicule WHERE marque LIKE 'ZzTest%')");
        $connection->executeStatement("DELETE FROM vehicule WHERE marque LIKE 'ZzTest%'");
        $connection->executeStatement('DELETE FROM client WHERE contact_id IN (SELECT id FROM contact WHERE email = ?)', [self::EMAIL_CLIENT]);
        $connection->executeStatement('DELETE FROM contact WHERE email = ?', [self::EMAIL_CLIENT]);
    }
}