<?php

namespace App\Tests;

use App\Entity\ActionLog;
use App\Entity\Contact;
use App\Entity\User;
use App\Repository\ActionLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ActionLogRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ActionLogRepository $repository;
    private User $admin;
    private User $gestionnaire;
    private User $commercial;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository = $this->entityManager->getRepository(ActionLog::class);
        $this->nettoyer();

        $this->admin = $this->creerUser('admin', ['ROLE_ADMIN']);
        $this->gestionnaire = $this->creerUser('gestionnaire', ['ROLE_GESTIONNAIRE']);
        $this->commercial = $this->creerUser('commercial', ['ROLE_COMMERCIAL']);

        $this->creerLog('ZZTEST connexion commercial', 'connexion', $this->commercial);
        $this->creerLog('ZZTEST connexion admin', 'connexion', $this->admin);
        $this->creerLog('ZZTEST creation vehicule', 'creation_vehicule', $this->commercial);
        $this->creerLog('ZZTEST connexion sans acteur', 'connexion', null);
    }

    protected function tearDown(): void
    {
        $this->nettoyer();
        parent::tearDown();
    }

    public function testAdminVoitTousLesLogs(): void
    {
        $this->assertSame(
            [
                'ZZTEST connexion admin',
                'ZZTEST connexion commercial',
                'ZZTEST connexion sans acteur',
                'ZZTEST creation vehicule',
            ],
            $this->descriptionsVisiblesPar($this->admin)
        );
    }

    public function testGestionnaireVoitLesActionsUtilisateursHorsAdmin(): void
    {
        $this->assertSame(['ZZTEST connexion commercial'], $this->descriptionsVisiblesPar($this->gestionnaire));
    }

    public function testGestionnaireNeVoitPasLesLogsSansActeur(): void
    {
        $this->assertNotContains('ZZTEST connexion sans acteur', $this->descriptionsVisiblesPar($this->gestionnaire));
    }

    public function testCommercialNeVoitPasLesActionsDeGestionDesComptes(): void
    {
        $this->assertSame(['ZZTEST creation vehicule'], $this->descriptionsVisiblesPar($this->commercial));
    }

    public function testUtilisateurSansRoleMetierNeVoitRien(): void
    {
        $this->assertSame([], $this->descriptionsVisiblesPar(new User()));
    }

    /**
     * @return string[]
     */
    private function descriptionsVisiblesPar(User $user): array
    {
        $descriptions = [];
        foreach ($this->repository->findVisibleFor($user) as $log) {
            if (str_starts_with($log->getDescription(), 'ZZTEST')) {
                $descriptions[] = $log->getDescription();
            }
        }
        sort($descriptions);

        return $descriptions;
    }

    private function creerUser(string $suffixe, array $roles): User
    {
        $contact = new Contact();
        $contact->setNom('ZzTest');
        $contact->setPrenom($suffixe);
        $contact->setEmail("zztest-$suffixe@test.com");
        $this->entityManager->persist($contact);

        $user = new User();
        $user->setContact($contact);
        $user->setEmail("zztest-$suffixe@test.com");
        $user->setRoles($roles);
        $user->setIsActive(true);
        $user->setPassword('test');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function creerLog(string $description, string $type, ?User $acteur): void
    {
        $log = new ActionLog();
        $log->setActionType($type);
        $log->setDescription($description);
        $log->setCreatedAt(new \DateTimeImmutable());
        $log->setActor($acteur);
        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    private function nettoyer(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement("DELETE FROM action_log WHERE description LIKE 'ZZTEST%' OR actor_id IN (SELECT id FROM user WHERE email LIKE 'zztest-%@test.com')");
        $connection->executeStatement("DELETE FROM user WHERE email LIKE 'zztest-%@test.com'");
        $connection->executeStatement("DELETE FROM contact WHERE email LIKE 'zztest-%@test.com'");
    }

    public function testDemandeDeReinitialisationInvisiblePourLeCommercial(): void
    {
        $this->creerLog('ZZTEST demande reinitialisation', 'demande_reinitialisation_mdp', null);

        $this->assertNotContains('ZZTEST demande reinitialisation', $this->descriptionsVisiblesPar($this->commercial));
        $this->assertContains('ZZTEST demande reinitialisation', $this->descriptionsVisiblesPar($this->admin));
    }
}