<?php

namespace App\Tests\Service;

use App\Entity\Attribute;
use App\Entity\Position;
use App\Entity\PositionAccessRule;
use App\Entity\ProfileAttributeValue;
use App\Entity\User;
use App\Enum\AccessRuleOperator;
use App\Enum\AttributeType;
use App\Repository\PositionAccessRuleRepository;
use App\Repository\ProfileAttributeValueRepository;
use App\Service\PositionAccessEvaluator;
use PHPUnit\Framework\TestCase;

final class PositionAccessEvaluatorTest extends TestCase
{
    public function testNumericRuleAllowsMatchingCandidate(): void
    {
        $attribute = new Attribute();
        $position = new Position();
        $candidate = new User();
        $this->setId($attribute, 10);
        $this->setId($position, 20);
        $attribute->setType(AttributeType::Numeric);
        $position->setIsPublic(false);

        $profileValue = new ProfileAttributeValue($candidate, $attribute, '7.5');
        $rule = new PositionAccessRule($position, $attribute, AccessRuleOperator::GreaterThan, '7');
        $values = $this->createMock(ProfileAttributeValueRepository::class);
        $values->method('findForUser')->willReturn([$profileValue]);
        $rules = $this->createMock(PositionAccessRuleRepository::class);
        $rules->method('findForPosition')->willReturn([$rule]);
        $rules->method('findForPositions')->willReturn([$rule]);

        self::assertTrue((new PositionAccessEvaluator($rules, $values))->isAccessible($candidate, $position));
    }

    public function testMissingValueFailsRestrictedRule(): void
    {
        $attribute = new Attribute();
        $position = new Position();
        $candidate = new User();
        $this->setId($attribute, 11);
        $this->setId($position, 21);
        $position->setIsPublic(false);
        $rule = new PositionAccessRule($position, $attribute, AccessRuleOperator::Contains, 'Symfony');
        $values = $this->createMock(ProfileAttributeValueRepository::class);
        $values->method('findForUser')->willReturn([]);
        $rules = $this->createMock(PositionAccessRuleRepository::class);
        $rules->method('findForPosition')->willReturn([$rule]);

        self::assertFalse((new PositionAccessEvaluator($rules, $values))->isAccessible($candidate, $position));
    }

    private function setId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setValue($entity, $id);
    }
}