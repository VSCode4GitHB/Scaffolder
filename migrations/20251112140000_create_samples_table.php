<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateSamplesTable extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     */
    public function change(): void
    {
        // Table des exemples pour tests d'intégration
        // Check if table exists to avoid "table already exists" error (idempotent)
        if (!$this->hasTable('samples')) {
            $this->table('samples', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true])
                ->addColumn('name', 'string', ['limit' => 255])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->create();
        }
    }
}
