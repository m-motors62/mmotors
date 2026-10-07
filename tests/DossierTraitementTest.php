<?php

namespace App\Tests;

use App\Entity\Client;
use App\Entity\Contact;
use App\Entity\Document;
use App\Entity\Dossier;
use App\Entity\User;
use App\Entity\Vehicule;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DossierTraitementTest extends WebTestCase
{
    private const EMAIL_COMMERCIAL = 'zztest-trait-commercial@test.com';
    private const EMAIL_ADMIN = 'zztest-trait-admin@test.com';
    private const EMAIL_CLIENT = 'zztest-trait-client@test.com';
    private const IMMATRICULATION = 'ZT-910-AA';

    private KernelBrowser $browser;
    private int $commercialId;
    private int $adminId;
    private int $clientId;
    private int $vehiculeId;
    private int $dossierId;

    protected function setUp(): void
    {
        static::ensureKernelShutdown();
        $this->browser = static::createClient();
        $this->nettoyer();

        $em = $this->em();

        $commercial = $this->creerUser(self::EMAIL_COMMERCIAL, ['ROLE_COMMERCIAL']);
        $admin = $this->creerUser(self::EMAIL_ADMIN, ['ROLE_ADMIN']);

        $contactClient = new Contact();
        $contactClient->setNom('ZzTest');
        $contactClient->setPrenom('Traitement');
        $contactClient->setEmail(self::EMAIL_CLIENT);
        $em->persist($contactClient);

        $client = new Client();
        $client->setContact($contactClient);
        $client->setPassword('test');
        $client->setRoles([]);
        $em->persist($client);

        $vehicule = new Vehicule();
        $vehicule->setMarque('ZzTestTraitement');
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

        $this->commercialId = $commercial->getId();
        $this->adminId = $admin->getId();
        $this->clientId = $client->getId();
        $this->vehiculeId = $vehicule->getId();
    }

    protected function tearDown(): void
    {
        $this->nettoyer();
        parent::tearDown();
    }

    public static function casDeFinDeContrat(): array
    {
        return [
            'location validee : terminee' => ['valide', 'location', 'termine'],
            'location en cours : inchangee' => ['en_cours', 'location', 'en_cours'],
            'location refusee : inchangee' => ['refuse', 'location', 'refuse'],
            'achat valide : inchange' => ['valide', 'achat', 'valide'],
        ];
    }

    public function testValidationRefuseeTantQuUnDocumentActifNEstPasValide(): void
    {
        $this->creerDossier('en_cours', 'location');
        $this->creerDocument('carte_identite', 'valide');
        $this->creerDocument('fiche_paie', 'en_attente');
        $this->connecter($this->commercialId);

        $this->browser->request('POST', "/admin/dossiers/{$this->dossierId}/valider");

        $this->assertResponseRedirects("/admin/dossiers/{$this->dossierId}");
        $this->assertSame('en_cours', $this->dossier()->getStatut());
    }

    public function testValidationAccepteeQuandTousLesDocumentsActifsSontValides(): void
    {
        $this->creerDossier('en_cours', 'location');
        $this->creerDocument('carte_identite', 'valide');
        $this->creerDocument('fiche_paie', 'valide');
        $this->connecter($this->commercialId);

        $this->browser->request('POST', "/admin/dossiers/{$this->dossierId}/valider");

        $this->assertResponseRedirects("/admin/dossiers/{$this->dossierId}");
        $this->assertSame('valide', $this->dossier()->getStatut());
    }

    public function testUnDocumentRemplaceNeBloquePasLaValidation(): void
    {
        $this->creerDossier('en_cours', 'location');
        $this->creerDocument('fiche_paie', 'rejete', true);
        $this->creerDocument('fiche_paie', 'valide');
        $this->connecter($this->commercialId);

        $this->browser->request('POST', "/admin/dossiers/{$this->dossierId}/valider");

        $this->assertSame('valide', $this->dossier()->getStatut());
    }

    public function testRefusSansMotifEstRefuse(): void
    {
        $this->creerDossier('en_cours', 'location');
        $this->connecter($this->commercialId);

        $this->browser->request('POST', "/admin/dossiers/{$this->dossierId}/refuser");

        $this->assertResponseRedirects("/admin/dossiers/{$this->dossierId}");
        $this->assertSame('en_cours', $this->dossier()->getStatut());
    }

    public function testRefusAvecMotifEnregistreLeMotif(): void
    {
        $this->creerDossier('en_cours', 'location');
        $this->connecter($this->commercialId);

        $this->browser->request('POST', "/admin/dossiers/{$this->dossierId}/refuser", ['motif' => 'Revenus insuffisants']);

        $dossier = $this->dossier();
        $this->assertSame('refuse', $dossier->getStatut());
        $this->assertSame('Revenus insuffisants', $dossier->getMotifRefus());
    }

    public function testRejetDeDocumentSansMotifEstRefuse(): void
    {
        $this->creerDossier('en_cours', 'achat');
        $documentId = $this->creerDocument('carte_identite', 'en_attente');
        $this->connecter($this->commercialId);

        $this->browser->request('POST', "/admin/dossiers/document/{$documentId}/rejeter");

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame('en_attente', $this->document($documentId)->getStatut());
    }

    public function testRejetDeDocumentAvecMotif(): void
    {
        $this->creerDossier('en_cours', 'achat');
        $documentId = $this->creerDocument('carte_identite', 'en_attente');
        $this->connecter($this->commercialId);

        $this->browser->request('POST', "/admin/dossiers/document/{$documentId}/rejeter", ['motif' => 'Photo illisible']);

        $this->assertResponseIsSuccessful();
        $document = $this->document($documentId);
        $this->assertSame('rejete', $document->getStatut());
        $this->assertSame('Photo illisible', $document->getMotifRejet());
    }

    public function testUnDocumentRemplaceNePeutPlusEtreValide(): void
    {
        $this->creerDossier('en_cours', 'achat');
        $documentId = $this->creerDocument('carte_identite', 'rejete', true);
        $this->connecter($this->commercialId);

        $this->browser->request('POST', "/admin/dossiers/document/{$documentId}/valider");

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame('rejete', $this->document($documentId)->getStatut());
    }

    public function testUnDocumentRemplaceNePeutPlusEtreRejete(): void
    {
        $this->creerDossier('en_cours', 'achat');
        $documentId = $this->creerDocument('carte_identite', 'rejete', true);
        $this->connecter($this->commercialId);

        $this->browser->request('POST', "/admin/dossiers/document/{$documentId}/rejeter", ['motif' => 'Autre motif']);

        $this->assertResponseStatusCodeSame(422);
        $this->assertNotSame('Autre motif', $this->document($documentId)->getMotifRejet());
    }

    public function testAdministrateurNePeutPasValiderUnDossier(): void
    {
        $this->creerDossier('en_cours', 'achat');
        $this->creerDocument('carte_identite', 'valide');
        $this->connecter($this->adminId);

        $this->browser->request('POST', "/admin/dossiers/{$this->dossierId}/valider");

        $this->assertResponseRedirects('/admin/dashboard');
        $this->assertSame('en_cours', $this->dossier()->getStatut());
    }

    public function testAdministrateurNePeutPasRejeterUnDocument(): void
    {
        $this->creerDossier('en_cours', 'achat');
        $documentId = $this->creerDocument('carte_identite', 'en_attente');
        $this->connecter($this->adminId);

        $this->browser->request('POST', "/admin/dossiers/document/{$documentId}/rejeter", ['motif' => 'Refus admin']);

        $this->assertResponseRedirects('/admin/dashboard');
        $this->assertSame('en_attente', $this->document($documentId)->getStatut());
    }

    #[DataProvider('casDeFinDeContrat')]
    public function testFinDeContratNeConcerneQueUneLocationValidee(string $statut, string $type, string $attendu): void
    {
        $this->creerDossier($statut, $type);
        $this->connecter($this->commercialId);

        $this->browser->request('POST', "/admin/dossiers/{$this->dossierId}/terminer");

        $this->assertResponseRedirects("/admin/dossiers/{$this->dossierId}");
        $this->assertSame($attendu, $this->dossier()->getStatut());
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function connecter(int $userId): void
    {
        $this->browser->request('GET', '/admin/login?key=9184710');
        $this->browser->loginUser($this->em()->find(User::class, $userId), 'admin');
    }

    private function creerUser(string $email, array $roles): User
    {
        $contact = new Contact();
        $contact->setNom('ZzTest');
        $contact->setPrenom('Traitement');
        $contact->setEmail($email);
        $this->em()->persist($contact);

        $user = new User();
        $user->setContact($contact);
        $user->setEmail($email);
        $user->setRoles($roles);
        $user->setIsActive(true);
        $user->setPassword('test');
        $this->em()->persist($user);

        return $user;
    }

    private function creerDossier(string $statut, string $type): void
    {
        $em = $this->em();

        $dossier = new Dossier();
        $dossier->setType($type);
        $dossier->setStatut($statut);
        $dossier->setDateCreation(new \DateTimeImmutable());
        $dossier->setClient($em->find(Client::class, $this->clientId));
        $dossier->setVehicule($em->find(Vehicule::class, $this->vehiculeId));
        $em->persist($dossier);
        $em->flush();

        $this->dossierId = $dossier->getId();
    }

    private function creerDocument(string $type, string $statut, bool $remplace = false): int
    {
        $em = $this->em();

        $document = new Document();
        $document->setTypeDocument($type);
        $document->setNomFichier('zztest.pdf');
        $document->setDateDepot(new \DateTimeImmutable());
        $document->setStatut($statut);
        $document->setEstRemplace($remplace);
        $document->setDossier($em->find(Dossier::class, $this->dossierId));
        $em->persist($document);
        $em->flush();

        return $document->getId();
    }

    private function dossier(): Dossier
    {
        $em = $this->em();
        $em->clear();

        return $em->find(Dossier::class, $this->dossierId);
    }

    private function document(int $documentId): Document
    {
        $em = $this->em();
        $em->clear();

        return $em->find(Document::class, $documentId);
    }

    private function nettoyer(): void
    {
        $connection = $this->em()->getConnection();
        $connection->executeStatement('DELETE FROM action_log WHERE actor_id IN (SELECT id FROM user WHERE email IN (?, ?))', [self::EMAIL_COMMERCIAL, self::EMAIL_ADMIN]);
        $connection->executeStatement('DELETE FROM document WHERE dossier_id IN (SELECT id FROM dossier WHERE vehicule_id IN (SELECT id FROM vehicule WHERE immatriculation = ?))', [self::IMMATRICULATION]);
        $connection->executeStatement('DELETE FROM dossier WHERE vehicule_id IN (SELECT id FROM vehicule WHERE immatriculation = ?)', [self::IMMATRICULATION]);
        $connection->executeStatement('DELETE FROM vehicule WHERE immatriculation = ?', [self::IMMATRICULATION]);
        $connection->executeStatement('DELETE FROM client WHERE contact_id IN (SELECT id FROM contact WHERE email = ?)', [self::EMAIL_CLIENT]);
        $connection->executeStatement('DELETE FROM user WHERE email IN (?, ?)', [self::EMAIL_COMMERCIAL, self::EMAIL_ADMIN]);
        $connection->executeStatement('DELETE FROM contact WHERE email IN (?, ?, ?)', [self::EMAIL_COMMERCIAL, self::EMAIL_ADMIN, self::EMAIL_CLIENT]);
    }
}