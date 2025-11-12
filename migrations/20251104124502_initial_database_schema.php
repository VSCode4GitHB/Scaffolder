<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class InitialDatabaseSchema extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        // Table des projets
        $this->table('projects', [
            'id' => false,
            'primary_key' => ['project_id']
        ])
        ->addColumn('project_id', 'uuid')
        ->addColumn('name', 'string', ['limit' => 100])
        ->addColumn('description', 'text', ['null' => true])
        ->addColumn('base_path', 'string', ['limit' => 255])
        ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
        ->addColumn('updated_at', 'timestamp', [
            'default' => 'CURRENT_TIMESTAMP',
            'update' => 'CURRENT_TIMESTAMP'
        ])
        ->addIndex(['name'], ['unique' => true])
        ->create();

        // Table des modèles/templates
        $this->table('templates', [
            'id' => false,
            'primary_key' => ['template_id']
        ])
        ->addColumn('template_id', 'uuid')
        ->addColumn('name', 'string', ['limit' => 100])
        ->addColumn('type', 'string', ['limit' => 50])
        ->addColumn('description', 'text', ['null' => true])
        ->addColumn('content', 'text')
        ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
        ->addColumn('updated_at', 'timestamp', [
            'default' => 'CURRENT_TIMESTAMP',
            'update' => 'CURRENT_TIMESTAMP'
        ])
        ->addIndex(['name', 'type'], ['unique' => true])
        ->create();

        // Table des composants générés
        $this->table('components', [
            'id' => false,
            'primary_key' => ['component_id']
        ])
        ->addColumn('component_id', 'uuid')
        ->addColumn('project_id', 'uuid')
        ->addColumn('template_id', 'uuid')
        ->addColumn('name', 'string', ['limit' => 100])
        ->addColumn('path', 'string', ['limit' => 255])
        ->addColumn('metadata', 'json', ['null' => true])
        ->addColumn('generated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
        ->addColumn('status', 'string', [
            'limit' => 20,
            'default' => 'active',
            'comment' => 'Status du composant: active, archived, deleted'
        ])
        ->addForeignKey('project_id', 'projects', 'project_id', [
            'delete' => 'CASCADE',
            'update' => 'NO_ACTION'
        ])
        ->addForeignKey('template_id', 'templates', 'template_id', [
            'delete' => 'RESTRICT',
            'update' => 'NO_ACTION'
        ])
        ->addIndex(['project_id', 'path'], ['unique' => true])
        ->create();
    }
}
