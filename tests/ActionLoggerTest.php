<?php

namespace App\Tests;

use App\Entity\ActionLog;
use App\Entity\Client;
use App\Entity\Contact;
use App\Entity\User;
use App\Service\ActionLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class ActionLoggerTest extends KernelTestCase
{
    private const EMAIL_USER = 'zztest-logger@test.com';

    private EntityManagerInterface $entityManager;
    private ActionLogger $actionLogger;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->actionLogger = $container->get(ActionLogger::class);
        $this->nettoyer();
    }

    protected function tearDown(): void
    {
        $this->nettoyer();
        parent::tearDown();
    }

    public function testLogSansUtilisateurConnecteNAPasDActeur(): void
    {
        $this->actionLogger->log('connexion', 'ZZTEST sans acteur', 'Vehicule', 12);

        $log = $this->retrouver('ZZTEST sans acteur');

        $this->assertNull($log->getActor());
        $this->assertSame('connexion', $log->getActionType());
        $this->assertSame('Vehicule', $log->getTargetType());
        $this->assertSame(12, $log->getTargetId());
        $this->assertNull($log->getTargetType2());
        $this->assertNull($log->getTargetId2());
        $this->assertEqualsWithDelta(time(), $log->getCreatedAt()->getTimestamp(), 5);
    }

    public function testLogAvecUnUtilisateurDuBackOfficeRenseigneLActeur(): void
    {
        $user = $this->creerUser();
        $this->connecter($user);

        $this->actionLogger->log('modification_vehicule', 'ZZTEST avec acteur', 'Vehicule', 3);

        $log = $this->retrouver('ZZTEST avec acteur');

        $this->assertNotNull($log->getActor());
        $this->assertSame($user->getId(), $log->getActor()->getId());
    }

    public function testUnClientConnecteNEstPasEnregistreCommeActeur(): void
    {
        $contact = new Contact();
        $contact->setNom('ZzTest');
        $contact->setPrenom('Client');
        $contact->setEmail('zztest-client-logger@test.com');

        $client = new Client();
        $client->setContact($contact);
        $client->setPassword('test');
        $client->setRoles([]);

        $this->connecter($client);

        $this->actionLogger->log('creation_dossier', 'ZZTEST client connecte', 'Dossier', 5);

        $this->assertNull($this->retrouver('ZZTEST client connecte')->getActor());
    }

    public function testSecondeCibleOptionnelleEstEnregistree(): void
    {
        $this->actionLogger->log('depot_dossier_vehicule', 'ZZTEST double cible', 'Vehicule', 7, 'Dossier', 9);

        $log = $this->retrouver('ZZTEST double cible');

        $this->assertSame('Vehicule', $log->getTargetType());
        $this->assertSame(7, $log->getTargetId());
        $this->assertSame('Dossier', $log->getTargetType2());
        $this->assertSame(9, $log->getTargetId2());
    }

    private function connecter(object $user): void
    {
        static::getContainer()->get('security.token_storage')
            ->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
    }

    private function creerUser(): User
    {
        $contact = new Contact();
        $contact->setNom('ZzTest');
        $contact->setPrenom('Logger');
        $contact->setEmail(self::EMAIL_USER);
        $this->entityManager->persist($contact);

        $user = new User();
        $user->setContact($contact);
        $user->setEmail(self::EMAIL_USER);
        $user->setRoles(['ROLE_COMMERCIAL']);
        $user->setIsActive(true);
        $user->setPassword('test');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function retrouver(string $description): ActionLog
    {
        $this->entityManager->clear();

        $log = $this->entityManager->getRepository(ActionLog::class)->findOneBy(['description' => $description]);
        $this->assertNotNull($log, 'Le log attendu est introuvable en base.');

        return $log;
    }

    private function nettoyer(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement("DELETE FROM action_log WHERE description LIKE 'ZZTEST%'");
        $connection->executeStatement('DELETE FROM user WHERE email = ?', [self::EMAIL_USER]);
        $connection->executeStatement('DELETE FROM contact WHERE email = ?', [self::EMAIL_USER]);
    }
}