<?php

namespace App\Tests;

use App\Entity\Client;
use App\Entity\Contact;
use App\Entity\Dossier;
use App\Entity\Vehicule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class DossierDepotTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $entityManager;
    private Client $clientTest;
    private Vehicule $vehiculeTest;

    protected function setUp(): void
    {
        static::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $this->nettoyerDonneesTest();

        $contact = new Contact();
        $contact->setNom('DepotTest');
        $contact->setPrenom('Phpunit');
        $contact->setEmail('test-depot-dossier@test.com');
        $this->entityManager->persist($contact);

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $client = new Client();
        $client->setContact($contact);
        $client->setPassword($hasher->hashPassword($client, 'TestPassword123!'));
        $client->setIsVerified(true);
        $this->entityManager->persist($client);

        $vehicule = new Vehicule();
        $vehicule->setMarque('TestDepot');
        $vehicule->setModele('TestDepot');
        $vehicule->setKilometrage(5000);
        $vehicule->setPrix(200);
        $vehicule->setStatut('achat');
        $vehicule->setAssurance(false);
        $vehicule->setAssistance(false);
        $vehicule->setEntretien(false);
        $vehicule->setControleTechnique(false);
        $vehicule->setImmatriculation('ZZ-888-ZZ');
        $this->entityManager->persist($vehicule);

        $this->entityManager->flush();

        $this->clientTest = $client;
        $this->vehiculeTest = $vehicule;
    }

    protected function tearDown(): void
    {
        $this->nettoyerDonneesTest();
        parent::tearDown();
    }

    private function nettoyerDonneesTest(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();

        $connection->executeStatement(
            "DELETE d FROM document d INNER JOIN dossier doss ON d.dossier_id = doss.id INNER JOIN client c ON doss.client_id = c.id INNER JOIN contact ct ON c.contact_id = ct.id WHERE ct.email = ?",
            ['test-depot-dossier@test.com']
        );
        $connection->executeStatement(
            "DELETE doss FROM dossier doss INNER JOIN client c ON doss.client_id = c.id INNER JOIN contact ct ON c.contact_id = ct.id WHERE ct.email = ?",
            ['test-depot-dossier@test.com']
        );
        $connection->executeStatement("DELETE FROM client WHERE contact_id IN (SELECT id FROM contact WHERE email = ?)", ['test-depot-dossier@test.com']);
        $connection->executeStatement("DELETE FROM contact WHERE email = ?", ['test-depot-dossier@test.com']);
        $connection->executeStatement("DELETE FROM vehicule WHERE immatriculation = ?", ['ZZ-888-ZZ']);
    }

    public function testDepotDossierCompletPourUnAchat(): void
    {
        $this->client->loginUser($this->clientTest, 'main');

        $slug = $this->vehiculeTest->getSlug();
        $crawler = $this->client->request('GET', '/dossier/nouveau/' . $this->vehiculeTest->getId());
        $this->assertResponseIsSuccessful();

        $fauxFichier = tempnam(sys_get_temp_dir(), 'test').'.pdf';
        file_put_contents($fauxFichier, '%PDF-1.4 fichier de test');

        $form = $crawler->selectButton('Déposer mon dossier')->form();
        $form['dossier[carteIdentite]']->upload($fauxFichier);
        $form['dossier[justificatifDomicile]']->upload($fauxFichier);

        $this->client->submit($form);

        $this->assertResponseRedirects('/espace-client');

        $dossierRepository = $this->entityManager->getRepository(Dossier::class);
        $dossier = $dossierRepository->findOneBy(['vehicule' => $this->vehiculeTest]);

        $this->assertNotNull($dossier);
        $this->assertSame('achat', $dossier->getType());
        $this->assertSame('en_cours', $dossier->getStatut());
        $this->assertCount(2, $dossier->getDocuments());

        unlink($fauxFichier);
    }
}