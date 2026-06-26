<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260626130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Hernoemt result_via_api naar result_via_external_api (dekt ook MCP).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE football_match CHANGE result_via_api result_via_external_api TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE football_match CHANGE result_via_external_api result_via_api TINYINT(1) DEFAULT 0 NOT NULL');
    }
}
