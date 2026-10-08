<?php

namespace App\Tests;

use App\Entity\Contact;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminMotDePasseOublieTest extends WebTestCase
{
    private const EMAIL_ACTIF = 'zztest-oublie-actif@test.com';
    private const EMAIL_INACTIF = 'zztest-oublie-inactif@test.com';

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        static::ensureKernelShutdown();
        $this->browser = static::createClient();
        $this->nettoyer();

        $this->creerUser(self::EMAIL_ACTIF, true);
        $this->creerUser(self::EMAIL_INACTIF, false);
        $this->em()->flush();
    }

    protected function tearDown(): void
    {
        $this->nettoyer();
        parent::tearDown();
    }

    public function testCompteActifRecoitUnJetonValable2Heures(): void
    {
        $this->demander(self::EMAIL_ACTIF);

        $this->assertResponseRedirects('/admin/login');
        $user = $this->user(self::EMAIL_ACTIF);
        $this->assertNotNull($user->getResetToken());
        $this->assertEqualsWithDelta(time() + 7200, $user->getResetTokenExpiresAt()->getTimestamp(), 60);
    }

    public function testCompteDesactiveNeRecoitAucunJeton(): void
    {
        $this->demander(self::EMAIL_INACTIF);

        $this->assertResponseRedirects('/admin/login');
        $this->assertNull($this->user(self::EMAIL_INACTIF)->getResetToken());
    }

    public function testAdresseInconnueObtientLaMemeReponse(): void
    {
        $this->demander('zztest-oublie-inconnu@test.com');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testPageInaccessibleSansLaCleDAcces(): void
    {
        $this->browser->request('GET', '/admin/mot-de-passe-oublie');

        $this->assertResponseStatusCodeSame(404);
    }

    private function demander(string $email): void
    {
        $this->browser->request('GET', '/admin/login?key=9184710');
        $crawler = $this->browser->request('GET', '/admin/mot-de-passe-oublie');
        $form = $crawler->selectButton('Envoyer')->form();
        $form['forgot_password[email]'] = $email;
        $this->browser->submit($form);
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function user(string $email): User
    {
        $this->em()->clear();

        return $this->em()->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    private function creerUser(string $email, bool $actif): void
    {
        $contact = new Contact();
        $contact->setNom('ZzTest');
        $contact->setPrenom('Oubli');
        $contact->setEmail($email);
        $this->em()->persist($contact);

        $user = new User();
        $user->setContact($contact);
        $user->setEmail($email);
        $user->setRoles(['ROLE_COMMERCIAL']);
        $user->setIsActive($actif);
        $user->setPassword('test');
        $this->em()->persist($user);
    }

    private function nettoyer(): void
    {
        $connection = $this->em()->getConnection();
        $connection->executeStatement("DELETE FROM action_log WHERE action_type = 'demande_reinitialisation_mdp' AND target_id IN (SELECT id FROM user WHERE email LIKE 'zztest-oublie-%@test.com')");
        $connection->executeStatement("DELETE FROM user WHERE email LIKE 'zztest-oublie-%@test.com'");
        $connection->executeStatement("DELETE FROM contact WHERE email LIKE 'zztest-oublie-%@test.com'");
    }
}