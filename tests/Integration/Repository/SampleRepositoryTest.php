<?php
declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Domain\Entity\Sample;
use App\Infrastructure\Repository\SampleRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use Phinx\Config\Config;
use Phinx\Migration\Manager;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

final class SampleRepositoryTest extends TestCase
{
    private PDO $pdo;
    private SampleRepository $repository;

    protected function setUp(): void
    {
        // Create SQLite in-memory database
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // Load Phinx config but override with in-memory SQLite
        $configArray = require __DIR__ . '/../../../phinx.php';
        $configArray['environments']['test'] = [
            'adapter' => 'sqlite',
            'connection' => $this->pdo
        ];

        // Run migrations
        $config = new Config($configArray);
        $manager = new Manager($config, new StringInput(''), new NullOutput());
        $manager->migrate('test');

        $this->repository = new SampleRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        // Drop all tables (reset state)
        $this->pdo->exec('DROP TABLE IF EXISTS samples');
        $this->pdo->exec('DROP TABLE IF EXISTS phinxlog');
        unset($this->pdo);
    }

    public function testSaveAndFind(): void
    {
        $sample = new Sample(0, 'Test Entity');
        $saved = $this->repository->save($sample);

        $this->assertGreaterThan(0, $saved->getId(), 'Should have auto-incremented ID');
        
        $found = $this->repository->find($saved->getId());
        $this->assertNotNull($found);
        $this->assertSame($saved->getId(), $found->getId());
        $this->assertSame('Test Entity', $found->getName());
    }

    public function testFindAll(): void
    {
        // Create few samples
        $this->repository->save(new Sample(0, 'First'));
        $this->repository->save(new Sample(0, 'Second'));

        $all = $this->repository->findAll();
        $this->assertCount(2, $all);
        $this->assertContainsOnlyInstancesOf(Sample::class, $all);
    }

    public function testDelete(): void
    {
        $sample = new Sample(0, 'To Delete');
        $saved = $this->repository->save($sample);
        
        $this->repository->delete($saved);
        
        $found = $this->repository->find($saved->getId());
        $this->assertNull($found);
    }

    public function testFindBy(): void
    {
        $this->repository->save(new Sample(0, 'Alice'));
        $this->repository->save(new Sample(0, 'Bob'));
        $this->repository->save(new Sample(0, 'Alice')); // duplicate name

        $found = $this->repository->findBy(['name' => 'Alice']);
        $this->assertCount(2, $found);
        foreach ($found as $entity) {
            $this->assertSame('Alice', $entity->getName());
        }
    }

    public function testCount(): void
    {
        $this->repository->save(new Sample(0, 'One'));
        $this->repository->save(new Sample(0, 'Two'));

        $this->assertSame(2, $this->repository->count());
        $this->assertSame(1, $this->repository->count(['name' => 'One']));
    }
}