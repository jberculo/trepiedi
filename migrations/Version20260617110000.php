<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260617110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Slaat API-sleutels gehasht op via api_token_id en api_token_hash.';
    }

    public function up(Schema $schema): void
    {
        // Het sleutelformaat verandert volledig ({id}.{secret}); oude plaintext-tokens
        // kunnen niet worden omgezet. Ze vervallen — gebruikers genereren een nieuwe.
        $this->addSql('ALTER TABLE `user` ADD api_token_id VARCHAR(16) DEFAULT NULL, ADD api_token_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649EAFB6A5 ON `user` (api_token_id)');
        $this->addSql('DROP INDEX UNIQ_8D93D6497BA2F5EB ON `user`');
        $this->addSql('ALTER TABLE `user` DROP api_token');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD api_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D6497BA2F5EB ON `user` (api_token)');
        $this->addSql('DROP INDEX UNIQ_8D93D649EAFB6A5 ON `user`');
        $this->addSql('ALTER TABLE `user` DROP api_token_id, DROP api_token_hash');
    }
}
