<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260130100001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create ticket table ';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('ticket');
        $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $table->addColumn('title', 'string', ['length' => 255]);
        $table->addColumn('description', 'text');
        $table->addColumn('status', 'string', ['length' => 255]);
        $table->addColumn('priority', 'string', ['length' => 255]);
        $table->addColumn('created_at', 'datetime_immutable');
        $table->addColumn('updated_at', 'datetime_immutable');
        $table->addColumn('created_by_id', 'integer', ['notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addForeignKeyConstraint('user', ['created_by_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_97A0ADA3B03A8386');
        $table->addIndex(['created_by_id'], 'IDX_97A0ADA3B03A8386');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('ticket');
    }
}
