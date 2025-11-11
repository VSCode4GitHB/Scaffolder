<?php

declare(strict_types=1);

namespace App\Infrastructure\Hydration;

use App\Domain\Entity\Sample;
use App\Domain\Entity\SampleInterface;

final class SampleHydrator
{
    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): SampleInterface
    {
        return new Sample((int)($row['id'] ?? 0), (string)($row['name'] ?? ''));
    }

    /**
     * @return array<string, int|string>
     */
    public static function toRow(SampleInterface $e): array
    {
        return $e->toArray();
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, SampleInterface>
     */
    public static function fromRows(array $rows): array
    {
        return array_map([self::class, 'fromRow'], $rows);
    }
}
