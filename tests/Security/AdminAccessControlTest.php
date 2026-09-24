<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminAccessControlTest extends WebTestCase
{
    #[DataProvider('protectedAdminRoutesProvider')]
    public function testUnauthenticatedAccessToProtectedAdminRoutesIsRedirectedToLogin(string $method, string $path): void
    {
        $client = static::createClient();
        $client->request($method, $path);

        self::assertResponseRedirects('/login');
    }

    #[DataProvider('protectedAdminRoutesProvider')]
    public function testCandidateAccessToProtectedAdminRoutesIsForbidden(string $method, string $path): void
    {
        $client = static::createClient();
        $user = $this->getOrCreateUser($client, 'candidate-sec-test@example.com', ['ROLE_CANDIDATE']);

        $client->loginUser($user);
        $client->request($method, $path);

        self::assertResponseStatusCodeSame(403);
    }

    #[DataProvider('protectedAdminRoutesProvider')]
    public function testRecruiterAccessToProtectedAdminRoutesIsForbidden(string $method, string $path): void
    {
        $client = static::createClient();
        $user = $this->getOrCreateUser($client, 'recruiter-sec-test@example.com', ['ROLE_RECRUITER']);

        $client->loginUser($user);
        $client->request($method, $path);

        self::assertResponseStatusCodeSame(403);
    }

    public function testUnauthenticatedCannotAccessNewAdminUserForm(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/users/new');

        self::assertResponseRedirects('/login');
    }

    public function testCandidateCannotAccessNewAdminUserForm(): void
    {
        $client = static::createClient();
        $candidate = $this->getOrCreateUser($client, 'candidate-sec-test@example.com', ['ROLE_CANDIDATE']);
        $client->loginUser($candidate);

        $client->request('GET', '/admin/users/new');

        self::assertResponseStatusCodeSame(403);
    }

    public function testRecruiterCannotAccessNewAdminUserForm(): void
    {
        $client = static::createClient();
        $recruiter = $this->getOrCreateUser($client, 'recruiter-sec-test@example.com', ['ROLE_RECRUITER']);
        $client->loginUser($recruiter);

        $client->request('GET', '/admin/users/new');

        self::assertResponseStatusCodeSame(403);
    }

    public function testUnauthenticatedCannotSubmitNewAdminUserForm(): void
    {
        $client = static::createClient();
        $client->request('POST', '/admin/users/new', [
            'admin_user' => ['email' => 'new-unrestricted-admin@example.com', 'password' => 'SecureAdminPass123!'],
        ]);
        self::assertResponseRedirects('/login');
    }

    public function testAdminCanAccessNewAdminUserForm(): void
    {
        $client = static::createClient();
        $admin = $this->getOrCreateUser($client, 'admin-sec-test@example.com', ['ROLE_ADMIN']);
        $client->loginUser($admin);

        $client->request('GET', '/admin/users/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[name="admin_user"]');
    }

    public function testAdminAccessToUsersIndexIsSuccessful(): void
    {
        $client = static::createClient();
        $admin = $this->getOrCreateUser($client, 'admin-sec-test@example.com', ['ROLE_ADMIN']);

        $client->loginUser($admin);
        $client->request('GET', '/admin/users');

        self::assertResponseIsSuccessful();
    }

    public static function protectedAdminRoutesProvider(): array
    {
        return [
            'admin_user_index' => ['GET', '/admin/users'],
            'admin_user_toggle_blocked' => ['POST', '/admin/users/1/toggle-blocked'],
            'admin_user_roles' => ['POST', '/admin/users/1/roles'],
            'admin_user_delete' => ['POST', '/admin/users/1/delete'],
        ];
    }

    private function getOrCreateUser(KernelBrowser $client, string $email, array $roles): User
    {
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine')->getManager();
        /** @var UserRepository $repo */
        $repo = $em->getRepository(User::class);

        $user = $repo->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            $user = new User();
            $user->setEmail($email);
            $user->setPassword('password_hash_test');
            $user->setRoles($roles);
            $em->persist($user);
            $em->flush();
        } else {
            $user->setRoles($roles);
            $em->flush();
        }

        return $user;
    }

    public static function tearDownAfterClass(): void
    {
        $kernel = self::bootKernel();
        $em = $kernel->getContainer()->get('doctrine')->getManager();
        $repo = $em->getRepository(User::class);

        $testEmails = [
            'candidate-sec-test@example.com',
            'recruiter-sec-test@example.com',
            'admin-sec-test@example.com',
            'new-unrestricted-admin@example.com',
        ];

        foreach ($testEmails as $email) {
            $user = $repo->findOneBy(['email' => $email]);
            if ($user instanceof User) {
                $em->remove($user);
            }
        }
        $em->flush();
        parent::tearDownAfterClass();
    }
}
