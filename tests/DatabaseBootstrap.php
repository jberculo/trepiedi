<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class DatabaseBootstrap
{
    public static function resetSchema(EntityManagerInterface $em): void
    {
        $connection = $em->getConnection();
        $schemaManager = $connection->createSchemaManager();

        self::disableForeignKeyChecks($connection);
        try {
            foreach ($schemaManager->listTableNames() as $tableName) {
                $schemaManager->dropTable($tableName);
            }
        } finally {
            self::enableForeignKeyChecks($connection);
        }

        $metadata = $em->getMetadataFactory()->getAllMetadata();
        if ($metadata !== []) {
            (new SchemaTool($em))->createSchema($metadata);
        }
    }

    public static function seedFixtures(EntityManagerInterface $em, ContainerInterface $container): void
    {
        $loader = new Loader();
        $loader->addFixture(new AppFixtures($container->get(UserPasswordHasherInterface::class)));
        (new ORMExecutor($em, new ORMPurger()))->execute($loader->getFixtures());
    }

    private static function disableForeignKeyChecks(Connection $connection): void
    {
        if ($connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        }
    }

    private static function enableForeignKeyChecks(Connection $connection): void
    {
        if ($connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
