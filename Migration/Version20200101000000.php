<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Immutable Settings module migration compiled by SymSvel Tooling. */
final class Version20200101000000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $table = $schema->createTable('symsvel_settings');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('setting_key', 'string', ['length' => 190]);
        $table->addColumn('value', 'text', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['setting_key'], 'uniq_symsvel_settings_key');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('symsvel_settings');
    }
}
