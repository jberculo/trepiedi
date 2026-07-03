<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260703124553 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Per-account beheer-melding: user.notice (tekst) en user.notice_type (info/warning/error).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD notice LONGTEXT DEFAULT NULL, ADD notice_type VARCHAR(10) DEFAULT \'info\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP notice, DROP notice_type');
    }
}
