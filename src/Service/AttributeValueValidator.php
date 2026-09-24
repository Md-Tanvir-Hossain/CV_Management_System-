<?php

namespace App\Service;

use App\Entity\Attribute;

final class AttributeValueValidator
{
    public function validate(Attribute $attribute, ?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $tuning = $attribute->getTuning();
        if (in_array($attribute->getType()->value, ['string', 'text'], true) && isset($tuning['maxLength']) && mb_strlen($value) > (int) $tuning['maxLength']) {
            return sprintf('%s must be %d characters or fewer.', $attribute->getName(), $tuning['maxLength']);
        }
        if ($attribute->getType()->value === 'string' && isset($tuning['pattern']) && @preg_match((string) $tuning['pattern'], $value) !== 1) {
            return sprintf('%s does not match the required format.', $attribute->getName());
        }
        if ($attribute->getType()->value === 'numeric' && is_numeric($value)) {
            $number = (float) $value;
            if (isset($tuning['minValue']) && $number < (float) $tuning['minValue'] || isset($tuning['maxValue']) && $number > (float) $tuning['maxValue']) {
                return sprintf('%s must be between %s and %s.', $attribute->getName(), $tuning['minValue'] ?? '-infinity', $tuning['maxValue'] ?? 'infinity');
            }
        }
        if (in_array($attribute->getType()->value, ['date', 'period'], true)) {
            foreach ($this->periodDates($value) as $date) {
                if (isset($tuning['minDate']) && $date < $tuning['minDate'] || isset($tuning['maxDate']) && $date > $tuning['maxDate']) {
                    return sprintf('%s contains a date outside the allowed range.', $attribute->getName());
                }
            }
        }

        return null;
    }

    /** @return list<string> */
    private function periodDates(string $value): array
    {
        $parts = preg_split('/\s*(?:\/|\s+to\s+)\s*/i', $value) ?: [];
        return array_values(array_filter($parts, static fn (string $part): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $part)));
    }
}