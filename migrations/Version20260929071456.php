<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260929071456 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE motif_refus (id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(255) NOT NULL, contexte VARCHAR(20) NOT NULL, date_creation DATETIME NOT NULL, type_document VARCHAR(50) DEFAULT NULL, cree_par_id INT DEFAULT NULL, INDEX IDX_FF8A71A3FC29C013 (cree_par_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE motif_refus ADD CONSTRAINT FK_FF8A71A3FC29C013 FOREIGN KEY (cree_par_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE motif_refus DROP FOREIGN KEY FK_FF8A71A3FC29C013');
        $this->addSql('DROP TABLE motif_refus');
    }
}
