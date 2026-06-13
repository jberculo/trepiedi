<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260612091442 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE football_match (id INT AUTO_INCREMENT NOT NULL, kickoff_at DATETIME NOT NULL, home_score INT DEFAULT NULL, away_score INT DEFAULT NULL, finished TINYINT NOT NULL, round_id INT NOT NULL, home_team_id INT NOT NULL, away_team_id INT NOT NULL, advancing_team_id INT DEFAULT NULL, INDEX IDX_8CE33ACEA6005CA0 (round_id), INDEX IDX_8CE33ACE9C4C13F6 (home_team_id), INDEX IDX_8CE33ACE45185D02 (away_team_id), INDEX IDX_8CE33ACEAAC2324E (advancing_team_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE prediction (id INT AUTO_INCREMENT NOT NULL, home_score INT NOT NULL, away_score INT NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, football_match_id INT NOT NULL, advancing_team_id INT DEFAULT NULL, INDEX IDX_36396FC8A76ED395 (user_id), INDEX IDX_36396FC8E1DA134D (football_match_id), INDEX IDX_36396FC8AAC2324E (advancing_team_id), UNIQUE INDEX uniq_user_match (user_id, football_match_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE round (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, sort_order INT NOT NULL, weight DOUBLE PRECISION NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE team (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, code VARCHAR(3) DEFAULT NULL, UNIQUE INDEX UNIQ_C4E0A61F5E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, display_name VARCHAR(100) NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE football_match ADD CONSTRAINT FK_8CE33ACEA6005CA0 FOREIGN KEY (round_id) REFERENCES round (id)');
        $this->addSql('ALTER TABLE football_match ADD CONSTRAINT FK_8CE33ACE9C4C13F6 FOREIGN KEY (home_team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE football_match ADD CONSTRAINT FK_8CE33ACE45185D02 FOREIGN KEY (away_team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE football_match ADD CONSTRAINT FK_8CE33ACEAAC2324E FOREIGN KEY (advancing_team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE prediction ADD CONSTRAINT FK_36396FC8A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE prediction ADD CONSTRAINT FK_36396FC8E1DA134D FOREIGN KEY (football_match_id) REFERENCES football_match (id)');
        $this->addSql('ALTER TABLE prediction ADD CONSTRAINT FK_36396FC8AAC2324E FOREIGN KEY (advancing_team_id) REFERENCES team (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE football_match DROP FOREIGN KEY FK_8CE33ACEA6005CA0');
        $this->addSql('ALTER TABLE football_match DROP FOREIGN KEY FK_8CE33ACE9C4C13F6');
        $this->addSql('ALTER TABLE football_match DROP FOREIGN KEY FK_8CE33ACE45185D02');
        $this->addSql('ALTER TABLE football_match DROP FOREIGN KEY FK_8CE33ACEAAC2324E');
        $this->addSql('ALTER TABLE prediction DROP FOREIGN KEY FK_36396FC8A76ED395');
        $this->addSql('ALTER TABLE prediction DROP FOREIGN KEY FK_36396FC8E1DA134D');
        $this->addSql('ALTER TABLE prediction DROP FOREIGN KEY FK_36396FC8AAC2324E');
        $this->addSql('DROP TABLE football_match');
        $this->addSql('DROP TABLE prediction');
        $this->addSql('DROP TABLE round');
        $this->addSql('DROP TABLE team');
        $this->addSql('DROP TABLE `user`');
    }
}
