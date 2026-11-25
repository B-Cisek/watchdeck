<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261125001630 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'create services and service_groups tables';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE "service_groups" (id UUID NOT NULL, name VARCHAR(100) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE "services" (id UUID NOT NULL, name VARCHAR(100) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, group_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_7332E169FE54D947 ON "services" (group_id)');
        $this->addSql('ALTER TABLE "services" ADD CONSTRAINT FK_7332E169FE54D947 FOREIGN KEY (group_id) REFERENCES "service_groups" (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "services" DROP CONSTRAINT FK_7332E169FE54D947');
        $this->addSql('DROP TABLE "service_groups"');
        $this->addSql('DROP TABLE "services"');
    }
}
