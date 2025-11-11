<?php

declare(strict_types=1);

namespace App\Domain\Entity;

final class Sample implements SampleInterface
{
    private int $id;
    private string $name;
    public function __construct(int $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name];
    }

    /**
     * @param array<string, int|string> $data
     */
    public static function fromArray(array $data): self
    {
        return new self((int)($data['id'] ?? 0), (string)($data['name'] ?? ''));
    }
}
