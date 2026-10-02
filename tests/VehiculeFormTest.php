<?php

namespace App\Tests;

use App\Entity\Contact;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class VehiculeFormTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $entityManager;
    private User $commercial;

    protected function setUp(): void
    {
        static::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        // Nettoyage preventif au cas ou un test precedent aurait echoue avant son tearDown
        $ancienUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'test-form-vehicule@test.com']);
        if ($ancienUser) {
            $this->entityManager->getConnection()->executeStatement(
                'DELETE FROM action_log WHERE actor_id = ?',
                [$ancienUser->getId()]
            );

            $ancienContact = $ancienUser->getContact();
            $this->entityManager->remove($ancienUser);
            $this->entityManager->remove($ancienContact);
            $this->entityManager->flush();
        }

        $contact = new Contact();

        $contact = new Contact();
        $contact->setNom('TestForm');
        $contact->setPrenom('Phpunit');
        $contact->setEmail('test-form-vehicule@test.com');
        $this->entityManager->persist($contact);

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new User();
        $user->setContact($contact);
        $user->setEmail('test-form-vehicule@test.com');
        $user->setRoles(['ROLE_COMMERCIAL']);
        $user->setIsActive(true);
        $user->setPassword($hasher->hashPassword($user, 'TestPassword123!'));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->commercial = $user;
    }

    protected function tearDown(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = $entityManager->getRepository(User::class)->find($this->commercial->getId());

        if ($user) {
            $entityManager->getConnection()->executeStatement(
                'DELETE FROM action_log WHERE actor_id = ?',
                [$user->getId()]
            );

            $contact = $user->getContact();
            $entityManager->remove($user);
            $entityManager->remove($contact);
            $entityManager->flush();
        }

        parent::tearDown();
    }

    public function testImmatriculationFormatInvalideRejetee(): void
    {
        $this->client->request('GET', '/admin/login?key=9184710');
        $this->client->loginUser($this->commercial, 'admin');

        $crawler = $this->client->request('GET', '/admin/vehicules/nouveau-location');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Ajouter le véhicule')->form();
        $form['vehicule[marque]'] = 'Renault';
        $form['vehicule[modele]'] = 'Clio';
        $form['vehicule[motorisation]'] = 'Essence';
        $form['vehicule[kilometrage]'] = '15000';
        $form['vehicule[prix]'] = '250';
        $form['vehicule[immatriculation]'] = 'PASBONDUTOUT';

        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorExists('.invalid-feedback');
    }

    public function testFormulaireValideCreeLeVehicule(): void
    {
        $this->client->request('GET', '/admin/login?key=9184710');
        $this->client->loginUser($this->commercial, 'admin');

        $crawler = $this->client->request('GET', '/admin/vehicules/nouveau-location');

        $form = $crawler->selectButton('Ajouter le véhicule')->form();
        $form['vehicule[marque]'] = 'TestFormMarque';
        $form['vehicule[modele]'] = 'TestFormModele';
        $form['vehicule[motorisation]'] = 'Essence';
        $form['vehicule[kilometrage]'] = '10000';
        $form['vehicule[prix]'] = '300';
        $form['vehicule[immatriculation]'] = 'ZZ-999-ZZ';

        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/vehicules');

        $vehiculeRepository = $this->entityManager->getRepository(\App\Entity\Vehicule::class);
        $vehicule = $vehiculeRepository->findOneBy(['immatriculation' => 'ZZ-999-ZZ']);

        $this->assertNotNull($vehicule);

        $this->entityManager->remove($vehicule);
        $this->entityManager->flush();
    }
}