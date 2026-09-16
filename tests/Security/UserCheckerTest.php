<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

final class UserCheckerTest extends TestCase
{
    public function testBlockedUserFailsPreAuthentication(): void
    {
        $user = new User();
        $user->setBlocked(true);

        $this->expectException(CustomUserMessageAuthenticationException::class);
        (new UserChecker())->checkPreAuth($user);
    }
}
