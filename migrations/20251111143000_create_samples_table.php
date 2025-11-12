<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateSamplesTable extends AbstractMigration
{
    /**
     * Change method for creating the samples table.
     * This migration is used for testing the Sample repository.
     */
    public function change(): void
    {
        // Create samples table with auto-increment id and name column
        $this->table('samples')
            ->addColumn('name', 'string', ['limit' => 255])
            ->create();
    }
}
