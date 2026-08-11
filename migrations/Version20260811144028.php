<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260811144028 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE client (id INT AUTO_INCREMENT NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, contact_id INT NOT NULL, UNIQUE INDEX UNIQ_C7440455E7A1254A (contact_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE contact (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, prenom VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, telephone VARCHAR(20) DEFAULT NULL, adresse VARCHAR(255) DEFAULT NULL, code_postal VARCHAR(10) DEFAULT NULL, ville VARCHAR(100) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE document (id INT AUTO_INCREMENT NOT NULL, type_document VARCHAR(50) NOT NULL, nom_fichier VARCHAR(255) NOT NULL, date_depot DATETIME NOT NULL, statut VARCHAR(20) NOT NULL, motif_rejet LONGTEXT DEFAULT NULL, dossier_id INT NOT NULL, INDEX IDX_D8698A76611C0C56 (dossier_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE dossier (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, statut VARCHAR(20) NOT NULL, date_creation DATETIME NOT NULL, motif_refus LONGTEXT DEFAULT NULL, client_id INT NOT NULL, vehicule_id INT NOT NULL, INDEX IDX_3D48E03719EB6921 (client_id), INDEX IDX_3D48E0374A4A3511 (vehicule_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, is_active TINYINT NOT NULL, deactivated_at DATETIME DEFAULT NULL, contact_id INT NOT NULL, deactivated_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D649E7A1254A (contact_id), INDEX IDX_8D93D649BDC76BAA (deactivated_by_id), UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE vehicule (id INT AUTO_INCREMENT NOT NULL, marque VARCHAR(100) NOT NULL, modele VARCHAR(100) NOT NULL, motorisation VARCHAR(100) DEFAULT NULL, kilometrage INT NOT NULL, prix DOUBLE PRECISION NOT NULL, statut VARCHAR(20) NOT NULL, etat LONGTEXT DEFAULT NULL, assurance TINYINT NOT NULL, assistance TINYINT NOT NULL, entretien TINYINT NOT NULL, controle_technique TINYINT NOT NULL, immatriculation VARCHAR(15) NOT NULL, UNIQUE INDEX UNIQ_292FFF1DBE73422E (immatriculation), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT FK_C7440455E7A1254A FOREIGN KEY (contact_id) REFERENCES contact (id)');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76611C0C56 FOREIGN KEY (dossier_id) REFERENCES dossier (id)');
        $this->addSql('ALTER TABLE dossier ADD CONSTRAINT FK_3D48E03719EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE dossier ADD CONSTRAINT FK_3D48E0374A4A3511 FOREIGN KEY (vehicule_id) REFERENCES vehicule (id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649E7A1254A FOREIGN KEY (contact_id) REFERENCES contact (id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649BDC76BAA FOREIGN KEY (deactivated_by_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client DROP FOREIGN KEY FK_C7440455E7A1254A');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76611C0C56');
        $this->addSql('ALTER TABLE dossier DROP FOREIGN KEY FK_3D48E03719EB6921');
        $this->addSql('ALTER TABLE dossier DROP FOREIGN KEY FK_3D48E0374A4A3511');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649E7A1254A');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649BDC76BAA');
        $this->addSql('DROP TABLE client');
        $this->addSql('DROP TABLE contact');
        $this->addSql('DROP TABLE document');
        $this->addSql('DROP TABLE dossier');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE vehicule');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
