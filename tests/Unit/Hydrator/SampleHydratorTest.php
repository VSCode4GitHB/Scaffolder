<?php
declare(strict_types=1);

namespace Tests\Unit\Hydrator;

use App\Domain\Entity\Sample;
use App\Infrastructure\Hydration\SampleHydrator;
use PHPUnit\Framework\TestCase;

final class SampleHydratorTest extends TestCase
{
    public function testFromRowAndToRow(): void
    {
        $row = ['id' => '10', 'name' => 'Bob'];
        $entity = SampleHydrator::fromRow($row);

        $this->assertInstanceOf(Sample::class, $entity);
        $this->assertSame(10, $entity->getId());
        $this->assertSame('Bob', $entity->getName());

        $row2 = SampleHydrator::toRow($entity);
        $this->assertSame(['id' => 10, 'name' => 'Bob'], $row2);
    }
}
