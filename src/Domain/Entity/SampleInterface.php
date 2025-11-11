<?php

declare(strict_types=1);

namespace App\Domain\Entity;

interface SampleInterface
{
    public function getId(): int;
    public function getName(): string;
/**
     * @return array<string, int|string>
     */
    public function toArray(): array;
/**
     * @param array<string, int|string> $data
     */
    public static function fromArray(array $data): self;
}
