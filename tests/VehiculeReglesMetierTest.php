<?php

namespace App\Tests;

use App\Entity\Client;
use App\Entity\Contact;
use App\Entity\Dossier;
use App\Entity\User;
use App\Entity\Vehicule;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class VehiculeReglesMetierTest extends WebTestCase
{
    private const EMAIL_STAFF = 'zztest-regles-staff@test.com';
    private const EMAIL_CLIENT = 'zztest-regles-client@test.com';
    private const IMMATRICULATION = 'ZT-900-AA';
    private const XHR = ['HTTP_X-Requested-With' => 'XMLHttpRequest'];

    private KernelBrowser $browser;
    private int $staffId;
    private int $clientId;
    private int $vehiculeId;

    protected function setUp(): void
    {
        static::ensureKernelShutdown();
        $this->browser = static::createClient();
        $this->nettoyer();

        $em = $this->em();

        $staff = new User();
        $staff->setContact($this->creerContact(self::EMAIL_STAFF));
        $staff->setEmail(self::EMAIL_STAFF);
        $staff->setRoles(['ROLE_COMMERCIAL']);
        $staff->setIsActive(true);
        $staff->setPassword('test');
        $em->persist($staff);

        $client = new Client();
        $client->setContact($this->creerContact(self::EMAIL_CLIENT));
        $client->setPassword('test');
        $client->setRoles([]);
        $em->persist($client);

        $vehicule = new Vehicule();
        $vehicule->setMarque('ZzTestRegles');
        $vehicule->setModele('Clio');
        $vehicule->setMotorisation('Essence');
        $vehicule->setKilometrage(1000);
        $vehicule->setPrix(200);
        $vehicule->setStatut('location');
        $vehicule->setAssurance(false);
        $vehicule->setAssistance(false);
        $vehicule->setEntretien(false);
        $vehicule->setControleTechnique(false);
        $vehicule->setImmatriculation(self::IMMATRICULATION);
        $em->persist($vehicule);

        $em->flush();

        $this->staffId = $staff->getId();
        $this->clientId = $client->getId();
        $this->vehiculeId = $vehicule->getId();
    }

    protected function tearDown(): void
    {
        $this->nettoyer();
        parent::tearDown();
    }

    public static function statutsBloquants(): array
    {
        return [
            'dossier en cours' => ['en_cours'],
            'dossier valide' => ['valide'],
        ];
    }

    public static function statutsNonBloquants(): array
    {
        return [
            'dossier refuse' => ['refuse'],
            'dossier termine' => ['termine'],
        ];
    }

    public static function tousLesStatuts(): array
    {
        return [
            'dossier en cours' => ['en_cours'],
            'dossier valide' => ['valide'],
            'dossier refuse' => ['refuse'],
            'dossier termine' => ['termine'],
        ];
    }

    #[DataProvider('statutsBloquants')]
    public function testBasculeBloqueeParUnDossierActif(string $statut): void
    {
        $this->creerDossier($statut);
        $this->connecter();

        $this->browser->request('GET', "/admin/vehicules/{$this->vehiculeId}/basculer", [], [], self::XHR);

        $this->assertResponseStatusCodeSame(422);
        $this->assertFalse($this->reponseJson()['success']);
        $this->assertSame('location', $this->vehicule()->getStatut());
    }

    #[DataProvider('statutsNonBloquants')]
    public function testBasculeAutoriseeAvecUnDossierRefuseOuTermine(string $statut): void
    {
        $this->creerDossier($statut);
        $this->connecter();

        $this->browser->request('GET', "/admin/vehicules/{$this->vehiculeId}/basculer", [], [], self::XHR);

        $this->assertResponseIsSuccessful();
    }

    #[DataProvider('statutsBloquants')]
    public function testArchivageBloqueParUnDossierActif(string $statut): void
    {
        $this->creerDossier($statut);
        $this->connecter();

        $this->browser->request('POST', "/admin/vehicules/{$this->vehiculeId}/archiver");

        $this->assertResponseStatusCodeSame(422);
        $this->assertFalse($this->vehicule()->isArchived());
    }

    public function testArchivagePossibleSansDossier(): void
    {
        $this->connecter();

        $this->browser->request('POST', "/admin/vehicules/{$this->vehiculeId}/archiver");

        $this->assertResponseIsSuccessful();
        $this->assertTrue($this->vehicule()->isArchived());
    }

    #[DataProvider('tousLesStatuts')]
    public function testSuppressionBloqueeParNImporteQuelDossier(string $statut): void
    {
        $this->creerDossier($statut);
        $this->connecter();

        $this->browser->request('POST', "/admin/vehicules/{$this->vehiculeId}/supprimer");

        $this->assertResponseStatusCodeSame(422);
        $this->assertNotNull($this->vehicule());
    }

    public function testSuppressionPossibleSansDossier(): void
    {
        $this->connecter();

        $this->browser->request('POST', "/admin/vehicules/{$this->vehiculeId}/supprimer");

        $this->assertResponseIsSuccessful();
        $this->assertNull($this->vehicule());
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function connecter(): void
    {
        $this->browser->request('GET', '/admin/login?key=9184710');
        $this->browser->loginUser($this->em()->find(User::class, $this->staffId), 'admin');
    }

    private function creerContact(string $email): Contact
    {
        $contact = new Contact();
        $contact->setNom('ZzTest');
        $contact->setPrenom('Regles');
        $contact->setEmail($email);
        $this->em()->persist($contact);

        return $contact;
    }

    private function creerDossier(string $statut): void
    {
        $em = $this->em();

        $dossier = new Dossier();
        $dossier->setType('location');
        $dossier->setStatut($statut);
        $dossier->setDateCreation(new \DateTimeImmutable());
        $dossier->setClient($em->find(Client::class, $this->clientId));
        $dossier->setVehicule($em->find(Vehicule::class, $this->vehiculeId));
        $em->persist($dossier);
        $em->flush();
    }

    private function vehicule(): ?Vehicule
    {
        $em = $this->em();
        $em->clear();

        return $em->find(Vehicule::class, $this->vehiculeId);
    }

    private function reponseJson(): array
    {
        return json_decode($this->browser->getResponse()->getContent(), true);
    }

    private function nettoyer(): void
    {
        $connection = $this->em()->getConnection();
        $connection->executeStatement('DELETE FROM action_log WHERE actor_id IN (SELECT id FROM user WHERE email = ?)', [self::EMAIL_STAFF]);
        $connection->executeStatement('DELETE FROM dossier WHERE vehicule_id IN (SELECT id FROM vehicule WHERE immatriculation = ?)', [self::IMMATRICULATION]);
        $connection->executeStatement('DELETE FROM vehicule WHERE immatriculation = ?', [self::IMMATRICULATION]);
        $connection->executeStatement('DELETE FROM client WHERE contact_id IN (SELECT id FROM contact WHERE email = ?)', [self::EMAIL_CLIENT]);
        $connection->executeStatement('DELETE FROM user WHERE email = ?', [self::EMAIL_STAFF]);
        $connection->executeStatement('DELETE FROM contact WHERE email IN (?, ?)', [self::EMAIL_STAFF, self::EMAIL_CLIENT]);
    }
}