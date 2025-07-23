<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250723164731 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE client_professional_history (client_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_383D90DD19EB6921 (client_id), INDEX IDX_383D90DDA76ED395 (user_id), PRIMARY KEY(client_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE client_professional_history ADD CONSTRAINT FK_383D90DD19EB6921 FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE client_professional_history ADD CONSTRAINT FK_383D90DDA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client_professional_history DROP FOREIGN KEY FK_383D90DD19EB6921');
        $this->addSql('ALTER TABLE client_professional_history DROP FOREIGN KEY FK_383D90DDA76ED395');
        $this->addSql('DROP TABLE client_professional_history');
    }
}
