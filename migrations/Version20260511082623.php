<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260511082623 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product CHANGE faq faq JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE shipping_info ADD billing_first_name VARCHAR(255) DEFAULT NULL, ADD billing_last_name VARCHAR(255) DEFAULT NULL, ADD billing_address VARCHAR(255) DEFAULT NULL, ADD billing_city VARCHAR(100) DEFAULT NULL, ADD billing_postal_code VARCHAR(20) DEFAULT NULL, ADD billing_country VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product CHANGE faq faq JSON NOT NULL');
        $this->addSql('ALTER TABLE shipping_info DROP billing_first_name, DROP billing_last_name, DROP billing_address, DROP billing_city, DROP billing_postal_code, DROP billing_country');
    }
}
