<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260827132007 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE portfolio (id INT AUTO_INCREMENT NOT NULL, account_type VARCHAR(50) NOT NULL, account_name VARCHAR(255) NOT NULL, total_value DOUBLE PRECISION DEFAULT NULL, cash DOUBLE PRECISION DEFAULT NULL, last_updated DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE position (id INT AUTO_INCREMENT NOT NULL, ticker VARCHAR(20) NOT NULL, name VARCHAR(255) DEFAULT NULL, shares INT NOT NULL, current_price DOUBLE PRECISION NOT NULL, avg_price DOUBLE PRECISION NOT NULL, total_spent DOUBLE PRECISION NOT NULL, current_value DOUBLE PRECISION NOT NULL, profit_loss DOUBLE PRECISION NOT NULL, profit_loss_percent DOUBLE PRECISION NOT NULL, last_updated DATETIME DEFAULT NULL, portfolio_id INT NOT NULL, INDEX IDX_462CE4F5B96B5643 (portfolio_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE position ADD CONSTRAINT FK_462CE4F5B96B5643 FOREIGN KEY (portfolio_id) REFERENCES portfolio (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE position DROP FOREIGN KEY FK_462CE4F5B96B5643');
        $this->addSql('DROP TABLE portfolio');
        $this->addSql('DROP TABLE position');
        $this->addSql('DROP TABLE user');
    }
}
