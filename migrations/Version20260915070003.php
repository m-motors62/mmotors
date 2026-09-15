<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260915070003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE vehicule_photo (id INT AUTO_INCREMENT NOT NULL, filename VARCHAR(255) NOT NULL, position INT NOT NULL, vehicule_id INT NOT NULL, INDEX IDX_246E7C4A4A4A3511 (vehicule_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE vehicule_photo ADD CONSTRAINT FK_246E7C4A4A4A3511 FOREIGN KEY (vehicule_id) REFERENCES vehicule (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE vehicule_photo DROP FOREIGN KEY FK_246E7C4A4A4A3511');
        $this->addSql('DROP TABLE vehicule_photo');
    }
}
