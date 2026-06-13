<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260613094829 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user ADD slug VARCHAR(130) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649989D9B62 ON user (slug)');
    }

    public function postUp(Schema $schema): void
    {
        // Bestaande gebruikers een unieke slug geven op basis van hun weergavenaam.
        $rows = $this->connection->fetchAllAssociative('SELECT id, display_name FROM `user` WHERE slug IS NULL');
        $used = [];
        foreach ($rows as $row) {
            $base = \App\Util\Slug::make((string) $row['display_name']);
            $slug = $base;
            $i = 2;
            while (in_array($slug, $used, true)) {
                $slug = $base . '-' . $i;
                ++$i;
            }
            $used[] = $slug;
            $this->connection->executeStatement(
                'UPDATE `user` SET slug = :slug WHERE id = :id',
                ['slug' => $slug, 'id' => $row['id']]
            );
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_8D93D649989D9B62 ON `user`');
        $this->addSql('ALTER TABLE `user` DROP slug');
    }
}
