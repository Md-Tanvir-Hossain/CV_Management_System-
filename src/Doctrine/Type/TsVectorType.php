<?php

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class TsVectorType extends Type
{
    public const NAME = 'tsvector';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'TSVECTOR';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value === null ? null : (string) $value;
    }

    public function getName(): string
    {
        return self::NAME;
    }
}