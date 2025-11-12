<?php
declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Entity\Sample;
use PHPUnit\Framework\TestCase;

final class SampleTest extends TestCase
{
    public function testFromArrayAndToArray(): void
    {
        $data = ['id' => 42, 'name' => 'Alice'];
        $s = Sample::fromArray($data);

        $this->assertSame(42, $s->getId());
        $this->assertSame('Alice', $s->getName());
        $this->assertSame($data, $s->toArray());
    }

    public function testDefaultsWhenMissing(): void
    {
        $s = Sample::fromArray([]);
        $this->assertSame(0, $s->getId());
        $this->assertSame('', $s->getName());
    }
}
