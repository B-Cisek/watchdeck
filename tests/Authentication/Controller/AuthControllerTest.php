<?php

declare(strict_types=1);

namespace App\Tests\Authentication\Controller;

use App\Authentication\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Uid\Uuid;

final class AuthControllerTest extends WebTestCase
{
    private const string PASSWORD = 'correct-horse';

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private string $email;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        // Keeps one connection, so the transaction below also covers the requests.
        $this->client->disableReboot();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->getConnection()->beginTransaction();
        // Login throttling outlives a test run, so no two tests may share an email or an IP.
        $this->email = Uuid::v7()->toRfc4122().'@example.com';
        $this->client->setServerParameter('REMOTE_ADDR', implode('.', [10, random_int(0, 255), random_int(0, 255), random_int(1, 254)]));
    }

    protected function tearDown(): void
    {
        $this->entityManager->getConnection()->rollBack();
        $this->entityManager->close();

        parent::tearDown();
    }

    public function testLoginReturnsUserWithRoles(): void
    {
        $user = $this->createUser();

        $this->login();

        self::assertResponseIsSuccessful();
        self::assertSame([
            'id' => $user->getId()->toRfc4122(),
            'email' => $this->email,
            'roles' => ['ROLE_USER'],
        ], $this->json());
    }

    public function testRolesAreAlwaysAJsonList(): void
    {
        $this->createUser(['ROLE_USER', 'ROLE_USER', 'ROLE_ADMIN']);

        $this->login();

        self::assertSame(['ROLE_USER', 'ROLE_ADMIN'], $this->json()['roles']);
    }

    public function testMeReturnsSamePayloadAsLogin(): void
    {
        $this->createUser();
        $this->login();
        $loginPayload = $this->json();

        $this->client->request('GET', '/api/me');

        self::assertResponseIsSuccessful();
        self::assertSame($loginPayload, $this->json());
    }

    public function testMeRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/me');

        self::assertResponseStatusCodeSame(401);
    }

    public function testInvalidPasswordReturns401WithError(): void
    {
        $this->createUser();

        $this->login(password: 'wrong');

        self::assertResponseStatusCodeSame(401);
        self::assertSame('Invalid credentials.', $this->json()['error']);
    }

    public function testRememberMeRestoresSession(): void
    {
        $this->createUser();
        $this->login(['_remember_me' => true]);
        self::assertResponseHasCookie('REMEMBERME');

        $this->expireSession();
        $this->client->request('GET', '/api/me');

        self::assertResponseIsSuccessful();
        self::assertSame($this->email, $this->json()['email']);
    }

    public function testRememberMeCookieIsHardened(): void
    {
        $this->createUser();

        $this->login(['_remember_me' => true]);

        $cookies = array_filter(
            $this->client->getResponse()->headers->getCookies(),
            static fn ($cookie) => $cookie->getName() === 'REMEMBERME',
        );
        $cookie = array_values($cookies)[0];
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame('lax', $cookie->getSameSite());
    }

    public function testSessionIsNotRestoredWhenRememberMeIsMissing(): void
    {
        $this->createUser();

        $this->login();

        self::assertResponseIsSuccessful();
        $this->expireSession();
        $this->client->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(401);
    }

    public function testSessionIsNotRestoredWhenRememberMeIsFalse(): void
    {
        $this->createUser();

        $this->login(['_remember_me' => false]);

        self::assertResponseIsSuccessful();
        $this->expireSession();
        $this->client->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(401);
    }

    public function testLogoutClearsSessionAndRememberMe(): void
    {
        $this->createUser();
        $this->login(['_remember_me' => true]);

        $this->client->request('POST', '/api/logout');
        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(401);
    }

    public function testPasswordChangeInvalidatesRememberMe(): void
    {
        $user = $this->createUser();
        $this->login(['_remember_me' => true]);

        $user = $this->entityManager->find(User::class, $user->getId());
        $user->changePassword($this->hash('another-password'));
        $this->entityManager->flush();
        $this->expireSession();
        $this->client->request('GET', '/api/me');

        self::assertResponseStatusCodeSame(401);
    }

    /** @param list<string> $roles */
    private function createUser(array $roles = []): User
    {
        $user = new User(Uuid::v7(), $this->email, $this->hash(self::PASSWORD), $roles);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function hash(string $password): string
    {
        return self::getContainer()->get(PasswordHasherFactoryInterface::class)->getPasswordHasher(User::class)->hash($password);
    }

    /** @param array<string, mixed> $extra */
    private function login(array $extra = [], string $password = self::PASSWORD): void
    {
        $this->client->jsonRequest('POST', '/api/login', ['email' => $this->email, 'password' => $password] + $extra);
    }

    private function expireSession(): void
    {
        self::assertNotNull($this->client->getCookieJar()->get('MOCKSESSID'));
        $this->client->getCookieJar()->expire('MOCKSESSID');
    }

    /** @return array<string, mixed> */
    private function json(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
