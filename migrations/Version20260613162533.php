<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260613162533 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ploegnamen als vrije tekst op de wedstrijd + doorgaande kant (home/away); team-entiteit verwijderd.';
    }

    public function up(Schema $schema): void
    {
        // Eerst de foreign keys + kolommen die naar team verwijzen weg, daarna pas de team-tabel.
        $this->addSql('ALTER TABLE football_match DROP FOREIGN KEY `FK_8CE33ACE45185D02`');
        $this->addSql('ALTER TABLE football_match DROP FOREIGN KEY `FK_8CE33ACE9C4C13F6`');
        $this->addSql('ALTER TABLE football_match DROP FOREIGN KEY `FK_8CE33ACEAAC2324E`');
        $this->addSql('DROP INDEX IDX_8CE33ACE45185D02 ON football_match');
        $this->addSql('DROP INDEX IDX_8CE33ACEAAC2324E ON football_match');
        $this->addSql('DROP INDEX IDX_8CE33ACE9C4C13F6 ON football_match');
        $this->addSql('ALTER TABLE football_match ADD home_team VARCHAR(100) NOT NULL, ADD away_team VARCHAR(100) NOT NULL, ADD advancing_side VARCHAR(4) DEFAULT NULL, DROP home_team_id, DROP away_team_id, DROP advancing_team_id');
        $this->addSql('ALTER TABLE prediction DROP FOREIGN KEY `FK_36396FC8AAC2324E`');
        $this->addSql('DROP INDEX IDX_36396FC8AAC2324E ON prediction');
        $this->addSql('ALTER TABLE prediction ADD advancing_side VARCHAR(4) DEFAULT NULL, DROP advancing_team_id');
        $this->addSql('DROP TABLE team');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE team (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, code VARCHAR(3) DEFAULT NULL, UNIQUE INDEX UNIQ_C4E0A61F5E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE football_match ADD home_team_id INT NOT NULL, ADD away_team_id INT NOT NULL, ADD advancing_team_id INT DEFAULT NULL, DROP home_team, DROP away_team, DROP advancing_side');
        $this->addSql('ALTER TABLE football_match ADD CONSTRAINT `FK_8CE33ACE45185D02` FOREIGN KEY (away_team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE football_match ADD CONSTRAINT `FK_8CE33ACE9C4C13F6` FOREIGN KEY (home_team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE football_match ADD CONSTRAINT `FK_8CE33ACEAAC2324E` FOREIGN KEY (advancing_team_id) REFERENCES team (id)');
        $this->addSql('CREATE INDEX IDX_8CE33ACE45185D02 ON football_match (away_team_id)');
        $this->addSql('CREATE INDEX IDX_8CE33ACEAAC2324E ON football_match (advancing_team_id)');
        $this->addSql('CREATE INDEX IDX_8CE33ACE9C4C13F6 ON football_match (home_team_id)');
        $this->addSql('ALTER TABLE prediction ADD advancing_team_id INT DEFAULT NULL, DROP advancing_side');
        $this->addSql('ALTER TABLE prediction ADD CONSTRAINT `FK_36396FC8AAC2324E` FOREIGN KEY (advancing_team_id) REFERENCES team (id)');
        $this->addSql('CREATE INDEX IDX_36396FC8AAC2324E ON prediction (advancing_team_id)');
    }
}
