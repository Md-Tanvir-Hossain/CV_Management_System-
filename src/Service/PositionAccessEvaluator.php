<?php

namespace App\Service;

use App\Entity\Position;
use App\Entity\ProfileAttributeValue;
use App\Entity\User;
use App\Enum\AccessRuleOperator;
use App\Enum\AttributeType;
use App\Repository\PositionAccessRuleRepository;
use App\Repository\ProfileAttributeValueRepository;

final class PositionAccessEvaluator
{
    public function __construct(
        private readonly PositionAccessRuleRepository $ruleRepository,
        private readonly ProfileAttributeValueRepository $valueRepository,
    ) {
    }

    public function isAccessible(User $candidate, Position $position): bool
    {
        if ($position->isPublic()) {
            return true;
        }

        $rules = $this->ruleRepository->findForPosition($position);

        return $rules !== [] && $this->matchesRules($this->valueMap($candidate), $rules);
    }

    /** @param list<Position> $positions @return list<Position> */
    public function filterAccessible(User $candidate, array $positions): array
    {
        $values = $this->valueMap($candidate);
        $rulesByPosition = [];
        foreach ($this->ruleRepository->findForPositions($positions) as $rule) {
            $rulesByPosition[$rule->getPosition()->getId()][] = $rule;
        }

        return array_values(array_filter($positions, function (Position $position) use ($values, $rulesByPosition): bool {
            $rules = $rulesByPosition[$position->getId()] ?? [];

            return $position->isPublic() || ($rules !== [] && $this->matchesRules($values, $rules));
        }));
    }

    /** @return list<AccessRuleOperator> */
    public function allowedOperators(AttributeType $type): array
    {
        return match ($type) {
            AttributeType::Numeric, AttributeType::Date => [AccessRuleOperator::Equals, AccessRuleOperator::GreaterThan, AccessRuleOperator::LessThan],
            AttributeType::Boolean => [AccessRuleOperator::IsChecked],
            AttributeType::Dropdown => [AccessRuleOperator::Equals],
            AttributeType::String, AttributeType::Text => [AccessRuleOperator::Equals, AccessRuleOperator::Contains],
            AttributeType::Image, AttributeType::Period => [AccessRuleOperator::Equals],
        };
    }

    private function matches(?string $actual, AttributeType $type, AccessRuleOperator $operator, ?string $expected): bool
    {
        if ($actual === null || trim($actual) === '' || !in_array($operator, $this->allowedOperators($type), true)) {
            return false;
        }
        if ($operator === AccessRuleOperator::IsChecked) {
            return strtolower($actual) === 'true';
        }
        if ($expected === null || $expected === '') {
            return false;
        }
        if ($type === AttributeType::Numeric) {
            $actualNumber = filter_var($actual, FILTER_VALIDATE_FLOAT);
            $expectedNumber = filter_var($expected, FILTER_VALIDATE_FLOAT);
            if ($actualNumber === false || $expectedNumber === false) {
                return false;
            }
            return match ($operator) {
                AccessRuleOperator::Equals => $actualNumber == $expectedNumber,
                AccessRuleOperator::GreaterThan => $actualNumber > $expectedNumber,
                AccessRuleOperator::LessThan => $actualNumber < $expectedNumber,
                default => false,
            };
        }
        if ($type === AttributeType::Date) {
            $actualDate = strtotime($actual);
            $expectedDate = strtotime($expected);
            if ($actualDate === false || $expectedDate === false) {
                return false;
            }
            return match ($operator) {
                AccessRuleOperator::Equals => $actualDate === $expectedDate,
                AccessRuleOperator::GreaterThan => $actualDate > $expectedDate,
                AccessRuleOperator::LessThan => $actualDate < $expectedDate,
                default => false,
            };
        }

        return match ($operator) {
            AccessRuleOperator::Equals => strcasecmp($actual, $expected) === 0,
            AccessRuleOperator::Contains => str_contains(strtolower($actual), strtolower($expected)),
            default => false,
        };
    }

    /** @return array<int|string, ProfileAttributeValue> */
    private function valueMap(User $candidate): array
    {
        $values = [];
        foreach ($this->valueRepository->findForUser($candidate) as $profileValue) {
            $values[$profileValue->getAttribute()->getId()] = $profileValue;
        }

        return $values;
    }

    /** @param list<\App\Entity\PositionAccessRule> $rules */
    private function matchesRules(array $values, array $rules): bool
    {
        foreach ($rules as $rule) {
            $profileValue = $values[$rule->getAttribute()->getId()] ?? null;
            if (!$profileValue instanceof ProfileAttributeValue || !$this->matches($profileValue->getValue(), $rule->getAttribute()->getType(), $rule->getOperator(), $rule->getComparisonValue())) {
                return false;
            }
        }

        return true;
    }
}