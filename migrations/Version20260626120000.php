<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260626120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Houdt per wedstrijd bij of de uitslag via de API/MCP is binnengekomen (result_via_api).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE football_match ADD result_via_api TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE football_match DROP result_via_api');
    }
}
