<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entity\Sample;
use PDO;

final class SampleRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function save(Sample $sample): Sample
    {
        if ($sample->getId() === 0) {
            $stmt = $this->pdo->prepare('INSERT INTO samples (name) VALUES (:name)');
            $stmt->execute(['name' => $sample->getName()]);
            return new Sample((int)$this->pdo->lastInsertId(), $sample->getName());
        } else {
            $stmt = $this->pdo->prepare('UPDATE samples SET name = :name WHERE id = :id');
            $stmt->execute([
                'id' => $sample->getId(),
                'name' => $sample->getName()
            ]);
            return $sample;
        }
    }

    public function find(int $id): ?Sample
    {
        $stmt = $this->pdo->prepare('SELECT * FROM samples WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        if (!isset($row['id']) || !isset($row['name'])) {
            throw new \RuntimeException('Missing required fields in database row');
        }
        return new Sample((int)$row['id'], (string)$row['name']);
    }

    /**
     * @return array<int, Sample>
     */
    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM samples');
        if ($stmt === false) {
            return [];
        }
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === false) {
            return [];
        }

        return array_map(fn(array $row) => new Sample((int)$row['id'], (string)$row['name']), $rows);
    }

    public function delete(Sample $sample): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM samples WHERE id = :id');
        $stmt->execute(['id' => $sample->getId()]);
    }

    /**
     * @param array<string, string|int> $criteria
     * @return array<int, Sample>
     */
    public function findBy(array $criteria): array
    {
        $conditions = [];
        $params = [];
        foreach ($criteria as $field => $value) {
            $conditions[] = "$field = :$field";
            $params[$field] = $value;
        }

        $sql = 'SELECT * FROM samples';
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
/** @var array<int, array<string, mixed>>|false $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === false) {
            return [];
        }

        return array_map(fn(array $row) => new Sample((int)$row['id'], (string)$row['name']), $rows);
    }

    /**
     * @param array<string, string|int> $criteria
     */
    public function count(array $criteria = []): int
    {
        $conditions = [];
        $params = [];
        foreach ($criteria as $field => $value) {
            $conditions[] = "$field = :$field";
            $params[$field] = $value;
        }

        $sql = 'SELECT COUNT(*) FROM samples';
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }
}
