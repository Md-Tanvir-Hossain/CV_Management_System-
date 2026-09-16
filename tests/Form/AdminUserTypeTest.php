<?php

namespace App\Tests\Form;

use App\Entity\User;
use App\Form\AdminUserType;
use Symfony\Component\Form\Test\TypeTestCase;

final class AdminUserTypeTest extends TypeTestCase
{
    public function testAdminAccountFormContainsOnlyEmailAndPassword(): void
    {
        $form = $this->factory->create(AdminUserType::class, new User());

        self::assertSame(['email', 'password'], array_keys(iterator_to_array($form)));
    }
}
