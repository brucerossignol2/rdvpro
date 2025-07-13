<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250703131221 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE client ADD last_name VARCHAR(100) NOT NULL, ADD first_name VARCHAR(100) NOT NULL, ADD password VARCHAR(255) NOT NULL, ADD roles JSON NOT NULL, ADD is_verified TINYINT(1) NOT NULL, DROP nom, DROP prenom, CHANGE telephone telephone VARCHAR(20) NOT NULL, CHANGE rue rue VARCHAR(255) DEFAULT NULL, CHANGE code_postal code_postal VARCHAR(10) DEFAULT NULL, CHANGE ville ville VARCHAR(100) DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE client ADD nom VARCHAR(100) NOT NULL, ADD prenom VARCHAR(100) NOT NULL, DROP last_name, DROP first_name, DROP password, DROP roles, DROP is_verified, CHANGE telephone telephone VARCHAR(20) DEFAULT NULL, CHANGE rue rue VARCHAR(255) NOT NULL, CHANGE code_postal code_postal VARCHAR(10) NOT NULL, CHANGE ville ville VARCHAR(100) NOT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
    }
}
