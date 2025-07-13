<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250701093209 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE client CHANGE created_at created_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user ADD created_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE business_name business_name VARCHAR(255) DEFAULT NULL, CHANGE business_address business_address VARCHAR(255) DEFAULT NULL, CHANGE business_phone business_phone VARCHAR(20) DEFAULT NULL, CHANGE business_email business_email VARCHAR(180) DEFAULT NULL, CHANGE booking_link booking_link VARCHAR(255) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE client CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE `user` DROP created_at, CHANGE business_name business_name VARCHAR(100) NOT NULL, CHANGE business_address business_address LONGTEXT NOT NULL, CHANGE business_phone business_phone VARCHAR(20) NOT NULL, CHANGE business_email business_email VARCHAR(100) NOT NULL, CHANGE booking_link booking_link VARCHAR(255) NOT NULL
        SQL);
    }
}
