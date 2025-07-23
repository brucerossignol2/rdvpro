<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250723191823 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client_professional_history DROP FOREIGN KEY FK_383D90DD19EB6921');
        $this->addSql('ALTER TABLE client_professional_history DROP FOREIGN KEY FK_383D90DDA76ED395');
        $this->addSql('ALTER TABLE client_professional_history ADD id INT AUTO_INCREMENT NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE client_professional_history ADD CONSTRAINT FK_383D90DD19EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE client_professional_history ADD CONSTRAINT FK_383D90DDA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client_professional_history MODIFY id INT NOT NULL');
        $this->addSql('ALTER TABLE client_professional_history DROP FOREIGN KEY FK_383D90DD19EB6921');
        $this->addSql('ALTER TABLE client_professional_history DROP FOREIGN KEY FK_383D90DDA76ED395');
        $this->addSql('DROP INDEX `PRIMARY` ON client_professional_history');
        $this->addSql('ALTER TABLE client_professional_history DROP id');
        $this->addSql('ALTER TABLE client_professional_history ADD CONSTRAINT FK_383D90DD19EB6921 FOREIGN KEY (client_id) REFERENCES client (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE client_professional_history ADD CONSTRAINT FK_383D90DDA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE client_professional_history ADD PRIMARY KEY (client_id, user_id)');
    }
}
