<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251210101845 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE shipping_tariff (id INT AUTO_INCREMENT NOT NULL, country_code VARCHAR(2) NOT NULL, mode_code VARCHAR(20) NOT NULL, weight_max_g INT NOT NULL, price_ht NUMERIC(10, 2) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE `order` ADD shipping_method VARCHAR(100) DEFAULT NULL, ADD shipping_cost NUMERIC(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE shipping_info ADD pudo_id VARCHAR(20) DEFAULT NULL, ADD pudo_name VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE shipping_tariff');
        $this->addSql('ALTER TABLE `order` DROP shipping_method, DROP shipping_cost');
        $this->addSql('ALTER TABLE shipping_info DROP pudo_id, DROP pudo_name');
    }
}
