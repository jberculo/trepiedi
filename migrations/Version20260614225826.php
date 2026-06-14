<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Poules: pool-tabel + user_pool-koppeltabel + user.active_pool_id.
 * Maakt meteen de standaardpoule aan en zet alle bestaande spelers daarin.
 */
final class Version20260614225826 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Poules (mini-leagues): pool, user_pool, user.active_pool_id + standaardpoule';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE pool (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, code VARCHAR(32) NOT NULL, is_default TINYINT DEFAULT 0 NOT NULL, UNIQUE INDEX UNIQ_AF91A98677153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_pool (user_id INT NOT NULL, pool_id INT NOT NULL, INDEX IDX_D510E54FA76ED395 (user_id), INDEX IDX_D510E54F7B3406DF (pool_id), PRIMARY KEY (user_id, pool_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE user_pool ADD CONSTRAINT FK_D510E54FA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_pool ADD CONSTRAINT FK_D510E54F7B3406DF FOREIGN KEY (pool_id) REFERENCES pool (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `user` ADD active_pool_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT FK_8D93D649F878BAC1 FOREIGN KEY (active_pool_id) REFERENCES pool (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_8D93D649F878BAC1 ON `user` (active_pool_id)');

        // Standaardpoule aanmaken en alle bestaande spelers erin zetten.
        $this->addSql("INSERT INTO pool (name, code, is_default) VALUES ('Algemeen', 'algemeen', 1)");
        $this->addSql('INSERT INTO user_pool (user_id, pool_id) SELECT u.id, (SELECT id FROM pool WHERE is_default = 1 LIMIT 1) FROM `user` u');
    }

    public function down(Schema $schema): void
    {
        // Eerst de verwijzing vanuit user weg, dan pas de pool-tabellen.
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D649F878BAC1');
        $this->addSql('DROP INDEX IDX_8D93D649F878BAC1 ON `user`');
        $this->addSql('ALTER TABLE `user` DROP active_pool_id');
        $this->addSql('ALTER TABLE user_pool DROP FOREIGN KEY FK_D510E54FA76ED395');
        $this->addSql('ALTER TABLE user_pool DROP FOREIGN KEY FK_D510E54F7B3406DF');
        $this->addSql('DROP TABLE user_pool');
        $this->addSql('DROP TABLE pool');
    }
}
