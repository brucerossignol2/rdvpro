<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250702103621 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE service ADD appointment_type VARCHAR(50) NOT NULL, DROP home_service, DROP office_service, DROP video_service, DROP phone_service
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE service ADD home_service TINYINT(1) NOT NULL, ADD office_service TINYINT(1) NOT NULL, ADD video_service TINYINT(1) NOT NULL, ADD phone_service TINYINT(1) NOT NULL, DROP appointment_type
        SQL);
    }
}
