<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260915050121 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE action_log (id INT AUTO_INCREMENT NOT NULL, action_type VARCHAR(50) NOT NULL, target_type VARCHAR(50) DEFAULT NULL, target_id INT DEFAULT NULL, description LONGTEXT NOT NULL, created_at DATETIME NOT NULL, actor_id INT DEFAULT NULL, INDEX IDX_B2C5F68510DAF24A (actor_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE action_log ADD CONSTRAINT FK_B2C5F68510DAF24A FOREIGN KEY (actor_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE action_log DROP FOREIGN KEY FK_B2C5F68510DAF24A');
        $this->addSql('DROP TABLE action_log');
    }
}
