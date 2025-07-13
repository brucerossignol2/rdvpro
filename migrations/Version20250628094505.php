<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250628094505 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE appointment (id INT AUTO_INCREMENT NOT NULL, professional_id INT NOT NULL, client_id INT NOT NULL, appointment_date DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', duration INT NOT NULL, status VARCHAR(20) NOT NULL, type VARCHAR(20) NOT NULL, notes LONGTEXT DEFAULT NULL, total_price NUMERIC(8, 2) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', confirmed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', reminder_sent TINYINT(1) NOT NULL, temp_client_first_name VARCHAR(100) DEFAULT NULL, temp_client_last_name VARCHAR(100) DEFAULT NULL, temp_client_phone VARCHAR(20) DEFAULT NULL, temp_client_email VARCHAR(100) DEFAULT NULL, temp_client_address LONGTEXT DEFAULT NULL, INDEX IDX_FE38F844DB77003 (professional_id), INDEX IDX_FE38F84419EB6921 (client_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE appointment_service (appointment_id INT NOT NULL, service_id INT NOT NULL, INDEX IDX_70BEA8FAE5B533F9 (appointment_id), INDEX IDX_70BEA8FAED5CA9E6 (service_id), PRIMARY KEY(appointment_id, service_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE business_hours (id INT AUTO_INCREMENT NOT NULL, professional_id INT NOT NULL, day_of_week INT NOT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL, is_open TINYINT(1) NOT NULL, INDEX IDX_F4FB5A32DB77003 (professional_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE client (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, prenom VARCHAR(100) NOT NULL, email VARCHAR(255) NOT NULL, telephone VARCHAR(20) DEFAULT NULL, rue VARCHAR(255) NOT NULL, code_postal VARCHAR(10) NOT NULL, ville VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_C7440455E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE service (id INT AUTO_INCREMENT NOT NULL, professional_id INT NOT NULL, name VARCHAR(150) NOT NULL, description LONGTEXT DEFAULT NULL, price NUMERIC(8, 2) NOT NULL, duration INT NOT NULL, active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_E19D9AD2DB77003 (professional_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE unavailability (id INT AUTO_INCREMENT NOT NULL, professional_id INT NOT NULL, title VARCHAR(150) NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, start_time TIME DEFAULT NULL, end_time TIME DEFAULT NULL, all_day TINYINT(1) NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_F0016D1DB77003 (professional_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, business_name VARCHAR(100) NOT NULL, business_address LONGTEXT NOT NULL, business_phone VARCHAR(20) NOT NULL, business_email VARCHAR(100) NOT NULL, booking_link VARCHAR(255) NOT NULL, home_service TINYINT(1) NOT NULL, office_service TINYINT(1) NOT NULL, video_service TINYINT(1) NOT NULL, phone_service TINYINT(1) NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE appointment ADD CONSTRAINT FK_FE38F844DB77003 FOREIGN KEY (professional_id) REFERENCES `user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE appointment ADD CONSTRAINT FK_FE38F84419EB6921 FOREIGN KEY (client_id) REFERENCES client (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE appointment_service ADD CONSTRAINT FK_70BEA8FAE5B533F9 FOREIGN KEY (appointment_id) REFERENCES appointment (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE appointment_service ADD CONSTRAINT FK_70BEA8FAED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE business_hours ADD CONSTRAINT FK_F4FB5A32DB77003 FOREIGN KEY (professional_id) REFERENCES `user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE service ADD CONSTRAINT FK_E19D9AD2DB77003 FOREIGN KEY (professional_id) REFERENCES `user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE unavailability ADD CONSTRAINT FK_F0016D1DB77003 FOREIGN KEY (professional_id) REFERENCES `user` (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE appointment DROP FOREIGN KEY FK_FE38F844DB77003
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE appointment DROP FOREIGN KEY FK_FE38F84419EB6921
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE appointment_service DROP FOREIGN KEY FK_70BEA8FAE5B533F9
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE appointment_service DROP FOREIGN KEY FK_70BEA8FAED5CA9E6
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE business_hours DROP FOREIGN KEY FK_F4FB5A32DB77003
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE service DROP FOREIGN KEY FK_E19D9AD2DB77003
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE unavailability DROP FOREIGN KEY FK_F0016D1DB77003
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE appointment
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE appointment_service
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE business_hours
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE client
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE service
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE unavailability
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE `user`
        SQL);
    }
}
